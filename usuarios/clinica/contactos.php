<?php
declare(strict_types=1);

/**
 * contactos.php — Panel Clínica (PILLAR Locums)
 * - Lista contactos
 * - Crear / Editar / Eliminar
 * - Búsqueda simple (?q=...)
 *
 * Requiere: $pdo (PDO), $clinicId (int), $uiErrors (array), helper h()
 */

if (!function_exists('h')) {
  function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
}

$tab = (string)($_GET['tab'] ?? 'contactos');
$q   = trim((string)($_GET['q'] ?? ''));

$editing = null;
$editId  = (int)($_GET['edit'] ?? 0);

$contacts = [];
$tableMissing = false;

try {
  if (!isset($pdo) || !($pdo instanceof PDO)) {
    throw new RuntimeException("PDO no disponible.");
  }

  // Verifica si existe tabla clinic_contacts
  $stT = $pdo->prepare("SHOW TABLES LIKE 'clinic_contacts'");
  $stT->execute();
  $has = (bool)$stT->fetchColumn();

  if (!$has) {
    $tableMissing = true;
  } else {
    // Detecta columnas existentes (robusto)
    $cols = [];
    $stC = $pdo->query("SHOW COLUMNS FROM clinic_contacts");
    foreach (($stC->fetchAll(PDO::FETCH_ASSOC) ?: []) as $r) $cols[] = (string)$r['Field'];

    // Requeridas mínimas
    $required = ['id','clinic_user_id','name'];
    foreach ($required as $req) {
      if (!in_array($req, $cols, true)) {
        throw new RuntimeException("clinic_contacts no tiene la columna requerida: {$req}");
      }
    }

    $hasEmail     = in_array('email', $cols, true);
    $hasPhone     = in_array('phone', $cols, true);
    $hasRole      = in_array('role', $cols, true);
    $hasTags      = in_array('tags', $cols, true);
    $hasNotes     = in_array('notes', $cols, true);
    $hasCreatedAt = in_array('created_at', $cols, true);

    // =========================
    // POST actions (solo contactos)
    // =========================
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tab === 'contactos') {
      $action = (string)($_POST['action'] ?? '');

      if (in_array($action, ['create_contact','update_contact','delete_contact'], true)) {
        $contactId = (int)($_POST['contact_id'] ?? 0);

        $name  = trim((string)($_POST['name'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $phone = trim((string)($_POST['phone'] ?? ''));
        $role  = trim((string)($_POST['role'] ?? ''));
        $tags  = trim((string)($_POST['tags'] ?? ''));
        $notes = trim((string)($_POST['notes'] ?? ''));

        $errs = [];

        if ($action !== 'delete_contact') {
          if ($name === '') $errs[] = "Nombre obligatorio.";

          if ($hasEmail && $email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errs[] = "Email inválido.";
          }
        } else {
          if ($contactId <= 0) $errs[] = "Contacto inválido.";
        }

        // Ownership check para update/delete
        if (!$errs && in_array($action, ['update_contact','delete_contact'], true)) {
          if ($contactId <= 0) $errs[] = "Contacto inválido.";
          else {
            $stOwn = $pdo->prepare("SELECT id FROM clinic_contacts WHERE id=? AND clinic_user_id=? LIMIT 1");
            $stOwn->execute([$contactId, $clinicId]);
            if (!$stOwn->fetchColumn()) $errs[] = "No autorizado.";
          }
        }

        if ($errs) {
          foreach ($errs as $er) $uiErrors[] = $er;
        } else {

          if ($action === 'create_contact') {
            $fields = [
              'clinic_user_id' => $clinicId,
              'name'           => $name,
            ];
            if ($hasEmail) $fields['email'] = ($email !== '' ? $email : null);
            if ($hasPhone) $fields['phone'] = ($phone !== '' ? $phone : null);
            if ($hasRole)  $fields['role']  = ($role !== '' ? $role : null);
            if ($hasTags)  $fields['tags']  = ($tags !== '' ? $tags : null);
            if ($hasNotes) $fields['notes'] = ($notes !== '' ? $notes : null);

            $colsSql = implode(',', array_map(fn($c) => "`{$c}`", array_keys($fields)));
            $valsSql = implode(',', array_fill(0, count($fields), '?'));

            $sql = "INSERT INTO clinic_contacts ({$colsSql}) VALUES ({$valsSql})";
            $st = $pdo->prepare($sql);
            $st->execute(array_values($fields));

            header("Location: ?tab=contactos");
            exit;
          }

          if ($action === 'update_contact') {
            $sets = ["name=?"];
            $vals = [$name];

            if ($hasEmail) { $sets[] = "email=?"; $vals[] = ($email !== '' ? $email : null); }
            if ($hasPhone) { $sets[] = "phone=?"; $vals[] = ($phone !== '' ? $phone : null); }
            if ($hasRole)  { $sets[] = "role=?";  $vals[] = ($role !== '' ? $role : null); }
            if ($hasTags)  { $sets[] = "tags=?";  $vals[] = ($tags !== '' ? $tags : null); }
            if ($hasNotes) { $sets[] = "notes=?"; $vals[] = ($notes !== '' ? $notes : null); }

            $vals[] = $contactId;
            $vals[] = $clinicId;

            $sql = "UPDATE clinic_contacts SET ".implode(',', $sets)." WHERE id=? AND clinic_user_id=? LIMIT 1";
            $st = $pdo->prepare($sql);
            $st->execute($vals);

            header("Location: ?tab=contactos");
            exit;
          }

          if ($action === 'delete_contact') {
            $st = $pdo->prepare("DELETE FROM clinic_contacts WHERE id=? AND clinic_user_id=? LIMIT 1");
            $st->execute([$contactId, $clinicId]);

            header("Location: ?tab=contactos");
            exit;
          }
        }
      }
    }

    // =========================
    // Load edit contact (GET edit)
    // =========================
    if ($editId > 0) {
      $stE = $pdo->prepare("SELECT * FROM clinic_contacts WHERE id=? AND clinic_user_id=? LIMIT 1");
      $stE->execute([$editId, $clinicId]);
      $editing = $stE->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    // =========================
    // Load contacts list (with search)
    // =========================
    $where = "clinic_user_id=?";
    $params = [$clinicId];

    if ($q !== '') {
      $like = "%{$q}%";
      $parts = ["name LIKE ?"];
      $params[] = $like;

      if ($hasEmail) { $parts[] = "email LIKE ?"; $params[] = $like; }
      if ($hasPhone) { $parts[] = "phone LIKE ?"; $params[] = $like; }
      if ($hasRole)  { $parts[] = "role LIKE ?";  $params[] = $like; }
      if ($hasTags)  { $parts[] = "tags LIKE ?";  $params[] = $like; }

      $where .= " AND (".implode(" OR ", $parts).")";
    }

    $order = $hasCreatedAt ? "ORDER BY created_at DESC" : "ORDER BY id DESC";
    $sql = "SELECT * FROM clinic_contacts WHERE {$where} {$order}";
    $stL = $pdo->prepare($sql);
    $stL->execute($params);
    $contacts = $stL->fetchAll(PDO::FETCH_ASSOC) ?: [];
  }

} catch (Throwable $e) {
  error_log("CONTACTOS.PHP ERROR: ".$e->getMessage());
  $uiErrors[] = "No se pudo cargar la sección de contactos.";
}

?>

<div class="sectionTitle">
  <h2>Contactos</h2>

  <form method="get" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
    <input type="hidden" name="tab" value="contactos">
    <input class="input" name="q" value="<?= h($q) ?>" placeholder="Buscar por nombre, email, teléfono, etiquetas..." style="min-width:260px;">
    <button class="btn btnGhost" type="submit">Buscar</button>
    <?php if ($q !== ''): ?>
      <a class="btn btnGhost" href="?tab=contactos">Limpiar</a>
    <?php endif; ?>
  </form>
</div>

<?php if (!empty($tableMissing)): ?>
  <div class="note">
    <strong>Contactos aún no está habilitado en base de datos.</strong><br>
    Falta la tabla <code>clinic_contacts</code>. Crea la tabla con este SQL (MariaDB/MySQL):
    <pre style="white-space:pre-wrap; margin:10px 0 0; padding:10px; border-radius:12px; border:1px solid rgba(255,255,255,.10); background:rgba(0,0,0,.16); color:rgba(246,241,231,.88);">
CREATE TABLE `clinic_contacts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `clinic_user_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(160) NOT NULL,
  `email` VARCHAR(190) NULL,
  `phone` VARCHAR(60) NULL,
  `role` VARCHAR(60) NULL,
  `tags` VARCHAR(220) NULL,
  `notes` VARCHAR(500) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_clinic_contacts_clinic` (`clinic_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    </pre>
  </div>
  <?php return; ?>
<?php endif; ?>

<div class="grid2" style="grid-template-columns: 1.05fr .95fr;">
  <div class="panel">
    <h3 style="margin:0 0 10px;">Lista de contactos</h3>

    <table class="table">
      <thead>
        <tr>
          <th>Contacto</th>
          <th>Datos</th>
          <th style="width:180px;">Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$contacts): ?>
          <tr>
            <td colspan="3" class="muted">No hay contactos guardados.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($contacts as $c): ?>
            <?php
              $cid   = (int)($c['id'] ?? 0);
              $name  = (string)($c['name'] ?? '');
              $email = (string)($c['email'] ?? '');
              $phone = (string)($c['phone'] ?? '');
              $role  = (string)($c['role'] ?? '');
              $tags  = (string)($c['tags'] ?? '');
              $notes = (string)($c['notes'] ?? '');
            ?>
            <tr>
              <td>
                <strong><?= h($name) ?></strong>
                <?php if (!empty($role)): ?>
                  <div class="muted" style="margin-top:6px;"><?= h($role) ?></div>
                <?php endif; ?>
              </td>

              <td>
                <div class="muted" style="line-height:1.35;">
                  <?= $email !== '' ? '<div><strong class="muted">Email:</strong> '.h($email).'</div>' : '' ?>
                  <?= $phone !== '' ? '<div><strong class="muted">Tel:</strong> '.h($phone).'</div>' : '' ?>
                  <?= $tags !== '' ? '<div><strong class="muted">Tags:</strong> '.h($tags).'</div>' : '' ?>
                  <?= $notes !== '' ? '<div style="margin-top:6px;">'.h($notes).'</div>' : '' ?>
                </div>
              </td>

              <td>
                <div class="actions">
                  <a class="iconBtn" href="?tab=contactos&edit=<?= (int)$cid ?>">Editar</a>
                  <form method="post" style="display:inline;" onsubmit="return confirm('¿Eliminar este contacto?');">
                    <input type="hidden" name="action" value="delete_contact">
                    <input type="hidden" name="contact_id" value="<?= (int)$cid ?>">
                    <button class="btnDanger" type="submit">Eliminar</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>

    <div class="note" style="margin-top:12px;">
      Usa contactos para guardar médicos, agencias o interlocutores clave y mantener trazabilidad del proceso (emails, teléfonos, notas y etiquetas).
    </div>
  </div>

  <div class="panel">
    <h3 style="margin:0 0 10px;"><?= $editing ? 'Editar contacto' : 'Nuevo contacto' ?></h3>

    <form method="post">
      <input type="hidden" name="action" value="<?= $editing ? 'update_contact' : 'create_contact' ?>">
      <input type="hidden" name="contact_id" value="<?= (int)($editing['id'] ?? 0) ?>">

      <div class="field">
        <label>Nombre</label>
        <input class="input" name="name" value="<?= h((string)($editing['name'] ?? '')) ?>" required>
      </div>

      <div class="row">
        <div class="field">
          <label>Email (opcional)</label>
          <input class="input" name="email" value="<?= h((string)($editing['email'] ?? '')) ?>" placeholder="ej. contacto@correo.com">
        </div>
        <div class="field">
          <label>Teléfono (opcional)</label>
          <input class="input" name="phone" value="<?= h((string)($editing['phone'] ?? '')) ?>" placeholder="+34 ...">
        </div>
      </div>

      <div class="row">
        <div class="field">
          <label>Rol (opcional)</label>
          <input class="input" name="role" value="<?= h((string)($editing['role'] ?? '')) ?>" placeholder="ej. Médico, Agencia, RRHH...">
        </div>
        <div class="field">
          <label>Etiquetas (opcional)</label>
          <input class="input" name="tags" value="<?= h((string)($editing['tags'] ?? '')) ?>" placeholder="ej. pediatría, guardias, urgente">
        </div>
      </div>

      <div class="field">
        <label>Notas (opcional)</label>
        <input class="input" name="notes" value="<?= h((string)($editing['notes'] ?? '')) ?>" placeholder="Observaciones internas...">
      </div>

      <div class="btnRow">
        <?php if ($editing): ?>
          <a class="btn btnGhost" href="?tab=contactos">Cancelar edición</a>
        <?php else: ?>
          <button class="btn btnGhost" type="reset">Limpiar</button>
        <?php endif; ?>
        <button class="btn btnGold" type="submit">Guardar</button>
      </div>
    </form>
  </div>
</div>

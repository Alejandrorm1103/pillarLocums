<?php
declare(strict_types=1);

/**
 * contactos.php — Panel Hospital (PILLAR Locums)
 * - Lista contactos
 * - Crear / Editar / Eliminar
 * - Búsqueda simple (?q=...)
 *
 * Requiere: $pdo (PDO), $hospitalId (int), $uiErrors (array), helper h()
 */

if (!function_exists('h')) {
  function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
  }
}

// =====================
// I18N LOCAL PARA CONTACTOS
// =====================
$plLang = (string)($plLang ?? $_GET['lang'] ?? $_SESSION['lang'] ?? 'es');
$plLang = ($plLang === 'en') ? 'en' : 'es';
$_SESSION['lang'] = $plLang;

$PILLAR_HOSP_CONTACTS_DICT = [
  'es' => [
    'contacts' => 'Contactos',
    'search_placeholder' => 'Buscar por nombre, email, teléfono, etiquetas...',
    'search' => 'Buscar',
    'clear' => 'Limpiar',
    'contacts_not_enabled' => 'Contactos aún no está habilitado en base de datos.',
    'missing_table_text' => 'Falta la tabla',
    'create_table_sql' => 'Crea la tabla con este SQL (MariaDB/MySQL):',
    'contacts_list' => 'Lista de contactos',
    'contact' => 'Contacto',
    'data' => 'Datos',
    'actions' => 'Acciones',
    'no_contacts_saved' => 'No hay contactos guardados.',
    'email_label' => 'Email:',
    'phone_label' => 'Tel:',
    'tags_label' => 'Tags:',
    'edit' => 'Editar',
    'delete' => 'Eliminar',
    'delete_contact_confirm' => '¿Eliminar este contacto?',
    'contacts_help' => 'Usa contactos para guardar médicos, agencias o interlocutores clave y mantener trazabilidad del proceso (emails, teléfonos, notas y etiquetas).',
    'edit_contact' => 'Editar contacto',
    'new_contact' => 'Nuevo contacto',
    'name' => 'Nombre',
    'email_optional' => 'Email (opcional)',
    'phone_optional' => 'Teléfono (opcional)',
    'role_optional' => 'Rol (opcional)',
    'tags_optional' => 'Etiquetas (opcional)',
    'notes_optional' => 'Notas (opcional)',
    'email_placeholder' => 'ej. contacto@correo.com',
    'phone_placeholder' => '+34 ...',
    'role_placeholder' => 'ej. Médico, Agencia, RRHH...',
    'tags_placeholder' => 'ej. pediatría, guardias, urgente',
    'notes_placeholder' => 'Observaciones internas...',
    'cancel_edit' => 'Cancelar edición',
    'save' => 'Guardar',
    'required_name' => 'Nombre obligatorio.',
    'invalid_email' => 'Email inválido.',
    'invalid_contact' => 'Contacto inválido.',
    'unauthorized' => 'No autorizado.',
    'contacts_load_error' => 'No se pudo cargar la sección de contactos.',
    'pdo_not_available' => 'PDO no disponible.',
    'invalid_hospital_id' => 'hospitalId inválido.',
    'missing_required_column' => 'no tiene la columna requerida',
    'missing_owner_column' => 'no tiene columna owner (hospital_user_id/clinic_user_id).',
  ],
  'en' => [
    'contacts' => 'Contacts',
    'search_placeholder' => 'Search by name, email, phone, tags...',
    'search' => 'Search',
    'clear' => 'Clear',
    'contacts_not_enabled' => 'Contacts is not enabled in the database yet.',
    'missing_table_text' => 'Missing table',
    'create_table_sql' => 'Create the table with this SQL (MariaDB/MySQL):',
    'contacts_list' => 'Contacts list',
    'contact' => 'Contact',
    'data' => 'Data',
    'actions' => 'Actions',
    'no_contacts_saved' => 'No saved contacts.',
    'email_label' => 'Email:',
    'phone_label' => 'Phone:',
    'tags_label' => 'Tags:',
    'edit' => 'Edit',
    'delete' => 'Delete',
    'delete_contact_confirm' => 'Delete this contact?',
    'contacts_help' => 'Use contacts to save doctors, agencies, or key counterparts and keep process traceability (emails, phones, notes, and tags).',
    'edit_contact' => 'Edit contact',
    'new_contact' => 'New contact',
    'name' => 'Name',
    'email_optional' => 'Email (optional)',
    'phone_optional' => 'Phone (optional)',
    'role_optional' => 'Role (optional)',
    'tags_optional' => 'Tags (optional)',
    'notes_optional' => 'Notes (optional)',
    'email_placeholder' => 'e.g. contact@mail.com',
    'phone_placeholder' => '+34 ...',
    'role_placeholder' => 'e.g. Doctor, Agency, HR...',
    'tags_placeholder' => 'e.g. pediatrics, shifts, urgent',
    'notes_placeholder' => 'Internal notes...',
    'cancel_edit' => 'Cancel editing',
    'save' => 'Save',
    'required_name' => 'Name is required.',
    'invalid_email' => 'Invalid email.',
    'invalid_contact' => 'Invalid contact.',
    'unauthorized' => 'Unauthorized.',
    'contacts_load_error' => 'Could not load the contacts section.',
    'pdo_not_available' => 'PDO not available.',
    'invalid_hospital_id' => 'Invalid hospitalId.',
    'missing_required_column' => 'does not have the required column',
    'missing_owner_column' => 'does not have an owner column (hospital_user_id/clinic_user_id).',
  ],
];

// Si existe el diccionario global del index, lo extendemos
if (isset($plDict) && is_array($plDict)) {
  $plDict['es'] = array_merge($plDict['es'] ?? [], $PILLAR_HOSP_CONTACTS_DICT['es']);
  $plDict['en'] = array_merge($plDict['en'] ?? [], $PILLAR_HOSP_CONTACTS_DICT['en']);
}

// Fallback local
if (!function_exists('pl_t')) {
  function pl_t(string $key, ?string $fallback = null): string {
    global $plLang, $PILLAR_HOSP_CONTACTS_DICT;
    return $PILLAR_HOSP_CONTACTS_DICT[$plLang][$key] ?? ($fallback ?? $key);
  }
}

$tab = (string)($_GET['tab'] ?? 'contactos');
$q   = trim((string)($_GET['q'] ?? ''));

$editing = null;
$editId  = (int)($_GET['edit'] ?? 0);

$contacts = [];
$tableMissing = false;

try {
  if (!isset($pdo) || !($pdo instanceof PDO)) {
    throw new RuntimeException(pl_t('pdo_not_available'));
  }

  $hospitalId = isset($hospitalId) ? (int)$hospitalId : (int)($_SESSION['user']['id'] ?? 0);
  if ($hospitalId <= 0) {
    throw new RuntimeException(pl_t('invalid_hospital_id'));
  }

  // Preferimos hospital_contacts; fallback clinic_contacts si aún no creas tabla hospital
  $contactsTable = null;

  $stT = $pdo->prepare("SHOW TABLES LIKE 'hospital_contacts'");
  $stT->execute();
  if ((bool)$stT->fetchColumn()) {
    $contactsTable = 'hospital_contacts';
  } else {
    $stT2 = $pdo->prepare("SHOW TABLES LIKE 'clinic_contacts'");
    $stT2->execute();
    if ((bool)$stT2->fetchColumn()) {
      $contactsTable = 'clinic_contacts';
    }
  }

  if (!$contactsTable) {
    $tableMissing = true;
  } else {
    // Detecta columnas existentes (robusto)
    $cols = [];
    $stC = $pdo->query("SHOW COLUMNS FROM `{$contactsTable}`");
    foreach (($stC->fetchAll(PDO::FETCH_ASSOC) ?: []) as $r) {
      $cols[] = (string)$r['Field'];
    }

    // Owner column (hospital primero, fallback clínica)
    $ownerCol = null;
    foreach (['hospital_user_id','clinic_user_id','hospital_id','clinic_id'] as $cand) {
      if (in_array($cand, $cols, true)) {
        $ownerCol = $cand;
        break;
      }
    }

    // Requeridas mínimas: id + owner + name
    $required = ['id','name'];
    foreach ($required as $req) {
      if (!in_array($req, $cols, true)) {
        throw new RuntimeException("{$contactsTable} " . pl_t('missing_required_column') . ": {$req}");
      }
    }
    if ($ownerCol === null) {
      throw new RuntimeException("{$contactsTable} " . pl_t('missing_owner_column'));
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
          if ($name === '') $errs[] = pl_t('required_name');

          if ($hasEmail && $email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errs[] = pl_t('invalid_email');
          }
        } else {
          if ($contactId <= 0) $errs[] = pl_t('invalid_contact');
        }

        // Ownership check para update/delete
        if (!$errs && in_array($action, ['update_contact','delete_contact'], true)) {
          if ($contactId <= 0) {
            $errs[] = pl_t('invalid_contact');
          } else {
            $stOwn = $pdo->prepare("SELECT id FROM `{$contactsTable}` WHERE id=? AND `{$ownerCol}`=? LIMIT 1");
            $stOwn->execute([$contactId, $hospitalId]);
            if (!$stOwn->fetchColumn()) $errs[] = pl_t('unauthorized');
          }
        }

        if ($errs) {
          foreach ($errs as $er) $uiErrors[] = $er;
        } else {
          if ($action === 'create_contact') {
            $fields = [
              $ownerCol => $hospitalId,
              'name'    => $name,
            ];
            if ($hasEmail) $fields['email'] = ($email !== '' ? $email : null);
            if ($hasPhone) $fields['phone'] = ($phone !== '' ? $phone : null);
            if ($hasRole)  $fields['role']  = ($role !== '' ? $role : null);
            if ($hasTags)  $fields['tags']  = ($tags !== '' ? $tags : null);
            if ($hasNotes) $fields['notes'] = ($notes !== '' ? $notes : null);

            $colsSql = implode(',', array_map(fn($c) => "`{$c}`", array_keys($fields)));
            $valsSql = implode(',', array_fill(0, count($fields), '?'));

            $sql = "INSERT INTO `{$contactsTable}` ({$colsSql}) VALUES ({$valsSql})";
            $st = $pdo->prepare($sql);
            $st->execute(array_values($fields));

            header("Location: ?tab=contactos&lang=" . urlencode($plLang));
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
            $vals[] = $hospitalId;

            $sql = "UPDATE `{$contactsTable}` SET " . implode(',', $sets) . " WHERE id=? AND `{$ownerCol}`=? LIMIT 1";
            $st = $pdo->prepare($sql);
            $st->execute($vals);

            header("Location: ?tab=contactos&lang=" . urlencode($plLang));
            exit;
          }

          if ($action === 'delete_contact') {
            $st = $pdo->prepare("DELETE FROM `{$contactsTable}` WHERE id=? AND `{$ownerCol}`=? LIMIT 1");
            $st->execute([$contactId, $hospitalId]);

            header("Location: ?tab=contactos&lang=" . urlencode($plLang));
            exit;
          }
        }
      }
    }

    // =========================
    // Load edit contact (GET edit)
    // =========================
    if ($editId > 0) {
      $stE = $pdo->prepare("SELECT * FROM `{$contactsTable}` WHERE id=? AND `{$ownerCol}`=? LIMIT 1");
      $stE->execute([$editId, $hospitalId]);
      $editing = $stE->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    // =========================
    // Load contacts list (with search)
    // =========================
    $where = "`{$ownerCol}`=?";
    $params = [$hospitalId];

    if ($q !== '') {
      $like = "%{$q}%";
      $parts = ["name LIKE ?"];
      $params[] = $like;

      if ($hasEmail) { $parts[] = "email LIKE ?"; $params[] = $like; }
      if ($hasPhone) { $parts[] = "phone LIKE ?"; $params[] = $like; }
      if ($hasRole)  { $parts[] = "role LIKE ?";  $params[] = $like; }
      if ($hasTags)  { $parts[] = "tags LIKE ?";  $params[] = $like; }

      $where .= " AND (" . implode(" OR ", $parts) . ")";
    }

    $order = $hasCreatedAt ? "ORDER BY created_at DESC" : "ORDER BY id DESC";
    $sql = "SELECT * FROM `{$contactsTable}` WHERE {$where} {$order}";
    $stL = $pdo->prepare($sql);
    $stL->execute($params);
    $contacts = $stL->fetchAll(PDO::FETCH_ASSOC) ?: [];
  }

} catch (Throwable $e) {
  error_log("HOSP CONTACTOS.PHP ERROR: " . $e->getMessage());
  $uiErrors[] = pl_t('contacts_load_error');
}
?>

<div class="sectionTitle">
  <h2><?= h(pl_t('contacts')) ?></h2>

  <form method="get" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
    <input type="hidden" name="tab" value="contactos">
    <input type="hidden" name="lang" value="<?= h($plLang) ?>">
    <input class="input" name="q" value="<?= h($q) ?>" placeholder="<?= h(pl_t('search_placeholder')) ?>" style="min-width:260px;">
    <button class="btn btnGhost" type="submit"><?= h(pl_t('search')) ?></button>
    <?php if ($q !== ''): ?>
      <a class="btn btnGhost" href="?tab=contactos&lang=<?= urlencode($plLang) ?>"><?= h(pl_t('clear')) ?></a>
    <?php endif; ?>
  </form>
</div>

<?php if (!empty($tableMissing)): ?>
  <div class="note">
    <strong><?= h(pl_t('contacts_not_enabled')) ?></strong><br>
    <?= h(pl_t('missing_table_text')) ?> <code>hospital_contacts</code>. <?= h(pl_t('create_table_sql')) ?>
    <pre style="white-space:pre-wrap; margin:10px 0 0; padding:10px; border-radius:12px; border:1px solid rgba(255,255,255,.10); background:rgba(0,0,0,.16); color:rgba(246,241,231,.88);">
CREATE TABLE `hospital_contacts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `hospital_user_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(160) NOT NULL,
  `email` VARCHAR(190) NULL,
  `phone` VARCHAR(60) NULL,
  `role` VARCHAR(60) NULL,
  `tags` VARCHAR(220) NULL,
  `notes` VARCHAR(500) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_hospital_contacts_hospital` (`hospital_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    </pre>
  </div>
  <?php return; ?>
<?php endif; ?>

<div class="grid2" style="grid-template-columns: 1.05fr .95fr;">
  <div class="panel">
    <h3 style="margin:0 0 10px;"><?= h(pl_t('contacts_list')) ?></h3>

    <div class="tableWrap">
      <table class="table">
        <thead>
          <tr>
            <th><?= h(pl_t('contact')) ?></th>
            <th><?= h(pl_t('data')) ?></th>
            <th style="width:180px;"><?= h(pl_t('actions')) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$contacts): ?>
            <tr>
              <td colspan="3" class="muted"><?= h(pl_t('no_contacts_saved')) ?></td>
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
                    <?= $email !== '' ? '<div><strong class="muted">' . h(pl_t('email_label')) . '</strong> ' . h($email) . '</div>' : '' ?>
                    <?= $phone !== '' ? '<div><strong class="muted">' . h(pl_t('phone_label')) . '</strong> ' . h($phone) . '</div>' : '' ?>
                    <?= $tags !== '' ? '<div><strong class="muted">' . h(pl_t('tags_label')) . '</strong> ' . h($tags) . '</div>' : '' ?>
                    <?= $notes !== '' ? '<div style="margin-top:6px;">' . h($notes) . '</div>' : '' ?>
                  </div>
                </td>

                <td>
                  <div class="actions">
                    <a class="iconBtn" href="?tab=contactos&edit=<?= (int)$cid ?>&lang=<?= urlencode($plLang) ?>"><?= h(pl_t('edit')) ?></a>
                    <form method="post" style="display:inline;" onsubmit="return confirm(<?= json_encode(pl_t('delete_contact_confirm')) ?>);">
                      <input type="hidden" name="action" value="delete_contact">
                      <input type="hidden" name="contact_id" value="<?= (int)$cid ?>">
                      <button class="btnDanger" type="submit"><?= h(pl_t('delete')) ?></button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <div class="note" style="margin-top:12px;">
      <?= h(pl_t('contacts_help')) ?>
    </div>
  </div>

  <div class="panel">
    <h3 style="margin:0 0 10px;"><?= h($editing ? pl_t('edit_contact') : pl_t('new_contact')) ?></h3>

    <form method="post">
      <input type="hidden" name="action" value="<?= $editing ? 'update_contact' : 'create_contact' ?>">
      <input type="hidden" name="contact_id" value="<?= (int)($editing['id'] ?? 0) ?>">

      <div class="field">
        <label><?= h(pl_t('name')) ?></label>
        <input class="input" name="name" value="<?= h((string)($editing['name'] ?? '')) ?>" required>
      </div>

      <div class="row">
        <div class="field">
          <label><?= h(pl_t('email_optional')) ?></label>
          <input class="input" name="email" value="<?= h((string)($editing['email'] ?? '')) ?>" placeholder="<?= h(pl_t('email_placeholder')) ?>">
        </div>
        <div class="field">
          <label><?= h(pl_t('phone_optional')) ?></label>
          <input class="input" name="phone" value="<?= h((string)($editing['phone'] ?? '')) ?>" placeholder="<?= h(pl_t('phone_placeholder')) ?>">
        </div>
      </div>

      <div class="row">
        <div class="field">
          <label><?= h(pl_t('role_optional')) ?></label>
          <input class="input" name="role" value="<?= h((string)($editing['role'] ?? '')) ?>" placeholder="<?= h(pl_t('role_placeholder')) ?>">
        </div>
        <div class="field">
          <label><?= h(pl_t('tags_optional')) ?></label>
          <input class="input" name="tags" value="<?= h((string)($editing['tags'] ?? '')) ?>" placeholder="<?= h(pl_t('tags_placeholder')) ?>">
        </div>
      </div>

      <div class="field">
        <label><?= h(pl_t('notes_optional')) ?></label>
        <input class="input" name="notes" value="<?= h((string)($editing['notes'] ?? '')) ?>" placeholder="<?= h(pl_t('notes_placeholder')) ?>">
      </div>

      <div class="btnRow">
        <?php if ($editing): ?>
          <a class="btn btnGhost" href="?tab=contactos&lang=<?= urlencode($plLang) ?>"><?= h(pl_t('cancel_edit')) ?></a>
        <?php else: ?>
          <button class="btn btnGhost" type="reset"><?= h(pl_t('clear')) ?></button>
        <?php endif; ?>
        <button class="btn btnGold" type="submit"><?= h(pl_t('save')) ?></button>
      </div>
    </form>
  </div>
</div>
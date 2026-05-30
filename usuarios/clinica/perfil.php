<?php
declare(strict_types=1);

if (!isset($uiErrors) || !is_array($uiErrors)) $uiErrors = [];
$uiOk = null;

function h2(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

// Asegura que existen $pdo y $clinicId desde index.php
if (!isset($pdo) || !isset($clinicId)) {
  $uiErrors[] = "Falta conexión PDO o clinicId.";
}

// --- Cargar datos actuales desde users ---
$current = [
  'name' => '',
  'email' => '',
  'phone' => '',
  'location' => '',
];

try {
  if (!$uiErrors) {
    $st = $pdo->prepare("SELECT id, role, name, email, phone, location FROM users WHERE id=? LIMIT 1");
    $st->execute([(int)$clinicId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
      $uiErrors[] = "No se encontró el usuario de clínica.";
    } else {
      // Seguridad extra: solo clínica
      if (($row['role'] ?? '') !== 'clinica') {
        $uiErrors[] = "No autorizado (rol no es clínica).";
      } else {
        $current['name'] = (string)($row['name'] ?? '');
        $current['email'] = (string)($row['email'] ?? '');
        $current['phone'] = (string)($row['phone'] ?? '');
        $current['location'] = (string)($row['location'] ?? '');
      }
    }
  }
} catch (Throwable $e) {
  error_log("PERFIL LOAD ERROR: " . $e->getMessage());
  $uiErrors[] = "No se pudo cargar el perfil.";
}

// --- Guardar cambios ---
try {
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'profile_save') {
    if ($uiErrors) {
      // Si ya hay errores de carga, no hacemos nada.
    } else {
      $name = trim((string)($_POST['name'] ?? ''));
      $phone = trim((string)($_POST['phone'] ?? ''));
      $location = trim((string)($_POST['location'] ?? ''));

      if ($name === '') $uiErrors[] = "El nombre es obligatorio.";

      // Normalización ligera
      if (strlen($name) > 120) $uiErrors[] = "El nombre es demasiado largo.";
      if (strlen($phone) > 60) $uiErrors[] = "El teléfono es demasiado largo.";
      if (strlen($location) > 120) $uiErrors[] = "La ubicación es demasiado larga.";

      if (!$uiErrors) {
        $st = $pdo->prepare("UPDATE users SET name=?, phone=?, location=? WHERE id=? AND role='clinica' LIMIT 1");
        $st->execute([
          $name,
          ($phone !== '' ? $phone : null),
          ($location !== '' ? $location : null),
          (int)$clinicId
        ]);

        // Refrescar en sesión para que el header use lo nuevo
        $_SESSION['user']['name'] = $name;
        $_SESSION['user']['phone'] = ($phone !== '' ? $phone : null);
        $_SESSION['user']['location'] = ($location !== '' ? $location : null);

        $current['name'] = $name;
        $current['phone'] = $phone;
        $current['location'] = $location;

        $uiOk = "Perfil actualizado.";
      }
    }
  }
} catch (Throwable $e) {
  error_log("PERFIL SAVE ERROR: " . $e->getMessage());
  $uiErrors[] = "Error interno al guardar el perfil.";
}
?>

<?php if ($uiOk): ?>
  <div class="note" style="border-color: rgba(46,204,113,.25); background: rgba(46,204,113,.08); margin-bottom:12px;">
    <?= h2($uiOk) ?>
  </div>
<?php endif; ?>

<?php if ($uiErrors): ?>
  <div class="note" style="border-color: rgba(255,99,99,.28); background: rgba(255,99,99,.10); margin-bottom:12px;">
    <strong>Revisa lo siguiente:</strong>
    <ul style="margin:8px 0 0; padding-left:18px;">
      <?php foreach ($uiErrors as $e): ?>
        <li><?= h2((string)$e) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<div class="sectionTitle">
  <h2>Perfil de la clínica</h2>
</div>

<div class="note" style="margin-bottom:12px;">
  Esta información se usa para identificar tu clínica en ofertas, agenda y comunicaciones internas.
</div>

<form method="post">
  <input type="hidden" name="action" value="profile_save">

  <table class="table">
    <thead>
      <tr>
        <th style="width:260px;">Campo</th>
        <th>Valor</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td><strong>Nombre</strong></td>
        <td>
          <input class="input" name="name" value="<?= h2($current['name']) ?>" placeholder="Ej: Clínica RM" required>
        </td>
      </tr>

      <tr>
        <td><strong>Email</strong></td>
        <td>
          <input class="input" value="<?= h2($current['email']) ?>" disabled>
          <div class="muted" style="margin-top:6px; font-size:.9rem;">
            Por seguridad, el email no se cambia desde aquí.
          </div>
        </td>
      </tr>

      <tr>
        <td><strong>Teléfono</strong></td>
        <td>
          <input class="input" name="phone" value="<?= h2($current['phone']) ?>" placeholder="Ej: +34 600 123 456">
        </td>
      </tr>

      <tr>
        <td><strong>Ubicación</strong></td>
        <td>
          <input class="input" name="location" value="<?= h2($current['location']) ?>" placeholder="Ej: Gandía, Valencia">
        </td>
      </tr>
    </tbody>
  </table>

  <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:12px; flex-wrap:wrap;">
    <button class="btn btnGold" type="submit">Guardar cambios</button>
  </div>
</form>


</div>

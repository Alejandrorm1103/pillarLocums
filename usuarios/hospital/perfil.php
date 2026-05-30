<?php
declare(strict_types=1);

if (!isset($uiErrors) || !is_array($uiErrors)) $uiErrors = [];
$uiOk = null;

if (!function_exists('h2')) {
  function h2(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
  }
}

// =====================
// I18N LOCAL PARA PERFIL
// =====================
$plLang = (string)($plLang ?? $_GET['lang'] ?? $_SESSION['lang'] ?? 'es');
$plLang = ($plLang === 'en') ? 'en' : 'es';
$_SESSION['lang'] = $plLang;

$PILLAR_HOSP_PROFILE_DICT = [
  'es' => [
    'missing_pdo_or_hospital'   => 'Falta conexión PDO o hospitalId.',
    'hospital_user_not_found'   => 'No se encontró el usuario de hospital.',
    'unauthorized_role'         => 'No autorizado (rol no es hospital).',
    'profile_load_error'        => 'No se pudo cargar el perfil.',
    'name_required'             => 'El nombre es obligatorio.',
    'name_too_long'             => 'El nombre es demasiado largo.',
    'phone_too_long'            => 'El teléfono es demasiado largo.',
    'location_too_long'         => 'La ubicación es demasiado larga.',
    'profile_updated'           => 'Perfil actualizado.',
    'profile_save_error'        => 'Error interno al guardar el perfil.',
    'review_following'          => 'Revisa lo siguiente:',
    'hospital_profile'          => 'Perfil del hospital',
    'profile_intro'             => 'Esta información se usa para identificar tu hospital en ofertas, agenda y comunicaciones internas.',
    'field'                     => 'Campo',
    'value'                     => 'Valor',
    'name'                      => 'Nombre',
    'email'                     => 'Email',
    'phone'                     => 'Teléfono',
    'location'                  => 'Ubicación',
    'name_placeholder'          => 'Ej: Hospital RM',
    'phone_placeholder'         => 'Ej: +34 600 123 456',
    'location_placeholder'      => 'Ej: Valencia, España',
    'email_security_note'       => 'Por seguridad, el email no se cambia desde aquí.',
    'save_changes'              => 'Guardar cambios',
  ],
  'en' => [
    'missing_pdo_or_hospital'   => 'PDO connection or hospitalId is missing.',
    'hospital_user_not_found'   => 'Hospital user not found.',
    'unauthorized_role'         => 'Unauthorized (role is not hospital).',
    'profile_load_error'        => 'Could not load the profile.',
    'name_required'             => 'Name is required.',
    'name_too_long'             => 'The name is too long.',
    'phone_too_long'            => 'The phone number is too long.',
    'location_too_long'         => 'The location is too long.',
    'profile_updated'           => 'Profile updated.',
    'profile_save_error'        => 'Internal error while saving the profile.',
    'review_following'          => 'Please review the following:',
    'hospital_profile'          => 'Hospital profile',
    'profile_intro'             => 'This information is used to identify your hospital in offers, schedule, and internal communications.',
    'field'                     => 'Field',
    'value'                     => 'Value',
    'name'                      => 'Name',
    'email'                     => 'Email',
    'phone'                     => 'Phone',
    'location'                  => 'Location',
    'name_placeholder'          => 'Ex: RM Hospital',
    'phone_placeholder'         => 'Ex: +34 600 123 456',
    'location_placeholder'      => 'Ex: Valencia, Spain',
    'email_security_note'       => 'For security reasons, the email cannot be changed here.',
    'save_changes'              => 'Save changes',
  ],
];

// Si existe el diccionario global del index, lo extendemos
if (isset($plDict) && is_array($plDict)) {
  $plDict['es'] = array_merge($plDict['es'] ?? [], $PILLAR_HOSP_PROFILE_DICT['es']);
  $plDict['en'] = array_merge($plDict['en'] ?? [], $PILLAR_HOSP_PROFILE_DICT['en']);
}

// Fallback local si index no expone pl_t()
if (!function_exists('pl_t')) {
  function pl_t(string $key, ?string $fallback = null): string {
    global $plLang, $PILLAR_HOSP_PROFILE_DICT;
    return $PILLAR_HOSP_PROFILE_DICT[$plLang][$key] ?? ($fallback ?? $key);
  }
}

// Asegura que existen $pdo y $hospitalId desde index.php
if (!isset($pdo) || !isset($hospitalId)) {
  $uiErrors[] = pl_t('missing_pdo_or_hospital');
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
    $st->execute([(int)$hospitalId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
      $uiErrors[] = pl_t('hospital_user_not_found');
    } else {
      if (($row['role'] ?? '') !== 'hospital') {
        $uiErrors[] = pl_t('unauthorized_role');
      } else {
        $current['name'] = (string)($row['name'] ?? '');
        $current['email'] = (string)($row['email'] ?? '');
        $current['phone'] = (string)($row['phone'] ?? '');
        $current['location'] = (string)($row['location'] ?? '');
      }
    }
  }
} catch (Throwable $e) {
  error_log("HOSP PERFIL LOAD ERROR: " . $e->getMessage());
  $uiErrors[] = pl_t('profile_load_error');
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

      if ($name === '') $uiErrors[] = pl_t('name_required');

      if (strlen($name) > 120) $uiErrors[] = pl_t('name_too_long');
      if (strlen($phone) > 60) $uiErrors[] = pl_t('phone_too_long');
      if (strlen($location) > 120) $uiErrors[] = pl_t('location_too_long');

      if (!$uiErrors) {
        $st = $pdo->prepare("UPDATE users SET name=?, phone=?, location=? WHERE id=? AND role='hospital' LIMIT 1");
        $st->execute([
          $name,
          ($phone !== '' ? $phone : null),
          ($location !== '' ? $location : null),
          (int)$hospitalId
        ]);

        $_SESSION['user']['name'] = $name;
        $_SESSION['user']['phone'] = ($phone !== '' ? $phone : null);
        $_SESSION['user']['location'] = ($location !== '' ? $location : null);

        $current['name'] = $name;
        $current['phone'] = $phone;
        $current['location'] = $location;

        $uiOk = pl_t('profile_updated');
      }
    }
  }
} catch (Throwable $e) {
  error_log("HOSP PERFIL SAVE ERROR: " . $e->getMessage());
  $uiErrors[] = pl_t('profile_save_error');
}
?>

<?php if ($uiOk): ?>
  <div class="note" style="border-color: rgba(46,204,113,.25); background: rgba(46,204,113,.08); margin-bottom:12px;">
    <?= h2($uiOk) ?>
  </div>
<?php endif; ?>

<?php if ($uiErrors): ?>
  <div class="note" style="border-color: rgba(255,99,99,.28); background: rgba(255,99,99,.10); margin-bottom:12px;">
    <strong><?= h2(pl_t('review_following')) ?></strong>
    <ul style="margin:8px 0 0; padding-left:18px;">
      <?php foreach ($uiErrors as $e): ?>
        <li><?= h2((string)$e) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<div class="sectionTitle">
  <h2><?= h2(pl_t('hospital_profile')) ?></h2>
</div>

<div class="note" style="margin-bottom:12px;">
  <?= h2(pl_t('profile_intro')) ?>
</div>

<form method="post">
  <input type="hidden" name="action" value="profile_save">

  <div class="tableWrap">
    <table class="table">
      <thead>
        <tr>
          <th style="width:260px;"><?= h2(pl_t('field')) ?></th>
          <th><?= h2(pl_t('value')) ?></th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td><strong><?= h2(pl_t('name')) ?></strong></td>
          <td>
            <input class="input" name="name" value="<?= h2($current['name']) ?>" placeholder="<?= h2(pl_t('name_placeholder')) ?>" required>
          </td>
        </tr>

        <tr>
          <td><strong><?= h2(pl_t('email')) ?></strong></td>
          <td>
            <input class="input" value="<?= h2($current['email']) ?>" disabled>
            <div class="muted" style="margin-top:6px; font-size:.9rem;">
              <?= h2(pl_t('email_security_note')) ?>
            </div>
          </td>
        </tr>

        <tr>
          <td><strong><?= h2(pl_t('phone')) ?></strong></td>
          <td>
            <input class="input" name="phone" value="<?= h2($current['phone']) ?>" placeholder="<?= h2(pl_t('phone_placeholder')) ?>">
          </td>
        </tr>

        <tr>
          <td><strong><?= h2(pl_t('location')) ?></strong></td>
          <td>
            <input class="input" name="location" value="<?= h2($current['location']) ?>" placeholder="<?= h2(pl_t('location_placeholder')) ?>">
          </td>
        </tr>
      </tbody>
    </table>
  </div>

  <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:12px; flex-wrap:wrap;">
    <button class="btn btnGold" type="submit"><?= h2(pl_t('save_changes')) ?></button>
  </div>
</form>
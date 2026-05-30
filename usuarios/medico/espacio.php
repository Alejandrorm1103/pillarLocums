<?php
declare(strict_types=1);

/**
 * espacio.php — Panel Médico
 * CONTENT-ONLY (sin topbar ni tabs) para evitar duplicación de navegación.
 *
 * Funciones:
 * - Subir foto (JPG/PNG)
 * - Guardar descripción (bio)
 * - Guardar idiomas (checkboxes)
 * - Ver estado activo/desactivado (lo controla admin)
 *
 * Requiere:
 * - auth.php (require_login + sesión)
 * - db.php ($pdo)
 * - Tabla: medico_profile (medico_id PK, photo_path, bio, languages, is_active, updated_at)
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

require_once __DIR__ . "/../../config/auth.php";
require_login();

require_once __DIR__ . "/../db.php";

$user = $_SESSION['user'] ?? null;
if (!$user || ($user['role'] ?? '') !== 'medico') {
  header("Location: /usuarios/dashboard.php");
  exit;
}

if (!function_exists('h')) {
  function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
}

/** Base de rutas del módulo */
$BASE = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/');
if ($BASE === '') $BASE = '/usuarios/medico';
if (!function_exists('u')) {
  function u(string $file): string {
    global $BASE;
    return $BASE . '/' . ltrim($file, '/');
  }
}

$medicoId    = (int)($user['id'] ?? 0);
$medicoName  = (string)($user['name'] ?? 'Médico');
$medicoEmail = (string)($user['email'] ?? '');

$flash = ['ok' => '', 'err' => ''];

/* ============================================================
   I18N LOCAL DEL MÓDULO ESPACIO
============================================================ */
$espLang = (string)($_SESSION['lang'] ?? 'es');
if (!in_array($espLang, ['es', 'en'], true)) {
  $espLang = 'es';
}

$espDict = [
  'es' => [
    'space_title' => 'Mi espacio',
    'session' => 'Sesión',

    'saved_ok' => 'Información guardada correctamente.',
    'photo_ok' => 'Foto actualizada correctamente.',
    'generic_err' => 'No se pudo completar la acción. Intenta de nuevo.',
    'profile_load_error' => 'No se pudo cargar tu perfil (BD). Revisa la tabla medico_profile.',

    'photo_and_status' => 'Foto y estado',
    'photo_status_text' => 'Tu estado lo habilita el administrador. La foto puedes actualizarla cuando quieras.',
    'profile_status' => 'Estado del perfil',
    'profile_active' => 'Activo (habilitado por admin)',
    'profile_inactive' => 'Desactivado (no habilitado)',
    'last_updated' => 'Última actualización',

    'upload_photo' => 'Subir/actualizar foto (JPG/PNG, máx 4MB)',
    'recommended_photo' => 'Recomendado: 600×600 o mayor, cuadrada.',
    'save_photo' => 'Guardar foto',

    'description_languages' => 'Descripción e idiomas',
    'professional_info_text' => 'Esta información puede mostrarse en tu perfil profesional.',
    'description_max' => 'Descripción (máx 900 caracteres)',
    'languages_i_speak' => 'Idiomas que manejo',
    'save_changes' => 'Guardar cambios',

    'lang_es' => 'Español',
    'lang_en' => 'Inglés',
    'lang_it' => 'Italiano',
    'lang_fr' => 'Francés',
    'lang_de' => 'Alemán',
    'lang_pt' => 'Portugués',
    'lang_ca' => 'Catalán/Valenciano',
  ],
  'en' => [
    'space_title' => 'My space',
    'session' => 'Session',

    'saved_ok' => 'Information saved successfully.',
    'photo_ok' => 'Photo updated successfully.',
    'generic_err' => 'The action could not be completed. Please try again.',
    'profile_load_error' => 'Your profile could not be loaded (DB). Check the medico_profile table.',

    'photo_and_status' => 'Photo and status',
    'photo_status_text' => 'Your status is enabled by the administrator. You can update the photo whenever you want.',
    'profile_status' => 'Profile status',
    'profile_active' => 'Active (enabled by admin)',
    'profile_inactive' => 'Disabled (not enabled)',
    'last_updated' => 'Last updated',

    'upload_photo' => 'Upload/update photo (JPG/PNG, max 4MB)',
    'recommended_photo' => 'Recommended: 600×600 or larger, square.',
    'save_photo' => 'Save photo',

    'description_languages' => 'Description and languages',
    'professional_info_text' => 'This information may be displayed on your professional profile.',
    'description_max' => 'Description (max 900 characters)',
    'languages_i_speak' => 'Languages I speak',
    'save_changes' => 'Save changes',

    'lang_es' => 'Spanish',
    'lang_en' => 'English',
    'lang_it' => 'Italian',
    'lang_fr' => 'French',
    'lang_de' => 'German',
    'lang_pt' => 'Portuguese',
    'lang_ca' => 'Catalan/Valencian',
  ],
];

if (!function_exists('esp_t')) {
  function esp_t(string $key, string $fallback = ''): string {
    global $espDict, $espLang;

    if (isset($espDict[$espLang][$key])) {
      return (string)$espDict[$espLang][$key];
    }

    if (function_exists('md_t')) {
      return md_t($key, $fallback !== '' ? $fallback : $key);
    }

    if (function_exists('t')) {
      $v = t($key);
      if ($v !== $key) return (string)$v;
    }

    return $fallback !== '' ? $fallback : $key;
  }
}

$LANG_QS = '&lang=' . urlencode($espLang);

/** ✅ URL de retorno SIEMPRE al layout principal */
$returnBase = u("index.php") . "?tab=espacio&lang=" . urlencode($espLang);

/** CSRF */
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = (string)$_SESSION['csrf_token'];

/** Idiomas disponibles */
$languageOptions = [
  'es' => esp_t('lang_es', 'Español'),
  'en' => esp_t('lang_en', 'Inglés'),
  'it' => esp_t('lang_it', 'Italiano'),
  'fr' => esp_t('lang_fr', 'Francés'),
  'de' => esp_t('lang_de', 'Alemán'),
  'pt' => esp_t('lang_pt', 'Portugués'),
  'ca' => esp_t('lang_ca', 'Catalán/Valenciano'),
];

$debug = isset($_GET['debug']) && $_GET['debug'] === '1';
$debugInfo = [];

try {
  if (isset($pdo) && $pdo instanceof PDO) {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  }
} catch (Throwable $e) {}

/** PRG flash */
if (isset($_GET['saved']) && $_GET['saved'] === '1') $flash['ok'] = esp_t('saved_ok', 'Información guardada correctamente.');
if (isset($_GET['photo']) && $_GET['photo'] === '1') $flash['ok'] = esp_t('photo_ok', 'Foto actualizada correctamente.');
if (isset($_GET['err']) && $_GET['err'] === '1') $flash['err'] = esp_t('generic_err', 'No se pudo completar la acción. Intenta de nuevo.');

/** Cargar perfil */
$profile = [
  'photo_path' => null,
  'bio' => '',
  'languages' => '',
  'is_active' => 0,
  'updated_at' => null
];

try {
  $stmt = $pdo->prepare("
    SELECT photo_path, bio, languages, is_active, updated_at
    FROM medico_profile
    WHERE medico_id = :id
    LIMIT 1
  ");
  $stmt->execute([':id' => $medicoId]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($row) {
    $profile['photo_path'] = $row['photo_path'] ?? null;
    $profile['bio']        = (string)($row['bio'] ?? '');
    $profile['languages']  = (string)($row['languages'] ?? '');
    $profile['is_active']  = (int)($row['is_active'] ?? 0);
    $profile['updated_at'] = $row['updated_at'] ?? null;
  } else {
    $ins = $pdo->prepare("INSERT INTO medico_profile (medico_id, is_active) VALUES (:id, 0)");
    $ins->execute([':id' => $medicoId]);
  }
} catch (Throwable $e) {
  error_log("espacio.php medico_profile load error: " . $e->getMessage());
  $flash['err'] = esp_t('profile_load_error', 'No se pudo cargar tu perfil (BD). Revisa la tabla medico_profile.');
}

$currentLangs = array_filter(array_map('trim', explode(',', (string)$profile['languages'])));
$currentLangs = array_values(array_unique(array_map('strtolower', $currentLangs)));

/** POST */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $postedCsrf = (string)($_POST['csrf_token'] ?? '');
  if (!hash_equals($csrf, $postedCsrf)) {
    header("Location: " . $returnBase . "&err=1");
    exit;
  }

  $action = (string)($_POST['action'] ?? 'save_profile');

  // Guardar bio + idiomas
  if ($action === 'save_profile') {
    $bio = trim((string)($_POST['bio'] ?? ''));

    if (mb_strlen($bio) > 900) {
      header("Location: " . $returnBase . "&err=1");
      exit;
    }

    $langs = $_POST['languages'] ?? [];
    if (!is_array($langs)) $langs = [];

    $langsClean = [];
    foreach ($langs as $code) {
      $code = strtolower(trim((string)$code));
      if ($code !== '' && isset($languageOptions[$code])) $langsClean[] = $code;
    }
    $langsClean = array_values(array_unique($langsClean));
    $langsStr = implode(',', $langsClean);

    try {
      $upd = $pdo->prepare("
        UPDATE medico_profile
        SET bio = :bio, languages = :langs, updated_at = NOW()
        WHERE medico_id = :id
      ");
      $upd->execute([
        ':bio' => $bio,
        ':langs' => $langsStr,
        ':id' => $medicoId
      ]);

      header("Location: " . $returnBase . "&saved=1");
      exit;
    } catch (Throwable $e) {
      error_log("espacio.php save_profile error: " . $e->getMessage());
      header("Location: " . $returnBase . "&err=1");
      exit;
    }
  }

  // Subir foto
  if ($action === 'upload_photo') {
    if (!isset($_FILES['photo']) || !is_array($_FILES['photo'])) {
      header("Location: " . $returnBase . "&err=1");
      exit;
    }

    $f = $_FILES['photo'];

    if (($f['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
      header("Location: " . $returnBase . "&err=1");
      exit;
    }

    $tmp  = (string)($f['tmp_name'] ?? '');
    $size = (int)($f['size'] ?? 0);

    if ($size <= 0 || $size > (4 * 1024 * 1024)) {
      header("Location: " . $returnBase . "&err=1");
      exit;
    }

    $mime = '';
    if (is_uploaded_file($tmp)) {
      $fi = new finfo(FILEINFO_MIME_TYPE);
      $mime = (string)$fi->file($tmp);
    }

    $ext = null;
    if ($mime === 'image/jpeg') $ext = 'jpg';
    if ($mime === 'image/png')  $ext = 'png';

    if (!$ext) {
      header("Location: " . $returnBase . "&err=1");
      exit;
    }

    $baseDir = __DIR__ . "/../../uploads/medicos/" . $medicoId;
    if (!is_dir($baseDir)) {
      @mkdir($baseDir, 0755, true);
    }

    $filename = "profile_" . date('Ymd_His') . "." . $ext;
    $destAbs  = $baseDir . "/" . $filename;

    if (!move_uploaded_file($tmp, $destAbs)) {
      header("Location: " . $returnBase . "&err=1");
      exit;
    }

    $destRel = "/uploads/medicos/" . $medicoId . "/" . $filename;

    try {
      $upd = $pdo->prepare("
        UPDATE medico_profile
        SET photo_path = :p, updated_at = NOW()
        WHERE medico_id = :id
      ");
      $upd->execute([':p' => $destRel, ':id' => $medicoId]);

      header("Location: " . $returnBase . "&photo=1");
      exit;
    } catch (Throwable $e) {
      error_log("espacio.php upload_photo db error: " . $e->getMessage());
      header("Location: " . $returnBase . "&err=1");
      exit;
    }
  }

  header("Location: " . $returnBase . "&err=1");
  exit;
}

/** Debug */
if ($debug) {
  try { $pdo->query("SELECT 1"); $debugInfo[] = "✅ PDO OK (SELECT 1)."; }
  catch (Throwable $e) { $debugInfo[] = "❌ PDO ERROR: " . $e->getMessage(); }

  try {
    $st = $pdo->query("SHOW TABLES LIKE 'medico_profile'");
    $debugInfo[] = "Tabla medico_profile existe: " . ($st->fetch() ? 'sí' : 'no');
  } catch (Throwable $e) {
    $debugInfo[] = "❌ SHOW TABLES error: " . $e->getMessage();
  }
}

$isActive = ((int)$profile['is_active'] === 1);
?>

<div class="sectionTitle">
  <div>
    <h2><?= h(esp_t('space_title', 'Mi espacio')) ?></h2>
    <div class="muted"><?= h(esp_t('session', 'Sesión')) ?>: <?= h($medicoEmail) ?> • ID: <?= (int)$medicoId ?></div>
  </div>
</div>

<?php if ($flash['ok']): ?>
  <div class="flashOk"><?= h($flash['ok']) ?></div>
<?php endif; ?>

<?php if ($flash['err']): ?>
  <div class="flashErr"><?= h($flash['err']) ?></div>
<?php endif; ?>

<div class="grid2" style="grid-template-columns: 1fr 1fr;">
  <div class="panel">
    <h3 style="margin:0 0 10px;"><?= h(esp_t('photo_and_status', 'Foto y estado')) ?></h3>
    <div class="muted" style="margin-bottom:12px;">
      <?= h(esp_t('photo_status_text', 'Tu estado lo habilita el administrador. La foto puedes actualizarla cuando quieras.')) ?>
    </div>

    <div style="display:flex; gap:14px; align-items:center; flex-wrap:wrap;">
      <div style="width:82px;height:82px;border-radius:16px;border:1px solid rgba(201,162,39,.35);background:rgba(0,0,0,.18);overflow:hidden;display:flex;align-items:center;justify-content:center;font-weight:900;">
        <?php if (!empty($profile['photo_path'])): ?>
          <img src="<?= h((string)$profile['photo_path']) ?>" alt="Foto de perfil" style="width:100%;height:100%;object-fit:cover;display:block;">
        <?php else: ?>
          <?= h(mb_strtoupper(mb_substr($medicoName, 0, 1))) ?>
        <?php endif; ?>
      </div>

      <div>
        <div style="font-weight:900; margin-bottom:6px;"><?= h(esp_t('profile_status', 'Estado del perfil')) ?></div>
        <span class="<?= $isActive ? 'badgeOk' : 'badgeBad' ?>">
          <?= $isActive ? h(esp_t('profile_active', 'Activo (habilitado por admin)')) : h(esp_t('profile_inactive', 'Desactivado (no habilitado)')) ?>
        </span>
        <div class="muted" style="margin-top:8px;">
          <?= h(esp_t('last_updated', 'Última actualización')) ?>: <?= h((string)($profile['updated_at'] ?? '—')) ?>
        </div>
      </div>
    </div>

    <div class="divider"></div>

    <form method="post" action="<?= h(u("index.php")) ?>?tab=espacio&lang=<?= h($espLang) ?>" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
      <input type="hidden" name="action" value="upload_photo">

      <label><?= h(esp_t('upload_photo', 'Subir/actualizar foto (JPG/PNG, máx 4MB)')) ?></label>
      <input class="input" name="photo" type="file" accept=".jpg,.jpeg,.png,image/jpeg,image/png" required>

      <div class="muted" style="margin-top:6px;"><?= h(esp_t('recommended_photo', 'Recomendado: 600×600 o mayor, cuadrada.')) ?></div>

      <div style="margin-top:12px;">
        <button class="btn btnGold" type="submit"><?= h(esp_t('save_photo', 'Guardar foto')) ?></button>
      </div>
    </form>
  </div>

  <div class="panel">
    <h3 style="margin:0 0 10px;"><?= h(esp_t('description_languages', 'Descripción e idiomas')) ?></h3>
    <div class="muted" style="margin-bottom:12px;">
      <?= h(esp_t('professional_info_text', 'Esta información puede mostrarse en tu perfil profesional.')) ?>
    </div>

    <form method="post" action="<?= h(u("index.php")) ?>?tab=espacio&lang=<?= h($espLang) ?>">
      <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
      <input type="hidden" name="action" value="save_profile">

      <label><?= h(esp_t('description_max', 'Descripción (máx 900 caracteres)')) ?></label>
      <textarea class="input" name="bio" maxlength="900" style="min-height:120px;resize:vertical;"><?= h((string)$profile['bio']) ?></textarea>

      <div style="height:12px;"></div>

      <label><?= h(esp_t('languages_i_speak', 'Idiomas que manejo')) ?></label>
      <div class="grid2" style="grid-template-columns: 1fr 1fr; gap:10px;">
        <?php foreach ($languageOptions as $code => $label): ?>
          <?php $checked = in_array($code, $currentLangs, true); ?>
          <label class="panel" style="padding:10px 12px; display:flex; gap:10px; align-items:center;">
            <input type="checkbox" name="languages[]" value="<?= h($code) ?>" <?= $checked ? 'checked' : '' ?>>
            <span style="font-weight:800;"><?= h($label) ?></span>
          </label>
        <?php endforeach; ?>
      </div>

      <div style="margin-top:12px;">
        <button class="btn btnGold" type="submit"><?= h(esp_t('save_changes', 'Guardar cambios')) ?></button>
      </div>
    </form>

    <?php if ($debug && $debugInfo): ?>
      <div class="note" style="margin-top:12px; white-space:pre-wrap;">
        <?= h(implode("\n", $debugInfo)) ?>
      </div>
    <?php endif; ?>
  </div>
</div>
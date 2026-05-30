<?php
declare(strict_types=1);

/**
 * perfil.php — Módulo Perfil (CONTENT-ONLY)
 * ------------------------------------------------------------
 * Se incluye desde index.php cuando tab=perfil.
 *
 * Función:
 * - Mostrar estado de verificación (medico_verifications)
 * - Subir documentos (medico_documents)
 * - Listar documentos enviados (pending/approved/rejected)
 *
 * Requiere (normalmente desde index.php):
 * - $pdo (PDO)
 * - $_SESSION['user'] (id, role, name, email)
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

if (!function_exists('h')) {
  function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
}

/** helper: verifica si existe una tabla (evita fatal errors) */
if (!function_exists('table_exists')) {
  function table_exists(PDO $pdo, string $table): bool {
    try {
      $stmt = $pdo->prepare("SHOW TABLES LIKE :t");
      $stmt->execute([':t' => $table]);
      return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
      return false;
    }
  }
}

/** helper: verifica si existe una columna en una tabla */
if (!function_exists('column_exists')) {
  function column_exists(PDO $pdo, string $table, string $column): bool {
    try {
      $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE :c");
      $stmt->execute([':c' => $column]);
      return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
      return false;
    }
  }
}

/** Base URL del módulo (para redirects PRG) */
$BASE = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/');
if ($BASE === '') $BASE = '/usuarios/medico';
if (!function_exists('u')) {
  function u(string $file): string {
    global $BASE;
    return $BASE . '/' . ltrim($file, '/');
  }
}

/** Si este módulo se abre directo (no desde index), intentamos garantizar auth/db */
if (!isset($pdo) || !($pdo instanceof PDO)) {
  require_once __DIR__ . "/../../config/auth.php";
  require_login();
  require_once __DIR__ . "/../db.php";
}

$user = $_SESSION['user'] ?? null;
if (!$user || ($user['role'] ?? '') !== 'medico') {
  header("Location: /usuarios/dashboard.php");
  exit;
}

$medicoId    = (int)($user['id'] ?? 0);
$medicoName  = (string)($user['name'] ?? 'Médico');
$medicoEmail = (string)($user['email'] ?? '');

$uiErrors = $uiErrors ?? [];
$flashOk  = '';
$flashErr = '';

/* ============================================================
   I18N LOCAL DEL MÓDULO PERFIL
============================================================ */
$perfLang = (string)($_SESSION['lang'] ?? 'es');
if (!in_array($perfLang, ['es', 'en'], true)) {
  $perfLang = 'es';
}

$perfDict = [
  'es' => [
    'profile_title' => 'Perfil',
    'session' => 'Sesión',
    'review_following' => 'Revisa lo siguiente:',

    'doc_sent_pending_review' => 'Documento enviado. Queda pendiente de revisión por el administrador.',
    'doc_duplicate' => 'Ese documento ya fue enviado anteriormente (o se detectó duplicado).',

    'verification_load_error' => 'No se pudo cargar el estado de verificación.',
    'csrf_invalid' => 'Sesión inválida (CSRF). Recarga e inténtalo de nuevo.',
    'file_not_received' => 'No se recibió ningún archivo.',
    'upload_error_code' => 'Error al subir el archivo. Código:',
    'file_max_8mb' => 'El archivo debe pesar máximo 8MB.',
    'format_not_allowed' => 'Formato no permitido. Solo PDF, JPG o PNG.',
    'file_not_allowed' => 'Archivo no permitido.',
    'save_file_server_error' => 'No se pudo guardar el archivo en el servidor.',
    'db_register_error' => 'El archivo se guardó, pero no se pudo registrar en base de datos.',

    'verification_status' => 'Estado de verificación',
    'email' => 'Email',
    'status' => 'Estado',
    'last_updated' => 'Última actualización',
    'admin_note' => 'Nota admin',
    'no_admin_notes' => 'Sin observaciones del administrador.',

    'pending' => 'Pendiente',
    'authorized' => 'Autorizado',
    'rejected' => 'Rechazado',

    'application_access' => 'Acceso a postulación',
    'application_enabled' => 'Habilitado. Ya puedes aplicar a ofertas.',
    'application_blocked' => 'Bloqueado. Sube credenciales y espera aprobación.',

    'upload_credentials' => 'Subir credenciales',
    'allowed_formats' => 'Formatos permitidos: PDF, JPG, PNG • Tamaño máximo: 8MB.',
    'document_type' => 'Tipo de documento',
    'file' => 'Archivo',
    'upload_and_send' => 'Subir y enviar',

    'doc_dni' => 'DNI/NIE',
    'doc_registration' => 'Colegiación',
    'doc_degree' => 'Título / Homologación',
    'doc_insurance' => 'Seguro RC',
    'doc_other' => 'Otros',
    'doc_default' => 'Documento',

    'sent_documents' => 'Documentos enviados',
    'docs_reviewed_admin' => 'Se aprueban o rechazan desde el panel del administrador.',
    'no_docs_uploaded' => 'Aún no has subido documentos.',
    'file_label' => 'Archivo',
    'date' => 'Fecha',
    'view' => 'Ver',

    'approved' => 'Aprobado',
  ],
  'en' => [
    'profile_title' => 'Profile',
    'session' => 'Session',
    'review_following' => 'Please review the following:',

    'doc_sent_pending_review' => 'Document uploaded. It is now pending administrator review.',
    'doc_duplicate' => 'That document was already submitted before (or a duplicate was detected).',

    'verification_load_error' => 'The verification status could not be loaded.',
    'csrf_invalid' => 'Invalid session (CSRF). Refresh and try again.',
    'file_not_received' => 'No file was received.',
    'upload_error_code' => 'Error uploading file. Code:',
    'file_max_8mb' => 'The file must be 8MB maximum.',
    'format_not_allowed' => 'Format not allowed. Only PDF, JPG or PNG.',
    'file_not_allowed' => 'File not allowed.',
    'save_file_server_error' => 'The file could not be saved on the server.',
    'db_register_error' => 'The file was saved, but it could not be registered in the database.',

    'verification_status' => 'Verification status',
    'email' => 'Email',
    'status' => 'Status',
    'last_updated' => 'Last updated',
    'admin_note' => 'Admin note',
    'no_admin_notes' => 'No administrator notes.',

    'pending' => 'Pending',
    'authorized' => 'Authorized',
    'rejected' => 'Rejected',

    'application_access' => 'Application access',
    'application_enabled' => 'Enabled. You can now apply for offers.',
    'application_blocked' => 'Blocked. Upload credentials and wait for approval.',

    'upload_credentials' => 'Upload credentials',
    'allowed_formats' => 'Allowed formats: PDF, JPG, PNG • Maximum size: 8MB.',
    'document_type' => 'Document type',
    'file' => 'File',
    'upload_and_send' => 'Upload and send',

    'doc_dni' => 'DNI/NIE',
    'doc_registration' => 'Medical registration',
    'doc_degree' => 'Degree / Homologation',
    'doc_insurance' => 'Liability insurance',
    'doc_other' => 'Other',
    'doc_default' => 'Document',

    'sent_documents' => 'Uploaded documents',
    'docs_reviewed_admin' => 'They are approved or rejected from the administrator panel.',
    'no_docs_uploaded' => 'You have not uploaded documents yet.',
    'file_label' => 'File',
    'date' => 'Date',
    'view' => 'View',

    'approved' => 'Approved',
  ],
];

if (!function_exists('perf_t')) {
  function perf_t(string $key, string $fallback = ''): string {
    global $perfDict, $perfLang;

    if (isset($perfDict[$perfLang][$key])) {
      return (string)$perfDict[$perfLang][$key];
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

if (!function_exists('perf_doc_label')) {
  function perf_doc_label(string $dtype): string {
    $v = trim($dtype);

    return match ($v) {
      'DNI/NIE' => perf_t('doc_dni', 'DNI/NIE'),
      'Colegiación' => perf_t('doc_registration', 'Colegiación'),
      'Título / Homologación' => perf_t('doc_degree', 'Título / Homologación'),
      'Seguro RC' => perf_t('doc_insurance', 'Seguro RC'),
      'Otros' => perf_t('doc_other', 'Otros'),
      'Documento' => perf_t('doc_default', 'Documento'),
      default => $v !== '' ? $v : perf_t('doc_default', 'Documento'),
    };
  }
}

$LANG_QS = '&lang=' . urlencode($perfLang);

/** Base de retorno PRG con idioma */
$returnBase = u("index.php") . "?tab=perfil&lang=" . urlencode($perfLang);

/** CSRF */
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = (string)$_SESSION['csrf_token'];

/** PRG: mensajes */
if (isset($_GET['ok']) && $_GET['ok'] === '1') {
  $flashOk = perf_t('doc_sent_pending_review', 'Documento enviado. Queda pendiente de revisión por el administrador.');
}
if (isset($_GET['ok']) && $_GET['ok'] === 'dup') {
  $flashErr = perf_t('doc_duplicate', 'Ese documento ya fue enviado anteriormente (o se detectó duplicado).');
}

/** PDO modo excepción */
try {
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Throwable $e) {}

/**
 * ============================================================
 * 1) Estado de verificación (medico_verifications)
 * ============================================================
 */
$verification = ['status' => 'pending', 'note' => null, 'updated_at' => null];

try {
  $stmt = $pdo->prepare("
    SELECT status, note, updated_at
    FROM medico_verifications
    WHERE medico_id = :id
    LIMIT 1
  ");
  $stmt->execute([':id' => $medicoId]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($row) {
    $verification['status'] = (string)$row['status'];
    $verification['note'] = $row['note'] ?? null;
    $verification['updated_at'] = $row['updated_at'] ?? null;
  } else {
    $ins = $pdo->prepare("INSERT INTO medico_verifications (medico_id, status) VALUES (:id, 'pending')");
    $ins->execute([':id' => $medicoId]);
  }
} catch (Throwable $e) {
  error_log("perfil.php medico_verifications error: " . $e->getMessage());
  $uiErrors[] = perf_t('verification_load_error', 'No se pudo cargar el estado de verificación.');
}

/**
 * ============================================================
 * 2) Upload de documentos (medico_documents)
 * ============================================================
 */
$allowedMime = [
  'application/pdf' => 'pdf',
  'image/jpeg'      => 'jpg',
  'image/png'       => 'png',
];
$maxBytes = 8 * 1024 * 1024; // 8MB

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $postedCsrf = (string)($_POST['csrf_token'] ?? '');
  if (!hash_equals($csrf, $postedCsrf)) {
    $flashErr = perf_t('csrf_invalid', 'Sesión inválida (CSRF). Recarga e inténtalo de nuevo.');
  } else {
    $action = (string)($_POST['action'] ?? 'upload_doc');

    if ($action === 'upload_doc') {
      $docType = trim((string)($_POST['doc_type'] ?? 'Documento'));
      if ($docType === '') $docType = 'Documento';

      if (!isset($_FILES['doc_file']) || !is_array($_FILES['doc_file'])) {
        $flashErr = perf_t('file_not_received', 'No se recibió ningún archivo.');
      } else {
        $f = $_FILES['doc_file'];

        if (($f['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
          $flashErr = perf_t('upload_error_code', 'Error al subir el archivo. Código:') . ' ' . (int)$f['error'];
        } elseif (($f['size'] ?? 0) <= 0 || ($f['size'] ?? 0) > $maxBytes) {
          $flashErr = perf_t('file_max_8mb', 'El archivo debe pesar máximo 8MB.');
        } else {
          $tmp = (string)($f['tmp_name'] ?? '');
          $original = (string)($f['name'] ?? 'archivo');

          $mime = '';
          if (is_uploaded_file($tmp)) {
            $fi = new finfo(FILEINFO_MIME_TYPE);
            $mime = (string)$fi->file($tmp);
          }

          if (!isset($allowedMime[$mime])) {
            $flashErr = perf_t('format_not_allowed', 'Formato no permitido. Solo PDF, JPG o PNG.');
          } else {
            $ext = $allowedMime[$mime];

            $lowerName = strtolower($original);
            if (str_ends_with($lowerName, '.php') || str_ends_with($lowerName, '.phtml') || str_ends_with($lowerName, '.phar')) {
              $flashErr = perf_t('file_not_allowed', 'Archivo no permitido.');
            } else {
              $baseDir = __DIR__ . "/../../uploads/medicos/" . $medicoId;
              if (!is_dir($baseDir)) {
                @mkdir($baseDir, 0755, true);
              }

              $safeBase = preg_replace('/[^a-zA-Z0-9_\-]/', '_', pathinfo($original, PATHINFO_FILENAME));
              $safeBase = trim((string)$safeBase, '_');
              if ($safeBase === '') $safeBase = 'doc';

              $filename = $safeBase . "_" . date('Ymd_His') . "." . $ext;
              $destAbs  = $baseDir . "/" . $filename;

              if (!move_uploaded_file($tmp, $destAbs)) {
                $flashErr = perf_t('save_file_server_error', 'No se pudo guardar el archivo en el servidor.');
              } else {
                $destRel = "/uploads/medicos/" . $medicoId . "/" . $filename;

                try {
                  if (table_exists($pdo, 'medico_documents')) {
                    $stDup = $pdo->prepare("
                      SELECT id FROM medico_documents
                      WHERE medico_id = :mid AND doc_type = :dt AND original_name = :orig
                      LIMIT 1
                    ");
                    $stDup->execute([':mid' => $medicoId, ':dt' => $docType, ':orig' => $original]);
                    if ($stDup->fetchColumn()) {
                      @unlink($destAbs);
                      header("Location: " . $returnBase . "&ok=dup");
                      exit;
                    }
                  }

                  $hasStored = column_exists($pdo, 'medico_documents', 'stored_name');
                  $hasSize   = column_exists($pdo, 'medico_documents', 'size_bytes');

                  if ($hasStored && $hasSize) {
                    $stmt = $pdo->prepare("
                      INSERT INTO medico_documents (medico_id, doc_type, file_path, mime_type, original_name, stored_name, size_bytes, status)
                      VALUES (:mid, :dtype, :path, :mime, :orig, :stored, :size, 'pending')
                    ");
                    $stmt->execute([
                      ':mid'    => $medicoId,
                      ':dtype'  => $docType,
                      ':path'   => $destRel,
                      ':mime'   => $mime,
                      ':orig'   => $original,
                      ':stored' => $filename,
                      ':size'   => (int)($f['size'] ?? 0),
                    ]);
                  } elseif ($hasStored) {
                    $stmt = $pdo->prepare("
                      INSERT INTO medico_documents (medico_id, doc_type, file_path, mime_type, original_name, stored_name, status)
                      VALUES (:mid, :dtype, :path, :mime, :orig, :stored, 'pending')
                    ");
                    $stmt->execute([
                      ':mid'    => $medicoId,
                      ':dtype'  => $docType,
                      ':path'   => $destRel,
                      ':mime'   => $mime,
                      ':orig'   => $original,
                      ':stored' => $filename,
                    ]);
                  } elseif ($hasSize) {
                    $stmt = $pdo->prepare("
                      INSERT INTO medico_documents (medico_id, doc_type, file_path, mime_type, original_name, size_bytes, status)
                      VALUES (:mid, :dtype, :path, :mime, :orig, :size, 'pending')
                    ");
                    $stmt->execute([
                      ':mid'   => $medicoId,
                      ':dtype' => $docType,
                      ':path'  => $destRel,
                      ':mime'  => $mime,
                      ':orig'  => $original,
                      ':size'  => (int)($f['size'] ?? 0),
                    ]);
                  } else {
                    $stmt = $pdo->prepare("
                      INSERT INTO medico_documents (medico_id, doc_type, file_path, mime_type, original_name, status)
                      VALUES (:mid, :dtype, :path, :mime, :orig, 'pending')
                    ");
                    $stmt->execute([
                      ':mid'   => $medicoId,
                      ':dtype' => $docType,
                      ':path'  => $destRel,
                      ':mime'  => $mime,
                      ':orig'  => $original,
                    ]);
                  }

                  header("Location: " . $returnBase . "&ok=1");
                  exit;

                } catch (Throwable $e) {
                  error_log("perfil.php medico_documents insert error: " . $e->getMessage());
                  $flashErr = perf_t('db_register_error', 'El archivo se guardó, pero no se pudo registrar en base de datos.');
                }
              }
            }
          }
        }
      }
    }
  }
}

/**
 * ============================================================
 * 3) Listado de documentos
 * ============================================================
 */
$docs = [];
try {
  $stmt = $pdo->prepare("
    SELECT id, doc_type, file_path, status, created_at, original_name
    FROM medico_documents
    WHERE medico_id = :id
    ORDER BY created_at DESC
    LIMIT 50
  ");
  $stmt->execute([':id' => $medicoId]);
  $docs = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
  error_log("perfil.php medico_documents list error: " . $e->getMessage());
}

if (!function_exists('perf_badge')) {
  function perf_badge(string $status): array {
    $s = strtolower(trim($status));
    return match ($s) {
      'approved' => [perf_t('approved', 'Aprobado'), 'badgeOk'],
      'rejected' => [perf_t('rejected', 'Rechazado'), 'badgeBad'],
      default    => [perf_t('pending', 'Pendiente'), 'note'],
    };
  }
}

$verStatus = strtolower((string)($verification['status'] ?? 'pending'));
$canRequestShifts = ($verStatus === 'approved');
?>

<div class="sectionTitle">
  <div>
    <h2><?= h(perf_t('profile_title', 'Perfil')) ?></h2>
    <div class="muted" style="margin-top:6px;">
      <?= h(perf_t('session', 'Sesión')) ?>: <?= h($medicoEmail) ?> • ID: <?= (int)$medicoId ?>
    </div>
  </div>
</div>

<?php if ($flashOk): ?>
  <div class="flashOk"><?= h($flashOk) ?></div>
<?php endif; ?>

<?php if ($flashErr): ?>
  <div class="flashErr"><?= h($flashErr) ?></div>
<?php endif; ?>

<?php if (!empty($uiErrors)): ?>
  <div class="errBox" style="margin-bottom:12px;">
    <strong><?= h(perf_t('review_following', 'Revisa lo siguiente:')) ?></strong>
    <ul style="margin:8px 0 0; padding-left:18px;">
      <?php foreach ($uiErrors as $e): ?>
        <li><?= h((string)$e) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<div class="grid2">
  <div class="panel">
    <h3 style="margin:0 0 10px;"><?= h(perf_t('verification_status', 'Estado de verificación')) ?></h3>

    <?php
      $label = perf_t('pending', 'Pendiente');
      $badgeClass = 'note';
      if ($verStatus === 'approved') { $label = perf_t('authorized', 'Autorizado'); $badgeClass = 'badgeOk'; }
      if ($verStatus === 'rejected') { $label = perf_t('rejected', 'Rechazado');  $badgeClass = 'badgeBad'; }
    ?>

    <div class="note" style="margin-bottom:12px;">
      <strong><?= h($medicoName) ?></strong><br>
      <span class="muted"><?= h(perf_t('email', 'Email')) ?>: <?= h($medicoEmail) ?></span>
    </div>

    <div class="<?= h($badgeClass) ?>" style="display:inline-flex;">
      <strong><?= h(perf_t('status', 'Estado')) ?>:</strong>&nbsp;<?= h($label) ?>
    </div>

    <div class="divider"></div>

    <div class="muted" style="line-height:1.45;">
      <div><strong class="muted"><?= h(perf_t('last_updated', 'Última actualización')) ?>:</strong> <?= h((string)($verification['updated_at'] ?? '—')) ?></div>
      <div><strong class="muted"><?= h(perf_t('admin_note', 'Nota admin')) ?>:</strong> <?= h((string)($verification['note'] ?? perf_t('no_admin_notes', 'Sin observaciones del administrador.'))) ?></div>
    </div>

    <div class="divider"></div>

    <div class="note">
      <strong><?= h(perf_t('application_access', 'Acceso a postulación')) ?>:</strong><br>
      <?= h($canRequestShifts ? perf_t('application_enabled', 'Habilitado. Ya puedes aplicar a ofertas.') : perf_t('application_blocked', 'Bloqueado. Sube credenciales y espera aprobación.')) ?>
    </div>
  </div>

  <div class="panel">
    <h3 style="margin:0 0 10px;"><?= h(perf_t('upload_credentials', 'Subir credenciales')) ?></h3>
    <div class="muted" style="margin-bottom:12px;">
      <?= h(perf_t('allowed_formats', 'Formatos permitidos: PDF, JPG, PNG • Tamaño máximo: 8MB.')) ?>
    </div>

    <form method="post" action="<?= h(u('index.php')) ?>?tab=perfil&lang=<?= h($perfLang) ?>" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
      <input type="hidden" name="action" value="upload_doc">

      <label><?= h(perf_t('document_type', 'Tipo de documento')) ?></label>
      <select class="select" name="doc_type" required>
        <option value="DNI/NIE"><?= h(perf_t('doc_dni', 'DNI/NIE')) ?></option>
        <option value="Colegiación"><?= h(perf_t('doc_registration', 'Colegiación')) ?></option>
        <option value="Título / Homologación"><?= h(perf_t('doc_degree', 'Título / Homologación')) ?></option>
        <option value="Seguro RC"><?= h(perf_t('doc_insurance', 'Seguro RC')) ?></option>
        <option value="Otros"><?= h(perf_t('doc_other', 'Otros')) ?></option>
      </select>

      <div style="height:10px;"></div>

      <label><?= h(perf_t('file', 'Archivo')) ?></label>
      <input class="input" type="file" name="doc_file"
             accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" required>

      <div style="margin-top:12px;">
        <button class="btn btnGold" type="submit"><?= h(perf_t('upload_and_send', 'Subir y enviar')) ?></button>
      </div>
    </form>
  </div>
</div>

<div style="height:12px;"></div>

<div class="panel">
  <h3 style="margin:0 0 10px;"><?= h(perf_t('sent_documents', 'Documentos enviados')) ?></h3>
  <div class="muted" style="margin-bottom:12px;"><?= h(perf_t('docs_reviewed_admin', 'Se aprueban o rechazan desde el panel del administrador.')) ?></div>

  <?php if (!$docs): ?>
    <div class="note"><?= h(perf_t('no_docs_uploaded', 'Aún no has subido documentos.')) ?></div>
  <?php else: ?>
    <?php foreach ($docs as $d): ?>
      <?php
        [$bText, $bCls] = perf_badge((string)($d['status'] ?? 'pending'));
        $file = (string)($d['file_path'] ?? '#');
        $orig = (string)($d['original_name'] ?? 'archivo');
        $dtype = (string)($d['doc_type'] ?? 'Documento');
        $created = (string)($d['created_at'] ?? '—');
      ?>
      <div class="note" style="margin-bottom:10px;">
        <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:12px; flex-wrap:wrap;">
          <div>
            <strong><?= h(perf_doc_label($dtype)) ?></strong>
            <div class="muted" style="margin-top:6px;"><?= h(perf_t('file_label', 'Archivo')) ?>: <?= h($orig) ?></div>
            <div class="muted"><?= h(perf_t('date', 'Fecha')) ?>: <?= h($created) ?></div>
          </div>

          <div style="min-width:240px;">
            <div class="<?= h($bCls) ?>" style="display:inline-flex; margin-bottom:10px;">
              <?= h($bText) ?>
            </div>
            <div>
              <a class="btn btnGhost" href="<?= h($file) ?>" target="_blank" rel="noopener"><?= h(perf_t('view', 'Ver')) ?></a>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
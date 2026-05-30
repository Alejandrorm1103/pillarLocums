<?php
declare(strict_types=1);

/**
 * ofertas.php — Módulo “Ofertas” (CONTENT-ONLY)
 * ------------------------------------------------------------
 * Se incluye desde index.php cuando tab=ofertas.
 *
 * Función:
 * - Listar ofertas desde jobs
 * - En modo médico: permitir “Solicitar turno” => inserta en job_applications
 *
 * Requiere (normalmente desde index.php):
 * - $pdo (PDO)
 * - $doctorId (int) (si no existe, se toma de sesión)
 * - $PILLAR_DOCTOR_CAN_APPLY (bool) opcional
 *
 * Importante:
 * - NO imprime topbar/tabs/html completo, solo contenido.
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

if (!function_exists('h')) {
  function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
}

/** Base de rutas del módulo (para redirects PRG) */
$BASE = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/');
if ($BASE === '') $BASE = '/usuarios/medico';
if (!function_exists('u')) {
  function u(string $file): string {
    global $BASE;
    return $BASE . '/' . ltrim($file, '/');
  }
}

/* =========================
   I18N LOCAL DEL MÓDULO OFERTAS
========================= */
$offLang = (string)($_SESSION['lang'] ?? 'es');
if (!in_array($offLang, ['es', 'en'], true)) {
  $offLang = 'es';
}

$offDict = [
  'es' => [
    'offers_title' => 'Ofertas',
    'offers_subtitle' => 'Turnos publicados por clínicas y hospitales.',
    'search_placeholder' => 'Buscar por título, ubicación, especialidad...',
    'search' => 'Buscar',
    'clear' => 'Limpiar',

    'success_applied' => 'Solicitud enviada correctamente.',
    'review_following' => 'Revisa lo siguiente:',
    'no_offers_available' => 'No hay ofertas disponibles en este momento.',
    'listing' => 'Listado',

    'posted_by' => 'Publicado por',
    'location' => 'Ubicación',
    'specialty' => 'Especialidad',
    'date' => 'Fecha',
    'time' => 'Hora',
    'rate' => 'Tarifa',

    'clinic' => 'Clínica',
    'hospital' => 'Hospital',
    'publisher' => 'Publicador',

    'request_shift' => 'Solicitar turno',
    'complete_profile_to_apply' => 'Completa Perfil/credenciales para aplicar.',
    'read_only_view' => 'Vista solo lectura',

    'missing_jobs_table' => 'No existe la tabla jobs.',
    'base_load_error' => 'No se pudo cargar Ofertas.',
    'csrf_invalid' => 'Sesión inválida (CSRF). Recarga e inténtalo de nuevo.',
    'invalid_session' => 'Sesión inválida.',
    'invalid_offer' => 'Oferta inválida.',
    'must_complete_credentials' => 'Debes completar tus credenciales en Perfil para poder solicitar turnos.',
    'missing_job_applications_table' => 'Falta la tabla job_applications.',
    'missing_applications_user_col' => 'job_applications no tiene columna doctor_user_id / medico_user_id / user_id.',
    'offer_not_found_or_inactive' => 'La oferta no existe o no está activa.',
    'already_applied' => 'Ya solicitaste este turno.',
    'apply_error' => 'No se pudo enviar la solicitud. Revisa el log del servidor.',
    'load_error' => 'No se pudieron cargar las ofertas.',

    'pending_status_hint' => 'Al “Solicitar turno” se crea un registro en job_applications (estado pending si existe columna status)',
  ],
  'en' => [
    'offers_title' => 'Offers',
    'offers_subtitle' => 'Shifts published by clinics and hospitals.',
    'search_placeholder' => 'Search by title, location, specialty...',
    'search' => 'Search',
    'clear' => 'Clear',

    'success_applied' => 'Application submitted successfully.',
    'review_following' => 'Please review the following:',
    'no_offers_available' => 'There are no offers available at this time.',
    'listing' => 'Listing',

    'posted_by' => 'Posted by',
    'location' => 'Location',
    'specialty' => 'Specialty',
    'date' => 'Date',
    'time' => 'Time',
    'rate' => 'Rate',

    'clinic' => 'Clinic',
    'hospital' => 'Hospital',
    'publisher' => 'Publisher',

    'request_shift' => 'Request shift',
    'complete_profile_to_apply' => 'Complete Profile/credentials to apply.',
    'read_only_view' => 'Read-only view',

    'missing_jobs_table' => 'The jobs table does not exist.',
    'base_load_error' => 'Could not load Offers.',
    'csrf_invalid' => 'Invalid session (CSRF). Refresh and try again.',
    'invalid_session' => 'Invalid session.',
    'invalid_offer' => 'Invalid offer.',
    'must_complete_credentials' => 'You must complete your credentials in Profile before requesting shifts.',
    'missing_job_applications_table' => 'The job_applications table is missing.',
    'missing_applications_user_col' => 'job_applications does not have a doctor_user_id / medico_user_id / user_id column.',
    'offer_not_found_or_inactive' => 'The offer does not exist or is not active.',
    'already_applied' => 'You have already requested this shift.',
    'apply_error' => 'The application could not be sent. Check the server log.',
    'load_error' => 'Offers could not be loaded.',

    'pending_status_hint' => 'Clicking “Request shift” creates a record in job_applications (pending status if the status column exists).',
  ],
];

if (!function_exists('off_t')) {
  function off_t(string $key, string $fallback = ''): string {
    global $offDict, $offLang;

    if (isset($offDict[$offLang][$key])) {
      return (string)$offDict[$offLang][$key];
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

$LANG_QS = '&lang=' . urlencode($offLang);

/* =========================
   Helpers DB (solo si no existen ya)
========================= */
if (!function_exists('table_exists')) {
  function table_exists(PDO $pdo, string $table): bool {
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) return false;
    $st = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($table));
    return (bool)$st->fetchColumn();
  }
}
if (!function_exists('table_columns')) {
  function table_columns(PDO $pdo, string $table): array {
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) return [];
    $cols = [];
    $st = $pdo->query("SHOW COLUMNS FROM `{$table}`");
    foreach (($st->fetchAll(PDO::FETCH_ASSOC) ?: []) as $r) $cols[] = (string)($r['Field'] ?? '');
    return array_values(array_filter($cols));
  }
}
if (!function_exists('pick_col')) {
  function pick_col(array $cols, array $candidates): ?string {
    foreach ($candidates as $c) if (in_array($c, $cols, true)) return $c;
    return null;
  }
}

/* =========================
   Context / Mode
========================= */
$PILLAR_OFFERS_MODE = (string)($PILLAR_OFFERS_MODE ?? 'doctor'); // doctor|clinic|hospital
$doctorId = isset($doctorId) ? (int)$doctorId : (int)($_SESSION['user']['id'] ?? 0);
$canApply = isset($PILLAR_DOCTOR_CAN_APPLY) ? (bool)$PILLAR_DOCTOR_CAN_APPLY : true;

$uiErrors = $uiErrors ?? [];
$uiOk     = $uiOk ?? null;

/** CSRF */
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = (string)$_SESSION['csrf_token'];

/** PRG ok flag */
if (isset($_GET['applied']) && $_GET['applied'] === '1') {
  $uiOk = off_t('success_applied', 'Solicitud enviada correctamente.');
}

/* =========================
   Validaciones base
========================= */
try {
  if (!isset($pdo) || !($pdo instanceof PDO)) throw new RuntimeException("PDO no disponible.");
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  if (!table_exists($pdo, 'jobs')) {
    throw new RuntimeException(off_t('missing_jobs_table', 'No existe la tabla jobs.'));
  }
} catch (Throwable $e) {
  error_log("ofertas.php BASE ERROR: " . $e->getMessage());
  echo '<div class="note"><strong>' . h(off_t('base_load_error', 'No se pudo cargar Ofertas.')) . '</strong><br><span class="muted">' . h($e->getMessage()) . '</span></div>';
  return;
}

/* =========================
   Detectar columnas jobs
========================= */
$jobsCols = table_columns($pdo, 'jobs');

$colId        = 'id';
$colTitle     = pick_col($jobsCols, ['title','name','job_title','puesto','position']);
$colDesc      = pick_col($jobsCols, ['description','details','notes','summary','descripcion']);
$colLocation  = pick_col($jobsCols, ['location','city','place','ubicacion']);
$colStartDate = pick_col($jobsCols, ['start_date','date_start','shift_date','job_date','from_date']);
$colEndDate   = pick_col($jobsCols, ['end_date','date_end','to_date']);
$colStartTime = pick_col($jobsCols, ['start_time','time_start']);
$colEndTime   = pick_col($jobsCols, ['end_time','time_end']);
$colRate      = pick_col($jobsCols, ['rate','pay','salary','price','hourly_rate']);
$colSpecialty = pick_col($jobsCols, ['specialty','speciality','area','especialidad']);
$colCreatedAt = pick_col($jobsCols, ['created_at','created']);
$colStatus    = pick_col($jobsCols, ['status','state']);
$colIsActive  = pick_col($jobsCols, ['is_active','active','enabled']);

// Publicador
$colClinicOwner   = pick_col($jobsCols, ['clinic_user_id','clinic_id']);
$colHospitalOwner = pick_col($jobsCols, ['hospital_user_id','hospital_id']);
$colOwnerUser     = pick_col($jobsCols, ['user_id','owner_user_id','posted_by']);

// users join
$usersCols = table_exists($pdo, 'users') ? table_columns($pdo, 'users') : [];
$uNameCol  = $usersCols ? (pick_col($usersCols, ['name','full_name','display_name']) ?? 'name') : null;
$uRoleCol  = $usersCols ? pick_col($usersCols, ['role','user_role']) : null;

/* =========================
   POST: aplicar a oferta (doctor) + PRG
========================= */
try {
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && $PILLAR_OFFERS_MODE === 'doctor') {
    $postedCsrf = (string)($_POST['csrf_token'] ?? '');
    if (!hash_equals($csrf, $postedCsrf)) {
      $uiErrors[] = off_t('csrf_invalid', 'Sesión inválida (CSRF). Recarga e inténtalo de nuevo.');
    } else {
      $action = (string)($_POST['action'] ?? '');

      if ($action === 'apply_job') {
        $jobId = (int)($_POST['job_id'] ?? 0);

        if ($doctorId <= 0) $uiErrors[] = off_t('invalid_session', 'Sesión inválida.');
        if ($jobId <= 0) $uiErrors[] = off_t('invalid_offer', 'Oferta inválida.');

        if (!$canApply) {
          $uiErrors[] = off_t('must_complete_credentials', 'Debes completar tus credenciales en Perfil para poder solicitar turnos.');
        }

        if (!$uiErrors) {
          if (!table_exists($pdo, 'job_applications')) {
            $uiErrors[] = off_t('missing_job_applications_table', 'Falta la tabla job_applications.');
          } else {
            $appCols = table_columns($pdo, 'job_applications');
            $appJobCol    = pick_col($appCols, ['job_id']) ?? 'job_id';
            $appDocCol    = pick_col($appCols, ['doctor_user_id','medico_user_id','user_id']);
            $appStatusCol = pick_col($appCols, ['status','state']);

            if ($appDocCol === null) {
              $uiErrors[] = off_t('missing_applications_user_col', 'job_applications no tiene columna doctor_user_id / medico_user_id / user_id.');
            } else {
              // Validar que la oferta existe y está activa
              $w = ["`{$colId}`=?"];
              $p = [$jobId];

              if ($colStatus !== null) {
                $w[] = "`{$colStatus}` IN ('active','open','published')";
              } elseif ($colIsActive !== null) {
                $w[] = "`{$colIsActive}`=1";
              }

              $stJ = $pdo->prepare("SELECT `{$colId}` FROM `jobs` WHERE " . implode(' AND ', $w) . " LIMIT 1");
              $stJ->execute($p);

              if (!$stJ->fetchColumn()) {
                $uiErrors[] = off_t('offer_not_found_or_inactive', 'La oferta no existe o no está activa.');
              } else {
                // Evitar duplicados
                $stC = $pdo->prepare("SELECT id FROM `job_applications` WHERE `{$appJobCol}`=? AND `{$appDocCol}`=? LIMIT 1");
                $stC->execute([$jobId, $doctorId]);

                if ($stC->fetchColumn()) {
                  $uiErrors[] = off_t('already_applied', 'Ya solicitaste este turno.');
                } else {
                  $fields = [
                    $appJobCol => $jobId,
                    $appDocCol => $doctorId,
                  ];
                  if ($appStatusCol !== null) $fields[$appStatusCol] = 'pending';

                  $colsSql = implode(',', array_map(fn($c) => "`{$c}`", array_keys($fields)));
                  $valsSql = implode(',', array_fill(0, count($fields), '?'));

                  $sqlIns = "INSERT INTO `job_applications` ({$colsSql}) VALUES ({$valsSql})";
                  $stI = $pdo->prepare($sqlIns);
                  $stI->execute(array_values($fields));

                  // PRG: redirige a la misma vista (con tab=ofertas)
                  $q = trim((string)($_GET['q'] ?? ''));
                  $qs = "tab=ofertas&applied=1&lang=" . urlencode($offLang);
                  if ($q !== '') $qs .= "&q=" . urlencode($q);

                  header("Location: " . u("index.php") . "?" . $qs);
                  exit;
                }
              }
            }
          }
        }
      }
    }
  }
} catch (Throwable $e) {
  error_log("ofertas.php APPLY ERROR: " . $e->getMessage());
  $uiErrors[] = off_t('apply_error', 'No se pudo enviar la solicitud. Revisa el log del servidor.');
}

/* =========================
   Cargar ofertas
========================= */
$jobs = [];

try {
  $select = ["j.`{$colId}` AS id"];

  if ($colTitle)     $select[] = "j.`{$colTitle}` AS title";
  if ($colDesc)      $select[] = "j.`{$colDesc}` AS description";
  if ($colLocation)  $select[] = "j.`{$colLocation}` AS location";
  if ($colStartDate) $select[] = "j.`{$colStartDate}` AS start_date";
  if ($colEndDate)   $select[] = "j.`{$colEndDate}` AS end_date";
  if ($colStartTime) $select[] = "j.`{$colStartTime}` AS start_time";
  if ($colEndTime)   $select[] = "j.`{$colEndTime}` AS end_time";
  if ($colRate)      $select[] = "j.`{$colRate}` AS rate";
  if ($colSpecialty) $select[] = "j.`{$colSpecialty}` AS specialty";
  if ($colCreatedAt) $select[] = "j.`{$colCreatedAt}` AS created_at";
  if ($colStatus)    $select[] = "j.`{$colStatus}` AS status";
  if ($colIsActive)  $select[] = "j.`{$colIsActive}` AS is_active";

  // Publisher join
  $join = "";
  $publisherExpr = "''";
  $publisherRoleExpr = "''";

  if ($uNameCol !== null && table_exists($pdo, 'users')) {
    $ownerForJoin = $colClinicOwner ?? $colHospitalOwner ?? $colOwnerUser;
    if ($ownerForJoin !== null && in_array($ownerForJoin, $jobsCols, true)) {
      $join = " LEFT JOIN `users` u ON u.`id` = j.`{$ownerForJoin}` ";
      $publisherExpr = "COALESCE(u.`{$uNameCol}`, '')";
      if ($uRoleCol !== null) $publisherRoleExpr = "COALESCE(u.`{$uRoleCol}`, '')";
    }
  }

  $select[] = "{$publisherExpr} AS publisher_name";
  $select[] = "{$publisherRoleExpr} AS publisher_role";

  $select[] = "CASE
      WHEN " . ($colClinicOwner ? "j.`{$colClinicOwner}` IS NOT NULL AND j.`{$colClinicOwner}`<>0" : "0") . " THEN 'clinica'
      WHEN " . ($colHospitalOwner ? "j.`{$colHospitalOwner}` IS NOT NULL AND j.`{$colHospitalOwner}`<>0" : "0") . " THEN 'hospital'
      ELSE 'publicador'
    END AS publisher_type";

  $where = [];
  $params = [];

  // Doctor ve solo activas por defecto
  if ($PILLAR_OFFERS_MODE === 'doctor') {
    if ($colStatus !== null) $where[] = "j.`{$colStatus}` IN ('active','open','published')";
    elseif ($colIsActive !== null) $where[] = "j.`{$colIsActive}`=1";
  }

  // Búsqueda (?q=)
  $q = trim((string)($_GET['q'] ?? ''));
  if ($q !== '') {
    $like = "%{$q}%";
    $parts = [];
    if ($colTitle)     $parts[] = "j.`{$colTitle}` LIKE ?";
    if ($colLocation)  $parts[] = "j.`{$colLocation}` LIKE ?";
    if ($colSpecialty) $parts[] = "j.`{$colSpecialty}` LIKE ?";
    if ($colDesc)      $parts[] = "j.`{$colDesc}` LIKE ?";

    if ($parts) {
      $where[] = "(" . implode(" OR ", $parts) . ")";
      foreach ($parts as $_) $params[] = $like;
    }
  }

  $sql = "SELECT " . implode(", ", $select) . " FROM `jobs` j {$join}";
  if ($where) $sql .= " WHERE " . implode(" AND ", $where);

  if ($colCreatedAt) $sql .= " ORDER BY j.`{$colCreatedAt}` DESC, j.`{$colId}` DESC";
  else $sql .= " ORDER BY j.`{$colId}` DESC";

  $st = $pdo->prepare($sql);
  $st->execute($params);
  $jobs = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

} catch (Throwable $e) {
  error_log("ofertas.php LOAD ERROR: " . $e->getMessage());
  $uiErrors[] = off_t('load_error', 'No se pudieron cargar las ofertas.');
}

/* =========================
   RENDER (content-only)
========================= */
?>
<div class="sectionTitle">
  <div>
    <h2><?= h(off_t('offers_title', 'Ofertas')) ?></h2>
    <div class="muted" style="margin-top:6px;"><?= h(off_t('offers_subtitle', 'Turnos publicados por clínicas y hospitales.')) ?></div>
  </div>

  <form method="get" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
    <input type="hidden" name="tab" value="ofertas">
    <input type="hidden" name="lang" value="<?= h($offLang) ?>">
    <input
      class="input"
      name="q"
      value="<?= h((string)($_GET['q'] ?? '')) ?>"
      placeholder="<?= h(off_t('search_placeholder', 'Buscar por título, ubicación, especialidad...')) ?>"
      style="min-width:260px;"
    >
    <button class="btn btnGhost" type="submit"><?= h(off_t('search', 'Buscar')) ?></button>
    <?php if (trim((string)($_GET['q'] ?? '')) !== ''): ?>
      <a class="btn btnGhost" href="?tab=ofertas<?= $LANG_QS ?>"><?= h(off_t('clear', 'Limpiar')) ?></a>
    <?php endif; ?>
  </form>
</div>

<?php if (!empty($uiOk)): ?>
  <div class="note" style="border-color: rgba(46,204,113,.25); background: rgba(46,204,113,.08); margin: 12px 0;">
    <?= h((string)$uiOk) ?>
  </div>
<?php endif; ?>

<?php if (!empty($uiErrors)): ?>
  <div class="errBox" style="margin: 12px 0;">
    <strong><?= h(off_t('review_following', 'Revisa lo siguiente:')) ?></strong>
    <ul style="margin:8px 0 0; padding-left:18px;">
      <?php foreach ($uiErrors as $e): ?>
        <li><?= h((string)$e) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<?php if (!$jobs): ?>
  <div class="note"><?= h(off_t('no_offers_available', 'No hay ofertas disponibles en este momento.')) ?></div>
  <?php return; ?>
<?php endif; ?>

<div class="grid2" style="grid-template-columns: 1fr;">
  <div class="panel">
    <h3 style="margin:0 0 10px;"><?= h(off_t('listing', 'Listado')) ?></h3>

    <?php foreach ($jobs as $j): ?>
      <?php
        $jid   = (int)($j['id'] ?? 0);
        $title = (string)($j['title'] ?? ('Oferta #' . $jid));
        $loc   = (string)($j['location'] ?? '');
        $spec  = (string)($j['specialty'] ?? '');
        $sd    = (string)($j['start_date'] ?? '');
        $ed    = (string)($j['end_date'] ?? '');
        $stt   = (string)($j['start_time'] ?? '');
        $ett   = (string)($j['end_time'] ?? '');
        $rate  = (string)($j['rate'] ?? '');
        $pubN  = (string)($j['publisher_name'] ?? '');
        $pubT  = (string)($j['publisher_type'] ?? '');
        $pubR  = (string)($j['publisher_role'] ?? '');

        $who = $pubN !== ''
          ? $pubN
          : (($pubT === 'clinica')
              ? off_t('clinic', 'Clínica')
              : (($pubT === 'hospital')
                  ? off_t('hospital', 'Hospital')
                  : off_t('publisher', 'Publicador')));

        $when = trim($sd);
        if ($ed !== '' && $ed !== $sd) $when .= ($when !== '' ? ' → ' : '') . $ed;

        $time = trim($stt);
        if ($ett !== '' && $ett !== $stt) $time .= ($time !== '' ? ' - ' : '') . $ett;
      ?>

      <div class="note" style="margin-bottom:12px;">
        <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:12px; flex-wrap:wrap;">
          <div>
            <strong><?= h($title) ?></strong>
            <div class="muted" style="margin-top:6px;">
              <?= h(off_t('posted_by', 'Publicado por')) ?>: <?= h($who) ?><?= ($pubR !== '' ? ' • ' . h($pubR) : '') ?>
            </div>

            <div class="muted" style="margin-top:10px; line-height:1.45;">
              <?php if ($loc !== ''): ?><div><strong class="muted"><?= h(off_t('location', 'Ubicación')) ?>:</strong> <?= h($loc) ?></div><?php endif; ?>
              <?php if ($spec !== ''): ?><div><strong class="muted"><?= h(off_t('specialty', 'Especialidad')) ?>:</strong> <?= h($spec) ?></div><?php endif; ?>
              <?php if ($when !== ''): ?><div><strong class="muted"><?= h(off_t('date', 'Fecha')) ?>:</strong> <?= h($when) ?></div><?php endif; ?>
              <?php if ($time !== ''): ?><div><strong class="muted"><?= h(off_t('time', 'Hora')) ?>:</strong> <?= h($time) ?></div><?php endif; ?>
              <?php if ($rate !== ''): ?><div><strong class="muted"><?= h(off_t('rate', 'Tarifa')) ?>:</strong> <?= h($rate) ?></div><?php endif; ?>
            </div>
          </div>

          <div style="min-width:220px;">
            <?php if ($PILLAR_OFFERS_MODE === 'doctor'): ?>
              <form method="post" style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                <input type="hidden" name="action" value="apply_job">
                <input type="hidden" name="job_id" value="<?= (int)$jid ?>">
                <button class="btn btnGold" type="submit" <?= $canApply ? '' : 'disabled style="opacity:.55; cursor:not-allowed;"' ?>>
                  <?= h(off_t('request_shift', 'Solicitar turno')) ?>
                </button>
              </form>

              <?php if (!$canApply): ?>
                <div class="muted" style="margin-top:8px; font-size:.85rem;">
                  <?= h(off_t('complete_profile_to_apply', 'Completa Perfil/credenciales para aplicar.')) ?>
                </div>
              <?php endif; ?>
            <?php else: ?>
              <div class="muted"><?= h(off_t('read_only_view', 'Vista solo lectura')) ?></div>
            <?php endif; ?>
          </div>
        </div>
      </div>

    <?php endforeach; ?>

    <?php if (isset($_GET['debug']) && $_GET['debug'] === '1'): ?>
      <div class="note">
        <?= h(off_t('pending_status_hint', 'Al “Solicitar turno” se crea un registro en job_applications (estado pending si existe columna status)')) ?>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

if (!function_exists('h')) {
  function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
  }
}

/** Base URL del módulo */
$BASE = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/');
if ($BASE === '') $BASE = '/usuarios/medico';
if (!function_exists('u')) {
  function u(string $file): string {
    global $BASE;
    return $BASE . '/' . ltrim($file, '/');
  }
}

/** Si este módulo se abre directo (no desde index), garantizamos auth/db */
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

/* ============================================================
   I18N LOCAL DEL MÓDULO SOLICITUDES
   - evita depender de claves externas incompletas
============================================================ */
$solLang = (string)($_SESSION['lang'] ?? 'es');
if (!in_array($solLang, ['es', 'en'], true)) {
  $solLang = 'es';
}

$solDict = [
  'es' => [
    'requests_title' => 'Mis solicitudes',
    'session' => 'Sesión',
    'filters_all' => 'Todas',
    'filters_pending' => 'Pendientes',
    'filters_active' => 'Activas',
    'filters_rejected' => 'Rechazadas',

    'review_following' => 'Revisa lo siguiente:',

    'authorization_status' => 'Estado de autorización',
    'authorization_text' => 'Para postular a ofertas debes estar Autorizado y Activo.',
    'verification_label' => 'Verificación',
    'active_status_label' => 'Activo',
    'can_apply_now' => 'Puedes postular',
    'cannot_apply_yet' => 'No puedes postular aún',

    'go_to_profile' => 'Ir a Perfil',
    'view_offers' => 'Ver ofertas',

    'showing_results' => 'Mostrando',
    'total_results' => 'Total',

    'offer_id' => 'ID oferta',
    'notes_optional' => 'Notas (opcional)',
    'applied_at' => 'Aplicaste',

    'missing_job_applications_table' => 'Falta la tabla job_applications. Sin ella no se pueden registrar solicitudes.',
    'no_requests_yet' => 'Aún no tienes solicitudes registradas.',

    'cannot_apply_pending_and_inactive' => 'No puedes postular todavía: tu perfil está pendiente de autorización y además no está activo.',
    'cannot_apply_pending_authorization' => 'No puedes postular todavía: tu perfil está pendiente de autorización por el administrador.',
    'cannot_apply_inactive' => 'No puedes postular todavía: tu cuenta no está activa. Pide activación al administrador.',
    'cannot_apply_generic' => 'No puedes postular por ahora.',

    'authorized_and_active_text' => 'Estás autorizado y activo. En “Ofertas” podrás aplicar de forma inmediata y aquí verás el seguimiento.',

    'status_active_short' => 'Activa',
    'status_rejected_short' => 'Rechazada',
    'status_pending_short' => 'Pendiente',

    'approved_status' => 'Aprobado',
    'pending_status' => 'Pendiente',
    'rejected_status' => 'Rechazado',

    'yes' => 'Sí',
    'no' => 'No',
  ],
  'en' => [
    'requests_title' => 'My applications',
    'session' => 'Session',
    'filters_all' => 'All',
    'filters_pending' => 'Pending',
    'filters_active' => 'Active',
    'filters_rejected' => 'Rejected',

    'review_following' => 'Please review the following:',

    'authorization_status' => 'Authorization status',
    'authorization_text' => 'To apply for offers you must be Authorized and Active.',
    'verification_label' => 'Verification',
    'active_status_label' => 'Active',
    'can_apply_now' => 'You can apply',
    'cannot_apply_yet' => 'You cannot apply yet',

    'go_to_profile' => 'Go to Profile',
    'view_offers' => 'View offers',

    'showing_results' => 'Showing',
    'total_results' => 'Total',

    'offer_id' => 'Offer ID',
    'notes_optional' => 'Notes (optional)',
    'applied_at' => 'Applied on',

    'missing_job_applications_table' => 'The job_applications table is missing. Without it, applications cannot be registered.',
    'no_requests_yet' => 'You do not have any registered applications yet.',

    'cannot_apply_pending_and_inactive' => 'You cannot apply yet: your profile is still pending authorization and is also not active.',
    'cannot_apply_pending_authorization' => 'You cannot apply yet: your profile is still pending administrator authorization.',
    'cannot_apply_inactive' => 'You cannot apply yet: your account is not active. Ask the administrator for activation.',
    'cannot_apply_generic' => 'You cannot apply for now.',

    'authorized_and_active_text' => 'You are authorized and active. In “Offers” you can apply immediately, and here you will see the follow-up.',

    'status_active_short' => 'Active',
    'status_rejected_short' => 'Rejected',
    'status_pending_short' => 'Pending',

    'approved_status' => 'Approved',
    'pending_status' => 'Pending',
    'rejected_status' => 'Rejected',

    'yes' => 'Yes',
    'no' => 'No',
  ],
];

if (!function_exists('sol_t')) {
  function sol_t(string $key, string $fallback = ''): string {
    global $solDict, $solLang;

    if (isset($solDict[$solLang][$key])) {
      return (string)$solDict[$solLang][$key];
    }

    if (function_exists('md_t')) {
      return md_t($key, $fallback !== '' ? $fallback : $key);
    }

    if (function_exists('t')) {
      $v = t($key);
      if ($v !== $key) {
        return (string)$v;
      }
    }

    return $fallback !== '' ? $fallback : $key;
  }
}

$LANG_QS = '&lang=' . urlencode($solLang);

/** Helpers DB mínimos (si no existen ya por index.php) */
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
    foreach (($st->fetchAll(PDO::FETCH_ASSOC) ?: []) as $r) {
      $cols[] = (string)($r['Field'] ?? '');
    }
    return array_values(array_filter($cols));
  }
}
if (!function_exists('pick_col')) {
  function pick_col(array $cols, array $candidates): ?string {
    foreach ($candidates as $c) {
      if (in_array($c, $cols, true)) return $c;
    }
    return null;
  }
}

/** PDO exceptions */
try {
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Throwable $e) {}

/* ============================================================
   1) Gating autorización (admin)
   - medico_verifications.status = 'approved'
   - medico_profile.is_active = 1
============================================================ */
$verStatus = 'pending';
$isActive  = 0;

try {
  if (table_exists($pdo, 'medico_verifications')) {
    $stmt = $pdo->prepare("SELECT status FROM medico_verifications WHERE medico_id = :id LIMIT 1");
    $stmt->execute([':id' => $medicoId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
      $verStatus = strtolower((string)$row['status']);
    }
  }
} catch (Throwable $e) {
  error_log("solicitudes.php verif error: " . $e->getMessage());
}

try {
  if (table_exists($pdo, 'medico_profile')) {
    $stmt = $pdo->prepare("SELECT is_active FROM medico_profile WHERE medico_id = :id LIMIT 1");
    $stmt->execute([':id' => $medicoId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
      $isActive = (int)($row['is_active'] ?? 0);
    }
  }
} catch (Throwable $e) {
  error_log("solicitudes.php profile error: " . $e->getMessage());
}

/*
  FIX:
  Si admin ya aprobó al médico, sincronizamos activo=1.
*/
if ($verStatus === 'approved' && $isActive !== 1) {
  try {
    if (table_exists($pdo, 'medico_profile')) {
      $stmt = $pdo->prepare("UPDATE medico_profile SET is_active = 1 WHERE medico_id = :id LIMIT 1");
      $stmt->execute([':id' => $medicoId]);
    }
  } catch (Throwable $e) {
    error_log("solicitudes.php sync active error: " . $e->getMessage());
  }

  $isActive = 1;
}

$canApply = ($verStatus === 'approved' && $isActive === 1);

$verStatusLabel = match ($verStatus) {
  'approved' => sol_t('approved_status', 'Aprobado'),
  'pending'  => sol_t('pending_status', 'Pendiente'),
  'rejected' => sol_t('rejected_status', 'Rechazado'),
  default    => $verStatus,
};

/* ============================================================
   2) Filtro por estado
============================================================ */
$allowedFilter = ['all','pending','active','rejected'];
$filter = strtolower((string)($_GET['status'] ?? 'all'));
if (!in_array($filter, $allowedFilter, true)) {
  $filter = 'all';
}

$filterLabel = match ($filter) {
  'pending'  => sol_t('filters_pending', 'Pendientes'),
  'active'   => sol_t('filters_active', 'Activas'),
  'rejected' => sol_t('filters_rejected', 'Rechazadas'),
  default    => sol_t('filters_all', 'Todas'),
};

/* ============================================================
   3) Query solicitudes (job_applications + jobs)
============================================================ */
$applications = [];

try {
  if (!table_exists($pdo, 'job_applications') || !table_exists($pdo, 'jobs')) {
    // si no existen tablas, lo mostramos vacío con nota
  } else {
    $appCols = table_columns($pdo, 'job_applications');
    $jobCols = table_columns($pdo, 'jobs');

    $appJobCol     = pick_col($appCols, ['job_id']) ?? 'job_id';
    $appUserCol    = pick_col($appCols, ['doctor_user_id','medico_user_id','user_id']);
    $appStatusCol  = pick_col($appCols, ['status','state']);
    $appNoteCol    = pick_col($appCols, ['note','admin_note','notes']);
    $appCreatedCol = pick_col($appCols, ['created_at','applied_at','created']);

    if ($appUserCol === null) {
      $uiErrors[] = "job_applications no tiene columna doctor_user_id / medico_user_id / user_id.";
    } else {
      $jobTitleCol   = pick_col($jobCols, ['title','name','job_title']) ?? 'id';
      $jobLocCol     = pick_col($jobCols, ['location','city','place','ubicacion']);
      $jobStartCol   = pick_col($jobCols, ['start_date','date_start','shift_date','job_date']);
      $jobEndCol     = pick_col($jobCols, ['end_date','date_end']);
      $jobStatusCol  = pick_col($jobCols, ['status','state']);
      $jobCreatedCol = pick_col($jobCols, ['created_at','created']);

      $select = [
        "a.`id` AS application_id",
        "a.`{$appJobCol}` AS job_id"
      ];

      if ($appStatusCol)  $select[] = "a.`{$appStatusCol}` AS application_status";
      else                $select[] = "'pending' AS application_status";

      if ($appNoteCol)    $select[] = "a.`{$appNoteCol}` AS application_note";
      else                $select[] = "'' AS application_note";

      if ($appCreatedCol) $select[] = "a.`{$appCreatedCol}` AS applied_at";
      else                $select[] = "NULL AS applied_at";

      $select[] = "j.`id` AS offer_id";
      $select[] = "j.`{$jobTitleCol}` AS offer_title";

      if ($jobLocCol)   $select[] = "j.`{$jobLocCol}` AS location";
      else              $select[] = "'' AS location";

      if ($jobStartCol) $select[] = "j.`{$jobStartCol}` AS start_date";
      else              $select[] = "NULL AS start_date";

      if ($jobEndCol)   $select[] = "j.`{$jobEndCol}` AS end_date";
      else              $select[] = "NULL AS end_date";

      if ($jobStatusCol) $select[] = "j.`{$jobStatusCol}` AS offer_status";
      else               $select[] = "'' AS offer_status";

      $sql = "SELECT " . implode(", ", $select) . "
              FROM `job_applications` a
              JOIN `jobs` j ON j.`id` = a.`{$appJobCol}`
              WHERE a.`{$appUserCol}` = :mid";

      $params = [':mid' => $medicoId];

      if ($filter !== 'all' && $appStatusCol) {
        $sql .= " AND a.`{$appStatusCol}` = :st";
        $params[':st'] = $filter;
      }

      $orderCol = $appCreatedCol ? "a.`{$appCreatedCol}`" : "a.`id`";
      $sql .= " ORDER BY {$orderCol} DESC LIMIT 100";

      $stmt = $pdo->prepare($sql);
      $stmt->execute($params);
      $applications = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
  }
} catch (Throwable $e) {
  error_log("solicitudes.php load error: " . $e->getMessage());
  $uiErrors[] = "No se pudieron cargar tus solicitudes.";
}

function badge(string $status): array {
  $s = strtolower(trim($status));
  return match ($s) {
    'active', 'approved', 'accepted' => [sol_t('status_active_short', 'Activa'), 'badgeOk'],
    'rejected', 'denied'             => [sol_t('status_rejected_short', 'Rechazada'), 'badgeBad'],
    default                          => [sol_t('status_pending_short', 'Pendiente'), 'note'],
  };
}

/* ============================================================
   RENDER (content-only)
============================================================ */
?>

<div class="sectionTitle">
  <div>
    <h2><?= h(sol_t('requests_title', 'Mis solicitudes')) ?></h2>
    <div class="muted" style="margin-top:6px;">
      <?= h(sol_t('session', 'Sesión')) ?>: <?= h($medicoEmail) ?> • ID: <?= (int)$medicoId ?>
    </div>
  </div>

  <div style="display:flex; gap:10px; flex-wrap:wrap;">
    <a class="btn btnGhost" href="?tab=solicitudes&status=all<?= $LANG_QS ?>"><?= h(sol_t('filters_all', 'Todas')) ?></a>
    <a class="btn btnGhost" href="?tab=solicitudes&status=pending<?= $LANG_QS ?>"><?= h(sol_t('filters_pending', 'Pendientes')) ?></a>
    <a class="btn btnGhost" href="?tab=solicitudes&status=active<?= $LANG_QS ?>"><?= h(sol_t('filters_active', 'Activas')) ?></a>
    <a class="btn btnGhost" href="?tab=solicitudes&status=rejected<?= $LANG_QS ?>"><?= h(sol_t('filters_rejected', 'Rechazadas')) ?></a>
  </div>
</div>

<?php if (!empty($uiErrors)): ?>
  <div class="errBox" style="margin-bottom:12px;">
    <strong><?= h(sol_t('review_following', 'Revisa lo siguiente:')) ?></strong>
    <ul style="margin:8px 0 0; padding-left:18px;">
      <?php foreach ($uiErrors as $e): ?>
        <li><?= h((string)$e) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<div class="panel" style="margin-bottom:12px;">
  <div class="note" style="margin:0;">
    <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:12px; flex-wrap:wrap;">
      <div>
        <strong><?= h(sol_t('authorization_status', 'Estado de autorización')) ?></strong>
        <div class="muted" style="margin-top:6px;">
          <?= h(sol_t('authorization_text', 'Para postular a ofertas debes estar Autorizado y Activo.')) ?>
        </div>

        <div style="margin-top:10px; display:flex; gap:10px; flex-wrap:wrap;">
          <span class="note"><?= h(sol_t('verification_label', 'Verificación')) ?>: <strong><?= h($verStatusLabel) ?></strong></span>
          <span class="note"><?= h(sol_t('active_status_label', 'Activo')) ?>: <strong><?= $isActive ? h(sol_t('yes', 'Sí')) : h(sol_t('no', 'No')) ?></strong></span>
          <span class="<?= $canApply ? 'badgeOk' : 'badgeBad' ?>">
            <?= $canApply ? h(sol_t('can_apply_now', 'Puedes postular')) : h(sol_t('cannot_apply_yet', 'No puedes postular aún')) ?>
          </span>
        </div>
      </div>

      <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
        <a class="btn btnGhost" href="?tab=perfil<?= $LANG_QS ?>"><?= h(sol_t('go_to_profile', 'Ir a Perfil')) ?></a>
        <a class="btn btnGold" href="?tab=ofertas<?= $LANG_QS ?>"><?= h(sol_t('view_offers', 'Ver ofertas')) ?></a>
      </div>
    </div>
  </div>
</div>

<div class="panel">
  <div class="muted" style="margin-bottom:10px;">
    <?= h(sol_t('showing_results', 'Mostrando')) ?>: <strong><?= h($filterLabel) ?></strong> • <?= h(sol_t('total_results', 'Total')) ?>: <strong><?= (int)count($applications) ?></strong>
  </div>

  <?php if (!table_exists($pdo, 'job_applications')): ?>
    <div class="note">
      <?= h(sol_t('missing_job_applications_table', 'Falta la tabla job_applications. Sin ella no se pueden registrar solicitudes.')) ?>
    </div>
  <?php elseif (!$applications): ?>
    <div class="note"><?= h(sol_t('no_requests_yet', 'Aún no tienes solicitudes registradas.')) ?></div>
  <?php else: ?>

    <?php foreach ($applications as $a): ?>
      <?php
        [$bText, $bCls] = badge((string)($a['application_status'] ?? 'pending'));

        $offerId    = (int)($a['offer_id'] ?? $a['job_id'] ?? 0);
        $offerTitle = (string)($a['offer_title'] ?? ('Oferta #' . $offerId));
        $loc        = (string)($a['location'] ?? '');
        $start      = (string)($a['start_date'] ?? '');
        $end        = (string)($a['end_date'] ?? '');
        $appliedAt  = (string)($a['applied_at'] ?? '—');
        $note       = (string)($a['application_note'] ?? '');

        $when = trim($start);
        if ($end !== '' && $end !== $start) $when .= ($when !== '' ? ' → ' : '') . $end;
      ?>

      <div class="note" style="margin-bottom:10px;">
        <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:12px; flex-wrap:wrap;">
          <div>
            <strong><?= h($offerTitle) ?></strong>
            <div class="muted" style="margin-top:6px;">
              <?= h(sol_t('offer_id', 'ID oferta')) ?>: <?= (int)$offerId ?>
              <?= $loc !== '' ? ' • ' . h($loc) : '' ?>
              <?= $when !== '' ? ' • ' . h($when) : '' ?>
            </div>

            <?php if ($note !== ''): ?>
              <div class="muted" style="margin-top:8px;"><?= h(sol_t('notes_optional', 'Notas (opcional)')) ?>: <?= h($note) ?></div>
            <?php endif; ?>

            <div class="muted" style="margin-top:8px;"><?= h(sol_t('applied_at', 'Aplicaste')) ?>: <?= h($appliedAt) ?></div>
          </div>

          <div style="min-width:240px;">
            <div class="<?= h($bCls) ?>" style="display:inline-flex; margin-bottom:10px;">
              <?= h($bText) ?>
            </div>
            <div>
              <a class="btn btnGhost" href="?tab=ofertas<?= $LANG_QS ?>"><?= h(sol_t('view_offers', 'Ver ofertas')) ?></a>
            </div>
          </div>
        </div>
      </div>

    <?php endforeach; ?>

  <?php endif; ?>

  <div class="divider"></div>

  <?php if (!$canApply): ?>
    <div class="muted">
      <?php if ($verStatus !== 'approved' && $isActive !== 1): ?>
        <?= h(sol_t('cannot_apply_pending_and_inactive', 'No puedes postular todavía: tu perfil está pendiente de autorización y además no está activo.')) ?>
      <?php elseif ($verStatus !== 'approved'): ?>
        <?= h(sol_t('cannot_apply_pending_authorization', 'No puedes postular todavía: tu perfil está pendiente de autorización por el administrador.')) ?>
      <?php elseif ($isActive !== 1): ?>
        <?= h(sol_t('cannot_apply_inactive', 'No puedes postular todavía: tu cuenta no está activa. Pide activación al administrador.')) ?>
      <?php else: ?>
        <?= h(sol_t('cannot_apply_generic', 'No puedes postular por ahora.')) ?>
      <?php endif; ?>

      <div style="margin-top:10px; display:flex; gap:10px; flex-wrap:wrap;">
        <a class="btn btnGhost" href="?tab=perfil<?= $LANG_QS ?>"><?= h(sol_t('go_to_profile', 'Ir a Perfil')) ?></a>
        <a class="btn btnGold" href="?tab=ofertas<?= $LANG_QS ?>"><?= h(sol_t('view_offers', 'Ver ofertas')) ?></a>
      </div>
    </div>
  <?php else: ?>
    <div class="muted">
      <?= h(sol_t('authorized_and_active_text', 'Estás autorizado y activo. En “Ofertas” podrás aplicar de forma inmediata y aquí verás el seguimiento.')) ?>
    </div>
  <?php endif; ?>
</div>
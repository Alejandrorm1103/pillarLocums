<?php
declare(strict_types=1);

require_once __DIR__ . "/../../config/auth.php";
require_login();

// DB
require_once __DIR__ . "/../db.php";

// Seguridad: solo hospital
$user = $_SESSION['user'] ?? null;
if (!$user || ($user['role'] ?? '') !== 'hospital') {
  header("Location: /usuarios/dashboard.php");
  exit;
}

// Debug opcional (solo si entras con &debug=1)
if (isset($_GET['debug']) && $_GET['debug'] === '1') {
  ini_set('display_errors', '1');
  ini_set('display_startup_errors', '1');
  error_reporting(E_ALL);
}

if (!function_exists('h')) {
  function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
  }
}

$hospitalId    = (int)($user['id'] ?? 0);
$hospitalName  = (string)($user['name'] ?? 'Hospital');
$hospitalEmail = (string)($user['email'] ?? '');

// Alias para compatibilidad con módulos clonados de clínica
$clinicId    = $hospitalId;
$clinicName  = $hospitalName;
$clinicEmail = $hospitalEmail;

// Tabs
$allowedTabs = ['ofertas', 'calendario', 'espacio', 'perfil', 'aplicantes', 'contactos'];
$tab = (string)($_GET['tab'] ?? 'ofertas');
if (!in_array($tab, $allowedTabs, true)) {
  $tab = 'ofertas';
}

$uiErrors = [];
$uiOk = null;

// =====================
// I18N
// =====================
if (!function_exists('pl_t')) {
  function pl_t(string $key, ?string $fallback = null): string {
    global $plLang, $plDict;
    return $plDict[$plLang][$key] ?? ($fallback ?? $key);
  }
}

if (!function_exists('pl_lang_url')) {
  function pl_lang_url(string $lang): string {
    $params = $_GET;
    $params['lang'] = $lang;
    return '?' . http_build_query($params);
  }
}

$plLang = (string)($_GET['lang'] ?? $_SESSION['lang'] ?? 'es');
$plLang = ($plLang === 'en') ? 'en' : 'es';
$_SESSION['lang'] = $plLang;

$plNextLang = ($plLang === 'es') ? 'en' : 'es';
$plNextLangLabel = strtoupper($plNextLang);

$plDict = [
  'es' => [
    'brand_tagline' => 'Locums • Supporting Care You Can Count On',
    'panel' => 'Panel',
    'logout' => 'Salir',
    'hospital_panel' => 'Panel Hospital',
    'session' => 'Sesión',
    'active_offers' => 'Ofertas activas',
    'active_publications' => 'Publicaciones vigentes',
    'applicants' => 'Aplicantes',
    'received_candidates' => 'Candidatos recibidos',
    'upcoming_schedule' => 'Agenda próxima',
    'planned_events' => 'Eventos planificados',
    'offers' => 'Ofertas',
    'calendar' => 'Calendario',
    'my_space' => 'Mi espacio',
    'profile' => 'Perfil',
    'contacts' => 'Contactos',
    'review_following' => 'Revisa lo siguiente:',
    'calendar_schedule' => 'Calendario de agenda',
    'today' => 'Hoy',
    'assigned_doctor' => 'Médico asignado',
    'open_chat' => 'Abrir chat',
    'day_agenda' => 'Agenda del día',
    'events_count' => 'evento(s)',
    'offer' => 'Oferta',
    'edit' => 'Editar',
    'delete' => 'Eliminar',
    'no_events_day' => 'Sin eventos para este día.',
    'edit_event' => 'Editar evento',
    'create_event' => 'Crear evento',
    'date' => 'Fecha',
    'link_offer_optional' => 'Vincular a oferta (opcional)',
    'no_offer' => 'Sin oferta',
    'title' => 'Título',
    'start_time_optional' => 'Hora inicio (opcional)',
    'end_time_optional' => 'Hora fin (opcional)',
    'status' => 'Estado',
    'notes_optional' => 'Notas (opcional)',
    'planned' => 'Planificado',
    'confirmed' => 'Confirmado',
    'cancelled' => 'Cancelado',
    'completed' => 'Completado',
    'cancel_edit' => 'Cancelar edición',
    'clear' => 'Limpiar',
    'save' => 'Guardar',
    'agenda_note' => 'Organiza tu agenda por día y, si quieres, vincula cada evento a una oferta para mantener el flujo de contratación claro.',
    'delete_event_confirm' => '¿Eliminar este evento?',
    'change_language' => 'Cambiar idioma',
    'language' => 'Idioma',
    'hospital_type' => 'Hospital',
    'sun' => 'Dom',
    'mon' => 'Lun',
    'tue' => 'Mar',
    'wed' => 'Mié',
    'thu' => 'Jue',
    'fri' => 'Vie',
    'sat' => 'Sáb',
    'january' => 'Enero',
    'february' => 'Febrero',
    'march' => 'Marzo',
    'april' => 'Abril',
    'may' => 'Mayo',
    'june' => 'Junio',
    'july' => 'Julio',
    'august' => 'Agosto',
    'september' => 'Septiembre',
    'october' => 'Octubre',
    'november' => 'Noviembre',
    'december' => 'Diciembre'
  ],
  'en' => [
    'brand_tagline' => 'Locums • Supporting Care You Can Count On',
    'panel' => 'Dashboard',
    'logout' => 'Log out',
    'hospital_panel' => 'Hospital Dashboard',
    'session' => 'Session',
    'active_offers' => 'Active offers',
    'active_publications' => 'Live postings',
    'applicants' => 'Applicants',
    'received_candidates' => 'Received candidates',
    'upcoming_schedule' => 'Upcoming schedule',
    'planned_events' => 'Planned events',
    'offers' => 'Offers',
    'calendar' => 'Calendar',
    'my_space' => 'My space',
    'profile' => 'Profile',
    'contacts' => 'Contacts',
    'review_following' => 'Please review the following:',
    'calendar_schedule' => 'Schedule calendar',
    'today' => 'Today',
    'assigned_doctor' => 'Assigned doctor',
    'open_chat' => 'Open chat',
    'day_agenda' => 'Daily agenda',
    'events_count' => 'event(s)',
    'offer' => 'Offer',
    'edit' => 'Edit',
    'delete' => 'Delete',
    'no_events_day' => 'No events for this day.',
    'edit_event' => 'Edit event',
    'create_event' => 'Create event',
    'date' => 'Date',
    'link_offer_optional' => 'Link to offer (optional)',
    'no_offer' => 'No offer',
    'title' => 'Title',
    'start_time_optional' => 'Start time (optional)',
    'end_time_optional' => 'End time (optional)',
    'status' => 'Status',
    'notes_optional' => 'Notes (optional)',
    'planned' => 'Planned',
    'confirmed' => 'Confirmed',
    'cancelled' => 'Cancelled',
    'completed' => 'Completed',
    'cancel_edit' => 'Cancel editing',
    'clear' => 'Clear',
    'save' => 'Save',
    'agenda_note' => 'Organize your schedule by day and, if you want, link each event to an offer to keep the hiring flow clear.',
    'delete_event_confirm' => 'Delete this event?',
    'change_language' => 'Change language',
    'language' => 'Language',
    'hospital_type' => 'Hospital',
    'sun' => 'Sun',
    'mon' => 'Mon',
    'tue' => 'Tue',
    'wed' => 'Wed',
    'thu' => 'Thu',
    'fri' => 'Fri',
    'sat' => 'Sat',
    'january' => 'January',
    'february' => 'February',
    'march' => 'March',
    'april' => 'April',
    'may' => 'May',
    'june' => 'June',
    'july' => 'July',
    'august' => 'August',
    'september' => 'September',
    'october' => 'October',
    'november' => 'November',
    'december' => 'December'
  ]
];

$plMonthNames = [
  1  => pl_t('january'),
  2  => pl_t('february'),
  3  => pl_t('march'),
  4  => pl_t('april'),
  5  => pl_t('may'),
  6  => pl_t('june'),
  7  => pl_t('july'),
  8  => pl_t('august'),
  9  => pl_t('september'),
  10 => pl_t('october'),
  11 => pl_t('november'),
  12 => pl_t('december'),
];

$plDayNames = [
  pl_t('sun'),
  pl_t('mon'),
  pl_t('tue'),
  pl_t('wed'),
  pl_t('thu'),
  pl_t('fri'),
  pl_t('sat')
];

$plLangQS = '&lang=' . urlencode($plLang);
$pageTitle = ($plLang === 'en') ? 'Hospital Dashboard | PILLAR Locums' : 'Panel Hospital | PILLAR Locums';

// =====================
// Utilidades robustas
// =====================
function table_exists(PDO $pdo, string $table): bool {
  if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) return false;
  $sql = "SHOW TABLES LIKE " . $pdo->quote($table);
  $st = $pdo->query($sql);
  return (bool)$st->fetchColumn();
}

function table_columns(PDO $pdo, string $table): array {
  if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) return [];
  $cols = [];
  $st = $pdo->query("SHOW COLUMNS FROM `{$table}`");
  foreach (($st->fetchAll(PDO::FETCH_ASSOC) ?: []) as $r) {
    $cols[] = (string)($r['Field'] ?? '');
  }
  return array_values(array_filter($cols));
}

function pick_owner_column(array $cols, array $preferred): ?string {
  foreach ($preferred as $c) {
    if (in_array($c, $cols, true)) return $c;
  }
  return null;
}

// =====================
// VARIABLES GLOBALES
// =====================
$jobs = [];
$eventsMonth = [];
$countByDate = [];
$eventsDay = [];
$kpiActiveOffers = 0;
$kpiApplicants = 0;
$kpiUpcoming = 0;

// =====================
// CARGA OFERTAS
// =====================
$PILLAR_OFFERS_RENDER = false;
require __DIR__ . "/ofertas.php";

// =====================
// CALENDARIO: FECHAS
// =====================
$todayY = (int)date('Y');
$todayM = (int)date('n');
$todayD = (int)date('j');

$year  = (int)($_GET['y'] ?? $todayY);
$month = (int)($_GET['m'] ?? $todayM);
$day   = (int)($_GET['d'] ?? $todayD);

if ($month < 1)  { $month = 12; $year--; }
if ($month > 12) { $month = 1;  $year++; }

$daysInMonth = (int)cal_days_in_month(CAL_GREGORIAN, $month, $year);
if ($day < 1) $day = 1;
if ($day > $daysInMonth) $day = $daysInMonth;

$firstDay = (int)date('w', strtotime(sprintf('%04d-%02d-01', $year, $month)));
$selectedDate = sprintf('%04d-%02d-%02d', $year, $month, $day);
$rangeStart = sprintf('%04d-%02d-01', $year, $month);
$rangeEnd   = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);

// =====================
// CALENDARIO: CRUD + CARGA
// =====================
$editing = null;
$editId = (int)($_GET['edit'] ?? 0);

try {
  if (!isset($pdo) || !($pdo instanceof PDO)) {
    throw new RuntimeException("PDO no disponible. Revisa /usuarios/db.php");
  }

  $eventsTable = table_exists($pdo, 'hospital_events')
    ? 'hospital_events'
    : (table_exists($pdo, 'clinic_events') ? 'clinic_events' : '');

  if ($eventsTable === '') {
    throw new RuntimeException("No existe tabla hospital_events ni clinic_events.");
  }

  $cols = table_columns($pdo, $eventsTable);

  foreach (['id', 'event_date', 'title'] as $req) {
    if (!in_array($req, $cols, true)) {
      throw new RuntimeException("{$eventsTable} no tiene la columna requerida: {$req}");
    }
  }

  $ownerCol = pick_owner_column($cols, ['hospital_user_id', 'clinic_user_id']);
  if ($ownerCol === null) {
    throw new RuntimeException("{$eventsTable} no tiene columna de propietario (hospital_user_id / clinic_user_id).");
  }

  $hasJobId   = in_array('job_id', $cols, true);
  $hasStart   = in_array('start_time', $cols, true);
  $hasEnd     = in_array('end_time', $cols, true);
  $hasStatus  = in_array('status', $cols, true);
  $hasNotes   = in_array('notes', $cols, true);

  if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tab === 'calendario') {
    $action = (string)($_POST['action'] ?? '');

    if (in_array($action, ['create_event', 'update_event', 'delete_event'], true)) {
      $eventId = (int)($_POST['event_id'] ?? 0);
      $date    = trim((string)($_POST['date'] ?? $selectedDate));
      $title   = trim((string)($_POST['title'] ?? ''));

      $jobIdRaw = trim((string)($_POST['job_id'] ?? '0'));
      $jobId    = ($jobIdRaw === '' || $jobIdRaw === '0') ? null : (int)$jobIdRaw;

      $start  = trim((string)($_POST['start_time'] ?? ''));
      $end    = trim((string)($_POST['end_time'] ?? ''));
      $status = trim((string)($_POST['status'] ?? 'planned'));
      $notes  = trim((string)($_POST['notes'] ?? ''));

      $errs = [];

      if ($action !== 'delete_event') {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $errs[] = "Fecha inválida.";
        if ($title === '') $errs[] = "Título obligatorio.";
        if ($start !== '' && !preg_match('/^\d{2}:\d{2}$/', $start)) $errs[] = "Hora inicio inválida.";
        if ($end !== '' && !preg_match('/^\d{2}:\d{2}$/', $end)) $errs[] = "Hora fin inválida.";
        if ($start !== '' && $end !== '' && $end < $start) $errs[] = "Hora fin no puede ser menor a inicio.";
      } else {
        if ($eventId <= 0) $errs[] = "Evento inválido.";
      }

      if (!$errs && in_array($action, ['update_event', 'delete_event'], true)) {
        $stOwn = $pdo->prepare("SELECT id FROM `{$eventsTable}` WHERE id=? AND `{$ownerCol}`=? LIMIT 1");
        $stOwn->execute([$eventId, $hospitalId]);
        if (!$stOwn->fetchColumn()) $errs[] = "No autorizado.";
      }

      if (!$errs) {
        if ($action === 'create_event') {
          $fields = [
            $ownerCol    => $hospitalId,
            'event_date' => $date,
            'title'      => $title,
          ];

          if ($hasJobId)  $fields['job_id'] = $jobId;
          if ($hasStart)  $fields['start_time'] = ($start !== '' ? $start : null);
          if ($hasEnd)    $fields['end_time'] = ($end !== '' ? $end : null);
          if ($hasStatus) $fields['status'] = ($status !== '' ? $status : null);
          if ($hasNotes)  $fields['notes'] = ($notes !== '' ? $notes : null);

          $colsSql = implode(',', array_map(fn($c) => "`{$c}`", array_keys($fields)));
          $valsSql = implode(',', array_fill(0, count($fields), '?'));

          $sql = "INSERT INTO `{$eventsTable}` ({$colsSql}) VALUES ({$valsSql})";
          $st = $pdo->prepare($sql);
          $st->execute(array_values($fields));

          header("Location: ?tab=calendario&y={$year}&m={$month}&d=" . (int)substr($date, 8, 2) . $plLangQS);
          exit;
        }

        if ($action === 'update_event') {
          $sets = ["event_date=?", "title=?"];
          $vals = [$date, $title];

          if ($hasJobId)  { $sets[] = "job_id=?";      $vals[] = $jobId; }
          if ($hasStart)  { $sets[] = "start_time=?";  $vals[] = ($start !== '' ? $start : null); }
          if ($hasEnd)    { $sets[] = "end_time=?";    $vals[] = ($end !== '' ? $end : null); }
          if ($hasStatus) { $sets[] = "status=?";      $vals[] = ($status !== '' ? $status : null); }
          if ($hasNotes)  { $sets[] = "notes=?";       $vals[] = ($notes !== '' ? $notes : null); }

          $vals[] = $eventId;
          $vals[] = $hospitalId;

          $sql = "UPDATE `{$eventsTable}` SET " . implode(',', $sets) . " WHERE id=? AND `{$ownerCol}`=? LIMIT 1";
          $st = $pdo->prepare($sql);
          $st->execute($vals);

          header("Location: ?tab=calendario&y={$year}&m={$month}&d=" . (int)substr($date, 8, 2) . $plLangQS);
          exit;
        }

        if ($action === 'delete_event') {
          $stDel = $pdo->prepare("DELETE FROM `{$eventsTable}` WHERE id=? AND `{$ownerCol}`=? LIMIT 1");
          $stDel->execute([$eventId, $hospitalId]);

          header("Location: ?tab=calendario&y={$year}&m={$month}&d={$day}" . $plLangQS);
          exit;
        }
      }

      foreach ($errs as $er) {
        $uiErrors[] = $er;
      }
    }
  }

  $sqlMonth = "SELECT * FROM `{$eventsTable}`
               WHERE `{$ownerCol}`=? AND event_date BETWEEN ? AND ?
               ORDER BY event_date ASC";
  if ($hasStart) $sqlMonth .= ", start_time ASC";

  $stM = $pdo->prepare($sqlMonth);
  $stM->execute([$hospitalId, $rangeStart, $rangeEnd]);
  $eventsMonth = $stM->fetchAll(PDO::FETCH_ASSOC) ?: [];

  foreach ($eventsMonth as $ev) {
    $dte = (string)($ev['event_date'] ?? '');
    if ($dte === '') continue;
    $countByDate[$dte] = ($countByDate[$dte] ?? 0) + 1;
  }

  $eventsDay = array_values(array_filter(
    $eventsMonth,
    fn($ev) => (string)($ev['event_date'] ?? '') === $selectedDate
  ));

  $kpiUpcoming = count($eventsMonth);

  if ($tab === 'calendario' && $editId > 0) {
    $stE = $pdo->prepare("SELECT * FROM `{$eventsTable}` WHERE id=? AND `{$ownerCol}`=? LIMIT 1");
    $stE->execute([$editId, $hospitalId]);
    $editing = $stE->fetch(PDO::FETCH_ASSOC) ?: null;
  }

} catch (Throwable $e) {
  error_log("HOSPITAL INDEX (calendar) ERROR: " . $e->getMessage());
  if ($tab === 'calendario') {
    $uiErrors[] = "No se pudo cargar la agenda.";
    if (isset($_GET['debug']) && $_GET['debug'] === '1') {
      $uiErrors[] = "DEBUG: " . $e->getMessage();
    }
  }
}

// =====================
// KPI: APLICANTES
// =====================
try {
  if (isset($pdo) && ($pdo instanceof PDO)) {
    if (table_exists($pdo, 'jobs')) {
      $jobCols = table_columns($pdo, 'jobs');
      $jobOwner = pick_owner_column($jobCols, ['hospital_user_id', 'clinic_user_id']);

      if ($jobOwner !== null && table_exists($pdo, 'job_applications')) {
        $st = $pdo->prepare("
          SELECT COUNT(*)
          FROM `job_applications` ja
          INNER JOIN `jobs` j ON j.`id` = ja.`job_id`
          WHERE j.`{$jobOwner}` = ?
        ");
        $st->execute([$hospitalId]);
        $kpiApplicants = (int)$st->fetchColumn();
      }
    }
  }
} catch (Throwable $e) {
  error_log('KPI Applicants (hospital) ERROR: ' . $e->getMessage());
}

// Perfil
$profile = [
  'name' => $hospitalName ?: 'Hospital',
  'location' => 'Gandía, Valencia',
  'type' => pl_t('hospital_type'),
];

function pillStatus(string $status): array {
  return match ($status) {
    'active'    => ['bg' => 'rgba(46, 204, 113, .18)', 'bd' => 'rgba(46, 204, 113, .35)', 'tx' => '#bfead1', 'label' => 'Activa'],
    'closed'    => ['bg' => 'rgba(185,180,170,.14)', 'bd' => 'rgba(185,180,170,.24)', 'tx' => '#d6d1c7', 'label' => 'Cerrada'],
    default     => ['bg' => 'rgba(185,180,170,.14)', 'bd' => 'rgba(185,180,170,.24)', 'tx' => '#d6d1c7', 'label' => 'Estado'],
  };
}
?>
<!doctype html>
<html lang="<?= h($plLang) ?>">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title><?= h($pageTitle) ?></title>

  <style>
    :root{
      --pl-teal:#3D6D76;
      --pl-mint:#97C3BC;
      --pl-mist:#EEF5F8;
      --pl-blush:#F5DBD0;
      --pl-terracotta:#CE9379;
      --pl-charcoal:#464646;
      --pl-soft:#FFF1F1;
      --pl-line:rgba(61,109,118,.14);
      --pl-line-strong:rgba(61,109,118,.22);
      --pl-shadow:0 18px 45px rgba(61,109,118,.10);
      --pl-shadow-soft:0 10px 25px rgba(61,109,118,.08);
      --pl-radius:22px;
      --pl-radius-sm:16px;
    }

    *{ box-sizing:border-box; }

    html, body{
      margin:0;
      padding:0;
      min-height:100%;
    }

    body{
      font-family:"Galatea","Avenir Next","Montserrat","Segoe UI",sans-serif;
      background:
        radial-gradient(circle at top right, rgba(151,195,188,.22), transparent 26%),
        linear-gradient(180deg, #f7f8f8 0%, #f1f3f4 100%);
      color:var(--pl-charcoal);
      min-height:100vh;
    }

    a{
      color:inherit;
      text-decoration:none;
    }

    .container{
      width:min(1220px, calc(100% - 32px));
      margin:34px auto 60px;
    }

    .topbar{
      position:sticky;
      top:0;
      z-index:100;
      backdrop-filter:blur(12px);
      background:rgba(247,248,248,.82);
      border-bottom:1px solid var(--pl-line);
    }

    .topbar .inner{
      width:min(1220px, calc(100% - 32px));
      margin:0 auto;
      min-height:84px;
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:18px;
      padding:14px 0;
    }

    .brand{
      display:flex;
      align-items:center;
      gap:14px;
      min-width:0;
    }

    .mark{
      width:58px;
      height:58px;
      border-radius:50%;
      display:grid;
      place-items:center;
      background:linear-gradient(135deg, var(--pl-teal), #497f89);
      color:#fff;
      font-weight:800;
      font-size:18px;
      letter-spacing:.14em;
      box-shadow:var(--pl-shadow-soft);
      border:1px solid rgba(255,255,255,.28);
      flex:0 0 auto;
    }

    .brandTitle{
      display:flex;
      flex-direction:column;
      justify-content:center;
      min-width:0;
    }

    .brandTitle strong{
      font-size:34px;
      line-height:1;
      letter-spacing:.34em;
      color:var(--pl-teal);
      font-weight:800;
    }

    .brandTitle span{
      font-size:14px;
      letter-spacing:.16em;
      color:rgba(61,109,118,.88);
      margin-top:6px;
      text-transform:none;
    }

    .topActions{
      display:flex;
      align-items:center;
      gap:10px;
      flex-wrap:wrap;
      justify-content:flex-end;
    }

    .btn,
    .iconBtn,
    .btnDanger,
    .langBtn,
    .modalClose,
    .chatBtn{
      appearance:none;
      outline:none;
      cursor:pointer;
      transition:.22s ease;
      font-family:inherit;
    }

    .btn{
      display:inline-flex;
      align-items:center;
      justify-content:center;
      gap:8px;
      min-height:44px;
      padding:0 18px;
      border-radius:999px;
      font-weight:700;
      letter-spacing:.02em;
      box-shadow:none;
      border:1px solid transparent;
    }

    .btn:hover,
    .iconBtn:hover,
    .btnDanger:hover,
    .langBtn:hover,
    .modalClose:hover,
    .chatBtn:hover{
      transform:translateY(-1px);
    }

    .btnGhost{
      background:rgba(255,255,255,.72);
      color:var(--pl-teal);
      border:1px solid rgba(61,109,118,.16);
    }

    .btnGhost:hover{
      background:#fff;
      border-color:rgba(61,109,118,.28);
    }

    .btnGold{
      background:linear-gradient(135deg, var(--pl-terracotta), #d9a184);
      color:#fff;
      border:1px solid rgba(206,147,121,.45);
      box-shadow:0 10px 22px rgba(206,147,121,.20);
    }

    .btnGold:hover{
      filter:brightness(1.02);
    }

    .langBtn{
      width:44px;
      height:44px;
      display:inline-flex;
      align-items:center;
      justify-content:center;
      border-radius:999px;
      background:linear-gradient(135deg, var(--pl-teal), var(--pl-mint));
      color:#fff;
      border:1px solid rgba(255,255,255,.28);
      box-shadow:0 10px 24px rgba(61,109,118,.18);
      position:relative;
      font-size:18px;
      overflow:hidden;
    }

    .langBtn .langCode{
      position:absolute;
      right:4px;
      bottom:3px;
      font-size:9px;
      line-height:1;
      font-weight:800;
      letter-spacing:.08em;
      background:rgba(255,255,255,.92);
      color:var(--pl-teal);
      padding:2px 4px;
      border-radius:999px;
    }

    .hero{
      display:flex;
      align-items:flex-start;
      justify-content:space-between;
      gap:18px;
      padding:30px 30px;
      border-radius:30px;
      background:linear-gradient(135deg, rgba(61,109,118,.96), rgba(151,195,188,.86));
      color:#fff;
      box-shadow:var(--pl-shadow);
      border:1px solid rgba(255,255,255,.16);
      margin-bottom:22px;
      position:relative;
      overflow:hidden;
    }

    .hero::after{
      content:"";
      position:absolute;
      inset:auto -90px -90px auto;
      width:260px;
      height:260px;
      border-radius:50%;
      background:rgba(255,255,255,.08);
      pointer-events:none;
    }

    .hero h1{
      margin:0;
      font-size:34px;
      line-height:1.05;
      letter-spacing:.01em;
      font-weight:800;
    }

    .hero p{
      margin:8px 0 0;
      font-size:15px;
      color:rgba(255,255,255,.92);
    }

    .muted{
      color:rgba(70,70,70,.70);
    }

    .hero .muted{
      color:rgba(255,255,255,.72);
    }

    .cards{
      display:grid;
      grid-template-columns:repeat(3, minmax(0,1fr));
      gap:18px;
      margin-bottom:22px;
    }

    .card,
    .panel,
    .content,
    .tabs,
    .note,
    .errBox,
    .tableWrap{
      background:rgba(255,255,255,.74);
      backdrop-filter:blur(12px);
      border:1px solid var(--pl-line);
      box-shadow:var(--pl-shadow-soft);
    }

    .card{
      padding:22px 22px;
      border-radius:var(--pl-radius);
    }

    .label{
      color:var(--pl-teal);
      font-size:14px;
      font-weight:800;
      letter-spacing:.04em;
      text-transform:uppercase;
      margin-bottom:10px;
    }

    .kpi{
      font-size:42px;
      font-weight:900;
      line-height:1;
      color:var(--pl-charcoal);
      margin-bottom:8px;
    }

    .panelWrap{
      display:grid;
      grid-template-columns:260px minmax(0,1fr);
      gap:18px;
      align-items:start;
      overflow:visible;
    }

    .tabs{
      border-radius:28px;
      padding:14px;
      display:flex;
      flex-direction:column;
      gap:10px;
      position:sticky;
      top:100px;
    }

    .tab{
      display:flex;
      align-items:center;
      min-height:50px;
      padding:0 16px;
      border-radius:16px;
      color:var(--pl-teal);
      font-weight:700;
      border:1px solid transparent;
      background:transparent;
      white-space:nowrap;
    }

    .tab:hover{
      background:rgba(151,195,188,.14);
      border-color:rgba(151,195,188,.24);
    }

    .tab.active{
      background:linear-gradient(135deg, rgba(61,109,118,.96), rgba(151,195,188,.88));
      color:#fff;
      box-shadow:0 10px 24px rgba(61,109,118,.14);
    }

    .content{
      border-radius:30px;
      padding:24px;
      min-width:0;
      overflow:visible;
    }

    .sectionTitle{
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:16px;
      margin-bottom:18px;
    }

    .sectionTitle h2{
      margin:0;
      font-size:28px;
      color:var(--pl-teal);
      font-weight:800;
    }

    .grid2{
      display:grid;
      grid-template-columns:1.18fr .92fr;
      gap:18px;
      align-items:start;
    }

    .panel{
      border-radius:24px;
      padding:20px;
      min-width:0;
    }

    .panel h3{
      margin:0 0 8px;
      color:var(--pl-teal);
      font-size:20px;
      font-weight:800;
    }

    .note{
      border-radius:18px;
      padding:14px 15px;
      background:rgba(245,219,208,.24);
      color:var(--pl-charcoal);
      line-height:1.45;
      font-size:.95rem;
    }

    .errBox{
      border-radius:18px;
      padding:16px 18px;
      background:rgba(206,147,121,.12);
      border-color:rgba(206,147,121,.28);
      color:var(--pl-charcoal);
      margin-bottom:18px;
    }

    .tableWrap{
      border-radius:20px;
      overflow:hidden;
    }

    .table{
      width:100%;
      border-collapse:collapse;
      background:transparent;
    }

    .table th,
    .table td{
      padding:12px 10px;
      border-bottom:1px solid rgba(61,109,118,.10);
      text-align:left;
      font-size:.92rem;
      vertical-align:top;
      color:var(--pl-charcoal);
    }

    .table th{
      color:var(--pl-teal);
      font-weight:800;
      background:rgba(151,195,188,.16);
    }

    .pill{
      display:inline-flex;
      padding:6px 10px;
      border-radius:999px;
      font-size:.82rem;
      border:1px solid rgba(61,109,118,.16);
      background:rgba(151,195,188,.14);
      color:var(--pl-teal);
    }

    .calHeader{
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:12px;
      margin-bottom:16px;
      flex-wrap:wrap;
    }

    .calNav{
      display:flex;
      align-items:center;
      gap:8px;
      flex-wrap:wrap;
    }

    .calGrid{
      border-radius:20px;
      overflow:hidden;
      border:1px solid var(--pl-line);
      background:#fff;
    }

    .calWeek{
      display:grid;
      grid-template-columns:repeat(7,1fr);
      background:rgba(151,195,188,.18);
      border-bottom:1px solid var(--pl-line);
    }

    .calWeek > div{
      padding:12px 8px;
      text-align:center;
      color:var(--pl-teal);
      font-size:13px;
      font-weight:800;
      letter-spacing:.04em;
      text-transform:uppercase;
    }

    .calDays{
      display:grid;
      grid-template-columns:repeat(7,1fr);
    }

    .calCell{
      min-height:102px;
      padding:10px;
      border-right:1px solid rgba(61,109,118,.08);
      border-bottom:1px solid rgba(61,109,118,.08);
      display:flex;
      flex-direction:column;
      justify-content:space-between;
      color:var(--pl-charcoal);
      background:rgba(255,255,255,.94);
      transition:.18s ease;
    }

    .calCell:hover{
      background:rgba(238,245,248,.94);
    }

    .calDayNum{
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:8px;
      font-weight:800;
      color:var(--pl-teal);
    }

    .dot{
      width:10px;
      height:10px;
      border-radius:50%;
      background:var(--pl-terracotta);
      box-shadow:0 0 0 4px rgba(206,147,121,.16);
      flex:0 0 auto;
    }

    .small{
      font-size:12px;
      color:rgba(70,70,70,.72);
      line-height:1.3;
    }

    .field{
      display:flex;
      flex-direction:column;
      gap:8px;
      margin-bottom:14px;
      min-width:0;
      flex:1 1 0;
    }

    .field label{
      font-size:13px;
      font-weight:800;
      color:var(--pl-teal);
      letter-spacing:.02em;
    }

    .row{
      display:flex;
      gap:14px;
      flex-wrap:wrap;
    }

    .input,
    .select,
    textarea{
      width:100%;
      min-height:48px;
      border-radius:15px;
      border:1px solid rgba(61,109,118,.16);
      background:#fff;
      padding:12px 14px;
      color:var(--pl-charcoal);
      font:inherit;
      outline:none;
      transition:.18s ease;
    }

    .input:focus,
    .select:focus,
    textarea:focus{
      border-color:rgba(61,109,118,.34);
      box-shadow:0 0 0 4px rgba(151,195,188,.18);
    }

    .btnRow,
    .actions{
      display:flex;
      align-items:center;
      gap:10px;
      flex-wrap:wrap;
    }

    .iconBtn{
      display:inline-flex;
      align-items:center;
      justify-content:center;
      min-height:40px;
      padding:0 14px;
      border-radius:999px;
      background:rgba(151,195,188,.18);
      color:var(--pl-teal);
      border:1px solid rgba(151,195,188,.28);
      font-weight:700;
      text-decoration:none;
    }

    .chatBtn{
      display:inline-flex;
      align-items:center;
      justify-content:center;
      min-height:40px;
      padding:0 14px;
      border-radius:999px;
      background:linear-gradient(135deg, rgba(61,109,118,.96), rgba(151,195,188,.88));
      color:#fff;
      border:1px solid rgba(61,109,118,.16);
      font-weight:700;
      text-decoration:none;
      box-shadow:0 10px 24px rgba(61,109,118,.14);
    }

    .btnDanger{
      display:inline-flex;
      align-items:center;
      justify-content:center;
      min-height:40px;
      padding:0 14px;
      border-radius:999px;
      background:rgba(206,147,121,.14);
      color:#9b5f46;
      border:1px solid rgba(206,147,121,.30);
      font-weight:700;
    }

    .contacto{
      margin-bottom:16px;
      padding:16px;
      border-radius:18px;
      background:rgba(151,195,188,.16);
      border:1px solid rgba(151,195,188,.26);
    }

    .contacto h3{
      margin:0 0 10px;
    }

    .contacto a{
      display:inline-flex;
      align-items:center;
      justify-content:center;
      margin-top:8px;
      min-height:40px;
      padding:0 14px;
      border-radius:999px;
      background:var(--pl-teal);
      color:#fff;
      font-weight:700;
    }

    .modalOverlay{
      position:fixed;
      inset:0;
      z-index:2500;
      display:none;
      align-items:center;
      justify-content:center;
      padding:22px;
      background:rgba(70,70,70,.45);
      backdrop-filter:blur(6px);
    }

    .modalOverlay.isOpen{
      display:flex;
    }

    .modalCard{
      width:min(920px, 94vw);
      max-height:calc(100vh - 120px);
      overflow:hidden;
      border-radius:24px;
      border:1px solid var(--pl-line);
      background:#fff;
      box-shadow:0 24px 60px rgba(61,109,118,.14);
    }

    .modalHeader{
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:12px;
      padding:14px 16px;
      border-bottom:1px solid rgba(61,109,118,.10);
    }

    .modalHeader strong{
      font-size:1rem;
      color:var(--pl-teal);
    }

    .modalBody{
      padding:14px 16px;
      overflow:auto;
      max-height:calc(100vh - 210px);
    }

    .modalClose{
      border:1px solid rgba(61,109,118,.12);
      background:rgba(151,195,188,.14);
      color:var(--pl-teal);
      border-radius:12px;
      padding:8px 12px;
      font-weight:700;
    }

    @media (max-width: 1080px){
      .cards{
        grid-template-columns:repeat(2, minmax(0,1fr));
      }

      .grid2{
        grid-template-columns:1fr;
      }

      .panelWrap{
        grid-template-columns:1fr;
      }

      .tabs{
        position:static;
        flex-direction:row;
        flex-wrap:wrap;
        overflow:auto;
      }
    }

    @media (max-width: 760px){
      .topbar .inner{
        align-items:flex-start;
        flex-direction:column;
      }

      .brandTitle strong{
        font-size:26px;
        letter-spacing:.24em;
      }

      .brandTitle span{
        font-size:12px;
        letter-spacing:.08em;
      }

      .container{
        width:min(100% - 20px, 100%);
        margin:20px auto 48px;
      }

      .hero{
        padding:22px 18px;
      }

      .hero h1{
        font-size:28px;
      }

      .cards{
        grid-template-columns:1fr;
      }

      .content,
      .panel,
      .card{
        padding:18px;
      }

      .calCell{
        min-height:88px;
        padding:8px;
      }

      .row,
      .btnRow,
      .actions{
        flex-direction:column;
        align-items:stretch;
      }

      .btn,
      .iconBtn,
      .btnDanger,
      .chatBtn{
        width:100%;
      }
    }
  </style>
</head>

<body>
  <header class="topbar">
    <div class="inner">
      <a class="brand" href="/index.html">
        <div class="mark">PL</div>
        <div class="brandTitle">
          <strong>PILLAR</strong>
          <span><?= h(pl_t('brand_tagline')) ?></span>
        </div>
      </a>

      <div class="topActions">
        <a
          class="langBtn"
          href="<?= h(pl_lang_url($plNextLang)) ?>"
          title="<?= h(pl_t('change_language')) ?>"
          aria-label="<?= h(pl_t('change_language')) ?>"
        >
          🌐
          <span class="langCode"><?= h($plNextLangLabel) ?></span>
        </a>

        <a class="btn btnGhost" href="/usuarios/dashboard.php"><?= h(pl_t('panel')) ?></a>
        <a class="btn btnGold" href="/usuarios/logout.php"><?= h(pl_t('logout')) ?></a>
      </div>
    </div>
  </header>

  <main class="container">
    <div class="hero">
      <div>
        <h1><?= h($profile['name']) ?> — <?= h(pl_t('hospital_panel')) ?></h1>
        <p><?= h($profile['location']) ?> • <?= h($profile['type']) ?></p>
        <p class="muted" style="margin-top:8px;">
          <?= h(pl_t('session')) ?>: <?= h($hospitalEmail) ?> • ID: <?= (int)$hospitalId ?>
        </p>
      </div>
    </div>

    <section class="cards">
      <div class="card">
        <div class="label"><?= h(pl_t('active_offers')) ?></div>
        <div class="kpi"><?= (int)$kpiActiveOffers ?></div>
        <div class="muted"><?= h(pl_t('active_publications')) ?></div>
      </div>
      <div class="card">
        <div class="label"><?= h(pl_t('applicants')) ?></div>
        <div class="kpi"><?= (int)$kpiApplicants ?></div>
        <div class="muted"><?= h(pl_t('received_candidates')) ?></div>
      </div>
      <div class="card">
        <div class="label"><?= h(pl_t('upcoming_schedule')) ?></div>
        <div class="kpi"><?= (int)$kpiUpcoming ?></div>
        <div class="muted"><?= h(pl_t('planned_events')) ?></div>
      </div>
    </section>

    <section class="panelWrap">
      <nav class="tabs">
        <a class="tab <?= $tab === 'ofertas' ? 'active' : '' ?>" href="?tab=ofertas<?= $plLangQS ?>"><?= h(pl_t('offers')) ?></a>
        <a class="tab <?= $tab === 'calendario' ? 'active' : '' ?>" href="?tab=calendario&y=<?= (int)$year ?>&m=<?= (int)$month ?>&d=<?= (int)$day ?><?= $plLangQS ?>"><?= h(pl_t('calendar')) ?></a>
        <a class="tab <?= $tab === 'espacio' ? 'active' : '' ?>" href="?tab=espacio<?= $plLangQS ?>"><?= h(pl_t('my_space')) ?></a>
        <a class="tab <?= $tab === 'perfil' ? 'active' : '' ?>" href="?tab=perfil<?= $plLangQS ?>"><?= h(pl_t('profile')) ?></a>
        <a class="tab <?= $tab === 'aplicantes' ? 'active' : '' ?>" href="?tab=aplicantes<?= $plLangQS ?>"><?= h(pl_t('applicants')) ?></a>
        <a class="tab <?= $tab === 'contactos' ? 'active' : '' ?>" href="?tab=contactos<?= $plLangQS ?>"><?= h(pl_t('contacts')) ?></a>
      </nav>

      <div class="content">
        <?php if ($uiErrors): ?>
          <div class="errBox">
            <strong><?= h(pl_t('review_following')) ?></strong>
            <ul style="margin:8px 0 0; padding-left:18px;">
              <?php foreach ($uiErrors as $e): ?>
                <li><?= h((string)$e) ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

        <?php if ($tab === 'ofertas'): ?>
          <?php $PILLAR_OFFERS_RENDER = true; require __DIR__ . "/ofertas.php"; ?>

        <?php elseif ($tab === 'calendario'): ?>
          <div class="sectionTitle">
            <h2><?= h(pl_t('calendar_schedule')) ?></h2>
          </div>

          <div class="grid2">
            <div class="panel">
              <div class="calHeader">
                <div class="muted"><?= h($plMonthNames[$month] ?? '') ?> <?= (int)$year ?></div>
                <div class="calNav">
                  <a class="btn btnGhost" href="?tab=calendario&y=<?= (int)$year ?>&m=<?= (int)$month - 1 ?>&d=<?= (int)$day ?><?= $plLangQS ?>">‹</a>
                  <a class="btn btnGhost" href="?tab=calendario&y=<?= (int)date('Y') ?>&m=<?= (int)date('n') ?>&d=<?= (int)date('j') ?><?= $plLangQS ?>"><?= h(pl_t('today')) ?></a>
                  <a class="btn btnGhost" href="?tab=calendario&y=<?= (int)$year ?>&m=<?= (int)$month + 1 ?>&d=<?= (int)$day ?><?= $plLangQS ?>">›</a>
                </div>
              </div>

              <div class="calGrid">
                <div class="calWeek">
                  <div><?= h($plDayNames[0]) ?></div>
                  <div><?= h($plDayNames[1]) ?></div>
                  <div><?= h($plDayNames[2]) ?></div>
                  <div><?= h($plDayNames[3]) ?></div>
                  <div><?= h($plDayNames[4]) ?></div>
                  <div><?= h($plDayNames[5]) ?></div>
                  <div><?= h($plDayNames[6]) ?></div>
                </div>

                <div class="calDays">
                  <?php
                    for ($i = 0; $i < $firstDay; $i++) {
                      echo '<div class="calCell" style="background:rgba(61,109,118,.05)"></div>';
                    }

                    for ($d = 1; $d <= $daysInMonth; $d++) {
                      $dateKey = sprintf('%04d-%02d-%02d', $year, $month, $d);
                      $count = (int)($countByDate[$dateKey] ?? 0);
                      $isSel = ($dateKey === $selectedDate);
                      $style = $isSel ? 'border-color: rgba(61,109,118,.34); background: rgba(151,195,188,.18);' : '';

                      echo '<a class="calCell" style="' . $style . '" href="?tab=calendario&y=' . $year . '&m=' . $month . '&d=' . $d . $plLangQS . '">';
                      echo '<div class="calDayNum"><span>' . $d . '</span>';
                      if ($count > 0) {
                        echo '<span class="dot" title="' . h((string)$count) . ' ' . h(pl_t('events_count')) . '"></span>';
                      }
                      echo '</div>';
                      echo ($count > 0)
                        ? '<div class="small">' . $count . ' ' . h(pl_t('events_count')) . '</div>'
                        : '<div class="small">&nbsp;</div>';
                      echo '</a>';
                    }
                  ?>
                </div>
              </div>
            </div>

            <div class="panel">
              <h3><?= h(pl_t('day_agenda')) ?></h3>
              <div class="muted" style="margin-bottom:10px;"><?= h($selectedDate) ?></div>

              <?php if ($eventsDay): ?>
                <?php foreach ($eventsDay as $ev): ?>
                  <?php
                    $jid = (int)($ev['job_id'] ?? 0);
                    $jobLabel = '';
                    if ($jid > 0) {
                      foreach ($jobs as $j) {
                        if ((int)($j['id'] ?? 0) === $jid) {
                          $jobLabel = (string)($j['title'] ?? $j['name'] ?? '');
                          break;
                        }
                      }
                    }
                  ?>
                  <div class="note" style="margin-bottom:10px;">
                    <strong><?= h((string)($ev['title'] ?? '')) ?></strong><br>
                    <span class="muted">
                      <?= h((string)($ev['start_time'] ?? '')) ?>
                      <?= (($ev['end_time'] ?? '') !== '' ? ' - ' . h((string)$ev['end_time']) : '') ?>
                      <?= ($jobLabel !== '' ? ' • ' . h(pl_t('offer')) . ': ' . h($jobLabel) : '') ?>
                    </span>

                    <div class="actions" style="margin-top:10px;">
                      <a class="iconBtn" href="?tab=calendario&y=<?= (int)$year ?>&m=<?= (int)$month ?>&d=<?= (int)$day ?>&edit=<?= (int)$ev['id'] ?><?= $plLangQS ?>"><?= h(pl_t('edit')) ?></a>

                      <form method="post" style="display:inline;" onsubmit="return confirm('<?= h(pl_t('delete_event_confirm')) ?>');">
                        <input type="hidden" name="action" value="delete_event">
                        <input type="hidden" name="event_id" value="<?= (int)$ev['id'] ?>">
                        <button class="btnDanger" type="submit"><?= h(pl_t('delete')) ?></button>
                      </form>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php else: ?>
                <div class="muted" style="margin-bottom:12px;"><?= h(pl_t('no_events_day')) ?></div>
              <?php endif; ?>

              <div style="border-top:1px solid rgba(61,109,118,.12); padding-top:12px; margin-top:10px;">
                <h3 style="margin-bottom:8px;"><?= $editing ? h(pl_t('edit_event')) : h(pl_t('create_event')) ?></h3>

                <form method="post">
                  <input type="hidden" name="action" value="<?= $editing ? 'update_event' : 'create_event' ?>">
                  <input type="hidden" name="event_id" value="<?= (int)($editing['id'] ?? 0) ?>">

                  <div class="field">
                    <label><?= h(pl_t('date')) ?></label>
                    <input class="input" name="date" type="date" value="<?= h((string)($editing['event_date'] ?? $selectedDate)) ?>" required>
                  </div>

                  <div class="field">
                    <label><?= h(pl_t('link_offer_optional')) ?></label>
                    <select class="select" name="job_id">
                      <option value="0"><?= h(pl_t('no_offer')) ?></option>
                      <?php
                        $selJob = (int)($editing['job_id'] ?? 0);
                        foreach ($jobs as $j):
                          $jid = (int)($j['id'] ?? 0);
                          $ttl = (string)($j['title'] ?? $j['name'] ?? '');
                      ?>
                        <option value="<?= $jid ?>" <?= ($selJob === $jid ? 'selected' : '') ?>>
                          <?= h($ttl) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>

                  <div class="field">
                    <label><?= h(pl_t('title')) ?></label>
                    <input class="input" name="title" value="<?= h((string)($editing['title'] ?? '')) ?>" required>
                  </div>

                  <div class="row">
                    <div class="field">
                      <label><?= h(pl_t('start_time_optional')) ?></label>
                      <input class="input" name="start_time" type="time" value="<?= h((string)($editing['start_time'] ?? '')) ?>">
                    </div>
                    <div class="field">
                      <label><?= h(pl_t('end_time_optional')) ?></label>
                      <input class="input" name="end_time" type="time" value="<?= h((string)($editing['end_time'] ?? '')) ?>">
                    </div>
                  </div>

                  <div class="row">
                    <div class="field">
                      <label><?= h(pl_t('status')) ?></label>
                      <?php $cur = (string)($editing['status'] ?? 'planned'); ?>
                      <select class="select" name="status">
                        <option value="planned" <?= $cur === 'planned' ? 'selected' : '' ?>><?= h(pl_t('planned')) ?></option>
                        <option value="confirmed" <?= $cur === 'confirmed' ? 'selected' : '' ?>><?= h(pl_t('confirmed')) ?></option>
                        <option value="cancelled" <?= $cur === 'cancelled' ? 'selected' : '' ?>><?= h(pl_t('cancelled')) ?></option>
                        <option value="completed" <?= $cur === 'completed' ? 'selected' : '' ?>><?= h(pl_t('completed')) ?></option>
                      </select>
                    </div>

                    <div class="field">
                      <label><?= h(pl_t('notes_optional')) ?></label>
                      <input class="input" name="notes" value="<?= h((string)($editing['notes'] ?? '')) ?>">
                    </div>
                  </div>

                  <div class="btnRow">
                    <?php if ($editing): ?>
                      <a class="btn btnGhost" href="?tab=calendario&y=<?= (int)$year ?>&m=<?= (int)$month ?>&d=<?= (int)$day ?><?= $plLangQS ?>"><?= h(pl_t('cancel_edit')) ?></a>
                    <?php else: ?>
                      <button class="btn btnGhost" type="reset"><?= h(pl_t('clear')) ?></button>
                    <?php endif; ?>

                    <button class="btn btnGold" type="submit"><?= h(pl_t('save')) ?></button>
                  </div>
                </form>
              </div>

              <div class="note" style="margin-top:12px;">
                <?= h(pl_t('agenda_note')) ?>
              </div>
            </div>
          </div>

        <?php elseif ($tab === 'espacio'): ?>
          <?php require __DIR__ . "/espacio.php"; ?>

        <?php elseif ($tab === 'perfil'): ?>
          <?php require __DIR__ . "/perfil.php"; ?>

        <?php elseif ($tab === 'aplicantes'): ?>
          <?php require __DIR__ . "/aplicantes.php"; ?>

        <?php elseif ($tab === 'contactos'): ?>
          <?php require __DIR__ . "/contactos.php"; ?>

        <?php endif; ?>
      </div>
    </section>
  </main>

  <script>
    (function(){
      function openModal(id){
        const el = document.getElementById(id);
        if(!el) return;
        el.classList.add('isOpen');
        el.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
      }

      function closeModal(el){
        if(!el) return;
        el.classList.remove('isOpen');
        el.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
      }

      document.addEventListener('click', function(e){
        const openBtn = e.target.closest('[data-open-modal]');
        if(openBtn){
          openModal(openBtn.getAttribute('data-open-modal'));
          return;
        }

        const closeBtn = e.target.closest('[data-close-modal]');
        if(closeBtn){
          closeModal(closeBtn.closest('.modalOverlay'));
          return;
        }

        if (e.target.classList && e.target.classList.contains('modalOverlay')) {
          closeModal(e.target);
        }
      });

      document.addEventListener('keydown', function(e){
        if(e.key === 'Escape'){
          const el = document.querySelector('.modalOverlay.isOpen');
          if(el) closeModal(el);
        }
      });
    })();
  </script>
</body>
</html>
<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

/* =========================
   IDIOMA LOCAL DEL PANEL MÉDICO
   ========================= */
if (isset($_GET['lang']) && in_array($_GET['lang'], ['es', 'en'], true)) {
  $_SESSION['lang'] = $_GET['lang'];
}
$mdLang = (string)($_SESSION['lang'] ?? 'es');
if (!in_array($mdLang, ['es', 'en'], true)) {
  $mdLang = 'es';
}
$_SESSION['lang'] = $mdLang;

$mdDict = [
  'es' => [
    'page_title' => 'Panel Médico | PILLAR Locums',
    'tagline' => 'Locums • Supporting Care You Can Count On',

    'panel' => 'Panel',
    'logout' => 'Salir',
    'change_language' => 'Cambiar idioma',

    'doctor_panel' => 'Panel Médico',
    'session' => 'Sesión',

    'available_offers' => 'Ofertas disponibles',
    'active_posts' => 'Publicaciones activas',
    'my_applications' => 'Mis solicitudes',
    'applications_sent' => 'Aplicaciones enviadas',
    'upcoming_schedule' => 'Agenda próxima',
    'planned_events' => 'Eventos planificados',

    'offers' => 'Ofertas',
    'requests' => 'Mis solicitudes',
    'calendar' => 'Calendario',
    'space' => 'Mi espacio',
    'profile' => 'Perfil',
    'contacts' => 'Contactos',

    'review_following' => 'Revisa lo siguiente:',

    /* Estados / autorización */
    'action_required' => 'Acción requerida:',
    'account_must_be_approved' => 'tu cuenta debe ser aprobada por el administrador para poder aplicar a ofertas.',
    'current_status' => 'Estado actual',
    'go_to_profile' => 'Ir a Perfil',
    'all' => 'Todas',
    'pending' => 'Pendientes',
    'active' => 'Activas',
    'rejected' => 'Rechazadas',
    'authorization_status' => 'Estado de autorización',
    'authorization_required_text' => 'Para postular a ofertas debes estar Autorizado y Activo.',
    'verification' => 'Verificación',
    'active_label' => 'Activo',
    'cannot_apply_yet' => 'No puedes postular aún',
    'view_offers' => 'Ver ofertas',
    'showing' => 'Mostrando',
    'total' => 'Total',
    'offer_id' => 'ID oferta',
    'applied_on' => 'Aplicaste',
    'no_applications_registered' => 'Aún no tienes solicitudes registradas.',
    'cannot_apply_until_active' => 'No puedes postular todavía: tu cuenta no está activa. Pide activación al administrador.',

    /* Calendario */
    'agenda_calendar' => 'Calendario de agenda',
    'missing_events_table' => 'No existe tabla de eventos todavía',
    'create_table_to_activate' => 'Crea la tabla para activar la agenda.',
    'today' => 'Hoy',

    'sun' => 'Dom',
    'mon' => 'Lun',
    'tue' => 'Mar',
    'wed' => 'Mié',
    'thu' => 'Jue',
    'fri' => 'Vie',
    'sat' => 'Sáb',

    'events_count' => 'evento(s)',
    'day_agenda' => 'Agenda del día',
    'offer' => 'Oferta',
    'edit' => 'Editar',
    'delete' => 'Eliminar',
    'delete_event_confirm' => '¿Eliminar este evento?',
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
    'cancel_editing' => 'Cancelar edición',
    'clear' => 'Limpiar',
    'save' => 'Guardar',
    'organize_agenda' => 'Organiza tu agenda por día y vincula eventos a ofertas (opcional).',

    /* Mi espacio */
    'photo_and_status' => 'Foto y estado',
    'description_and_languages' => 'Descripción e idiomas',
    'status_enabled_by_admin' => 'Tu estado lo habilita el administrador. La foto puedes hacerla pública cuando quieras.',
    'profile_status' => 'Estado del perfil',
    'profile_status_disabled' => 'Desactivado (no habilitado)',
    'last_updated' => 'Última actualización',
    'upload_update_photo' => 'Subir/actualizar foto (JPG/PNG, máx 4MB)',
    'select_file' => 'Seleccionar archivo',
    'no_file_selected' => 'Ningún archivo seleccionado',
    'recommended_square' => 'Recomendado: 600×600 o mayor, cuadrada.',
    'save_photo' => 'Guardar foto',
    'info_displayed_professional_profile' => 'Esta información puede mostrarse en tu perfil profesional.',
    'description_max_900' => 'Descripción (máx 900 caracteres)',
    'languages_i_speak' => 'Idiomas que manejo',
    'save_changes' => 'Guardar cambios',

    /* Idiomas */
    'spanish' => 'Español',
    'english' => 'Inglés',
    'italian' => 'Italiano',
    'french' => 'Francés',
    'german' => 'Alemán',
    'portuguese' => 'Portugués',
    'catalan_valencian' => 'Catalán/Valenciano',

    /* Defaults / labels */
    'location_default' => 'España',
    'type_default' => 'Médico',
    'status_pending' => 'Pendiente',
    'status_approved' => 'Aprobado',
    'status_rejected' => 'Rechazado',
    'status_under_review' => 'En revisión',
    'yes' => 'Sí',
    'no' => 'No',

    /* Placeholders módulos */
    'pending_requests_module' => 'Módulo “Mis solicitudes” pendiente.',
    'pending_space_module' => 'Módulo “Mi espacio” pendiente.',
    'pending_profile_module' => 'Módulo “Perfil” pendiente.',
    'pending_contacts_module' => 'Módulo “Contactos” pendiente.',

    'months' => [
      1 => 'Enero',
      2 => 'Febrero',
      3 => 'Marzo',
      4 => 'Abril',
      5 => 'Mayo',
      6 => 'Junio',
      7 => 'Julio',
      8 => 'Agosto',
      9 => 'Septiembre',
      10 => 'Octubre',
      11 => 'Noviembre',
      12 => 'Diciembre',
    ],
  ],

  'en' => [
    'page_title' => 'Doctor Dashboard | PILLAR Locums',
    'tagline' => 'Locums • Supporting Care You Can Count On',

    'panel' => 'Dashboard',
    'logout' => 'Logout',
    'change_language' => 'Change language',

    'doctor_panel' => 'Doctor Dashboard',
    'session' => 'Session',

    'available_offers' => 'Available offers',
    'active_posts' => 'Active posts',
    'my_applications' => 'My applications',
    'applications_sent' => 'Submitted applications',
    'upcoming_schedule' => 'Upcoming schedule',
    'planned_events' => 'Planned events',

    'offers' => 'Offers',
    'requests' => 'My applications',
    'calendar' => 'Calendar',
    'space' => 'My space',
    'profile' => 'Profile',
    'contacts' => 'Contacts',

    'review_following' => 'Please review the following:',

    /* States / authorization */
    'action_required' => 'Action required:',
    'account_must_be_approved' => 'your account must be approved by the administrator before you can apply for offers.',
    'current_status' => 'Current status',
    'go_to_profile' => 'Go to Profile',
    'all' => 'All',
    'pending' => 'Pending',
    'active' => 'Active',
    'rejected' => 'Rejected',
    'authorization_status' => 'Authorization status',
    'authorization_required_text' => 'To apply for offers you must be Authorized and Active.',
    'verification' => 'Verification',
    'active_label' => 'Active',
    'cannot_apply_yet' => 'You cannot apply yet',
    'view_offers' => 'View offers',
    'showing' => 'Showing',
    'total' => 'Total',
    'offer_id' => 'Offer ID',
    'applied_on' => 'Applied on',
    'no_applications_registered' => 'You do not have any registered applications yet.',
    'cannot_apply_until_active' => 'You cannot apply yet: your account is not active. Ask the administrator for activation.',

    /* Calendar */
    'agenda_calendar' => 'Schedule calendar',
    'missing_events_table' => 'Events table does not exist yet',
    'create_table_to_activate' => 'Create the table to enable the schedule.',
    'today' => 'Today',

    'sun' => 'Sun',
    'mon' => 'Mon',
    'tue' => 'Tue',
    'wed' => 'Wed',
    'thu' => 'Thu',
    'fri' => 'Fri',
    'sat' => 'Sat',

    'events_count' => 'event(s)',
    'day_agenda' => 'Day agenda',
    'offer' => 'Offer',
    'edit' => 'Edit',
    'delete' => 'Delete',
    'delete_event_confirm' => 'Delete this event?',
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
    'cancel_editing' => 'Cancel editing',
    'clear' => 'Clear',
    'save' => 'Save',
    'organize_agenda' => 'Organize your schedule by day and optionally link events to offers.',

    /* My space */
    'photo_and_status' => 'Photo and status',
    'description_and_languages' => 'Description and languages',
    'status_enabled_by_admin' => 'Your status is enabled by the administrator. You can make the photo public whenever you want.',
    'profile_status' => 'Profile status',
    'profile_status_disabled' => 'Disabled (not enabled)',
    'last_updated' => 'Last updated',
    'upload_update_photo' => 'Upload/update photo (JPG/PNG, max 4MB)',
    'select_file' => 'Select file',
    'no_file_selected' => 'No file selected',
    'recommended_square' => 'Recommended: 600×600 or larger, square.',
    'save_photo' => 'Save photo',
    'info_displayed_professional_profile' => 'This information may be displayed on your professional profile.',
    'description_max_900' => 'Description (max 900 characters)',
    'languages_i_speak' => 'Languages I speak',
    'save_changes' => 'Save changes',

    /* Languages */
    'spanish' => 'Spanish',
    'english' => 'English',
    'italian' => 'Italian',
    'french' => 'French',
    'german' => 'German',
    'portuguese' => 'Portuguese',
    'catalan_valencian' => 'Catalan/Valencian',

    /* Defaults / labels */
    'location_default' => 'Spain',
    'type_default' => 'Doctor',
    'status_pending' => 'Pending',
    'status_approved' => 'Approved',
    'status_rejected' => 'Rejected',
    'status_under_review' => 'Under review',
    'yes' => 'Yes',
    'no' => 'No',

    /* Placeholder modules */
    'pending_requests_module' => '“My applications” module pending.',
    'pending_space_module' => '“My space” module pending.',
    'pending_profile_module' => '“Profile” module pending.',
    'pending_contacts_module' => '“Contacts” module pending.',

    'months' => [
      1 => 'January',
      2 => 'February',
      3 => 'March',
      4 => 'April',
      5 => 'May',
      6 => 'June',
      7 => 'July',
      8 => 'August',
      9 => 'September',
      10 => 'October',
      11 => 'November',
      12 => 'December',
    ],
  ],
];

if (!function_exists('md_t')) {
  function md_t(string $key, ?string $fallback = null): string {
    global $mdLang, $mdDict;
    return $mdDict[$mdLang][$key] ?? ($fallback ?? $key);
  }
}

if (!function_exists('md_lang_url')) {
  function md_lang_url(string $lang): string {
    $params = $_GET;
    $params['lang'] = $lang;
    return '?' . http_build_query($params);
  }
}

if (!function_exists('md_verification_label')) {
  function md_verification_label(string $status): string {
    $status = strtolower(trim($status));
    return match ($status) {
      'approved' => md_t('status_approved'),
      'rejected' => md_t('status_rejected'),
      'under_review' => md_t('status_under_review'),
      'pending' => md_t('status_pending'),
      default => $status !== '' ? $status : md_t('status_pending'),
    };
  }
}

$mdNextLang = ($mdLang === 'en') ? 'es' : 'en';
$mdNextLangLabel = strtoupper($mdNextLang);
$mdLangQS = '&lang=' . urlencode($mdLang);
$mdMonthNames = $mdDict[$mdLang]['months'];
$mdWeekDays = [
  md_t('sun'),
  md_t('mon'),
  md_t('tue'),
  md_t('wed'),
  md_t('thu'),
  md_t('fri'),
  md_t('sat')
];

/* =========================
   AUTH / DB
   ========================= */
require_once __DIR__ . "/../../config/auth.php";
require_login();

require_once __DIR__ . "/../db.php"; // debe dejar $pdo disponible

// ===== i18n SAFE externo (por si otros includes lo usan) =====
$__i18nPath = __DIR__ . "/../../config/i18n.php";
if (is_file($__i18nPath)) {
  require_once $__i18nPath;
}
if (!function_exists('t')) {
  function t(string $key, array $vars = []): string { return $key; }
}
if (!function_exists('i18n_get_lang')) {
  function i18n_get_lang(): string { return 'es'; }
}

// ===== Usuario =====
$user = $_SESSION['user'] ?? null;
$role = (string)($user['role'] ?? '');
if (!$user || !in_array($role, ['medico','doctor'], true)) {
  header("Location: /usuarios/dashboard.php");
  exit;
}

/** Debug opcional */
$debug = isset($_GET['debug']) && $_GET['debug'] === '1';
if ($debug) {
  ini_set('display_errors', '1');
  ini_set('display_startup_errors', '1');
  error_reporting(E_ALL);
}

if (!function_exists('h')) {
  function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
  }
}

/** Datos sesión */
$doctorId    = (int)($user['id'] ?? 0);
$doctorName  = (string)($user['name'] ?? md_t('type_default'));
$doctorEmail = (string)($user['email'] ?? '');

/** Tabs */
$allowedTabs = ['ofertas','solicitudes','calendario','espacio','perfil','contactos'];
$tab = (string)($_GET['tab'] ?? 'ofertas');
if (!in_array($tab, $allowedTabs, true)) $tab = 'ofertas';

$uiErrors = [];

/* =========================
   HELPERS DB
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
    foreach (($st->fetchAll(PDO::FETCH_ASSOC) ?: []) as $r) {
      $cols[] = (string)($r['Field'] ?? '');
    }
    return array_values(array_filter($cols));
  }
}

if (!function_exists('pick_owner_column')) {
  function pick_owner_column(array $cols, array $preferred): ?string {
    foreach ($preferred as $c) {
      if (in_array($c, $cols, true)) return $c;
    }
    return null;
  }
}

/** Credenciales */
if (!function_exists('detect_doctor_credentials')) {
  function detect_doctor_credentials(PDO $pdo, int $doctorId): array {
    try {
      if (table_exists($pdo, 'users')) {
        $uCols = table_columns($pdo, 'users');
        $flagCol = pick_owner_column($uCols, ['credentials_uploaded','has_credentials','profile_verified']);
        $pathCol = pick_owner_column($uCols, ['credentials_path','credentials_url','license_path','collegiate_card_path']);

        if ($flagCol !== null) {
          $st = $pdo->prepare("SELECT `{$flagCol}` FROM `users` WHERE id=? LIMIT 1");
          $st->execute([$doctorId]);
          $v = $st->fetchColumn();
          $ok = (int)$v === 1 || $v === '1' || $v === true;
          return [$ok, $ok ? 'Credenciales cargadas' : 'Faltan credenciales'];
        }

        if ($pathCol !== null) {
          $st = $pdo->prepare("SELECT `{$pathCol}` FROM `users` WHERE id=? LIMIT 1");
          $st->execute([$doctorId]);
          $v = (string)($st->fetchColumn() ?? '');
          $ok = trim($v) !== '';
          return [$ok, $ok ? 'Credenciales cargadas' : 'Faltan credenciales'];
        }
      }

      $credTable = '';
      foreach (['medico_documents','doctor_credentials','medico_credentials','user_credentials','credentials'] as $t) {
        if (table_exists($pdo, $t)) {
          $credTable = $t;
          break;
        }
      }

      if ($credTable !== '') {
        $cCols = table_columns($pdo, $credTable);
        $owner = pick_owner_column($cCols, ['doctor_user_id','medico_user_id','user_id','medico_id','doctor_id']);
        if ($owner !== null) {
          $st = $pdo->prepare("SELECT COUNT(*) FROM `{$credTable}` WHERE `{$owner}`=?");
          $st->execute([$doctorId]);
          $n = (int)$st->fetchColumn();
          return [$n > 0, $n > 0 ? 'Credenciales cargadas' : 'Faltan credenciales'];
        }
      }

      return [false, 'Faltan credenciales'];
    } catch (Throwable $e) {
      error_log("detect_doctor_credentials ERROR: " . $e->getMessage());
      return [false, 'No se pudo validar credenciales'];
    }
  }
}

/** Estado verificación admin */
if (!function_exists('get_doctor_verification_status')) {
  function get_doctor_verification_status(PDO $pdo, int $doctorId): string {
    try {
      if (table_exists($pdo, 'medico_verifications')) {
        $st = $pdo->prepare("SELECT status FROM medico_verifications WHERE medico_id=? LIMIT 1");
        $st->execute([$doctorId]);
        $s = strtolower((string)($st->fetchColumn() ?? 'pending'));
        return $s !== '' ? $s : 'pending';
      }
    } catch (Throwable $e) {
      error_log("get_doctor_verification_status ERROR: " . $e->getMessage());
    }
    return 'pending';
  }
}

// ===== Estado credenciales + aprobación =====
$doctorCredentialsComplete = false;
$doctorCredentialsLabel = 'Faltan credenciales';

try {
  if (!isset($pdo) || !($pdo instanceof PDO)) {
    throw new RuntimeException("PDO no disponible.");
  }
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  [$doctorCredentialsComplete, $doctorCredentialsLabel] = detect_doctor_credentials($pdo, $doctorId);
} catch (Throwable $e) {
  $doctorCredentialsComplete = false;
  $doctorCredentialsLabel = 'No se pudo validar credenciales';
  if ($debug) $uiErrors[] = "DEBUG PDO: " . $e->getMessage();
}

$doctorVerificationStatus = get_doctor_verification_status($pdo, $doctorId);
$doctorIsApproved = ($doctorVerificationStatus === 'approved');

// permiso final para postular
$doctorCanApply = $doctorIsApproved;
// $doctorCanApply = $doctorIsApproved && $doctorCredentialsComplete;

// ===== KPIs =====
$jobs = [];
$kpiAvailableOffers = 0;
$kpiMyApplications  = 0;
$kpiUpcoming        = 0;

/** Ofertas */
try {
  if (table_exists($pdo, 'jobs')) {
    $jCols = table_columns($pdo, 'jobs');

    $colTitle   = pick_owner_column($jCols, ['title','name','job_title']);
    $colStatus  = pick_owner_column($jCols, ['status','state']);
    $colActive  = pick_owner_column($jCols, ['is_active','active']);
    $colCreated = pick_owner_column($jCols, ['created_at','created']);

    $select = ['`id`'];
    if ($colTitle)   $select[] = "`{$colTitle}` AS `title`";
    if ($colStatus)  $select[] = "`{$colStatus}` AS `status`";
    if ($colActive)  $select[] = "`{$colActive}` AS `is_active`";
    if ($colCreated) $select[] = "`{$colCreated}` AS `created_at`";

    $sql = "SELECT " . implode(', ', $select) . " FROM `jobs`";
    if ($colStatus) $sql .= " WHERE `{$colStatus}` IN ('active','open','published')";
    $sql .= $colCreated ? " ORDER BY `{$colCreated}` DESC" : " ORDER BY `id` DESC";

    $st = $pdo->query($sql);
    $jobs = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $kpiAvailableOffers = 0;
    foreach ($jobs as $j) {
      $status = (string)($j['status'] ?? '');
      $isActive = isset($j['is_active']) ? (int)$j['is_active'] : null;

      if ($status !== '') {
        if (in_array($status, ['active','open','published'], true)) $kpiAvailableOffers++;
      } elseif ($isActive !== null) {
        if ($isActive === 1) $kpiAvailableOffers++;
      } else {
        $kpiAvailableOffers = count($jobs);
        break;
      }
    }

    if ($kpiAvailableOffers === 0 && $jobs) {
      $kpiAvailableOffers = count($jobs);
    }
  }
} catch (Throwable $e) {
  error_log("LOAD jobs ERROR: " . $e->getMessage());
  if ($debug) $uiErrors[] = "DEBUG jobs: " . $e->getMessage();
}

/** KPI solicitudes */
try {
  if (table_exists($pdo, 'job_applications')) {
    $appCols = table_columns($pdo, 'job_applications');
    $appOwner = pick_owner_column($appCols, ['doctor_user_id','medico_user_id','user_id']);
    if ($appOwner !== null) {
      $st = $pdo->prepare("SELECT COUNT(*) FROM `job_applications` WHERE `{$appOwner}`=?");
      $st->execute([$doctorId]);
      $kpiMyApplications = (int)$st->fetchColumn();
    }
  }
} catch (Throwable $e) {
  error_log("KPI applications ERROR: " . $e->getMessage());
  if ($debug) $uiErrors[] = "DEBUG applications: " . $e->getMessage();
}

/** Calendario */
$eventsMonth = [];
$countByDate = [];
$eventsDay = [];
$editing = null;
$eventsTable = '';

$todayY = (int)date('Y');
$todayM = (int)date('n');
$todayD = (int)date('j');

$year  = (int)($_GET['y'] ?? $todayY);
$month = (int)($_GET['m'] ?? $todayM);
$day   = (int)($_GET['d'] ?? $todayD);

if ($month < 1)  { $month = 12; $year--; }
if ($month > 12) { $month = 1;  $year++; }

$daysInMonth = (int)cal_days_in_month(CAL_GREGORIAN, $month, $year);
$day = max(1, min($day, $daysInMonth));

$firstDay = (int)date('w', strtotime(sprintf('%04d-%02d-01', $year, $month)));
$selectedDate = sprintf('%04d-%02d-%02d', $year, $month, $day);
$rangeStart = sprintf('%04d-%02d-01', $year, $month);
$rangeEnd   = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);

$editId = (int)($_GET['edit'] ?? 0);

try {
  foreach (['doctor_events','medico_events','clinic_events','hospital_events'] as $t) {
    if (table_exists($pdo, $t)) {
      $eventsTable = $t;
      break;
    }
  }

  if ($eventsTable !== '') {
    $cols = table_columns($pdo, $eventsTable);

    foreach (['id','event_date','title'] as $req) {
      if (!in_array($req, $cols, true)) {
        throw new RuntimeException("{$eventsTable} sin columna: {$req}");
      }
    }

    $ownerCol = pick_owner_column($cols, ['doctor_user_id','medico_user_id','clinic_user_id','hospital_user_id','user_id']);
    if ($ownerCol === null) {
      throw new RuntimeException("{$eventsTable} sin columna owner.");
    }

    $hasJobId  = in_array('job_id', $cols, true);
    $hasStart  = in_array('start_time', $cols, true);
    $hasEnd    = in_array('end_time', $cols, true);
    $hasStatus = in_array('status', $cols, true);
    $hasNotes  = in_array('notes', $cols, true);

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tab === 'calendario') {
      $action = (string)($_POST['action'] ?? '');

      if (in_array($action, ['create_event','update_event','delete_event'], true)) {
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

        if (!$errs && in_array($action, ['update_event','delete_event'], true)) {
          $stOwn = $pdo->prepare("SELECT id FROM `{$eventsTable}` WHERE id=? AND `{$ownerCol}`=? LIMIT 1");
          $stOwn->execute([$eventId, $doctorId]);
          if (!$stOwn->fetchColumn()) $errs[] = "No autorizado.";
        }

        if ($errs) {
          foreach ($errs as $er) $uiErrors[] = $er;
        } else {
          if ($action === 'create_event') {
            $fields = [
              $ownerCol    => $doctorId,
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

            $sqlIns = "INSERT INTO `{$eventsTable}` ({$colsSql}) VALUES ({$valsSql})";
            $stI = $pdo->prepare($sqlIns);
            $stI->execute(array_values($fields));

            header("Location: ?tab=calendario&y={$year}&m={$month}&d=" . (int)substr($date, 8, 2) . $mdLangQS);
            exit;
          }

          if ($action === 'update_event') {
            $sets = ["event_date=?", "title=?"];
            $vals = [$date, $title];

            if ($hasJobId)  { $sets[] = "job_id=?";     $vals[] = $jobId; }
            if ($hasStart)  { $sets[] = "start_time=?"; $vals[] = ($start !== '' ? $start : null); }
            if ($hasEnd)    { $sets[] = "end_time=?";   $vals[] = ($end !== '' ? $end : null); }
            if ($hasStatus) { $sets[] = "status=?";     $vals[] = ($status !== '' ? $status : null); }
            if ($hasNotes)  { $sets[] = "notes=?";      $vals[] = ($notes !== '' ? $notes : null); }

            $vals[] = $eventId;
            $vals[] = $doctorId;

            $sqlUp = "UPDATE `{$eventsTable}` SET " . implode(',', $sets) . " WHERE id=? AND `{$ownerCol}`=? LIMIT 1";
            $stU = $pdo->prepare($sqlUp);
            $stU->execute($vals);

            header("Location: ?tab=calendario&y={$year}&m={$month}&d=" . (int)substr($date, 8, 2) . $mdLangQS);
            exit;
          }

          if ($action === 'delete_event') {
            $stD = $pdo->prepare("DELETE FROM `{$eventsTable}` WHERE id=? AND `{$ownerCol}`=? LIMIT 1");
            $stD->execute([$eventId, $doctorId]);

            header("Location: ?tab=calendario&y={$year}&m={$month}&d={$day}{$mdLangQS}");
            exit;
          }
        }
      }
    }

    $sqlMonth = "SELECT * FROM `{$eventsTable}` WHERE `{$ownerCol}`=? AND event_date BETWEEN ? AND ? ORDER BY event_date ASC";
    if ($hasStart) $sqlMonth .= ", start_time ASC";

    $stM = $pdo->prepare($sqlMonth);
    $stM->execute([$doctorId, $rangeStart, $rangeEnd]);
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
      $stE->execute([$editId, $doctorId]);
      $editing = $stE->fetch(PDO::FETCH_ASSOC) ?: null;
    }
  }

} catch (Throwable $e) {
  error_log("calendar ERROR: " . $e->getMessage());
  if ($tab === 'calendario') $uiErrors[] = "No se pudo cargar la agenda.";
  if ($debug) $uiErrors[] = "DEBUG calendar: " . $e->getMessage();
}

/** Perfil mínimo header */
$profile = [
  'name' => $doctorName ?: md_t('type_default'),
  'location' => md_t('location_default'),
  'type' => md_t('type_default'),
];
?>
<!doctype html>
<html lang="<?= h($mdLang) ?>">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title><?= h(md_t('page_title')) ?></title>

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
    .btnDanger,
    .btnLang{
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
    .btnDanger:hover,
    .btnLang:hover{
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

    .btnLang{
      width:44px;
      height:44px;
      min-width:44px;
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
      text-decoration:none;
      line-height:1;
    }

    .btnLang .langCode{
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
      color:#fff;
    }

    .hero p{
      margin:8px 0 0;
      font-size:15px;
      color:rgba(255,255,255,.90);
    }

    .muted{
      color:rgba(70,70,70,.70);
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
    .alert{
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

    .alert{
      border-radius:18px;
      padding:16px 18px;
      margin:10px 0 18px;
      background:linear-gradient(135deg, rgba(245,219,208,.70), rgba(255,255,255,.96));
      display:flex;
      align-items:flex-start;
      justify-content:space-between;
      gap:14px;
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

    .errBox{
      border-radius:18px;
      padding:16px 18px;
      background:rgba(206,147,121,.12);
      border-color:rgba(206,147,121,.28);
      color:var(--pl-charcoal);
      margin-bottom:18px;
    }

    .sectionTitle{
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:16px;
      margin-bottom:18px;
      flex-wrap:wrap;
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

    .divider{
      height:1px;
      background:rgba(61,109,118,.12);
      margin:12px 0;
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
      display:grid;
      grid-template-columns:1fr 1fr;
      gap:10px;
    }

    .input,
    .select,
    textarea.input,
    input[type="date"],
    input[type="time"],
    input[type="text"],
    select{
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
    textarea.input:focus,
    input:focus,
    select:focus{
      border-color:rgba(61,109,118,.34);
      box-shadow:0 0 0 4px rgba(151,195,188,.18);
    }

    .note{
      border-radius:18px;
      padding:14px 15px;
      background:rgba(245,219,208,.24);
      color:var(--pl-charcoal);
      line-height:1.45;
      font-size:.95rem;
    }

    .btnRow{
      display:flex;
      gap:10px;
      justify-content:flex-end;
      flex-wrap:wrap;
      margin-top:12px;
      align-items:center;
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

    code{
      background:rgba(61,109,118,.08);
      padding:2px 6px;
      border-radius:8px;
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

      .alert{
        flex-direction:column;
        align-items:flex-start;
      }

      .calCell{
        min-height:88px;
        padding:8px;
      }

      .row,
      .btnRow{
        grid-template-columns:1fr;
        display:grid;
      }

      .btn,
      .btnDanger{
        width:100%;
      }

      .btnLang{
        width:40px;
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
          <span><?= h(md_t('tagline')) ?></span>
        </div>
      </a>

      <div class="topActions">
        <a class="btn btnGhost" href="/usuarios/dashboard.php"><?= h(md_t('panel')) ?></a>

        <a
          class="btnLang"
          href="<?= h(md_lang_url($mdNextLang)) ?>"
          title="<?= h(md_t('change_language')) ?>"
          aria-label="<?= h(md_t('change_language')) ?>"
        >
          🌐
          <span class="langCode"><?= h($mdNextLangLabel) ?></span>
        </a>

        <a class="btn btnGold" href="/usuarios/logout.php"><?= h(md_t('logout')) ?></a>
      </div>
    </div>
  </header>

  <main class="container">
    <div class="hero">
      <div>
        <h1><?= h($profile['name']) ?> — <?= h(md_t('doctor_panel')) ?></h1>
        <p><?= h($profile['location']) ?> • <?= h($profile['type']) ?></p>
        <p class="muted" style="margin-top:8px; color:rgba(255,255,255,.85);">
          <?= h(md_t('session')) ?>: <?= h($doctorEmail) ?> • ID: <?= (int)$doctorId ?>
        </p>
      </div>
    </div>

    <?php if (!$doctorIsApproved): ?>
      <div class="alert">
        <div>
          <strong><?= h(md_t('action_required')) ?></strong>
          <?= h(md_t('account_must_be_approved')) ?><br>
          <span class="muted"><?= h(md_t('current_status')) ?>: <?= h(md_verification_label($doctorVerificationStatus)) ?></span>
        </div>
        <div>
          <a class="btn btnGold" href="?tab=perfil<?= $mdLangQS ?>"><?= h(md_t('go_to_profile')) ?></a>
        </div>
      </div>
    <?php endif; ?>

    <section class="cards">
      <div class="card">
        <div class="label"><?= h(md_t('available_offers')) ?></div>
        <div class="kpi"><?= (int)$kpiAvailableOffers ?></div>
        <div class="muted"><?= h(md_t('active_posts')) ?></div>
      </div>

      <div class="card">
        <div class="label"><?= h(md_t('my_applications')) ?></div>
        <div class="kpi"><?= (int)$kpiMyApplications ?></div>
        <div class="muted"><?= h(md_t('applications_sent')) ?></div>
      </div>

      <div class="card">
        <div class="label"><?= h(md_t('upcoming_schedule')) ?></div>
        <div class="kpi"><?= (int)$kpiUpcoming ?></div>
        <div class="muted"><?= h(md_t('planned_events')) ?></div>
      </div>
    </section>

    <section class="panelWrap">
      <nav class="tabs">
        <a class="tab <?= $tab === 'ofertas' ? 'active' : '' ?>" href="?tab=ofertas<?= $mdLangQS ?>"><?= h(md_t('offers')) ?></a>
        <a class="tab <?= $tab === 'solicitudes' ? 'active' : '' ?>" href="?tab=solicitudes<?= $mdLangQS ?>"><?= h(md_t('requests')) ?></a>
        <a class="tab <?= $tab === 'calendario' ? 'active' : '' ?>" href="?tab=calendario&y=<?= (int)$year ?>&m=<?= (int)$month ?>&d=<?= (int)$day ?><?= $mdLangQS ?>"><?= h(md_t('calendar')) ?></a>
        <a class="tab <?= $tab === 'espacio' ? 'active' : '' ?>" href="?tab=espacio<?= $mdLangQS ?>"><?= h(md_t('space')) ?></a>
        <a class="tab <?= $tab === 'perfil' ? 'active' : '' ?>" href="?tab=perfil<?= $mdLangQS ?>"><?= h(md_t('profile')) ?></a>
        <a class="tab <?= $tab === 'contactos' ? 'active' : '' ?>" href="?tab=contactos<?= $mdLangQS ?>"><?= h(md_t('contacts')) ?></a>
      </nav>

      <div class="content">
        <?php if ($uiErrors): ?>
          <div class="errBox">
            <strong><?= h(md_t('review_following')) ?></strong>
            <ul style="margin:8px 0 0; padding-left:18px;">
              <?php foreach ($uiErrors as $e): ?>
                <li><?= h((string)$e) ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

        <?php if ($tab === 'ofertas'): ?>
          <?php
            $PILLAR_OFFERS_RENDER = true;
            $PILLAR_OFFERS_MODE = 'doctor';
            $PILLAR_DOCTOR_CAN_APPLY = $doctorCanApply;
            require __DIR__ . "/ofertas.php";
          ?>

        <?php elseif ($tab === 'solicitudes'): ?>
          <?php
            if (is_file(__DIR__ . "/solicitudes.php")) {
              require __DIR__ . "/solicitudes.php";
            } else {
              echo '<div class="note"><strong>' . h(md_t('pending_requests_module')) . '</strong></div>';
            }
          ?>

        <?php elseif ($tab === 'calendario'): ?>

          <div class="sectionTitle">
            <h2><?= h(md_t('agenda_calendar')) ?></h2>
          </div>

          <?php if (empty($eventsTable)): ?>
            <div class="note">
              <?= h(md_t('missing_events_table')) ?> (<code>doctor_events</code> o <code>medico_events</code>).<br>
              <?= h(md_t('create_table_to_activate')) ?>
            </div>
          <?php endif; ?>

          <div class="grid2">
            <div class="panel">
              <div class="calHeader">
                <div class="muted"><?= h($mdMonthNames[$month] ?? '') ?> <?= (int)$year ?></div>
                <div class="calNav">
                  <a class="btn btnGhost" href="?tab=calendario&y=<?= (int)$year ?>&m=<?= (int)($month - 1) ?>&d=<?= (int)$day ?><?= $mdLangQS ?>">‹</a>
                  <a class="btn btnGhost" href="?tab=calendario&y=<?= (int)date('Y') ?>&m=<?= (int)date('n') ?>&d=<?= (int)date('j') ?><?= $mdLangQS ?>"><?= h(md_t('today')) ?></a>
                  <a class="btn btnGhost" href="?tab=calendario&y=<?= (int)$year ?>&m=<?= (int)($month + 1) ?>&d=<?= (int)$day ?><?= $mdLangQS ?>">›</a>
                </div>
              </div>

              <div class="calGrid">
                <div class="calWeek">
                  <div><?= h($mdWeekDays[0]) ?></div>
                  <div><?= h($mdWeekDays[1]) ?></div>
                  <div><?= h($mdWeekDays[2]) ?></div>
                  <div><?= h($mdWeekDays[3]) ?></div>
                  <div><?= h($mdWeekDays[4]) ?></div>
                  <div><?= h($mdWeekDays[5]) ?></div>
                  <div><?= h($mdWeekDays[6]) ?></div>
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
                      $style = $isSel ? 'border-color: rgba(61,109,118,.35); background: rgba(151,195,188,.16);' : '';

                      echo '<a class="calCell" style="' . $style . '" href="?tab=calendario&y=' . $year . '&m=' . $month . '&d=' . $d . $mdLangQS . '">';
                      echo '<div class="calDayNum"><span>' . $d . '</span>';
                      if ($count > 0) {
                        echo '<span class="dot" title="' . $count . ' ' . h(md_t('events_count')) . '"></span>';
                      }
                      echo '</div>';
                      echo ($count > 0)
                        ? '<div class="small">' . $count . ' ' . h(md_t('events_count')) . '</div>'
                        : '<div class="small">&nbsp;</div>';
                      echo '</a>';
                    }
                  ?>
                </div>
              </div>
            </div>

            <div class="panel">
              <h3><?= h(md_t('day_agenda')) ?></h3>
              <div class="muted" style="margin-bottom:10px;"><?= h($selectedDate) ?></div>

              <?php if (!empty($eventsDay)): ?>
                <?php foreach ($eventsDay as $ev): ?>
                  <?php
                    $jid = (int)($ev['job_id'] ?? 0);
                    $jobLabel = '';
                    if ($jid > 0 && !empty($jobs)) {
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
                      <?= ($jobLabel !== '' ? ' • ' . h(md_t('offer')) . ': ' . h($jobLabel) : '') ?>
                    </span>

                    <div class="btnRow">
                      <a class="btn btnGhost" href="?tab=calendario&y=<?= (int)$year ?>&m=<?= (int)$month ?>&d=<?= (int)$day ?>&edit=<?= (int)$ev['id'] ?><?= $mdLangQS ?>"><?= h(md_t('edit')) ?></a>

                      <form method="post" onsubmit="return confirm('<?= h(md_t('delete_event_confirm')) ?>');">
                        <input type="hidden" name="action" value="delete_event">
                        <input type="hidden" name="event_id" value="<?= (int)$ev['id'] ?>">
                        <button class="btnDanger" type="submit"><?= h(md_t('delete')) ?></button>
                      </form>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php else: ?>
                <div class="note"><?= h(md_t('no_events_day')) ?></div>
              <?php endif; ?>

              <div class="divider"></div>

              <h3 style="margin:0 0 8px;"><?= $editing ? h(md_t('edit_event')) : h(md_t('create_event')) ?></h3>

              <form method="post">
                <input type="hidden" name="action" value="<?= $editing ? 'update_event' : 'create_event' ?>">
                <input type="hidden" name="event_id" value="<?= (int)($editing['id'] ?? 0) ?>">

                <div class="field">
                  <label><?= h(md_t('date')) ?></label>
                  <input class="input" name="date" type="date" value="<?= h((string)($editing['event_date'] ?? $selectedDate)) ?>" required>
                </div>

                <div class="field">
                  <label><?= h(md_t('link_offer_optional')) ?></label>
                  <select class="select" name="job_id">
                    <option value="0"><?= h(md_t('no_offer')) ?></option>
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
                  <label><?= h(md_t('title')) ?></label>
                  <input class="input" name="title" value="<?= h((string)($editing['title'] ?? '')) ?>" required>
                </div>

                <div class="row">
                  <div class="field">
                    <label><?= h(md_t('start_time_optional')) ?></label>
                    <input class="input" name="start_time" type="time" value="<?= h((string)($editing['start_time'] ?? '')) ?>">
                  </div>

                  <div class="field">
                    <label><?= h(md_t('end_time_optional')) ?></label>
                    <input class="input" name="end_time" type="time" value="<?= h((string)($editing['end_time'] ?? '')) ?>">
                  </div>
                </div>

                <div class="row">
                  <div class="field">
                    <label><?= h(md_t('status')) ?></label>
                    <?php $cur = (string)($editing['status'] ?? 'planned'); ?>
                    <select class="select" name="status">
                      <option value="planned" <?= $cur === 'planned' ? 'selected' : '' ?>><?= h(md_t('planned')) ?></option>
                      <option value="confirmed" <?= $cur === 'confirmed' ? 'selected' : '' ?>><?= h(md_t('confirmed')) ?></option>
                      <option value="cancelled" <?= $cur === 'cancelled' ? 'selected' : '' ?>><?= h(md_t('cancelled')) ?></option>
                      <option value="completed" <?= $cur === 'completed' ? 'selected' : '' ?>><?= h(md_t('completed')) ?></option>
                    </select>
                  </div>

                  <div class="field">
                    <label><?= h(md_t('notes_optional')) ?></label>
                    <input class="input" name="notes" value="<?= h((string)($editing['notes'] ?? '')) ?>">
                  </div>
                </div>

                <div class="btnRow">
                  <?php if ($editing): ?>
                    <a class="btn btnGhost" href="?tab=calendario&y=<?= (int)$year ?>&m=<?= (int)$month ?>&d=<?= (int)$day ?><?= $mdLangQS ?>"><?= h(md_t('cancel_editing')) ?></a>
                  <?php else: ?>
                    <button class="btn btnGhost" type="reset"><?= h(md_t('clear')) ?></button>
                  <?php endif; ?>

                  <button class="btn btnGold" type="submit"><?= h(md_t('save')) ?></button>
                </div>
              </form>

              <div class="note" style="margin-top:12px;">
                <?= h(md_t('organize_agenda')) ?>
              </div>
            </div>
          </div>

        <?php elseif ($tab === 'espacio'): ?>
          <?php
            if (is_file(__DIR__ . "/espacio.php")) {
              require __DIR__ . "/espacio.php";
            } else {
              echo '<div class="note"><strong>' . h(md_t('pending_space_module')) . '</strong></div>';
            }
          ?>

        <?php elseif ($tab === 'perfil'): ?>
          <?php
            if (is_file(__DIR__ . "/perfil.php")) {
              require __DIR__ . "/perfil.php";
            } else {
              echo '<div class="note"><strong>' . h(md_t('pending_profile_module')) . '</strong></div>';
            }
          ?>

        <?php elseif ($tab === 'contactos'): ?>
          <?php
            if (is_file(__DIR__ . "/contactos.php")) {
              require __DIR__ . "/contactos.php";
            } else {
              echo '<div class="note"><strong>' . h(md_t('pending_contacts_module')) . '</strong></div>';
            }
          ?>
        <?php endif; ?>

      </div>
    </section>
  </main>
</body>
</html>
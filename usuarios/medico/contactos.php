<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

if (!function_exists('h')) {
  function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
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

/** Si entra directo (no desde index), garantizamos auth/db */
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

$doctorId = (int)($user['id'] ?? 0);
$q        = trim((string)($_GET['q'] ?? ''));
$editId   = (int)($_GET['edit'] ?? 0);

$uiErrors = $uiErrors ?? [];
$flashOk  = '';
$flashErr = '';

/* ============================================================
   I18N LOCAL DEL MÓDULO CONTACTOS
============================================================ */
$conLang = (string)($_SESSION['lang'] ?? 'es');
if (!in_array($conLang, ['es', 'en'], true)) {
  $conLang = 'es';
}

$conDict = [
  'es' => [
    'saved_ok' => 'Cambios guardados correctamente.',
    'deleted_ok' => 'Contacto eliminado.',
    'csrf_invalid' => 'Sesión inválida (CSRF). Recarga e inténtalo de nuevo.',
    'load_error' => 'No se pudo cargar la sección de contactos.',
    'review_following' => 'Revisa lo siguiente:',

    'contacts_title' => 'Contactos',
    'contacts_subtitle' => 'Guarda agencias, RRHH, clínicas/hospitales o colegas para seguimiento.',
    'search_placeholder' => 'Buscar por nombre, email, teléfono, etiquetas...',
    'search' => 'Buscar',
    'clear' => 'Limpiar',

    'active_chats' => 'Chats activos',
    'no_active_chats' => 'No hay conversaciones activas todavía.',
    'open_chat' => 'Abrir chat',
    'job_id' => 'Job ID',
    'last_message' => 'Último mensaje',
    'counterpart' => 'Contacto',

    'contacts_not_enabled' => 'Contactos aún no está habilitado en base de datos.',
    'missing_contacts_table' => 'Falta la tabla doctor_contacts.',

    'contacts_list' => 'Lista de contactos',
    'no_contacts' => 'No hay contactos guardados.',
    'email' => 'Email',
    'phone' => 'Tel',
    'tags' => 'Tags',
    'edit' => 'Editar',
    'delete' => 'Eliminar',
    'delete_confirm' => '¿Eliminar este contacto?',
    'tracking_hint' => 'Útil para seguimiento: teléfono, email, notas, etiquetas.',

    'edit_contact' => 'Editar contacto',
    'new_contact' => 'Nuevo contacto',
    'name' => 'Nombre',
    'email_optional' => 'Email (opcional)',
    'phone_optional' => 'Teléfono (opcional)',
    'role_company_optional' => 'Rol / Empresa (opcional)',
    'tags_optional' => 'Etiquetas (opcional)',
    'notes_optional' => 'Notas (opcional)',
    'cancel' => 'Cancelar',
    'reset' => 'Limpiar',
    'save' => 'Guardar',

    'name_required' => 'Nombre obligatorio.',
    'invalid_email' => 'Email inválido.',
    'invalid_contact' => 'Contacto inválido.',
    'invalid_action' => 'Acción no válida.',
    'not_authorized' => 'No autorizado.',
    'owner_col_missing' => 'La tabla de contactos no tiene columna owner.',
    'required_column_missing' => 'La tabla de contactos no tiene una columna requerida.',

    /* NUEVO: autoguardado desde chat */
    'auto_contact_tag' => 'chat activo',
    'auto_contact_note' => 'Contacto creado automáticamente desde el chat · Conversación #{conversation_id} · Job #{job_id}',
    'contact_role_clinic' => 'Clínica',
    'contact_role_hospital' => 'Hospital',
  ],
  'en' => [
    'saved_ok' => 'Changes saved successfully.',
    'deleted_ok' => 'Contact deleted.',
    'csrf_invalid' => 'Invalid session (CSRF). Refresh and try again.',
    'load_error' => 'The contacts section could not be loaded.',
    'review_following' => 'Please review the following:',

    'contacts_title' => 'Contacts',
    'contacts_subtitle' => 'Save agencies, HR contacts, clinics/hospitals or colleagues for follow-up.',
    'search_placeholder' => 'Search by name, email, phone, tags...',
    'search' => 'Search',
    'clear' => 'Clear',

    'active_chats' => 'Active chats',
    'no_active_chats' => 'There are no active conversations yet.',
    'open_chat' => 'Open chat',
    'job_id' => 'Job ID',
    'last_message' => 'Last message',
    'counterpart' => 'Contact',

    'contacts_not_enabled' => 'Contacts is not enabled in the database yet.',
    'missing_contacts_table' => 'The doctor_contacts table is missing.',

    'contacts_list' => 'Contacts list',
    'no_contacts' => 'There are no saved contacts.',
    'email' => 'Email',
    'phone' => 'Phone',
    'tags' => 'Tags',
    'edit' => 'Edit',
    'delete' => 'Delete',
    'delete_confirm' => 'Delete this contact?',
    'tracking_hint' => 'Useful for follow-up: phone, email, notes, tags.',

    'edit_contact' => 'Edit contact',
    'new_contact' => 'New contact',
    'name' => 'Name',
    'email_optional' => 'Email (optional)',
    'phone_optional' => 'Phone (optional)',
    'role_company_optional' => 'Role / Company (optional)',
    'tags_optional' => 'Tags (optional)',
    'notes_optional' => 'Notes (optional)',
    'cancel' => 'Cancel',
    'reset' => 'Reset',
    'save' => 'Save',

    'name_required' => 'Name is required.',
    'invalid_email' => 'Invalid email.',
    'invalid_contact' => 'Invalid contact.',
    'invalid_action' => 'Invalid action.',
    'not_authorized' => 'Not authorized.',
    'owner_col_missing' => 'The contacts table has no owner column.',
    'required_column_missing' => 'The contacts table is missing a required column.',

    /* NUEVO: autoguardado desde chat */
    'auto_contact_tag' => 'active chat',
    'auto_contact_note' => 'Contact automatically created from chat · Conversation #{conversation_id} · Job #{job_id}',
    'contact_role_clinic' => 'Clinic',
    'contact_role_hospital' => 'Hospital',
  ],
];

if (!function_exists('con_t')) {
  function con_t(string $key, string $fallback = ''): string {
    global $conDict, $conLang;

    if (isset($conDict[$conLang][$key])) {
      return (string)$conDict[$conLang][$key];
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

$LANG_QS = '&lang=' . urlencode($conLang);
$returnBase = u("index.php") . "?tab=contactos&lang=" . urlencode($conLang);

/** CSRF */
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = (string)$_SESSION['csrf_token'];

/** PRG flash */
if (isset($_GET['ok']) && $_GET['ok'] === '1')   $flashOk = con_t('saved_ok', 'Cambios guardados correctamente.');
if (isset($_GET['ok']) && $_GET['ok'] === 'del') $flashOk = con_t('deleted_ok', 'Contacto eliminado.');

/** Helpers DB */
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
    foreach ($candidates as $c) {
      if (in_array($c, $cols, true)) return $c;
    }
    return null;
  }
}
if (!function_exists('merge_contact_tags')) {
  function merge_contact_tags(string $existing, string $newTag): string {
    $existing = trim($existing);
    $newTag   = trim($newTag);

    if ($newTag === '') return $existing;

    $parts = array_filter(array_map('trim', explode(',', $existing)));
    if (!in_array($newTag, $parts, true)) {
      $parts[] = $newTag;
    }
    return implode(', ', $parts);
  }
}
if (!function_exists('merge_contact_notes')) {
  function merge_contact_notes(string $existing, string $newNote): string {
    $existing = trim($existing);
    $newNote  = trim($newNote);

    if ($newNote === '') return $existing;
    if ($existing === '') return $newNote;
    if (mb_stripos($existing, $newNote) !== false) return $existing;

    return $existing . ' | ' . $newNote;
  }
}

$contactsTable = null;
$tableMissing  = false;
$cols          = [];
$ownerCol      = null;

$contacts = [];
$editing  = null;
$activeChats = [];

try {
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  if ($doctorId <= 0) {
    throw new RuntimeException("doctorId inválido.");
  }

  /* ============================================================
     1) CARGAR CHATS REALES DESDE conversaciones
  ============================================================ */
  if (table_exists($pdo, 'conversaciones')) {
    $convCols = table_columns($pdo, 'conversaciones');
    $msgCols  = table_exists($pdo, 'mensajes') ? table_columns($pdo, 'mensajes') : [];
    $userCols = table_exists($pdo, 'users') ? table_columns($pdo, 'users') : [];
    $jobCols  = table_exists($pdo, 'jobs') ? table_columns($pdo, 'jobs') : [];

    $convIdCol       = pick_col($convCols, ['id']) ?? 'id';
    $convDoctorCol   = pick_col($convCols, ['doctor_id','medico_id','doctor_user_id','medico_user_id']);
    $convClinicCol   = pick_col($convCols, ['clinica_id','clinic_id','clinic_user_id']);
    $convHospitalCol = pick_col($convCols, ['hospital_id','hospital_user_id']);
    $convJobCol      = pick_col($convCols, ['job_id']);

    $uNameCol        = $userCols ? (pick_col($userCols, ['name','full_name','display_name']) ?? 'name') : null;
    $uEmailCol       = $userCols ? pick_col($userCols, ['email','mail']) : null;
    $uPhoneCol       = $userCols ? pick_col($userCols, ['phone','telefono','mobile','phone_number']) : null;

    $msgConvCol      = $msgCols ? pick_col($msgCols, ['conversation_id']) : null;
    $msgBodyCol      = $msgCols ? pick_col($msgCols, ['message','body','content','texto']) : null;
    $msgCreatedCol   = $msgCols ? pick_col($msgCols, ['created_at','sent_at','created']) : null;
    $jobTitleCol     = $jobCols ? pick_col($jobCols, ['title','name','job_title']) : null;

    if ($convDoctorCol) {
      $sqlC = "SELECT * FROM `conversaciones` WHERE `{$convDoctorCol}` = :did ORDER BY `{$convIdCol}` DESC";
      $stC = $pdo->prepare($sqlC);
      $stC->execute([':did' => $doctorId]);
      $conversations = $stC->fetchAll(PDO::FETCH_ASSOC) ?: [];

      foreach ($conversations as $conv) {
        $conversationId = (int)($conv[$convIdCol] ?? 0);
        $jobId = (int)($conv[$convJobCol] ?? 0);

        $counterpartId = 0;
        $counterpartType = '';
        if ($convClinicCol && !empty($conv[$convClinicCol])) {
          $counterpartId = (int)$conv[$convClinicCol];
          $counterpartType = 'clinic';
        } elseif ($convHospitalCol && !empty($conv[$convHospitalCol])) {
          $counterpartId = (int)$conv[$convHospitalCol];
          $counterpartType = 'hospital';
        }

        $counterpartName  = ($counterpartType === 'hospital')
          ? con_t('contact_role_hospital', 'Hospital')
          : con_t('contact_role_clinic', 'Clínica');
        $counterpartEmail = '';
        $counterpartPhone = '';

        if ($counterpartId > 0 && ($uNameCol || $uEmailCol || $uPhoneCol)) {
          $userSelect = [];
          if ($uNameCol)  $userSelect[] = "`{$uNameCol}` AS uname";
          if ($uEmailCol) $userSelect[] = "`{$uEmailCol}` AS uemail";
          if ($uPhoneCol) $userSelect[] = "`{$uPhoneCol}` AS uphone";

          if ($userSelect) {
            $stU = $pdo->prepare("SELECT " . implode(', ', $userSelect) . " FROM `users` WHERE `id` = :id LIMIT 1");
            $stU->execute([':id' => $counterpartId]);
            $u = $stU->fetch(PDO::FETCH_ASSOC);

            if ($u) {
              if (!empty($u['uname']))  $counterpartName  = (string)$u['uname'];
              if (!empty($u['uemail'])) $counterpartEmail = (string)$u['uemail'];
              if (!empty($u['uphone'])) $counterpartPhone = (string)$u['uphone'];
            }
          }
        }

        $jobTitle = '';
        if ($jobId > 0 && $jobTitleCol) {
          $stJ = $pdo->prepare("SELECT `{$jobTitleCol}` AS jtitle FROM `jobs` WHERE `id` = :id LIMIT 1");
          $stJ->execute([':id' => $jobId]);
          $j = $stJ->fetch(PDO::FETCH_ASSOC);
          if ($j && !empty($j['jtitle'])) {
            $jobTitle = (string)$j['jtitle'];
          }
        }

        $lastMessage = '';
        $lastDate = '';
        if ($msgConvCol && $msgBodyCol) {
          $orderMsg = $msgCreatedCol ? "`{$msgCreatedCol}` DESC" : "`id` DESC";
          $selectMsgDate = $msgCreatedCol ? ", `{$msgCreatedCol}` AS mcreated" : ", NULL AS mcreated";
          $stM = $pdo->prepare("SELECT `{$msgBodyCol}` AS mbody {$selectMsgDate} FROM `mensajes` WHERE `{$msgConvCol}` = :cid ORDER BY {$orderMsg} LIMIT 1");
          $stM->execute([':cid' => $conversationId]);
          $m = $stM->fetch(PDO::FETCH_ASSOC);
          if ($m) {
            $lastMessage = (string)($m['mbody'] ?? '');
            $lastDate = (string)($m['mcreated'] ?? '');
          }
        }

        $activeChats[] = [
          'conversation_id'   => $conversationId,
          'job_id'            => $jobId,
          'job_title'         => $jobTitle,
          'counterpart_id'    => $counterpartId,
          'counterpart_name'  => $counterpartName,
          'counterpart_type'  => $counterpartType,
          'counterpart_email' => $counterpartEmail,
          'counterpart_phone' => $counterpartPhone,
          'last_message'      => $lastMessage,
          'last_date'         => $lastDate,
        ];
      }
    }
  }

  /* ============================================================
     2) CONTACTOS MANUALES
  ============================================================ */
  if (table_exists($pdo, 'doctor_contacts')) $contactsTable = 'doctor_contacts';
  elseif (table_exists($pdo, 'user_contacts')) $contactsTable = 'user_contacts';

  if (!$contactsTable) {
    $tableMissing = true;
  } else {
    $cols = table_columns($pdo, $contactsTable);

    foreach (['doctor_user_id','user_id','doctor_id'] as $cand) {
      if (in_array($cand, $cols, true)) { $ownerCol = $cand; break; }
    }

    foreach (['id','name'] as $req) {
      if (!in_array($req, $cols, true)) {
        throw new RuntimeException(con_t('required_column_missing', 'La tabla de contactos no tiene una columna requerida.') . " ({$req})");
      }
    }

    if ($ownerCol === null) {
      throw new RuntimeException(con_t('owner_col_missing', 'La tabla de contactos no tiene columna owner.'));
    }

    $hasEmail     = in_array('email', $cols, true);
    $hasPhone     = in_array('phone', $cols, true);
    $hasRole      = in_array('role', $cols, true);
    $hasTags      = in_array('tags', $cols, true);
    $hasNotes     = in_array('notes', $cols, true);
    $hasCreatedAt = in_array('created_at', $cols, true);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      $postedCsrf = (string)($_POST['csrf_token'] ?? '');
      if (!hash_equals($csrf, $postedCsrf)) {
        $flashErr = con_t('csrf_invalid', 'Sesión inválida (CSRF). Recarga e inténtalo de nuevo.');
      } else {
        $action    = (string)($_POST['action'] ?? '');
        $contactId = (int)($_POST['contact_id'] ?? 0);

        $name  = trim((string)($_POST['name'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $phone = trim((string)($_POST['phone'] ?? ''));
        $role  = trim((string)($_POST['role'] ?? ''));
        $tags  = trim((string)($_POST['tags'] ?? ''));
        $notes = trim((string)($_POST['notes'] ?? ''));

        $errs = [];

        if (!in_array($action, ['create_contact','update_contact','delete_contact'], true)) {
          $errs[] = con_t('invalid_action', 'Acción no válida.');
        }

        if ($action !== 'delete_contact') {
          if ($name === '') $errs[] = con_t('name_required', 'Nombre obligatorio.');
          if ($hasEmail && $email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errs[] = con_t('invalid_email', 'Email inválido.');
          }
        } else {
          if ($contactId <= 0) $errs[] = con_t('invalid_contact', 'Contacto inválido.');
        }

        if (!$errs && in_array($action, ['update_contact','delete_contact'], true)) {
          $stOwn = $pdo->prepare("SELECT id FROM `{$contactsTable}` WHERE id=? AND `{$ownerCol}`=? LIMIT 1");
          $stOwn->execute([$contactId, $doctorId]);
          if (!$stOwn->fetchColumn()) $errs[] = con_t('not_authorized', 'No autorizado.');
        }

        if ($errs) {
          foreach ($errs as $er) $uiErrors[] = $er;
        } else {
          if ($action === 'create_contact') {
            $fields = [$ownerCol => $doctorId, 'name' => $name];
            if ($hasEmail) $fields['email'] = ($email !== '' ? $email : null);
            if ($hasPhone) $fields['phone'] = ($phone !== '' ? $phone : null);
            if ($hasRole)  $fields['role']  = ($role !== '' ? $role : null);
            if ($hasTags)  $fields['tags']  = ($tags !== '' ? $tags : null);
            if ($hasNotes) $fields['notes'] = ($notes !== '' ? $notes : null);

            $colsSql = implode(',', array_map(fn($c) => "`{$c}`", array_keys($fields)));
            $valsSql = implode(',', array_fill(0, count($fields), '?'));
            $sql = "INSERT INTO `{$contactsTable}` ({$colsSql}) VALUES ({$valsSql})";
            $st  = $pdo->prepare($sql);
            $st->execute(array_values($fields));

            header("Location: " . $returnBase . "&ok=1");
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
            $vals[] = $doctorId;

            $sql = "UPDATE `{$contactsTable}` SET " . implode(',', $sets) . " WHERE id=? AND `{$ownerCol}`=? LIMIT 1";
            $st  = $pdo->prepare($sql);
            $st->execute($vals);

            header("Location: " . $returnBase . "&ok=1");
            exit;
          }

          if ($action === 'delete_contact') {
            $st = $pdo->prepare("DELETE FROM `{$contactsTable}` WHERE id=? AND `{$ownerCol}`=? LIMIT 1");
            $st->execute([$contactId, $doctorId]);

            header("Location: " . $returnBase . "&ok=del");
            exit;
          }
        }
      }
    }

    /* ============================================================
       2.1) INYECCIÓN: AUTOGUARDAR CONTACTOS DESDE CHATS ACTIVOS
       No rompe nada: si la tabla tiene columnas extra las usa,
       y si no las tiene, sigue funcionando igual.
    ============================================================ */
    if ($activeChats) {
      $hasSourceType     = in_array('source_type', $cols, true);
      $hasSourceUserId   = in_array('source_user_id', $cols, true);
      $hasConversationId = in_array('conversation_id', $cols, true);

      foreach ($activeChats as $chat) {
        $autoConversationId = (int)($chat['conversation_id'] ?? 0);
        $autoJobId          = (int)($chat['job_id'] ?? 0);
        $autoCounterpartId  = (int)($chat['counterpart_id'] ?? 0);
        $autoName           = trim((string)($chat['counterpart_name'] ?? ''));
        $autoType           = trim((string)($chat['counterpart_type'] ?? ''));
        $autoEmail          = trim((string)($chat['counterpart_email'] ?? ''));
        $autoPhone          = trim((string)($chat['counterpart_phone'] ?? ''));

        if ($autoName === '') {
          continue;
        }

        $autoRole = ($autoType === 'hospital')
          ? con_t('contact_role_hospital', 'Hospital')
          : con_t('contact_role_clinic', 'Clínica');

        $autoTag = con_t('auto_contact_tag', 'chat activo');
        $autoNote = strtr(
          con_t('auto_contact_note', 'Contacto creado automáticamente desde el chat · Conversación #{conversation_id} · Job #{job_id}'),
          [
            '#{conversation_id}' => (string)$autoConversationId,
            '#{job_id}' => (string)$autoJobId,
          ]
        );

        $existing = null;

        /* 1) Prioridad: columnas técnicas si existen */
        if ($hasSourceType && $hasSourceUserId && $autoType !== '' && $autoCounterpartId > 0) {
          $stFind = $pdo->prepare("
            SELECT *
            FROM `{$contactsTable}`
            WHERE `{$ownerCol}` = ? AND `source_type` = ? AND `source_user_id` = ?
            LIMIT 1
          ");
          $stFind->execute([$doctorId, $autoType, $autoCounterpartId]);
          $existing = $stFind->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        /* 2) Fallback por conversation_id si existe */
        if (!$existing && $hasConversationId && $autoConversationId > 0) {
          $stFind = $pdo->prepare("
            SELECT *
            FROM `{$contactsTable}`
            WHERE `{$ownerCol}` = ? AND `conversation_id` = ?
            LIMIT 1
          ");
          $stFind->execute([$doctorId, $autoConversationId]);
          $existing = $stFind->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        /* 3) Fallback suave si la tabla no tiene columnas técnicas */
        if (!$existing) {
          $stFind = $pdo->prepare("
            SELECT *
            FROM `{$contactsTable}`
            WHERE `{$ownerCol}` = ? AND `name` = ?
            LIMIT 1
          ");
          $stFind->execute([$doctorId, $autoName]);
          $existing = $stFind->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        if ($existing) {
          $updateSets = [];
          $updateVals = [];

          if ($hasEmail && $autoEmail !== '' && trim((string)($existing['email'] ?? '')) === '') {
            $updateSets[] = "`email` = ?";
            $updateVals[] = $autoEmail;
          }

          if ($hasPhone && $autoPhone !== '' && trim((string)($existing['phone'] ?? '')) === '') {
            $updateSets[] = "`phone` = ?";
            $updateVals[] = $autoPhone;
          }

          if ($hasRole && $autoRole !== '' && trim((string)($existing['role'] ?? '')) === '') {
            $updateSets[] = "`role` = ?";
            $updateVals[] = $autoRole;
          }

          if ($hasTags) {
            $mergedTags = merge_contact_tags((string)($existing['tags'] ?? ''), $autoTag);
            if ($mergedTags !== (string)($existing['tags'] ?? '')) {
              $updateSets[] = "`tags` = ?";
              $updateVals[] = $mergedTags;
            }
          }

          if ($hasNotes) {
            $mergedNotes = merge_contact_notes((string)($existing['notes'] ?? ''), $autoNote);
            if ($mergedNotes !== (string)($existing['notes'] ?? '')) {
              $updateSets[] = "`notes` = ?";
              $updateVals[] = $mergedNotes;
            }
          }

          if ($hasSourceType && $autoType !== '' && trim((string)($existing['source_type'] ?? '')) === '') {
            $updateSets[] = "`source_type` = ?";
            $updateVals[] = $autoType;
          }

          if ($hasSourceUserId && $autoCounterpartId > 0 && (int)($existing['source_user_id'] ?? 0) <= 0) {
            $updateSets[] = "`source_user_id` = ?";
            $updateVals[] = $autoCounterpartId;
          }

          if ($hasConversationId && $autoConversationId > 0 && (int)($existing['conversation_id'] ?? 0) <= 0) {
            $updateSets[] = "`conversation_id` = ?";
            $updateVals[] = $autoConversationId;
          }

          if ($updateSets) {
            $updateVals[] = (int)$existing['id'];
            $updateVals[] = $doctorId;

            $sqlAutoUp = "UPDATE `{$contactsTable}` SET " . implode(', ', $updateSets) . " WHERE `id` = ? AND `{$ownerCol}` = ? LIMIT 1";
            $stAutoUp = $pdo->prepare($sqlAutoUp);
            $stAutoUp->execute($updateVals);
          }
        } else {
          $fields = [
            $ownerCol => $doctorId,
            'name'    => $autoName,
          ];

          if ($hasEmail) $fields['email'] = ($autoEmail !== '' ? $autoEmail : null);
          if ($hasPhone) $fields['phone'] = ($autoPhone !== '' ? $autoPhone : null);
          if ($hasRole)  $fields['role']  = ($autoRole !== '' ? $autoRole : null);
          if ($hasTags)  $fields['tags']  = ($autoTag !== '' ? $autoTag : null);
          if ($hasNotes) $fields['notes'] = ($autoNote !== '' ? $autoNote : null);

          if ($hasSourceType)     $fields['source_type']     = ($autoType !== '' ? $autoType : null);
          if ($hasSourceUserId)   $fields['source_user_id']  = ($autoCounterpartId > 0 ? $autoCounterpartId : null);
          if ($hasConversationId) $fields['conversation_id'] = ($autoConversationId > 0 ? $autoConversationId : null);

          $insertCols = implode(',', array_map(fn($c) => "`{$c}`", array_keys($fields)));
          $insertVals = implode(',', array_fill(0, count($fields), '?'));

          $stAutoIns = $pdo->prepare("INSERT INTO `{$contactsTable}` ({$insertCols}) VALUES ({$insertVals})");
          $stAutoIns->execute(array_values($fields));
        }
      }
    }

    if ($editId > 0) {
      $stE = $pdo->prepare("SELECT * FROM `{$contactsTable}` WHERE id=? AND `{$ownerCol}`=? LIMIT 1");
      $stE->execute([$editId, $doctorId]);
      $editing = $stE->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    $where  = "`{$ownerCol}`=?";
    $params = [$doctorId];

    if ($q !== '') {
      $like  = "%{$q}%";
      $parts = ["name LIKE ?"];
      $params[] = $like;

      if ($hasEmail) { $parts[] = "email LIKE ?"; $params[] = $like; }
      if ($hasPhone) { $parts[] = "phone LIKE ?"; $params[] = $like; }
      if ($hasRole)  { $parts[] = "role LIKE ?";  $params[] = $like; }
      if ($hasTags)  { $parts[] = "tags LIKE ?";  $params[] = $like; }

      $where .= " AND (" . implode(" OR ", $parts) . ")";
    }

    $order = $hasCreatedAt ? "ORDER BY created_at DESC" : "ORDER BY id DESC";
    $sql   = "SELECT * FROM `{$contactsTable}` WHERE {$where} {$order}";
    $stL   = $pdo->prepare($sql);
    $stL->execute($params);
    $contacts = $stL->fetchAll(PDO::FETCH_ASSOC) ?: [];
  }

} catch (Throwable $e) {
  error_log("contactos.php ERROR: " . $e->getMessage());
  $uiErrors[] = con_t('load_error', 'No se pudo cargar la sección de contactos.');
}
?>

<?php if ($flashOk): ?>
  <div class="flashOk"><?= h($flashOk) ?></div>
<?php endif; ?>

<?php if ($flashErr): ?>
  <div class="flashErr"><?= h($flashErr) ?></div>
<?php endif; ?>

<?php if ($uiErrors): ?>
  <div class="errBox" style="margin-bottom:12px;">
    <strong><?= h(con_t('review_following', 'Revisa lo siguiente:')) ?></strong>
    <ul style="margin:8px 0 0; padding-left:18px;">
      <?php foreach ($uiErrors as $er): ?>
        <li><?= h((string)$er) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<div class="sectionTitle">
  <div>
    <h2><?= h(con_t('contacts_title', 'Contactos')) ?></h2>
    <div class="muted" style="margin-top:6px;">
      <?= h(con_t('contacts_subtitle', 'Guarda agencias, RRHH, clínicas/hospitales o colegas para seguimiento.')) ?>
    </div>
  </div>

  <form method="get" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
    <input type="hidden" name="tab" value="contactos">
    <input type="hidden" name="lang" value="<?= h($conLang) ?>">
    <input
      class="input"
      name="q"
      value="<?= h($q) ?>"
      placeholder="<?= h(con_t('search_placeholder', 'Buscar por nombre, email, teléfono, etiquetas...')) ?>"
      style="min-width:260px;"
    >
    <button class="btn btnGhost" type="submit"><?= h(con_t('search', 'Buscar')) ?></button>
    <?php if ($q !== ''): ?>
      <a class="btn btnGhost" href="?tab=contactos<?= $LANG_QS ?>"><?= h(con_t('clear', 'Limpiar')) ?></a>
    <?php endif; ?>
  </form>
</div>

<!-- CHATS ACTIVOS REALES -->
<div class="panel" style="margin-bottom:12px;">
  <h3 style="margin:0 0 10px;"><?= h(con_t('active_chats', 'Chats activos')) ?></h3>

  <?php if (!$activeChats): ?>
    <div class="note"><?= h(con_t('no_active_chats', 'No hay conversaciones activas todavía.')) ?></div>
  <?php else: ?>
    <?php foreach ($activeChats as $chat): ?>
      <div class="note" style="margin-bottom:10px;">
        <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:12px; flex-wrap:wrap;">
          <div>
            <strong><?= h($chat['counterpart_name']) ?></strong>
            <div class="muted" style="margin-top:6px;">
              <?= h(con_t('job_id', 'Job ID')) ?>: <?= (int)$chat['job_id'] ?>
              <?= $chat['job_title'] !== '' ? ' • ' . h($chat['job_title']) : '' ?>
            </div>
            <?php if ($chat['last_message'] !== ''): ?>
              <div class="muted" style="margin-top:8px;">
                <?= h(con_t('last_message', 'Último mensaje')) ?>: <?= h($chat['last_message']) ?>
              </div>
            <?php endif; ?>
          </div>

          <div>
            <a class="btn btnGold" href="/usuarios/medico/chat.php?conversation_id=<?= (int)$chat['conversation_id'] ?>">
              <?= h(con_t('open_chat', 'Abrir chat')) ?>
            </a>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<?php if ($tableMissing): ?>
  <div class="note">
    <strong><?= h(con_t('contacts_not_enabled', 'Contactos aún no está habilitado en base de datos.')) ?></strong><br>
    <?= h(con_t('missing_contacts_table', 'Falta la tabla doctor_contacts.')) ?>
  </div>
  <?php return; ?>
<?php endif; ?>

<div class="grid2">
  <div class="panel">
    <h3 style="margin:0 0 10px;"><?= h(con_t('contacts_list', 'Lista de contactos')) ?></h3>

    <?php if (!$contacts): ?>
      <div class="note"><?= h(con_t('no_contacts', 'No hay contactos guardados.')) ?></div>
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
        <div class="note" style="margin-bottom:10px;">
          <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:12px; flex-wrap:wrap;">
            <div>
              <strong><?= h($name) ?></strong>
              <?php if ($role !== ''): ?>
                <div class="muted" style="margin-top:6px;"><?= h($role) ?></div>
              <?php endif; ?>

              <div class="muted" style="margin-top:10px; line-height:1.45;">
                <?php if ($email !== ''): ?><div><strong class="muted"><?= h(con_t('email', 'Email')) ?>:</strong> <?= h($email) ?></div><?php endif; ?>
                <?php if ($phone !== ''): ?><div><strong class="muted"><?= h(con_t('phone', 'Tel')) ?>:</strong> <?= h($phone) ?></div><?php endif; ?>
                <?php if ($tags !== ''): ?><div><strong class="muted"><?= h(con_t('tags', 'Tags')) ?>:</strong> <?= h($tags) ?></div><?php endif; ?>
                <?php if ($notes !== ''): ?><div style="margin-top:6px;"><?= h($notes) ?></div><?php endif; ?>
              </div>
            </div>

            <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
              <a class="btn btnGhost" href="?tab=contactos&edit=<?= (int)$cid ?><?= $LANG_QS ?>"><?= h(con_t('edit', 'Editar')) ?></a>
              <form method="post" onsubmit="return confirm('<?= h(con_t('delete_confirm', '¿Eliminar este contacto?')) ?>');">
                <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                <input type="hidden" name="action" value="delete_contact">
                <input type="hidden" name="contact_id" value="<?= (int)$cid ?>">
                <button class="btnDanger" type="submit"><?= h(con_t('delete', 'Eliminar')) ?></button>
              </form>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>

    <div class="note">
      <?= h(con_t('tracking_hint', 'Útil para seguimiento: teléfono, email, notas, etiquetas.')) ?>
    </div>
  </div>

  <div class="panel">
    <h3 style="margin:0 0 10px;"><?= $editing ? h(con_t('edit_contact', 'Editar contacto')) : h(con_t('new_contact', 'Nuevo contacto')) ?></h3>

    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
      <input type="hidden" name="action" value="<?= $editing ? 'update_contact' : 'create_contact' ?>">
      <input type="hidden" name="contact_id" value="<?= (int)($editing['id'] ?? 0) ?>">

      <div class="field">
        <label><?= h(con_t('name', 'Nombre')) ?></label>
        <input class="input" name="name" value="<?= h((string)($editing['name'] ?? '')) ?>" required>
      </div>

      <div class="row">
        <div class="field" style="flex:1;">
          <label><?= h(con_t('email_optional', 'Email (opcional)')) ?></label>
          <input class="input" name="email" value="<?= h((string)($editing['email'] ?? '')) ?>" placeholder="ej. rrhh@clinica.com">
        </div>
        <div class="field" style="flex:1;">
          <label><?= h(con_t('phone_optional', 'Teléfono (opcional)')) ?></label>
          <input class="input" name="phone" value="<?= h((string)($editing['phone'] ?? '')) ?>" placeholder="+34 ...">
        </div>
      </div>

      <div class="row">
        <div class="field" style="flex:1;">
          <label><?= h(con_t('role_company_optional', 'Rol / Empresa (opcional)')) ?></label>
          <input class="input" name="role" value="<?= h((string)($editing['role'] ?? '')) ?>" placeholder="ej. RRHH, Agencia, Director médico">
        </div>
        <div class="field" style="flex:1;">
          <label><?= h(con_t('tags_optional', 'Etiquetas (opcional)')) ?></label>
          <input class="input" name="tags" value="<?= h((string)($editing['tags'] ?? '')) ?>" placeholder="ej. guardias, pediatría, Valencia">
        </div>
      </div>

      <div class="field">
        <label><?= h(con_t('notes_optional', 'Notas (opcional)')) ?></label>
        <input class="input" name="notes" value="<?= h((string)($editing['notes'] ?? '')) ?>" placeholder="Observaciones internas...">
      </div>

      <div class="btnRow">
        <?php if ($editing): ?>
          <a class="btn btnGhost" href="?tab=contactos<?= $LANG_QS ?>"><?= h(con_t('cancel', 'Cancelar')) ?></a>
        <?php else: ?>
          <button class="btn btnGhost" type="reset"><?= h(con_t('reset', 'Limpiar')) ?></button>
        <?php endif; ?>
        <button class="btn btnGold" type="submit"><?= h(con_t('save', 'Guardar')) ?></button>
      </div>
    </form>
  </div>
</div>
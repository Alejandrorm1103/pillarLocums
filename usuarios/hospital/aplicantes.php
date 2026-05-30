<?php
// /usuarios/hospital/aplicantes.php
// Se incluye dentro de index.php (NO pongas <html> aquí)

if (!function_exists('h')) {
  function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
  }
}

// =====================
// I18N LOCAL PARA APLICANTES
// =====================
$plLang = (string)($plLang ?? $_GET['lang'] ?? $_SESSION['lang'] ?? 'es');
$plLang = ($plLang === 'en') ? 'en' : 'es';
$_SESSION['lang'] = $plLang;

$PILLAR_HOSP_APPLICANTS_DICT = [
  'es' => [
    'applicants'                 => 'Aplicantes',
    'review_following'           => 'Revisa lo siguiente:',
    'db_not_available'           => 'BD no disponible (PDO).',
    'no_title_column'            => 'No hay columna de título en jobs.',
    'no_owner_column'            => 'No hay columna owner en jobs.',
    'no_applicants_yet'          => 'Aún no hay aplicantes para tus ofertas. Cuando un médico aplique, aparecerá aquí.',
    'offer'                      => 'Oferta',
    'doctor'                     => 'Médico',
    'contact'                    => 'Contacto',
    'status'                     => 'Estado',
    'message'                    => 'Mensaje',
    'date'                       => 'Fecha',
    'action'                     => 'Acción',
    'pending'                    => 'Pendiente',
    'accepted'                   => 'Aceptado',
    'accepted_doctor'            => 'Aceptar médico',
    'chat'                       => 'Chat',
    'no_data'                    => '—',
    'load_error'                 => 'No se pudieron cargar los aplicantes.',
    'error'                      => 'Error:',
    'status_pending'             => 'Pendiente',
    'status_accepted'            => 'Aceptado',
    'status_rejected'            => 'Rechazado',
    'status_closed'              => 'Cerrado',
  ],
  'en' => [
    'applicants'                 => 'Applicants',
    'review_following'           => 'Please review the following:',
    'db_not_available'           => 'Database not available (PDO).',
    'no_title_column'            => 'No title column found in jobs.',
    'no_owner_column'            => 'No owner column found in jobs.',
    'no_applicants_yet'          => 'There are no applicants for your offers yet. When a doctor applies, they will appear here.',
    'offer'                      => 'Offer',
    'doctor'                     => 'Doctor',
    'contact'                    => 'Contact',
    'status'                     => 'Status',
    'message'                    => 'Message',
    'date'                       => 'Date',
    'action'                     => 'Action',
    'pending'                    => 'Pending',
    'accepted'                   => 'Accepted',
    'accepted_doctor'            => 'Accept doctor',
    'chat'                       => 'Chat',
    'no_data'                    => '—',
    'load_error'                 => 'Could not load applicants.',
    'error'                      => 'Error:',
    'status_pending'             => 'Pending',
    'status_accepted'            => 'Accepted',
    'status_rejected'            => 'Rejected',
    'status_closed'              => 'Closed',
  ],
];

// Si existe el diccionario global del index, lo extendemos
if (isset($plDict) && is_array($plDict)) {
  $plDict['es'] = array_merge($plDict['es'] ?? [], $PILLAR_HOSP_APPLICANTS_DICT['es']);
  $plDict['en'] = array_merge($plDict['en'] ?? [], $PILLAR_HOSP_APPLICANTS_DICT['en']);
}

// Fallback local si index no expone pl_t()
if (!function_exists('pl_t')) {
  function pl_t(string $key, ?string $fallback = null): string {
    global $plLang, $PILLAR_HOSP_APPLICANTS_DICT;
    return $PILLAR_HOSP_APPLICANTS_DICT[$plLang][$key] ?? ($fallback ?? $key);
  }
}

$ownerId = isset($ownerId) ? (int)$ownerId : (int)($_SESSION['user']['id'] ?? 0);
$debug   = isset($_GET['debug']) && $_GET['debug'] === '1';
$plLangQS = 'lang=' . urlencode($plLang);

$statusLabel = function(string $status): string {
  $s = strtolower(trim($status));

  return match ($s) {
    'pending', 'pendiente' => pl_t('status_pending'),
    'accepted', 'aceptado', 'aceptada' => pl_t('status_accepted'),
    'rejected', 'rechazado', 'rechazada' => pl_t('status_rejected'),
    'closed', 'cerrado', 'cerrada' => pl_t('status_closed'),
    default => ($status !== '' ? $status : pl_t('pending')),
  };
};

$statusStyle = function(string $status): array {
  $s = strtolower(trim($status));

  return match ($s) {
    'pending', 'pendiente' => [
      'bg' => 'rgba(151,195,188,.14)',
      'bd' => 'rgba(61,109,118,.18)',
      'tx' => '#3D6D76',
    ],
    'accepted', 'aceptado', 'aceptada' => [
      'bg' => 'rgba(151,195,188,.24)',
      'bd' => 'rgba(151,195,188,.40)',
      'tx' => '#3D6D76',
    ],
    'rejected', 'rechazado', 'rechazada' => [
      'bg' => 'rgba(206,147,121,.14)',
      'bd' => 'rgba(206,147,121,.30)',
      'tx' => '#9b5f46',
    ],
    default => [
      'bg' => 'rgba(151,195,188,.14)',
      'bd' => 'rgba(61,109,118,.18)',
      'tx' => '#3D6D76',
    ],
  };
};

echo '<div class="sectionTitle"><h2>' . h(pl_t('applicants')) . '</h2></div>';

if (!isset($pdo) || !($pdo instanceof PDO)) {
  echo '<div class="errBox"><strong>' . h(pl_t('review_following')) . '</strong><ul style="margin:8px 0 0; padding-left:18px;"><li>' . h(pl_t('db_not_available')) . '</li></ul></div>';
  return;
}

try {
  // 1) Detectar columnas reales en jobs (título + owner)
  $cols = [];
  $stC = $pdo->query("SHOW COLUMNS FROM jobs");
  foreach (($stC->fetchAll(PDO::FETCH_ASSOC) ?: []) as $r) {
    $cols[] = (string)($r['Field'] ?? '');
  }

  $titleCol = null;
  foreach (['title', 'name', 'position', 'job_title'] as $cand) {
    if (in_array($cand, $cols, true)) {
      $titleCol = $cand;
      break;
    }
  }
  if ($titleCol === null) {
    throw new RuntimeException(pl_t('no_title_column'));
  }

  $ownerCol = null;
  foreach (['hospital_user_id', 'clinic_user_id', 'hospital_id', 'clinic_id'] as $cand) {
    if (in_array($cand, $cols, true)) {
      $ownerCol = $cand;
      break;
    }
  }
  if ($ownerCol === null) {
    throw new RuntimeException(pl_t('no_owner_column'));
  }

  // 2) Consulta con nombre del médico y contacto
  $sql = "
    SELECT
      ja.id AS application_id,
      ja.job_id,
      ja.doctor_user_id,
      ja.status,
      ja.message,
      ja.created_at,
      j.`{$titleCol}` AS job_title,
      u.name AS doctor_name,
      u.email AS doctor_email,
      u.phone AS doctor_phone
    FROM job_applications ja
    INNER JOIN jobs j ON j.id = ja.job_id
    LEFT JOIN users u ON u.id = ja.doctor_user_id
    WHERE j.`{$ownerCol}` = ?
    ORDER BY ja.created_at DESC, ja.id DESC
  ";

  $st = $pdo->prepare($sql);
  $st->execute([$ownerId]);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

  if (!$rows) {
    echo '<div class="note">' . h(pl_t('no_applicants_yet')) . '</div>';
    return;
  }

  // 3) Tabla de aplicantes
  echo '<div class="tableWrap">';
  echo '<table class="table">';
  echo '<thead><tr>
          <th>' . h(pl_t('offer')) . '</th>
          <th>' . h(pl_t('doctor')) . '</th>
          <th>' . h(pl_t('contact')) . '</th>
          <th>' . h(pl_t('status')) . '</th>
          <th>' . h(pl_t('message')) . '</th>
          <th>' . h(pl_t('date')) . '</th>
          <th>' . h(pl_t('action')) . '</th>
        </tr></thead>';
  echo '<tbody>';

  foreach ($rows as $r) {
    $jobTitle       = (string)($r['job_title'] ?? '');
    $docId          = (int)($r['doctor_user_id'] ?? 0);
    $applicationId  = (int)($r['application_id'] ?? 0);
    $doctorName     = (string)($r['doctor_name'] ?? pl_t('no_data'));
    $doctorEmail    = (string)($r['doctor_email'] ?? pl_t('no_data'));
    $doctorPhone    = (string)($r['doctor_phone'] ?? pl_t('no_data'));
    $status         = (string)($r['status'] ?? 'pendiente');
    $message        = (string)($r['message'] ?? '');
    $created        = (string)($r['created_at'] ?? '');
    $jobId          = (int)($r['job_id'] ?? 0);

    $pill = $statusStyle($status);
    $statusText = $statusLabel($status);

    echo '<tr>';
    echo '<td><strong>' . h($jobTitle) . '</strong></td>';
    echo '<td>' . h($doctorName) . '</td>';
    echo '<td>' . h($doctorEmail) . '<br>' . h($doctorPhone) . '</td>';
    echo '<td><span class="pill" style="background:' . h($pill['bg']) . '; border-color:' . h($pill['bd']) . '; color:' . h($pill['tx']) . ';">' . h($statusText) . '</span></td>';
    echo '<td>' . ($message !== '' ? h($message) : '<span class="muted">' . h(pl_t('no_data')) . '</span>') . '</td>';
    echo '<td>' . ($created !== '' ? h($created) : '<span class="muted">' . h(pl_t('no_data')) . '</span>') . '</td>';

    if (strtolower($status) === 'pendiente' || strtolower($status) === 'pending') {
      echo '<td>
              <form method="POST" action="aceptar_turno.php" style="margin:0; display:inline;">
                <input type="hidden" name="application_id" value="' . (int)$applicationId . '">
                <input type="hidden" name="doctor_id" value="' . (int)$docId . '">
                <input type="hidden" name="lang" value="' . h($plLang) . '">
                <button class="iconBtn" type="submit">' . h(pl_t('accepted_doctor')) . '</button>
              </form>
            </td>';
    } else {
      echo '<td><a class="chatBtn" href="chat.php?job_id=' . (int)$jobId . '&doctor_id=' . (int)$docId . '&' . $plLangQS . '">' . h(pl_t('chat')) . '</a></td>';
    }

    echo '</tr>';
  }

  echo '</tbody></table>';
  echo '</div>';

} catch (Throwable $e) {
  error_log("APLICANTES ERROR: " . $e->getMessage());
  echo '<div class="errBox"><strong>' . h(pl_t('error')) . '</strong> ' . h(pl_t('load_error')) . '</div>';
  if ($debug) {
    echo '<div style="font-family: monospace; font-size:.85rem; white-space:pre-wrap;">' . h($e->getMessage()) . '</div>';
  }
}
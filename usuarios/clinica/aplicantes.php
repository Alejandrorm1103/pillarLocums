<?php
// /usuarios/hospital/aplicantes.php o clínica
// Se incluye dentro de index.php (NO pongas <html> aquí)

if (!function_exists('h')) {
  function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
}

$ownerId = isset($ownerId) ? (int)$ownerId : (int)($_SESSION['user']['id'] ?? 0);
$debug   = isset($_GET['debug']) && $_GET['debug'] === '1';

echo '<div class="sectionTitle"><h2>Aplicantes</h2></div>';

if (!isset($pdo) || !($pdo instanceof PDO)) {
  echo '<div class="errBox"><strong>Revisa lo siguiente:</strong><ul style="margin:8px 0 0; padding-left:18px;"><li>BD no disponible (PDO).</li></ul></div>';
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
  foreach (['title','name','position','job_title'] as $cand) {
    if (in_array($cand, $cols, true)) { $titleCol = $cand; break; }
  }
  if ($titleCol === null) throw new RuntimeException("No hay columna de título en jobs.");

  $ownerCol = null;
  foreach (['hospital_user_id','clinic_user_id','hospital_id','clinic_id'] as $cand) {
    if (in_array($cand, $cols, true)) { $ownerCol = $cand; break; }
  }
  if ($ownerCol === null) throw new RuntimeException("No hay columna owner en jobs.");

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
    echo '<div class="note">Aún no hay aplicantes para tus ofertas. Cuando un médico aplique, aparecerá aquí.</div>';
    return;
  }

  // 3) Tabla de aplicantes
  echo '<table class="table">';
  echo '<thead><tr>
          <th>Oferta</th>
          <th>Médico</th>
          <th>Contacto</th>
          <th>Estado</th>
          <th>Mensaje</th>
          <th>Fecha</th>
          <th>Acción</th>
        </tr></thead>';
  echo '<tbody>';

  foreach ($rows as $r) {
    $jobTitle    = (string)($r['job_title'] ?? '');
    $docId       = (int)($r['doctor_user_id'] ?? 0);
    $applicationId = (int)($r['application_id'] ?? 0);
    $doctorName  = (string)($r['doctor_name'] ?? '—');
    $doctorEmail = (string)($r['doctor_email'] ?? '—');
    $doctorPhone = (string)($r['doctor_phone'] ?? '—');
    $status      = (string)($r['status'] ?? 'pendiente');
    $message     = (string)($r['message'] ?? '');
    $created     = (string)($r['created_at'] ?? '');

    echo '<tr>';
    echo '<td><strong>' . h($jobTitle) . '</strong></td>';
    echo '<td>' . h($doctorName) . '</td>';
    echo '<td>' . h($doctorEmail) . '<br>' . h($doctorPhone) . '</td>';
    echo '<td><span class="pill">' . h($status) . '</span></td>';
    echo '<td>' . ($message !== '' ? h($message) : '<span class="muted">—</span>') . '</td>';
    echo '<td>' . ($created !== '' ? h($created) : '<span class="muted">—</span>') . '</td>';

    // Botón de acción
    if ($status === 'pendiente') {
      echo '<td>
              <form method="POST" action="aceptar_turno.php" style="margin:0;">
                <input type="hidden" name="application_id" value="' . $applicationId . '">
                <input type="hidden" name="doctor_id" value="' . $docId . '">
                <button type="submit">Aceptar Médico</button>
              </form>
            </td>';
    } else {
      echo '<td><a href="chat.php?job_id=' . (int)$r['job_id'] . '&doctor_id=' . $docId . '">Chat</a></td>';
    }

    echo '</tr>';
  }

  echo '</tbody></table>';

} catch (Throwable $e) {
  error_log("APLICANTES ERROR: " . $e->getMessage());
  echo '<div class="errBox"><strong>Error:</strong> No se pudieron cargar los aplicantes.</div>';
  if ($debug) {
    echo '<div style="font-family: monospace; font-size:.85rem; white-space:pre-wrap;">' . h($e->getMessage()) . '</div>';
  }
}
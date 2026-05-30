<?php
declare(strict_types=1);

function load_calendar_data(PDO $pdo, int $clinicId, array $q): array {
  $y = (int)($q['y'] ?? date('Y'));
  $m = (int)($q['m'] ?? date('n'));
  if ($m < 1) { $m = 12; $y--; }
  if ($m > 12) { $m = 1; $y++; }

  $daysInMonth = (int)cal_days_in_month(CAL_GREGORIAN, $m, $y);

  $selectedDay = (string)($q['day'] ?? date('Y-m-d'));
  // Seguridad: si seleccionan un día de otro mes, igual lo aceptamos, pero puedes normalizar.

  // 1) Ofertas para select “Vincular a oferta”
  $st = $pdo->prepare("SELECT id, title FROM jobs WHERE clinic_user_id=? ORDER BY id DESC");
  $st->execute([$clinicId]);
  $jobsForSelect = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

  // 2) Conteo por día (event dots)
  $from = sprintf('%04d-%02d-01', $y, $m);
  $to   = sprintf('%04d-%02d-%02d', $y, $m, $daysInMonth);

  $st = $pdo->prepare("SELECT event_date, COUNT(*) c
                       FROM clinic_events
                       WHERE clinic_user_id=? AND event_date BETWEEN ? AND ?
                       GROUP BY event_date");
  $st->execute([$clinicId, $from, $to]);
  $eventCounts = [];
  foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $eventCounts[(string)$row['event_date']] = (int)$row['c'];
  }

  // 3) Eventos del día seleccionado
  $st = $pdo->prepare("SELECT e.*, j.title AS job_title
                       FROM clinic_events e
                       LEFT JOIN jobs j ON j.id = e.job_id
                       WHERE e.clinic_user_id=? AND e.event_date=?
                       ORDER BY COALESCE(e.start_time,'23:59:59') ASC, e.id ASC");
  $st->execute([$clinicId, $selectedDay]);
  $dayEvents = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

  return compact('y','m','selectedDay','eventCounts','dayEvents','jobsForSelect');
}

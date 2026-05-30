<?php
declare(strict_types=1);

function handle_calendar_actions(PDO $pdo, int $clinicId): void {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;

  $action = (string)($_POST['action'] ?? '');
  if ($action === '') return;

  // Importantísimo: redirigir al final para evitar re-post
  $redirect = "/usuarios/clinica/index.php?tab=calendario";
  $day = (string)($_POST['event_date'] ?? '');
  if ($day !== '') $redirect .= "&day=" . urlencode($day);

  try {
    if ($action === 'event_create') {
      $event_date = (string)($_POST['event_date'] ?? '');
      $title = trim((string)($_POST['title'] ?? ''));
      $job_id = (int)($_POST['job_id'] ?? 0);
      $start_time = trim((string)($_POST['start_time'] ?? ''));
      $end_time = trim((string)($_POST['end_time'] ?? ''));
      $status = (string)($_POST['status'] ?? 'planned');
      $notes = trim((string)($_POST['notes'] ?? ''));

      if ($event_date === '' || $title === '') {
        flash_set('error', 'Fecha y título son obligatorios.');
        header("Location: $redirect");
        exit;
      }

      $st = $pdo->prepare("INSERT INTO clinic_events
        (clinic_user_id, job_id, event_date, start_time, end_time, title, status, notes)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
      $st->execute([
        $clinicId,
        $job_id > 0 ? $job_id : null,
        $event_date,
        $start_time !== '' ? $start_time : null,
        $end_time !== '' ? $end_time : null,
        $title,
        $status,
        $notes !== '' ? $notes : null
      ]);

      flash_set('ok', 'Evento creado.');
      header("Location: $redirect");
      exit;
    }

    if ($action === 'event_delete') {
      $id = (int)($_POST['id'] ?? 0);
      if ($id <= 0) {
        flash_set('error', 'ID inválido.');
        header("Location: $redirect");
        exit;
      }

      $st = $pdo->prepare("DELETE FROM clinic_events WHERE id=? AND clinic_user_id=? LIMIT 1");
      $st->execute([$id, $clinicId]);

      flash_set('ok', 'Evento eliminado.');
      header("Location: $redirect");
      exit;
    }

    // (Opcional) update
    if ($action === 'event_update') {
      $id = (int)($_POST['id'] ?? 0);
      $event_date = (string)($_POST['event_date'] ?? '');
      $title = trim((string)($_POST['title'] ?? ''));
      $job_id = (int)($_POST['job_id'] ?? 0);
      $start_time = trim((string)($_POST['start_time'] ?? ''));
      $end_time = trim((string)($_POST['end_time'] ?? ''));
      $status = (string)($_POST['status'] ?? 'planned');
      $notes = trim((string)($_POST['notes'] ?? ''));

      if ($id <= 0 || $event_date === '' || $title === '') {
        flash_set('error', 'Datos incompletos para actualizar.');
        header("Location: $redirect");
        exit;
      }

      $st = $pdo->prepare("UPDATE clinic_events
        SET job_id=?, event_date=?, start_time=?, end_time=?, title=?, status=?, notes=?
        WHERE id=? AND clinic_user_id=?
        LIMIT 1");
      $st->execute([
        $job_id > 0 ? $job_id : null,
        $event_date,
        $start_time !== '' ? $start_time : null,
        $end_time !== '' ? $end_time : null,
        $title,
        $status,
        $notes !== '' ? $notes : null,
        $id,
        $clinicId
      ]);

      flash_set('ok', 'Evento actualizado.');
      header("Location: $redirect");
      exit;
    }

  } catch (Throwable $e) {
    error_log("CALENDAR ACTION ERROR: " . $e->getMessage());
    flash_set('error', 'Error interno. Revisa el log del servidor.');
    header("Location: $redirect");
    exit;
  }
}

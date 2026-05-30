<?php
/**
 * calendar.php
 * - MVP de calendario por usuario: agrega disponibilidad por fecha/hora.
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

require_login();
$pdo = db();

$userId = (int)($_GET['user_id'] ?? 0);

// Dropdown de usuarios
$allUsers = $pdo->query("SELECT id, role, name FROM users ORDER BY name ASC")->fetchAll();

$selected = $userId > 0 ? $userId : (int)($allUsers[0]['id'] ?? 0);

$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  verify_csrf();

  $selected = (int)($_POST['user_id'] ?? 0);
  $date = (string)($_POST['date'] ?? '');
  $start = (string)($_POST['start_time'] ?? '');
  $end = (string)($_POST['end_time'] ?? '');
  $note = trim((string)($_POST['note'] ?? ''));

  if ($selected <= 0) $err = 'Selecciona un usuario.';
  elseif (!$date || !$start || !$end) $err = 'Fecha y horas son obligatorias.';
  else {
    $stmt = $pdo->prepare("INSERT INTO availability(user_id,date,start_time,end_time,note) VALUES(?,?,?,?,?)");
    $stmt->execute([$selected, $date, $start, $end, $note ?: null]);
    $msg = 'Disponibilidad agregada.';
  }
}

$events = [];
if ($selected > 0) {
  $stmt = $pdo->prepare("SELECT * FROM availability WHERE user_id=? ORDER BY date DESC, start_time DESC");
  $stmt->execute([$selected]);
  $events = $stmt->fetchAll();
}

$csrf = csrf_token();
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= htmlspecialchars(APP_NAME) ?> | Calendario</title>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/admin.css">
</head>
<body>
  <div class="container">
    <div class="topbar">
      <div class="brand">
        <strong>PILLAR</strong>
        <span>Calendario / Disponibilidad</span>
      </div>
      <nav class="nav">
        <a href="<?= BASE_URL ?>/index.php">Dashboard</a>
        <a href="<?= BASE_URL ?>/users.php">Usuarios</a>
        <a class="active" href="<?= BASE_URL ?>/calendar.php">Calendario</a>
        <a href="<?= BASE_URL ?>/media.php">Media</a>
      </nav>
      <div class="actions">
        <a class="btn" href="<?= BASE_URL ?>/logout.php">Salir</a>
      </div>
    </div>

    <div class="grid">
      <section class="card">
        <div class="hd">
          <div>
            <h2>Gestionar disponibilidad</h2>
            <p>Selecciona un usuario/centro y agrega bloques de horario</p>
          </div>
          <span class="badge">Calendario</span>
        </div>

        <div class="bd">
          <?php if ($msg): ?>
            <div class="card" style="border-radius:12px;border-color:rgba(34,197,94,.35);">
              <div class="bd" style="background:rgba(34,197,94,.10);color:#14532d;"><?= htmlspecialchars($msg) ?></div>
            </div>
          <?php endif; ?>
          <?php if ($err): ?>
            <div class="card" style="border-radius:12px;border-color:rgba(239,68,68,.35);">
              <div class="bd" style="background:rgba(239,68,68,.10);color:#991b1b;"><?= htmlspecialchars($err) ?></div>
            </div>
          <?php endif; ?>

          <form method="post" class="form">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">

            <div class="field full">
              <label for="user_id">Usuario / Clínica / Hospital</label>
              <select id="user_id" name="user_id" onchange="this.form.submit()">
                <?php foreach ($allUsers as $u): ?>
                  <option value="<?= (int)$u['id'] ?>" <?= (int)$u['id']===$selected?'selected':'' ?>>
                    #<?= (int)$u['id'] ?> — <?= htmlspecialchars($u['name']) ?> (<?= htmlspecialchars($u['role']) ?>)
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="field">
              <label for="date">Fecha</label>
              <input id="date" name="date" type="date" required>
            </div>

            <div class="field">
              <label for="start_time">Inicio</label>
              <input id="start_time" name="start_time" type="time" required>
            </div>

            <div class="field">
              <label for="end_time">Fin</label>
              <input id="end_time" name="end_time" type="time" required>
            </div>

            <div class="field full">
              <label for="note">Nota</label>
              <input id="note" name="note" placeholder="Ej: Turno diurno, guardia, etc.">
            </div>

            <div class="field full">
              <button class="btn primary" type="submit">Agregar disponibilidad</button>
            </div>
          </form>

          <div style="margin-top:14px;">
            <h3 style="margin:0 0 10px;">Bloques registrados</h3>
            <table class="table">
              <thead>
                <tr>
                  <th>Fecha</th>
                  <th>Inicio</th>
                  <th>Fin</th>
                  <th>Nota</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($events as $ev): ?>
                  <tr>
                    <td><?= htmlspecialchars($ev['date']) ?></td>
                    <td><?= htmlspecialchars($ev['start_time']) ?></td>
                    <td><?= htmlspecialchars($ev['end_time']) ?></td>
                    <td><?= htmlspecialchars((string)$ev['note']) ?></td>
                  </tr>
                <?php endforeach; ?>
                <?php if (!$events): ?>
                  <tr><td colspan="4">Sin disponibilidad registrada.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>

         
          </div>
        </div>
      </section>
    </div>
  </div>
</body>
</html>

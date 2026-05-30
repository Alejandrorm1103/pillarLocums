<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

require_login();
$pdo = db();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { http_response_code(400); exit('ID inválido'); }

$roles = ['medico','clinica','hospital'];
$msg = '';
$err = '';

$stmt = $pdo->prepare("SELECT * FROM users WHERE id=?");
$stmt->execute([$id]);
$user = $stmt->fetch();
if (!$user) { http_response_code(404); exit('Usuario no encontrado'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  verify_csrf();

  $role = (string)($_POST['role'] ?? $user['role']);
  $name = trim((string)($_POST['name'] ?? $user['name']));
  $email = trim((string)($_POST['email'] ?? $user['email']));
  $phone = trim((string)($_POST['phone'] ?? ''));
  $location = trim((string)($_POST['location'] ?? ''));
  $status = (string)($_POST['status'] ?? $user['status']);

  if (!in_array($role, $roles, true)) $err = 'Rol inválido.';
  elseif ($name === '' || $email === '') $err = 'Nombre y email son obligatorios.';
  elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $err = 'Email inválido.';

  if ($err === '') {
    try {
      $stmt = $pdo->prepare("UPDATE users SET role=?,name=?,email=?,phone=?,location=?,status=?,updated_at=NOW() WHERE id=?");
      $stmt->execute([$role,$name,$email,$phone?:null,$location?:null,$status,$id]);
      $msg = 'Cambios guardados.';
      $stmt = $pdo->prepare("SELECT * FROM users WHERE id=?");
      $stmt->execute([$id]);
      $user = $stmt->fetch();
    } catch (Throwable $e) {
      $err = 'No se pudo guardar (email duplicado u otro error).';
    }
  }
}

$csrf = csrf_token();
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= htmlspecialchars(APP_NAME) ?> | Editar usuario</title>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/admin.css">
</head>
<body>
  <div class="container">
    <div class="topbar">
      <div class="brand">
        <strong>PILLAR</strong>
        <span>Editar usuario</span>
      </div>
      <nav class="nav">
        <a href="<?= BASE_URL ?>/index.php">Dashboard</a>
        <a class="active" href="<?= BASE_URL ?>/users.php">Usuarios</a>
        <a href="<?= BASE_URL ?>/calendar.php">Calendario</a>
        <a href="<?= BASE_URL ?>/media.php">Media</a>
      </nav>
      <div class="actions">
        <a class="btn" href="<?= BASE_URL ?>/users.php">Volver</a>
        <a class="btn" href="<?= BASE_URL ?>/logout.php">Salir</a>
      </div>
    </div>

    <div class="grid">
      <section class="card">
        <div class="hd">
          <div>
            <h2>Usuario #<?= (int)$user['id'] ?></h2>
            <p>Actualiza datos y estado</p>
          </div>
          <span class="badge"><?= htmlspecialchars($user['role']) ?></span>
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

            <div class="field">
              <label for="role">Rol</label>
              <select id="role" name="role">
                <?php foreach (['medico'=>'Médico','clinica'=>'Clínica','hospital'=>'Hospital'] as $k=>$v): ?>
                  <option value="<?= $k ?>" <?= $user['role']===$k?'selected':'' ?>><?= $v ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="field">
              <label for="status">Estado</label>
              <select id="status" name="status">
                <option value="activo" <?= $user['status']==='activo'?'selected':'' ?>>Activo</option>
                <option value="inactivo" <?= $user['status']==='inactivo'?'selected':'' ?>>Inactivo</option>
              </select>
            </div>

            <div class="field">
              <label for="name">Nombre</label>
              <input id="name" name="name" value="<?= htmlspecialchars($user['name']) ?>" required>
            </div>

            <div class="field">
              <label for="email">Email</label>
              <input id="email" name="email" type="email" value="<?= htmlspecialchars($user['email']) ?>" required>
            </div>

            <div class="field">
              <label for="phone">Teléfono</label>
              <input id="phone" name="phone" value="<?= htmlspecialchars((string)$user['phone']) ?>">
            </div>

            <div class="field">
              <label for="location">Ubicación</label>
              <input id="location" name="location" value="<?= htmlspecialchars((string)$user['location']) ?>">
            </div>

            <div class="field full">
              <button class="btn primary" type="submit">Guardar cambios</button>
              <a class="btn" href="<?= BASE_URL ?>/calendar.php?user_id=<?= (int)$user['id'] ?>">Editar calendario</a>
            </div>
          </form>
        </div>
      </section>
    </div>
  </div>
</body>
</html>

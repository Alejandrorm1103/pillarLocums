<?php
/**
 * users.php
 * - Listado con filtro por rol.
 * - Formulario para crear usuario (admin).
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

require_login();
$pdo = db();

$roleFilter = (string)($_GET['role'] ?? 'all');

$roles = ['medico','clinica','hospital'];

$createMsg = '';
$createErr = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  verify_csrf();

  $role = (string)($_POST['role'] ?? '');
  $name = trim((string)($_POST['name'] ?? ''));
  $email = trim((string)($_POST['email'] ?? ''));
  $phone = trim((string)($_POST['phone'] ?? ''));
  $location = trim((string)($_POST['location'] ?? ''));
  $status = (string)($_POST['status'] ?? 'activo');

  if (!in_array($role, $roles, true)) $createErr = 'Rol inválido.';
  elseif ($name === '' || $email === '') $createErr = 'Nombre y email son obligatorios.';
  elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $createErr = 'Email inválido.';

  if ($createErr === '') {
    try {
      $stmt = $pdo->prepare("INSERT INTO users(role,name,email,phone,location,status) VALUES(?,?,?,?,?,?)");
      $stmt->execute([$role, $name, $email, $phone ?: null, $location ?: null, $status]);
      $createMsg = 'Usuario creado correctamente.';
    } catch (Throwable $e) {
      $createErr = 'No se pudo crear. Verifica si el email ya existe.';
    }
  }
}

// Listado
if ($roleFilter !== 'all' && in_array($roleFilter, $roles, true)) {
  $stmt = $pdo->prepare("SELECT * FROM users WHERE role=? ORDER BY created_at DESC");
  $stmt->execute([$roleFilter]);
  $users = $stmt->fetchAll();
} else {
  $users = $pdo->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll();
}

$csrf = csrf_token();
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= htmlspecialchars(APP_NAME) ?> | Usuarios</title>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/admin.css">
</head>
<body>
  <div class="container">
    <div class="topbar">
      <div class="brand">
        <strong>PILLAR</strong>
        <span>Usuarios</span>
      </div>

      <nav class="nav">
        <a href="<?= BASE_URL ?>/index.php">Dashboard</a>
        <a class="active" href="<?= BASE_URL ?>/users.php">Usuarios</a>
        <a href="<?= BASE_URL ?>/calendar.php">Calendario</a>
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
            <h2>Listado de usuarios</h2>
            <p>Filtra por rol y gestiona registros</p>
          </div>

          <div class="row">
            <a class="btn <?= $roleFilter==='all'?'primary':'' ?>" href="<?= BASE_URL ?>/users.php?role=all">Todos</a>
            <a class="btn <?= $roleFilter==='medico'?'primary':'' ?>" href="<?= BASE_URL ?>/users.php?role=medico">Médicos</a>
            <a class="btn <?= $roleFilter==='clinica'?'primary':'' ?>" href="<?= BASE_URL ?>/users.php?role=clinica">Clínicas</a>
            <a class="btn <?= $roleFilter==='hospital'?'primary':'' ?>" href="<?= BASE_URL ?>/users.php?role=hospital">Hospitales</a>
          </div>
        </div>

        <div class="bd">
          <table class="table" aria-label="Usuarios">
            <thead>
              <tr>
                <th>ID</th>
                <th>Rol</th>
                <th>Nombre</th>
                <th>Email</th>
                <th>Ubicación</th>
                <th>Estado</th>
                <th>Creado</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($users as $u): ?>
                <tr>
                  <td><?= (int)$u['id'] ?></td>
                  <td><?= htmlspecialchars($u['role']) ?></td>
                  <td><?= htmlspecialchars($u['name']) ?></td>
                  <td><?= htmlspecialchars($u['email']) ?></td>
                  <td><?= htmlspecialchars((string)$u['location']) ?></td>
                  <td><?= htmlspecialchars($u['status']) ?></td>
                  <td><?= htmlspecialchars($u['created_at']) ?></td>
                  <td class="row">
                    <a class="btn" href="<?= BASE_URL ?>/user_edit.php?id=<?= (int)$u['id'] ?>">Editar</a>
                    <a class="btn" href="<?= BASE_URL ?>/calendar.php?user_id=<?= (int)$u['id'] ?>">Calendario</a>

                    <!-- ✅ Eliminar por POST + CSRF -->
                    <form method="POST" action="<?= BASE_URL ?>/user_delete.php" style="display:inline"
                          onsubmit="return confirm('¿Eliminar este usuario?');">
                      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                      <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                      <button class="btn danger" type="submit">Eliminar</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if (!$users): ?>
                <tr><td colspan="8">No hay usuarios para mostrar.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>

      <section class="card">
        <div class="hd">
          <div>
            <h2>Crear usuario</h2>
            <p>Registro manual por el administrador</p>
          </div>
          <span class="badge">Alta</span>
        </div>

        <div class="bd">
          <?php if ($createMsg): ?>
            <div class="card" style="border-radius:12px;border-color:rgba(34,197,94,.35);">
              <div class="bd" style="background:rgba(34,197,94,.10);color:#14532d;"><?= htmlspecialchars($createMsg) ?></div>
            </div>
          <?php endif; ?>
          <?php if ($createErr): ?>
            <div class="card" style="border-radius:12px;border-color:rgba(239,68,68,.35);">
              <div class="bd" style="background:rgba(239,68,68,.10);color:#991b1b;"><?= htmlspecialchars($createErr) ?></div>
            </div>
          <?php endif; ?>

          <!-- ✅ Form correcto: Crear usuario (con CSRF) -->
          <form method="post" class="form" autocomplete="off">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">

            <div class="field">
              <label for="role">Rol</label>
              <select id="role" name="role" required>
                <option value="medico">Médico</option>
                <option value="clinica">Clínica</option>
                <option value="hospital">Hospital</option>
              </select>
            </div>

            <div class="field">
              <label for="status">Estado</label>
              <select id="status" name="status">
                <option value="activo">Activo</option>
                <option value="inactivo">Inactivo</option>
              </select>
            </div>

            <div class="field">
              <label for="name">Nombre</label>
              <input id="name" name="name" required placeholder="Nombre completo o entidad">
            </div>

            <div class="field">
              <label for="email">Email</label>
              <input id="email" name="email" type="email" required placeholder="usuario@correo.com">
            </div>

            <div class="field">
              <label for="phone">Teléfono</label>
              <input id="phone" name="phone" placeholder="+353 ...">
            </div>

            <div class="field">
              <label for="location">Ubicación</label>
              <input id="location" name="location" placeholder="Dublin / Cork / Galway...">
            </div>

            <div class="field full">
              <button class="btn primary" type="submit">Crear usuario</button>
            </div>
          </form>

          <p class="note">
            Este MVP gestiona datos esenciales. Puedes ampliar perfiles (especialidad, licencias, etc.) agregando columnas
            en <code>users</code> o una tabla <code>profiles</code>.
          </p>
        </div>
      </section>
    </div>
  </div>
</body>
</html>
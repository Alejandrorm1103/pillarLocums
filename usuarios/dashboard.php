<?php
declare(strict_types=1);

require_once __DIR__ . "/../config/auth.php";
require_login();

$user = $_SESSION['user'] ?? null;
$role = $user['role'] ?? '';

/**
 * Redirección por rol 
 */
if ($role === 'medico' && file_exists(__DIR__ . "/medico/index.php")) {
  header("Location: /usuarios/medico/index.php");
  exit;
}

if ($role === 'clinica' && file_exists(__DIR__ . "/clinica/index.php")) {
  header("Location: /usuarios/clinica/index.php");
  exit;
}

if ($role === 'hospital' && file_exists(__DIR__ . "/hospital/index.php")) {
  header("Location: /usuarios/hospital/index.php");
  exit;
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Dashboard | PILLAR Locums</title>
</head>
<body>
  <h1>Dashboard</h1>
  <p>Sesión iniciada correctamente.</p>

  <ul>
    <li><strong>ID:</strong> <?= (int)($user['id'] ?? 0) ?></li>
    <li><strong>Rol:</strong> <?= htmlspecialchars((string)($user['role'] ?? '')) ?></li>
    <li><strong>Nombre:</strong> <?= htmlspecialchars((string)($user['name'] ?? '')) ?></li>
    <li><strong>Email:</strong> <?= htmlspecialchars((string)($user['email'] ?? '')) ?></li>
  </ul>

  <?php if ($role === 'medico'): ?>
    <p><a href="/usuarios/medico/index.php">Ir a mi panel de Médico</a></p>
  <?php elseif ($role === 'clinica'): ?>
    <p><a href="/usuarios/clinica/index.php">Ir a mi panel de Clínica</a></p>
  <?php elseif ($role === 'hospital'): ?>
    <p><a href="/usuarios/hospital/index.php">Ir a mi panel de Hospital</a></p>
  <?php else: ?>
    <p><em>Rol no reconocido o dashboard específico aún no creado.</em></p>
  <?php endif; ?>

  <p><a href="/usuarios/logout.php">Cerrar sesión</a></p>
  <p><a href="/index.html">Volver al inicio</a></p>
</body>
</html>

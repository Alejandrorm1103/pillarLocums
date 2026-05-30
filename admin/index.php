<?php
declare(strict_types=1);

/**
 * index.php
 * Dashboard admin
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

require_login();

$pdo = db();

// DEBUG opcional
if (isset($_GET['debug'])) {
  echo "<pre>ADMIN DB = " . htmlspecialchars((string)$pdo->query("SELECT DATABASE()")->fetchColumn()) . "</pre>";
}

// Stats
$total = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$medicos = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='medico'")->fetchColumn();
$clinicas = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='clinica'")->fetchColumn();
$hospitales = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='hospital'")->fetchColumn();
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= htmlspecialchars(APP_NAME) ?> | Dashboard</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/admin.css">
</head>

<body>
<div class="container">

<div class="topbar">
<div class="brand">
<strong>PILLAR</strong>
<span>Locums — Admin Panel</span>
</div>

<nav class="nav">
<a class="active" href="<?= BASE_URL ?>/index.php">Dashboard</a>
<a href="<?= BASE_URL ?>/users.php">Usuarios</a>
<a href="<?= BASE_URL ?>/calendar.php">Calendario</a>
<a href="<?= BASE_URL ?>/media.php">Media</a>
<a href="<?= BASE_URL ?>/credenciales.php">Credenciales</a>
</nav>

<div class="actions">
<a class="btn" href="<?= BASE_URL ?>/logout.php">Salir</a>
</div>
</div>

<div class="grid">

<section class="card">

<div class="hd">
<div>
<h2>Resumen</h2>
<p>Estadísticas generales</p>
</div>
<span class="badge">Vista general</span>
</div>

<div class="bd">

<div class="stats">
<div class="stat"><strong><?= $total ?></strong><span>Total usuarios</span></div>
<div class="stat"><strong><?= $medicos ?></strong><span>Médicos</span></div>
<div class="stat"><strong><?= $clinicas ?></strong><span>Clínicas</span></div>
<div class="stat"><strong><?= $hospitales ?></strong><span>Hospitales</span></div>
</div>

<div class="row" style="margin-top:14px;">
<a class="btn primary" href="<?= BASE_URL ?>/users.php">Gestionar usuarios</a>
<a class="btn" href="<?= BASE_URL ?>/calendar.php">Gestionar calendario</a>
<a class="btn" href="<?= BASE_URL ?>/media.php">Actualizar carrusel / video</a>
</div>

</div>
</section>

</div>
</div>

</body>
</html>

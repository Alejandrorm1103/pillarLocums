<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

start_session();
unset($_SESSION['admin_logged_in'], $_SESSION['admin'], $_SESSION['csrf']);
$_SESSION = [];

// Borra cookie de sesión si existe
if (ini_get('session.use_cookies')) {
  $params = session_get_cookie_params();
  setcookie(session_name(), '', time() - 42000,
    $params['path'] ?? '/',
    $params['domain'] ?? '',
    (bool)($params['secure'] ?? false),
    (bool)($params['httponly'] ?? true)
  );
}

// Destruye sesión
session_destroy();

// Redirige al login
header('Location: ' . BASE_URL . '/login.php');
exit;
<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

/**
 * Helpers de auth (mínimos y estables)
 */

function is_logged_in(): bool {
  return isset($_SESSION['user']) && is_array($_SESSION['user']);
}

function require_login(): void {
  if (!is_logged_in()) {
    header("Location: /usuarios/login.php");
    exit;
  }
}

function login_user(array $userRow): void {
  session_regenerate_id(true);
  $_SESSION['user'] = [
    'id'    => (int)($userRow['id'] ?? 0),
    'role'  => (string)($userRow['role'] ?? ''),
    'name'  => (string)($userRow['name'] ?? ''),
    'email' => (string)($userRow['email'] ?? ''),
  ];
}

function logout_user(): void {
  $_SESSION = [];
  if (ini_get("session.use_cookies")) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
  }
  session_destroy();
}

function role_home(string $role): string {
  return match ($role) {
    'medico'   => '/usuarios/medico/index.php',
    'clinica'  => '/usuarios/clinica/index.php',
    'hospital' => '/usuarios/hospital/index.php',
    default    => '/usuarios/dashboard.php',
  };
}

<?php
/**
 * auth.php
 * - Manejo de sesión, protección de rutas, CSRF básico.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

function start_session(): void {
  if (session_status() !== PHP_SESSION_ACTIVE) {

    // Endurecimiento de cookie de sesión (compatible y sin frameworks)
    // Nota: Secure solo si estás en HTTPS
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
      || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);

    // Evita warnings si ya se enviaron headers
    if (!headers_sent()) {
      session_name(SESSION_NAME);

      session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
      ]);
    }

    session_start();
  }
}

function is_logged_in(): bool {
  start_session();

  // Compatibilidad: hoy usas admin_logged_in, mañana puedes usar $_SESSION['admin']
  if (!empty($_SESSION['admin_logged_in'])) return true;
  if (!empty($_SESSION['admin']) && is_array($_SESSION['admin'])) return true;

  return false;
}

function require_login(): void {
  if (!is_logged_in()) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
  }
}

function csrf_token(): string {
  start_session();
  if (empty($_SESSION['csrf'])) {
    // 32 bytes => 64 hex (más robusto)
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
  }
  return (string)$_SESSION['csrf'];
}

function verify_csrf(): void {
  start_session();
  $token = $_POST['csrf'] ?? '';
  if (!is_string($token) || $token === '' || empty($_SESSION['csrf']) || !hash_equals((string)$_SESSION['csrf'], $token)) {
    http_response_code(400);
    exit('CSRF inválido.');
  }
}
<?php
/**
 * login.php
 * - Login con contraseña única (hash) para Admin.
 * - Opción: generar hash una vez (solo dev) con ?gen=1
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

start_session();


 
$allowHashGen = defined('ALLOW_HASH_GEN') ? (bool)ALLOW_HASH_GEN : false;

// Generador de hash (úsalo 1 vez y pega el resultado en ADMIN_PASSWORD_HASH)
// Generador de hash (solo desarrollo/mantenimiento controlado)
if (isset($_GET['gen'])) {
  // Por defecto: deshabilitado si no existe la constante
  $allow = defined('ALLOW_HASH_GEN') ? (bool)ALLOW_HASH_GEN : false;

  if (!$allow) {
    http_response_code(403);
    exit('Disabled.');
  }

  $plain = 'PILLAR Locums2026@123';
  header('Content-Type: text/plain; charset=utf-8');
  echo password_hash($plain, PASSWORD_DEFAULT);
  exit;
}

$error = '';

/**
 * Rate limit básico por sesión (no requiere DB):
 * - max X intentos en ventana de Y segundos
 */
$maxAttempts = 8;
$windowSec   = 10 * 60; // 10 min

$_SESSION['login_attempts'] = $_SESSION['login_attempts'] ?? [];
// Limpia intentos viejos
$_SESSION['login_attempts'] = array_values(array_filter(
  (array)$_SESSION['login_attempts'],
  fn($t) => is_int($t) && ($t > (time() - $windowSec))
));

$blocked = count($_SESSION['login_attempts']) >= $maxAttempts;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if ($blocked) {
    $error = 'Demasiados intentos. Espera unos minutos e inténtalo de nuevo.';
  } else {
    $pass = (string)($_POST['password'] ?? '');

    if (!defined('ADMIN_PASSWORD_HASH') || ADMIN_PASSWORD_HASH === 'PEGAR_HASH_AQUI') {
      $error = 'Configura ADMIN_PASSWORD_HASH en config.php (usa ?gen=1 una vez).';
    } else {
      if (password_verify($pass, ADMIN_PASSWORD_HASH)) {

        // ✅ Previene session fixation
        session_regenerate_id(true);

        // Marca login (compatible con tu sistema actual)
        $_SESSION['admin_logged_in'] = true;

        // CSRF para formularios (si ya existía, lo rotamos)
        $_SESSION['csrf'] = bin2hex(random_bytes(32));

       
         
         
        $_SESSION['admin'] = [
          'id'    => 1,
          'email' => 'admin@local',
          'role'  => 'superadmin',
        ];

        // Limpia intentos al éxito
        $_SESSION['login_attempts'] = [];

        header('Location: ' . BASE_URL . '/index.php');
        exit;
      }

      // Fallo: registrar intento
      $_SESSION['login_attempts'][] = time();
      $error = 'Contraseña incorrecta.';
    }
  }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= htmlspecialchars(APP_NAME) ?> | Login</title>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/admin.css">
</head>
<body>
  <div class="container">
    <div class="card" style="max-width:520px;margin:60px auto;">
      <div class="hd">
        <div>
          <h2>Acceso Administrador</h2>
          <p>Panel de gestión — PILLAR Locums</p>
        </div>
      </div>
      <div class="bd">
        <p class="note">
          Este acceso está protegido por sesión. No guardes la contraseña en HTML/JS.
        </p>

        <?php if ($error): ?>
          <div class="card" style="border-radius:12px;margin:12px 0;border-color:rgba(239,68,68,.35);">
            <div class="bd" style="color:#991b1b;background:rgba(239,68,68,.08);">
              <?= htmlspecialchars($error) ?>
            </div>
          </div>
        <?php endif; ?>

        <form method="post" class="form" autocomplete="off">
          <div class="field full">
            <label for="password">Contraseña</label>
            <input id="password" name="password" type="password" required placeholder="••••••••••">
          </div>

          <div class="field full">
            <button class="btn primary" type="submit" style="width:100%;">Entrar</button>
          </div>
        </form>

        <p class="note" style="margin-top:10px;">
          Si aún no pegaste el hash en <code>config.php</code>, abre una vez:
          <code>/admin/login.php?gen=1</code> y copia el hash.
        </p>

        <?php if (!$allowHashGen): ?>
          <p class="note" style="margin-top:10px;">
            Nota: El generador <code>?gen=1</code> está deshabilitado (recomendado en producción).
          </p>
        <?php endif; ?>

      </div>
    </div>
  </div>
</body>
</html>
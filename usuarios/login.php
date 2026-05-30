<?php
declare(strict_types=1);

require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/auth.php";

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

/* =========================
   Idioma ES / EN
========================= */
$supportedLangs = ['es', 'en'];

if (isset($_GET['lang']) && in_array($_GET['lang'], $supportedLangs, true)) {
  $_SESSION['lang'] = $_GET['lang'];
}

$lang = $_SESSION['lang'] ?? 'es';

$translations = [
  'es' => [
    'html_lang'                => 'es',
    'page_title'               => 'Login',
    'brand_name'               => 'PILLAR',
    'brand_sub'                => 'Locums',
    'brand_claim'              => 'Supporting Care You Can Count On',
    'login_title'              => 'Iniciar sesión',
    'login_subtitle'           => 'Accede a tu panel según tu rol (médico, clínica u hospital).',
    'email'                    => 'Email',
    'password'                 => 'Contraseña',
    'login_button'             => 'Entrar',
    'create_account'           => 'Crear cuenta',
    'back_home'                => 'Volver al inicio',
    'secure_title'             => 'Acceso seguro por sesión.',
    'secure_text'              => 'Si tu cuenta está activa, al entrar se te redirige automáticamente al panel correspondiente.',
    'review_errors'            => 'Revisa lo siguiente:',
    'invalid_email'            => 'Email inválido.',
    'required_password'        => 'Contraseña obligatoria.',
    'wrong_credentials'        => 'Credenciales incorrectas.',
    'inactive_user'            => 'Usuario inactivo. Contacta soporte.',
    'internal_error'           => 'Error interno al iniciar sesión.',
    'lang_es'                  => 'ES',
    'lang_en'                  => 'EN',
  ],
  'en' => [
    'html_lang'                => 'en',
    'page_title'               => 'Login',
    'brand_name'               => 'PILLAR',
    'brand_sub'                => 'Locums',
    'brand_claim'              => 'Supporting Care You Can Count On',
    'login_title'              => 'Sign in',
    'login_subtitle'           => 'Access your dashboard according to your role (doctor, clinic or hospital).',
    'email'                    => 'Email',
    'password'                 => 'Password',
    'login_button'             => 'Log in',
    'create_account'           => 'Create account',
    'back_home'                => 'Back to home',
    'secure_title'             => 'Secure session access.',
    'secure_text'              => 'If your account is active, once you log in you will be redirected automatically to the corresponding dashboard.',
    'review_errors'            => 'Please review the following:',
    'invalid_email'            => 'Invalid email.',
    'required_password'        => 'Password is required.',
    'wrong_credentials'        => 'Incorrect credentials.',
    'inactive_user'            => 'Inactive user. Contact support.',
    'internal_error'           => 'Internal error while signing in.',
    'lang_es'                  => 'ES',
    'lang_en'                  => 'EN',
  ],
];

function t(string $key): string {
  global $translations, $lang;
  return htmlspecialchars($translations[$lang][$key] ?? $key, ENT_QUOTES, 'UTF-8');
}

$errors = [];

// Si ya está logueado, manda al home por rol
if (is_logged_in()) {
  if (function_exists('role_home')) {
    header("Location: " . role_home((string)$_SESSION['user']['role']));
    exit;
  }
  header("Location: /usuarios/dashboard.php");
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = trim((string)($_POST['email'] ?? ''));
  $password = (string)($_POST['password'] ?? '');

  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = t('invalid_email');
  if ($password === '') $errors[] = t('required_password');

  if (!$errors) {
    try {
      $sql = "SELECT id, role, name, email, status, password_hash
              FROM users
              WHERE email = ?
              LIMIT 1";
      $stmt = $pdo->prepare($sql);
      $stmt->execute([$email]);
      $user = $stmt->fetch(PDO::FETCH_ASSOC);

      if (!$user) {
        $errors[] = t('wrong_credentials');
      } elseif (($user['status'] ?? 'activo') !== 'activo') {
        $errors[] = t('inactive_user');
      } elseif (!password_verify($password, (string)$user['password_hash'])) {
        $errors[] = t('wrong_credentials');
      } else {
        login_user($user);
        header("Location: " . (function_exists('role_home') ? role_home((string)$user['role']) : "/usuarios/dashboard.php"));
        exit;
      }
    } catch (Throwable $e) {
      error_log("LOGIN ERROR: " . $e->getMessage());
      $errors[] = t('internal_error');
    }
  }
}
?>
<!doctype html>
<html lang="<?= t('html_lang') ?>">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title><?= t('page_title') ?> | PILLAR Locums</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Libre+Baskerville:wght@400;700&display=swap" rel="stylesheet">

  <style>
    :root{
      /* Nueva paleta */
      --teal:#3F7882;
      --mint:#9CC9C4;
      --mist:#DDE5EA;
      --blush:#EED6CC;
      --terracotta:#CF8F6B;
      --charcoal:#444446;

      --card: rgba(255,255,255,.06);
      --border: rgba(255,255,255,.12);
      --shadow: 0 24px 70px rgba(0,0,0,.35);
      --radius: 20px;
      --radius-sm: 14px;

      --font-title:"Libre Baskerville", serif;
      --font-ui:"Galatea","Segoe UI",Arial,sans-serif;
    }

    *{ box-sizing:border-box; }
    html,body{ height:100%; }

    body{
      margin:0;
      font-family:var(--font-ui);
      color: #F7F7F7;
      background:
        radial-gradient(1200px 700px at 18% 10%, rgba(63,120,130,.20), transparent 60%),
        radial-gradient(900px 520px at 85% 18%, rgba(156,201,196,.14), transparent 58%),
        radial-gradient(700px 420px at 70% 80%, rgba(207,143,107,.12), transparent 50%),
        linear-gradient(145deg, rgba(20,24,28,.96), rgba(44,49,55,.96));
      display:grid;
      place-items:center;
      padding:22px;
    }

    .shell{
      width:min(980px, 100%);
      display:grid;
      grid-template-columns: 1.05fr .95fr;
      gap:16px;
      align-items:stretch;
    }

    .card{
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      backdrop-filter: blur(16px);
      overflow:hidden;
    }

    .formCard{ padding:22px 22px 18px; }

    .brandRow{
      display:flex;
      align-items:flex-start;
      justify-content:space-between;
      gap:12px;
      margin-bottom:10px;
    }

    .brand{
      display:flex;
      align-items:center;
      gap:12px;
      min-width:0;
    }

    .brandMark{
      width:44px;
      height:44px;
      border-radius:14px;
      background: rgba(63,120,130,.18);
      border: 1px solid rgba(156,201,196,.28);
      display:grid;
      place-items:center;
      font-weight:700;
      color: var(--mint);
      letter-spacing:.08em;
      font-family:var(--font-title);
    }

    .brandTitle{
      line-height:1.05;
    }

    .brandTitle strong{
      display:block;
      text-transform:uppercase;
      letter-spacing:.08em;
      font-size:.95rem;
      font-family:var(--font-title);
      color:#F4F0EA;
    }

    .brandTitle span{
      display:block;
      color: rgba(246,241,231,.78);
      font-size:.9rem;
      margin-top:2px;
    }

    .langSwitch{
      display:inline-flex;
      align-items:center;
      gap:6px;
      padding:5px 6px;
      border-radius:999px;
      background:rgba(255,255,255,.05);
      border:1px solid rgba(255,255,255,.10);
      flex-shrink:0;
    }

    .globe{
      width:28px;
      height:28px;
      border-radius:50%;
      display:grid;
      place-items:center;
      background:linear-gradient(180deg, rgba(63,120,130,.28), rgba(156,201,196,.08));
      color:var(--mint);
      border:1px solid rgba(156,201,196,.24);
    }

    .langBtn{
      display:inline-flex;
      align-items:center;
      justify-content:center;
      min-width:34px;
      height:28px;
      padding:0 10px;
      border-radius:999px;
      text-decoration:none;
      font-size:11px;
      font-weight:700;
      letter-spacing:.06em;
      color:#DDE5EA;
      border:1px solid transparent;
      transition:.2s ease;
    }

    .langBtn:hover{
      background:rgba(255,255,255,.06);
      border-color:rgba(255,255,255,.08);
    }

    .langBtn.active{
      background:linear-gradient(180deg, var(--teal), #4b8b95);
      color:#fff;
      box-shadow:0 6px 16px rgba(63,120,130,.30);
    }

    h1{
      margin:10px 0 6px;
      font-size:1.55rem;
      font-family:var(--font-title);
      color:#F5F1EB;
    }

    .subtitle{
      margin:0 0 16px;
      color: rgba(246,241,231,.78);
      font-size:.95rem;
      line-height:1.55;
      font-family:var(--font-ui);
    }

    .errorBox{
      border-radius:var(--radius-sm);
      border:1px solid rgba(207,143,107,.45);
      background: rgba(207,143,107,.12);
      padding:12px 12px 10px;
      margin:10px 0 14px;
      color:#F7EDE8;
    }

    .errorBox strong{
      display:block;
      margin-bottom:6px;
      font-family:var(--font-ui);
    }

    .errorBox ul{
      margin:0;
      padding-left:18px;
    }

    label{
      display:block;
      margin:10px 0 6px;
      color: rgba(246,241,231,.86);
      font-size:.92rem;
      font-family:var(--font-ui);
    }

    .input{
      width:100%;
      padding:12px 12px;
      border-radius:14px;
      border:1px solid rgba(255,255,255,.14);
      background: rgba(0,0,0,.18);
      color:#F6F8F8;
      font-family:var(--font-ui);
      outline:none;
    }

    .input:focus{
      border-color: rgba(156,201,196,.58);
      box-shadow: 0 0 0 6px rgba(156,201,196,.10);
    }

    .row{
      display:flex;
      gap:10px;
      margin-top:12px;
      flex-wrap:wrap;
    }

    .btn{
      display:inline-flex;
      align-items:center;
      justify-content:center;
      padding:12px 14px;
      border-radius:14px;
      border:1px solid rgba(0,0,0,.18);
      background: linear-gradient(180deg, rgba(63,120,130,.96), rgba(63,120,130,.78));
      color:#F6FAFB;
      font-family:var(--font-ui);
      font-weight:700;
      cursor:pointer;
      min-width:160px;
      box-shadow:0 10px 24px rgba(63,120,130,.24);
    }

    .btn:hover{
      filter:brightness(1.03);
    }

    .links{
      margin-top:14px;
      display:flex;
      flex-direction:column;
      gap:8px;
      font-size:.92rem;
      font-family:var(--font-ui);
    }

    .links a{
      color: var(--terracotta);
      text-decoration:none;
    }

    .links a:hover{
      text-decoration:underline;
      color:#e2a98a;
    }

    .artCard{
      padding:18px;
      display:flex;
      flex-direction:column;
      justify-content:space-between;
      gap:14px;
    }

    .artTop{
      border-radius:var(--radius);
      border:1px solid rgba(255,255,255,.10);
      background:
        radial-gradient(560px 300px at 30% 30%, rgba(63,120,130,.18), transparent 60%),
        radial-gradient(520px 260px at 80% 40%, rgba(207,143,107,.14), transparent 60%),
        rgba(0,0,0,.14);
      padding:16px;
      min-height:260px;
      display:grid;
      place-items:center;
    }

    .artNote{
      border-radius:var(--radius);
      border:1px solid rgba(255,255,255,.10);
      background: rgba(0,0,0,.14);
      padding:14px;
      color: rgba(246,241,231,.80);
      line-height:1.55;
      font-size:.92rem;
      font-family:var(--font-ui);
    }

    .artNote strong{
      font-family:var(--font-title);
      color:#F4F0EA;
    }

    @media (max-width: 880px){
      .shell{ grid-template-columns:1fr; }
      .btn{ width:100%; }
      .artTop{ min-height:220px; }
    }

    @media (max-width: 560px){
      .brandRow{
        flex-direction:column;
        align-items:flex-start;
      }
    }
  </style>
</head>
<body>
  <main class="shell">
    <section class="card formCard">

      <div class="brandRow">
        <div class="brand">
          <div class="brandMark">PL</div>
          <div class="brandTitle">
            <strong><?= t('brand_name') ?></strong>
            <span><?= t('brand_sub') ?> • <?= t('brand_claim') ?></span>
          </div>
        </div>

        <div class="langSwitch" aria-label="Language switcher">
          <div class="globe" aria-hidden="true">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
              <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2Z" stroke="currentColor" stroke-width="1.7"/>
              <path d="M2 12h20" stroke="currentColor" stroke-width="1.7"/>
              <path d="M12 2c2.8 2.7 4.5 6.2 4.5 10S14.8 19.3 12 22c-2.8-2.7-4.5-6.2-4.5-10S9.2 4.7 12 2Z" stroke="currentColor" stroke-width="1.7"/>
            </svg>
          </div>
          <a class="langBtn <?= $lang === 'es' ? 'active' : '' ?>" href="/usuarios/login.php?lang=es"><?= t('lang_es') ?></a>
          <a class="langBtn <?= $lang === 'en' ? 'active' : '' ?>" href="/usuarios/login.php?lang=en"><?= t('lang_en') ?></a>
        </div>
      </div>

      <h1><?= t('login_title') ?></h1>
      <p class="subtitle"><?= t('login_subtitle') ?></p>

      <?php if (!empty($errors)): ?>
        <div class="errorBox" role="alert">
          <strong><?= t('review_errors') ?></strong>
          <ul>
            <?php foreach ($errors as $err): ?>
              <li><?= htmlspecialchars((string)$err) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <form method="post" action="/usuarios/login.php" autocomplete="on">
        <label for="email"><?= t('email') ?></label>
        <input class="input" id="email" name="email" type="email" required
               value="<?= htmlspecialchars((string)($_POST['email'] ?? '')) ?>">

        <label for="password"><?= t('password') ?></label>
        <input class="input" id="password" name="password" type="password" required>

        <div class="row">
          <button class="btn" type="submit"><?= t('login_button') ?></button>
        </div>
      </form>

      <div class="links">
        <a href="/usuarios/register.php"><?= t('create_account') ?></a>
        <a href="/index.html"><?= t('back_home') ?></a>
      </div>
    </section>

    <aside class="card artCard">
      <div class="artTop">
        <svg width="280" height="220" viewBox="0 0 280 220" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
          <defs>
            <linearGradient id="g" x1="70" y1="20" x2="210" y2="200" gradientUnits="userSpaceOnUse">
              <stop stop-color="#3F7882" stop-opacity="0.95"/>
              <stop offset="0.55" stop-color="#9CC9C4" stop-opacity="0.72"/>
              <stop offset="1" stop-color="#CF8F6B" stop-opacity="0.62"/>
            </linearGradient>
          </defs>
          <circle cx="140" cy="92" r="74" stroke="url(#g)" stroke-width="10" opacity="0.72"/>
          <circle cx="140" cy="92" r="52" stroke="#DDE5EA" stroke-opacity="0.18" stroke-width="10"/>
          <circle cx="140" cy="92" r="36" fill="rgba(0,0,0,.25)" stroke="rgba(255,255,255,.14)" stroke-width="2"/>
          <circle cx="128" cy="90" r="4" fill="#F6F1E7" fill-opacity="0.85"/>
          <circle cx="152" cy="90" r="4" fill="#F6F1E7" fill-opacity="0.85"/>
          <path d="M128 106C134 112 146 112 152 106" stroke="#CF8F6B" stroke-width="3" stroke-linecap="round"/>
          <path d="M104 168C108 145 124 130 140 130C156 130 172 145 176 168" fill="rgba(0,0,0,.22)" stroke="rgba(255,255,255,.12)" stroke-width="2" stroke-linecap="round"/>
          <path d="M118 150H162" stroke="#9CC9C4" stroke-width="4" stroke-linecap="round" opacity="0.85"/>
          <rect x="198" y="120" width="54" height="48" rx="12" fill="rgba(0,0,0,.22)" stroke="rgba(255,255,255,.12)" stroke-width="2"/>
          <path d="M211 120V110C211 100 219 92 229 92C239 92 247 100 247 110V120" stroke="#CF8F6B" stroke-width="4" stroke-linecap="round"/>
          <circle cx="229" cy="143" r="6" fill="#CF8F6B" opacity="0.9"/>
          <path d="M229 149V160" stroke="#CF8F6B" stroke-width="4" stroke-linecap="round" opacity="0.9"/>
          <path d="M60 202H220" stroke="rgba(255,255,255,.10)" stroke-width="8" stroke-linecap="round"/>
        </svg>
      </div>

      <div class="artNote">
        <strong><?= t('secure_title') ?></strong><br>
        <?= t('secure_text') ?>
      </div>
    </aside>
  </main>
</body>
</html>
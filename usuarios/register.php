<?php
declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . "/db.php";
require_once __DIR__ . "/../config/auth.php";

if (!function_exists('h')) {
  function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
  }
}

// =====================
// I18N
// =====================
$plLang = (string)($_GET['lang'] ?? $_SESSION['lang'] ?? 'es');
$plLang = ($plLang === 'en') ? 'en' : 'es';
$_SESSION['lang'] = $plLang;

$plNextLang = ($plLang === 'es') ? 'en' : 'es';
$plNextLangLabel = strtoupper($plNextLang);

$plDict = [
  'es' => [
    'page_title' => 'Registro | PILLAR Locums',
    'brand_tagline' => 'Locums • Supporting Care You Can Count On',
    'change_language' => 'Cambiar idioma',
    'dashboard' => 'Panel',
    'register' => 'Registro',
    'create_account_sub' => 'Crea tu cuenta en PILLAR Locums',
    'role' => 'Rol',
    'doctor' => 'Médico',
    'clinic' => 'Clínica',
    'hospital' => 'Hospital',
    'name' => 'Nombre',
    'email' => 'Email',
    'phone_optional' => 'Teléfono (opcional)',
    'location_optional' => 'Ubicación (opcional)',
    'password' => 'Contraseña',
    'repeat_password' => 'Repetir contraseña',
    'create_account' => 'Crear cuenta',
    'already_have_account' => '¿Ya tienes cuenta?',
    'login' => 'Inicia sesión',
    'back_home' => 'Volver al inicio',

    'invalid_role' => 'Rol no válido.',
    'name_required' => 'El nombre es obligatorio.',
    'invalid_email' => 'Email no válido.',
    'password_min' => 'La contraseña debe tener al menos 8 caracteres.',
    'password_mismatch' => 'Las contraseñas no coinciden.',
    'email_exists' => 'Ya existe un usuario con ese email.',
    'register_internal_error' => 'Error interno al registrar. Revisa el log del servidor.',
  ],
  'en' => [
    'page_title' => 'Register | PILLAR Locums',
    'brand_tagline' => 'Locums • Supporting Care You Can Count On',
    'change_language' => 'Change language',
    'dashboard' => 'Dashboard',
    'register' => 'Register',
    'create_account_sub' => 'Create your account on PILLAR Locums',
    'role' => 'Role',
    'doctor' => 'Doctor',
    'clinic' => 'Clinic',
    'hospital' => 'Hospital',
    'name' => 'Name',
    'email' => 'Email',
    'phone_optional' => 'Phone (optional)',
    'location_optional' => 'Location (optional)',
    'password' => 'Password',
    'repeat_password' => 'Repeat password',
    'create_account' => 'Create account',
    'already_have_account' => 'Already have an account?',
    'login' => 'Log in',
    'back_home' => 'Back to home',

    'invalid_role' => 'Invalid role.',
    'name_required' => 'Name is required.',
    'invalid_email' => 'Invalid email.',
    'password_min' => 'Password must be at least 8 characters long.',
    'password_mismatch' => 'Passwords do not match.',
    'email_exists' => 'A user with that email already exists.',
    'register_internal_error' => 'Internal error while registering. Check the server log.',
  ],
];

if (!function_exists('pl_t')) {
  function pl_t(string $key, ?string $fallback = null): string {
    global $plLang, $plDict;
    return $plDict[$plLang][$key] ?? ($fallback ?? $key);
  }
}

if (!function_exists('pl_lang_url')) {
  function pl_lang_url(string $lang): string {
    $params = $_GET;
    $params['lang'] = $lang;
    return '?' . http_build_query($params);
  }
}

$errors = [];

// Valores por defecto
$role = 'medico';
$name = '';
$email = '';
$phone = '';
$location = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $role = trim((string)($_POST['role'] ?? 'medico'));
  $name = trim((string)($_POST['name'] ?? ''));
  $email = trim((string)($_POST['email'] ?? ''));
  $phone = trim((string)($_POST['phone'] ?? ''));
  $location = trim((string)($_POST['location'] ?? ''));
  $password = (string)($_POST['password'] ?? '');
  $password2 = (string)($_POST['password2'] ?? '');

  $allowedRoles = ['medico', 'clinica', 'hospital'];
  if (!in_array($role, $allowedRoles, true)) $errors[] = pl_t('invalid_role');
  if ($name === '') $errors[] = pl_t('name_required');
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = pl_t('invalid_email');
  if (strlen($password) < 8) $errors[] = pl_t('password_min');
  if ($password !== $password2) $errors[] = pl_t('password_mismatch');

  if (!$errors) {
    try {
      $st = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
      $st->execute([$email]);

      if ($st->fetchColumn()) {
        $errors[] = pl_t('email_exists');
      } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $sql = "INSERT INTO users (role, name, email, phone, location, status, password_hash)
                VALUES (:role, :name, :email, :phone, :location, 'activo', :password_hash)";
        $ins = $pdo->prepare($sql);
        $ins->execute([
          ':role' => $role,
          ':name' => $name,
          ':email' => $email,
          ':phone' => ($phone !== '' ? $phone : null),
          ':location' => ($location !== '' ? $location : null),
          ':password_hash' => $hash,
        ]);

        $newId = (int)$pdo->lastInsertId();
        $userRow = [
          'id' => $newId,
          'role' => $role,
          'name' => $name,
          'email' => $email,
        ];
        login_user($userRow);

        header("Location: " . role_home($role));
        exit;
      }
    } catch (Throwable $e) {
      $errors[] = pl_t('register_internal_error');
    }
  }
}
?>
<!doctype html>
<html lang="<?= h($plLang) ?>">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title><?= h(pl_t('page_title')) ?></title>

  <style>
    :root{
      --pl-teal:#3D6D76;
      --pl-mint:#97C3BC;
      --pl-mist:#EEF5F8;
      --pl-blush:#F5DBD0;
      --pl-terracotta:#CE9379;
      --pl-charcoal:#464646;
      --pl-soft:#FFF1F1;
      --pl-line:rgba(61,109,118,.14);
      --pl-line-strong:rgba(61,109,118,.22);
      --pl-shadow:0 18px 45px rgba(61,109,118,.10);
      --pl-shadow-soft:0 10px 25px rgba(61,109,118,.08);
      --pl-radius:22px;
      --pl-radius-sm:16px;
    }

    *{ box-sizing:border-box; }

    html, body{
      margin:0;
      padding:0;
      min-height:100%;
    }

    body{
      font-family:"Galatea","Avenir Next","Montserrat","Segoe UI",sans-serif;
      background:
        radial-gradient(circle at top right, rgba(151,195,188,.22), transparent 26%),
        linear-gradient(180deg, #f7f8f8 0%, #f1f3f4 100%);
      color:var(--pl-charcoal);
      min-height:100vh;
    }

    a{
      color:inherit;
      text-decoration:none;
    }

    .topbar{
      position:sticky;
      top:0;
      z-index:100;
      backdrop-filter:blur(12px);
      background:rgba(247,248,248,.82);
      border-bottom:1px solid var(--pl-line);
    }

    .topbar .inner{
      width:min(1220px, calc(100% - 32px));
      margin:0 auto;
      min-height:84px;
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:18px;
      padding:14px 0;
    }

    .brand{
      display:flex;
      align-items:center;
      gap:14px;
      min-width:0;
    }

    .mark{
      width:58px;
      height:58px;
      border-radius:50%;
      display:grid;
      place-items:center;
      background:linear-gradient(135deg, var(--pl-teal), #497f89);
      color:#fff;
      font-weight:800;
      font-size:18px;
      letter-spacing:.14em;
      box-shadow:var(--pl-shadow-soft);
      border:1px solid rgba(255,255,255,.28);
      flex:0 0 auto;
    }

    .brandTitle{
      display:flex;
      flex-direction:column;
      justify-content:center;
      min-width:0;
    }

    .brandTitle strong{
      font-size:34px;
      line-height:1;
      letter-spacing:.34em;
      color:var(--pl-teal);
      font-weight:800;
    }

    .brandTitle span{
      font-size:14px;
      letter-spacing:.16em;
      color:rgba(61,109,118,.88);
      margin-top:6px;
    }

    .topActions{
      display:flex;
      align-items:center;
      gap:10px;
      flex-wrap:wrap;
      justify-content:flex-end;
    }

    .langBtn,
    .btn{
      appearance:none;
      outline:none;
      cursor:pointer;
      transition:.22s ease;
      font-family:inherit;
    }

    .langBtn:hover,
    .btn:hover{
      transform:translateY(-1px);
    }

    .langBtn{
      width:44px;
      height:44px;
      display:inline-flex;
      align-items:center;
      justify-content:center;
      border-radius:999px;
      background:linear-gradient(135deg, var(--pl-teal), var(--pl-mint));
      color:#fff;
      border:1px solid rgba(255,255,255,.28);
      box-shadow:0 10px 24px rgba(61,109,118,.18);
      position:relative;
      font-size:18px;
      overflow:hidden;
    }

    .langBtn .langCode{
      position:absolute;
      right:4px;
      bottom:3px;
      font-size:9px;
      line-height:1;
      font-weight:800;
      letter-spacing:.08em;
      background:rgba(255,255,255,.92);
      color:var(--pl-teal);
      padding:2px 4px;
      border-radius:999px;
    }

    .btn{
      display:inline-flex;
      align-items:center;
      justify-content:center;
      gap:8px;
      min-height:44px;
      padding:0 18px;
      border-radius:999px;
      font-weight:700;
      letter-spacing:.02em;
      box-shadow:none;
      border:1px solid transparent;
    }

    .btnGhost{
      background:rgba(255,255,255,.72);
      color:var(--pl-teal);
      border:1px solid rgba(61,109,118,.16);
    }

    .btnGhost:hover{
      background:#fff;
      border-color:rgba(61,109,118,.28);
    }

    .container{
      width:min(1220px, calc(100% - 32px));
      margin:34px auto 60px;
    }

    .authWrap{
      display:grid;
      place-items:center;
      min-height:calc(100vh - 160px);
    }

    .card{
      width:min(760px, 100%);
      background:rgba(255,255,255,.78);
      backdrop-filter:blur(12px);
      border:1px solid var(--pl-line);
      box-shadow:var(--pl-shadow);
      border-radius:30px;
      padding:28px;
    }

    .cardHead{
      margin-bottom:18px;
    }

    .cardHead h1{
      margin:0 0 6px;
      font-size:34px;
      line-height:1.05;
      color:var(--pl-teal);
      font-weight:800;
    }

    .sub{
      margin:0;
      color:rgba(70,70,70,.72);
      font-size:15px;
    }

    .err{
      margin: 0 0 16px;
      padding: 14px 16px;
      border-radius: 18px;
      background: rgba(206,147,121,.12);
      border: 1px solid rgba(206,147,121,.28);
      color: var(--pl-charcoal);
    }

    .err ul{
      margin:8px 0 0;
      padding-left:18px;
    }

    form{
      display:grid;
      grid-template-columns: 1fr 1fr;
      gap: 14px;
      align-items:start;
    }

    .full{
      grid-column: 1 / -1;
    }

    .field{
      display:flex;
      flex-direction:column;
      gap:8px;
      min-width:0;
    }

    label{
      display:block;
      font-size:13px;
      font-weight:800;
      color:var(--pl-teal);
      letter-spacing:.02em;
    }

    input, select{
      width:100%;
      min-height:48px;
      border-radius:15px;
      border:1px solid rgba(61,109,118,.16);
      background:#fff;
      padding:12px 14px;
      color:var(--pl-charcoal);
      font:inherit;
      outline:none;
      transition:.18s ease;
    }

    input:focus, select:focus{
      border-color:rgba(61,109,118,.34);
      box-shadow:0 0 0 4px rgba(151,195,188,.18);
    }

    .actions{
      grid-column: 1 / -1;
      display:flex;
      gap: 12px;
      align-items:center;
      justify-content:flex-start;
      margin-top: 4px;
      flex-wrap: wrap;
    }

    .submitBtn{
      display:inline-flex;
      align-items:center;
      justify-content:center;
      min-height:46px;
      padding:0 20px;
      border-radius:999px;
      border:1px solid rgba(206,147,121,.45);
      background:linear-gradient(135deg, var(--pl-terracotta), #d9a184);
      color:#fff;
      font-weight:800;
      box-shadow:0 10px 22px rgba(206,147,121,.20);
      cursor:pointer;
    }

    .submitBtn:hover{
      filter:brightness(1.02);
      transform:translateY(-1px);
    }

    .footer{
      margin-top: 18px;
      color:rgba(70,70,70,.78);
      line-height: 1.55;
    }

    .footer a{
      color:var(--pl-teal);
      font-weight:700;
    }

    @media (max-width: 760px){
      .topbar .inner{
        align-items:flex-start;
        flex-direction:column;
      }

      .brandTitle strong{
        font-size:26px;
        letter-spacing:.24em;
      }

      .brandTitle span{
        font-size:12px;
        letter-spacing:.08em;
      }

      .container{
        width:min(100% - 20px, 100%);
        margin:20px auto 48px;
      }

      .card{
        padding:20px;
        border-radius:24px;
      }

      .cardHead h1{
        font-size:28px;
      }

      form{
        grid-template-columns:1fr;
      }

      .submitBtn{
        width:100%;
      }
    }
  </style>
</head>
<body>
  <header class="topbar">
    <div class="inner">
      <a class="brand" href="/index.html">
        <div class="mark">PL</div>
        <div class="brandTitle">
          <strong>PILLAR</strong>
          <span><?= h(pl_t('brand_tagline')) ?></span>
        </div>
      </a>

      <div class="topActions">
        <a
          class="langBtn"
          href="<?= h(pl_lang_url($plNextLang)) ?>"
          title="<?= h(pl_t('change_language')) ?>"
          aria-label="<?= h(pl_t('change_language')) ?>"
        >
          🌐
          <span class="langCode"><?= h($plNextLangLabel) ?></span>
        </a>

        <a class="btn btnGhost" href="/usuarios/login.php?lang=<?= urlencode($plLang) ?>"><?= h(pl_t('login')) ?></a>
      </div>
    </div>
  </header>

  <main class="container">
    <div class="authWrap">
      <div class="card">
        <div class="cardHead">
          <h1><?= h(pl_t('register')) ?></h1>
          <p class="sub"><?= h(pl_t('create_account_sub')) ?></p>
        </div>

        <?php if ($errors): ?>
          <div class="err">
            <ul>
              <?php foreach ($errors as $e): ?>
                <li><?= h((string)$e) ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

        <form method="post" action="?lang=<?= h($plLang) ?>">
          <div class="field full">
            <label><?= h(pl_t('role')) ?></label>
            <select name="role" required>
              <option value="medico" <?= $role === 'medico' ? 'selected' : '' ?>><?= h(pl_t('doctor')) ?></option>
              <option value="clinica" <?= $role === 'clinica' ? 'selected' : '' ?>><?= h(pl_t('clinic')) ?></option>
              <option value="hospital" <?= $role === 'hospital' ? 'selected' : '' ?>><?= h(pl_t('hospital')) ?></option>
            </select>
          </div>

          <div class="field full">
            <label><?= h(pl_t('name')) ?></label>
            <input name="name" value="<?= h((string)$name) ?>" required>
          </div>

          <div class="field full">
            <label><?= h(pl_t('email')) ?></label>
            <input name="email" type="email" value="<?= h((string)$email) ?>" required>
          </div>

          <div class="field">
            <label><?= h(pl_t('phone_optional')) ?></label>
            <input name="phone" value="<?= h((string)$phone) ?>">
          </div>

          <div class="field">
            <label><?= h(pl_t('location_optional')) ?></label>
            <input name="location" value="<?= h((string)$location) ?>">
          </div>

          <div class="field">
            <label><?= h(pl_t('password')) ?></label>
            <input name="password" type="password" required>
          </div>

          <div class="field">
            <label><?= h(pl_t('repeat_password')) ?></label>
            <input name="password2" type="password" required>
          </div>

          <div class="actions">
            <button class="submitBtn" type="submit"><?= h(pl_t('create_account')) ?></button>
          </div>
        </form>

        <div class="footer">
          <?= h(pl_t('already_have_account')) ?> <a href="/usuarios/login.php?lang=<?= urlencode($plLang) ?>"><?= h(pl_t('login')) ?></a><br>
          <a href="/index.html"><?= h(pl_t('back_home')) ?></a>
        </div>
      </div>
    </div>
  </main>
</body>
</html>
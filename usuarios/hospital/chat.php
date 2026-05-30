<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . "/../../config/auth.php";
require_login();
require_once __DIR__ . "/../../config/db.php";

if (!function_exists('h')) {
    function h(string $s): string {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('table_exists')) {
    function table_exists(PDO $pdo, string $table): bool {
        try {
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) return false;
            $sql = "SHOW TABLES LIKE " . $pdo->quote($table);
            $st = $pdo->query($sql);
            return (bool)$st->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('table_columns')) {
    function table_columns(PDO $pdo, string $table): array {
        try {
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) return [];
            $cols = [];
            $st = $pdo->query("SHOW COLUMNS FROM `{$table}`");
            foreach (($st->fetchAll(PDO::FETCH_ASSOC) ?: []) as $r) {
                $cols[] = (string)($r['Field'] ?? '');
            }
            return array_values(array_filter($cols));
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('pick_col')) {
    function pick_col(array $cols, array $candidates): ?string {
        foreach ($candidates as $c) {
            if (in_array($c, $cols, true)) return $c;
        }
        return null;
    }
}

$user = $_SESSION['user'] ?? null;
$role = (string)($user['role'] ?? '');
if (!$user || !in_array($role, ['clinica', 'hospital'], true)) {
    header("Location: /usuarios/dashboard.php");
    exit;
}

$userId    = (int)($user['id'] ?? 0);
$userName  = (string)($user['name'] ?? 'Centro');
$jobId     = (int)($_GET['job_id'] ?? 0);
$doctorId  = (int)($_GET['doctor_id'] ?? 0);
$lang      = (string)($_SESSION['lang'] ?? 'es');
$lang      = in_array($lang, ['es', 'en'], true) ? $lang : 'es';
$langQS    = '&lang=' . urlencode($lang);

if ($jobId <= 0 || $doctorId <= 0) {
    exit("Parámetros incompletos");
}

try {
    $pdo = db();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Throwable $e) {
    exit("Error de base de datos.");
}

if (!table_exists($pdo, 'conversaciones')) {
    exit("No existe la tabla conversaciones.");
}
if (!table_exists($pdo, 'mensajes')) {
    exit("No existe la tabla mensajes.");
}

$convCols = table_columns($pdo, 'conversaciones');
$msgCols  = table_columns($pdo, 'mensajes');
$userCols = table_exists($pdo, 'users') ? table_columns($pdo, 'users') : [];
$jobCols  = table_exists($pdo, 'jobs') ? table_columns($pdo, 'jobs') : [];

$convIdCol      = pick_col($convCols, ['id']) ?? 'id';
$convJobCol     = pick_col($convCols, ['job_id']);
$convDoctorCol  = pick_col($convCols, ['doctor_id', 'medico_id', 'doctor_user_id', 'medico_user_id']);
$convOwnerCol   = null;

// Prioriza hospital si el usuario es hospital, pero acepta fallback por compatibilidad
if ($role === 'hospital') {
    $convOwnerCol = pick_col($convCols, ['hospital_id', 'hospital_user_id', 'clinica_id', 'clinic_id', 'clinic_user_id']);
} else {
    $convOwnerCol = pick_col($convCols, ['clinica_id', 'clinic_id', 'clinic_user_id', 'hospital_id', 'hospital_user_id']);
}

$msgConvCol     = pick_col($msgCols, ['conversation_id']);
$msgSenderCol   = pick_col($msgCols, ['sender_id']);
$msgReceiverCol = pick_col($msgCols, ['receiver_id']);
$msgBodyCol     = pick_col($msgCols, ['message', 'body', 'content', 'texto']);
$msgCreatedCol  = pick_col($msgCols, ['created_at', 'sent_at', 'created']);

$uNameCol       = $userCols ? (pick_col($userCols, ['name', 'full_name', 'display_name']) ?? 'name') : null;
$uRoleCol       = $userCols ? pick_col($userCols, ['role', 'user_role']) : null;
$jobTitleCol    = $jobCols ? pick_col($jobCols, ['title', 'name', 'job_title']) : null;

if (!$convDoctorCol || !$convOwnerCol || !$convJobCol || !$msgConvCol || !$msgSenderCol || !$msgBodyCol) {
    exit("Estructura de tablas incompleta.");
}

$convId = 0;
$doctorName = 'Médico';
$doctorRole = 'doctor';
$jobTitle = '';

try {
    // Buscar conversación existente
    $sqlConv = "SELECT * FROM `conversaciones`
                WHERE `{$convJobCol}` = :job_id
                  AND `{$convDoctorCol}` = :doctor_id
                  AND `{$convOwnerCol}` = :owner_id
                LIMIT 1";
    $stConv = $pdo->prepare($sqlConv);
    $stConv->execute([
        ':job_id'    => $jobId,
        ':doctor_id' => $doctorId,
        ':owner_id'  => $userId,
    ]);
    $conv = $stConv->fetch(PDO::FETCH_ASSOC);

    if ($conv) {
        $convId = (int)($conv[$convIdCol] ?? 0);
    } else {
        // Crear conversación si no existe
        $fields = [
            $convJobCol    => $jobId,
            $convDoctorCol => $doctorId,
            $convOwnerCol  => $userId,
        ];

        $colsSql = implode(',', array_map(fn($c) => "`{$c}`", array_keys($fields)));
        $valsSql = implode(',', array_fill(0, count($fields), '?'));

        $sqlIns = "INSERT INTO `conversaciones` ({$colsSql}) VALUES ({$valsSql})";
        $stIns = $pdo->prepare($sqlIns);
        $stIns->execute(array_values($fields));

        $convId = (int)$pdo->lastInsertId();
    }

    // Nombre del médico
    if ($uNameCol && table_exists($pdo, 'users')) {
        $sqlDoc = "SELECT `{$uNameCol}` AS name" . ($uRoleCol ? ", `{$uRoleCol}` AS role" : "") . " FROM `users` WHERE `id` = :id LIMIT 1";
        $stDoc = $pdo->prepare($sqlDoc);
        $stDoc->execute([':id' => $doctorId]);
        $doc = $stDoc->fetch(PDO::FETCH_ASSOC);
        if ($doc) {
            $doctorName = (string)($doc['name'] ?? $doctorName);
            $doctorRole = (string)($doc['role'] ?? $doctorRole);
        }
    }

    // Título de la oferta
    if ($jobTitleCol && table_exists($pdo, 'jobs')) {
        $sqlJob = "SELECT `{$jobTitleCol}` AS title FROM `jobs` WHERE `id` = :id LIMIT 1";
        $stJob = $pdo->prepare($sqlJob);
        $stJob->execute([':id' => $jobId]);
        $job = $stJob->fetch(PDO::FETCH_ASSOC);
        if ($job) {
            $jobTitle = (string)($job['title'] ?? '');
        }
    }

} catch (Throwable $e) {
    exit("No se pudo cargar la conversación.");
}

$mensajes = [];
try {
    $select = [
        "m.`{$msgSenderCol}` AS sender_id",
        "m.`{$msgBodyCol}` AS message"
    ];

    if ($msgCreatedCol) {
        $select[] = "m.`{$msgCreatedCol}` AS created_at";
    } else {
        $select[] = "NULL AS created_at";
    }

    $join = "";
    if ($uNameCol && table_exists($pdo, 'users')) {
        $select[] = "COALESCE(u.`{$uNameCol}`, '') AS sender_name";
        $join = "LEFT JOIN `users` u ON u.`id` = m.`{$msgSenderCol}`";
    } else {
        $select[] = "'' AS sender_name";
    }

    $orderCol = $msgCreatedCol ? "m.`{$msgCreatedCol}` ASC" : "m.`id` ASC";

    $sqlMsg = "SELECT " . implode(', ', $select) . "
               FROM `mensajes` m
               {$join}
               WHERE m.`{$msgConvCol}` = :cid
               ORDER BY {$orderCol}";
    $stMsg = $pdo->prepare($sqlMsg);
    $stMsg->execute([':cid' => $convId]);
    $mensajes = $stMsg->fetchAll(PDO::FETCH_ASSOC) ?: [];

} catch (Throwable $e) {
    $mensajes = [];
}

$panelBase = ($role === 'hospital') ? '/usuarios/hospital/index.php' : '/usuarios/clinica/index.php';
?>
<!doctype html>
<html lang="<?= h($lang) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= $role === 'hospital' ? 'Chat con médico | Hospital' : 'Chat con médico | Clínica' ?></title>

  <style>
    :root{
      --pl-teal:#3D6D76;
      --pl-mint:#97C3BC;
      --pl-mist:#EEF5F8;
      --pl-terracotta:#CE9379;
      --pl-charcoal:#464646;
      --pl-line:rgba(61,109,118,.14);
      --pl-shadow:0 18px 45px rgba(61,109,118,.10);
      --pl-white:#ffffff;
    }

    *{ box-sizing:border-box; }

    body{
      margin:0;
      font-family:"Segoe UI", Arial, sans-serif;
      background:linear-gradient(180deg,#f7f8f8 0%,#eef5f8 100%);
      color:var(--pl-charcoal);
    }

    .wrap{
      width:min(980px, calc(100% - 24px));
      margin:24px auto 40px;
    }

    .panel{
      background:var(--pl-white);
      border:1px solid var(--pl-line);
      border-radius:22px;
      box-shadow:var(--pl-shadow);
      padding:18px;
    }

    .top{
      display:flex;
      justify-content:space-between;
      align-items:flex-start;
      gap:12px;
      flex-wrap:wrap;
      margin-bottom:16px;
    }

    h1{
      margin:0;
      color:var(--pl-teal);
      font-size:28px;
    }

    .muted{
      color:#6e858a;
    }

    .btn{
      display:inline-flex;
      align-items:center;
      justify-content:center;
      min-height:42px;
      padding:0 16px;
      border-radius:999px;
      text-decoration:none;
      border:1px solid var(--pl-line);
      background:#fff;
      color:var(--pl-teal);
      font-weight:700;
      cursor:pointer;
    }

    .btnGold{
      background:linear-gradient(135deg,var(--pl-terracotta),#d9a184);
      color:#fff;
      border:none;
    }

    .chatBox{
      border:1px solid var(--pl-line);
      border-radius:18px;
      padding:14px;
      min-height:320px;
      max-height:520px;
      overflow-y:auto;
      background:#f9fbfc;
    }

    .msg{
      margin-bottom:12px;
      padding:14px;
      border-radius:18px;
      max-width:82%;
      white-space:pre-wrap;
      word-break:break-word;
    }

    .mine{
      margin-left:auto;
      background:rgba(151,195,188,.22);
    }

    .theirs{
      margin-right:auto;
      background:rgba(238,245,248,.92);
    }

    .meta{
      font-size:12px;
      color:#6e858a;
      margin-top:8px;
      text-align:right;
    }

    .send{
      display:flex;
      gap:10px;
      margin-top:14px;
      flex-wrap:wrap;
    }

    .input{
      flex:1;
      min-height:48px;
      border-radius:14px;
      border:1px solid var(--pl-line);
      padding:12px 14px;
      background:#fff;
      font:inherit;
    }

    @media (max-width:700px){
      .send{ flex-direction:column; }
      .btnGold, .input{ width:100%; }
      .msg{ max-width:100%; }
    }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="panel">
      <div class="top">
        <div>
          <h1>Chat</h1>
          <div class="muted">
            Conversación con <strong><?= h($doctorName) ?></strong>
            <?= $doctorRole !== '' ? ' • ' . h($doctorRole) : '' ?>
            <?= $jobId > 0 ? ' • Job ID: ' . (int)$jobId : '' ?>
            <?= $jobTitle !== '' ? ' • ' . h($jobTitle) : '' ?>
          </div>
        </div>

        <a class="btn" href="<?= h($panelBase) ?>?tab=aplicantes<?= h($langQS) ?>">Volver al panel</a>
      </div>

      <div class="chatBox" id="chatBox">
        <?php if (!$mensajes): ?>
          <div class="muted">No hay mensajes todavía.</div>
        <?php else: ?>
          <?php foreach ($mensajes as $m): ?>
            <?php $mine = ((int)($m['sender_id'] ?? 0) === $userId); ?>
            <div class="msg <?= $mine ? 'mine' : 'theirs' ?>">
              <strong><?= h((string)($m['sender_name'] ?? ($mine ? $userName : $doctorName))) ?>:</strong>
              <?= nl2br(h((string)($m['message'] ?? ''))) ?>
              <div class="meta"><?= h((string)($m['created_at'] ?? '')) ?></div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <form method="POST" action="enviar_mensaje.php" class="send">
        <input type="hidden" name="conversation_id" value="<?= (int)$convId ?>">
        <input type="hidden" name="receiver_id" value="<?= (int)$doctorId ?>">
        <input type="hidden" name="job_id" value="<?= (int)$jobId ?>">
        <input class="input" type="text" name="message" placeholder="Escribe tu mensaje..." required>
        <button type="submit" class="btn btnGold">Enviar</button>
      </form>
    </div>
  </div>

  <script>
    (function(){
      var box = document.getElementById('chatBox');
      if (box) box.scrollTop = box.scrollHeight;
    })();
  </script>
</body>
</html>
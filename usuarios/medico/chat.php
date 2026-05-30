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
            if ($st && $st->fetchColumn()) return true;

            $stAll = $pdo->query("SHOW TABLES");
            $rows = $stAll ? $stAll->fetchAll(PDO::FETCH_NUM) : [];
            foreach ($rows as $r) {
                if (isset($r[0]) && strtolower((string)$r[0]) === strtolower($table)) {
                    return true;
                }
            }
            return false;
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
if (!$user || !in_array($role, ['medico', 'doctor'], true)) {
    header("Location: /usuarios/dashboard.php");
    exit;
}

$doctorId = (int)($user['id'] ?? 0);
$conversationId = (int)($_GET['conversation_id'] ?? ($_POST['conversation_id'] ?? 0));

$flashOk = '';
$flashErr = '';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = (string)$_SESSION['csrf_token'];

$lang = (string)($_SESSION['lang'] ?? 'es');
if (!in_array($lang, ['es', 'en'], true)) $lang = 'es';
$langQS = '&lang=' . urlencode($lang);

/* ============================================================
   Conexión PDO robusta
============================================================ */
$pdo = null;

try {
    if (function_exists('db')) {
        $pdo = db();
    } elseif (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
        $pdo = $GLOBALS['pdo'];
    }

    if (!$pdo instanceof PDO) {
        exit("Error de base de datos.");
    }

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Throwable $e) {
    exit("Error de base de datos.");
}

/* ============================================================
   Validación de tablas reales
============================================================ */
if (!table_exists($pdo, 'conversaciones')) {
    exit("No existe la tabla conversaciones.");
}
if (!table_exists($pdo, 'mensajes')) {
    exit("No existe la tabla mensajes.");
}

$convCols = table_columns($pdo, 'conversaciones');
$msgCols  = table_columns($pdo, 'mensajes');
$userCols = table_exists($pdo, 'users') ? table_columns($pdo, 'users') : [];

$convIdCol       = pick_col($convCols, ['id']) ?? 'id';
$convJobCol      = pick_col($convCols, ['job_id']);
$convDoctorCol   = pick_col($convCols, ['doctor_id', 'medico_id', 'doctor_user_id', 'medico_user_id']);
$convClinicCol   = pick_col($convCols, ['clinica_id', 'clinic_id', 'clinic_user_id']);
$convHospitalCol = pick_col($convCols, ['hospital_id', 'hospital_user_id']);

$msgIdCol        = pick_col($msgCols, ['id']) ?? 'id';
$msgConvCol      = pick_col($msgCols, ['conversation_id']);
$msgSenderCol    = pick_col($msgCols, ['sender_id']);
$msgReceiverCol  = pick_col($msgCols, ['receiver_id']);
$msgBodyCol      = pick_col($msgCols, ['message', 'body', 'content', 'texto']);
$msgCreatedCol   = pick_col($msgCols, ['created_at', 'sent_at', 'created']);

$uNameCol        = $userCols ? (pick_col($userCols, ['name', 'full_name', 'display_name']) ?? 'name') : null;
$uRoleCol        = $userCols ? pick_col($userCols, ['role', 'user_role']) : null;

if ($conversationId <= 0 || !$convDoctorCol || !$msgConvCol || !$msgSenderCol || !$msgBodyCol) {
    exit("Parámetros o estructura incompleta.");
}

/* ============================================================
   Cargar conversación
============================================================ */
$conversation = null;
$counterpartId = 0;
$counterpartType = '';
$counterpartName = 'Contacto';
$counterpartRole = '';
$jobId = 0;

try {
    $sql = "SELECT * FROM `conversaciones` WHERE `{$convIdCol}` = :cid AND `{$convDoctorCol}` = :did LIMIT 1";
    $st = $pdo->prepare($sql);
    $st->execute([
        ':cid' => $conversationId,
        ':did' => $doctorId
    ]);
    $conversation = $st->fetch(PDO::FETCH_ASSOC) ?: null;

    if (!$conversation) {
        exit("Conversación no encontrada o no autorizada.");
    }

    $jobId = $convJobCol ? (int)($conversation[$convJobCol] ?? 0) : 0;

    if ($convClinicCol && !empty($conversation[$convClinicCol])) {
        $counterpartId = (int)$conversation[$convClinicCol];
        $counterpartType = 'clinica';
    } elseif ($convHospitalCol && !empty($conversation[$convHospitalCol])) {
        $counterpartId = (int)$conversation[$convHospitalCol];
        $counterpartType = 'hospital';
    }

    if ($counterpartId > 0 && table_exists($pdo, 'users') && $uNameCol) {
        $sqlU = "SELECT `{$uNameCol}` AS name" . ($uRoleCol ? ", `{$uRoleCol}` AS role" : "") . " FROM `users` WHERE `id` = :id LIMIT 1";
        $stU = $pdo->prepare($sqlU);
        $stU->execute([':id' => $counterpartId]);
        $u = $stU->fetch(PDO::FETCH_ASSOC);

        if ($u) {
            $counterpartName = (string)($u['name'] ?? $counterpartName);
            $counterpartRole = (string)($u['role'] ?? '');
        }
    } else {
        $counterpartName = ($counterpartType === 'hospital') ? 'Hospital' : 'Clínica';
    }
} catch (Throwable $e) {
    exit("No se pudo cargar la conversación.");
}

/* ============================================================
   Enviar mensaje
============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedCsrf = (string)($_POST['csrf_token'] ?? '');
    $message = trim((string)($_POST['message'] ?? ''));

    if (!hash_equals($csrf, $postedCsrf)) {
        $flashErr = "Sesión inválida. Recarga la página.";
    } elseif ($message === '') {
        $flashErr = "Escribe un mensaje.";
    } elseif (mb_strlen($message) > 3000) {
        $flashErr = "El mensaje es demasiado largo.";
    } else {
        try {
            $fields = [
                $msgConvCol   => $conversationId,
                $msgSenderCol => $doctorId,
                $msgBodyCol   => $message,
            ];

            if ($msgReceiverCol && $counterpartId > 0) {
                $fields[$msgReceiverCol] = $counterpartId;
            }

            $colsSql = [];
            $valsSql = [];
            $params  = [];

            foreach ($fields as $col => $val) {
                $colsSql[] = "`{$col}`";
                $valsSql[] = '?';
                $params[]  = $val;
            }

            if ($msgCreatedCol) {
                $colsSql[] = "`{$msgCreatedCol}`";
                $valsSql[] = "NOW()";
            }

            $sqlI = "INSERT INTO `mensajes` (" . implode(',', $colsSql) . ") VALUES (" . implode(',', $valsSql) . ")";
            $stI = $pdo->prepare($sqlI);
            $stI->execute($params);

            header("Location: ?conversation_id=" . $conversationId . $langQS);
            exit;
        } catch (Throwable $e) {
            $flashErr = "No se pudo enviar el mensaje.";
        }
    }
}

/* ============================================================
   Cargar mensajes
============================================================ */
$messages = [];

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

    if ($uNameCol) {
        $select[] = "COALESCE(u.`{$uNameCol}`, '') AS sender_name";
        $join = " LEFT JOIN `users` u ON u.`id` = m.`{$msgSenderCol}` ";
    } else {
        $select[] = "'' AS sender_name";
        $join = "";
    }

    $orderCol = $msgCreatedCol ? "m.`{$msgCreatedCol}` ASC" : "m.`{$msgIdCol}` ASC";

    $sqlM = "SELECT " . implode(', ', $select) . "
             FROM `mensajes` m
             {$join}
             WHERE m.`{$msgConvCol}` = :cid
             ORDER BY {$orderCol}";
    $stM = $pdo->prepare($sqlM);
    $stM->execute([':cid' => $conversationId]);
    $messages = $stM->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    $flashErr = "No se pudieron cargar los mensajes.";
}
?>
<!doctype html>
<html lang="<?= h($lang) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Chat médico | PILLAR Locums</title>
    <style>
        :root{
            --pl-teal:#3D6D76;
            --pl-mint:#97C3BC;
            --pl-mist:#EEF5F8;
            --pl-terracotta:#CE9379;
            --pl-charcoal:#464646;
            --pl-line:rgba(61,109,118,.14);
            --pl-shadow:0 18px 45px rgba(61,109,118,.10);
        }
        *{box-sizing:border-box}
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
            background:#fff;
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
        .muted{color:#6e858a}
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
        .flashOk,.flashErr{
            padding:12px 14px;
            border-radius:14px;
            margin-bottom:12px;
            background:#fff;
            border:1px solid var(--pl-line);
        }
        .flashErr{border-color:rgba(206,147,121,.35)}
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
            padding:12px;
            border-radius:16px;
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
            margin-top:6px;
            text-align:right;
        }
        form.send{
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
            form.send{flex-direction:column}
            .btnGold,.input{width:100%}
            .msg{max-width:100%}
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
                        Conversación con <strong><?= h($counterpartName) ?></strong>
                        <?= $counterpartRole !== '' ? ' • ' . h($counterpartRole) : '' ?>
                        <?= $jobId > 0 ? ' • Job ID: ' . (int)$jobId : '' ?>
                    </div>
                </div>
                <a class="btn" href="/usuarios/medico/index.php?tab=contactos<?= h($langQS) ?>">Volver a contactos</a>
            </div>

            <?php if ($flashOk): ?>
                <div class="flashOk"><?= h($flashOk) ?></div>
            <?php endif; ?>

            <?php if ($flashErr): ?>
                <div class="flashErr"><?= h($flashErr) ?></div>
            <?php endif; ?>

            <div class="chatBox" id="chatBox">
                <?php if (!$messages): ?>
                    <div class="muted">No hay mensajes todavía.</div>
                <?php else: ?>
                    <?php foreach ($messages as $m): ?>
                        <?php $mine = ((int)($m['sender_id'] ?? 0) === $doctorId); ?>
                        <div class="msg <?= $mine ? 'mine' : 'theirs' ?>">
                            <strong><?= h((string)($m['sender_name'] ?? ($mine ? 'Tú' : $counterpartName))) ?>:</strong>
                            <?= nl2br(h((string)($m['message'] ?? ''))) ?>
                            <div class="meta"><?= h((string)($m['created_at'] ?? '')) ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <form method="post" class="send">
                <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                <input type="hidden" name="conversation_id" value="<?= (int)$conversationId ?>">
                <input class="input" type="text" name="message" placeholder="Escribe tu mensaje..." required>
                <button class="btn btnGold" type="submit">Enviar</button>
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
<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . "/../../config/auth.php";
require_login();
require_once __DIR__ . "/../../config/db.php";

if (!function_exists('table_exists')) {
    function table_exists(PDO $pdo, string $table): bool {
        try {
            $stmt = $pdo->prepare("SHOW TABLES LIKE :t");
            $stmt->execute([':t' => $table]);
            return (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('table_columns')) {
    function table_columns(PDO $pdo, string $table): array {
        try {
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

$conversationId = (int)($_POST['conversation_id'] ?? 0);
$message        = trim((string)($_POST['message'] ?? ''));
$senderId       = (int)($_SESSION['user']['id'] ?? 0);
$userRole       = (string)($_SESSION['user']['role'] ?? '');

if ($conversationId <= 0 || $senderId <= 0 || $message === '') {
    exit("Datos incompletos");
}

try {
    $pdo = db();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if (!table_exists($pdo, 'conversaciones')) {
        exit("No existe la tabla conversaciones.");
    }
    if (!table_exists($pdo, 'mensajes')) {
        exit("No existe la tabla mensajes.");
    }

    $convCols = table_columns($pdo, 'conversaciones');
    $msgCols  = table_columns($pdo, 'mensajes');

    $convIdCol       = pick_col($convCols, ['id']) ?? 'id';
    $convDoctorCol   = pick_col($convCols, ['doctor_id', 'medico_id', 'doctor_user_id', 'medico_user_id']);
    $convClinicCol   = pick_col($convCols, ['clinica_id', 'clinic_id', 'clinic_user_id']);
    $convHospitalCol = pick_col($convCols, ['hospital_id', 'hospital_user_id']);
    $convJobCol      = pick_col($convCols, ['job_id']);

    $msgConvCol      = pick_col($msgCols, ['conversation_id']);
    $msgSenderCol    = pick_col($msgCols, ['sender_id']);
    $msgReceiverCol  = pick_col($msgCols, ['receiver_id']);
    $msgBodyCol      = pick_col($msgCols, ['message', 'body', 'content', 'texto']);
    $msgCreatedCol   = pick_col($msgCols, ['created_at', 'sent_at', 'created']);

    if (!$convDoctorCol || !$msgConvCol || !$msgSenderCol || !$msgBodyCol) {
        exit("Estructura de tablas incompleta.");
    }

    // Cargar conversación real
    $sqlConv = "SELECT * FROM `conversaciones` WHERE `{$convIdCol}` = :cid LIMIT 1";
    $stConv = $pdo->prepare($sqlConv);
    $stConv->execute([':cid' => $conversationId]);
    $conv = $stConv->fetch(PDO::FETCH_ASSOC);

    if (!$conv) {
        exit("La conversación no existe.");
    }

    $doctorId   = (int)($conv[$convDoctorCol] ?? 0);
    $clinicId   = $convClinicCol ? (int)($conv[$convClinicCol] ?? 0) : 0;
    $hospitalId = $convHospitalCol ? (int)($conv[$convHospitalCol] ?? 0) : 0;
    $jobId      = $convJobCol ? (int)($conv[$convJobCol] ?? 0) : 0;

    // Validar que el usuario pertenece a la conversación
    $authorized = false;
    $receiverId = 0;
    $redirect   = '';

    if (in_array($userRole, ['medico', 'doctor'], true)) {
        if ($senderId === $doctorId) {
            $authorized = true;
            $receiverId = $clinicId > 0 ? $clinicId : $hospitalId;
            $redirect = "/usuarios/medico/chat.php?conversation_id=" . $conversationId;
        }
    } elseif ($userRole === 'clinica') {
        if ($clinicId > 0 && $senderId === $clinicId) {
            $authorized = true;
            $receiverId = $doctorId;
            $redirect = "/usuarios/clinica/chat.php?job_id=" . $jobId . "&doctor_id=" . $doctorId;
        }
    } elseif ($userRole === 'hospital') {
        if ($hospitalId > 0 && $senderId === $hospitalId) {
            $authorized = true;
            $receiverId = $doctorId;
            $redirect = "/usuarios/hospital/chat.php?job_id=" . $jobId . "&doctor_id=" . $doctorId;
        }
    }

    if (!$authorized) {
        exit("No autorizado para esta conversación.");
    }

    if ($receiverId <= 0) {
        exit("No se pudo determinar el destinatario.");
    }

    // Insertar mensaje
    $fields = [
        $msgConvCol   => $conversationId,
        $msgSenderCol => $senderId,
        $msgBodyCol   => $message,
    ];

    if ($msgReceiverCol) {
        $fields[$msgReceiverCol] = $receiverId;
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

    $sqlIns = "INSERT INTO `mensajes` (" . implode(',', $colsSql) . ") VALUES (" . implode(',', $valsSql) . ")";
    $stIns = $pdo->prepare($sqlIns);
    $stIns->execute($params);

    header("Location: " . $redirect);
    exit;

} catch (PDOException $e) {
    exit("Error de base de datos: " . $e->getMessage());
} catch (Throwable $e) {
    exit("Error: " . $e->getMessage());
}
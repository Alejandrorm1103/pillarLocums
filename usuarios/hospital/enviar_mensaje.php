<?php
session_start();
require_once __DIR__ . "/../../config/auth.php";
require_login();
require_once __DIR__ . "/../../config/db.php";

$conversation_id = $_POST['conversation_id'] ?? null;
$receiver_id     = $_POST['receiver_id'] ?? null;
$job_id          = $_POST['job_id'] ?? null;
$message         = trim($_POST['message'] ?? '');
$sender_id       = $_SESSION['user']['id'] ?? null;

if (!$conversation_id || !$receiver_id || !$job_id || $message === '') {
    exit("Datos incompletos");
}

$pdo = db();

try {
    $sql = "INSERT INTO mensajes (conversation_id, sender_id, receiver_id, message, created_at)
            VALUES (:conv_id, :sender_id, :receiver_id, :message, NOW())";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':conv_id'    => $conversation_id,
        ':sender_id'  => $sender_id,
        ':receiver_id'=> $receiver_id,
        ':message'    => $message
    ]);

    // Redirigir al chat del mismo turno
    header("Location: chat.php?job_id=" . $job_id . "&doctor_id=" . $receiver_id);
    exit;

} catch (PDOException $e) {
    exit("Error de base de datos: " . $e->getMessage());
}
<?php

session_start();
require_once __DIR__ . "/../../config/auth.php";
require_login();
require_once __DIR__ . "/../../config/db.php";

// validar POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit("Acceso no permitido");
}

$application_id = $_POST['application_id'] ?? null; // id de job_applications
$doctor_id      = $_POST['doctor_id'] ?? null;

if (!$application_id || !$doctor_id) {
    exit("Datos incompletos");
}

// id de la clínica/hospital desde la sesión
$clinica_id = $_SESSION['user']['id'] ?? null;
if (!$clinica_id) {
    exit("Sesión inválida");
}

// obtener PDO
$pdo = db();

try {
    // ----------- ACTUALIZAR EL ESTADO DE LA APLICACIÓN -----------
    $sql = "UPDATE job_applications 
            SET status = 'aceptado' 
            WHERE id = :application_id
              AND doctor_user_id = :doctor_id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':application_id', $application_id, PDO::PARAM_INT);
    $stmt->bindParam(':doctor_id', $doctor_id, PDO::PARAM_INT);
    $stmt->execute();

    // ----------- CREAR CONVERSACIÓN -----------
    $sql2 = "INSERT INTO conversaciones (job_id, doctor_id, clinica_id)
             SELECT job_id, doctor_user_id, :clinica_id
             FROM job_applications
             WHERE id = :application_id
             LIMIT 1";
    $stmt2 = $pdo->prepare($sql2);
    $stmt2->bindParam(':clinica_id', $clinica_id, PDO::PARAM_INT);
    $stmt2->bindParam(':application_id', $application_id, PDO::PARAM_INT);
    $stmt2->execute();

    // ----------- REDIRECCIÓN -----------
    header("Location: aplicantes.php?success=1");
    exit;

} catch (PDOException $e) {
    exit("Error en base de datos: " . $e->getMessage());
}
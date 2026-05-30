<?php
declare(strict_types=1);

/**
 * /config/db.php  (DB CENTRAL ÚNICO)
 * - Exporta: $pdo (compatibilidad con paneles existentes)
 * - Exporta: db(): PDO (compatibilidad con admin y nuevos módulos)
 */

$DB_HOST = 'localhost';
$DB_PORT = '3306';
$DB_NAME = 'u941754996_PILLARLocums';
$DB_USER = 'u941754996_PILLARLocums';
$DB_PASS = 'PILLARLocums2026';

function db(): PDO {
  static $pdo = null;
  if ($pdo instanceof PDO) return $pdo;

  global $DB_HOST, $DB_PORT, $DB_NAME, $DB_USER, $DB_PASS;

  $dsn = "mysql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME};charset=utf8mb4";

  $options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
  ];

  try {
    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, $options);
    return $pdo;
  } catch (Throwable $e) {
    error_log("DB CONNECTION ERROR: " . $e->getMessage());
    http_response_code(500);
    exit("DB CONNECTION ERROR");
  }
}

// ✅ Compatibilidad: si tu código antiguo usa $pdo directamente
$pdo = db();
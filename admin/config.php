<?php
/**
 * config.php
 * - Configuración central del panel.
 * - NUNCA pongas contraseñas en frontend; aquí se guarda un HASH.
 */

declare(strict_types=1);

define('APP_NAME', 'PILLAR Locums Admin');
define('BASE_URL', '/admin'); // ajusta si tu admin vive en otra ruta

// ======= DB =======
// Coloca aquí tus datos reales de Hostinger
define('DB_HOST', 'localhost'); // si no conecta, prueba '127.0.0.1'
define('DB_NAME', 'u253081188_PILLARLocums');
define('DB_USER', 'u253081188_PILLARLocums');
define('DB_PASS', 'PILLARLocums2026');
define('DB_CHARSET', 'utf8mb4');


// ======= ADMIN PASSWORD =======
// Contraseña solicitada: "PILLAR Locums2026@123"
// Genera el hash 1 sola vez y pégalo aquí.
// Puedes generar hash temporal con /admin/login.php?gen=1 (ver login.php)
define('ADMIN_PASSWORD_HASH', '$2y$10$kEQR/ubDmMo79iZl03Yzgu6N9Q3jhguCAtwk8.PAHW80xzLg3AaEO');

// ======= MEDIA JSON =======
define('MEDIA_JSON_PATH', dirname(__DIR__) . '/assets/media.json');
define('UPLOAD_CAROUSEL_DIR', dirname(__DIR__) . '/assets/uploads/carousel/');
define('UPLOAD_VIDEO_DIR', dirname(__DIR__) . '/assets/uploads/video/');

// Seguridad básica de sesión
define('SESSION_NAME', 'PILLAR_ADMIN_SESS');
define('ALLOW_HASH_GEN', false);
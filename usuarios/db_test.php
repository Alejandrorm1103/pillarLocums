<?php
declare(strict_types=1);
ini_set('display_errors','1');
ini_set('display_startup_errors','1');
error_reporting(E_ALL);

echo "<pre>PHP OK\n</pre>";

$db1 = __DIR__ . "/db.php";
$db2 = __DIR__ . "/../config/db.php";

echo "<pre>Buscando db.php en:\n- $db1\n- $db2\n</pre>";

if (file_exists($db1)) {
  require_once $db1;
  echo "<pre>Cargado: usuarios/db.php\n</pre>";
} elseif (file_exists($db2)) {
  require_once $db2;
  echo "<pre>Cargado: config/db.php\n</pre>";
} else {
  exit("<pre>ERROR: No se encontró db.php en ninguno de los dos caminos.</pre>");
}

echo "<pre>Existe \$pdo? " . (isset($pdo) ? "SI" : "NO") . "</pre>";

if (!isset($pdo) || !($pdo instanceof PDO)) {
  exit("<pre>ERROR: db.php NO definió \$pdo como instancia PDO.</pre>");
}

$stmt = $pdo->query("SELECT NOW() AS now_time");
$row = $stmt->fetch(PDO::FETCH_ASSOC);

echo "<pre>DB OK: " . print_r($row, true) . "</pre>";

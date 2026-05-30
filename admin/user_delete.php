<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

require_login();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  exit('Método no permitido');
}

verify_csrf();

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
  http_response_code(400);
  exit('ID inválido');
}

// (Opcional) Protege IDs críticos (ajústalo si quieres)
// Por ejemplo, si el usuario 1 es "sistema" o reservado:
$protectedIds = [1];
if (in_array($id, $protectedIds, true)) {
  http_response_code(403);
  exit('No se puede eliminar este usuario.');
}

// Verifica existencia antes de borrar
$check = $pdo->prepare("SELECT id, role FROM users WHERE id=? LIMIT 1");
$check->execute([$id]);
$row = $check->fetch();

if (!$row) {
  // No existe: vuelve a usuarios sin romper UX
  header('Location: ' . BASE_URL . '/users.php');
  exit;
}

// (Opcional futuro) si algún día guardas admins en users:
// if (in_array((string)$row['role'], ['admin','superadmin'], true)) { ... }

$stmt = $pdo->prepare("DELETE FROM users WHERE id=?");
$stmt->execute([$id]);

header('Location: ' . BASE_URL . '/users.php');
exit;
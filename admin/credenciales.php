<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

require_login();
$pdo = db();

// Helpers
if (!function_exists('h')) {
  function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
}

$debug = isset($_GET['debug']) && $_GET['debug'] === '1';
if ($debug) {
  $debugDb = $pdo->query("SELECT DATABASE()")->fetchColumn();
  echo "<div style='padding:8px;background:#fff3cd;border:1px solid #ffeeba;margin:10px 0;'>ADMIN DB: <b>" . h((string)$debugDb) . "</b></div>";
}

$csrf = csrf_token();

$medicoId = (int)($_GET['medico_id'] ?? 0);

$msg = '';
$err = '';

/**
 * Acciones: aprobar / rechazar
 * - approved => medico_profile.is_active=1
 * - rejected => medico_profile.is_active=0
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  verify_csrf();

  $action = (string)($_POST['action'] ?? '');
  $mid    = (int)($_POST['medico_id'] ?? 0);
  $note   = trim((string)($_POST['note'] ?? ''));

  if ($mid <= 0) {
    $err = 'Médico inválido.';
  } elseif (!in_array($action, ['approve', 'reject'], true)) {
    $err = 'Acción inválida.';
  } else {
    $newStatus = ($action === 'approve') ? 'approved' : 'rejected';
    $newActive = ($action === 'approve') ? 1 : 0;

    try {
      $pdo->beginTransaction();

      // 1) Estado global del médico (verificación)
      $stmt = $pdo->prepare("
        INSERT INTO medico_verifications (medico_id, status, note, updated_at)
        VALUES (:id, :st, :note, NOW())
        ON DUPLICATE KEY UPDATE status = VALUES(status), note = VALUES(note), updated_at = NOW()
      ");
      $stmt->execute([
        ':id'   => $mid,
        ':st'   => $newStatus,
        ':note' => ($note !== '' ? $note : null),
      ]);

      // 2) ✅ ACTIVO/DESACTIVO (esto es lo que faltaba)
      // Si la fila no existe, la crea (PK por medico_id)
      $stmtA = $pdo->prepare("
        INSERT INTO medico_profile (medico_id, is_active, updated_at)
        VALUES (:id, :active, NOW())
        ON DUPLICATE KEY UPDATE is_active = VALUES(is_active), updated_at = NOW()
      ");
      $stmtA->execute([
        ':id' => $mid,
        ':active' => $newActive,
      ]);

      // 3) (Opcional) marcar documentos pendientes con el mismo estado
      $stmt2 = $pdo->prepare("
        UPDATE medico_documents
        SET status = :st
        WHERE medico_id = :id AND status = 'pending'
      ");
      $stmt2->execute([':st' => $newStatus, ':id' => $mid]);

      $pdo->commit();

      $msg = ($newStatus === 'approved')
        ? 'Médico aprobado y activado.'
        : 'Médico rechazado y desactivado.';

      // PRG: vuelve al mismo médico y evita re-POST
      $qs = [
        'medico_id' => $mid,
        'ok' => 1,
      ];
      if ($debug) $qs['debug'] = 1;

      header("Location: " . BASE_URL . "/credenciales.php?" . http_build_query($qs));
      exit;

    } catch (Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      error_log("credenciales.php update error: " . $e->getMessage());
      $err = 'No se pudo actualizar el estado.';
    }
  }
}

// Mensaje PRG
if (isset($_GET['ok']) && $_GET['ok'] === '1' && $msg === '') {
  $msg = 'Cambios guardados.';
}

// Listado de médicos con su estado + activo
$medicos = [];
try {
  $medicos = $pdo->query("
    SELECT u.id, u.name, u.email,
           COALESCE(v.status, 'pending') AS v_status,
           COALESCE(p.is_active, 0) AS is_active,
           v.updated_at
    FROM users u
    LEFT JOIN medico_verifications v ON v.medico_id = u.id
    LEFT JOIN medico_profile p ON p.medico_id = u.id
    WHERE u.role = 'medico'
    ORDER BY
      (COALESCE(v.status, 'pending') = 'pending') DESC,
      u.name ASC
  ")->fetchAll();
} catch (Throwable $e) {
  error_log("credenciales.php list error: " . $e->getMessage());
  $err = $err ?: 'No se pudo cargar el listado de médicos.';
}

// Detalle: documentos del médico seleccionado
$docs = [];
$medico = null;
$activeFlag = 0;
$verFlag = 'pending';

if ($medicoId > 0) {
  try {
    $st = $pdo->prepare("
      SELECT u.id, u.name, u.email,
             COALESCE(v.status,'pending') AS v_status,
             COALESCE(p.is_active,0) AS is_active,
             COALESCE(v.note,'') AS note
      FROM users u
      LEFT JOIN medico_verifications v ON v.medico_id = u.id
      LEFT JOIN medico_profile p ON p.medico_id = u.id
      WHERE u.id=? AND u.role='medico'
      LIMIT 1
    ");
    $st->execute([$medicoId]);
    $medico = $st->fetch() ?: null;

    if ($medico) {
      $activeFlag = (int)($medico['is_active'] ?? 0);
      $verFlag = strtolower((string)($medico['v_status'] ?? 'pending'));
    }

    $st2 = $pdo->prepare("
      SELECT id, doc_type, original_name, file_path, mime_type, status, created_at
      FROM medico_documents
      WHERE medico_id = ?
      ORDER BY created_at DESC
      LIMIT 100
    ");
    $st2->execute([$medicoId]);
    $docs = $st2->fetchAll() ?: [];
  } catch (Throwable $e) {
    error_log("credenciales.php detail error: " . $e->getMessage());
    $err = $err ?: 'No se pudo cargar el detalle del médico.';
  }
}

function badgeClass(string $st): string {
  $s = strtolower(trim($st));
  return match ($s) {
    'approved' => 'badgeOk',
    'rejected' => 'badgeBad',
    default => 'note',
  };
}
function badgeText(string $st): string {
  $s = strtolower(trim($st));
  return match ($s) {
    'approved' => 'Aprobado',
    'rejected' => 'Rechazado',
    default => 'Pendiente',
  };
}

?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= h(APP_NAME) ?> | Credenciales</title>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/admin.css">
</head>
<body>
  <div class="container">
    <div class="topbar">
      <div class="brand">
        <strong>PILLAR</strong>
        <span>Credenciales</span>
      </div>

      <nav class="nav">
        <a href="<?= BASE_URL ?>/index.php">Dashboard</a>
        <a href="<?= BASE_URL ?>/users.php">Usuarios</a>
        <a href="<?= BASE_URL ?>/calendar.php">Calendario</a>
        <a href="<?= BASE_URL ?>/media.php">Media</a>
        <a class="active" href="<?= BASE_URL ?>/credenciales.php">Credenciales</a>
      </nav>

      <div class="actions">
        <a class="btn" href="<?= BASE_URL ?>/logout.php">Salir</a>
      </div>
    </div>

    <?php if ($msg): ?>
      <div class="card" style="border-radius:12px;border-color:rgba(34,197,94,.35);margin:12px 0;">
        <div class="bd" style="background:rgba(34,197,94,.10);color:#14532d;"><?= h($msg) ?></div>
      </div>
    <?php endif; ?>
    <?php if ($err): ?>
      <div class="card" style="border-radius:12px;border-color:rgba(239,68,68,.35);margin:12px 0;">
        <div class="bd" style="background:rgba(239,68,68,.10);color:#991b1b;"><?= h($err) ?></div>
      </div>
    <?php endif; ?>

    <div class="grid" style="grid-template-columns: 1fr 1.2fr; gap:14px;">
      <!-- Lista médicos -->
      <section class="card">
        <div class="hd">
          <div>
            <h2>Médicos</h2>
            <p>Revisión de credenciales</p>
          </div>
        </div>
        <div class="bd">
          <table class="table">
            <thead>
              <tr>
                <th>ID</th><th>Nombre</th><th>Email</th><th>Verif</th><th>Activo</th><th></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($medicos as $m): ?>
                <tr>
                  <td><?= (int)$m['id'] ?></td>
                  <td><?= h((string)$m['name']) ?></td>
                  <td><?= h((string)$m['email']) ?></td>
                  <td>
                    <span class="<?= h(badgeClass((string)$m['v_status'])) ?>">
                      <?= h(badgeText((string)$m['v_status'])) ?>
                    </span>
                  </td>
                  <td><?= ((int)($m['is_active'] ?? 0) === 1) ? 'sí' : 'no' ?></td>
                  <td>
                    <a class="btn" href="<?= BASE_URL ?>/credenciales.php?medico_id=<?= (int)$m['id'] ?><?= $debug ? '&debug=1':'' ?>">Ver</a>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if (!$medicos): ?>
                <tr><td colspan="6">No hay médicos.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>

      <!-- Detalle -->
      <section class="card">
        <div class="hd">
          <div>
            <h2>Detalle</h2>
            <p><?= $medico ? 'Revisa documentos y decide' : 'Selecciona un médico' ?></p>
          </div>
        </div>
        <div class="bd">
          <?php if (!$medico): ?>
            <div class="note">Selecciona un médico en la lista.</div>
          <?php else: ?>
            <div class="note" style="margin-bottom:12px;">
              <strong><?= h((string)$medico['name']) ?></strong><br>
              <span class="muted"><?= h((string)$medico['email']) ?> • ID <?= (int)$medico['id'] ?></span><br>
              <span class="muted">Verificación: <strong><?= h($verFlag) ?></strong> • Activo: <strong><?= $activeFlag ? 'sí' : 'no' ?></strong></span>
            </div>

            <form method="POST" class="form" style="margin-bottom:12px;">
              <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
              <input type="hidden" name="medico_id" value="<?= (int)$medico['id'] ?>">

              <div class="field full">
                <label for="note">Nota admin (opcional)</label>
                <input id="note" name="note" placeholder="Observaciones para el médico" value="<?= h((string)($medico['note'] ?? '')) ?>">
              </div>

              <div class="row" style="gap:10px; margin-top:10px;">
                <button class="btn primary" type="submit" name="action" value="approve">Aprobar + Activar</button>
                <button class="btn danger" type="submit" name="action" value="reject"
                        onclick="return confirm('¿Rechazar al médico?');">Rechazar + Desactivar</button>
              </div>
            </form>

            <h3 style="margin:16px 0 10px;">Documentos</h3>
            <?php if (!$docs): ?>
              <div class="note">Este médico no ha subido documentos.</div>
            <?php else: ?>
              <table class="table">
                <thead>
                  <tr>
                    <th>Tipo</th><th>Archivo</th><th>Estado</th><th>Fecha</th><th></th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($docs as $d): ?>
                    <tr>
                      <td><?= h((string)$d['doc_type']) ?></td>
                      <td><?= h((string)$d['original_name']) ?></td>
                      <td><span class="<?= h(badgeClass((string)$d['status'])) ?>"><?= h(badgeText((string)$d['status'])) ?></span></td>
                      <td><?= h((string)$d['created_at']) ?></td>
                      <td>
                        <a class="btn" href="<?= h((string)$d['file_path']) ?>" target="_blank" rel="noopener">Ver</a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </section>
    </div>
  </div>
</body>
</html>
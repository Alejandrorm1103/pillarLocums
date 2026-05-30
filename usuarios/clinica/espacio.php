<?php
declare(strict_types=1);

/**
 * Requiere que index.php ya tenga:
 * - $pdo (PDO)
 * - $clinicId (int)
 * - $uiErrors (array) (si no existe, lo creamos aquí)
 */

if (!isset($uiErrors) || !is_array($uiErrors)) $uiErrors = [];
$uiOk = null;

// -------- Helpers ----------
function h2(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function ensureDir(string $dir): bool {
  if (is_dir($dir)) return true;
  return @mkdir($dir, 0755, true);
}
function safeBasename(string $name): string {
  $name = preg_replace('/[^\w\-.]+/u', '_', $name);
  $name = trim($name, '._');
  if ($name === '') $name = 'archivo';
  return $name;
}

// -------- Verificar tablas (sin romper) ----------
$tablesOk = ['docs' => true, 'msgs' => true, 'activity' => true];

try {
  $t = $pdo->query("SHOW TABLES LIKE 'clinic_documents'");
  $tablesOk['docs'] = (bool)$t->fetchColumn();

  $t = $pdo->query("SHOW TABLES LIKE 'clinic_messages'");
  $tablesOk['msgs'] = (bool)$t->fetchColumn();

  $t = $pdo->query("SHOW TABLES LIKE 'clinic_activity'");
  $tablesOk['activity'] = (bool)$t->fetchColumn();
} catch (Throwable $e) {
  error_log("ESPACIO tables check: ".$e->getMessage());
}

// -------- Acciones POST ----------
try {
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    // LOG helper
    $logActivity = function(string $type, string $desc) use ($pdo, $clinicId, $tablesOk) {
      if (!$tablesOk['activity']) return;
      $st = $pdo->prepare("INSERT INTO clinic_activity (clinic_user_id, type, description) VALUES (?, ?, ?)");
      $st->execute([$clinicId, $type, $desc]);
    };

    // 1) Subir documento
    if ($action === 'space_upload_doc') {
      if (!$tablesOk['docs']) {
        $uiErrors[] = "Falta la tabla clinic_documents.";
      } else {
        $title = trim((string)($_POST['doc_title'] ?? ''));
        $category = trim((string)($_POST['doc_category'] ?? 'General'));
        if ($title === '') $uiErrors[] = "El título del documento es obligatorio.";

        if (!isset($_FILES['doc_file']) || !is_array($_FILES['doc_file'])) {
          $uiErrors[] = "No se recibió el archivo.";
        } else {
          $f = $_FILES['doc_file'];
          if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $uiErrors[] = "Error subiendo el archivo.";
          } else {
            $max = 8 * 1024 * 1024; // 8MB
            if (($f['size'] ?? 0) > $max) $uiErrors[] = "El archivo supera 8MB.";

            $orig = (string)($f['name'] ?? 'archivo');
            $origSafe = safeBasename($orig);
            $ext = strtolower(pathinfo($origSafe, PATHINFO_EXTENSION));

            $allowed = ['pdf','png','jpg','jpeg','doc','docx'];
            if (!in_array($ext, $allowed, true)) $uiErrors[] = "Formato no permitido. Usa: PDF, PNG, JPG, DOC, DOCX.";

            if (!$uiErrors) {
              // Carpeta de subida
              $base = dirname(__DIR__); // /usuarios
              $uploadDir = $base . "/uploads/clinic/" . $clinicId;
              if (!ensureDir($uploadDir)) $uiErrors[] = "No se pudo crear la carpeta de subida.";

              if (!$uiErrors) {
                $uniq = date('Ymd_His') . "_" . bin2hex(random_bytes(4));
                $finalName = $uniq . "_" . $origSafe;
                $absPath = $uploadDir . "/" . $finalName;
                $relPath = "/usuarios/uploads/clinic/" . $clinicId . "/" . $finalName;

                if (!@move_uploaded_file((string)$f['tmp_name'], $absPath)) {
                  $uiErrors[] = "No se pudo guardar el archivo en el servidor.";
                } else {
                  $st = $pdo->prepare("INSERT INTO clinic_documents (clinic_user_id, title, category, file_path, original_name, file_size)
                                       VALUES (?, ?, ?, ?, ?, ?)");
                  $st->execute([$clinicId, $title, ($category!==''?$category:'General'), $relPath, $orig, (int)$f['size']]);

                  $logActivity('document', "Subió documento: {$title}");
                  $uiOk = "Documento subido correctamente.";
                }
              }
            }
          }
        }
      }
    }

    // 2) Eliminar documento
    if ($action === 'space_delete_doc') {
      $docId = (int)($_POST['doc_id'] ?? 0);
      if ($docId <= 0) $uiErrors[] = "Documento inválido.";

      if (!$uiErrors && $tablesOk['docs']) {
        $st = $pdo->prepare("SELECT file_path, title FROM clinic_documents WHERE id=? AND clinic_user_id=? LIMIT 1");
        $st->execute([$docId, $clinicId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
          $uiErrors[] = "No autorizado o documento no existe.";
        } else {
          // borrar archivo físico si existe
          $rel = (string)($row['file_path'] ?? '');
          $title = (string)($row['title'] ?? '');
          if ($rel !== '' && str_starts_with($rel, "/usuarios/")) {
            $abs = dirname(__DIR__) . substr($rel, strlen("/usuarios"));
            if (is_file($abs)) @unlink($abs);
          }

          $st = $pdo->prepare("DELETE FROM clinic_documents WHERE id=? AND clinic_user_id=? LIMIT 1");
          $st->execute([$docId, $clinicId]);

          if ($tablesOk['activity']) {
            $st = $pdo->prepare("INSERT INTO clinic_activity (clinic_user_id, type, description) VALUES (?, ?, ?)");
            $st->execute([$clinicId, 'document', "Eliminó documento: {$title}"]);
          }

          $uiOk = "Documento eliminado.";
        }
      }
    }

    // 3) Crear mensaje/nota interna
    if ($action === 'space_add_note') {
      if (!$tablesOk['msgs']) {
        $uiErrors[] = "Falta la tabla clinic_messages.";
      } else {
        $subject = trim((string)($_POST['msg_subject'] ?? ''));
        $body = trim((string)($_POST['msg_body'] ?? ''));
        if ($subject === '') $uiErrors[] = "Asunto obligatorio.";
        if ($body === '') $uiErrors[] = "Mensaje obligatorio.";

        if (!$uiErrors) {
          $st = $pdo->prepare("INSERT INTO clinic_messages (clinic_user_id, subject, body) VALUES (?, ?, ?)");
          $st->execute([$clinicId, $subject, $body]);

          if ($tablesOk['activity']) {
            $st = $pdo->prepare("INSERT INTO clinic_activity (clinic_user_id, type, description) VALUES (?, ?, ?)");
            $st->execute([$clinicId, 'message', "Creó nota: {$subject}"]);
          }

          $uiOk = "Nota guardada.";
        }
      }
    }
  }
} catch (Throwable $e) {
  error_log("ESPACIO POST ERROR: ".$e->getMessage());
  $uiErrors[] = "Error interno. Revisa el log del servidor.";
}

// -------- Cargar datos para UI ----------
$docs = [];
$msgs = [];
$acts = [];

try {
  if ($tablesOk['docs']) {
    $st = $pdo->prepare("SELECT id, title, category, file_path, original_name, file_size, created_at
                         FROM clinic_documents
                         WHERE clinic_user_id=?
                         ORDER BY created_at DESC, id DESC
                         LIMIT 20");
    $st->execute([$clinicId]);
    $docs = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
  }

  if ($tablesOk['msgs']) {
    $st = $pdo->prepare("SELECT id, subject, body, created_at
                         FROM clinic_messages
                         WHERE clinic_user_id=?
                         ORDER BY created_at DESC, id DESC
                         LIMIT 15");
    $st->execute([$clinicId]);
    $msgs = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
  }

  if ($tablesOk['activity']) {
    $st = $pdo->prepare("SELECT id, type, description, created_at
                         FROM clinic_activity
                         WHERE clinic_user_id=?
                         ORDER BY created_at DESC, id DESC
                         LIMIT 15");
    $st->execute([$clinicId]);
    $acts = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
  }
} catch (Throwable $e) {
  error_log("ESPACIO LOAD ERROR: ".$e->getMessage());
  $uiErrors[] = "No se pudo cargar Mi espacio.";
}
?>

<?php if ($uiOk): ?>
  <div class="note" style="border-color: rgba(46,204,113,.25); background: rgba(46,204,113,.08); margin-bottom:12px;">
    <?= h2($uiOk) ?>
  </div>
<?php endif; ?>

<div class="grid2">
  <!-- Documentos -->
  <div class="panel">
    <div class="sectionTitle">
      <h2>Documentos</h2>
      <span class="muted">Repositorio interno</span>
    </div>

    <?php if (!$tablesOk['docs']): ?>
      <div class="note">Falta la tabla <strong>clinic_documents</strong>. Crea la tabla con el SQL de abajo.</div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" style="margin-bottom:12px;">
      <input type="hidden" name="action" value="space_upload_doc">

      <div class="row">
        <div class="field">
          <label>Título</label>
          <input class="input" name="doc_title" placeholder="Ej: Consentimiento informado / Protocolo / PDF oferta" required>
        </div>
        <div class="field">
          <label>Categoría</label>
          <select class="select" name="doc_category">
            <option>General</option>
            <option>Ofertas</option>
            <option>Legal</option>
            <option>Protocolos</option>
            <option>Plantillas</option>
          </select>
        </div>
      </div>

      <div class="field">
        <label>Archivo (PDF/PNG/JPG/DOC/DOCX)</label>
        <input class="input" type="file" name="doc_file" required>
      </div>

      <div class="btnRow">
        <button class="btn btnGold" type="submit">Subir documento</button>
      </div>
    </form>

    <?php if ($docs): ?>
      <table class="table">
        <thead>
          <tr>
            <th>Documento</th>
            <th>Categoría</th>
            <th>Fecha</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($docs as $d): ?>
          <tr>
            <td>
              <strong><?= h2((string)$d['title']) ?></strong><br>
              <span class="muted"><?= h2((string)$d['original_name']) ?> • <?= (int)$d['file_size'] ?> bytes</span>
            </td>
            <td><?= h2((string)$d['category']) ?></td>
            <td><?= h2((string)$d['created_at']) ?></td>
            <td>
              <div class="actions">
                <?php if (!empty($d['file_path'])): ?>
                  <a class="iconBtn" href="<?= h2((string)$d['file_path']) ?>" target="_blank" rel="noopener">Ver</a>
                <?php endif; ?>
                <form method="post" style="display:inline;" onsubmit="return confirm('¿Eliminar este documento?');">
                  <input type="hidden" name="action" value="space_delete_doc">
                  <input type="hidden" name="doc_id" value="<?= (int)$d['id'] ?>">
                  <button class="iconBtn" type="submit">Eliminar</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <div class="note">Aún no has subido documentos.</div>
    <?php endif; ?>
  </div>

  <!-- Mensajes + Historial -->
  <div class="panel">
    <div class="sectionTitle">
      <h2>Notas y actividad</h2>
      <span class="muted">Organización interna</span>
    </div>

    <div class="note" style="margin-bottom:12px;">
      Usa este espacio para guardar notas operativas vinculadas a ofertas: entrevistas, recordatorios, documentación pendiente y confirmaciones.
    </div>

    <!-- Crear nota -->
    <form method="post" style="margin-bottom:12px;">
      <input type="hidden" name="action" value="space_add_note">

      <div class="field">
        <label>Asunto</label>
        <input class="input" name="msg_subject" placeholder="Ej: Entrevista oferta Traumatólogo / Faltan documentos" required>
      </div>
      <div class="field">
        <label>Nota</label>
        <input class="input" name="msg_body" placeholder="Ej: llamar 16:00, pedir colegiación, confirmar disponibilidad..." required>
      </div>

      <div class="btnRow">
        <button class="btn btnGold" type="submit">Guardar nota</button>
      </div>
    </form>

    <!-- Lista notas -->
    <div class="note" style="margin-bottom:12px;">
      <strong>Últimas notas</strong>
      <?php if (!$tablesOk['msgs']): ?>
        <div class="muted" style="margin-top:6px;">Falta la tabla <strong>clinic_messages</strong>.</div>
      <?php elseif (!$msgs): ?>
        <div class="muted" style="margin-top:6px;">Sin notas todavía.</div>
      <?php else: ?>
        <ul style="margin:10px 0 0; padding-left:18px;">
          <?php foreach ($msgs as $m): ?>
            <li style="margin:8px 0;">
              <strong><?= h2((string)$m['subject']) ?></strong>
              <div class="muted"><?= h2((string)$m['body']) ?></div>
              <div class="muted" style="font-size:.85rem;"><?= h2((string)$m['created_at']) ?></div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>

    <!-- Actividad -->
    <div class="note">
      <strong>Actividad reciente</strong>
      <?php if (!$tablesOk['activity']): ?>
        <div class="muted" style="margin-top:6px;">Falta la tabla <strong>clinic_activity</strong>.</div>
      <?php elseif (!$acts): ?>
        <div class="muted" style="margin-top:6px;">Sin actividad registrada.</div>
      <?php else: ?>
        <ul style="margin:10px 0 0; padding-left:18px;">
          <?php foreach ($acts as $a): ?>
            <li style="margin:6px 0;">
              <span class="muted"><?= h2((string)$a['created_at']) ?></span>
              — <?= h2((string)$a['description']) ?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </div>
</div>




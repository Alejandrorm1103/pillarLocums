<?php
declare(strict_types=1);

/**
 * Requiere que index.php ya tenga:
 * - $pdo (PDO)
 * - $hospitalId (int)
 * - $uiErrors (array) (si no existe, lo creamos aquí)
 * - idealmente $plLang / $plDict / pl_t() desde index.php
 */

if (!isset($uiErrors) || !is_array($uiErrors)) $uiErrors = [];
$uiOk = null;

// -------- Helpers ----------
if (!function_exists('h2')) {
  function h2(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
  }
}

if (!function_exists('ensureDir')) {
  function ensureDir(string $dir): bool {
    if (is_dir($dir)) return true;
    return @mkdir($dir, 0755, true);
  }
}

if (!function_exists('safeBasename')) {
  function safeBasename(string $name): string {
    $name = preg_replace('/[^\w\-.]+/u', '_', $name);
    $name = trim($name, '._');
    if ($name === '') $name = 'archivo';
    return $name;
  }
}

// -------- I18N LOCAL PARA MI ESPACIO ----------
$plLang = (string)($plLang ?? $_GET['lang'] ?? $_SESSION['lang'] ?? 'es');
$plLang = ($plLang === 'en') ? 'en' : 'es';
$_SESSION['lang'] = $plLang;

$PILLAR_HOSP_SPACE_DICT = [
  'es' => [
    'internal_error' => 'Error interno. Revisa el log del servidor.',
    'space_load_error' => 'No se pudo cargar Mi espacio.',
    'table_missing_documents' => 'Falta la tabla hospital_documents.',
    'table_missing_messages' => 'Falta la tabla hospital_messages.',
    'table_missing_activity' => 'Falta la tabla hospital_activity.',
    'document_title_required' => 'El título del documento es obligatorio.',
    'file_not_received' => 'No se recibió el archivo.',
    'file_upload_error' => 'Error subiendo el archivo.',
    'file_too_large' => 'El archivo supera 8MB.',
    'file_format_not_allowed' => 'Formato no permitido. Usa: PDF, PNG, JPG, DOC, DOCX.',
    'upload_folder_error' => 'No se pudo crear la carpeta de subida.',
    'save_file_server_error' => 'No se pudo guardar el archivo en el servidor.',
    'document_uploaded_ok' => 'Documento subido correctamente.',
    'invalid_document' => 'Documento inválido.',
    'unauthorized_or_missing_document' => 'No autorizado o documento no existe.',
    'document_deleted_ok' => 'Documento eliminado.',
    'subject_required' => 'Asunto obligatorio.',
    'message_required' => 'Mensaje obligatorio.',
    'note_saved_ok' => 'Nota guardada.',
    'my_space' => 'Mi espacio',
    'documents' => 'Documentos',
    'internal_repository' => 'Repositorio interno',
    'missing_table' => 'Falta la tabla',
    'create_table_with_sql' => 'Crea la tabla con este SQL:',
    'title' => 'Título',
    'category' => 'Categoría',
    'general' => 'General',
    'offers' => 'Ofertas',
    'legal' => 'Legal',
    'protocols' => 'Protocolos',
    'templates' => 'Plantillas',
    'file_label' => 'Archivo (PDF/PNG/JPG/DOC/DOCX)',
    'upload_document' => 'Subir documento',
    'document' => 'Documento',
    'date' => 'Fecha',
    'actions' => 'Acciones',
    'view' => 'Ver',
    'delete' => 'Eliminar',
    'delete_document_confirm' => '¿Eliminar este documento?',
    'no_documents_yet' => 'Aún no has subido documentos.',
    'notes_activity' => 'Notas y actividad',
    'internal_organization' => 'Organización interna',
    'space_note_intro' => 'Usa este espacio para guardar notas operativas vinculadas a ofertas: entrevistas, recordatorios, documentación pendiente y confirmaciones.',
    'subject' => 'Asunto',
    'subject_placeholder' => 'Ej: Entrevista / Faltan documentos / Confirmación',
    'note' => 'Nota',
    'note_placeholder' => 'Ej: llamar 16:00, pedir colegiación, confirmar disponibilidad...',
    'save_note' => 'Guardar nota',
    'latest_notes' => 'Últimas notas',
    'no_notes_yet' => 'Sin notas todavía.',
    'recent_activity' => 'Actividad reciente',
    'no_activity_yet' => 'Sin actividad registrada.',
    'bytes' => 'bytes',
    'doc_placeholder' => 'Ej: Protocolo / Contrato / PDF oferta',
    'uploaded_document_activity' => 'Subió documento:',
    'deleted_document_activity' => 'Eliminó documento:',
    'created_note_activity' => 'Creó nota:',
  ],
  'en' => [
    'internal_error' => 'Internal error. Check the server log.',
    'space_load_error' => 'Could not load My space.',
    'table_missing_documents' => 'The hospital_documents table is missing.',
    'table_missing_messages' => 'The hospital_messages table is missing.',
    'table_missing_activity' => 'The hospital_activity table is missing.',
    'document_title_required' => 'The document title is required.',
    'file_not_received' => 'No file was received.',
    'file_upload_error' => 'Error uploading the file.',
    'file_too_large' => 'The file exceeds 8MB.',
    'file_format_not_allowed' => 'File format not allowed. Use: PDF, PNG, JPG, DOC, DOCX.',
    'upload_folder_error' => 'Could not create the upload folder.',
    'save_file_server_error' => 'Could not save the file on the server.',
    'document_uploaded_ok' => 'Document uploaded successfully.',
    'invalid_document' => 'Invalid document.',
    'unauthorized_or_missing_document' => 'Unauthorized or document does not exist.',
    'document_deleted_ok' => 'Document deleted.',
    'subject_required' => 'Subject is required.',
    'message_required' => 'Message is required.',
    'note_saved_ok' => 'Note saved.',
    'my_space' => 'My space',
    'documents' => 'Documents',
    'internal_repository' => 'Internal repository',
    'missing_table' => 'Missing table',
    'create_table_with_sql' => 'Create the table with this SQL:',
    'title' => 'Title',
    'category' => 'Category',
    'general' => 'General',
    'offers' => 'Offers',
    'legal' => 'Legal',
    'protocols' => 'Protocols',
    'templates' => 'Templates',
    'file_label' => 'File (PDF/PNG/JPG/DOC/DOCX)',
    'upload_document' => 'Upload document',
    'document' => 'Document',
    'date' => 'Date',
    'actions' => 'Actions',
    'view' => 'View',
    'delete' => 'Delete',
    'delete_document_confirm' => 'Delete this document?',
    'no_documents_yet' => 'You have not uploaded any documents yet.',
    'notes_activity' => 'Notes and activity',
    'internal_organization' => 'Internal organization',
    'space_note_intro' => 'Use this space to keep operational notes linked to offers: interviews, reminders, pending documentation, and confirmations.',
    'subject' => 'Subject',
    'subject_placeholder' => 'Ex: Interview / Missing documents / Confirmation',
    'note' => 'Note',
    'note_placeholder' => 'Ex: call 16:00, request registration, confirm availability...',
    'save_note' => 'Save note',
    'latest_notes' => 'Latest notes',
    'no_notes_yet' => 'No notes yet.',
    'recent_activity' => 'Recent activity',
    'no_activity_yet' => 'No activity recorded yet.',
    'bytes' => 'bytes',
    'doc_placeholder' => 'Ex: Protocol / Contract / Offer PDF',
    'uploaded_document_activity' => 'Uploaded document:',
    'deleted_document_activity' => 'Deleted document:',
    'created_note_activity' => 'Created note:',
  ],
];

// Si existe el diccionario global del index, lo extendemos
if (isset($plDict) && is_array($plDict)) {
  $plDict['es'] = array_merge($plDict['es'] ?? [], $PILLAR_HOSP_SPACE_DICT['es']);
  $plDict['en'] = array_merge($plDict['en'] ?? [], $PILLAR_HOSP_SPACE_DICT['en']);
}

// Fallback local si index no expone pl_t()
if (!function_exists('pl_t')) {
  function pl_t(string $key, ?string $fallback = null): string {
    global $plLang, $PILLAR_HOSP_SPACE_DICT;
    return $PILLAR_HOSP_SPACE_DICT[$plLang][$key] ?? ($fallback ?? $key);
  }
}

// -------- Verificar tablas (sin romper) ----------
$tablesOk = ['docs' => true, 'msgs' => true, 'activity' => true];

try {
  $t = $pdo->query("SHOW TABLES LIKE 'hospital_documents'");
  $tablesOk['docs'] = (bool)$t->fetchColumn();

  $t = $pdo->query("SHOW TABLES LIKE 'hospital_messages'");
  $tablesOk['msgs'] = (bool)$t->fetchColumn();

  $t = $pdo->query("SHOW TABLES LIKE 'hospital_activity'");
  $tablesOk['activity'] = (bool)$t->fetchColumn();
} catch (Throwable $e) {
  error_log("HOSP ESPACIO tables check: " . $e->getMessage());
}

// -------- Acciones POST ----------
try {
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    // LOG helper
    $logActivity = function(string $type, string $desc) use ($pdo, $hospitalId, $tablesOk) {
      if (!$tablesOk['activity']) return;
      $st = $pdo->prepare("INSERT INTO hospital_activity (hospital_user_id, type, description) VALUES (?, ?, ?)");
      $st->execute([$hospitalId, $type, $desc]);
    };

    // 1) Subir documento
    if ($action === 'space_upload_doc') {
      if (!$tablesOk['docs']) {
        $uiErrors[] = pl_t('table_missing_documents');
      } else {
        $title = trim((string)($_POST['doc_title'] ?? ''));
        $category = trim((string)($_POST['doc_category'] ?? 'General'));

        if ($title === '') $uiErrors[] = pl_t('document_title_required');

        if (!isset($_FILES['doc_file']) || !is_array($_FILES['doc_file'])) {
          $uiErrors[] = pl_t('file_not_received');
        } else {
          $f = $_FILES['doc_file'];

          if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $uiErrors[] = pl_t('file_upload_error');
          } else {
            $max = 8 * 1024 * 1024; // 8MB
            if (($f['size'] ?? 0) > $max) $uiErrors[] = pl_t('file_too_large');

            $orig = (string)($f['name'] ?? 'archivo');
            $origSafe = safeBasename($orig);
            $ext = strtolower(pathinfo($origSafe, PATHINFO_EXTENSION));

            $allowed = ['pdf', 'png', 'jpg', 'jpeg', 'doc', 'docx'];
            if (!in_array($ext, $allowed, true)) {
              $uiErrors[] = pl_t('file_format_not_allowed');
            }

            if (!$uiErrors) {
              $base = dirname(__DIR__); // /usuarios
              $uploadDir = $base . "/uploads/hospital/" . $hospitalId;

              if (!ensureDir($uploadDir)) {
                $uiErrors[] = pl_t('upload_folder_error');
              }

              if (!$uiErrors) {
                $uniq = date('Ymd_His') . "_" . bin2hex(random_bytes(4));
                $finalName = $uniq . "_" . $origSafe;
                $absPath = $uploadDir . "/" . $finalName;
                $relPath = "/usuarios/uploads/hospital/" . $hospitalId . "/" . $finalName;

                if (!@move_uploaded_file((string)$f['tmp_name'], $absPath)) {
                  $uiErrors[] = pl_t('save_file_server_error');
                } else {
                  $st = $pdo->prepare("INSERT INTO hospital_documents (hospital_user_id, title, category, file_path, original_name, file_size)
                                       VALUES (?, ?, ?, ?, ?, ?)");
                  $st->execute([$hospitalId, $title, ($category !== '' ? $category : 'General'), $relPath, $orig, (int)$f['size']]);

                  $logActivity('document', pl_t('uploaded_document_activity') . " {$title}");
                  $uiOk = pl_t('document_uploaded_ok');
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
      if ($docId <= 0) $uiErrors[] = pl_t('invalid_document');

      if (!$uiErrors && $tablesOk['docs']) {
        $st = $pdo->prepare("SELECT file_path, title FROM hospital_documents WHERE id=? AND hospital_user_id=? LIMIT 1");
        $st->execute([$docId, $hospitalId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
          $uiErrors[] = pl_t('unauthorized_or_missing_document');
        } else {
          $rel = (string)($row['file_path'] ?? '');
          $title = (string)($row['title'] ?? '');

          if ($rel !== '' && str_starts_with($rel, "/usuarios/")) {
            $abs = dirname(__DIR__) . substr($rel, strlen("/usuarios"));
            if (is_file($abs)) @unlink($abs);
          }

          $st = $pdo->prepare("DELETE FROM hospital_documents WHERE id=? AND hospital_user_id=? LIMIT 1");
          $st->execute([$docId, $hospitalId]);

          if ($tablesOk['activity']) {
            $st = $pdo->prepare("INSERT INTO hospital_activity (hospital_user_id, type, description) VALUES (?, ?, ?)");
            $st->execute([$hospitalId, 'document', pl_t('deleted_document_activity') . " {$title}"]);
          }

          $uiOk = pl_t('document_deleted_ok');
        }
      }
    }

    // 3) Crear mensaje/nota interna
    if ($action === 'space_add_note') {
      if (!$tablesOk['msgs']) {
        $uiErrors[] = pl_t('table_missing_messages');
      } else {
        $subject = trim((string)($_POST['msg_subject'] ?? ''));
        $body = trim((string)($_POST['msg_body'] ?? ''));

        if ($subject === '') $uiErrors[] = pl_t('subject_required');
        if ($body === '') $uiErrors[] = pl_t('message_required');

        if (!$uiErrors) {
          $st = $pdo->prepare("INSERT INTO hospital_messages (hospital_user_id, subject, body) VALUES (?, ?, ?)");
          $st->execute([$hospitalId, $subject, $body]);

          if ($tablesOk['activity']) {
            $st = $pdo->prepare("INSERT INTO hospital_activity (hospital_user_id, type, description) VALUES (?, ?, ?)");
            $st->execute([$hospitalId, 'message', pl_t('created_note_activity') . " {$subject}"]);
          }

          $uiOk = pl_t('note_saved_ok');
        }
      }
    }
  }
} catch (Throwable $e) {
  error_log("HOSP ESPACIO POST ERROR: " . $e->getMessage());
  $uiErrors[] = pl_t('internal_error');
}

// -------- Cargar datos para UI ----------
$docs = [];
$msgs = [];
$acts = [];

try {
  if ($tablesOk['docs']) {
    $st = $pdo->prepare("SELECT id, title, category, file_path, original_name, file_size, created_at
                         FROM hospital_documents
                         WHERE hospital_user_id=?
                         ORDER BY created_at DESC, id DESC
                         LIMIT 20");
    $st->execute([$hospitalId]);
    $docs = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
  }

  if ($tablesOk['msgs']) {
    $st = $pdo->prepare("SELECT id, subject, body, created_at
                         FROM hospital_messages
                         WHERE hospital_user_id=?
                         ORDER BY created_at DESC, id DESC
                         LIMIT 15");
    $st->execute([$hospitalId]);
    $msgs = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
  }

  if ($tablesOk['activity']) {
    $st = $pdo->prepare("SELECT id, type, description, created_at
                         FROM hospital_activity
                         WHERE hospital_user_id=?
                         ORDER BY created_at DESC, id DESC
                         LIMIT 15");
    $st->execute([$hospitalId]);
    $acts = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
  }
} catch (Throwable $e) {
  error_log("HOSP ESPACIO LOAD ERROR: " . $e->getMessage());
  $uiErrors[] = pl_t('space_load_error');
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
      <h2><?= h2(pl_t('documents')) ?></h2>
      <span class="muted"><?= h2(pl_t('internal_repository')) ?></span>
    </div>

    <?php if (!$tablesOk['docs']): ?>
      <div class="note">
        <?= h2(pl_t('table_missing_documents')) ?>
      </div>
      <div class="note" style="margin-top:10px;">
        <?= h2(pl_t('create_table_with_sql')) ?>
        <pre style="white-space:pre-wrap; margin:10px 0 0; padding:10px; border-radius:12px; border:1px solid rgba(255,255,255,.10); background:rgba(0,0,0,.16); color:rgba(246,241,231,.88);">
CREATE TABLE `hospital_documents` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `hospital_user_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(190) NOT NULL,
  `category` VARCHAR(60) NOT NULL DEFAULT 'General',
  `file_path` VARCHAR(255) NOT NULL,
  `original_name` VARCHAR(255) NOT NULL,
  `file_size` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_hospital_docs_hospital` (`hospital_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        </pre>
      </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" style="margin-bottom:12px;">
      <input type="hidden" name="action" value="space_upload_doc">

      <div class="row">
        <div class="field">
          <label><?= h2(pl_t('title')) ?></label>
          <input class="input" name="doc_title" placeholder="<?= h2(pl_t('doc_placeholder')) ?>" required>
        </div>
        <div class="field">
          <label><?= h2(pl_t('category')) ?></label>
          <select class="select" name="doc_category">
            <option value="General"><?= h2(pl_t('general')) ?></option>
            <option value="Ofertas"><?= h2(pl_t('offers')) ?></option>
            <option value="Legal"><?= h2(pl_t('legal')) ?></option>
            <option value="Protocolos"><?= h2(pl_t('protocols')) ?></option>
            <option value="Plantillas"><?= h2(pl_t('templates')) ?></option>
          </select>
        </div>
      </div>

      <div class="field">
        <label><?= h2(pl_t('file_label')) ?></label>
        <input class="input" type="file" name="doc_file" required>
      </div>

      <div class="btnRow">
        <button class="btn btnGold" type="submit"><?= h2(pl_t('upload_document')) ?></button>
      </div>
    </form>

    <?php if ($docs): ?>
      <div class="tableWrap">
        <table class="table">
          <thead>
            <tr>
              <th><?= h2(pl_t('document')) ?></th>
              <th><?= h2(pl_t('category')) ?></th>
              <th><?= h2(pl_t('date')) ?></th>
              <th><?= h2(pl_t('actions')) ?></th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($docs as $d): ?>
            <tr>
              <td>
                <strong><?= h2((string)$d['title']) ?></strong><br>
                <span class="muted">
                  <?= h2((string)$d['original_name']) ?> • <?= (int)$d['file_size'] ?> <?= h2(pl_t('bytes')) ?>
                </span>
              </td>
              <td><?= h2((string)$d['category']) ?></td>
              <td><?= h2((string)$d['created_at']) ?></td>
              <td>
                <div class="actions">
                  <?php if (!empty($d['file_path'])): ?>
                    <a class="iconBtn" href="<?= h2((string)$d['file_path']) ?>" target="_blank" rel="noopener"><?= h2(pl_t('view')) ?></a>
                  <?php endif; ?>
                  <form method="post" style="display:inline;" onsubmit="return confirm(<?= json_encode(pl_t('delete_document_confirm')) ?>);">
                    <input type="hidden" name="action" value="space_delete_doc">
                    <input type="hidden" name="doc_id" value="<?= (int)$d['id'] ?>">
                    <button class="iconBtn" type="submit"><?= h2(pl_t('delete')) ?></button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <div class="note"><?= h2(pl_t('no_documents_yet')) ?></div>
    <?php endif; ?>
  </div>

  <!-- Mensajes + Historial -->
  <div class="panel">
    <div class="sectionTitle">
      <h2><?= h2(pl_t('notes_activity')) ?></h2>
      <span class="muted"><?= h2(pl_t('internal_organization')) ?></span>
    </div>

    <div class="note" style="margin-bottom:12px;">
      <?= h2(pl_t('space_note_intro')) ?>
    </div>

    <form method="post" style="margin-bottom:12px;">
      <input type="hidden" name="action" value="space_add_note">

      <div class="field">
        <label><?= h2(pl_t('subject')) ?></label>
        <input class="input" name="msg_subject" placeholder="<?= h2(pl_t('subject_placeholder')) ?>" required>
      </div>

      <div class="field">
        <label><?= h2(pl_t('note')) ?></label>
        <input class="input" name="msg_body" placeholder="<?= h2(pl_t('note_placeholder')) ?>" required>
      </div>

      <div class="btnRow">
        <button class="btn btnGold" type="submit"><?= h2(pl_t('save_note')) ?></button>
      </div>
    </form>

    <div class="note" style="margin-bottom:12px;">
      <strong><?= h2(pl_t('latest_notes')) ?></strong>
      <?php if (!$tablesOk['msgs']): ?>
        <div class="muted" style="margin-top:6px;"><?= h2(pl_t('table_missing_messages')) ?></div>
      <?php elseif (!$msgs): ?>
        <div class="muted" style="margin-top:6px;"><?= h2(pl_t('no_notes_yet')) ?></div>
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

    <div class="note">
      <strong><?= h2(pl_t('recent_activity')) ?></strong>
      <?php if (!$tablesOk['activity']): ?>
        <div class="muted" style="margin-top:6px;"><?= h2(pl_t('table_missing_activity')) ?></div>
      <?php elseif (!$acts): ?>
        <div class="muted" style="margin-top:6px;"><?= h2(pl_t('no_activity_yet')) ?></div>
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
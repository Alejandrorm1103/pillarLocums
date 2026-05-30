<?php
/**
 * media.php
 * - Administra assets/media.json
 * - Subida de imágenes (carrusel) y video.
 * - Ajuste individual por slide: scale, x, y.
 * - Fuerza refresco del video en front con video_updated_at
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/config.php';

require_login();

function read_media(): array {
  $default = [
    'video' => '',
    'video_updated_at' => 0,
    'carousel' => [],
  ];

  if (!file_exists(MEDIA_JSON_PATH)) {
    return $default;
  }

  $raw = file_get_contents(MEDIA_JSON_PATH);
  $data = json_decode($raw ?: '{}', true);

  if (!is_array($data)) {
    return $default;
  }

  if (!isset($data['video']) || !is_string($data['video'])) {
    $data['video'] = '';
  }

  if (!isset($data['video_updated_at']) || !is_numeric($data['video_updated_at'])) {
    $data['video_updated_at'] = 0;
  }

  if (!isset($data['carousel']) || !is_array($data['carousel'])) {
    $data['carousel'] = [];
  }

  return $data;
}

function write_media(array $data): void {
  $payload = [
    'video' => (string)($data['video'] ?? ''),
    'video_updated_at' => (int)($data['video_updated_at'] ?? 0),
    'carousel' => array_values(is_array($data['carousel'] ?? null) ? $data['carousel'] : []),
  ];

  $json = json_encode(
    $payload,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
  );

  if ($json === false) {
    throw new RuntimeException('No se pudo convertir media.json.');
  }

  $tmp = MEDIA_JSON_PATH . '.tmp';

  if (file_put_contents($tmp, $json, LOCK_EX) === false) {
    throw new RuntimeException('No se pudo escribir archivo temporal.');
  }

  if (!rename($tmp, MEDIA_JSON_PATH)) {
    @unlink($tmp);
    throw new RuntimeException('No se pudo reemplazar media.json.');
  }

  clearstatcache(true, MEDIA_JSON_PATH);
}

function ensure_dirs(): void {
  if (!is_dir(UPLOAD_CAROUSEL_DIR)) {
    @mkdir(UPLOAD_CAROUSEL_DIR, 0755, true);
  }

  if (!is_dir(UPLOAD_VIDEO_DIR)) {
    @mkdir(UPLOAD_VIDEO_DIR, 0755, true);
  }
}

function detect_mime(string $tmpPath): string {
  $finfo = finfo_open(FILEINFO_MIME_TYPE);
  if (!$finfo) return '';
  $mime = (string)finfo_file($finfo, $tmpPath);
  finfo_close($finfo);
  return strtolower(trim($mime));
}

ensure_dirs();

$msg = '';
$err = '';
$data = read_media();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  verify_csrf();

  $action = (string)($_POST['action'] ?? '');

  // ---- Actualizar captions + transforms (scale/x/y) ----
  if ($action === 'save_meta') {
    $carousel = $data['carousel'];

    foreach ($carousel as $i => $item) {
      $caption = (string)($_POST["caption_$i"] ?? ($item['caption'] ?? ''));
      $scale   = (float)($_POST["scale_$i"] ?? ($item['scale'] ?? 1));
      $x       = (string)($_POST["x_$i"] ?? ($item['x'] ?? '0px'));
      $y       = (string)($_POST["y_$i"] ?? ($item['y'] ?? '0px'));

      if ($scale <= 0.05) $scale = 0.05;
      if ($scale > 2.0) $scale = 2.0;

      $carousel[$i]['caption'] = $caption;
      $carousel[$i]['scale']   = $scale;
      $carousel[$i]['x']       = $x;
      $carousel[$i]['y']       = $y;
    }

    $data['carousel'] = $carousel;
    write_media($data);
    $msg = 'Cambios guardados en carrusel.';
  }

  // ---- Subir imagen a carrusel ----
  if ($action === 'upload_image') {
    if (empty($_FILES['image']['name'])) {
      $err = 'Selecciona una imagen.';
    } else {
      $f = $_FILES['image'];

      if ((int)$f['error'] !== UPLOAD_ERR_OK) {
        $err = 'Error subiendo archivo.';
      } else {
        $mime = detect_mime($f['tmp_name']);
        $allowed = ['image/png', 'image/jpeg', 'image/webp'];

        if (!in_array($mime, $allowed, true)) {
          $err = 'Formato no permitido. Usa PNG/JPG/WEBP.';
        } else {
          $ext = match ($mime) {
            'image/png'  => 'png',
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            default      => 'bin'
          };

          $safeName = 'carousel-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
          $dest = rtrim(UPLOAD_CAROUSEL_DIR, '/\\') . DIRECTORY_SEPARATOR . $safeName;

          if (!move_uploaded_file($f['tmp_name'], $dest)) {
            $err = 'No se pudo guardar la imagen (permisos).';
          } else {
            @chmod($dest, 0644);

            $rel = 'assets/uploads/carousel/' . $safeName;

            $data['carousel'][] = [
              'src' => $rel,
              'caption' => 'Nuevo slide',
              'scale' => 1.0,
              'x' => '0px',
              'y' => '0px',
            ];

            write_media($data);
            $msg = 'Imagen agregada al carrusel.';
          }
        }
      }
    }
  }

  // ---- Subir video promocional ----
  if ($action === 'upload_video') {
    if (empty($_FILES['video']['name'])) {
      $err = 'Selecciona un video MP4.';
    } else {
      $f = $_FILES['video'];

      if ((int)$f['error'] !== UPLOAD_ERR_OK) {
        $err = 'Error subiendo video.';
      } else {
        $mime = detect_mime($f['tmp_name']);
        $ext  = strtolower((string)pathinfo((string)$f['name'], PATHINFO_EXTENSION));

        $isValidMp4 =
          $ext === 'mp4' &&
          in_array($mime, ['video/mp4', 'application/mp4', 'application/octet-stream'], true);

        if (!$isValidMp4) {
          $err = 'Formato no permitido. Solo MP4.';
        } else {
          // nombre fijo + versión en JSON para obligar refresco en el front
          $safeName = 'promo-current.mp4';
          $dest = rtrim(UPLOAD_VIDEO_DIR, '/\\') . DIRECTORY_SEPARATOR . $safeName;

          if (!move_uploaded_file($f['tmp_name'], $dest)) {
            $err = 'No se pudo guardar el video (permisos).';
          } else {
            @chmod($dest, 0644);

            $data['video'] = 'assets/uploads/video/' . $safeName;
            $data['video_updated_at'] = time();

            write_media($data);
            $msg = 'Video actualizado.';
          }
        }
      }
    }
  }

  $data = read_media();
}

$csrf = csrf_token();
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= htmlspecialchars(APP_NAME) ?> | Media</title>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/admin.css">
</head>
<body>
  <div class="container">
    <div class="topbar">
      <div class="brand">
        <strong>PILLAR</strong>
        <span>Media (Index)</span>
      </div>
      <nav class="nav">
        <a href="<?= BASE_URL ?>/index.php">Dashboard</a>
        <a href="<?= BASE_URL ?>/users.php">Usuarios</a>
        <a href="<?= BASE_URL ?>/calendar.php">Calendario</a>
        <a class="active" href="<?= BASE_URL ?>/media.php">Media</a>
      </nav>
      <div class="actions">
        <a class="btn" href="<?= BASE_URL ?>/logout.php">Salir</a>
      </div>
    </div>

    <div class="grid">
      <section class="card">
        <div class="hd">
          <div>
            <h2>Gestión de carrusel y video</h2>
            <p>Sube media y ajusta cada imagen (scale / x / y) para centrarla</p>
          </div>
          <span class="badge">assets/media.json</span>
        </div>

        <div class="bd">
          <?php if ($msg): ?>
            <div class="card" style="border-radius:12px;border-color:rgba(34,197,94,.35);">
              <div class="bd" style="background:rgba(34,197,94,.10);color:#14532d;"><?= htmlspecialchars($msg) ?></div>
            </div>
          <?php endif; ?>

          <?php if ($err): ?>
            <div class="card" style="border-radius:12px;border-color:rgba(239,68,68,.35);">
              <div class="bd" style="background:rgba(239,68,68,.10);color:#991b1b;"><?= htmlspecialchars($err) ?></div>
            </div>
          <?php endif; ?>

          <div class="grid" style="margin-top:0;">
            <div class="card" style="grid-column: span 12;">
              <div class="hd">
                <div>
                  <h2>Video actual</h2>
                  <p><?= htmlspecialchars($data['video'] ?: 'No definido') ?></p>
                </div>
              </div>
              <div class="bd">
                <form method="post" enctype="multipart/form-data" class="form">
                  <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                  <input type="hidden" name="action" value="upload_video">

                  <div class="field full">
                    <label>Subir nuevo video (MP4)</label>
                    <input type="file" name="video" accept="video/mp4" required>
                  </div>

                  <div class="field full">
                    <button class="btn primary" type="submit">Actualizar video</button>
                  </div>
                </form>
              </div>
            </div>

            <div class="card" style="grid-column: span 12;">
              <div class="hd">
                <div>
                  <h2>Carrusel</h2>
                  <p>Ajusta centrado con: scale (0.5 a 1.2 típico), x/y (px)</p>
                </div>
              </div>

              <div class="bd">
                <form method="post" enctype="multipart/form-data" class="form" style="margin-bottom:14px;">
                  <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                  <input type="hidden" name="action" value="upload_image">

                  <div class="field full">
                    <label>Subir nueva imagen (PNG/JPG/WEBP)</label>
                    <input type="file" name="image" accept="image/png,image/jpeg,image/webp" required>
                  </div>

                  <div class="field full">
                    <button class="btn primary" type="submit">Agregar al carrusel</button>
                  </div>
                </form>

                <form method="post" class="form">
                  <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                  <input type="hidden" name="action" value="save_meta">

                  <div class="field full">
                    <p class="note" style="margin:0;">
                      Reglas rápidas: <strong>centrado perfecto</strong> suele ser <code>y=0px</code>.
                      Si la imagen se ve abajo, pon <code>y=-20px</code>.
                      <code>scale</code> es decimal: <code>0.82</code>, no 50.
                    </p>
                  </div>

                  <?php foreach ($data['carousel'] as $i => $item): ?>
                    <div class="card" style="grid-column: span 12; border-radius:16px;">
                      <div class="bd">
                        <div class="row" style="justify-content:space-between;">
                          <div>
                            <strong>Slide <?= $i + 1 ?></strong>
                            <div class="note"><?= htmlspecialchars($item['src'] ?? '') ?></div>
                          </div>
                          <span class="badge">Ajuste individual</span>
                        </div>

                        <div class="form" style="margin-top:10px;">
                          <div class="field full">
                            <label>Caption</label>
                            <input name="caption_<?= $i ?>" value="<?= htmlspecialchars($item['caption'] ?? '') ?>">
                          </div>

                          <div class="field">
                            <label>Scale</label>
                            <input
                              name="scale_<?= $i ?>"
                              type="number"
                              step="0.01"
                              min="0.05"
                              max="2"
                              value="<?= htmlspecialchars((string)($item['scale'] ?? 1)) ?>"
                            >
                          </div>

                          <div class="field">
                            <label>X (px)</label>
                            <input
                              name="x_<?= $i ?>"
                              placeholder="0px"
                              value="<?= htmlspecialchars($item['x'] ?? '0px') ?>"
                            >
                          </div>

                          <div class="field">
                            <label>Y (px)</label>
                            <input
                              name="y_<?= $i ?>"
                              placeholder="0px"
                              value="<?= htmlspecialchars($item['y'] ?? '0px') ?>"
                            >
                          </div>

                          <div class="field full">
                            <div class="note">
                              Vista rápida: en tu index el slide aplicará:
                              <code>transform: translate(x,y) scale(scale)</code>.
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  <?php endforeach; ?>

                  <div class="field full">
                    <button class="btn primary" type="submit">Guardar ajustes del carrusel</button>
                  </div>
                </form>
              </div>
            </div>
          </div>
        </div>
      </section>
    </div>
  </div>
</body>
</html>
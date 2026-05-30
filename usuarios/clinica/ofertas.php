<?php
// /usuarios/clinica/ofertas.php
// MODO:
// - include normal: corre lógica (POST + carga jobs + KPI)
// - si seteas $PILLAR_OFFERS_RENDER=true antes del require: renderiza UI (sin repetir lógica)

if (!defined('PILLAR_OFFERS_BOOTSTRAPPED')) {
  define('PILLAR_OFFERS_BOOTSTRAPPED', true);

  if (!function_exists('h')) {
    function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
  }

  // Requiere: $pdo, $clinicId, $tab, $uiErrors (array), y variables $jobs/$kpiActiveOffers definidas en index.
  $jobs = $jobs ?? [];
  $kpiActiveOffers = $kpiActiveOffers ?? 0;

  try {
    if (!isset($pdo) || !($pdo instanceof PDO)) {
      throw new RuntimeException("PDO no disponible.");
    }
    if (!isset($clinicId) || (int)$clinicId <= 0) {
      throw new RuntimeException("clinicId inválido.");
    }

    // Detectar columnas reales de jobs (evita 500 por columnas inexistentes)
    $cols = [];
    $stC = $pdo->query("SHOW COLUMNS FROM `jobs`");
    foreach (($stC->fetchAll(PDO::FETCH_ASSOC) ?: []) as $r) {
      $cols[] = (string)$r['Field'];
    }

    $pick = function(array $cands) use ($cols): ?string {
      foreach ($cands as $c) if (in_array($c, $cols, true)) return $c;
      return null;
    };

    $COL_ID          = $pick(['id']);
    $COL_CLINIC_ID   = $pick(['clinic_user_id','clinic_id']);
    $COL_TITLE       = $pick(['title','name','position','job_title']);
    $COL_SCHEDULE    = $pick(['schedule','horario','shift']);
    $COL_SALARY      = $pick(['salary','pay','payment']);
    $COL_LOCATION    = $pick(['location','ubicacion','city']);
    $COL_DESCRIPTION = $pick(['description','details','descripcion']);
    $COL_STATUS      = $pick(['status','estado']);
    $COL_CREATED_AT  = $pick(['created_at','created','createdOn']);

    if (!$COL_ID || !$COL_CLINIC_ID) {
      throw new RuntimeException("Tabla jobs no tiene columnas mínimas (id/clinic_user_id).");
    }
    if (!$COL_TITLE || !$COL_SCHEDULE || !$COL_LOCATION) {
      throw new RuntimeException("Tabla jobs no tiene columnas requeridas (title/schedule/location).");
    }
    if (!$COL_STATUS) {
      // status puede no existir; si no existe, igual funcionará, pero sin cerrar/reactivar y sin KPI “activa”
      $COL_STATUS = null;
    }

    // ---------- CRUD (solo si tab=ofertas) ----------
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($tab ?? '') === 'ofertas') {
      $action = (string)($_POST['action'] ?? '');

      // Helpers
      $ownJob = function(int $jobId) use ($pdo, $COL_ID, $COL_CLINIC_ID, $clinicId): bool {
        $st = $pdo->prepare("SELECT `$COL_ID` FROM `jobs` WHERE `$COL_ID`=? AND `$COL_CLINIC_ID`=? LIMIT 1");
        $st->execute([$jobId, (int)$clinicId]);
        return (bool)$st->fetchColumn();
      };

      if ($action === 'create_job') {
        $title = trim((string)($_POST['title'] ?? ''));
        $schedule = trim((string)($_POST['schedule'] ?? ''));
        $salary = trim((string)($_POST['salary'] ?? ''));
        $location = trim((string)($_POST['location'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));

        $errs = [];
        if ($title === '') $errs[] = "La posición/título es obligatoria.";
        if ($schedule === '') $errs[] = "El horario es obligatorio.";
        if ($location === '') $errs[] = "La ubicación es obligatoria.";

        if (!$errs) {
          $fields = [
            $COL_CLINIC_ID => (int)$clinicId,
            $COL_TITLE     => $title,
            $COL_SCHEDULE  => $schedule,
            $COL_LOCATION  => $location,
          ];
          if ($COL_SALARY)      $fields[$COL_SALARY] = ($salary !== '' ? $salary : null);
          if ($COL_DESCRIPTION) $fields[$COL_DESCRIPTION] = ($description !== '' ? $description : null);
          if ($COL_STATUS)      $fields[$COL_STATUS] = 'active';

          $colsSql = implode(',', array_map(fn($c)=>"`$c`", array_keys($fields)));
          $valsSql = implode(',', array_fill(0, count($fields), '?'));

          $st = $pdo->prepare("INSERT INTO `jobs` ($colsSql) VALUES ($valsSql)");
          $st->execute(array_values($fields));

          header("Location: ?tab=ofertas");
          exit;
        }
        foreach ($errs as $er) $uiErrors[] = $er;
      }

      if ($action === 'update_job') {
        $jobId = (int)($_POST['job_id'] ?? 0);
        $title = trim((string)($_POST['title'] ?? ''));
        $schedule = trim((string)($_POST['schedule'] ?? ''));
        $salary = trim((string)($_POST['salary'] ?? ''));
        $location = trim((string)($_POST['location'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));

        $errs = [];
        if ($jobId <= 0) $errs[] = "Oferta inválida.";
        if ($title === '') $errs[] = "La posición/título es obligatoria.";
        if ($schedule === '') $errs[] = "El horario es obligatorio.";
        if ($location === '') $errs[] = "La ubicación es obligatoria.";
        if (!$errs && !$ownJob($jobId)) $errs[] = "No autorizado.";

        if (!$errs) {
          $sets = ["`$COL_TITLE`=?", "`$COL_SCHEDULE`=?", "`$COL_LOCATION`=?"];
          $vals = [$title, $schedule, $location];

          if ($COL_SALARY)      { $sets[] = "`$COL_SALARY`=?"; $vals[] = ($salary !== '' ? $salary : null); }
          if ($COL_DESCRIPTION) { $sets[] = "`$COL_DESCRIPTION`=?"; $vals[] = ($description !== '' ? $description : null); }

          $vals[] = $jobId;
          $vals[] = (int)$clinicId;

          $sql = "UPDATE `jobs` SET ".implode(',', $sets)." WHERE `$COL_ID`=? AND `$COL_CLINIC_ID`=? LIMIT 1";
          $st = $pdo->prepare($sql);
          $st->execute($vals);

          header("Location: ?tab=ofertas");
          exit;
        }
        foreach ($errs as $er) $uiErrors[] = $er;
      }

      // Cerrar / Reactivar / Eliminar solo si existe status
      if ($action === 'close_job' && $COL_STATUS) {
        $jobId = (int)($_POST['job_id'] ?? 0);
        if ($jobId > 0 && $ownJob($jobId)) {
          $st = $pdo->prepare("UPDATE `jobs` SET `$COL_STATUS`='closed' WHERE `$COL_ID`=? AND `$COL_CLINIC_ID`=? LIMIT 1");
          $st->execute([$jobId, (int)$clinicId]);
        }
        header("Location: ?tab=ofertas"); exit;
      }

      if ($action === 'reactivate_job' && $COL_STATUS) {
        $jobId = (int)($_POST['job_id'] ?? 0);
        if ($jobId > 0 && $ownJob($jobId)) {
          $st = $pdo->prepare("UPDATE `jobs` SET `$COL_STATUS`='active' WHERE `$COL_ID`=? AND `$COL_CLINIC_ID`=? LIMIT 1");
          $st->execute([$jobId, (int)$clinicId]);
        }
        header("Location: ?tab=ofertas"); exit;
      }

      if ($action === 'delete_job') {
        $jobId = (int)($_POST['job_id'] ?? 0);
        if ($jobId > 0 && $ownJob($jobId)) {
          $st = $pdo->prepare("DELETE FROM `jobs` WHERE `$COL_ID`=? AND `$COL_CLINIC_ID`=? LIMIT 1");
          $st->execute([$jobId, (int)$clinicId]);
        }
        header("Location: ?tab=ofertas"); exit;
      }
    }

    // ---------- Cargar jobs para la UI + KPI ----------
    $order = $COL_CREATED_AT ? "`$COL_CREATED_AT` DESC, `$COL_ID` DESC" : "`$COL_ID` DESC";
    $st = $pdo->prepare("SELECT * FROM `jobs` WHERE `$COL_CLINIC_ID`=? ORDER BY $order");
    $st->execute([(int)$clinicId]);
    $jobs = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    if ($COL_STATUS) {
      $kpiActiveOffers = 0;
      foreach ($jobs as $j) {
        $v = strtolower((string)($j[$COL_STATUS] ?? ''));
        if ($v === 'active' || $v === 'activa') $kpiActiveOffers++;
      }
    } else {
      $kpiActiveOffers = count($jobs);
    }

    // Exponer columnas para la vista
    $PILLAR_JOBS_COLS = [
      'id' => $COL_ID,
      'clinic' => $COL_CLINIC_ID,
      'title' => $COL_TITLE,
      'schedule' => $COL_SCHEDULE,
      'salary' => $COL_SALARY,
      'location' => $COL_LOCATION,
      'description' => $COL_DESCRIPTION,
      'status' => $COL_STATUS,
      'created_at' => $COL_CREATED_AT,
    ];

  } catch (Throwable $e) {
    error_log("OFERTAS.PHP ERROR: ".$e->getMessage());
    if (($tab ?? '') === 'ofertas') $uiErrors[] = "Error interno. Revisa el log del servidor.";
    $jobs = [];
    $kpiActiveOffers = 0;
    $PILLAR_JOBS_COLS = [];
  }
}

// ---------------- VIEW ----------------
if (!empty($PILLAR_OFFERS_RENDER)):

  $cols = $PILLAR_JOBS_COLS ?? [];
  $cId = $cols['id'] ?? 'id';
  $cTitle = $cols['title'] ?? 'title';
  $cSchedule = $cols['schedule'] ?? 'schedule';
  $cSalary = $cols['salary'] ?? null;
  $cLocation = $cols['location'] ?? 'location';
  $cDesc = $cols['description'] ?? null;
  $cStatus = $cols['status'] ?? null;
  $cCreated = $cols['created_at'] ?? null;

  // función pillStatus la tienes en index.php; si no existe, fallback simple
  if (!function_exists('pillStatus')) {
    function pillStatus(string $status): array {
      return ['bg'=>'rgba(185,180,170,.14)','bd'=>'rgba(185,180,170,.24)','tx'=>'#d6d1c7','label'=>$status ?: 'Estado'];
    }
  }
?>
  <div class="sectionTitle">
    <h2>Gestión de ofertas</h2>
    <button class="btn btnGold" type="button" onclick="openModal('modalJob')">Publicar oferta</button>
  </div>

  <?php if (empty($jobs)): ?>
    <div class="note">Aún no tienes ofertas publicadas.</div>
  <?php else: ?>
    <table class="table">
      <thead>
        <tr>
          <th>Posición</th>
          <th>Horario</th>
          <th>Salario</th>
          <th>Ubicación</th>
          <th>Estado</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($jobs as $j):
          $statusVal = $cStatus ? (string)($j[$cStatus] ?? '') : '';
          $p = pillStatus($statusVal);
          $jid = (int)($j[$cId] ?? 0);
        ?>
          <tr>
            <td>
              <strong><?= h((string)($j[$cTitle] ?? '')) ?></strong><br>
              <span class="muted">Publicado: <?= h($cCreated ? (string)($j[$cCreated] ?? '') : '') ?></span>
            </td>
            <td><?= h((string)($j[$cSchedule] ?? '')) ?></td>
            <td><?= h($cSalary ? (string)($j[$cSalary] ?? '') : '') ?></td>
            <td><?= h((string)($j[$cLocation] ?? '')) ?></td>
            <td>
              <span class="pill" style="background:<?= $p['bg'] ?>;border-color:<?= $p['bd'] ?>;color:<?= $p['tx'] ?>;">
                <?= h($p['label'] ?? 'Estado') ?>
              </span>
            </td>
            <td>
              <div class="actions">
                <button class="iconBtn" type="button"
                  onclick='openEditJob(
                    <?= (int)$jid ?>,
                    <?= json_encode((string)($j[$cTitle] ?? "")) ?>,
                    <?= json_encode((string)($j[$cSchedule] ?? "")) ?>,
                    <?= json_encode((string)($cSalary ? ($j[$cSalary] ?? "") : "")) ?>,
                    <?= json_encode((string)($j[$cLocation] ?? "")) ?>,
                    <?= json_encode((string)($cDesc ? ($j[$cDesc] ?? "") : "")) ?>
                  )'>Editar</button>

                <?php if ($cStatus && strtolower((string)($j[$cStatus] ?? '')) === 'active'): ?>
                  <form method="post" style="display:inline;">
                    <input type="hidden" name="action" value="close_job">
                    <input type="hidden" name="job_id" value="<?= (int)$jid ?>">
                    <button class="iconBtn" type="submit">Cerrar</button>
                  </form>
                <?php elseif ($cStatus): ?>
                  <form method="post" style="display:inline;">
                    <input type="hidden" name="action" value="reactivate_job">
                    <input type="hidden" name="job_id" value="<?= (int)$jid ?>">
                    <button class="iconBtn" type="submit">Reactivar</button>
                  </form>
                <?php endif; ?>

                <form method="post" style="display:inline;" onsubmit="return confirm('¿Eliminar esta oferta?');">
                  <input type="hidden" name="action" value="delete_job">
                  <input type="hidden" name="job_id" value="<?= (int)$jid ?>">
                  <button class="iconBtn" type="submit">Eliminar</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <!-- Modal Publicar/Editar Oferta -->
  <div id="modalJob" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.55); z-index:50; padding:18px;">
    <div style="max-width:760px;margin:40px auto; background:rgba(31,35,40,.96); border:1px solid rgba(255,255,255,.12); border-radius:18px; box-shadow:var(--shadow);">
      <div style="padding:14px 14px; border-bottom:1px solid rgba(255,255,255,.10); display:flex; align-items:center; justify-content:space-between; gap:10px;">
        <strong id="jobModalTitle">Publicar nueva oferta (Clínica)</strong>
        <button class="iconBtn" type="button" onclick="closeModal('modalJob')">Cerrar</button>
      </div>

      <div style="padding:14px;">
        <form method="post" id="jobForm">
          <input type="hidden" name="action" id="jobAction" value="create_job">
          <input type="hidden" name="job_id" id="jobId" value="0">

          <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 10px;">
            <div>
              <label class="muted">Posición</label>
              <input class="input" name="title" id="jobTitle" required>
            </div>
            <div>
              <label class="muted">Horario</label>
              <input class="input" name="schedule" id="jobSchedule" required>
            </div>
            <div>
              <label class="muted">Salario (opcional)</label>
              <input class="input" name="salary" id="jobSalary">
            </div>
            <div>
              <label class="muted">Ubicación</label>
              <input class="input" name="location" id="jobLocation" required>
            </div>
          </div>

          <div style="margin-top:10px;">
            <label class="muted">Descripción (opcional)</label>
            <input class="input" name="description" id="jobDescription">
          </div>

          <div class="btnRow" style="margin-top:12px;">
            <button class="btn btnGhost" type="button" onclick="closeModal('modalJob')">Cancelar</button>
            <button class="btn btnGold" type="submit">Guardar</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script>
    function openModal(id){ document.getElementById(id).style.display = 'block'; }
    function closeModal(id){ document.getElementById(id).style.display = 'none'; }

    document.addEventListener('click', function(e){
      const m = document.getElementById('modalJob');
      if (!m || m.style.display !== 'block') return;
      if (e.target === m) m.style.display = 'none';
    });

    function openEditJob(id, title, schedule, salary, location, description){
      document.getElementById('jobModalTitle').textContent = 'Editar oferta (Clínica)';
      document.getElementById('jobAction').value = 'update_job';
      document.getElementById('jobId').value = id;

      document.getElementById('jobTitle').value = title || '';
      document.getElementById('jobSchedule').value = schedule || '';
      document.getElementById('jobSalary').value = salary || '';
      document.getElementById('jobLocation').value = location || '';
      document.getElementById('jobDescription').value = description || '';

      openModal('modalJob');
    }
  </script>
<?php endif; ?>

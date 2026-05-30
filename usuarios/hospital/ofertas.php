<?php
// /usuarios/hospital/ofertas.php
// MODO:
// - include normal: corre lógica (POST + carga jobs + KPI)
// - si seteas $PILLAR_OFFERS_RENDER=true antes del require: renderiza UI (sin repetir lógica)

if (!defined('PILLAR_HOSP_OFFERS_BOOTSTRAPPED')) {
  define('PILLAR_HOSP_OFFERS_BOOTSTRAPPED', true);

  if (!function_exists('h')) {
    function h(string $s): string {
      return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }
  }

  // =====================
  // I18N LOCAL PARA OFERTAS
  // =====================
  $plLang = (string)($plLang ?? $_GET['lang'] ?? $_SESSION['lang'] ?? 'es');
  $plLang = ($plLang === 'en') ? 'en' : 'es';
  $_SESSION['lang'] = $plLang;

  $PILLAR_HOSP_OFFERS_DICT = [
    'es' => [
      'offers_management'        => 'Gestión de ofertas',
      'publish_offer'            => 'Publicar oferta',
      'no_offers_yet'            => 'Aún no tienes ofertas publicadas.',
      'position'                 => 'Posición',
      'schedule'                 => 'Horario',
      'salary'                   => 'Salario',
      'location'                 => 'Ubicación',
      'status'                   => 'Estado',
      'actions'                  => 'Acciones',
      'published'                => 'Publicado',
      'edit'                     => 'Editar',
      'close'                    => 'Cerrar',
      'reactivate'               => 'Reactivar',
      'delete'                   => 'Eliminar',
      'delete_offer_confirm'     => '¿Eliminar esta oferta?',
      'new_offer_hospital'       => 'Publicar nueva oferta (Hospital)',
      'edit_offer_hospital'      => 'Editar oferta (Hospital)',
      'close_modal'              => 'Cerrar',
      'cancel'                   => 'Cancelar',
      'save'                     => 'Guardar',
      'title_label'              => 'Posición',
      'schedule_label'           => 'Horario',
      'salary_optional'          => 'Salario (opcional)',
      'location_label'           => 'Ubicación',
      'description_optional'     => 'Descripción (opcional)',
      'status_active'            => 'Activa',
      'status_closed'            => 'Cerrada',
      'status_default'           => 'Estado',
      'offer_invalid'            => 'Oferta inválida.',
      'unauthorized'             => 'No autorizado.',
      'title_required'           => 'La posición/título es obligatoria.',
      'schedule_required'        => 'El horario es obligatorio.',
      'location_required'        => 'La ubicación es obligatoria.',
      'internal_error'           => 'Error interno. Revisa el log del servidor.',
    ],
    'en' => [
      'offers_management'        => 'Offer management',
      'publish_offer'            => 'Publish offer',
      'no_offers_yet'            => 'You do not have any published offers yet.',
      'position'                 => 'Position',
      'schedule'                 => 'Schedule',
      'salary'                   => 'Salary',
      'location'                 => 'Location',
      'status'                   => 'Status',
      'actions'                  => 'Actions',
      'published'                => 'Published',
      'edit'                     => 'Edit',
      'close'                    => 'Close',
      'reactivate'               => 'Reactivate',
      'delete'                   => 'Delete',
      'delete_offer_confirm'     => 'Delete this offer?',
      'new_offer_hospital'       => 'Publish new offer (Hospital)',
      'edit_offer_hospital'      => 'Edit offer (Hospital)',
      'close_modal'              => 'Close',
      'cancel'                   => 'Cancel',
      'save'                     => 'Save',
      'title_label'              => 'Position',
      'schedule_label'           => 'Schedule',
      'salary_optional'          => 'Salary (optional)',
      'location_label'           => 'Location',
      'description_optional'     => 'Description (optional)',
      'status_active'            => 'Active',
      'status_closed'            => 'Closed',
      'status_default'           => 'Status',
      'offer_invalid'            => 'Invalid offer.',
      'unauthorized'             => 'Unauthorized.',
      'title_required'           => 'The position/title is required.',
      'schedule_required'        => 'Schedule is required.',
      'location_required'        => 'Location is required.',
      'internal_error'           => 'Internal error. Check the server log.',
    ],
  ];

  // Si existe el diccionario global del index, lo extendemos sin romper nada
  if (isset($plDict) && is_array($plDict)) {
    $plDict['es'] = array_merge($plDict['es'] ?? [], $PILLAR_HOSP_OFFERS_DICT['es']);
    $plDict['en'] = array_merge($plDict['en'] ?? [], $PILLAR_HOSP_OFFERS_DICT['en']);
  }

  // Fallback local si el index todavía no expone pl_t()
  if (!function_exists('pl_t')) {
    function pl_t(string $key, ?string $fallback = null): string {
      global $plLang, $PILLAR_HOSP_OFFERS_DICT;
      return $PILLAR_HOSP_OFFERS_DICT[$plLang][$key] ?? ($fallback ?? $key);
    }
  }

  $plLangQS = '&lang=' . urlencode($plLang);

  // Requiere: $pdo, $hospitalId, $tab, $uiErrors (array), y variables $jobs/$kpiActiveOffers definidas en index.
  $jobs = $jobs ?? [];
  $kpiActiveOffers = $kpiActiveOffers ?? 0;

  try {
    if (!isset($pdo) || !($pdo instanceof PDO)) {
      throw new RuntimeException("PDO no disponible.");
    }
    if (!isset($hospitalId) || (int)$hospitalId <= 0) {
      throw new RuntimeException("hospitalId inválido.");
    }

    // Detectar columnas reales de jobs (evita 500 por columnas inexistentes)
    $cols = [];
    $stC = $pdo->query("SHOW COLUMNS FROM `jobs`");
    foreach (($stC->fetchAll(PDO::FETCH_ASSOC) ?: []) as $r) {
      $cols[] = (string)$r['Field'];
    }

    $pick = function(array $cands) use ($cols): ?string {
      foreach ($cands as $c) {
        if (in_array($c, $cols, true)) return $c;
      }
      return null;
    };

    $COL_ID          = $pick(['id']);
    $COL_OWNER_ID    = $pick(['hospital_user_id','clinic_user_id','hospital_id','clinic_id']);
    $COL_TITLE       = $pick(['title','name','position','job_title']);
    $COL_SCHEDULE    = $pick(['schedule','horario','shift']);
    $COL_SALARY      = $pick(['salary','pay','payment']);
    $COL_LOCATION    = $pick(['location','ubicacion','city']);
    $COL_DESCRIPTION = $pick(['description','details','descripcion']);
    $COL_STATUS      = $pick(['status','estado']);
    $COL_CREATED_AT  = $pick(['created_at','created','createdOn']);

    if (!$COL_ID || !$COL_OWNER_ID) {
      throw new RuntimeException("Tabla jobs no tiene columnas mínimas (id / hospital_user_id|clinic_user_id).");
    }
    if (!$COL_TITLE || !$COL_SCHEDULE || !$COL_LOCATION) {
      throw new RuntimeException("Tabla jobs no tiene columnas requeridas (title/schedule/location).");
    }
    if (!$COL_STATUS) {
      $COL_STATUS = null;
    }

    // ---------- CRUD (solo si tab=ofertas) ----------
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($tab ?? '') === 'ofertas') {
      $action = (string)($_POST['action'] ?? '');

      $ownJob = function(int $jobId) use ($pdo, $COL_ID, $COL_OWNER_ID, $hospitalId): bool {
        $st = $pdo->prepare("SELECT `$COL_ID` FROM `jobs` WHERE `$COL_ID`=? AND `$COL_OWNER_ID`=? LIMIT 1");
        $st->execute([$jobId, (int)$hospitalId]);
        return (bool)$st->fetchColumn();
      };

      if ($action === 'create_job') {
        $title = trim((string)($_POST['title'] ?? ''));
        $schedule = trim((string)($_POST['schedule'] ?? ''));
        $salary = trim((string)($_POST['salary'] ?? ''));
        $location = trim((string)($_POST['location'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));

        $errs = [];
        if ($title === '') $errs[] = pl_t('title_required');
        if ($schedule === '') $errs[] = pl_t('schedule_required');
        if ($location === '') $errs[] = pl_t('location_required');

        if (!$errs) {
          $fields = [
            $COL_OWNER_ID  => (int)$hospitalId,
            $COL_TITLE     => $title,
            $COL_SCHEDULE  => $schedule,
            $COL_LOCATION  => $location,
          ];
          if ($COL_SALARY)      $fields[$COL_SALARY] = ($salary !== '' ? $salary : null);
          if ($COL_DESCRIPTION) $fields[$COL_DESCRIPTION] = ($description !== '' ? $description : null);
          if ($COL_STATUS)      $fields[$COL_STATUS] = 'active';

          $colsSql = implode(',', array_map(fn($c) => "`$c`", array_keys($fields)));
          $valsSql = implode(',', array_fill(0, count($fields), '?'));

          $st = $pdo->prepare("INSERT INTO `jobs` ($colsSql) VALUES ($valsSql)");
          $st->execute(array_values($fields));

          header("Location: ?tab=ofertas{$plLangQS}");
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
        if ($jobId <= 0) $errs[] = pl_t('offer_invalid');
        if ($title === '') $errs[] = pl_t('title_required');
        if ($schedule === '') $errs[] = pl_t('schedule_required');
        if ($location === '') $errs[] = pl_t('location_required');
        if (!$errs && !$ownJob($jobId)) $errs[] = pl_t('unauthorized');

        if (!$errs) {
          $sets = ["`$COL_TITLE`=?", "`$COL_SCHEDULE`=?", "`$COL_LOCATION`=?"];
          $vals = [$title, $schedule, $location];

          if ($COL_SALARY)      { $sets[] = "`$COL_SALARY`=?"; $vals[] = ($salary !== '' ? $salary : null); }
          if ($COL_DESCRIPTION) { $sets[] = "`$COL_DESCRIPTION`=?"; $vals[] = ($description !== '' ? $description : null); }

          $vals[] = $jobId;
          $vals[] = (int)$hospitalId;

          $sql = "UPDATE `jobs` SET " . implode(',', $sets) . " WHERE `$COL_ID`=? AND `$COL_OWNER_ID`=? LIMIT 1";
          $st = $pdo->prepare($sql);
          $st->execute($vals);

          header("Location: ?tab=ofertas{$plLangQS}");
          exit;
        }
        foreach ($errs as $er) $uiErrors[] = $er;
      }

      if ($action === 'close_job' && $COL_STATUS) {
        $jobId = (int)($_POST['job_id'] ?? 0);
        if ($jobId > 0 && $ownJob($jobId)) {
          $st = $pdo->prepare("UPDATE `jobs` SET `$COL_STATUS`='closed' WHERE `$COL_ID`=? AND `$COL_OWNER_ID`=? LIMIT 1");
          $st->execute([$jobId, (int)$hospitalId]);
        }
        header("Location: ?tab=ofertas{$plLangQS}");
        exit;
      }

      if ($action === 'reactivate_job' && $COL_STATUS) {
        $jobId = (int)($_POST['job_id'] ?? 0);
        if ($jobId > 0 && $ownJob($jobId)) {
          $st = $pdo->prepare("UPDATE `jobs` SET `$COL_STATUS`='active' WHERE `$COL_ID`=? AND `$COL_OWNER_ID`=? LIMIT 1");
          $st->execute([$jobId, (int)$hospitalId]);
        }
        header("Location: ?tab=ofertas{$plLangQS}");
        exit;
      }

      if ($action === 'delete_job') {
        $jobId = (int)($_POST['job_id'] ?? 0);
        if ($jobId > 0 && $ownJob($jobId)) {
          $st = $pdo->prepare("DELETE FROM `jobs` WHERE `$COL_ID`=? AND `$COL_OWNER_ID`=? LIMIT 1");
          $st->execute([$jobId, (int)$hospitalId]);
        }
        header("Location: ?tab=ofertas{$plLangQS}");
        exit;
      }
    }

    // ---------- Cargar jobs para la UI + KPI ----------
    $order = $COL_CREATED_AT ? "`$COL_CREATED_AT` DESC, `$COL_ID` DESC" : "`$COL_ID` DESC";
    $st = $pdo->prepare("SELECT * FROM `jobs` WHERE `$COL_OWNER_ID`=? ORDER BY $order");
    $st->execute([(int)$hospitalId]);
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
      'owner' => $COL_OWNER_ID,
      'title' => $COL_TITLE,
      'schedule' => $COL_SCHEDULE,
      'salary' => $COL_SALARY,
      'location' => $COL_LOCATION,
      'description' => $COL_DESCRIPTION,
      'status' => $COL_STATUS,
      'created_at' => $COL_CREATED_AT,
    ];

  } catch (Throwable $e) {
    error_log("HOSP OFERTAS.PHP ERROR: " . $e->getMessage());
    if (($tab ?? '') === 'ofertas') $uiErrors[] = pl_t('internal_error');
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

  $offerStatusMeta = function(string $status): array {
    $status = strtolower(trim($status));

    if ($status === 'active' || $status === 'activa') {
      return [
        'bg'    => 'rgba(151,195,188,.24)',
        'bd'    => 'rgba(151,195,188,.40)',
        'tx'    => '#3D6D76',
        'label' => pl_t('status_active'),
      ];
    }

    if ($status === 'closed' || $status === 'cerrada' || $status === 'closed_job') {
      return [
        'bg'    => 'rgba(206,147,121,.14)',
        'bd'    => 'rgba(206,147,121,.30)',
        'tx'    => '#9b5f46',
        'label' => pl_t('status_closed'),
      ];
    }

    return [
      'bg'    => 'rgba(151,195,188,.14)',
      'bd'    => 'rgba(61,109,118,.18)',
      'tx'    => '#3D6D76',
      'label' => pl_t('status_default'),
    ];
  };
?>
  <div class="sectionTitle">
    <h2><?= h(pl_t('offers_management')) ?></h2>
    <button class="btn btnGold" type="button" onclick="openModal('modalJob')"><?= h(pl_t('publish_offer')) ?></button>
  </div>

  <?php if (empty($jobs)): ?>
    <div class="note"><?= h(pl_t('no_offers_yet')) ?></div>
  <?php else: ?>
    <div class="tableWrap">
      <table class="table">
        <thead>
          <tr>
            <th><?= h(pl_t('position')) ?></th>
            <th><?= h(pl_t('schedule')) ?></th>
            <th><?= h(pl_t('salary')) ?></th>
            <th><?= h(pl_t('location')) ?></th>
            <th><?= h(pl_t('status')) ?></th>
            <th><?= h(pl_t('actions')) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($jobs as $j):
            $statusVal = $cStatus ? (string)($j[$cStatus] ?? '') : '';
            $p = $offerStatusMeta($statusVal);
            $jid = (int)($j[$cId] ?? 0);
          ?>
            <tr>
              <td>
                <strong><?= h((string)($j[$cTitle] ?? '')) ?></strong><br>
                <span class="muted">
                  <?= h(pl_t('published')) ?>: <?= h($cCreated ? (string)($j[$cCreated] ?? '') : '') ?>
                </span>
              </td>
              <td><?= h((string)($j[$cSchedule] ?? '')) ?></td>
              <td><?= h($cSalary ? (string)($j[$cSalary] ?? '') : '') ?></td>
              <td><?= h((string)($j[$cLocation] ?? '')) ?></td>
              <td>
                <span class="pill" style="background:<?= h($p['bg']) ?>; border-color:<?= h($p['bd']) ?>; color:<?= h($p['tx']) ?>;">
                  <?= h($p['label']) ?>
                </span>
              </td>
              <td>
                <div class="actions">
                  <button
                    class="iconBtn"
                    type="button"
                    onclick='openEditJob(
                      <?= (int)$jid ?>,
                      <?= json_encode((string)($j[$cTitle] ?? "")) ?>,
                      <?= json_encode((string)($j[$cSchedule] ?? "")) ?>,
                      <?= json_encode((string)($cSalary ? ($j[$cSalary] ?? "") : "")) ?>,
                      <?= json_encode((string)($j[$cLocation] ?? "")) ?>,
                      <?= json_encode((string)($cDesc ? ($j[$cDesc] ?? "") : "")) ?>
                    )'
                  >
                    <?= h(pl_t('edit')) ?>
                  </button>

                  <?php if ($cStatus && strtolower((string)($j[$cStatus] ?? '')) === 'active'): ?>
                    <form method="post" style="display:inline;">
                      <input type="hidden" name="action" value="close_job">
                      <input type="hidden" name="job_id" value="<?= (int)$jid ?>">
                      <button class="iconBtn" type="submit"><?= h(pl_t('close')) ?></button>
                    </form>
                  <?php elseif ($cStatus): ?>
                    <form method="post" style="display:inline;">
                      <input type="hidden" name="action" value="reactivate_job">
                      <input type="hidden" name="job_id" value="<?= (int)$jid ?>">
                      <button class="iconBtn" type="submit"><?= h(pl_t('reactivate')) ?></button>
                    </form>
                  <?php endif; ?>

                  <form method="post" style="display:inline;" onsubmit="return confirm(<?= json_encode(pl_t('delete_offer_confirm')) ?>);">
                    <input type="hidden" name="action" value="delete_job">
                    <input type="hidden" name="job_id" value="<?= (int)$jid ?>">
                    <button class="iconBtn" type="submit"><?= h(pl_t('delete')) ?></button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <!-- Modal Publicar/Editar Oferta -->
  <div id="modalJob" class="modalOverlay" aria-hidden="true">
    <div class="modalCard">
      <div class="modalHeader">
        <strong id="jobModalTitle"><?= h(pl_t('new_offer_hospital')) ?></strong>
        <button class="modalClose" type="button" onclick="closeModal('modalJob')"><?= h(pl_t('close_modal')) ?></button>
      </div>

      <div class="modalBody">
        <form method="post" id="jobForm">
          <input type="hidden" name="action" id="jobAction" value="create_job">
          <input type="hidden" name="job_id" id="jobId" value="0">

          <div class="row">
            <div class="field">
              <label><?= h(pl_t('title_label')) ?></label>
              <input class="input" name="title" id="jobTitle" required>
            </div>

            <div class="field">
              <label><?= h(pl_t('schedule_label')) ?></label>
              <input class="input" name="schedule" id="jobSchedule" required>
            </div>

            <div class="field">
              <label><?= h(pl_t('salary_optional')) ?></label>
              <input class="input" name="salary" id="jobSalary">
            </div>

            <div class="field">
              <label><?= h(pl_t('location_label')) ?></label>
              <input class="input" name="location" id="jobLocation" required>
            </div>
          </div>

          <div class="field">
            <label><?= h(pl_t('description_optional')) ?></label>
            <input class="input" name="description" id="jobDescription">
          </div>

          <div class="btnRow" style="margin-top:12px;">
            <button class="btn btnGhost" type="button" onclick="closeModal('modalJob')"><?= h(pl_t('cancel')) ?></button>
            <button class="btn btnGold" type="submit"><?= h(pl_t('save')) ?></button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script>
    const PILLAR_HOSP_OFFERS_I18N = {
      newOfferHospital: <?= json_encode(pl_t('new_offer_hospital')) ?>,
      editOfferHospital: <?= json_encode(pl_t('edit_offer_hospital')) ?>
    };

    function openModal(id){
      const el = document.getElementById(id);
      if (!el) return;
      el.classList.add('isOpen');
      el.setAttribute('aria-hidden', 'false');
      document.body.style.overflow = 'hidden';
    }

    function closeModal(id){
      const el = document.getElementById(id);
      if (!el) return;
      el.classList.remove('isOpen');
      el.setAttribute('aria-hidden', 'true');
      document.body.style.overflow = '';
    }

    document.addEventListener('click', function(e){
      const modal = document.getElementById('modalJob');
      if (!modal) return;
      if (e.target === modal) closeModal('modalJob');
    });

    document.addEventListener('keydown', function(e){
      if (e.key === 'Escape') {
        closeModal('modalJob');
      }
    });

    function resetJobFormToCreate(){
      document.getElementById('jobModalTitle').textContent = PILLAR_HOSP_OFFERS_I18N.newOfferHospital;
      document.getElementById('jobAction').value = 'create_job';
      document.getElementById('jobId').value = '0';
      document.getElementById('jobTitle').value = '';
      document.getElementById('jobSchedule').value = '';
      document.getElementById('jobSalary').value = '';
      document.getElementById('jobLocation').value = '';
      document.getElementById('jobDescription').value = '';
    }

    function openEditJob(id, title, schedule, salary, location, description){
      document.getElementById('jobModalTitle').textContent = PILLAR_HOSP_OFFERS_I18N.editOfferHospital;
      document.getElementById('jobAction').value = 'update_job';
      document.getElementById('jobId').value = id;

      document.getElementById('jobTitle').value = title || '';
      document.getElementById('jobSchedule').value = schedule || '';
      document.getElementById('jobSalary').value = salary || '';
      document.getElementById('jobLocation').value = location || '';
      document.getElementById('jobDescription').value = description || '';

      openModal('modalJob');
    }

    (function(){
      const openCreateBtn = document.querySelector('.sectionTitle .btn.btnGold');
      if (openCreateBtn) {
        openCreateBtn.addEventListener('click', function(){
          resetJobFormToCreate();
        });
      }
    })();
  </script>
<?php endif; ?>
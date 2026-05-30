<?php
declare(strict_types=1);

/**
 * MÓDULO: Calendario (Clínica)
 * Requiere desde index.php:
 * - $pdo (PDO)
 * - $clinicId (int)
 * - $monthNames (si NO existe, lo definimos aquí)
 * - Helpers opcionales: h()
 */

if (!function_exists('h')) {
  function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
}

$errors = [];
$okMsg  = '';

try {
  if (!isset($pdo) || !($pdo instanceof PDO)) {
    throw new RuntimeException('PDO no disponible. Revisa require de db.php en index.php.');
  }
  if (!isset($clinicId) || (int)$clinicId <= 0) {
    throw new RuntimeException('clinicId no válido.');
  }

  // ---------- Helpers de introspección (para no “romper” si cambiaste columnas) ----------
  $tableExists = function(string $table) use ($pdo): bool {
    $st = $pdo->prepare("SHOW TABLES LIKE ?");
    $st->execute([$table]);
    return (bool)$st->fetchColumn();
  };

  $getColumns = function(string $table) use ($pdo): array {
    $st = $pdo->query("SHOW COLUMNS FROM `$table`");
    $cols = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
      $cols[] = (string)$r['Field'];
    }
    return $cols;
  };

  $pickCol = function(array $cols, array $candidates, ?string $fallback = null): ?string {
    foreach ($candidates as $c) {
      if (in_array($c, $cols, true)) return $c;
    }
    return $fallback;
  };

  // ---------- Tabla de eventos ----------
  if (!$tableExists('clinic_events')) {
    throw new RuntimeException("No existe la tabla clinic_events. Créala (abajo te dejo SQL).");
  }

  $evCols = $getColumns('clinic_events');

  $COL_ID        = $pickCol($evCols, ['id'], 'id');
  $COL_CLINIC_ID = $pickCol($evCols, ['clinic_user_id','clinic_id','user_id'], null);
  $COL_JOB_ID    = $pickCol($evCols, ['job_id','offer_id','job_posting_id'], null);
  $COL_DATE      = $pickCol($evCols, ['event_date','date','day','start_date'], null);
  $COL_TITLE     = $pickCol($evCols, ['title','name','event_title'], null);
  $COL_START     = $pickCol($evCols, ['start_time','time_start','start'], null);
  $COL_END       = $pickCol($evCols, ['end_time','time_end','end'], null);
  $COL_STATUS    = $pickCol($evCols, ['status','state'], null);
  $COL_NOTES     = $pickCol($evCols, ['notes','note','description','details'], null);

  if (!$COL_CLINIC_ID || !$COL_DATE || !$COL_TITLE) {
    $need = [];
    if (!$COL_CLINIC_ID) $need[] = 'clinic_user_id (o equivalente)';
    if (!$COL_DATE)      $need[] = 'event_date (o equivalente)';
    if (!$COL_TITLE)     $need[] = 'title (o equivalente)';
    throw new RuntimeException("clinic_events no tiene columnas mínimas: " . implode(', ', $need));
  }

  // ---------- Fecha seleccionada / navegación ----------
  $todayY = (int)date('Y');
  $todayM = (int)date('n');
  $todayD = (int)date('j');

  $year  = (int)($_GET['y'] ?? $todayY);
  $month = (int)($_GET['m'] ?? $todayM);
  $day   = (int)($_GET['d'] ?? $todayD);

  if ($month < 1)  { $month = 12; $year--; }
  if ($month > 12) { $month = 1;  $year++; }

  $daysInMonth = (int)cal_days_in_month(CAL_GREGORIAN, $month, $year);
  if ($day < 1) $day = 1;
  if ($day > $daysInMonth) $day = $daysInMonth;

  $firstDay = (int)date('w', strtotime(sprintf('%04d-%02d-01', $year, $month))); // 0=Dom
  $monthNames = $monthNames ?? ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

  $selectedDate = sprintf('%04d-%02d-%02d', $year, $month, $day);
  $rangeStart   = sprintf('%04d-%02d-01', $year, $month);
  $rangeEnd     = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);

  // ---------- Ofertas (jobs) para el select “Vincular a oferta” ----------
  $jobs = [];
  if ($tableExists('jobs')) {
    $stJobs = $pdo->prepare("SELECT id, title, status, created_at FROM jobs WHERE clinic_user_id = ? ORDER BY created_at DESC, id DESC");
    $stJobs->execute([(int)$clinicId]);
    $jobs = $stJobs->fetchAll(PDO::FETCH_ASSOC) ?: [];
  }

  // ---------- Acciones (guardar / borrar / editar) ----------
  $action = (string)($_POST['action'] ?? '');

  if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['create','update','delete'], true)) {
    // Normaliza input
    $eventId  = (int)($_POST['event_id'] ?? 0);
    $date     = trim((string)($_POST['date'] ?? $selectedDate));
    $jobIdRaw = trim((string)($_POST['job_id'] ?? ''));
    $jobId    = ($jobIdRaw === '' || $jobIdRaw === '0') ? null : (int)$jobIdRaw;

    $title    = trim((string)($_POST['title'] ?? ''));
    $start    = trim((string)($_POST['start_time'] ?? ''));
    $end      = trim((string)($_POST['end_time'] ?? ''));
    $status   = trim((string)($_POST['status'] ?? 'planned'));
    $notes    = trim((string)($_POST['notes'] ?? ''));

    // Validaciones básicas
    if ($action !== 'delete') {
      if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $errors[] = "Fecha inválida.";
      if ($title === '') $errors[] = "Título obligatorio.";
      if ($start !== '' && !preg_match('/^\d{2}:\d{2}$/', $start)) $errors[] = "Hora inicio inválida (HH:MM).";
      if ($end !== '' && !preg_match('/^\d{2}:\d{2}$/', $end)) $errors[] = "Hora fin inválida (HH:MM).";
      if ($start !== '' && $end !== '' && $end < $start) $errors[] = "Hora fin no puede ser menor a inicio.";
    }

    // Seguridad: evitar tocar eventos de otra clínica
    if ($action === 'update' || $action === 'delete') {
      if ($eventId <= 0) $errors[] = "event_id inválido.";
    }

    if (!$errors) {
      if ($action === 'create') {
        $fields = [$COL_CLINIC_ID => (int)$clinicId, $COL_DATE => $date, $COL_TITLE => $title];

        if ($COL_JOB_ID)  $fields[$COL_JOB_ID] = $jobId;
        if ($COL_START)   $fields[$COL_START]  = ($start !== '' ? $start : null);
        if ($COL_END)     $fields[$COL_END]    = ($end !== '' ? $end : null);
        if ($COL_STATUS)  $fields[$COL_STATUS] = ($status !== '' ? $status : null);
        if ($COL_NOTES)   $fields[$COL_NOTES]  = ($notes !== '' ? $notes : null);

        $colsSql = implode(',', array_map(fn($c) => "`$c`", array_keys($fields)));
        $valsSql = implode(',', array_fill(0, count($fields), '?'));

        $sql = "INSERT INTO clinic_events ($colsSql) VALUES ($valsSql)";
        $st = $pdo->prepare($sql);
        $st->execute(array_values($fields));

        $okMsg = "Evento creado.";
        // Mantenerte en el mismo día
        header("Location: ?tab=calendario&y={$year}&m={$month}&d=" . (int)substr($date, 8, 2));
        exit;
      }

      if ($action === 'update') {
        // Verifica propiedad
        $stOwn = $pdo->prepare("SELECT `$COL_ID` FROM clinic_events WHERE `$COL_ID`=? AND `$COL_CLINIC_ID`=? LIMIT 1");
        $stOwn->execute([$eventId, (int)$clinicId]);
        if (!$stOwn->fetchColumn()) {
          $errors[] = "No autorizado.";
        } else {
          $sets = [];
          $vals = [];

          $sets[] = "`$COL_DATE`=?";  $vals[] = $date;
          $sets[] = "`$COL_TITLE`=?"; $vals[] = $title;

          if ($COL_JOB_ID)  { $sets[] = "`$COL_JOB_ID`=?";  $vals[] = $jobId; }
          if ($COL_START)   { $sets[] = "`$COL_START`=?";   $vals[] = ($start !== '' ? $start : null); }
          if ($COL_END)     { $sets[] = "`$COL_END`=?";     $vals[] = ($end !== '' ? $end : null); }
          if ($COL_STATUS)  { $sets[] = "`$COL_STATUS`=?";  $vals[] = ($status !== '' ? $status : null); }
          if ($COL_NOTES)   { $sets[] = "`$COL_NOTES`=?";   $vals[] = ($notes !== '' ? $notes : null); }

          $vals[] = $eventId;
          $vals[] = (int)$clinicId;

          $sql = "UPDATE clinic_events SET " . implode(',', $sets) . " WHERE `$COL_ID`=? AND `$COL_CLINIC_ID`=? LIMIT 1";
          $st = $pdo->prepare($sql);
          $st->execute($vals);

          $okMsg = "Evento actualizado.";
          header("Location: ?tab=calendario&y={$year}&m={$month}&d=" . (int)substr($date, 8, 2));
          exit;
        }
      }

      if ($action === 'delete') {
        // Verifica propiedad + borra
        $sql = "DELETE FROM clinic_events WHERE `$COL_ID`=? AND `$COL_CLINIC_ID`=? LIMIT 1";
        $st = $pdo->prepare($sql);
        $st->execute([$eventId, (int)$clinicId]);
        $okMsg = "Evento eliminado.";
        header("Location: ?tab=calendario&y={$year}&m={$month}&d={$day}");
        exit;
      }
    }
  }

  // ---------- Cargar eventos del mes (para dots y listados) ----------
  $sqlMonth = "SELECT * FROM clinic_events
               WHERE `$COL_CLINIC_ID`=? AND `$COL_DATE` BETWEEN ? AND ?
               ORDER BY `$COL_DATE` ASC";
  if ($COL_START) $sqlMonth .= ", `$COL_START` ASC";
  $stMonth = $pdo->prepare($sqlMonth);
  $stMonth->execute([(int)$clinicId, $rangeStart, $rangeEnd]);
  $monthEvents = $stMonth->fetchAll(PDO::FETCH_ASSOC) ?: [];

  // Mapa date=>count para pintar el calendario
  $countByDate = [];
  foreach ($monthEvents as $ev) {
    $d = (string)($ev[$COL_DATE] ?? '');
    if ($d === '') continue;
    $countByDate[$d] = ($countByDate[$d] ?? 0) + 1;
  }

  // Eventos del día seleccionado
  $dayEvents = array_values(array_filter($monthEvents, function($ev) use ($COL_DATE, $selectedDate) {
    return (string)($ev[$COL_DATE] ?? '') === $selectedDate;
  }));

  // Si se está editando un evento
  $editId = (int)($_GET['edit'] ?? 0);
  $editing = null;

  if ($editId > 0) {
    $stOne = $pdo->prepare("SELECT * FROM clinic_events WHERE `$COL_ID`=? AND `$COL_CLINIC_ID`=? LIMIT 1");
    $stOne->execute([$editId, (int)$clinicId]);
    $editing = $stOne->fetch(PDO::FETCH_ASSOC) ?: null;
  }

  // KPIs (si quieres que se reflejen arriba)
  // Nota: index.php imprime $kpi... ya inicializados; aquí los actualizamos.
  $GLOBALS['kpiUpcoming'] = (int)count($monthEvents);

} catch (Throwable $e) {
  error_log("CALENDAR MODULE ERROR: " . $e->getMessage());
  $errors[] = "No se pudo cargar la agenda.";
  // si quieres ver el error real un momento:
  // $errors[] = $e->getMessage();
}

// ---------- UI ----------
?>
<style>
  .sectionTitle{ display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:12px; }
  .sectionTitle h2{ margin:0; font-size:1.2rem; }

  .errBox{
    border-radius: 14px;
    border: 1px solid rgba(255, 99, 99, .28);
    background: rgba(120, 0, 0, .16);
    padding: 12px;
    margin-bottom: 12px;
    color: rgba(246,241,231,.92);
  }
  .okBox{
    border-radius: 14px;
    border: 1px solid rgba(46, 204, 113, .28);
    background: rgba(46, 204, 113, .12);
    padding: 12px;
    margin-bottom: 12px;
    color: rgba(246,241,231,.92);
  }

  .grid2{ display:grid; grid-template-columns: 1.1fr .9fr; gap: 12px; align-items:start; }
  .note{
    border: 1px solid rgba(255,255,255,.10);
    border-radius: 14px;
    background: rgba(0,0,0,.12);
    padding: 12px;
    color: rgba(246,241,231,.78);
    line-height:1.45;
    font-size:.92rem;
  }

  .calHeader{ display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:10px; flex-wrap:wrap; }
  .calNav{ display:flex; gap:8px; align-items:center; }

  .calGrid{
    border: 1px solid rgba(255,255,255,.10);
    border-radius: 14px;
    overflow:hidden;
  }
  .calWeek{
    display:grid;
    grid-template-columns: repeat(7, 1fr);
    background: rgba(0,0,0,.12);
  }
  .calWeek div{
    padding: 8px 6px;
    font-size:.8rem;
    color: rgba(246,241,231,.75);
    text-align:center;
    border-right: 1px solid rgba(255,255,255,.06);
  }
  .calWeek div:last-child{ border-right:0; }

  .calDays{ display:grid; grid-template-columns: repeat(7, 1fr); }
  .calCell{
    min-height: 64px;
    padding: 8px 8px 6px;
    border-right: 1px solid rgba(255,255,255,.06);
    border-bottom: 1px solid rgba(255,255,255,.06);
    background: rgba(0,0,0,.10);
    text-decoration:none;
    color: inherit;
    display:block;
  }
  .calCell:nth-child(7n){ border-right:0; }
  .calCell:hover{ border-color: rgba(198,165,91,.18); background: rgba(198,165,91,.06); }

  .calDayNum{
    font-size:.9rem;
    color: rgba(246,241,231,.88);
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap: 6px;
  }
  .dot{
    width: 8px; height: 8px;
    border-radius: 999px;
    background: rgba(198,165,91,.85);
    box-shadow: 0 0 0 4px rgba(198,165,91,.14);
    flex: 0 0 auto;
  }
  .small{ font-size:.82rem; color: rgba(246,241,231,.70); margin-top: 6px; line-height:1.2; }

  .panel{
    border: 1px solid rgba(255,255,255,.10);
    border-radius: 14px;
    background: rgba(0,0,0,.12);
    padding: 12px;
  }
  .panel h3{ margin:0 0 8px; font-size: 1.05rem; }
  .row{ display:grid; grid-template-columns: 1fr 1fr; gap: 10px; }
  .field label{ display:block; margin: 10px 0 6px; color: rgba(246,241,231,.86); font-size:.9rem; }
  .input, .select, .textarea{
    width:100%;
    padding: 10px 12px;
    border-radius: 12px;
    border: 1px solid rgba(255,255,255,.14);
    background: rgba(0,0,0,.18);
    color: rgba(246,241,231,.92);
    outline:none;
    font-family: inherit;
  }
  .textarea{ min-height: 74px; resize: vertical; }
  .btnRow{ display:flex; gap: 10px; justify-content:flex-end; flex-wrap:wrap; margin-top: 12px; }
  .btnGhost{ background: rgba(255,255,255,.06); color: rgba(246,241,231,.88); border: 1px solid rgba(255,255,255,.10); border-radius: 14px; padding: 10px 14px; font-weight:700; cursor:pointer; font-family:inherit; }
  .btnGold{ background: linear-gradient(180deg, rgba(198,165,91,.92), rgba(198,165,91,.72)); color:#0F1114; border:1px solid rgba(0,0,0,.20); border-radius:14px; padding: 10px 14px; font-weight:700; cursor:pointer; font-family:inherit; }
  .btnDanger{ background: rgba(255,99,99,.14); color: rgba(246,241,231,.92); border:1px solid rgba(255,99,99,.26); border-radius:14px; padding: 10px 14px; font-weight:700; cursor:pointer; font-family:inherit; }

  .list{
    margin-top: 10px;
    border-top: 1px solid rgba(255,255,255,.10);
    padding-top: 10px;
  }
  .item{
    border: 1px solid rgba(255,255,255,.10);
    background: rgba(0,0,0,.10);
    border-radius: 14px;
    padding: 10px;
    margin-bottom: 10px;
  }
  .item strong{ display:block; margin-bottom: 4px; }
  .item .meta{ font-size:.86rem; color: rgba(246,241,231,.72); display:flex; gap:10px; flex-wrap:wrap; }
  .item .actions{ margin-top: 10px; display:flex; gap: 8px; flex-wrap:wrap; }
  .aBtn{
    display:inline-flex;
    padding: 8px 10px;
    border-radius: 12px;
    border: 1px solid rgba(255,255,255,.12);
    background: rgba(0,0,0,.14);
    color: rgba(246,241,231,.88);
    text-decoration:none;
    font-size:.9rem;
    font-weight:700;
  }
  .aBtn:hover{ border-color: rgba(198,165,91,.30); }

  @media (max-width: 980px){
    .grid2{ grid-template-columns:1fr; }
  }
</style>

<div class="sectionTitle">
  <h2>Calendario de agenda</h2>
  <div class="calNav">
    <a class="btnGhost" href="?tab=calendario&y=<?= (int)$year ?>&m=<?= (int)$month-1 ?>&d=<?= (int)$day ?>">‹</a>
    <a class="btnGhost" href="?tab=calendario&y=<?= (int)date('Y') ?>&m=<?= (int)date('n') ?>&d=<?= (int)date('j') ?>">Hoy</a>
    <a class="btnGhost" href="?tab=calendario&y=<?= (int)$year ?>&m=<?= (int)$month+1 ?>&d=<?= (int)$day ?>">›</a>
  </div>
</div>

<?php if ($okMsg): ?>
  <div class="okBox"><?= h($okMsg) ?></div>
<?php endif; ?>

<?php if ($errors): ?>
  <div class="errBox">
    <strong>Revisa lo siguiente:</strong>
    <ul style="margin:8px 0 0; padding-left:18px;">
      <?php foreach ($errors as $e): ?>
        <li><?= h((string)$e) ?></li>
      <?php endforeach; ?>
    </ul>

    <div style="margin-top:10px; font-size:.9rem; color:rgba(246,241,231,.75);">
      Si el error dice “tabla clinic_events…”, crea la tabla (te dejo SQL al final).
    </div>
  </div>
<?php endif; ?>

<div class="grid2">
  <!-- Calendario compacto -->
  <div class="panel">
    <div class="calHeader">
      <div class="muted"><?= h($monthNames[$month] ?? '') ?> <?= (int)$year ?></div>
    </div>

    <div class="calGrid">
      <div class="calWeek">
        <div>Dom</div><div>Lun</div><div>Mar</div><div>Mié</div><div>Jue</div><div>Vie</div><div>Sáb</div>
      </div>

      <div class="calDays">
        <?php
          // celdas vacías antes del primer día
          for ($i=0; $i<$firstDay; $i++){
            echo '<div class="calCell" style="background:rgba(0,0,0,.06)"></div>';
          }

          for ($d=1; $d<=$daysInMonth; $d++){
            $dateKey = sprintf('%04d-%02d-%02d', $year, $month, $d);
            $count = (int)($countByDate[$dateKey] ?? 0);
            $isSel = ($dateKey === $selectedDate);

            $style = $isSel
              ? 'border-color: rgba(198,165,91,.35); background: rgba(198,165,91,.10);'
              : '';

            echo '<a class="calCell" style="'.$style.'" href="?tab=calendario&y='.$year.'&m='.$month.'&d='.$d.'">';
            echo '<div class="calDayNum"><span>'.$d.'</span>';
            if ($count > 0) echo '<span class="dot" title="'.$count.' evento(s)"></span>';
            echo '</div>';
            echo ($count > 0) ? '<div class="small">'.$count.' evento(s)</div>' : '<div class="small">&nbsp;</div>';
            echo '</a>';
          }
        ?>
      </div>
    </div>
  </div>

  <!-- Panel derecho: agenda del día + crear/editar -->
  <div class="panel">
    <h3>Agenda del día</h3>
    <div class="muted" style="margin-bottom:10px;"><?= h($selectedDate) ?></div>

    <?php if (!empty($dayEvents)): ?>
      <div class="list">
        <?php foreach ($dayEvents as $ev): ?>
          <?php
            $eid   = (int)($ev[$COL_ID] ?? 0);
            $ttl   = (string)($ev[$COL_TITLE] ?? '');
            $stt   = $COL_START ? (string)($ev[$COL_START] ?? '') : '';
            $ett   = $COL_END   ? (string)($ev[$COL_END] ?? '') : '';
            $jid   = $COL_JOB_ID ? (int)($ev[$COL_JOB_ID] ?? 0) : 0;

            $jobLabel = '';
            if ($jid > 0) {
              foreach ($jobs as $j) {
                if ((int)$j['id'] === $jid) { $jobLabel = (string)$j['title']; break; }
              }
            }
          ?>
          <div class="item">
            <strong><?= h($ttl) ?></strong>
            <div class="meta">
              <?php if ($stt !== '' || $ett !== ''): ?>
                <span>⏱ <?= h(trim($stt . ' - ' . $ett)) ?></span>
              <?php endif; ?>
              <?php if ($jobLabel !== ''): ?>
                <span>🔗 Oferta: <?= h($jobLabel) ?></span>
              <?php endif; ?>
            </div>
            <div class="actions">
              <a class="aBtn" href="?tab=calendario&y=<?= (int)$year ?>&m=<?= (int)$month ?>&d=<?= (int)$day ?>&edit=<?= (int)$eid ?>">Editar</a>
              <form method="post" style="display:inline;">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="event_id" value="<?= (int)$eid ?>">
                <button class="btnDanger" type="submit" onclick="return confirm('¿Eliminar este evento?');">Eliminar</button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="muted" style="margin-bottom:12px;">Sin eventos para este día.</div>
    <?php endif; ?>

    <div style="border-top:1px solid rgba(255,255,255,.10); padding-top:12px; margin-top:10px;">
      <h3 style="margin-bottom:8px;"><?= $editing ? 'Editar evento' : 'Crear evento' ?></h3>

      <form method="post">
        <input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>">
        <input type="hidden" name="event_id" value="<?= (int)($editing[$COL_ID] ?? 0) ?>">

        <div class="field">
          <label>Fecha</label>
          <input class="input" name="date" type="date"
                 value="<?= h((string)($editing[$COL_DATE] ?? $selectedDate)) ?>" required>
        </div>

        <div class="field">
          <label>Vincular a oferta (opcional)</label>
          <select class="select" name="job_id">
            <option value="0">Sin oferta</option>
            <?php foreach ($jobs as $j): ?>
              <?php
                $selJ = (int)($editing[$COL_JOB_ID] ?? 0);
                $jid = (int)$j['id'];
              ?>
              <option value="<?= $jid ?>" <?= ($selJ === $jid ? 'selected' : '') ?>>
                <?= h((string)$j['title']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <div class="muted" style="margin-top:6px; font-size:.86rem;">
            Si no aparecen ofertas aquí, primero crea una oferta en la pestaña “Ofertas”.
          </div>
        </div>

        <div class="field">
          <label>Título</label>
          <input class="input" name="title" placeholder="Ej: Consulta / Procedimiento / Entrevista"
                 value="<?= h((string)($editing[$COL_TITLE] ?? '')) ?>" required>
        </div>

        <div class="row">
          <div class="field">
            <label>Hora inicio (opcional)</label>
            <input class="input" name="start_time" type="time"
                   value="<?= $COL_START ? h((string)($editing[$COL_START] ?? '')) : '' ?>">
          </div>
          <div class="field">
            <label>Hora fin (opcional)</label>
            <input class="input" name="end_time" type="time"
                   value="<?= $COL_END ? h((string)($editing[$COL_END] ?? '')) : '' ?>">
          </div>
        </div>

        <div class="row">
          <div class="field">
            <label>Estado</label>
            <select class="select" name="status">
              <?php
                $curStatus = $COL_STATUS ? (string)($editing[$COL_STATUS] ?? 'planned') : 'planned';
                $opts = ['planned'=>'Planificado','confirmed'=>'Confirmado','cancelled'=>'Cancelado','completed'=>'Completado'];
              ?>
              <?php foreach ($opts as $k=>$lbl): ?>
                <option value="<?= h($k) ?>" <?= ($curStatus === $k ? 'selected' : '') ?>><?= h($lbl) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label>Notas (opcional)</label>
            <input class="input" name="notes" placeholder="Ej: traer documentos / confirmar con paciente"
                   value="<?= $COL_NOTES ? h((string)($editing[$COL_NOTES] ?? '')) : '' ?>">
          </div>
        </div>

        <div class="btnRow">
          <?php if ($editing): ?>
            <a class="btnGhost" href="?tab=calendario&y=<?= (int)$year ?>&m=<?= (int)$month ?>&d=<?= (int)$day ?>">Cancelar edición</a>
          <?php else: ?>
            <button class="btnGhost" type="reset">Limpiar</button>
          <?php endif; ?>
          <button class="btnGold" type="submit">Guardar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php
// ---------- Si no existe clinic_events o faltan columnas: SQL recomendado ----------
?>
<div class="note" style="margin-top:12px;">
  <strong>Si te sale “tabla clinic_events…”</strong><br>
  Crea una tabla compatible como esta (en phpMyAdmin → SQL). Ajusta si ya tienes otra.
  <pre style="white-space:pre-wrap; margin:10px 0 0; font-size:.85rem; color:rgba(246,241,231,.82);">
CREATE TABLE IF NOT EXISTS clinic_events (
  id INT AUTO_INCREMENT PRIMARY KEY,
  clinic_user_id INT NOT NULL,
  job_id INT NULL,
  event_date DATE NOT NULL,
  title VARCHAR(160) NOT NULL,
  start_time TIME NULL,
  end_time TIME NULL,
  status VARCHAR(30) NULL,
  notes VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_clinic_date (clinic_user_id, event_date),
  INDEX idx_job (job_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
  </pre>
</div>

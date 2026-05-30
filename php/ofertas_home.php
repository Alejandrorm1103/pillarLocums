<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../usuarios/db.php';

$cn = null;

if (isset($pdo) && $pdo instanceof PDO) {
    $cn = $pdo;
} elseif (isset($conn)) {
    $cn = $conn;
} elseif (isset($mysqli)) {
    $cn = $mysqli;
} elseif (isset($db)) {
    $cn = $db;
} elseif (function_exists('db')) {
    $cn = db();
} elseif (function_exists('getPDO')) {
    $cn = getPDO();
}

if (!$cn) {
    echo json_encode([
        'ok' => false,
        'offers' => [],
        'error' => 'No se encontró conexión válida en config/db.php'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function pickCol(array $cols, array $cands): ?string {
    foreach ($cands as $c) {
        if (in_array($c, $cols, true)) {
            return $c;
        }
    }
    return null;
}

function inferType(string $title, string $schedule = ''): string {
    $t = mb_strtolower(trim($title . ' ' . $schedule), 'UTF-8');

    if (
        str_contains($t, 'guardia') ||
        str_contains($t, 'urgencia') ||
        str_contains($t, 'night') ||
        str_contains($t, 'on-call')
    ) {
        return 'guardia';
    }

    if (
        str_contains($t, 'temporal') ||
        str_contains($t, 'sustit') ||
        str_contains($t, 'reemplazo') ||
        str_contains($t, 'temporary')
    ) {
        return 'temporal';
    }

    if (str_contains($t, 'locum')) {
        return 'locum';
    }

    return 'locum';
}

function inferSpecialty(string $title, string $desc = ''): string {
    $t = mb_strtolower(trim($title . ' ' . $desc), 'UTF-8');

    if (str_contains($t, 'pediatr')) {
        return 'pediatria';
    }

    if (str_contains($t, 'enfermer')) {
        return 'enfermeria';
    }

    if (str_contains($t, 'familia')) {
        return 'medicina de familia';
    }

    if (str_contains($t, 'anest')) {
        return 'anestesia';
    }

    if (str_contains($t, 'urgencia')) {
        return 'urgencias';
    }

    if (
        str_contains($t, 'médico general') ||
        str_contains($t, 'medico general') ||
        str_contains($t, 'general practitioner') ||
        preg_match('/\bgp\b/u', $t)
    ) {
        return 'medicina general';
    }

    return 'medicina general';
}

function buildBadge(?string $createdAt): string {
    $ts = !empty($createdAt) ? strtotime($createdAt) : time();
    $daysAgo = max(0, (int) floor((time() - $ts) / 86400));

    if ($daysAgo === 0) {
        return 'Nuevo';
    }

    if ($daysAgo === 1) {
        return 'Hace 1 día';
    }

    return 'Hace ' . $daysAgo . ' días';
}

try {
    $rows = [];
    $cols = [];

    if ($cn instanceof PDO) {
        $stc = $cn->query("SHOW COLUMNS FROM jobs");
        $meta = $stc ? $stc->fetchAll(PDO::FETCH_ASSOC) : [];
        foreach ($meta as $r) {
            $cols[] = (string) $r['Field'];
        }
    } else {
        $stc = $cn->query("SHOW COLUMNS FROM jobs");
        if ($stc) {
            while ($r = $stc->fetch_assoc()) {
                $cols[] = (string) $r['Field'];
            }
        }
    }

    if (!$cols) {
        throw new RuntimeException('No se pudieron leer las columnas de jobs.');
    }

    $COL_ID          = pickCol($cols, ['id']);
    $COL_TITLE       = pickCol($cols, ['title', 'name', 'position', 'job_title']);
    $COL_SCHEDULE    = pickCol($cols, ['schedule', 'horario', 'shift']);
    $COL_SALARY      = pickCol($cols, ['salary', 'pay', 'payment']);
    $COL_LOCATION    = pickCol($cols, ['location', 'ubicacion', 'city']);
    $COL_DESC        = pickCol($cols, ['description', 'details', 'descripcion']);
    $COL_STATUS      = pickCol($cols, ['status', 'estado']);
    $COL_CREATED_AT  = pickCol($cols, ['created_at', 'created', 'createdon']);
    $COL_CLINIC_ID   = pickCol($cols, ['clinic_user_id', 'clinic_id']);
    $COL_HOSPITAL_ID = pickCol($cols, ['hospital_user_id', 'hospital_id']);

    if (!$COL_ID || !$COL_TITLE || !$COL_SCHEDULE || !$COL_LOCATION) {
        throw new RuntimeException('La tabla jobs no tiene columnas mínimas requeridas.');
    }

    $selectCols = [$COL_ID, $COL_TITLE, $COL_SCHEDULE, $COL_LOCATION];

    if ($COL_SALARY) {
        $selectCols[] = $COL_SALARY;
    }
    if ($COL_DESC) {
        $selectCols[] = $COL_DESC;
    }
    if ($COL_STATUS) {
        $selectCols[] = $COL_STATUS;
    }
    if ($COL_CREATED_AT) {
        $selectCols[] = $COL_CREATED_AT;
    }
    if ($COL_CLINIC_ID) {
        $selectCols[] = $COL_CLINIC_ID;
    }
    if ($COL_HOSPITAL_ID) {
        $selectCols[] = $COL_HOSPITAL_ID;
    }

    $where = [];

    if ($COL_STATUS) {
        $where[] = "LOWER($COL_STATUS) IN ('active','activa','open')";
    }

    $sql = "SELECT " . implode(', ', array_unique($selectCols)) . " FROM jobs";

    if ($where) {
        $sql .= " WHERE " . implode(' AND ', $where);
    }

    if ($COL_CREATED_AT) {
        $sql .= " ORDER BY $COL_CREATED_AT DESC, $COL_ID DESC";
    } else {
        $sql .= " ORDER BY $COL_ID DESC";
    }

    $sql .= " LIMIT 12";

    if ($cn instanceof PDO) {
        $stmt = $cn->query($sql);
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    } else {
        $result = $cn->query($sql);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }
        }
    }

    $offers = [];

    foreach ($rows as $row) {
        $title    = trim((string) ($row[$COL_TITLE] ?? 'Oferta disponible'));
        $schedule = trim((string) ($row[$COL_SCHEDULE] ?? 'Horario por definir'));
        $location = trim((string) ($row[$COL_LOCATION] ?? ''));
        $desc     = $COL_DESC ? trim((string) ($row[$COL_DESC] ?? '')) : '';
        $created  = $COL_CREATED_AT ? (string) ($row[$COL_CREATED_AT] ?? '') : '';

        $clinic = 'Centro sanitario';

        if ($COL_HOSPITAL_ID && !empty($row[$COL_HOSPITAL_ID])) {
            $clinic = 'Hospital';
        } elseif ($COL_CLINIC_ID && !empty($row[$COL_CLINIC_ID])) {
            $clinic = 'Clínica';
        }

        $offers[] = [
            'title'      => $title,
            'type'       => inferType($title, $schedule),
            'specialty'  => inferSpecialty($title, $desc),
            'location'   => mb_strtolower($location, 'UTF-8'),
            'clinic'     => $clinic,
            'schedule'   => $schedule,
            'badge'      => buildBadge($created),
            'created_at' => $created
        ];
    }

    echo json_encode([
        'ok' => true,
        'offers' => $offers,
        'debug_count' => count($offers)
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;

} catch (Throwable $e) {
    echo json_encode([
        'ok' => false,
        'offers' => [],
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
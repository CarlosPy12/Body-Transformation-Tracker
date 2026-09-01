<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/config/bootstrap.php';

use App\Auth\AuthService;
use App\Auth\Authorization;
use App\Repositories\MeasurementRepository;
use App\Services\ImportService;
use App\Services\ResultsService;
use App\Import\MeasurementFingerprint;
use App\Support\Database;
use App\Support\Response;

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = trim((string) ($_GET['path'] ?? ''), '/');
$input = json_decode(file_get_contents('php://input') ?: '{}', true) ?: [];

function current_user_or_fail(AuthService $auth): array
{
    $user = $auth->user();
    if (!$user) {
        Response::fail('UNAUTHENTICATED', 'Accesso richiesto.', 401);
        exit;
    }
    return $user;
}

function require_csrf(AuthService $auth): void
{
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    if (!$auth->assertCsrf($token)) {
        Response::fail('CSRF_INVALID', 'Sessione non valida, ricarica la pagina.', 419);
        exit;
    }
}

try {
    $pdo = Database::pdo();
    $auth = new AuthService($pdo);

    if ($path === 'auth/login' && $method === 'POST') {
        if ($auth->login((string) ($input['email'] ?? ''), (string) ($input['password'] ?? ''))) {
            Response::ok(['user' => $auth->user(), 'csrf_token' => $auth->csrfToken()]);
            return;
        }
        Response::fail('LOGIN_FAILED', 'Email o password non corretti.', 422);
        return;
    }

    if ($path === 'auth/logout' && $method === 'POST') {
        require_csrf($auth);
        $auth->logout();
        Response::ok();
        return;
    }

    if ($path === 'auth/me') {
        $user = $auth->user();
        Response::ok(['user' => $user, 'csrf_token' => $user ? $auth->csrfToken() : null]);
        return;
    }

    $user = current_user_or_fail($auth);

    if ($path === 'dashboard') {
        $latest = (new MeasurementRepository($pdo))->latest((int) $user['id']);
        $metricSummary = dashboard_metric_summary($pdo, (int) $user['id']);
        $today = date('Y-m-d');
        $rangeStart = (!empty($_GET['start']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $_GET['start'])) ? $_GET['start'] . ' 00:00:00' : date('Y-m-d H:i:s', strtotime('-7 days'));
        $rangeEnd = (!empty($_GET['end']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $_GET['end'])) ? $_GET['end'] . ' 23:59:59' : date('Y-m-d H:i:s', strtotime('+7 days'));
        $steps = $pdo->prepare('SELECT steps FROM daily_steps WHERE user_id = ? AND step_date = ?');
        $steps->execute([$user['id'], $today]);
        $settings = $pdo->prepare('SELECT daily_steps_target FROM user_settings WHERE user_id = ? LIMIT 1');
        $settings->execute([$user['id']]);
        $nextInjection = $pdo->prepare('SELECT i.*, m.name AS medication_name FROM glp1_injections i JOIN glp1_medications m ON m.id = i.medication_id WHERE i.user_id = ? AND i.status = "scheduled" ORDER BY i.scheduled_at LIMIT 1');
        $nextInjection->execute([$user['id']]);
        $currentInjection = $pdo->prepare('SELECT COALESCE(i.administered_dose_mg, i.planned_dose_mg) AS dose_mg, i.administered_at, i.scheduled_at, m.name AS medication_name FROM glp1_injections i JOIN glp1_medications m ON m.id = i.medication_id WHERE i.user_id = ? AND i.status = "completed" ORDER BY COALESCE(i.administered_at, i.scheduled_at) DESC LIMIT 1');
        $currentInjection->execute([$user['id']]);
        $injectionCounts = $pdo->prepare('SELECT COALESCE(administered_dose_mg, planned_dose_mg) AS dose_mg, COUNT(*) AS total FROM glp1_injections WHERE user_id = ? AND status = "completed" GROUP BY COALESCE(administered_dose_mg, planned_dose_mg) ORDER BY dose_mg');
        $injectionCounts->execute([$user['id']]);
        $workouts = $pdo->prepare('SELECT COUNT(*) FROM workout_sessions WHERE user_id = ? AND status = "completed" AND COALESCE(completed_at, scheduled_at) BETWEEN ? AND ?');
        $workouts->execute([$user['id'], $rangeStart, $rangeEnd]);
        $plannedWorkouts = $pdo->prepare('SELECT COUNT(*) FROM workout_sessions WHERE user_id = ? AND scheduled_at BETWEEN ? AND ?');
        $plannedWorkouts->execute([$user['id'], $rangeStart, $rangeEnd]);
        $workoutCounts = $pdo->prepare('SELECT workout_type, SUM(status = "completed") AS completed, COUNT(*) AS scheduled FROM workout_sessions WHERE user_id = ? AND scheduled_at BETWEEN ? AND ? GROUP BY workout_type ORDER BY workout_type');
        $workoutCounts->execute([$user['id'], $rangeStart, $rangeEnd]);
        Response::ok([
            'latest_measurement' => $latest,
            'metric_summary' => $metricSummary,
            'steps_today' => (int) ($steps->fetchColumn() ?: 0),
            'steps_target' => (int) ($settings->fetchColumn() ?: 10000),
            'next_injection' => $nextInjection->fetch() ?: null,
            'current_injection' => $currentInjection->fetch() ?: null,
            'injection_counts' => $injectionCounts->fetchAll(),
            'completed_workouts_week' => (int) $workouts->fetchColumn(),
            'scheduled_workouts_week' => (int) $plannedWorkouts->fetchColumn(),
            'workout_counts_week' => $workoutCounts->fetchAll(),
        ]);
        return;
    }

    if ($path === 'results') {
        Response::ok((new ResultsService($pdo))->metric(
            (int) $user['id'],
            (string) ($_GET['metric'] ?? 'peso'),
            (string) ($_GET['range'] ?? '3m'),
            isset($_GET['start']) ? (string) $_GET['start'] : null,
            isset($_GET['end']) ? (string) $_GET['end'] : null
        ));
        return;
    }

    if ($path === 'measurements' && $method === 'GET') {
        $limit = min(500, max(25, (int) ($_GET['limit'] ?? 250)));
        $stmt = $pdo->prepare('SELECT * FROM body_measurements WHERE user_id = ? ORDER BY measured_at DESC, id DESC LIMIT ' . $limit);
        $stmt->execute([$user['id']]);
        Response::ok($stmt->fetchAll());
        return;
    }

    if ($path === 'measurements' && $method === 'POST') {
        require_csrf($auth);
        $fields = measurement_fields();
        $row = ['measured_at' => normalize_measured_at((string) ($input['measured_at'] ?? date('Y-m-d H:i:s')))];
        foreach ($fields as $field) {
            $row[$field] = decimal_or_null($input[$field] ?? null);
        }
        $row['measurement_hash'] = MeasurementFingerprint::hash((int) $user['id'], $row);
        $columns = implode(', ', array_merge(['user_id'], array_keys($row), ['source', 'import_id']));
        $marks = implode(', ', array_fill(0, count($row) + 3, '?'));
        $stmt = $pdo->prepare("INSERT IGNORE INTO body_measurements ({$columns}) VALUES ({$marks})");
        $stmt->execute(array_merge([$user['id']], array_values($row), ['manual', null]));
        Response::ok(['id' => (int) $pdo->lastInsertId(), 'inserted' => $stmt->rowCount() === 1]);
        return;
    }

    if (preg_match('#^measurements/(\d+)$#', $path, $m) && $method === 'PUT') {
        require_csrf($auth);
        $measurementId = (int) $m[1];
        $existing = $pdo->prepare('SELECT * FROM body_measurements WHERE id = ? AND user_id = ? LIMIT 1');
        $existing->execute([$measurementId, $user['id']]);
        $row = $existing->fetch();
        if (!$row) {
            Response::fail('NOT_FOUND', 'Misurazione non trovata.', 404);
            return;
        }

        $fields = measurement_fields();
        $updated = ['measured_at' => normalize_measured_at((string) ($input['measured_at'] ?? $row['measured_at']))];
        foreach ($fields as $field) {
            $updated[$field] = decimal_or_null($input[$field] ?? null);
        }
        $updated['measurement_hash'] = MeasurementFingerprint::hash((int) $user['id'], $updated);

        $assignments = array_map(static fn (string $field): string => "{$field} = ?", array_merge(['measured_at'], $fields, ['measurement_hash']));
        $sql = 'UPDATE body_measurements SET ' . implode(', ', $assignments) . ', source = IF(source = "manual", "manual", source), updated_at = UTC_TIMESTAMP() WHERE id = ? AND user_id = ?';
        $values = [];
        foreach (array_merge(['measured_at'], $fields, ['measurement_hash']) as $field) {
            $values[] = $updated[$field];
        }
        $values[] = $measurementId;
        $values[] = $user['id'];
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($values);
            Response::ok(['updated' => $stmt->rowCount(), 'measurement_hash' => $updated['measurement_hash']]);
        } catch (PDOException $e) {
            Response::fail('DUPLICATE_MEASUREMENT', 'Esiste già una misurazione identica per questo utente.', 409);
        }
        return;
    }

    if (preg_match('#^measurements/(\d+)$#', $path, $m) && $method === 'DELETE') {
        require_csrf($auth);
        $stmt = $pdo->prepare('DELETE FROM body_measurements WHERE id = ? AND user_id = ?');
        $stmt->execute([(int) $m[1], $user['id']]);
        Response::ok(['deleted' => $stmt->rowCount()]);
        return;
    }

    if ($path === 'imports/preview' && $method === 'POST') {
        require_csrf($auth);
        $targetUserId = (int) ($_POST['user_id'] ?? $user['id']);
        if (!Authorization::canAccessUser($user, $targetUserId)) {
            Response::fail('FORBIDDEN', 'Non puoi importare dati per questo utente.', 403);
            return;
        }
        if (empty($_FILES['file']['tmp_name'])) {
            Response::fail('VALIDATION_ERROR', 'Seleziona un file CSV.');
            return;
        }
        $content = file_get_contents($_FILES['file']['tmp_name']);
        Response::ok((new ImportService($pdo))->preview($targetUserId, $_FILES['file']['name'], $content ?: ''));
        return;
    }

    if ($path === 'imports/confirm' && $method === 'POST') {
        require_csrf($auth);
        $targetUserId = (int) ($_POST['user_id'] ?? $user['id']);
        if (!Authorization::canAccessUser($user, $targetUserId)) {
            Response::fail('FORBIDDEN', 'Non puoi importare dati per questo utente.', 403);
            return;
        }
        if (empty($_FILES['file']['tmp_name'])) {
            Response::fail('VALIDATION_ERROR', 'Seleziona un file CSV.');
            return;
        }
        $accepted = json_decode((string) ($_POST['accepted_hashes'] ?? '[]'), true) ?: [];
        $content = file_get_contents($_FILES['file']['tmp_name']);
        Response::ok((new ImportService($pdo))->import($targetUserId, $_FILES['file']['name'], $content ?: '', $accepted));
        return;
    }

    if ($path === 'imports/shared-preview' && $method === 'POST') {
        require_csrf($auth);
        if (empty($_SESSION['shared_import']['path']) || !is_file($_SESSION['shared_import']['path'])) {
            Response::fail('NOT_FOUND', 'Nessun CSV condiviso trovato.');
            return;
        }
        $targetUserId = (int) ($input['user_id'] ?? $user['id']);
        if (!Authorization::canAccessUser($user, $targetUserId)) {
            Response::fail('FORBIDDEN', 'Non puoi importare dati per questo utente.', 403);
            return;
        }
        $content = file_get_contents($_SESSION['shared_import']['path']);
        Response::ok((new ImportService($pdo))->preview($targetUserId, $_SESSION['shared_import']['name'], $content ?: ''));
        return;
    }

    if ($path === 'imports/shared-confirm' && $method === 'POST') {
        require_csrf($auth);
        if (empty($_SESSION['shared_import']['path']) || !is_file($_SESSION['shared_import']['path'])) {
            Response::fail('NOT_FOUND', 'Nessun CSV condiviso trovato.');
            return;
        }
        $targetUserId = (int) ($input['user_id'] ?? $user['id']);
        if (!Authorization::canAccessUser($user, $targetUserId)) {
            Response::fail('FORBIDDEN', 'Non puoi importare dati per questo utente.', 403);
            return;
        }
        $content = file_get_contents($_SESSION['shared_import']['path']);
        $done = (new ImportService($pdo))->import($targetUserId, $_SESSION['shared_import']['name'], $content ?: '', $input['accepted_hashes'] ?? []);
        @unlink($_SESSION['shared_import']['path']);
        unset($_SESSION['shared_import']);
        Response::ok($done);
        return;
    }

    if ($path === 'goals' && $method === 'GET') {
        $stmt = $pdo->prepare('SELECT * FROM goals WHERE user_id = ? ORDER BY is_active DESC, created_at DESC');
        $stmt->execute([$user['id']]);
        Response::ok($stmt->fetchAll());
        return;
    }

    if ($path === 'goals' && $method === 'POST') {
        require_csrf($auth);
        $pdo->prepare('UPDATE goals SET is_active = 0 WHERE user_id = ? AND metric_key = ?')->execute([$user['id'], $input['metric_key'] ?? 'peso']);
        $stmt = $pdo->prepare('INSERT INTO goals(user_id, metric_key, target_value, target_date, is_active) VALUES (?, ?, ?, ?, 1)');
        $stmt->execute([$user['id'], $input['metric_key'] ?? 'peso', $input['target_value'] ?? 0, $input['target_date'] ?? null]);
        Response::ok(['id' => (int) $pdo->lastInsertId()]);
        return;
    }

    if ($path === 'steps' && $method === 'POST') {
        require_csrf($auth);
        $date = normalize_date((string) ($input['step_date'] ?? date('Y-m-d')));
        $steps = max(0, (int) ($input['steps'] ?? 0));
        $stmt = $pdo->prepare('INSERT INTO daily_steps(user_id, step_date, steps, source, source_file_name, synced_at) VALUES (?, ?, ?, "manual", NULL, UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE steps = VALUES(steps), source = VALUES(source), synced_at = UTC_TIMESTAMP()');
        $stmt->execute([$user['id'], $date, $steps]);
        Response::ok(['saved' => true]);
        return;
    }

    if ($path === 'steps' && $method === 'GET') {
        $sql = 'SELECT * FROM daily_steps WHERE user_id = ?';
        $values = [$user['id']];
        if (!empty($_GET['start']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $_GET['start'])) {
            $sql .= ' AND step_date >= ?';
            $values[] = $_GET['start'];
        }
        if (!empty($_GET['end']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $_GET['end'])) {
            $sql .= ' AND step_date <= ?';
            $values[] = $_GET['end'];
        }
        $sql .= ' ORDER BY step_date DESC LIMIT 240';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);
        Response::ok($stmt->fetchAll());
        return;
    }

    if (preg_match('#^steps/(\d+)$#', $path, $m) && $method === 'PUT') {
        require_csrf($auth);
        $date = normalize_date((string) ($input['step_date'] ?? date('Y-m-d')));
        $steps = max(0, (int) ($input['steps'] ?? 0));
        $stmt = $pdo->prepare('UPDATE daily_steps SET step_date = ?, steps = ?, source = "manual", synced_at = UTC_TIMESTAMP() WHERE id = ? AND user_id = ?');
        $stmt->execute([$date, $steps, (int) $m[1], $user['id']]);
        Response::ok(['updated' => $stmt->rowCount()]);
        return;
    }

    if ($path === 'injections' && $method === 'GET') {
        $sql = 'SELECT i.*, m.name AS medication_name FROM glp1_injections i JOIN glp1_medications m ON m.id = i.medication_id WHERE i.user_id = ?';
        $values = [$user['id']];
        if (!empty($_GET['start']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $_GET['start'])) {
            $sql .= ' AND i.scheduled_at >= ?';
            $values[] = $_GET['start'] . ' 00:00:00';
        }
        if (!empty($_GET['end']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $_GET['end'])) {
            if (!empty($_GET['include_future'])) {
                $sql .= ' AND (i.scheduled_at <= ? OR (i.status = "scheduled" AND i.scheduled_at >= CURDATE()))';
            } else {
                $sql .= ' AND i.scheduled_at <= ?';
            }
            $values[] = $_GET['end'] . ' 23:59:59';
        }
        $sql .= ' ORDER BY i.scheduled_at DESC LIMIT 200';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);
        Response::ok($stmt->fetchAll());
        return;
    }

    if ($path === 'injections/defaults') {
        $stmt = $pdo->prepare('SELECT i.planned_dose_mg, m.name AS medication_name FROM glp1_injections i JOIN glp1_medications m ON m.id = i.medication_id WHERE i.user_id = ? ORDER BY COALESCE(i.administered_at, i.scheduled_at) DESC, i.id DESC LIMIT 1');
        $stmt->execute([$user['id']]);
        Response::ok($stmt->fetch() ?: ['medication_name' => 'Mounjaro', 'planned_dose_mg' => 7.5]);
        return;
    }

    if ($path === 'injections' && $method === 'POST') {
        require_csrf($auth);
        $med = medication_id($pdo, (int) $user['id'], (string) ($input['medication_name'] ?? 'Mounjaro'));
        $dose = decimal_or_null($input['planned_dose_mg'] ?? null) ?? 0.0;
        $status = !empty($input['completed']) ? 'completed' : 'scheduled';
        $dates = injection_schedule_dates($input);
        $reminders = reminder_payload($pdo, 'glp1_injections', $input, 1440);
        $columns = array_merge(['user_id', 'medication_id', 'scheduled_at', 'administered_at', 'planned_dose_mg', 'administered_dose_mg', 'status', 'notes'], array_keys($reminders));
        $marks = implode(', ', array_fill(0, count($columns), '?'));
        $exists = $pdo->prepare('SELECT id FROM glp1_injections WHERE user_id = ? AND medication_id = ? AND scheduled_at = ? AND planned_dose_mg = ? LIMIT 1');
        $stmt = $pdo->prepare('INSERT INTO glp1_injections(' . implode(', ', $columns) . ") VALUES ({$marks})");
        $created = 0;
        $skipped = 0;
        foreach ($dates as $scheduledAt) {
            $exists->execute([$user['id'], $med, $scheduledAt, $dose]);
            if ($exists->fetchColumn()) {
                $skipped++;
                continue;
            }
            $administeredAt = $status === 'completed' ? $scheduledAt : null;
            $administeredDose = $status === 'completed' ? $dose : null;
            $stmt->execute(array_merge([$user['id'], $med, $scheduledAt, $administeredAt, $dose, $administeredDose, $status, $input['notes'] ?? null], array_values($reminders)));
            $created++;
        }
        Response::ok(['created' => $created, 'skipped' => $skipped]);
        return;
    }

    if (preg_match('#^injections/(\d+)/complete$#', $path, $m) && $method === 'POST') {
        require_csrf($auth);
        $stmt = $pdo->prepare('UPDATE glp1_injections SET status = "completed", administered_at = ?, administered_dose_mg = COALESCE(?, planned_dose_mg) WHERE id = ? AND user_id = ?');
        $stmt->execute([$input['administered_at'] ?? date('Y-m-d H:i:s'), $input['administered_dose_mg'] ?? null, $m[1], $user['id']]);
        Response::ok(['updated' => $stmt->rowCount()]);
        return;
    }

    if (preg_match('#^injections/(\d+)$#', $path, $m) && $method === 'PUT') {
        require_csrf($auth);
        $med = medication_id($pdo, (int) $user['id'], (string) ($input['medication_name'] ?? 'Mounjaro'));
        $scheduledAt = normalize_measured_at(str_replace('T', ' ', (string) ($input['scheduled_at'] ?? date('Y-m-d H:i:s'))));
        $dose = decimal_or_null($input['planned_dose_mg'] ?? null) ?? 0.0;
        $status = !empty($input['completed']) ? 'completed' : 'scheduled';
        $administeredAt = $status === 'completed' ? $scheduledAt : null;
        $administeredDose = $status === 'completed' ? $dose : null;
        $reminders = reminder_payload($pdo, 'glp1_injections', $input, 1440);
        $assignments = ['medication_id = ?', 'scheduled_at = ?', 'administered_at = ?', 'planned_dose_mg = ?', 'administered_dose_mg = ?', 'status = ?', 'notes = ?'];
        foreach (array_keys($reminders) as $column) {
            $assignments[] = $column . ' = ?';
        }
        $stmt = $pdo->prepare('UPDATE glp1_injections SET ' . implode(', ', $assignments) . ' WHERE id = ? AND user_id = ?');
        $stmt->execute(array_merge([$med, $scheduledAt, $administeredAt, $dose, $administeredDose, $status, $input['notes'] ?? null], array_values($reminders), [(int) $m[1], $user['id']]));
        Response::ok(['updated' => $stmt->rowCount()]);
        return;
    }

    if (preg_match('#^injections/(\d+)$#', $path, $m) && $method === 'DELETE') {
        require_csrf($auth);
        $stmt = $pdo->prepare('DELETE FROM glp1_injections WHERE id = ? AND user_id = ?');
        $stmt->execute([(int) $m[1], $user['id']]);
        Response::ok(['deleted' => $stmt->rowCount()]);
        return;
    }

    if ($path === 'workouts' && $method === 'GET') {
        $sql = 'SELECT * FROM workout_sessions WHERE user_id = ?';
        $values = [$user['id']];
        if (!empty($_GET['start']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $_GET['start'])) {
            $sql .= ' AND scheduled_at >= ?';
            $values[] = $_GET['start'] . ' 00:00:00';
        }
        if (!empty($_GET['end']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $_GET['end'])) {
            if (!empty($_GET['include_future'])) {
                $sql .= ' AND (scheduled_at <= ? OR (status = "scheduled" AND scheduled_at >= CURDATE()))';
            } else {
                $sql .= ' AND scheduled_at <= ?';
            }
            $values[] = $_GET['end'] . ' 23:59:59';
        }
        $sql .= ' ORDER BY scheduled_at DESC LIMIT 200';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);
        Response::ok($stmt->fetchAll());
        return;
    }

    if ($path === 'workouts' && $method === 'POST') {
        require_csrf($auth);
        $dates = workout_schedule_dates($input);
        $completed = !empty($input['completed']);
        $status = $completed ? 'completed' : 'scheduled';
        $reminders = reminder_payload($pdo, 'workout_sessions', $input, 60);
        $columns = array_merge(['user_id', 'scheduled_at', 'completed_at', 'workout_type', 'duration_minutes', 'status', 'notes'], array_keys($reminders));
        $marks = implode(', ', array_fill(0, count($columns), '?'));
        $exists = $pdo->prepare('SELECT id FROM workout_sessions WHERE user_id = ? AND scheduled_at = ? AND workout_type = ? LIMIT 1');
        $stmt = $pdo->prepare('INSERT INTO workout_sessions(' . implode(', ', $columns) . ") VALUES ({$marks})");
        $created = 0;
        $skipped = 0;
        foreach ($dates as $scheduledAt) {
            $exists->execute([$user['id'], $scheduledAt, $input['workout_type'] ?? 'Allenamento']);
            if ($exists->fetchColumn()) {
                $skipped++;
                continue;
            }
            $completedAt = $completed ? $scheduledAt : null;
            $stmt->execute(array_merge([$user['id'], $scheduledAt, $completedAt, $input['workout_type'] ?? 'Allenamento', int_or_null($input['duration_minutes'] ?? null), $status, $input['notes'] ?? null], array_values($reminders)));
            $created++;
        }
        Response::ok(['id' => (int) $pdo->lastInsertId(), 'created' => $created, 'skipped' => $skipped]);
        return;
    }

    if (preg_match('#^workouts/(\d+)/complete$#', $path, $m) && $method === 'POST') {
        require_csrf($auth);
        $stmt = $pdo->prepare('UPDATE workout_sessions SET status = "completed", completed_at = ?, duration_minutes = COALESCE(?, duration_minutes), calories_burned = COALESCE(?, calories_burned), notes = COALESCE(?, notes) WHERE id = ? AND user_id = ?');
        $stmt->execute([$input['completed_at'] ?? date('Y-m-d H:i:s'), $input['duration_minutes'] ?? null, $input['calories_burned'] ?? null, $input['notes'] ?? null, $m[1], $user['id']]);
        Response::ok(['updated' => $stmt->rowCount()]);
        return;
    }

    if (preg_match('#^workouts/(\d+)$#', $path, $m) && $method === 'PUT') {
        require_csrf($auth);
        $scheduledAt = normalize_measured_at(str_replace('T', ' ', (string) ($input['scheduled_at'] ?? date('Y-m-d H:i:s'))));
        $completed = !empty($input['completed']);
        $reminders = reminder_payload($pdo, 'workout_sessions', $input, 60);
        $assignments = ['scheduled_at = ?', 'completed_at = ?', 'workout_type = ?', 'duration_minutes = ?', 'status = ?', 'notes = ?'];
        foreach (array_keys($reminders) as $column) {
            $assignments[] = $column . ' = ?';
        }
        $stmt = $pdo->prepare('UPDATE workout_sessions SET ' . implode(', ', $assignments) . ' WHERE id = ? AND user_id = ?');
        $stmt->execute(array_merge([$scheduledAt, $completed ? $scheduledAt : null, $input['workout_type'] ?? 'Allenamento', int_or_null($input['duration_minutes'] ?? null), $completed ? 'completed' : 'scheduled', $input['notes'] ?? null], array_values($reminders), [(int) $m[1], $user['id']]));
        Response::ok(['updated' => $stmt->rowCount()]);
        return;
    }

    if (preg_match('#^workouts/(\d+)$#', $path, $m) && $method === 'DELETE') {
        require_csrf($auth);
        $stmt = $pdo->prepare('DELETE FROM workout_sessions WHERE id = ? AND user_id = ?');
        $stmt->execute([(int) $m[1], $user['id']]);
        Response::ok(['deleted' => $stmt->rowCount()]);
        return;
    }

    if ($path === 'calendar') {
        $month = $_GET['month'] ?? date('Y-m');
        $from = $month . '-01';
        $to = date('Y-m-t', strtotime($from));
        $events = [];
        foreach ([
            ['body_measurements', 'measured_at', 'misurazione', 'SELECT *, DATE(measured_at) AS event_date FROM body_measurements WHERE user_id = ? AND DATE(measured_at) BETWEEN ? AND ?'],
            ['glp1_injections', 'scheduled_at', 'iniezione', 'SELECT i.*, m.name AS medication_name, DATE(i.scheduled_at) AS event_date FROM glp1_injections i JOIN glp1_medications m ON m.id = i.medication_id WHERE i.user_id = ? AND DATE(i.scheduled_at) BETWEEN ? AND ?'],
            ['workout_sessions', 'scheduled_at', 'allenamento', 'SELECT *, DATE(scheduled_at) AS event_date FROM workout_sessions WHERE user_id = ? AND DATE(scheduled_at) BETWEEN ? AND ?'],
            ['daily_steps', 'step_date', 'passi', 'SELECT *, DATE(step_date) AS event_date FROM daily_steps WHERE user_id = ? AND DATE(step_date) BETWEEN ? AND ?'],
        ] as [$table, $column, $type, $sql]) {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$user['id'], $from, $to]);
            foreach ($stmt->fetchAll() as $row) {
                $row['type'] = $type;
                $events[] = $row;
            }
        }
        Response::ok($events);
        return;
    }

    if ($path === 'push/subscribe' && $method === 'POST') {
        require_csrf($auth);
        $stmt = $pdo->prepare('INSERT INTO push_subscriptions(user_id, endpoint, public_key, auth_token, user_agent) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$user['id'], $input['endpoint'], $input['keys']['p256dh'] ?? '', $input['keys']['auth'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? null]);
        Response::ok();
        return;
    }

    if ($path === 'admin/users' && Authorization::isSuperAdmin($user)) {
        $stmt = $pdo->query('SELECT id, email, name, role, is_active, last_login_at, created_at FROM users ORDER BY created_at DESC');
        Response::ok($stmt->fetchAll());
        return;
    }

    Response::fail('NOT_FOUND', 'Endpoint non trovato.', 404);
} catch (Throwable $e) {
    Response::fail('SERVER_ERROR', $e->getMessage(), 500);
}

function measurement_fields(): array
{
    return [
        'weight_kg', 'bmi', 'body_fat', 'body_water', 'muscle', 'bone',
        'left_arm_body_fat', 'left_arm_muscle', 'right_arm_body_fat', 'right_arm_muscle',
        'left_leg_body_fat', 'left_leg_muscle', 'right_leg_body_fat', 'right_leg_muscle',
        'trunk_body_fat', 'trunk_muscle', 'metabolic_age', 'heart_rate_bpm', 'visceral_fat',
    ];
}

function decimal_or_null(mixed $value): ?float
{
    if ($value === null || trim((string) $value) === '') {
        return null;
    }
    return (float) str_replace(',', '.', (string) $value);
}

function int_or_null(mixed $value): ?int
{
    if ($value === null || trim((string) $value) === '') {
        return null;
    }
    return max(0, (int) $value);
}

function table_has_column(PDO $pdo, string $table, string $column): bool
{
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $stmt->execute([$table, $column]);
    return $cache[$key] = ((int) $stmt->fetchColumn() > 0);
}

function reminder_payload(PDO $pdo, string $table, array $input, int $defaultBefore): array
{
    $payload = [];
    if (table_has_column($pdo, $table, 'reminder_minutes_before')) {
        $payload['reminder_minutes_before'] = int_or_null($input['reminder_minutes_before'] ?? null) ?? $defaultBefore;
    }
    if (table_has_column($pdo, $table, 'reminder_repeat_minutes')) {
        $payload['reminder_repeat_minutes'] = int_or_null($input['reminder_repeat_minutes'] ?? null) ?? 0;
    }
    return $payload;
}

function normalize_date(string $value): string
{
    $value = trim($value);
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return $value;
    }
    $time = strtotime($value);
    return $time === false ? date('Y-m-d') : date('Y-m-d', $time);
}

function normalize_measured_at(string $value): string
{
    $value = trim($value);
    if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $value)) {
        return str_replace('T', ' ', $value) . ':00';
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $value)) {
        return $value . ':00';
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return $value . ' 00:00:00';
    }
    return $value;
}

function medication_id(PDO $pdo, int $userId, string $name): int
{
    $name = trim($name) !== '' ? trim($name) : 'Mounjaro';
    $stmt = $pdo->prepare('SELECT id FROM glp1_medications WHERE user_id = ? AND name = ? LIMIT 1');
    $stmt->execute([$userId, $name]);
    $id = $stmt->fetchColumn();
    if ($id) {
        return (int) $id;
    }

    $stmt = $pdo->prepare('INSERT INTO glp1_medications(user_id, name, active_ingredient, is_active) VALUES (?, ?, "tirzepatide", 1)');
    $stmt->execute([$userId, $name]);
    return (int) $pdo->lastInsertId();
}

/** @return list<string> */
function injection_schedule_dates(array $input): array
{
    $scheduledAt = (string) ($input['scheduled_at'] ?? '');
    if ($scheduledAt === '') {
        $date = normalize_date((string) ($input['start_date'] ?? date('Y-m-d')));
        $time = trim((string) ($input['start_time'] ?? '10:00'));
        $scheduledAt = $date . ' ' . ($time !== '' ? $time : '10:00');
    }
    $scheduledAt = normalize_measured_at(str_replace('T', ' ', $scheduledAt));
    $start = new DateTimeImmutable($scheduledAt);

    $untilRaw = trim((string) ($input['recurrence_until'] ?? ''));
    if ($untilRaw === '') {
        return [$start->format('Y-m-d H:i:s')];
    }

    $until = new DateTimeImmutable(normalize_date($untilRaw) . ' 23:59:59');
    $dates = [];
    $cursor = $start;
    while ($cursor <= $until && count($dates) < 80) {
        $dates[] = $cursor->format('Y-m-d H:i:s');
        $cursor = $cursor->modify('+1 week');
    }

    return $dates ?: [$start->format('Y-m-d H:i:s')];
}

/** @return list<string> */
function workout_schedule_dates(array $input): array
{
    $scheduledAt = (string) ($input['scheduled_at'] ?? '');
    if ($scheduledAt !== '') {
        return [normalize_measured_at(str_replace('T', ' ', $scheduledAt))];
    }

    $date = normalize_date((string) ($input['start_date'] ?? date('Y-m-d')));
    $time = trim((string) ($input['start_time'] ?? '10:00'));
    $start = new DateTimeImmutable($date . ' ' . ($time !== '' ? $time : '10:00'));
    $untilRaw = trim((string) ($input['recurrence_until'] ?? ''));
    $weekdays = array_values(array_unique(array_filter(array_map('strval', (array) ($input['weekdays'] ?? [])), static fn (string $day): bool => preg_match('/^[0-6]$/', $day) === 1)));

    if ($untilRaw === '' || $weekdays === []) {
        return [$start->format('Y-m-d H:i:s')];
    }

    $until = new DateTimeImmutable(normalize_date($untilRaw) . ' 23:59:59');
    $dates = [];
    $cursor = $start;
    while ($cursor <= $until && count($dates) < 240) {
        if (in_array($cursor->format('w'), $weekdays, true)) {
            $dates[] = $cursor->format('Y-m-d H:i:s');
        }
        $cursor = $cursor->modify('+1 day');
    }

    return $dates ?: [$start->format('Y-m-d H:i:s')];
}

function dashboard_metric_summary(PDO $pdo, int $userId): array
{
    $definitions = [
        'peso' => ['column' => 'weight_kg', 'goal' => 'peso'],
        'bmi' => ['column' => 'bmi', 'goal' => 'bmi'],
        'massa_grassa' => ['column' => 'body_fat', 'goal' => 'massa_grassa'],
        'muscoli' => ['column' => 'muscle', 'goal' => 'muscoli'],
    ];
    $summary = [];
    foreach ($definitions as $key => $definition) {
        $column = $definition['column'];
        $latest = dashboard_measurement_value($pdo, $userId, $column, 'DESC');
        $initial = dashboard_measurement_value($pdo, $userId, $column, 'ASC');
        $previous = dashboard_previous_value($pdo, $userId, $column);
        $target = dashboard_goal_value($pdo, $userId, $definition['goal']);
        $summary[$key] = [
            'current' => $latest['value'] ?? null,
            'current_at' => $latest['measured_at'] ?? null,
            'initial' => $initial['value'] ?? null,
            'initial_at' => $initial['measured_at'] ?? null,
            'target' => $target,
            'target_delta' => ($target !== null && isset($latest['value'])) ? ((float) $latest['value'] - $target) : null,
            'change_7d' => (isset($latest['value'], $previous['value'])) ? ((float) $latest['value'] - (float) $previous['value']) : null,
        ];
    }
    return $summary;
}

function dashboard_measurement_value(PDO $pdo, int $userId, string $column, string $direction): ?array
{
    $stmt = $pdo->prepare("SELECT {$column} AS value, measured_at FROM body_measurements WHERE user_id = ? AND {$column} IS NOT NULL ORDER BY measured_at {$direction}, id {$direction} LIMIT 1");
    $stmt->execute([$userId]);
    return $stmt->fetch() ?: null;
}

function dashboard_previous_value(PDO $pdo, int $userId, string $column): ?array
{
    $stmt = $pdo->prepare("SELECT {$column} AS value, measured_at FROM body_measurements WHERE user_id = ? AND {$column} IS NOT NULL AND measured_at <= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 7 DAY) ORDER BY measured_at DESC, id DESC LIMIT 1");
    $stmt->execute([$userId]);
    return $stmt->fetch() ?: null;
}

function dashboard_goal_value(PDO $pdo, int $userId, string $metricKey): ?float
{
    $stmt = $pdo->prepare('SELECT target_value FROM goals WHERE user_id = ? AND metric_key = ? AND is_active = 1 ORDER BY created_at DESC, id DESC LIMIT 1');
    $stmt->execute([$userId, $metricKey]);
    $value = $stmt->fetchColumn();
    return $value === false ? null : (float) $value;
}

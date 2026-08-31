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
        $today = date('Y-m-d');
        $steps = $pdo->prepare('SELECT steps FROM daily_steps WHERE user_id = ? AND step_date = ?');
        $steps->execute([$user['id'], $today]);
        $nextInjection = $pdo->prepare('SELECT i.*, m.name AS medication_name FROM glp1_injections i JOIN glp1_medications m ON m.id = i.medication_id WHERE i.user_id = ? AND i.status = "scheduled" ORDER BY i.scheduled_at LIMIT 1');
        $nextInjection->execute([$user['id']]);
        $workouts = $pdo->prepare('SELECT COUNT(*) FROM workout_sessions WHERE user_id = ? AND completed_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 7 DAY) AND status = "completed"');
        $workouts->execute([$user['id']]);
        Response::ok([
            'latest_measurement' => $latest,
            'steps_today' => (int) ($steps->fetchColumn() ?: 0),
            'next_injection' => $nextInjection->fetch() ?: null,
            'completed_workouts_week' => (int) $workouts->fetchColumn(),
        ]);
        return;
    }

    if ($path === 'results') {
        Response::ok((new ResultsService($pdo))->metric((int) $user['id'], (string) ($_GET['metric'] ?? 'peso'), (string) ($_GET['range'] ?? '3m')));
        return;
    }

    if ($path === 'measurements' && $method === 'GET') {
        $limit = min(500, max(25, (int) ($_GET['limit'] ?? 250)));
        $stmt = $pdo->prepare('SELECT * FROM body_measurements WHERE user_id = ? ORDER BY measured_at DESC, id DESC LIMIT ' . $limit);
        $stmt->execute([$user['id']]);
        Response::ok($stmt->fetchAll());
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
        $stmt = $pdo->prepare('INSERT INTO goals(user_id, metric_key, target_value, target_date, is_active) VALUES (?, ?, ?, ?, 1)');
        $stmt->execute([$user['id'], $input['metric_key'] ?? 'peso', $input['target_value'] ?? 0, $input['target_date'] ?? null]);
        Response::ok(['id' => (int) $pdo->lastInsertId()]);
        return;
    }

    if ($path === 'injections' && $method === 'GET') {
        $stmt = $pdo->prepare('SELECT i.*, m.name AS medication_name FROM glp1_injections i JOIN glp1_medications m ON m.id = i.medication_id WHERE i.user_id = ? ORDER BY i.scheduled_at DESC LIMIT 80');
        $stmt->execute([$user['id']]);
        Response::ok($stmt->fetchAll());
        return;
    }

    if ($path === 'injections' && $method === 'POST') {
        require_csrf($auth);
        $med = (int) ($input['medication_id'] ?? 0);
        if ($med === 0) {
            $pdo->prepare('INSERT INTO glp1_medications(user_id, name) VALUES (?, ?)')->execute([$user['id'], $input['medication_name'] ?? 'GLP-1']);
            $med = (int) $pdo->lastInsertId();
        }
        $stmt = $pdo->prepare('INSERT INTO glp1_injections(user_id, medication_id, scheduled_at, planned_dose_mg, status, notes) VALUES (?, ?, ?, ?, "scheduled", ?)');
        $stmt->execute([$user['id'], $med, $input['scheduled_at'], $input['planned_dose_mg'], $input['notes'] ?? null]);
        Response::ok(['id' => (int) $pdo->lastInsertId()]);
        return;
    }

    if (preg_match('#^injections/(\d+)/complete$#', $path, $m) && $method === 'POST') {
        require_csrf($auth);
        $stmt = $pdo->prepare('UPDATE glp1_injections SET status = "completed", administered_at = ?, administered_dose_mg = ? WHERE id = ? AND user_id = ?');
        $stmt->execute([$input['administered_at'] ?? date('Y-m-d H:i:s'), $input['administered_dose_mg'] ?? null, $m[1], $user['id']]);
        Response::ok(['updated' => $stmt->rowCount()]);
        return;
    }

    if ($path === 'workouts' && $method === 'GET') {
        $stmt = $pdo->prepare('SELECT * FROM workout_sessions WHERE user_id = ? ORDER BY scheduled_at DESC LIMIT 80');
        $stmt->execute([$user['id']]);
        Response::ok($stmt->fetchAll());
        return;
    }

    if ($path === 'workouts' && $method === 'POST') {
        require_csrf($auth);
        $stmt = $pdo->prepare('INSERT INTO workout_sessions(user_id, scheduled_at, workout_type, duration_minutes, status, notes) VALUES (?, ?, ?, ?, "scheduled", ?)');
        $stmt->execute([$user['id'], $input['scheduled_at'], $input['workout_type'], $input['duration_minutes'] ?? null, $input['notes'] ?? null]);
        Response::ok(['id' => (int) $pdo->lastInsertId()]);
        return;
    }

    if (preg_match('#^workouts/(\d+)/complete$#', $path, $m) && $method === 'POST') {
        require_csrf($auth);
        $stmt = $pdo->prepare('UPDATE workout_sessions SET status = "completed", completed_at = ?, duration_minutes = ?, calories_burned = ?, notes = ? WHERE id = ? AND user_id = ?');
        $stmt->execute([$input['completed_at'] ?? date('Y-m-d H:i:s'), $input['duration_minutes'] ?? null, $input['calories_burned'] ?? null, $input['notes'] ?? null, $m[1], $user['id']]);
        Response::ok(['updated' => $stmt->rowCount()]);
        return;
    }

    if ($path === 'calendar') {
        $month = $_GET['month'] ?? date('Y-m');
        $from = $month . '-01';
        $to = date('Y-m-t', strtotime($from));
        $events = [];
        foreach ([
            ['body_measurements', 'measured_at', 'misurazione'],
            ['glp1_injections', 'scheduled_at', 'iniezione'],
            ['workout_sessions', 'scheduled_at', 'allenamento'],
        ] as [$table, $column, $type]) {
            $stmt = $pdo->prepare("SELECT *, DATE({$column}) AS event_date FROM {$table} WHERE user_id = ? AND DATE({$column}) BETWEEN ? AND ?");
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

function normalize_measured_at(string $value): string
{
    $value = trim($value);
    if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $value)) {
        return str_replace('T', ' ', $value) . ':00';
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $value)) {
        return $value . ':00';
    }
    return $value;
}

<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

$input = json_decode(
    file_get_contents('php://input'),
    true
);

if (!is_array($input)) {
    $input = [];
}

$action = (string)(
    $_GET['action'] ?? $input['action'] ?? ''
);

$key = (string)(
    $_GET['api_key'] ?? $input['api_key'] ?? ''
);

if (!hash_equals(API_KEY, $key)) {
    jsonResponse([
        'ok' => false,
        'error' => 'Unauthorized'
    ], 401);
}

try {
    $pdo = db();

    // Queue for commands sent from the web dashboard to the ESP32.
    // Created automatically; no manual SQL is required.
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS device_commands (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            command VARCHAR(32) NOT NULL,
            created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
            processed_at DATETIME(3) NULL,
            INDEX idx_pending_command (processed_at, id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // ==========================================
    // STATUS
    // ==========================================
    if ($action === 'status' && $method === 'GET') {

        $sql = "
            SELECT
                d.is_running,
                d.active_session_id,
                s.start_time,
                s.end_time,
                s.duration_ms,

                CASE
                    WHEN d.is_running = 1
                         AND s.start_time IS NOT NULL
                    THEN GREATEST(
                        0,
                        TIMESTAMPDIFF(
                            MICROSECOND,
                            s.start_time,
                            NOW(3)
                        ) DIV 1000
                    )
                    ELSE COALESCE(s.duration_ms, 0)
                END AS elapsed_ms

            FROM device_state d

            LEFT JOIN timer_sessions s
                ON s.id = COALESCE(
                    d.active_session_id,
                    (
                        SELECT id
                        FROM timer_sessions
                        ORDER BY id DESC
                        LIMIT 1
                    )
                )

            WHERE d.id = 1
            LIMIT 1
        ";

        $state = $pdo->query($sql)->fetch();

        if (!$state) {
            jsonResponse([
                'ok' => false,
                'error' => 'Device state row missing'
            ], 500);
        }

        $running = (bool)$state['is_running'];

        jsonResponse([
            'ok' => true,
            'running' => $running,

            'session_id' => (
                $running &&
                $state['active_session_id'] !== null
            )
                ? (int)$state['active_session_id']
                : null,

            'start_time' => $state['start_time'],
            'end_time' => $state['end_time'],
            'elapsed_ms' => (int)(
                $state['elapsed_ms'] ?? 0
            )
        ]);
    }

    // ==========================================
    // START TIMER
    // ==========================================
    if ($action === 'start' && $method === 'POST') {

        $pdo->beginTransaction();

        $state = $pdo->query(
            "SELECT is_running, active_session_id
             FROM device_state
             WHERE id = 1
             FOR UPDATE"
        )->fetch();

        if (!$state) {
            throw new RuntimeException(
                'device_state row id=1 missing'
            );
        }

        if (!(bool)$state['is_running']) {

            $stmt = $pdo->prepare(
                "INSERT INTO timer_sessions
                    (
                        start_time,
                        end_time,
                        duration_ms,
                        red_led_on_times,
                        red_press_count,
                        status
                    )
                 VALUES
                    (NOW(3), NULL, NULL, NULL, 0, 'running')"
            );

            $stmt->execute();

            $sessionId = (int)$pdo->lastInsertId();

            $stmt = $pdo->prepare(
                "UPDATE device_state
                 SET is_running = 1,
                     active_session_id = ?
                 WHERE id = 1"
            );

            $stmt->execute([$sessionId]);

        } else {
            // Do not create duplicate sessions.
            $sessionId = (int)$state['active_session_id'];
        }

        $pdo->commit();

        jsonResponse([
            'ok' => true,
            'running' => true,
            'session_id' => $sessionId
        ]);
    }

    // ==========================================
    // STOP TIMER
    // ==========================================
    if ($action === 'stop' && $method === 'POST') {

        $pdo->beginTransaction();

        $state = $pdo->query(
            "SELECT is_running, active_session_id
             FROM device_state
             WHERE id = 1
             FOR UPDATE"
        )->fetch();

        if (!$state) {
            throw new RuntimeException(
                'device_state row id=1 missing'
            );
        }

        if (
            (bool)$state['is_running'] &&
            $state['active_session_id'] !== null
        ) {
            $sessionId = (int)$state['active_session_id'];

            $stmt = $pdo->prepare(
                "UPDATE timer_sessions
                 SET
                    end_time = NOW(3),

                    duration_ms = GREATEST(
                        0,
                        TIMESTAMPDIFF(
                            MICROSECOND,
                            start_time,
                            NOW(3)
                        ) DIV 1000
                    ),

                    status = 'stopped'

                 WHERE id = ?
                   AND status = 'running'"
            );

            $stmt->execute([$sessionId]);

            $stmt = $pdo->prepare(
                "UPDATE device_state
                 SET is_running = 0,
                     active_session_id = NULL
                 WHERE id = 1"
            );

            $stmt->execute();
        }

        // Cancel any dashboard Red LED requests that have not yet been
        // picked up by the ESP32. Do not carry them into a later session.
        $pdo->prepare(
            "UPDATE device_commands
             SET processed_at = NOW(3)
             WHERE command = 'red' AND processed_at IS NULL"
        )->execute();

        $pdo->commit();

        jsonResponse([
            'ok' => true,
            'running' => false
        ]);
    }

    // ==========================================
    // SAVE RED LED EVENT IN SESSION ROW
    // ==========================================
    if ($action === 'red_event' && $method === 'POST') {

        $epochMs = filter_var(
            $input['epoch_ms'] ?? null,
            FILTER_VALIDATE_INT
        );

        $sessionId = filter_var(
            $input['session_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        if (
            $epochMs === false ||
            $epochMs === null ||
            $epochMs < 1000000000000 ||
            $epochMs > 9999999999999
        ) {
            jsonResponse([
                'ok' => false,
                'error' => 'Invalid epoch_ms'
            ], 400);
        }

        if (
            $sessionId === false ||
            $sessionId === null ||
            $sessionId < 1
        ) {
            jsonResponse([
                'ok' => false,
                'error' => 'Invalid session_id'
            ], 400);
        }

        // Convert ESP32 epoch timestamp to Pakistan time.
        $eventTime = eventTimeFromEpochMs((int)$epochMs);

        $pdo->beginTransaction();

        // Lock the specified session, not a different
        // session that may have started afterwards.
        $stmt = $pdo->prepare(
            "SELECT
                id,
                start_time,
                end_time,
                status
             FROM timer_sessions
             WHERE id = ?
             FOR UPDATE"
        );

        $stmt->execute([(int)$sessionId]);
        $session = $stmt->fetch();

        if (!$session) {
            $pdo->rollBack();

            jsonResponse([
                'ok' => false,
                'error' => 'Session not found'
            ], 404);
        }

        // Reject events that occurred outside the session.
        if (
            $eventTime < $session['start_time'] ||
            (
                $session['end_time'] !== null &&
                $eventTime > $session['end_time']
            )
        ) {
            $pdo->rollBack();

            jsonResponse([
                'ok' => false,
                'error' => 'Event time outside session'
            ], 409);
        }

        // Append this timestamp to the existing session.
        $stmt = $pdo->prepare(
            "UPDATE timer_sessions

             SET red_led_on_times =
                 CASE
                    WHEN red_led_on_times IS NULL
                      OR red_led_on_times = ''
                    THEN ?
                    ELSE CONCAT(
                        red_led_on_times,
                        ' | ',
                        ?
                    )
                 END,

                 red_press_count = red_press_count + 1

             WHERE id = ?"
        );

        $stmt->execute([
            $eventTime,
            $eventTime,
            (int)$sessionId
        ]);

        $pdo->commit();

        jsonResponse([
            'ok' => true,
            'session_id' => (int)$sessionId,
            'event_time' => $eventTime,
            'duration_ms' => 500
        ]);
    }

    // ==========================================
    // QUEUE RED LED COMMAND FROM DASHBOARD
    // ==========================================
    if ($action === 'queue_red' && $method === 'POST') {
        $state = $pdo->query(
            "SELECT is_running, active_session_id
             FROM device_state WHERE id = 1"
        )->fetch();

        if (!$state || !(bool)$state['is_running'] ||
            $state['active_session_id'] === null) {
            jsonResponse([
                'ok' => false,
                'error' => 'Start the timer before triggering the Red LED'
            ], 409);
        }

        $stmt = $pdo->prepare(
            "INSERT INTO device_commands (command) VALUES ('red')"
        );
        $stmt->execute();

        jsonResponse([
            'ok' => true,
            'command_id' => (int)$pdo->lastInsertId(),
            'message' => 'Red LED command queued'
        ]);
    }

    // ==========================================
    // ESP32 CLAIMS NEXT DASHBOARD COMMAND
    // Mark it processed before returning to avoid duplicate pulses
    // when the ESP32 polls again.
    // ==========================================
    if ($action === 'next_command' && $method === 'GET') {
        $pdo->beginTransaction();

        $stmt = $pdo->query(
            "SELECT id, command
             FROM device_commands
             WHERE processed_at IS NULL
             ORDER BY id ASC
             LIMIT 1
             FOR UPDATE"
        );
        $command = $stmt->fetch();

        if ($command) {
            $update = $pdo->prepare(
                "UPDATE device_commands
                 SET processed_at = NOW(3)
                 WHERE id = ? AND processed_at IS NULL"
            );
            $update->execute([(int)$command['id']]);
        }

        $pdo->commit();

        jsonResponse([
            'ok' => true,
            'command' => $command ? [
                'id' => (int)$command['id'],
                'name' => (string)$command['command']
            ] : null
        ]);
    }

    // ==========================================
    // SESSION HISTORY - ONE CONSOLIDATED TABLE
    // ==========================================
    if ($action === 'sessions' && $method === 'GET') {
        $startDate = trim((string)($_GET['start_date'] ?? ''));
        $endDate = trim((string)($_GET['end_date'] ?? ''));

        $hasDateFilter = ($startDate !== '' || $endDate !== '');

        if ($hasDateFilter) {
            $validDate = static function (string $date): bool {
                $d = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
                return $d !== false && $d->format('Y-m-d') === $date;
            };

            if (($startDate !== '' && !$validDate($startDate)) ||
                ($endDate !== '' && !$validDate($endDate))) {
                jsonResponse([
                    'ok' => false,
                    'error' => 'Dates must use YYYY-MM-DD format'
                ], 400);
            }

            if ($startDate !== '' && $endDate !== '' && $startDate > $endDate) {
                jsonResponse([
                    'ok' => false,
                    'error' => 'Start date must not be after end date'
                ], 400);
            }

            $where = [];
            $params = [];

            if ($startDate !== '') {
                $where[] = 'start_time >= ?';
                $params[] = $startDate . ' 00:00:00.000';
            }

            if ($endDate !== '') {
                $where[] = 'start_time < DATE_ADD(?, INTERVAL 1 DAY)';
                $params[] = $endDate . ' 00:00:00';
            }

            $sql = "SELECT id, start_time, end_time, duration_ms,
                           red_led_on_times, red_press_count, status
                    FROM timer_sessions
                    WHERE " . implode(' AND ', $where) . "
                    ORDER BY id DESC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $sessions = $stmt->fetchAll();
        } else {
            $stmt = $pdo->query(
                "SELECT id, start_time, end_time, duration_ms,
                        red_led_on_times, red_press_count, status
                 FROM timer_sessions
                 ORDER BY id DESC
                 LIMIT 500"
            );
            $sessions = $stmt->fetchAll();
        }

        jsonResponse([
            'ok' => true,
            'sessions' => $sessions
        ]);
    }

    // ==========================================
    // INVALID ACTION
    // ==========================================
    jsonResponse([
        'ok' => false,
        'error' => 'Unknown action or method'
    ], 400);

} catch (Throwable $e) {

    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // Full details go to the XAMPP/PHP error log.
    error_log(
        'ESP32 Timer API: ' . $e->getMessage()
    );

    jsonResponse([
        'ok' => false,
        'error' => 'Server error; check PHP error log'
    ], 500);
}
?>
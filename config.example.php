<?php
declare(strict_types=1);

// Copy this file to config.php and set your own credentials.
// Do not commit config.php or real credentials to GitHub.
date_default_timezone_set('Asia/Karachi');

const DB_HOST = '127.0.0.1';
const DB_NAME = 'esp32_timer';
const DB_USER = 'YOUR_DATABASE_USER';
const DB_PASS = 'YOUR_DATABASE_PASSWORD';
const API_KEY = 'aba1234';

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
             PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
             PDO::ATTR_EMULATE_PREPARES => false]
        );
        $pdo->exec("SET time_zone = '+05:00'");
    }
    return $pdo;
}

function jsonResponse(array $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data);
    exit;
}

function eventTimeFromEpochMs(int $epochMs): string {
    $seconds = intdiv($epochMs, 1000);
    $milliseconds = $epochMs % 1000;
    $date = (new DateTimeImmutable('@' . $seconds))
        ->setTimezone(new DateTimeZone('Asia/Karachi'));
    return $date->format('Y-m-d H:i:s') . sprintf('.%03d', $milliseconds);
}
?>

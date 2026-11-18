<?php
session_start();

// XAMPP MySQL settings. Your Control Panel shows MySQL on port 3307.
const DB_HOST = '127.0.0.1';
const DB_PORT = 3307;
const DB_NAME = 'smart_garbage';
const DB_USER = 'root';
const DB_PASS = '';

const THRESHOLD  = 80;                     // % level at which a bin needs emptying
const DEVICE_KEY = 'bin-device-secret';    // key the IoT sensors must send

function db() {
    static $pdo = null;
    if (!$pdo) {
        try {
            $pdo = new PDO('mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        } catch (PDOException $ex) {
            die('Database connection failed. Is MySQL running in XAMPP and did you import database.sql in phpMyAdmin? (' . htmlspecialchars($ex->getMessage()) . ')');
        }
    }
    return $pdo;
}

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function user() { return isset($_SESSION['user']) ? $_SESSION['user'] : null; }

function require_role(...$roles) {
    $u = user();
    if (!$u) { header('Location: index.php'); exit; }
    if ($roles && !in_array($u['role'], $roles, true)) { header('Location: ' . $u['role'] . '.php'); exit; }
    return $u;
}

function csrf() {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return '<input type="hidden" name="csrf" value="' . $_SESSION['csrf'] . '">';
}
function check_csrf() {
    if (!hash_equals(isset($_SESSION['csrf']) ? $_SESSION['csrf'] : '', isset($_POST['csrf']) ? $_POST['csrf'] : '')) {
        http_response_code(400); die('Invalid request');
    }
}

function flash($msg = null) {
    if ($msg !== null) { $_SESSION['flash'] = $msg; return ''; }
    $f = isset($_SESSION['flash']) ? $_SESSION['flash'] : '';
    unset($_SESSION['flash']);
    return $f;
}

// Bins joined with the resident's name and any open pickup alert
function bin_rows($where = '', $params = []) {
    $sql = "SELECT b.*, u.name AS resident,
              (SELECT a.id FROM alerts a WHERE a.bin_id = b.id AND a.status = 'open' LIMIT 1) AS alert_id
            FROM bins b JOIN users u ON u.id = b.resident_id $where ORDER BY b.house";
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

<?php
session_start();
date_default_timezone_set('Africa/Nairobi');

// XAMPP MySQL settings (your MySQL runs on port 3307)
const DB_HOST = '127.0.0.1';
const DB_PORT = 3307;
const DB_NAME = 'smart_garbage';
const DB_USER = 'root';
const DB_PASS = '';

const THRESHOLD  = 80;                    // % fill level that triggers a full-bin alert
const DEVICE_KEY = 'bin-device-secret';   // key the ESP32 must send

function db() {
    static $pdo = null;
    if (!$pdo) {
        try {
            $pdo = new PDO('mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
            $pdo->exec("SET time_zone = '+03:00'");
        } catch (PDOException $ex) {
            die('Database connection failed. Is MySQL running in XAMPP and did you import database.sql in phpMyAdmin? (' . htmlspecialchars($ex->getMessage()) . ')');
        }
    }
    return $pdo;
}

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function user() { return isset($_SESSION['user']) ? $_SESSION['user'] : null; }
function bin_label($id) { return sprintf('BIN-%03d', $id); }

function require_role(...$roles) {
    $u = user();
    if (!$u) { header('Location: index.php'); exit; }
    if ($roles && !in_array($u['role'], $roles, true)) { header('Location: dashboard.php'); exit; }
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
function time_ago($dt) {
    if (!$dt) return 'never';
    $s = time() - strtotime($dt);
    if ($s < 60) return 'just now';
    if ($s < 3600) return floor($s / 60) . ' min ago';
    if ($s < 86400) return floor($s / 3600) . ' hr ago';
    return floor($s / 86400) . ' day(s) ago';
}

// ---------- notifications ----------
function notify($userId, $binId, $msg) {
    db()->prepare('INSERT INTO notifications (bin_id, user_id, message) VALUES (?,?,?)')->execute([$binId, $userId, substr($msg, 0, 250)]);
}
function notify_roles($roles, $binId, $msg) {
    $in = implode(',', array_fill(0, count($roles), '?'));
    $st = db()->prepare("SELECT id FROM users WHERE role IN ($in)");
    $st->execute($roles);
    foreach ($st->fetchAll() as $r) notify($r['id'], $binId, $msg);
}
function notif_state($uid) {   // [latest notification id, unread count]
    $st = db()->prepare("SELECT COALESCE(MAX(id),0) m, SUM(status='unread') u FROM notifications WHERE user_id=?");
    $st->execute([$uid]);
    $r = $st->fetch();
    return [(int)$r['m'], (int)$r['u']];
}

// ---------- bins ----------
function bins_for($u) {
    $sql = "SELECT b.*, r.name AS resident, r.unit AS unit, r.phone AS phone,
        (SELECT c.id FROM collection_records c WHERE c.bin_id=b.id AND c.status IN ('requested','assigned') ORDER BY c.id LIMIT 1) AS open_record,
        (SELECT c.collector_id FROM collection_records c WHERE c.bin_id=b.id AND c.status='assigned' ORDER BY c.id LIMIT 1) AS assigned_id,
        (SELECT x.name FROM collection_records c JOIN users x ON x.id=c.collector_id WHERE c.bin_id=b.id AND c.status='assigned' ORDER BY c.id LIMIT 1) AS assigned_to
        FROM garbage_bins b LEFT JOIN users r ON r.id=b.resident_id";
    $p = [];
    if ($u['role'] === 'resident') { $sql .= ' WHERE b.resident_id=?'; $p[] = $u['id']; }
    $st = db()->prepare($sql . ' ORDER BY b.id');
    $st->execute($p);
    return $st->fetchAll();
}

// Called by the ESP32 endpoint and by the admin "simulate" box.
// Stores the reading, updates the bin and raises the full-bin alert when it crosses the threshold.
function apply_reading($binId, $level) {
    $level = max(0, min(100, (int)$level));
    $pdo = db();
    $pdo->beginTransaction();
    $st = $pdo->prepare('SELECT * FROM garbage_bins WHERE id=? FOR UPDATE');
    $st->execute([$binId]);
    $b = $st->fetch();
    if (!$b) { $pdo->rollBack(); return false; }
    $new = $level >= THRESHOLD ? 'needs_collection' : 'normal';
    $pdo->prepare('INSERT INTO sensor_readings (bin_id, fill_level) VALUES (?,?)')->execute([$binId, $level]);
    $pdo->prepare('UPDATE garbage_bins SET fill_level=?, status=?, last_reading=NOW() WHERE id=?')->execute([$level, $new, $binId]);
    if ($new === 'needs_collection' && $b['status'] !== 'needs_collection') {
        notify_roles(['admin', 'collector'], $binId, bin_label($binId) . ' (' . $b['location'] . ') reached ' . $level . '% capacity - needs collection.');
        if ($b['resident_id']) notify($b['resident_id'], $binId, 'Your bin is ' . $level . '% full. A collector has been alerted.');
    }
    $pdo->commit();
    return ['level' => $level, 'status' => $new];
}

function assign_pickup($binId, $collectorId, $date) {
    $pdo = db();
    $st = $pdo->prepare('SELECT location FROM garbage_bins WHERE id=?'); $st->execute([$binId]); $b = $st->fetch();
    $st = $pdo->prepare("SELECT id FROM users WHERE id=? AND role='collector'"); $st->execute([$collectorId]); $c = $st->fetch();
    if (!$b || !$c) return false;
    $ts = strtotime($date);
    $date = date('Y-m-d', $ts ? $ts : time());
    $st = $pdo->prepare("SELECT id FROM collection_records WHERE bin_id=? AND status IN ('requested','assigned') ORDER BY id LIMIT 1");
    $st->execute([$binId]);
    $rid = $st->fetchColumn();
    if ($rid) $pdo->prepare("UPDATE collection_records SET collector_id=?, collection_date=?, status='assigned' WHERE id=?")->execute([$collectorId, $date, $rid]);
    else $pdo->prepare("INSERT INTO collection_records (bin_id, collector_id, collection_date, status) VALUES (?,?,?,'assigned')")->execute([$binId, $collectorId, $date]);
    notify($collectorId, $binId, 'Pickup assigned: ' . bin_label($binId) . ' (' . $b['location'] . ') on ' . $date . '.');
    return true;
}

function mark_collected($binId, $collector) {
    $pdo = db();
    $st = $pdo->prepare('SELECT * FROM garbage_bins WHERE id=?'); $st->execute([$binId]); $b = $st->fetch();
    if (!$b) return false;
    $pdo->beginTransaction();
    $st = $pdo->prepare("SELECT id FROM collection_records WHERE bin_id=? AND status IN ('requested','assigned') ORDER BY id LIMIT 1");
    $st->execute([$binId]);
    $rid = $st->fetchColumn();
    if ($rid) $pdo->prepare("UPDATE collection_records SET collector_id=?, collection_date=CURDATE(), status='collected' WHERE id=?")->execute([$collector['id'], $rid]);
    else $pdo->prepare("INSERT INTO collection_records (bin_id, collector_id, collection_date, status) VALUES (?,?,CURDATE(),'collected')")->execute([$binId, $collector['id']]);
    $pdo->prepare("UPDATE garbage_bins SET fill_level=0, status='normal' WHERE id=?")->execute([$binId]);
    $pdo->prepare('INSERT INTO sensor_readings (bin_id, fill_level) VALUES (?,0)')->execute([$binId]);
    notify_roles(['admin'], $binId, bin_label($binId) . ' (' . $b['location'] . ') collected by ' . $collector['name'] . '.');
    if ($b['resident_id']) notify($b['resident_id'], $binId, 'Your bin was collected by ' . $collector['name'] . '.');
    $pdo->commit();
    return true;
}

// Returns true on success or an error message. Bins are registered separately by the admin.
function create_user($name, $email, $phone, $role, $pw, $unit = '') {
    $name = trim($name); $email = strtolower(trim($email));
    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pw) < 6 || !in_array($role, ['admin', 'resident', 'collector'], true))
        return 'Fill in all fields correctly (password at least 6 characters).';
    if ($role === 'resident' && trim($unit) === '') return 'Unit number is required for residents.';
    try {
        db()->prepare('INSERT INTO users (name,email,phone,unit,role,password_hash) VALUES (?,?,?,?,?,?)')
            ->execute([$name, $email, trim($phone), trim($unit), $role, password_hash($pw, PASSWORD_DEFAULT)]);
        return true;
    } catch (PDOException $ex) {
        return $ex->getCode() === '23000' ? 'That email is already registered.' : 'Could not create the account.';
    }
}

// Residents who do not have a bin yet (each resident has exactly one bin)
function residents_without_bin() {
    return db()->query("SELECT u.id, u.name, u.unit FROM users u WHERE u.role='resident' AND NOT EXISTS (SELECT 1 FROM garbage_bins b WHERE b.resident_id=u.id) ORDER BY u.name")->fetchAll();
}

// ---------- damage reports from residents ----------
function report_types() {
    return ['damaged_lid' => 'Broken or stuck lid', 'cracked_body' => 'Cracked or damaged bin', 'missing' => 'Bin missing or stolen',
            'sensor' => 'Sensor or lid motor not working', 'odour' => 'Bad smell or pests', 'other' => 'Other problem'];
}
function open_issues() {
    try { return (int)db()->query("SELECT COUNT(*) FROM bin_reports WHERE status='open'")->fetchColumn(); }
    catch (PDOException $ex) { return 0; }
}
// Returns true or an error message
function create_report($binId, $user, $type, $desc) {
    $types = report_types();
    if (!isset($types[$type])) return 'Please choose the type of problem.';
    $pdo = db();
    $st = $pdo->prepare('SELECT * FROM garbage_bins WHERE id=? AND resident_id=?');
    $st->execute([$binId, $user['id']]);
    $b = $st->fetch();
    if (!$b) return 'Bin not found.';
    $desc = trim(substr($desc, 0, 200));
    $pdo->prepare('INSERT INTO bin_reports (bin_id, user_id, type, description) VALUES (?,?,?,?)')->execute([$binId, $user['id'], $type, $desc]);
    notify_roles(['admin'], $binId, 'Damage report from ' . $user['name'] . ' for ' . bin_label($binId) . ' (' . $b['location'] . '): ' . $types[$type] . ($desc !== '' ? ' - ' . $desc : ''));
    return true;
}

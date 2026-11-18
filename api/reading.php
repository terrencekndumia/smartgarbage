<?php
// IoT sensor endpoint: POST JSON {"binId": 1, "level": 85} with header X-Device-Key
require __DIR__ . '/../config.php';
header('Content-Type: application/json');

if ((isset($_SERVER['HTTP_X_DEVICE_KEY']) ? $_SERVER['HTTP_X_DEVICE_KEY'] : '') !== DEVICE_KEY) {
    http_response_code(401); echo json_encode(['error' => 'Bad device key']); exit;
}
$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in)) $in = $_POST;
$binId = (int)(isset($in['binId']) ? $in['binId'] : 0);
$level = max(0, min(100, (int)(isset($in['level']) ? $in['level'] : 0)));

$st = db()->prepare('SELECT COUNT(*) FROM bins WHERE id = ?');
$st->execute([$binId]);
if (!$st->fetchColumn()) { http_response_code(404); echo json_encode(['error' => 'Unknown bin']); exit; }

db()->prepare('UPDATE bins SET level = ?, updated = NOW() WHERE id = ?')->execute([$level, $binId]);
echo json_encode(['ok' => true, 'needsEmptying' => $level >= THRESHOLD]);

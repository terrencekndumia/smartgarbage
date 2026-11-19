<?php
// ESP32 endpoint: POST JSON {"binId": 1, "level": 85} with header X-Device-Key
require __DIR__ . '/../config.php';
header('Content-Type: application/json');
if ((isset($_SERVER['HTTP_X_DEVICE_KEY']) ? $_SERVER['HTTP_X_DEVICE_KEY'] : '') !== DEVICE_KEY) {
    http_response_code(401); echo json_encode(['error' => 'Bad device key']); exit;
}
$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in)) $in = $_POST;
$r = apply_reading((int)(isset($in['binId']) ? $in['binId'] : 0), isset($in['level']) ? $in['level'] : 0);
if (!$r) { http_response_code(404); echo json_encode(['error' => 'Unknown bin']); exit; }
echo json_encode(['ok' => true, 'level' => $r['level'], 'needsEmptying' => $r['status'] === 'needs_collection']);

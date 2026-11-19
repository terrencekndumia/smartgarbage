<?php
// Used by app.js to refresh bin levels without reloading the page
require __DIR__ . '/../layout.php';
header('Content-Type: application/json');
$u = user();
if (!$u) { http_response_code(401); echo '{}'; exit; }
$bins = [];
foreach (bins_for($u) as $b) {
    $bins[] = ['id' => (int)$b['id'], 'level' => (int)$b['fill_level'], 'needs' => $b['status'] === 'needs_collection',
        'status_html' => status_tag($b['status']), 'online_html' => online_tag($b['last_reading']), 'ago' => time_ago($b['last_reading'])];
}
list($notif, $unread) = notif_state($u['id']);
echo json_encode(['bins' => $bins, 'notif' => $notif, 'unread' => $unread]);

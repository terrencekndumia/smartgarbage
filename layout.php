<?php
require_once __DIR__ . '/config.php';

function bar($l) {
    $c = $l >= THRESHOLD ? 'full' : '';
    return '<span class="bar"><i class="' . $c . '" style="width:' . (int)$l . '%"></i></span> ' . (int)$l . '%';
}
function status_tag($b) {
    if ($b['alert_id']) return '<span class="tag amber">Collector alerted</span>';
    return $b['level'] >= THRESHOLD ? '<span class="tag red">Needs emptying</span>' : '<span class="tag">OK</span>';
}
function pay_tag($b) {
    return $b['paid'] ? '<span class="tag">Paid</span>' : '<span class="tag red">Unpaid</span>';
}

function page_start($title, $refresh = 0) {
    $u = user();
    $f = flash();
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . e($title) . ' - Smart Garbage</title>';
    if ($refresh) echo '<meta http-equiv="refresh" content="' . (int)$refresh . '">';
    echo '<link rel="stylesheet" href="style.css"></head><body><header><h1>♻ Smart Garbage Collection</h1>';
    if ($u) echo '<div>' . e($u['name']) . ' (' . e($u['role']) . ') <a class="btn alt" href="logout.php">Log out</a></div>';
    echo '</header><main>';
    if ($f) echo '<div class="card note">' . e($f) . '</div>';
}
function page_end() { echo '</main></body></html>'; }

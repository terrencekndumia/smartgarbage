<?php
require_once __DIR__ . '/config.php';

function status_tag($s) {
    return $s === 'needs_collection' ? '<span class="tag red">&#9679; Needs Collection</span>' : '<span class="tag green">&#9679; Normal</span>';
}
function pay_tag($b) {
    return $b['paid'] ? '<span class="tag green">Paid</span>' : '<span class="tag amber">Unpaid</span>';
}
function online_tag($ts) {
    if (!$ts) return '<span class="tag grey">No data</span>';
    return (time() - strtotime($ts)) < 120 ? '<span class="tag green">&#9679; Online</span>' : '<span class="tag grey">Offline</span>';
}
function level_class($l, $needs) { return $needs ? 'full' : ($l >= 50 ? 'mid' : ''); }
function bar_cell($b) {
    $n = $b['status'] === 'needs_collection';
    return '<span class="bar"><i data-f="bar" class="' . level_class($b['fill_level'], $n) . '" style="width:' . (int)$b['fill_level'] . '%"></i></span> <span data-f="level">' . (int)$b['fill_level'] . '</span>%';
}
function bin_card($b, $extra = '') {
    $n = $b['status'] === 'needs_collection';
    $lv = (int)$b['fill_level'];
    $who = $b['resident'] ? e($b['resident']) . ($b['unit'] ? ' (Unit ' . e($b['unit']) . ')' : '') : 'Not assigned';
    $v = user();
    if ($v && $v['role'] !== 'resident' && !empty($b['phone'])) $who .= ' &middot; &#128222; ' . e($b['phone']);
    return '<div class="bincard' . ($n ? ' alert' : '') . '" data-bin="' . (int)$b['id'] . '">'
        . '<div class="bc-head"><div><div class="bc-id">' . e(bin_label($b['id'])) . '</div><div class="muted">' . e($b['location']) . '</div></div>'
        . '<div class="ring ' . level_class($lv, $n) . '" data-f="ring" style="--p:' . $lv . '"><span><span data-f="level">' . $lv . '</span>%</span></div></div>'
        . '<div class="bc-row"><span data-f="status">' . status_tag($b['status']) . '</span>' . pay_tag($b) . '<span data-f="online">' . online_tag($b['last_reading']) . '</span></div>'
        . '<small class="muted">' . $who . ' &middot; updated <span data-f="ago">' . e(time_ago($b['last_reading'])) . '</span></small>'
        . ($b['assigned_to'] ? '<small class="muted">&#128666; Collector: ' . e($b['assigned_to']) . '</small>' : '')
        . $extra . '</div>';
}

function page_start($title, $active = '', $live = false) {
    $u = user();
    $f = flash();
    $notif = 0; $unread = 0;
    if ($u) list($notif, $unread) = notif_state($u['id']);
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . e($title) . ' - Bin Watch</title><link rel="stylesheet" href="style.css"></head>';
    echo '<body data-live="' . ($live ? 1 : 0) . '" data-notif="' . $notif . '">';
    if ($u) {
        $nav = [['dashboard.php', '&#127968;', 'Dashboard']];
        if ($u['role'] === 'admin') $nav[] = ['bins.php', '&#128465;', 'Bins'];
        $nav[] = ['alerts.php', '&#128276;', 'Notifications'];
        $nav[] = ['schedules.php', '&#128197;', 'Schedules'];
        if ($u['role'] === 'admin') { $nav[] = ['issues.php', '&#128736;', 'Issues']; $nav[] = ['device.php', '&#128225;', 'IoT Device']; $nav[] = ['users.php', '&#128101;', 'Users']; $nav[] = ['reports.php', '&#128202;', 'Reports']; }
        $nav[] = ['account.php', '&#128100;', 'Account'];
        $issues = $u['role'] === 'admin' ? open_issues() : 0;
        echo '<div class="shell"><aside><div class="brand"><span class="logo">&#9851;</span> Bin Watch</div><nav>';
        foreach ($nav as $n) {
            $badge = '';
            if ($n[0] === 'alerts.php') $badge = ' <span class="badge unread-count"' . ($unread ? '' : ' hidden') . '>' . $unread . '</span>';
            if ($n[0] === 'issues.php' && $issues) $badge = ' <span class="badge">' . $issues . '</span>';
            echo '<a href="' . $n[0] . '" class="' . ($n[0] === $active ? 'on' : '') . '"><span><i class="ico">' . $n[1] . '</i>' . $n[2] . '</span>' . $badge . '</a>';
        }
        echo '</nav><div class="me"><div class="avatar">' . e(strtoupper(substr($u['name'], 0, 1))) . '</div><div><b>' . e($u['name']) . '</b><br><small>' . e(ucfirst($u['role'])) . ' <span id="live" class="live" title="Live updates"></span></small></div></div>';
        echo '<a class="logout" href="logout.php">Log out</a></aside><main><div class="topbar"><span class="muted">' . date('l, j F Y') . '</span><a class="bell" href="alerts.php" title="Notifications">&#128276;<span class="badge unread-count"' . ($unread ? '' : ' hidden') . '>' . $unread . '</span></a></div>';
    } else {
        echo '<div class="auth"><section class="auth-hero"><div><div class="logo big">&#9851;</div><h2>Bin Watch</h2><p>Smart garbage collection for residential estates.</p>';
        echo '<ul><li>&#128225; Live bin levels from IoT sensors</li><li>&#128276; Instant full-bin alerts</li><li>&#128666; Faster, smarter collections</li></ul></div></section><main class="auth-form">';
    }
    if ($f) echo '<div class="note">' . e($f) . '</div>';
}
function page_end() {
    echo user() ? '</main></div>' : '</main></div>';
    echo '<script src="app.js"></script></body></html>';
}

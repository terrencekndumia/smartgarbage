<?php
require __DIR__ . '/layout.php';
$u = require_role();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $a = isset($_POST['action']) ? $_POST['action'] : '';
    $binId = (int)(isset($_POST['bin_id']) ? $_POST['bin_id'] : 0);
    if ($u['role'] === 'resident') {
        $st = $pdo->prepare('SELECT * FROM garbage_bins WHERE id=? AND resident_id=?');
        $st->execute([$binId, $u['id']]);
        if ($b = $st->fetch()) {
            $label = bin_label($binId) . ' (' . $b['location'] . ')';
            if ($a === 'pay') {
                $pdo->prepare('UPDATE garbage_bins SET paid=1, paid_on=NOW() WHERE id=?')->execute([$binId]);
                flash('Payment received. Thank you!');
            } elseif ($a === 'request') {          // resident sends a collection alert
                $st = $pdo->prepare("SELECT COUNT(*) FROM collection_records WHERE bin_id=? AND status IN ('requested','assigned')");
                $st->execute([$binId]);
                if (!$st->fetchColumn()) {
                    $note = trim(substr(isset($_POST['note']) ? $_POST['note'] : '', 0, 150));
                    $pdo->prepare("INSERT INTO collection_records (bin_id, status) VALUES (?, 'requested')")->execute([$binId]);
                    notify_roles(['admin', 'collector'], $binId, 'Collection requested by ' . $u['name'] . ' for ' . $label . ($note !== '' ? ': ' . $note : '.'));
                    flash('Collection alert sent to the estate manager and collectors.');
                } else {
                    flash('A collection is already pending for your bin.');
                }
            } elseif ($a === 'report') {           // resident reports damage
                $r = create_report($binId, $u, isset($_POST['type']) ? $_POST['type'] : '', isset($_POST['description']) ? $_POST['description'] : '');
                flash($r === true ? 'Damage report sent. The estate manager has been notified.' : $r);
            }
        }
    } elseif ($u['role'] === 'collector' && $a === 'collect') {
        if (mark_collected($binId, $u)) flash('Marked as collected.');
    }
    header('Location: dashboard.php'); exit;
}

$bins = bins_for($u);
page_start('Dashboard', 'dashboard.php', true);

if ($u['role'] === 'admin'):
    $need = 0; $unpaid = 0;
    foreach ($bins as $b) { if ($b['status'] === 'needs_collection') $need++; if (!$b['paid']) $unpaid++; }
    $pending = (int)$pdo->query("SELECT COUNT(*) FROM collection_records WHERE status='requested'")->fetchColumn();
    $st = $pdo->prepare('SELECT * FROM notifications WHERE user_id=? ORDER BY id DESC LIMIT 8');
    $st->execute([$u['id']]);
    $notes = $st->fetchAll();
?>
<h1>Estate bin overview</h1>
<div class="stats">
  <div class="stat"><i>&#128465;</i><b><?= count($bins) ?></b>Total bins</div>
  <div class="stat red"><i>&#128680;</i><b><?= $need ?></b>Need collection</div>
  <div class="stat"><i>&#128666;</i><b><?= $pending ?></b>Pickup requests</div>
  <div class="stat"><i>&#128736;</i><b><?= open_issues() ?></b>Open issues</div>
  <div class="stat"><i>&#128179;</i><b><?= $unpaid ?></b>Unpaid</div>
</div>
<div class="two">
  <div class="card"><h2>&#128276; Notifications <a class="more" href="alerts.php">View all</a></h2>
    <ul class="list">
    <?php foreach ($notes as $n): ?>
      <li class="<?= $n['status'] === 'unread' ? 'unread' : '' ?>"><span><?= e($n['message']) ?></span><span class="muted"><?= e(time_ago($n['sent_at'])) ?></span></li>
    <?php endforeach; ?>
    <?php if (!$notes): ?><li class="muted">No notifications yet.</li><?php endif; ?>
    </ul></div>
  <div class="card"><h2>&#128680; Needs attention</h2>
    <ul class="list">
    <?php $any = false; foreach ($bins as $b): if ($b['status'] === 'needs_collection' || $b['open_record']): $any = true; ?>
      <li><span><b><?= e(bin_label($b['id'])) ?></b> - <?= e($b['location']) ?><br><small class="muted"><?= e($b['resident']) ?> &middot; <?= (int)$b['fill_level'] ?>% full<?= $b['assigned_to'] ? ' &middot; collector ' . e($b['assigned_to']) : '' ?></small></span><?= status_tag($b['status']) ?></li>
    <?php endif; endforeach; ?>
    <?php if (!$any): ?><li class="muted">All bins are fine. &#127881;</li><?php endif; ?>
    </ul></div>
</div>
<h2 class="sect">All bins</h2>
<div class="grid"><?php foreach ($bins as $b) echo bin_card($b); ?></div>

<?php elseif ($u['role'] === 'resident'):
    if (!$bins): ?>
<div class="card">No bin has been allocated to you yet. Please contact the estate manager.</div>
<?php else:
    $b = $bins[0];
    $lv = (int)$b['fill_level'];
    $n = $b['status'] === 'needs_collection';
    $c = level_class($lv, $n);
    $first = explode(' ', $u['name']);
    $st = $pdo->prepare("SELECT c.*, k.name AS collector FROM collection_records c JOIN garbage_bins b ON b.id=c.bin_id LEFT JOIN users k ON k.id=c.collector_id WHERE b.resident_id=? ORDER BY c.id DESC LIMIT 5");
    $st->execute([$u['id']]);
    $reqs = $st->fetchAll();
    $st = $pdo->prepare('SELECT * FROM bin_reports WHERE user_id=? ORDER BY id DESC LIMIT 5');
    $st->execute([$u['id']]);
    $reps = $st->fetchAll();
    $types = report_types();
?>
<div class="hero" data-bin="<?= $b['id'] ?>">
  <div class="binviz"><div class="lid"></div>
    <div class="body"><div class="fill <?= $c ?>" data-f="fill" style="height:<?= $lv ?>%"></div><div class="pct"><span data-f="level"><?= $lv ?></span>%</div></div></div>
  <div class="hero-info">
    <h1>Hello, <?= e($first[0]) ?> &#128075;</h1>
    <p>Your smart bin <b><?= e(bin_label($b['id'])) ?></b> &middot; <?= e($b['location']) ?><?= $b['unit'] ? ' &middot; Unit ' . e($b['unit']) : '' ?></p>
    <div class="bc-row"><span data-f="status"><?= status_tag($b['status']) ?></span><?= pay_tag($b) ?></div>
    <small>Last sensor update: <span data-f="ago"><?= e(time_ago($b['last_reading'])) ?></span></small>
    <?php if (!$b['paid']): ?>
    <form method="post"><?= csrf() ?><input type="hidden" name="action" value="pay"><input type="hidden" name="bin_id" value="<?= $b['id'] ?>"><button class="btn-light">&#128179; Pay service fee</button></form>
    <?php endif; ?>
  </div>
</div>

<div class="two">
  <div class="card action"><h2>&#128666; Request a collection</h2>
    <p class="muted">Bin full or overflowing? Send an alert to the estate manager and the collectors.</p>
    <?php if ($b['open_record']): ?>
      <div class="pending">&#9203; A collection is already pending<?= $b['assigned_to'] ? ' - assigned to ' . e($b['assigned_to']) : ' - waiting for a collector' ?>.</div>
    <?php else: ?>
    <form method="post"><?= csrf() ?><input type="hidden" name="action" value="request"><input type="hidden" name="bin_id" value="<?= $b['id'] ?>">
      <textarea name="note" rows="2" maxlength="150" placeholder="Optional note, e.g. the bin is overflowing"></textarea>
      <button class="btn-lg">Send collection alert</button></form>
    <?php endif; ?>
  </div>
  <div class="card action"><h2>&#128736; Report bin damage</h2>
    <p class="muted">Cracked bin, broken lid, faulty sensor? Tell the estate manager.</p>
    <form method="post"><?= csrf() ?><input type="hidden" name="action" value="report"><input type="hidden" name="bin_id" value="<?= $b['id'] ?>">
      <select name="type" required><option value="">What is wrong?</option>
        <?php foreach ($types as $k => $label): ?><option value="<?= $k ?>"><?= e($label) ?></option><?php endforeach; ?></select>
      <textarea name="description" rows="2" maxlength="200" placeholder="Describe the problem (optional)"></textarea>
      <button class="btn-lg warn">Submit report</button></form>
  </div>
</div>

<div class="two">
  <div class="card"><h2>My collection requests</h2><ul class="list">
    <?php foreach ($reqs as $r): ?>
      <li><span><?= $r['status'] === 'requested' ? 'Waiting for a collector' : ($r['status'] === 'assigned' ? 'Assigned to ' . e($r['collector']) : ucfirst(e($r['status'])) . ($r['collector'] ? ' by ' . e($r['collector']) : '')) ?>
        <br><small class="muted"><?= e(time_ago($r['created_at'])) ?></small></span>
        <span class="tag <?= $r['status'] === 'collected' ? 'green' : ($r['status'] === 'cancelled' ? 'grey' : 'amber') ?>"><?= e(ucfirst($r['status'])) ?></span></li>
    <?php endforeach; ?>
    <?php if (!$reqs): ?><li class="muted">No requests yet.</li><?php endif; ?></ul></div>
  <div class="card"><h2>My damage reports</h2><ul class="list">
    <?php foreach ($reps as $r): ?>
      <li><span><?= e(isset($types[$r['type']]) ? $types[$r['type']] : $r['type']) ?><?= $r['description'] !== '' ? ' - ' . e($r['description']) : '' ?>
        <br><small class="muted"><?= e(time_ago($r['created_at'])) ?></small></span>
        <span class="tag <?= $r['status'] === 'resolved' ? 'green' : 'amber' ?>"><?= e(ucfirst($r['status'])) ?></span></li>
    <?php endforeach; ?>
    <?php if (!$reps): ?><li class="muted">No reports yet.</li><?php endif; ?></ul></div>
</div>
<?php endif; ?>

<?php else:
    $mine = [];
    foreach ($bins as $b) { if ($b['status'] === 'needs_collection' || $b['open_record'] || (int)$b['assigned_id'] === (int)$u['id']) $mine[] = $b; }
?>
<h1>Pickups</h1>
<div class="card scroll"><h2>Bins to collect</h2>
<table><tr><th>Bin</th><th>Location</th><th>Resident</th><th>Fill level</th><th>Status</th><th>Assigned</th><th></th></tr>
<?php foreach ($mine as $b): ?>
  <tr data-bin="<?= $b['id'] ?>"><td><b><?= e(bin_label($b['id'])) ?></b></td><td><?= e($b['location']) ?></td><td><?= e($b['resident']) ?> <?= $b['unit'] ? '(' . e($b['unit']) . ')' : '' ?></td>
    <td><?= bar_cell($b) ?></td>
    <td><span data-f="status"><?= status_tag($b['status']) ?></span><?= ($b['open_record'] && !$b['assigned_id']) ? ' <span class="tag blue">Requested</span>' : '' ?></td>
    <td><?= $b['assigned_to'] ? e($b['assigned_to']) : '<span class="muted">Unassigned</span>' ?></td>
    <td><form method="post" class="inline"><?= csrf() ?><input type="hidden" name="action" value="collect"><input type="hidden" name="bin_id" value="<?= $b['id'] ?>"><button>Mark collected</button></form></td></tr>
<?php endforeach; ?>
<?php if (!$mine): ?><tr><td colspan="7" class="muted">No pickups pending. &#127881;</td></tr><?php endif; ?>
</table></div>
<h2 class="sect">Residents' bins</h2>
<div class="grid"><?php foreach ($bins as $b) echo bin_card($b); ?></div>
<?php endif;
page_end();

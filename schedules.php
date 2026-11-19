<?php
require __DIR__ . '/layout.php';
$u = require_role();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $u['role'] === 'admin') {
    check_csrf();
    $a = isset($_POST['action']) ? $_POST['action'] : '';
    if ($a === 'assign') {
        flash(assign_pickup((int)$_POST['bin_id'], (int)$_POST['collector_id'], isset($_POST['date']) ? $_POST['date'] : '') ? 'Pickup assigned.' : 'Choose a bin and a collector.');
    } elseif ($a === 'cancel') {
        $pdo->prepare("UPDATE collection_records SET status='cancelled' WHERE id=? AND status IN ('requested','assigned')")->execute([(int)$_POST['id']]);
    }
    header('Location: schedules.php'); exit;
}

$sql = "SELECT c.*, b.location, r.name AS resident, k.name AS collector
        FROM collection_records c JOIN garbage_bins b ON b.id=c.bin_id
        LEFT JOIN users r ON r.id=b.resident_id LEFT JOIN users k ON k.id=c.collector_id";
$p = [];
if ($u['role'] === 'resident') { $sql .= ' WHERE b.resident_id=?'; $p[] = $u['id']; }
if ($u['role'] === 'collector') { $sql .= ' WHERE c.collector_id=?'; $p[] = $u['id']; }
$st = $pdo->prepare($sql . ' ORDER BY c.id DESC LIMIT 50');
$st->execute($p);
$rows = $st->fetchAll();
$tags = ['requested' => 'amber', 'assigned' => 'amber', 'collected' => 'green', 'cancelled' => 'grey'];

if ($u['role'] === 'admin') {
    $allBins = $pdo->query('SELECT id, location FROM garbage_bins ORDER BY id')->fetchAll();
    $collectors = $pdo->query("SELECT id, name FROM users WHERE role='collector' ORDER BY name")->fetchAll();
}
page_start('Schedules', 'schedules.php');
?>
<h1>Collection schedules</h1>
<?php if ($u['role'] === 'admin'): ?>
<div class="card"><h2>Assign a collection route</h2>
<form method="post" class="row"><?= csrf() ?><input type="hidden" name="action" value="assign">
  <select name="bin_id"><?php foreach ($allBins as $b): ?><option value="<?= $b['id'] ?>"><?= e(bin_label($b['id'])) ?> - <?= e($b['location']) ?></option><?php endforeach; ?></select>
  <select name="collector_id"><?php foreach ($collectors as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?></select>
  <input type="date" name="date" value="<?= date('Y-m-d') ?>">
  <button <?= $collectors ? '' : 'disabled' ?>>Assign</button></form></div>
<?php endif; ?>
<div class="card scroll"><table>
<tr><th>Bin</th><th>Location</th><th>Resident</th><th>Collector</th><th>Date</th><th>Status</th><?= $u['role'] === 'admin' ? '<th></th>' : '' ?></tr>
<?php foreach ($rows as $r): ?>
<tr><td><?= e(bin_label($r['bin_id'])) ?></td><td><?= e($r['location']) ?></td><td><?= e($r['resident']) ?></td>
  <td><?= $r['collector'] ? e($r['collector']) : '<span class="muted">Not assigned</span>' ?></td>
  <td><?= $r['collection_date'] ? e($r['collection_date']) : '-' ?></td>
  <td><span class="tag <?= $tags[$r['status']] ?>"><?= e(ucfirst($r['status'])) ?></span></td>
  <?php if ($u['role'] === 'admin'): ?><td><?php if (in_array($r['status'], ['requested', 'assigned'], true)): ?>
    <form method="post" class="inline"><?= csrf() ?><input type="hidden" name="action" value="cancel"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="alt">Cancel</button></form><?php endif; ?></td><?php endif; ?>
</tr>
<?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="7" class="muted">Nothing scheduled yet.</td></tr><?php endif; ?>
</table></div>
<?php page_end();

<?php
require __DIR__ . '/layout.php';
$u = require_role('admin');
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $a = isset($_POST['action']) ? $_POST['action'] : '';
    $id = (int)(isset($_POST['bin_id']) ? $_POST['bin_id'] : 0);
    $res = (int)(isset($_POST['resident_id']) ? $_POST['resident_id'] : 0);
    $hasBin = function ($rid) use ($pdo) {
        $st = $pdo->prepare('SELECT COUNT(*) FROM garbage_bins WHERE resident_id=?'); $st->execute([$rid]); return (int)$st->fetchColumn() > 0;
    };
    if ($a === 'alert') {                         // alert a collector to pick up the bin
        flash(assign_pickup($id, (int)(isset($_POST['collector_id']) ? $_POST['collector_id'] : 0), date('Y-m-d')) ? 'Collector alerted.' : 'Choose a collector first.');
    } elseif ($a === 'paid') {
        $paid = (int)$_POST['paid'];
        $pdo->prepare('UPDATE garbage_bins SET paid=?, paid_on=IF(?=1,NOW(),NULL) WHERE id=?')->execute([$paid, $paid, $id]);
    } elseif ($a === 'level') {                   // simulate a sensor reading
        apply_reading($id, (int)$_POST['level']);
        flash('Level updated (simulated sensor reading).');
    } elseif ($a === 'add_bin') {                 // register a new bin / sensor
        $loc = trim(isset($_POST['location']) ? $_POST['location'] : '');
        $cap = (int)(isset($_POST['capacity']) ? $_POST['capacity'] : 0);
        if ($loc === '') flash('Enter the bin location.');
        elseif ($res && $hasBin($res)) flash('That resident already has a bin.');
        else {
            $pdo->prepare('INSERT INTO garbage_bins (location, capacity, resident_id) VALUES (?,?,?)')->execute([$loc, $cap > 0 ? $cap : 120, $res ? $res : null]);
            $nid = (int)$pdo->lastInsertId();
            if ($res) notify($res, $nid, 'A smart bin (' . bin_label($nid) . ', ' . $loc . ') has been registered for you.');
            flash('Bin registered as ' . bin_label($nid) . '. Use BIN_ID = ' . $nid . ' in the ESP32 sketch.');
        }
    } elseif ($a === 'delete_bin') {              // remove a bin and its history
        $pdo->prepare('DELETE FROM garbage_bins WHERE id=?')->execute([$id]);
        flash('Bin deleted.');
    } elseif ($a === 'assign_resident') {         // give an unassigned bin to a resident
        $st = $pdo->prepare('SELECT location FROM garbage_bins WHERE id=? AND resident_id IS NULL'); $st->execute([$id]); $b = $st->fetch();
        if ($b && $res && !$hasBin($res)) {
            $pdo->prepare('UPDATE garbage_bins SET resident_id=? WHERE id=?')->execute([$res, $id]);
            notify($res, $id, 'A smart bin (' . bin_label($id) . ', ' . $b['location'] . ') has been registered for you.');
            flash('Bin assigned to the resident.');
        } else flash('Could not assign: the resident may already have a bin.');
    }
    header('Location: bins.php'); exit;
}

$bins = bins_for($u);
$collectors = $pdo->query("SELECT id, name FROM users WHERE role='collector' ORDER BY name")->fetchAll();
$free = residents_without_bin();
page_start('Bins', 'bins.php');
?>
<h1>Bins</h1>
<div class="card"><h2>&#10133; Register a new bin</h2>
<form method="post" class="row"><?= csrf() ?><input type="hidden" name="action" value="add_bin">
  <input name="location" placeholder="Location (e.g. Block 3, Unit A1)" required style="min-width:240px">
  <input name="capacity" type="number" placeholder="Capacity (litres)" style="width:160px">
  <select name="resident_id"><option value="0">Assign to a resident later</option>
    <?php foreach ($free as $r): ?><option value="<?= $r['id'] ?>"><?= e($r['name']) ?> (<?= e($r['unit']) ?>)</option><?php endforeach; ?></select>
  <button>Register bin</button></form>
<p class="muted"><small>Each resident has their own bin. Only residents without a bin are listed. After registering, put the Bin ID in the ESP32 sketch (see the <a href="device.php">IoT Device</a> page).</small></p></div>

<div class="card scroll"><table>
<tr><th>Bin</th><th>Location</th><th>Resident</th><th>Fill level</th><th>Status</th><th>Payment</th><th>Sensor</th><th>Actions</th></tr>
<?php foreach ($bins as $b): ?>
<tr data-bin="<?= $b['id'] ?>">
  <td><b><?= e(bin_label($b['id'])) ?></b><br><small class="muted">ID <?= $b['id'] ?></small></td><td><?= e($b['location']) ?></td>
  <td><?php if ($b['resident']): ?><?= e($b['resident']) ?> (<?= e($b['unit']) ?>)<?php elseif ($free): ?>
    <form method="post" class="inline"><?= csrf() ?><input type="hidden" name="action" value="assign_resident"><input type="hidden" name="bin_id" value="<?= $b['id'] ?>">
      <select name="resident_id"><?php foreach ($free as $r): ?><option value="<?= $r['id'] ?>"><?= e($r['name']) ?></option><?php endforeach; ?></select><button class="alt">Assign</button></form>
    <?php else: ?><span class="muted">Not assigned</span><?php endif; ?></td>
  <td><?= bar_cell($b) ?></td><td><span data-f="status"><?= status_tag($b['status']) ?></span></td><td><?= pay_tag($b) ?></td>
  <td><span data-f="online"><?= online_tag($b['last_reading']) ?></span><br><small class="muted" data-f="ago"><?= e(time_ago($b['last_reading'])) ?></small></td>
  <td>
    <form method="post" class="inline"><?= csrf() ?><input type="hidden" name="action" value="alert"><input type="hidden" name="bin_id" value="<?= $b['id'] ?>">
      <select name="collector_id"><?php foreach ($collectors as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?></select>
      <button class="warn" <?= $collectors ? '' : 'disabled' ?>>Alert collector</button></form>
    <form method="post" class="inline"><?= csrf() ?><input type="hidden" name="action" value="paid"><input type="hidden" name="bin_id" value="<?= $b['id'] ?>"><input type="hidden" name="paid" value="<?= $b['paid'] ? 0 : 1 ?>">
      <button class="alt">Mark <?= $b['paid'] ? 'unpaid' : 'paid' ?></button></form>
    <form method="post" class="inline"><?= csrf() ?><input type="hidden" name="action" value="level"><input type="hidden" name="bin_id" value="<?= $b['id'] ?>">
      <input type="number" name="level" min="0" max="100" value="<?= (int)$b['fill_level'] ?>" style="width:70px"><button class="alt">Set level</button></form>
    <form method="post" class="inline" onsubmit="return confirm('Delete this bin and its history? This cannot be undone.')"><?= csrf() ?><input type="hidden" name="action" value="delete_bin"><input type="hidden" name="bin_id" value="<?= $b['id'] ?>"><button class="warn">Delete</button></form>
  </td></tr>
<?php endforeach; ?>
<?php if (!$bins): ?><tr><td colspan="8" class="muted">No bins yet. Register one above.</td></tr><?php endif; ?>
</table>
<p class="muted"><small>Bins are flagged "Needs Collection" at <?= THRESHOLD ?>% or more. "Set level" simulates a sensor reading; the ESP32 sends real ones to api/reading.php.</small></p></div>
<?php page_end();

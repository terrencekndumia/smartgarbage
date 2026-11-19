<?php
require __DIR__ . '/layout.php';
$u = require_role('admin');
$pdo = db();

function csv_out($name, $rows) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $name . '"');
    $o = fopen('php://output', 'w');
    foreach ($rows as $i => $r) { if ($i === 0) fputcsv($o, array_keys($r)); fputcsv($o, $r); }
    fclose($o); exit;
}
$readingsSql = 'SELECT r.id, r.bin_id, b.location, r.fill_level, r.recorded_at FROM sensor_readings r JOIN garbage_bins b ON b.id=r.bin_id ORDER BY r.id DESC';
$collectSql = 'SELECT c.id, c.bin_id, b.location, k.name AS collector, c.collection_date, c.status FROM collection_records c JOIN garbage_bins b ON b.id=c.bin_id LEFT JOIN users k ON k.id=c.collector_id ORDER BY c.id DESC';
if (isset($_GET['export']) && $_GET['export'] === 'readings') csv_out('sensor_readings.csv', $pdo->query($readingsSql . ' LIMIT 5000')->fetchAll());
if (isset($_GET['export']) && $_GET['export'] === 'collections') csv_out('collections.csv', $pdo->query($collectSql . ' LIMIT 5000')->fetchAll());

$bins = (int)$pdo->query('SELECT COUNT(*) FROM garbage_bins')->fetchColumn();
$avg = round($pdo->query('SELECT COALESCE(AVG(fill_level),0) FROM garbage_bins')->fetchColumn());
$readingsN = (int)$pdo->query('SELECT COUNT(*) FROM sensor_readings')->fetchColumn();
$month = (int)$pdo->query("SELECT COUNT(*) FROM collection_records WHERE status='collected' AND collection_date >= DATE_FORMAT(CURDATE(),'%Y-%m-01')")->fetchColumn();
$unpaid = (int)$pdo->query('SELECT COUNT(*) FROM garbage_bins WHERE resident_id IS NOT NULL AND paid=0')->fetchColumn();
$byCollector = $pdo->query("SELECT u.name, COUNT(c.id) AS n FROM users u LEFT JOIN collection_records c ON c.collector_id=u.id AND c.status='collected' WHERE u.role='collector' GROUP BY u.id, u.name")->fetchAll();
$readings = $pdo->query($readingsSql . ' LIMIT 15')->fetchAll();
$recent = $pdo->query($collectSql . ' LIMIT 15')->fetchAll();
page_start('Reports', 'reports.php');
?>
<h1>Reports</h1>
<div class="stats">
  <div class="stat"><b><?= $bins ?></b>Bins</div><div class="stat"><b><?= $avg ?>%</b>Average fill level</div>
  <div class="stat"><b><?= $readingsN ?></b>Sensor readings</div><div class="stat"><b><?= $month ?></b>Collections this month</div>
  <div class="stat"><b><?= $unpaid ?></b>Unpaid residents</div>
</div>
<div class="card"><h2>Collections per collector</h2><ul class="list">
<?php foreach ($byCollector as $c): ?><li><span><?= e($c['name']) ?></span><b><?= (int)$c['n'] ?></b></li><?php endforeach; ?>
<?php if (!$byCollector): ?><li class="muted">No collectors yet.</li><?php endif; ?></ul></div>
<div class="card scroll"><h2>Latest sensor readings <a class="btn alt" href="reports.php?export=readings" style="font-size:.8rem;padding:4px 10px">Download CSV</a></h2>
<table><tr><th>Bin</th><th>Location</th><th>Fill level</th><th>Time</th></tr>
<?php foreach ($readings as $r): ?><tr><td><?= e(bin_label($r['bin_id'])) ?></td><td><?= e($r['location']) ?></td><td><?= (int)$r['fill_level'] ?>%</td><td><?= e($r['recorded_at']) ?></td></tr><?php endforeach; ?>
<?php if (!$readings): ?><tr><td colspan="4" class="muted">No readings yet.</td></tr><?php endif; ?></table></div>
<div class="card scroll"><h2>Recent collections <a class="btn alt" href="reports.php?export=collections" style="font-size:.8rem;padding:4px 10px">Download CSV</a></h2>
<table><tr><th>Bin</th><th>Location</th><th>Collector</th><th>Date</th><th>Status</th></tr>
<?php foreach ($recent as $r): ?><tr><td><?= e(bin_label($r['bin_id'])) ?></td><td><?= e($r['location']) ?></td><td><?= e($r['collector']) ?></td><td><?= e($r['collection_date']) ?></td><td><?= e($r['status']) ?></td></tr><?php endforeach; ?>
<?php if (!$recent): ?><tr><td colspan="5" class="muted">No collections yet.</td></tr><?php endif; ?></table></div>
<?php page_end();

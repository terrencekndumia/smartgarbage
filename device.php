<?php
require __DIR__ . '/layout.php';
$u = require_role('admin');
$pdo = db();
$https = (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
$host = $_SERVER['HTTP_HOST'];
$endpoint = ($https ? 'https' : 'http') . '://' . $host . rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/') . '/api/reading.php';
$local = (bool)preg_match('/^(localhost|127\.|192\.168\.|10\.|172\.)/', $host);
$bins = bins_for($u);
$recent = $pdo->query('SELECT r.*, b.location FROM sensor_readings r JOIN garbage_bins b ON b.id=r.bin_id ORDER BY r.id DESC LIMIT 12')->fetchAll();
$first = $bins ? (int)$bins[0]['id'] : 1;
page_start('IoT Device', 'device.php');
?>
<h1>&#128225; Connect the IoT device</h1>
<div class="card"><h2>Server address for the ESP32</h2>
  <div class="copybox"><input id="ep" readonly value="<?= e($endpoint) ?>"><button type="button" onclick="navigator.clipboard.writeText(document.getElementById('ep').value);this.textContent='Copied!'">Copy</button></div>
  <p class="muted">Device key: <b><?= e(DEVICE_KEY) ?></b> (must match <code>DEVICE_KEY</code> in the sketch)</p>
  <?php if ($local): ?>
  <div class="warnbox"><b>You opened this page on a local address (<?= e($host) ?>).</b> The Wokwi simulator runs on the internet and cannot reach it. To connect Wokwi:
    <ol class="steps"><li>Start Apache in XAMPP, then in a terminal run <code>ngrok http 80</code></li>
    <li>Open the <b>https://...ngrok-free.app</b> address that ngrok prints, and click <b>Visit Site</b></li>
    <li>Go to <b>smart-garbage &rarr; IoT Device</b> on that address. The box above then shows the public address.</li>
    <li>Paste it into <code>SERVER_URL</code> in the Wokwi sketch and press Play.</li></ol></div>
  <?php else: ?>
  <div class="okbox">&#10003; This is a reachable address. Paste it into <code>SERVER_URL</code> in the Wokwi sketch, set <code>BIN_ID</code>, and press Play.</div>
  <?php endif; ?>
</div>

<div class="card scroll"><h2>Bins and sensors</h2>
<table><tr><th>Bin ID</th><th>Bin</th><th>Location</th><th>Resident</th><th>Sensor</th><th>Last reading</th><th>Sketch setting</th></tr>
<?php foreach ($bins as $b): ?>
<tr data-bin="<?= $b['id'] ?>"><td><b><?= $b['id'] ?></b></td><td><?= e(bin_label($b['id'])) ?></td><td><?= e($b['location']) ?></td><td><?= $b['resident'] ? e($b['resident']) : '<span class="muted">Not assigned</span>' ?></td>
  <td><span data-f="online"><?= online_tag($b['last_reading']) ?></span></td><td><span data-f="level"><?= (int)$b['fill_level'] ?></span>% &middot; <span data-f="ago"><?= e(time_ago($b['last_reading'])) ?></span></td>
  <td><code>BIN_ID = <?= $b['id'] ?>;</code></td></tr>
<?php endforeach; ?>
<?php if (!$bins): ?><tr><td colspan="7" class="muted">Register a bin on the Bins page first.</td></tr><?php endif; ?>
</table></div>

<div class="card scroll"><h2>Live readings received <small class="muted">(refreshes every 8 seconds)</small></h2>
<table><tr><th>Bin</th><th>Location</th><th>Fill level</th><th>Received</th></tr>
<?php foreach ($recent as $r): ?><tr><td><?= e(bin_label($r['bin_id'])) ?></td><td><?= e($r['location']) ?></td><td><?= (int)$r['fill_level'] ?>%</td><td><?= e(time_ago($r['recorded_at'])) ?> <small class="muted">(<?= e($r['recorded_at']) ?>)</small></td></tr><?php endforeach; ?>
<?php if (!$recent): ?><tr><td colspan="4" class="muted">No readings yet. Start the Wokwi simulation.</td></tr><?php endif; ?></table></div>

<div class="card"><h2>Test without Wokwi</h2>
<p class="muted">Run this in PowerShell to send a reading of 85% for BIN-<?= sprintf('%03d', $first) ?>:</p>
<pre class="code">Invoke-RestMethod -Uri <?= e($endpoint) ?> -Method Post -Headers @{"X-Device-Key"="<?= e(DEVICE_KEY) ?>"} -ContentType "application/json" -Body '{"binId":<?= $first ?>,"level":85}'</pre></div>
<script>setTimeout(function () { location.reload(); }, 8000);</script>
<?php page_end();

<?php
require __DIR__ . '/layout.php';
$me = require_role('resident');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {      // simulated payment
    check_csrf();
    db()->prepare('UPDATE bins SET paid = 1, paid_on = NOW() WHERE id = ? AND resident_id = ?')
        ->execute([(int)$_POST['bin_id'], $me['id']]);
    flash('Payment received. Thank you!');
    header('Location: resident.php'); exit;
}

$bins = bin_rows('WHERE b.resident_id = ?', [$me['id']]);
page_start('My bin', 10);   // page refreshes every 10 seconds
foreach ($bins as $b): ?>
<div class="card"><h2>My bin - <?= e($b['house']) ?></h2>
  <p>Fill level: <?= bar($b['level']) ?></p>
  <p>Status: <?= status_tag($b) ?></p>
  <p>Payment: <?= pay_tag($b) ?>
    <?php if (!$b['paid']): ?>
      <form method="post" class="inline"><?= csrf() ?><input type="hidden" name="bin_id" value="<?= $b['id'] ?>"><button>Pay now</button></form>
    <?php endif; ?></p>
  <small>Last sensor update: <?= e($b['updated']) ?></small>
</div>
<?php endforeach;
if (!$bins) echo '<div class="card">No bin assigned yet.</div>';
page_end();

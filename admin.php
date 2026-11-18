<?php
require __DIR__ . '/layout.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $pdo = db();
    $a = isset($_POST['action']) ? $_POST['action'] : '';

    if ($a === 'create_user') {
        $name  = trim($_POST['name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $pw    = $_POST['password'] ?? '';
        $role  = $_POST['role'] ?? '';
        $house = trim($_POST['house'] ?? '');
        if (!$name || !$email || strlen($pw) < 6 || !in_array($role, ['admin', 'resident', 'collector'], true)) {
            flash('Fill in all fields (password at least 6 characters).');
        } elseif ($role === 'resident' && !$house) {
            flash('House / unit number is required for residents.');
        } else {
            try {
                $pdo->beginTransaction();
                $pdo->prepare('INSERT INTO users (name, email, role, password_hash) VALUES (?,?,?,?)')
                    ->execute([$name, $email, $role, password_hash($pw, PASSWORD_DEFAULT)]);
                if ($role === 'resident') {   // every resident gets their own bin
                    $pdo->prepare('INSERT INTO bins (resident_id, house) VALUES (?,?)')->execute([$pdo->lastInsertId(), $house]);
                }
                $pdo->commit();
                flash("User $name created.");
            } catch (PDOException $ex) {
                $pdo->rollBack();
                flash($ex->getCode() === '23000' ? 'That email is already used.' : 'Could not create user.');
            }
        }
    } elseif ($a === 'alert') {              // alert the collector
        $id = (int)$_POST['bin_id'];
        $st = $pdo->prepare("SELECT COUNT(*) FROM alerts WHERE bin_id = ? AND status = 'open'");
        $st->execute([$id]);
        if (!$st->fetchColumn()) {
            $pdo->prepare('INSERT INTO alerts (bin_id) VALUES (?)')->execute([$id]);
            flash('Collector alerted.');
        }
    } elseif ($a === 'paid') {
        $paid = (int)$_POST['paid'];
        $pdo->prepare('UPDATE bins SET paid = ?, paid_on = IF(? = 1, NOW(), NULL) WHERE id = ?')
            ->execute([$paid, $paid, (int)$_POST['bin_id']]);
    } elseif ($a === 'level') {              // simulate the IoT sensor
        $pdo->prepare('UPDATE bins SET level = ?, updated = NOW() WHERE id = ?')
            ->execute([max(0, min(100, (int)$_POST['level'])), (int)$_POST['bin_id']]);
    }
    header('Location: admin.php'); exit;
}

$bins  = bin_rows();
$users = db()->query('SELECT name, email, role FROM users ORDER BY role, name')->fetchAll();
page_start('Admin');
?>
<div class="card"><h2>Create user</h2>
  <form method="post" class="row">
    <?= csrf() ?><input type="hidden" name="action" value="create_user">
    <input name="name" placeholder="Full name" required>
    <input name="email" type="email" placeholder="Email" required>
    <input name="password" type="password" placeholder="Password (min 6)" required>
    <select name="role"><option value="resident">Resident</option><option value="collector">Collector</option><option value="admin">Admin</option></select>
    <input name="house" placeholder="House / unit (residents)">
    <button>Create</button>
  </form>
</div>

<div class="card"><h2>Bins (needs emptying at <?= THRESHOLD ?>%+) <a class="btn alt" href="admin.php">Refresh</a></h2>
  <table>
    <tr><th>House</th><th>Resident</th><th>Fill level</th><th>Status</th><th>Payment</th><th>Actions</th></tr>
    <?php foreach ($bins as $b): ?>
    <tr>
      <td><?= e($b['house']) ?></td><td><?= e($b['resident']) ?></td>
      <td><?= bar($b['level']) ?></td><td><?= status_tag($b) ?></td><td><?= pay_tag($b) ?></td>
      <td class="actions">
        <form method="post" class="inline"><?= csrf() ?><input type="hidden" name="action" value="alert"><input type="hidden" name="bin_id" value="<?= $b['id'] ?>">
          <button class="warn" <?= $b['alert_id'] ? 'disabled' : '' ?>>Alert collector</button></form>
        <form method="post" class="inline"><?= csrf() ?><input type="hidden" name="action" value="paid"><input type="hidden" name="bin_id" value="<?= $b['id'] ?>">
          <input type="hidden" name="paid" value="<?= $b['paid'] ? 0 : 1 ?>"><button class="alt">Mark <?= $b['paid'] ? 'unpaid' : 'paid' ?></button></form>
        <form method="post" class="inline"><?= csrf() ?><input type="hidden" name="action" value="level"><input type="hidden" name="bin_id" value="<?= $b['id'] ?>">
          <input type="number" name="level" min="0" max="100" value="<?= (int)$b['level'] ?>" style="width:70px"><button class="alt">Set level</button></form>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$bins): ?><tr><td colspan="6">No bins yet. Create a resident above.</td></tr><?php endif; ?>
  </table>
</div>

<div class="card"><h2>Users</h2>
  <table><tr><th>Name</th><th>Email</th><th>Role</th></tr>
  <?php foreach ($users as $u): ?><tr><td><?= e($u['name']) ?></td><td><?= e($u['email']) ?></td><td><?= e($u['role']) ?></td></tr><?php endforeach; ?>
  </table>
</div>
<?php page_end();

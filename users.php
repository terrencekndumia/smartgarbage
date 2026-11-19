<?php
require __DIR__ . '/layout.php';
$u = require_role('admin');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    if (($_POST['action'] ?? '') === 'delete') {          // remove a user
        $id = (int)($_POST['id'] ?? 0);
        $st = db()->prepare('SELECT role FROM users WHERE id=?'); $st->execute([$id]); $t = $st->fetch();
        if (!$t) flash('User not found.');
        elseif ($id === (int)$u['id']) flash('You cannot delete your own account.');
        elseif ($t['role'] === 'admin' && (int)db()->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn() <= 1) flash('Keep at least one admin account.');
        else { db()->prepare('DELETE FROM users WHERE id=?')->execute([$id]); flash('User deleted.'); }
        header('Location: users.php'); exit;
    }
    $role = $_POST['role'] ?? '';
    $r = create_user($_POST['name'] ?? '', $_POST['email'] ?? '', $_POST['phone'] ?? '', $role, $_POST['password'] ?? '', $_POST['unit'] ?? '');
    flash($r === true ? ($role === 'resident' ? 'Resident created. Now register their bin on the Bins page.' : 'User created.') : $r);
    header('Location: users.php'); exit;
}
$users = db()->query('SELECT u.*, (SELECT b.id FROM garbage_bins b WHERE b.resident_id=u.id LIMIT 1) AS bin_id FROM users u ORDER BY u.role, u.name')->fetchAll();
page_start('Users', 'users.php');
?>
<h1>Users</h1>
<div class="card"><h2>Create user</h2>
<form method="post" class="row"><?= csrf() ?>
  <input name="name" placeholder="Full name" required>
  <input name="email" type="email" placeholder="Email" required>
  <input name="phone" placeholder="Phone">
  <input name="password" type="password" placeholder="Password (min 6)" required>
  <select name="role"><option value="resident">Resident</option><option value="collector">Collector</option><option value="admin">Admin</option></select>
  <input name="unit" placeholder="Unit (residents)" style="width:140px">
  <button>Create</button></form>
<p class="muted"><small>After creating a resident, register their bin on the Bins page. Each resident has their own bin.</small></p></div>
<div class="card scroll"><table><tr><th>Name</th><th>Email</th><th>Phone</th><th>Unit</th><th>Role</th><th>Bin</th><th></th></tr>
<?php foreach ($users as $x): ?><tr><td><?= e($x['name']) ?></td><td><?= e($x['email']) ?></td><td><?= e($x['phone']) ?></td><td><?= e($x['unit']) ?></td><td><?= e($x['role']) ?></td>
  <td><?php if ($x['role'] === 'resident'): ?><?= $x['bin_id'] ? e(bin_label($x['bin_id'])) : '<span class="tag amber">No bin yet</span>' ?><?php endif; ?></td>
  <td><?php if ((int)$x['id'] !== (int)$u['id']): ?><form method="post" class="inline" onsubmit="return confirm('Delete this user? This cannot be undone.')"><?= csrf() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $x['id'] ?>"><button class="warn">Delete</button></form><?php endif; ?></td></tr><?php endforeach; ?>
</table></div>
<?php page_end();

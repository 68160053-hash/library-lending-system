<?php
require 'auth.php'; require 'config/db.php';
require_admin(); // server-side check: only admins, read from the database

$roleLabels = ['member' => 'สมาชิก', 'staff' => 'เจ้าหน้าที่', 'admin' => 'แอดมิน'];
$myId = (int)$_SESSION['user_id'];
$q = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 100);
$like = '%' . addcslashes($q, '%_\\') . '%';

$stmt = mysqli_prepare($conn, 'SELECT user_id, full_name, email, role, created_at, google_sub IS NOT NULL FROM lib_users
                               WHERE CHAR_LENGTH(?) = 0 OR full_name LIKE ? OR email LIKE ? ORDER BY user_id DESC LIMIT 500');
mysqli_stmt_bind_param($stmt, 'sss', $q, $like, $like);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $uid, $name, $email, $role, $created, $viaGoogle);
$users = [];
while (mysqli_stmt_fetch($stmt)) $users[] = compact('uid', 'name', 'email', 'role', 'created', 'viaGoogle');
mysqli_stmt_close($stmt);

$pageTitle = 'จัดการผู้ใช้';
require 'includes/header.php';
?>
<div class="page-head"><div><h1>จัดการผู้ใช้</h1><p>ตั้งบทบาทสมาชิก เจ้าหน้าที่ และแอดมิน</p></div>
  <form action="users.php" method="get" class="search"><label class="sr-only" for="uq">ค้นหา</label><input type="text" id="uq" name="q" value="<?= h($q) ?>" maxlength="100" placeholder="ชื่อหรืออีเมล"><button type="submit">ค้นหา</button></form></div>
<div class="card"><div class="tbl-wrap"><table class="tbl">
  <caption class="sr-only">ผู้ใช้ทั้งหมด</caption>
  <thead><tr><th>ชื่อ</th><th>อีเมล</th><th>สมัครเมื่อ</th><th>บทบาท</th></tr></thead>
  <tbody>
  <?php foreach ($users as $u): ?>
  <tr>
    <td><strong><?= h($u['name']) ?></strong><?= $u['viaGoogle'] ? ' <span class="badge b-info">Google</span>' : '' ?></td>
    <td><?= h($u['email']) ?></td>
    <td><?= h(date('d/m/Y', strtotime($u['created']))) ?></td>
    <td>
      <?php if ((int)$u['uid'] === $myId): ?><span class="badge b-ok"><?= h($roleLabels[$u['role']] ?? $u['role']) ?> (คุณ)</span>
      <?php else: ?>
      <form action="user-role.php" method="post" class="inline-form"><?php csrf_field(); ?>
        <input type="hidden" name="user_id" value="<?= (int)$u['uid'] ?>">
        <select name="role" aria-label="บทบาทของ <?= h($u['name']) ?>" style="width:auto;display:inline-block"><?php foreach ($roleLabels as $key => $label): ?><option value="<?= h($key) ?>"<?= $u['role'] === $key ? ' selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?></select>
        <button type="submit" class="btn btn-outline btn-sm">บันทึก</button>
      </form>
      <?php endif; ?>
    </td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$users): ?><tr><td colspan="4" class="center muted" style="padding:2rem">ไม่พบผู้ใช้</td></tr><?php endif; ?>
  </tbody>
</table></div></div>
<?php require 'includes/footer.php'; ?>

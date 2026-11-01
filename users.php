<?php
require 'auth.php'; require 'config/db.php';
require_admin(); // server-side check: only admins, read from the database

$roleLabels = ['member' => 'สมาชิก', 'staff' => 'เจ้าหน้าที่', 'admin' => 'แอดมิน'];
$myId = (int)$_SESSION['user_id'];

// All users, newest first. No user input here, so mysqli_query is safe.
$result = mysqli_query($conn, 'SELECT user_id, full_name, email, role, created_at FROM lib_users ORDER BY user_id DESC LIMIT 500');

$pageTitle = 'จัดการผู้ใช้';
require 'includes/header.php';
?>
<main id="main">
  <div class="container">
    <section class="panel frame-silver" aria-labelledby="users-title">
    <p class="eyebrow">THE GUEST BOOK</p>
    <h1 class="page-title" id="users-title">จัดการผู้ใช้</h1>
    <?php if (isset($_GET['saved'])): ?><p class="hint">เปลี่ยนบทบาทเรียบร้อย</p><?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
    <div class="alert" role="alert"><svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v6M12 16.5v.5"/></svg><span>เปลี่ยนไม่ได้ (เปลี่ยนบทบาทของตัวเองไม่ได้ เพื่อไม่ให้ไม่เหลือแอดมิน)</span></div>
    <?php endif; ?>
    <div class="checkout-card paper frame">
      <div class="table-wrap">
        <table>
          <caption>ผู้ใช้ทั้งหมด</caption>
          <thead><tr><th scope="col">ชื่อ</th><th scope="col">อีเมล</th><th scope="col">สมัครเมื่อ</th><th scope="col">บทบาท</th></tr></thead>
          <tbody>
            <?php if ($result): while ($row = mysqli_fetch_assoc($result)): ?>
            <tr>
              <td data-label="ชื่อ" class="book-title"><?= h($row['full_name']) ?></td>
              <td data-label="อีเมล"><?= h($row['email']) ?></td>
              <td data-label="สมัครเมื่อ"><span class="typed"><?= h(date('d/m/Y', strtotime($row['created_at']))) ?></span></td>
              <td data-label="บทบาท">
                <?php if ((int)$row['user_id'] === $myId): ?>
                <span class="stamp stamp--ok"><?= h($roleLabels[$row['role']] ?? $row['role']) ?> (คุณ)</span>
                <?php else: ?>
                <form action="user-role.php" method="post" style="display:inline"><?php csrf_field(); ?>
                  <input type="hidden" name="user_id" value="<?= (int)$row['user_id'] ?>">
                  <select name="role" aria-label="บทบาทของ <?= h($row['full_name']) ?>">
                    <?php foreach ($roleLabels as $key => $label): ?><option value="<?= h($key) ?>"<?= $row['role'] === $key ? ' selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?>
                  </select>
                  <button type="submit" class="btn btn-outline">บันทึก</button>
                </form>
                <?php endif; ?>
              </td>
            </tr>
            <?php endwhile; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    </section>
  </div>
</main>
<?php require 'includes/footer.php'; ?>

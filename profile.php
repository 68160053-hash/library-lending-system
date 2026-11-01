<?php
require 'auth.php'; require 'config/db.php';
require_login();
$me = (int)$_SESSION['user_id']; // the profile is always the logged-in member's own, never chosen from the URL
$errors = ['profile' => '', 'password' => ''];

$stmt = mysqli_prepare($conn, 'SELECT full_name, email, phone, default_address, google_sub IS NOT NULL, password_hash FROM lib_users WHERE user_id = ?');
mysqli_stmt_bind_param($stmt, 'i', $me);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $name, $email, $phone, $address, $viaGoogle, $hash);
if (!mysqli_stmt_fetch($stmt)) { mysqli_stmt_close($stmt); session_destroy(); header('Location: login.php'); exit; }
mysqli_stmt_close($stmt);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'profile') {
        $name = trim((string)($_POST['full_name'] ?? ''));
        $phone = trim((string)($_POST['phone'] ?? ''));
        $address = trim((string)($_POST['default_address'] ?? ''));
        if (mb_strlen($name) < 2 || mb_strlen($name) > 100) $errors['profile'] = 'ชื่อต้องยาว 2-100 ตัวอักษร';
        elseif ($phone !== '' && !preg_match('/^[0-9+ -]{9,20}$/', $phone)) $errors['profile'] = 'เบอร์โทรไม่ถูกต้อง (ตัวเลข 9-20 ตัว)';
        elseif (mb_strlen($address) > 255) $errors['profile'] = 'ที่อยู่ยาวได้ไม่เกิน 255 ตัวอักษร';
        else {
            $stmt = mysqli_prepare($conn, 'UPDATE lib_users SET full_name = ?, phone = ?, default_address = ? WHERE user_id = ?');
            mysqli_stmt_bind_param($stmt, 'sssi', $name, $phone, $address, $me);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $_SESSION['full_name'] = $name;
            flash('ok', 'บันทึกข้อมูลเรียบร้อย');
            header('Location: profile.php'); exit;
        }
    } elseif ($action === 'password') {
        $old = (string)($_POST['old_password'] ?? '');
        $new = (string)($_POST['new_password'] ?? '');
        $confirm = (string)($_POST['new_password_confirm'] ?? '');
        $key = 'u' . $me;
        if ($viaGoogle) $errors['password'] = 'บัญชีนี้เข้าด้วย Google จึงไม่มีรหัสผ่านให้เปลี่ยน';
        elseif (too_many_attempts($conn, 'password', $key, 5, 15)) { http_response_code(429); $errors['password'] = 'ใส่รหัสผ่านเดิมผิดหลายครั้งเกินไป กรุณารอ 15 นาที'; }
        elseif (!password_verify($old, $hash)) { log_attempt($conn, 'password', $key); $errors['password'] = 'รหัสผ่านเดิมไม่ถูกต้อง'; }
        elseif (preg_match('/[\x00-\x1f\x7f]/', $new)) $errors['password'] = 'รหัสผ่านมีอักขระที่ใช้ไม่ได้';
        elseif (strlen($new) < 8 || strlen($new) > 72) $errors['password'] = 'รหัสผ่านใหม่ต้องยาว 8-72 ตัวอักษร';
        elseif ($new !== $confirm) $errors['password'] = 'รหัสผ่านใหม่และการยืนยันไม่ตรงกัน';
        elseif ($new === $old) $errors['password'] = 'รหัสผ่านใหม่ต้องไม่เหมือนรหัสเดิม';
        else {
            $newHash = password_hash($new, PASSWORD_DEFAULT);
            $stmt = mysqli_prepare($conn, 'UPDATE lib_users SET password_hash = ? WHERE user_id = ?');
            mysqli_stmt_bind_param($stmt, 'si', $newHash, $me);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            session_regenerate_id(true); // a changed password gets a fresh session id
            flash('ok', 'เปลี่ยนรหัสผ่านเรียบร้อย');
            header('Location: profile.php'); exit;
        }
    }
}

$pageTitle = 'โปรไฟล์';
require 'includes/header.php';
?>
<div class="page-head"><div><h1>โปรไฟล์ของฉัน</h1><p>ข้อมูลนี้ใช้เติมให้อัตโนมัติตอนสั่งเช่า</p></div></div>
<div class="cols-2">
  <section class="card" aria-labelledby="p-info">
    <h3 id="p-info">ข้อมูลส่วนตัว</h3>
    <?php if ($errors['profile']): ?><div class="alert alert-err" role="alert"><?= h($errors['profile']) ?></div><?php endif; ?>
    <form action="profile.php" method="post"><?php csrf_field(); ?><input type="hidden" name="action" value="profile">
      <div class="field"><label for="email">อีเมล</label><input type="email" id="email" value="<?= h($email) ?>" disabled><p class="hint">เปลี่ยนอีเมลไม่ได้</p></div>
      <div class="field"><label for="full_name">ชื่อ-นามสกุล</label><input type="text" id="full_name" name="full_name" maxlength="100" autocomplete="name" value="<?= h($name) ?>" required></div>
      <div class="field"><label for="phone">เบอร์โทร</label><input type="tel" id="phone" name="phone" maxlength="20" autocomplete="tel" value="<?= h($phone) ?>"></div>
      <div class="field"><label for="default_address">ที่อยู่จัดส่งประจำ</label><textarea id="default_address" name="default_address" maxlength="255" autocomplete="street-address"><?= h($address) ?></textarea></div>
      <button type="submit" class="btn btn-primary">บันทึก</button>
    </form>
  </section>
  <section class="card" aria-labelledby="p-pw">
    <h3 id="p-pw">เปลี่ยนรหัสผ่าน</h3>
    <?php if ($viaGoogle): ?><div class="alert alert-info">บัญชีนี้เข้าสู่ระบบด้วย Google จึงไม่มีรหัสผ่านของร้าน</div>
    <?php else: ?>
    <?php if ($errors['password']): ?><div class="alert alert-err" role="alert"><?= h($errors['password']) ?></div><?php endif; ?>
    <form action="profile.php" method="post"><?php csrf_field(); ?><input type="hidden" name="action" value="password">
      <div class="field"><label for="old_password">รหัสผ่านเดิม</label><div class="pw-wrap"><input type="password" id="old_password" name="old_password" autocomplete="current-password" required><button type="button" class="pw-toggle" data-pw-toggle="old_password" aria-pressed="false">แสดง</button></div></div>
      <div class="field"><label for="new_password">รหัสผ่านใหม่</label><div class="pw-wrap"><input type="password" id="new_password" name="new_password" autocomplete="new-password" minlength="8" maxlength="72" required><button type="button" class="pw-toggle" data-pw-toggle="new_password" aria-pressed="false">แสดง</button></div><p class="hint">อย่างน้อย 8 ตัวอักษร</p></div>
      <div class="field"><label for="new_password_confirm">ยืนยันรหัสผ่านใหม่</label><div class="pw-wrap"><input type="password" id="new_password_confirm" name="new_password_confirm" autocomplete="new-password" required><button type="button" class="pw-toggle" data-pw-toggle="new_password_confirm" aria-pressed="false">แสดง</button></div></div>
      <button type="submit" class="btn btn-primary">เปลี่ยนรหัสผ่าน</button>
    </form>
    <?php endif; ?>
  </section>
</div>
<?php require 'includes/footer.php'; ?>

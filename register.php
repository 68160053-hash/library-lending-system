<?php
require 'auth.php'; require 'config/db.php';
if (!empty($_SESSION['user_id'])) { header('Location: index.php'); exit; }
$error = ''; $name = ''; $email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    // Step 1: read and clean the form values
    $name = trim((string)($_POST['full_name'] ?? ''));
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $pass = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['password_confirm'] ?? '');
    // Step 2: validate on the server (the browser's required/minlength can be bypassed)
    if (mb_strlen($name) < 2 || mb_strlen($name) > 100) $error = 'ชื่อต้องยาว 2-100 ตัวอักษร';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 120) $error = 'อีเมลไม่ถูกต้อง';
    elseif (strlen($pass) < 8 || strlen($pass) > 72) $error = 'รหัสผ่านต้องยาว 8-72 ตัวอักษร'; // 72 = bcrypt limit
    elseif ($pass !== $confirm) $error = 'รหัสผ่านและการยืนยันไม่ตรงกัน';
    else {
        // Step 3: is the email already used? (prepared SELECT)
        $stmt = mysqli_prepare($conn, 'SELECT user_id FROM lib_users WHERE email = ? LIMIT 1');
        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $existingId);
        $exists = mysqli_stmt_fetch($stmt);
        mysqli_stmt_close($stmt);
        if ($exists) $error = 'อีเมลนี้มีผู้ใช้แล้ว';
        else {
            // Step 4: hash the password; the role is fixed to 'member' here, never taken from the form
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $stmt = mysqli_prepare($conn, "INSERT INTO lib_users (email, password_hash, full_name, role) VALUES (?, ?, ?, 'member')");
            mysqli_stmt_bind_param($stmt, 'sss', $email, $hash, $name);
            $ok = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            // Step 5: success -> login page. A failed INSERT is most likely the UNIQUE email (two people at once).
            if ($ok) { header('Location: login.php?registered=1'); exit; }
            $error = 'อีเมลนี้มีผู้ใช้แล้ว';
        }
    }
}
$pageTitle = 'สมัครสมาชิก';
require 'includes/header.php';
?>
<main id="main">
  <div class="container">
    <div class="letter-wrap">
      <div class="envelope" aria-hidden="true"></div>
      <span class="seal" aria-hidden="true">B</span>
      <section class="letter paper frame" aria-labelledby="reg-title">
        <p class="greeting">ถึง ผู้มาเยือนคนใหม่,</p>
        <h1 id="reg-title">ลงทะเบียนเป็นสมาชิก</h1>
        <?php if ($error): ?>
        <div class="alert" role="alert"><svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v6M12 16.5v.5"/></svg><span><?= h($error) ?></span></div>
        <?php endif; ?>
        <form action="register.php" method="post">
          <?php csrf_field(); ?>
          <div class="field">
            <label for="full_name">ชื่อ-นามสกุล</label>
            <input type="text" id="full_name" name="full_name" autocomplete="name" maxlength="100" value="<?= h($name) ?>" required>
          </div>
          <div class="field">
            <label for="email">อีเมล</label>
            <input type="email" id="email" name="email" autocomplete="email" maxlength="120" value="<?= h($email) ?>" required>
          </div>
          <div class="field">
            <label for="password">รหัสผ่าน</label>
            <input type="password" id="password" name="password" autocomplete="new-password" minlength="8" maxlength="72" aria-describedby="pw-hint" required>
            <p class="hint" id="pw-hint">อย่างน้อย 8 ตัวอักษร</p>
          </div>
          <div class="field">
            <label for="password_confirm">ยืนยันรหัสผ่าน</label>
            <input type="password" id="password_confirm" name="password_confirm" autocomplete="new-password" required>
          </div>
          <button type="submit" class="btn btn-primary btn-magic btn-block">สมัครสมาชิก</button>
        </form>
        <p class="form-foot">มีบัญชีแล้ว? <a href="login.php">เข้าสู่ระบบ</a></p>
      </section>
    </div>
  </div>
</main>
<?php require 'includes/footer.php'; ?>

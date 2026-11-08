<?php
require 'auth.php'; require 'config/db.php';
if (!empty($_SESSION['user_id'])) { header('Location: index.php'); exit; }
// Messages for the ?google_error=... codes sent by google-callback.php (only these fixed texts are ever shown)
$googleMsgs = ['off' => 'ยังไม่เปิดใช้การเข้าสู่ระบบด้วย Google', 'state' => 'คำขอหมดอายุหรือไม่ถูกต้อง กรุณากดปุ่ม Google ใหม่', 'denied' => 'ยกเลิกการเข้าสู่ระบบด้วย Google',
    'code' => 'เข้าสู่ระบบด้วย Google ไม่สำเร็จ ลองใหม่อีกครั้ง', 'token' => 'เข้าสู่ระบบด้วย Google ไม่สำเร็จ ลองใหม่อีกครั้ง',
    'email' => 'บัญชี Google นี้ยังไม่ได้ยืนยันอีเมล', 'taken' => 'อีเมลนี้สมัครด้วยรหัสผ่านไว้แล้ว กรุณาเข้าสู่ระบบด้วยอีเมลและรหัสผ่าน'];
$googleError = $googleMsgs[get_str('google_error')] ?? '';
$error = ''; $name = ''; $email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    // Step 1: read and clean the form values
    $name = trim((string)($_POST['full_name'] ?? ''));
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $pass = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['password_confirm'] ?? '');
    // Step 1.5: at most 10 sign-ups per IP per hour (stops scripts that fill the user table)
    $limited = too_many_attempts($conn, 'register', '', 10, 60);
    // Step 2: validate on the server (the browser's required/minlength can be bypassed)
    if ($limited) { http_response_code(429); $error = 'สมัครบ่อยเกินไป กรุณารอสักครู่'; }
    elseif (mb_strlen($name) < 2 || mb_strlen($name) > 100) $error = 'ชื่อต้องยาว 2-100 ตัวอักษร';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 120) $error = 'อีเมลไม่ถูกต้อง';
    elseif (preg_match('/[\x00-\x1f\x7f]/', $pass)) $error = 'รหัสผ่านมีอักขระที่ใช้ไม่ได้'; // bcrypt stops reading at a NUL byte
    elseif (strlen($pass) < 8 || strlen($pass) > 72) $error = 'รหัสผ่านต้องยาว 8-72 ตัวอักษร'; // 72 = bcrypt limit
    elseif ($pass !== $confirm) $error = 'รหัสผ่านและการยืนยันไม่ตรงกัน';
    else {
        log_attempt($conn, 'register', ''); // count every validated sign-up POST, so the "email already used" answer cannot be probed without limit
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
            $errno = mysqli_stmt_errno($stmt);
            mysqli_stmt_close($stmt);
            // Step 5: success -> login page. Error 1062 = UNIQUE email hit (two people at once); anything else is a real fault.
            if ($ok) { header('Location: login.php?registered=1'); exit; }
            $error = $errno === 1062 ? 'อีเมลนี้มีผู้ใช้แล้ว' : 'สมัครไม่สำเร็จ ลองใหม่อีกครั้ง';
        }
    }
}
$pageTitle = 'สมัครสมาชิก'; $pageCss = ['platform'];
require 'includes/header.php';
?>
<div class="auth-hall">
  <aside class="auth-side">
    <h2>สมัครฟรี เข้าฮอลล์ได้ทันที</h2>
    <p>มีบัญชีแล้วก็เช่าได้เลย ไม่มีค่าสมัคร</p>
    <ul>
      <li><?= icon('calendar', 20) ?>เช่า 7 / 14 / 30 วัน</li>
      <li><?= icon('shield', 20) ?>มัดจำคืนเต็มจำนวนเมื่อร้านได้รับหนังสือ</li>
      <li><?= icon('truck', 20) ?>ส่งถึงบ้านพร้อมเลขพัสดุ</li>
    </ul>
  </aside>
  <div class="auth-main">
    <h1>สมัครสมาชิก</h1>
    <p class="muted">สมัครฟรี เช่าหนังสือได้ทันที</p>
    <?php if ($googleError): ?><div class="alert alert-err" role="alert"><?= icon('info', 20) ?><span><?= h($googleError) ?></span></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-err" role="alert"><?= icon('info', 20) ?><span><?= h($error) ?></span></div><?php endif; ?>
    <form action="register.php" method="post">
      <?php csrf_field(); ?>
      <div class="field"><label for="full_name">ชื่อ-นามสกุล</label><input type="text" id="full_name" name="full_name" autocomplete="name" maxlength="100" value="<?= h($name) ?>" required></div>
      <div class="field"><label for="email">อีเมล</label><input type="email" id="email" name="email" autocomplete="username" maxlength="120" value="<?= h($email) ?>" required></div>
      <div class="field"><label for="password">รหัสผ่าน</label><div class="pw-wrap"><input type="password" id="password" name="password" autocomplete="new-password" minlength="8" maxlength="72" aria-describedby="pw-hint" data-strength required><button type="button" class="pw-toggle" data-pw-toggle="password" aria-pressed="false">แสดง</button></div><p class="hint" id="pw-hint">อย่างน้อย 8 ตัวอักษร</p></div>
      <div class="field"><label for="password_confirm">ยืนยันรหัสผ่าน</label><div class="pw-wrap"><input type="password" id="password_confirm" name="password_confirm" autocomplete="new-password" required><button type="button" class="pw-toggle" data-pw-toggle="password_confirm" aria-pressed="false">แสดง</button></div></div>
      <button type="submit" class="btn btn-primary btn-block">สมัครสมาชิก</button>
    </form>
    <?php if ($googleReady): ?>
    <p class="or-line"><span>หรือ</span></p>
    <a class="btn btn-outline btn-block btn-google" href="google-login.php"><span class="g-mark" aria-hidden="true">G</span>สมัครด้วย Google</a>
    <?php endif; ?>
    <p class="switch-line">มีบัญชีแล้ว? <a href="login.php">เข้าสู่ระบบ</a></p>
  </div>
</div>
<?php require 'includes/footer.php'; ?>

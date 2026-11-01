<?php
require 'auth.php'; require 'config/db.php';
if (!empty($_SESSION['user_id'])) { header('Location: ' . (is_staff() ? 'dashboard.php' : 'index.php')); exit; }
// Messages for the ?google_error=... codes sent by google-callback.php (only these fixed texts are ever shown)
$googleMsgs = ['off' => 'ยังไม่เปิดใช้การเข้าสู่ระบบด้วย Google', 'state' => 'คำขอหมดอายุหรือไม่ถูกต้อง กรุณากดปุ่ม Google ใหม่', 'denied' => 'ยกเลิกการเข้าสู่ระบบด้วย Google',
    'code' => 'เข้าสู่ระบบด้วย Google ไม่สำเร็จ ลองใหม่อีกครั้ง', 'token' => 'เข้าสู่ระบบด้วย Google ไม่สำเร็จ ลองใหม่อีกครั้ง',
    'email' => 'บัญชี Google นี้ยังไม่ได้ยืนยันอีเมล', 'taken' => 'อีเมลนี้สมัครด้วยรหัสผ่านไว้แล้ว กรุณาเข้าสู่ระบบด้วยอีเมลและรหัสผ่าน'];
$googleError = $googleMsgs[get_str('google_error')] ?? '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $password = (string)($_POST['password'] ?? '');
    // Brake against password guessing: 10 wrong tries for the same email from one IP, or 100 from one IP, in 15 minutes (shared campus Wi-Fi means one IP can be many students)
    if (too_many_attempts($conn, 'login', $email, 10, 15) || too_many_attempts($conn, 'login', '', 100, 15)) {
        http_response_code(429);
        $error = 'ลองผิดหลายครั้งเกินไป กรุณารอ 15 นาที';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        $error = 'อีเมลหรือรหัสผ่านไม่ถูกต้อง';
    } else {
        // Step 1: one user by email, prepared statement (the email is never concatenated into SQL).
        $sql = 'SELECT user_id, full_name, role, password_hash FROM lib_users WHERE email = ? LIMIT 1';
        // Step 2: bind, execute, read the matched row.
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $userId, $fullName, $role, $hash);
        $found = mysqli_stmt_fetch($stmt);
        mysqli_stmt_close($stmt);
        // Step 3: compare with password_verify against the stored hash, never with ==.
        // An unknown email is checked against a dummy hash too, so both cases take about the same time (cost 12 = PHP 8.4 default on Angsila).
        if (!$found) $hash = '$2y$12$4vWZLm7cUZaT56ayoMSHk.nZfbLMXDxCeXZHZKBrp47Bc7EWqoAkm';
        if (password_verify($password, $hash) && $found) {
            // Step 4: new session id (stops session fixation) and remember who logged in.
            session_regenerate_id(true);
            $_SESSION['csrf'] = bin2hex(random_bytes(32)); // fresh token for the new login
            $_SESSION['login_at'] = $_SESSION['last_seen'] = time();
            $_SESSION['user_id'] = $userId; $_SESSION['full_name'] = $fullName; $_SESSION['role'] = $role;
            $_SESSION['pwv'] = pw_fingerprint($conn, $userId);
            // Step 5: go to the private page (staff start at the overdue list).
            header('Location: ' . (($role === 'staff' || $role === 'admin') ? 'dashboard.php' : 'index.php')); exit;
        }
        $error = 'อีเมลหรือรหัสผ่านไม่ถูกต้อง'; // same message for unknown email and wrong password
        log_attempt($conn, 'login', $email);
    }
}
$pageTitle = 'เข้าสู่ระบบ'; $pageCss = ['platform'];
require 'includes/header.php';
?>
<div class="auth-hall">
  <aside class="auth-side">
    <h2>ยินดีต้อนรับกลับสู่ฮอลล์</h2>
    <p>เข้าสู่ระบบเพื่อเช่าหนังสือและติดตามสถานะการเช่าของคุณ</p>
    <ul>
      <li><?= icon('calendar', 20) ?>เช่า 7 / 14 / 30 วัน</li>
      <li><?= icon('shield', 20) ?>มัดจำคืนเต็มจำนวนเมื่อร้านได้รับหนังสือ</li>
      <li><?= icon('truck', 20) ?>ส่งถึงบ้านพร้อมเลขพัสดุ</li>
    </ul>
  </aside>
  <div class="auth-main">
    <h1>เข้าสู่ระบบ</h1>
    <p class="muted">เข้าสู่ระบบเพื่อเช่าหนังสือและติดตามสถานะ</p>
    <?php if (isset($_GET['registered'])): ?><div class="alert alert-ok" role="status"><?= icon('check', 20) ?><span>สมัครสมาชิกเรียบร้อย กรุณาเข้าสู่ระบบ</span></div><?php endif; ?>
    <?php if (isset($_GET['logged_out'])): ?><div class="alert alert-info" role="status"><?= icon('info', 20) ?><span>ออกจากระบบแล้ว</span></div><?php endif; ?>
    <?php if ($googleError): ?><div class="alert alert-err" role="alert"><?= icon('info', 20) ?><span><?= h($googleError) ?></span></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-err" role="alert"><?= icon('info', 20) ?><span><?= h($error) ?></span></div><?php endif; ?>
    <form action="login.php" method="post">
      <?php csrf_field(); ?>
      <div class="field"><label for="email">อีเมล</label><input type="email" id="email" name="email" autocomplete="username" required></div>
      <div class="field"><label for="password">รหัสผ่าน</label><div class="pw-wrap"><input type="password" id="password" name="password" autocomplete="current-password" required><button type="button" class="pw-toggle" data-pw-toggle="password" aria-pressed="false">แสดง</button></div></div>
      <button type="submit" class="btn btn-primary btn-block">เข้าสู่ระบบ</button>
    </form>
    <?php if ($googleReady): ?>
    <p class="or-line"><span>หรือ</span></p>
    <a class="btn btn-outline btn-block btn-google" href="google-login.php"><span class="g-mark" aria-hidden="true">G</span>เข้าสู่ระบบด้วย Google</a>
    <?php endif; ?>
    <p class="switch-line">ยังไม่มีบัญชี? <a href="register.php">สมัครสมาชิก</a></p>
  </div>
</div>
<?php require 'includes/footer.php'; ?>

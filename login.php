<?php
require 'auth.php'; require 'config/db.php';
if (!empty($_SESSION['user_id'])) { header('Location: index.php'); exit; }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $password = (string)($_POST['password'] ?? '');
    // Brake against password guessing: 5 wrong tries for the same email from one IP, or 30 from one IP, in 15 minutes
    if (too_many_attempts($conn, 'login', $email, 5, 15) || too_many_attempts($conn, 'login', '', 30, 15)) {
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
        // An unknown email is checked against a dummy hash too, so both cases take about the same time.
        if (!$found) $hash = '$2y$10$zzb7tZBBW2Qrh3AJ5xM2g.LK5f8pCE8.ugOd7V5/51CyTxuHsC0Gm';
        if (password_verify($password, $hash) && $found) {
            // Step 4: new session id (stops session fixation) and remember who logged in.
            session_regenerate_id(true);
            $_SESSION['csrf'] = bin2hex(random_bytes(32)); // fresh token for the new login
            $_SESSION['login_at'] = $_SESSION['last_seen'] = time();
            $_SESSION['user_id'] = $userId; $_SESSION['full_name'] = $fullName; $_SESSION['role'] = $role;
            // Step 5: go to the private page (staff start at the overdue list).
            header('Location: ' . (($role === 'staff' || $role === 'admin') ? 'overdue.php' : 'my-loans.php')); exit;
        }
        $error = 'อีเมลหรือรหัสผ่านไม่ถูกต้อง'; // same message for unknown email and wrong password
        log_attempt($conn, 'login', $email);
    }
}
$pageTitle = 'เข้าสู่ระบบ';
require 'includes/header.php';
?>
<main id="main">
  <div class="container">
    <div class="letter-wrap">
      <div class="envelope" aria-hidden="true"></div>
      <span class="seal" aria-hidden="true">B</span>
      <section class="letter paper frame" aria-labelledby="login-title">
        <p class="greeting">ถึง ผู้มาเยือน,</p>
        <h1 id="login-title">เข้าสู่ระบบ</h1>
        <?php if (isset($_GET['registered'])): ?><p class="hint">สมัครสมาชิกเรียบร้อย กรุณาเข้าสู่ระบบ</p><?php endif; ?>
        <?php if (isset($_GET['logged_out'])): ?><p class="hint">ออกจากระบบแล้ว</p><?php endif; ?>
        <?php if ($error): ?>
        <div class="alert" role="alert"><svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v6M12 16.5v.5"/></svg><span><?= h($error) ?></span></div>
        <?php endif; ?>
        <form action="login.php" method="post">
          <?php csrf_field(); ?>
          <div class="field">
            <label for="email">อีเมล</label>
            <input type="email" id="email" name="email" autocomplete="email" required>
          </div>
          <div class="field">
            <label for="password">รหัสผ่าน</label>
            <input type="password" id="password" name="password" autocomplete="current-password" required>
          </div>
          <button type="submit" class="btn btn-primary btn-magic btn-block">เข้าสู่ระบบ</button>
        </form>
        <p class="form-foot">ยังไม่มีบัญชี? <a href="register.php">สมัครสมาชิก</a></p>
      </section>
    </div>
  </div>
</main>
<?php require 'includes/footer.php'; ?>

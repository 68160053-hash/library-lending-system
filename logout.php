<?php
require 'auth.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Logout flow: verify the CSRF token, clear the data, expire the cookie, destroy the session.
    verify_csrf();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $c = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $c['path'], $c['domain'], $c['secure'], $c['httponly']);
    }
    session_destroy();
    header('Location: login.php?logged_out=1'); exit;
}
// A GET request (typing the URL or a link) must NOT log out: show a confirm form instead.
$pageTitle = 'ออกจากระบบ';
require 'includes/header.php';
?>
<div class="auth-wrap"><div class="card center">
  <h1>ออกจากระบบ?</h1>
  <form action="logout.php" method="post"><?php csrf_field(); ?><button type="submit" class="btn btn-primary btn-block">ยืนยันออกจากระบบ</button></form>
  <p class="small" style="margin:1rem 0 0"><a href="index.php">กลับหน้าร้าน</a></p>
</div></div>
<?php require 'includes/footer.php'; ?>

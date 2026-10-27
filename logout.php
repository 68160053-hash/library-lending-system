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
<main id="main">
  <div class="container">
    <section class="letter paper frame">
      <h1>ออกจากระบบ?</h1>
      <form method="post"><?php csrf_field(); ?>
        <button type="submit" class="btn btn-primary btn-block">ยืนยันออกจากระบบ</button>
      </form>
      <p class="form-foot"><a href="index.php">กลับ</a></p>
    </section>
  </div>
</main>
<?php require 'includes/footer.php'; ?>

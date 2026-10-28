<?php
// Shared helper: session + CSRF + login/role checks. Included by every page.
if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params(['httponly'=>true, 'secure'=>(!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https', 'samesite'=>'Lax']);
    session_start();
}
// One CSRF token per session, used by every POST form
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function csrf_field() { echo '<input type="hidden" name="csrf" value="'.h($_SESSION['csrf']).'">'; }
function verify_csrf() {
    if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) { http_response_code(403); exit('Invalid CSRF token'); }
}
function require_login() {
    if (empty($_SESSION['user_id'])) { header('Location: login.php'); exit; }
}
function is_staff() {
    $role = $_SESSION['role'] ?? '';
    return $role === 'staff' || $role === 'admin';
}
// Role is checked on the server for every staff page (hiding the menu is not security).
// The role is re-read from the DB each time, so demoting a user takes effect at once (needs $conn from config/db.php).
function require_staff() {
    global $conn;
    require_login();
    $stmt = mysqli_prepare($conn, 'SELECT role FROM lib_users WHERE user_id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $_SESSION['user_id']);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $role);
    $found = mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
    $_SESSION['role'] = $found ? $role : ''; // keep the menu in sync; a deleted user loses all rights
    if (!is_staff()) { http_response_code(403); exit('หน้านี้สำหรับเจ้าหน้าที่เท่านั้น'); }
}

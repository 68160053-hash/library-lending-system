<?php
// Shared helper: session + CSRF + security headers + login/role checks. Included by every page.
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

// Security headers (sent before any output). CSP = only our own scripts run, so injected <script> is dead on arrival.
header_remove('X-Powered-By');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; img-src 'self' data:; object-src 'none'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'");
header('X-Frame-Options: DENY');                      // nobody can put our pages in a frame (clickjacking)
header('X-Content-Type-Options: nosniff');            // the browser must trust the declared file type
header('Referrer-Policy: same-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
if ($isHttps) header('Strict-Transport-Security: max-age=31536000');

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('bls_session');
    session_set_cookie_params(['httponly' => true, 'secure' => $isHttps, 'samesite' => 'Lax']);
    session_start();
}
// A logged-in session dies after 30 minutes without activity or 8 hours in total
if (!empty($_SESSION['user_id'])) {
    $now = time();
    if ($now - ($_SESSION['last_seen'] ?? 0) > 1800 || $now - ($_SESSION['login_at'] ?? 0) > 28800) {
        $_SESSION = [];
        session_regenerate_id(true);
    } else {
        $_SESSION['last_seen'] = $now;
        header('Cache-Control: no-store'); // private pages must not stay in the browser cache (Back button after logout)
    }
}
// One CSRF token per session, used by every POST form
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function csrf_field() { echo '<input type="hidden" name="csrf" value="'.h($_SESSION['csrf']).'">'; }
function verify_csrf() {
    if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) { http_response_code(403); exit('หน้าหมดอายุหรือแบบฟอร์มไม่ถูกต้อง <a href="login.php">กลับไปลองใหม่</a>'); }
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

// ---- Rate limiting (stored in lib_attempts, so a bot cannot reset it by dropping its cookie) ----
function client_ip() { return substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45); }
// Has this IP (and optionally this email) made $max or more attempts of this kind in the last $minutes?
function too_many_attempts($conn, $kind, $email, $max, $minutes) {
    $ip = client_ip();
    $stmt = mysqli_prepare($conn, 'SELECT COUNT(*) FROM lib_attempts WHERE kind = ? AND ip = ? AND (? = "" OR email = ?) AND tried_at > NOW() - INTERVAL ? MINUTE');
    mysqli_stmt_bind_param($stmt, 'ssssi', $kind, $ip, $email, $email, $minutes);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $n);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
    return $n >= $max;
}
function log_attempt($conn, $kind, $email) {
    $ip = client_ip(); $email = substr($email, 0, 120);
    $stmt = mysqli_prepare($conn, 'INSERT INTO lib_attempts (kind, ip, email) VALUES (?, ?, ?)');
    mysqli_stmt_bind_param($stmt, 'sss', $kind, $ip, $email);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    mysqli_query($conn, 'DELETE FROM lib_attempts WHERE tried_at < NOW() - INTERVAL 1 DAY'); // keep the table small
}

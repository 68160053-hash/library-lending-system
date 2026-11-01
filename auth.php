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
    // The cookie belongs to this app's folder only: the university host also serves other students' sites under other paths.
    $cookiePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/') . '/';
    session_set_cookie_params(['path' => $cookiePath, 'httponly' => true, 'secure' => $isHttps, 'samesite' => 'Lax']);
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

require __DIR__ . '/includes/helpers.php';
function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function csrf_field() { echo '<input type="hidden" name="csrf" value="'.h($_SESSION['csrf']).'">'; }
function verify_csrf() {
    if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) { http_response_code(403); exit('หน้าหมดอายุหรือแบบฟอร์มไม่ถูกต้อง <a href="login.php">กลับไปลองใหม่</a>'); }
}
// First 16 hex digits of the stored password hash's SHA-256 (Google accounts have no hash). It changes when the password changes.
function pw_fingerprint($conn, $userId) {
    $stmt = mysqli_prepare($conn, "SELECT LEFT(SHA2(IFNULL(password_hash, ''), 256), 16) FROM lib_users WHERE user_id = ?");
    $uid = (int)$userId;
    mysqli_stmt_bind_param($stmt, 'i', $uid);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $fp);
    $fp = mysqli_stmt_fetch($stmt) ? (string)$fp : '';
    mysqli_stmt_close($stmt);
    return $fp;
}
function require_login() {
    global $conn;
    if (empty($_SESSION['user_id'])) { header('Location: login.php'); exit; }
    if (isset($conn)) { // a session opened before the password was changed (or the user was deleted) is no longer valid
        $fp = pw_fingerprint($conn, $_SESSION['user_id']);
        if (!isset($_SESSION['pwv'])) $_SESSION['pwv'] = $fp;
        elseif ($fp === '' || !hash_equals($_SESSION['pwv'], $fp)) { $_SESSION = []; session_regenerate_id(true); header('Location: login.php'); exit; }
    }
}
function is_staff() {
    $role = $_SESSION['role'] ?? '';
    return $role === 'staff' || $role === 'admin';
}
// Role is checked on the server for every staff page (hiding the menu is not security).
// The role is re-read from the DB each time, so demoting a user takes effect at once (needs $conn from config/db.php).
function role_now() {
    global $conn;
    $stmt = mysqli_prepare($conn, 'SELECT role FROM lib_users WHERE user_id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $_SESSION['user_id']);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $role);
    $found = mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
    $_SESSION['role'] = $found ? $role : ''; // keep the menu in sync; a deleted user loses all rights
}
function require_staff() {
    require_login();
    role_now();
    if (!is_staff()) { http_response_code(403); exit('หน้านี้สำหรับเจ้าหน้าที่เท่านั้น'); }
}

// Admin-only pages (change roles). Like require_staff(), the role is read from the DB each time.
function require_admin() {
    global $conn;
    require_login();
    $stmt = mysqli_prepare($conn, 'SELECT role FROM lib_users WHERE user_id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $_SESSION['user_id']);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $role);
    $found = mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
    $_SESSION['role'] = $found ? $role : '';
    if ($role !== 'admin' || !$found) { http_response_code(403); exit('หน้านี้สำหรับผู้ดูแลระบบเท่านั้น'); }
}

// ---- Sign in with Google: which account does a verified Google identity belong to? ----
// Returns [user_id, full_name, role], or false when this email already belongs to a password account.
// Why false: Google proves the email is real, but our own sign-up never verified emails. If we merged by email, someone who
// registered a victim's email with a password first would keep access after the victim later used Google ("pre-hijacking").
function google_account($conn, $sub, $email, $name) {
    // 1) Already linked: this Google id signed in before
    $stmt = mysqli_prepare($conn, 'SELECT user_id, full_name, role FROM lib_users WHERE google_sub = ? LIMIT 1');
    mysqli_stmt_bind_param($stmt, 's', $sub);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $id, $fullName, $role);
    $found = mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
    if ($found) return [$id, $fullName, $role];
    // 2) The email is taken by a normal (password) account: refuse, the owner must log in with the password
    $stmt = mysqli_prepare($conn, 'SELECT user_id FROM lib_users WHERE email = ? LIMIT 1');
    mysqli_stmt_bind_param($stmt, 's', $email);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    $taken = mysqli_stmt_num_rows($stmt) > 0;
    mysqli_stmt_close($stmt);
    if ($taken) return false;
    // 3) New person: create a member. The password is random and never shown, so password login is impossible for this account.
    $name = mb_substr(trim(preg_replace('/[\x00-\x1f\x7f]/', '', (string)$name)), 0, 100);
    if ($name === '') $name = mb_substr(strstr($email, '@', true), 0, 100);
    $hash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
    $stmt = mysqli_prepare($conn, "INSERT INTO lib_users (email, password_hash, full_name, role, google_sub) VALUES (?, ?, ?, 'member', ?)");
    mysqli_stmt_bind_param($stmt, 'ssss', $email, $hash, $name, $sub);
    $ok = mysqli_stmt_execute($stmt);
    $newId = mysqli_stmt_insert_id($stmt);
    mysqli_stmt_close($stmt);
    if (!$ok) return false; // two requests at once: the UNIQUE keys stop the second one
    return [$newId, $name, 'member'];
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

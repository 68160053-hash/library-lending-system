<?php
require 'auth.php'; require 'config/db.php';
// Step 2: Google sends the visitor back here with ?code=...&state=... We check everything before logging anyone in.
function fail($why) { header('Location: login.php?google_error=' . $why); exit; }
if (!$googleReady) fail('off');
if (!empty($_SESSION['user_id'])) { header('Location: index.php'); exit; }

// 1) state must match the one we made in google-login.php, and it works only once
$state = (string)($_GET['state'] ?? '');
$expected = (string)($_SESSION['g_state'] ?? '');
$nonce = (string)($_SESSION['g_nonce'] ?? '');
unset($_SESSION['g_state'], $_SESSION['g_nonce']);
if ($expected === '' || !hash_equals($expected, $state)) fail('state');
if (isset($_GET['error'])) fail('denied');                 // the person pressed "cancel" at Google
$code = (string)($_GET['code'] ?? '');
if ($code === '' || strlen($code) > 2048) fail('code');

// 2) swap the one-time code for an ID token. This call is made by OUR server with our secret, never by the browser.
// (ยังไม่ได้เรียน) curl and json_decode: PHP's built-in way to call another website and read its JSON answer.
function call_google($url, $post) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_CONNECTTIMEOUT => 5]);
    if ($post !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post)); }
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    $data = $body === false ? null : json_decode($body, true);
    return ($status === 200 && is_array($data)) ? $data : null;
}
$token = call_google('https://oauth2.googleapis.com/token', [
    'code' => $code, 'client_id' => $googleId, 'client_secret' => $googleSecret,
    'redirect_uri' => $googleRedirect, 'grant_type' => 'authorization_code',
]);
if (!$token || empty($token['id_token'])) fail('token');

// 3) ask Google to check the ID token for us (signature, expiry) and read who it is about
$info = call_google('https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($token['id_token']), null);
if (!$info) fail('token');

// 4) our own checks: the token is for OUR app, from Google, not expired, same request, and the email is verified
$issuerOk = in_array($info['iss'] ?? '', ['https://accounts.google.com', 'accounts.google.com'], true);
$email = strtolower(trim((string)($info['email'] ?? '')));
$sub = (string)($info['sub'] ?? '');
if (!$issuerOk || ($info['aud'] ?? '') !== $googleId || (int)($info['exp'] ?? 0) < time()) fail('token');
if (!hash_equals($nonce, (string)($info['nonce'] ?? '')) || $nonce === '') fail('token');
if (($info['email_verified'] ?? '') !== 'true' && ($info['email_verified'] ?? false) !== true) fail('email');
if ($sub === '' || strlen($sub) > 40 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 120) fail('email');

// 5) find or create the account (see google_account() in auth.php for the rules)
$account = google_account($conn, $sub, $email, (string)($info['name'] ?? ''));
if ($account === false) fail('taken');

// 6) log in, exactly like the normal login page: new session id, fresh CSRF token, remember who it is
session_regenerate_id(true);
$_SESSION['csrf'] = bin2hex(random_bytes(32));
$_SESSION['login_at'] = $_SESSION['last_seen'] = time();
$_SESSION['user_id'] = $account[0]; $_SESSION['full_name'] = $account[1]; $_SESSION['role'] = $account[2];
header('Location: ' . (($account[2] === 'staff' || $account[2] === 'admin') ? 'rentals.php' : 'index.php')); exit;

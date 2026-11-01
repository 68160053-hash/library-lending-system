<?php
require 'auth.php'; require 'config/db.php';
// Step 1 of "Sign in with Google": send the visitor to Google. Nothing is changed here, so GET is fine.
if (!empty($_SESSION['user_id'])) { header('Location: index.php'); exit; }
if (!$googleReady) { header('Location: login.php?google_error=off'); exit; }

// "state" = a random secret that only this browser knows. Google sends it back, and the callback page refuses
// anything that does not match. This stops a stranger from pushing their own Google login into your browser (login CSRF).
$_SESSION['g_state'] = bin2hex(random_bytes(16));
// "nonce" = ties the answer from Google to this one request (checked again in the callback).
$_SESSION['g_nonce'] = bin2hex(random_bytes(16));

$query = http_build_query([
    'client_id' => $googleId,
    'redirect_uri' => $googleRedirect,
    'response_type' => 'code',
    'scope' => 'openid email profile',
    'state' => $_SESSION['g_state'],
    'nonce' => $_SESSION['g_nonce'],
    'prompt' => 'select_account',
]);
header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . $query);
exit;

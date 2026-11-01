<?php
// Credentials come from (1) environment variables, (2) the git-ignored secret/config.php
// on the server, or (3) local XAMPP defaults. Never hard-code real passwords here.
ini_set('display_errors', '0'); // never show file paths to visitors; errors go to the log
mysqli_report(MYSQLI_REPORT_OFF); // PHP 8.1+ throws by default; the checks below expect false
$secret = [];
$secretFile = __DIR__ . '/../secret/config.php';
if (is_file($secretFile)) { $secret = require $secretFile; }
$host = getenv('DB_HOST') ?: ($secret['host'] ?? 'localhost');
$user = getenv('DB_USER') ?: ($secret['user'] ?? 'root');
$pass = getenv('DB_PASS') ?: ($secret['pass'] ?? '');
$dbname = getenv('DB_NAME') ?: ($secret['name'] ?? 'library_db');
$conn = mysqli_connect($host, $user, $pass, $dbname);
if (!$conn) { error_log(mysqli_connect_error()); http_response_code(500); exit('Database unavailable'); }
// Google sign-in settings (optional). Without them the "Sign in with Google" button is simply hidden.
$googleId = getenv('GOOGLE_CLIENT_ID') ?: ($secret['google_client_id'] ?? '');
$googleSecret = getenv('GOOGLE_CLIENT_SECRET') ?: ($secret['google_client_secret'] ?? '');
$googleRedirect = getenv('GOOGLE_REDIRECT_URI') ?: ($secret['google_redirect_uri'] ?? '');
$googleReady = $googleId !== '' && $googleSecret !== '' && $googleRedirect !== '' && function_exists('curl_init');
// Where customers send the money (shown on the payment box). Set shop_* keys in secret/config.php; these are placeholders.
$shop = [
    'bank' => getenv('SHOP_BANK') ?: ($secret['shop_bank'] ?? ''),
    'account_no' => getenv('SHOP_ACCOUNT_NO') ?: ($secret['shop_account_no'] ?? ''),   // empty = no bank box, the shop tells the customer how to pay
    'account_name' => getenv('SHOP_ACCOUNT_NAME') ?: ($secret['shop_account_name'] ?? ''),
    'promptpay' => getenv('SHOP_PROMPTPAY') ?: ($secret['shop_promptpay'] ?? ''),
];
mysqli_set_charset($conn, 'utf8mb4');
mysqli_query($conn, "SET time_zone = '+07:00'"); // CURDATE() must be Thai time even if the server runs UTC

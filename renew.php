<?php
require 'auth.php'; require 'config/db.php';
// Renew changes data, so POST only (a link or the address bar must not renew anything).
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('<a href="my-loans.php">กลับไปการยืมของฉัน</a>');
}
require_login();
verify_csrf();
$loanId = filter_var($_POST['loan_id'] ?? null, FILTER_VALIDATE_INT);
if ($loanId === false || $loanId < 1) { http_response_code(400); exit('Invalid loan ID'); }
$userId = (int)$_SESSION['user_id'];

// ONE UPDATE checks both the rules and the owner. "user_id = ?" matters: without it a member
// could change loan_id in the form and renew someone else's loan.
$sql = 'UPDATE lib_loans SET due_date = DATE_ADD(due_date, INTERVAL 7 DAY), renew_count = renew_count + 1
        WHERE loan_id = ? AND user_id = ? AND returned_at IS NULL AND renew_count = 0 AND due_date >= CURDATE()';
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, 'ii', $loanId, $userId);
mysqli_stmt_execute($stmt);
$changed = mysqli_stmt_affected_rows($stmt); // 1 = renewed, 0 = a rule failed or not the owner
mysqli_stmt_close($stmt);

header('Location: my-loans.php?' . ($changed === 1 ? 'renewed=1' : 'renew_error=1')); exit;

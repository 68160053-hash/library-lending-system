<?php
require 'auth.php'; require 'config/db.php';
// Staff moves a rental to the next step: ship (pending -> shipped), receive (shipped -> returned), lost (shipped -> lost).
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('<a href="rentals.php">กลับ</a>'); }
require_staff();
verify_csrf();
$loanId = filter_var($_POST['loan_id'] ?? null, FILTER_VALIDATE_INT);
$action = (string)($_POST['action'] ?? '');
if ($loanId === false || $loanId < 1 || !in_array($action, ['ship', 'receive', 'lost'], true)) { http_response_code(400); exit('Invalid request'); }

// Each UPDATE has "status = <the step before>" in WHERE: pressing the button twice, or on a rental in the wrong step, changes nothing.
mysqli_begin_transaction($conn);
if ($action === 'ship') {
    $sql = "UPDATE lib_loans SET status = 'shipped', shipped_at = CURDATE(), due_date = CURDATE() + INTERVAL 7 DAY
            WHERE loan_id = ? AND status = 'pending'";
} elseif ($action === 'receive') {
    $sql = "UPDATE lib_loans SET status = 'returned', returned_at = CURDATE() WHERE loan_id = ? AND status = 'shipped'";
} else {
    $sql = "UPDATE lib_loans SET status = 'lost', returned_at = CURDATE() WHERE loan_id = ? AND status = 'shipped'";
}
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, 'i', $loanId);
mysqli_stmt_execute($stmt);
$changed = mysqli_stmt_affected_rows($stmt);
mysqli_stmt_close($stmt);

// A returned book goes back on the shelf; a lost book leaves the shelf for good (total copies - 1).
if ($changed === 1 && $action !== 'ship') {
    $col = $action === 'receive' ? 'copies_available = copies_available + 1' : 'copies_total = copies_total - 1';
    $sql = "UPDATE lib_books SET $col WHERE book_id = (SELECT book_id FROM lib_loans WHERE loan_id = ?)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $loanId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}
mysqli_commit($conn);
header('Location: rentals.php?' . ($changed === 1 ? 'done=1' : 'error=1')); exit;

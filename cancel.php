<?php
require 'auth.php'; require 'config/db.php';
// A member can cancel their OWN rental while it is still waiting to be shipped (status pending). The copy goes back on the shelf.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('<a href="my-loans.php">กลับไปการเช่าของฉัน</a>'); }
require_login();
verify_csrf();
$loanId = filter_var($_POST['loan_id'] ?? null, FILTER_VALIDATE_INT);
if ($loanId === false || $loanId < 1) { http_response_code(400); exit('Invalid loan ID'); }
$userId = (int)$_SESSION['user_id'];

mysqli_begin_transaction($conn);
// Step 1: find the book of THIS member's pending rental and lock the row (user_id in WHERE = only the owner can cancel)
$stmt = mysqli_prepare($conn, "SELECT book_id FROM lib_loans WHERE loan_id = ? AND user_id = ? AND status = 'pending' FOR UPDATE");
mysqli_stmt_bind_param($stmt, 'ii', $loanId, $userId);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $bookId);
$found = mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);
if (!$found) { mysqli_rollback($conn); header('Location: my-loans.php?cancel_error=1'); exit; }

// Step 2: delete the rental and give the copy back
$stmt = mysqli_prepare($conn, 'DELETE FROM lib_loans WHERE loan_id = ?');
mysqli_stmt_bind_param($stmt, 'i', $loanId);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);
$stmt = mysqli_prepare($conn, 'UPDATE lib_books SET copies_available = copies_available + 1 WHERE book_id = ?');
mysqli_stmt_bind_param($stmt, 'i', $bookId);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);
mysqli_commit($conn);
header('Location: my-loans.php?cancelled=1'); exit;

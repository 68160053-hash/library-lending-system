<?php
require 'auth.php'; require 'config/db.php';
// Renting changes data, so POST only. A member asks to rent one book and gives a delivery address.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('<a href="index.php">กลับหน้าร้าน</a>'); }
require_login();
verify_csrf();
$bookId = filter_var($_POST['book_id'] ?? null, FILTER_VALIDATE_INT);
$address = trim((string)($_POST['ship_address'] ?? ''));
if ($bookId === false || $bookId < 1) { http_response_code(400); exit('Invalid book ID'); }
if (mb_strlen($address) < 10 || mb_strlen($address) > 255) {
    header('Location: index.php?rent_error=address'); exit; // address too short/long: ask again
}
$userId = (int)$_SESSION['user_id'];

// Step 1: take one copy out of stock. ONE UPDATE both checks and changes the number, so two people
// clicking at the same moment cannot both get the last copy (the second one sees affected_rows = 0).
mysqli_begin_transaction($conn);
$stmt = mysqli_prepare($conn, 'UPDATE lib_books SET copies_available = copies_available - 1 WHERE book_id = ? AND copies_available > 0');
mysqli_stmt_bind_param($stmt, 'i', $bookId);
mysqli_stmt_execute($stmt);
$got = mysqli_stmt_affected_rows($stmt);
mysqli_stmt_close($stmt);

if ($got !== 1) {
    mysqli_rollback($conn);
    header('Location: index.php?rent_error=soldout'); exit;
}

// Step 2: write the rental. Rate and deposit are copied from the book now, so a later price change does not affect it.
// due_date is a placeholder (7 days); staff pressing "ส่งพัสดุ" restarts the 7 days from the real shipping date.
$sql = "INSERT INTO lib_loans (user_id, book_id, borrowed_at, due_date, status, daily_rate, deposit, ship_address)
        SELECT ?, book_id, CURDATE(), CURDATE() + INTERVAL 7 DAY, 'pending', rent_per_day, deposit, ? FROM lib_books WHERE book_id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, 'isi', $userId, $address, $bookId);
$ok = mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) === 1;
mysqli_stmt_close($stmt);

if (!$ok) { mysqli_rollback($conn); header('Location: index.php?rent_error=fail'); exit; }
mysqli_commit($conn);
header('Location: my-loans.php?rented=1'); exit;

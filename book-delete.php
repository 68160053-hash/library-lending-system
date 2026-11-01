<?php
require 'auth.php'; require 'config/db.php';
// Deleting changes data, so POST only, staff only, with a CSRF token.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('<a href="books.php">กลับ</a>'); }
require_staff();
verify_csrf();
$bookId = filter_var($_POST['book_id'] ?? null, FILTER_VALIDATE_INT);
if ($bookId === false || $bookId < 1) { http_response_code(400); exit('Invalid book ID'); }

// The database refuses (foreign key RESTRICT) when the book already has rental history, so history is never lost.
$stmt = mysqli_prepare($conn, 'DELETE FROM lib_books WHERE book_id = ?');
mysqli_stmt_bind_param($stmt, 'i', $bookId);
$ok = mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);
header('Location: books.php?' . ($ok ? 'deleted=1' : 'delete_error=1')); exit;

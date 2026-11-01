<?php
require 'auth.php'; require 'config/db.php';
// Save (or edit) the member's review of one book. Only someone who rented the book and sent it back may review it.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: index.php'); exit; }
verify_csrf();
require_login();
$uid = (int)$_SESSION['user_id'];
if (($_POST['action'] ?? '') === 'delete') { // staff take a review down (the book page shows the button to staff only)
    require_staff();
    $rid = filter_var($_POST['review_id'] ?? null, FILTER_VALIDATE_INT);
    $stmt = mysqli_prepare($conn, 'DELETE FROM lib_reviews WHERE review_id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $rid);
    mysqli_stmt_execute($stmt);
    $gone = $rid !== false && mysqli_stmt_affected_rows($stmt) === 1;
    mysqli_stmt_close($stmt);
    flash($gone ? 'ok' : 'err', $gone ? 'ลบรีวิวแล้ว' : 'ไม่พบรีวิวนี้');
    $b = filter_var($_POST['book_id'] ?? null, FILTER_VALIDATE_INT);
    header('Location: ' . ($b !== false && $b > 0 ? 'book.php?id=' . $b . '#reviews' : 'index.php')); exit;
}
$bookId = filter_var($_POST['book_id'] ?? null, FILTER_VALIDATE_INT);
if ($bookId === false || $bookId < 1) { http_response_code(404); exit('ไม่พบหนังสือเล่มนี้ <a href="index.php">กลับหน้าร้าน</a>'); }
$go = 'book.php?id=' . $bookId . '#reviews';

$rating = filter_var(is_string($_POST['rating'] ?? null) ? $_POST['rating'] : null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 5]]);
$body = is_string($_POST['body'] ?? null) ? $_POST['body'] : '';
$body = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', str_replace("\r\n", "\n", $body)));   // keep line breaks, drop other control characters
if ($rating === false || $rating === null) { flash('err', 'กรุณาให้คะแนน 1 ถึง 5 ดาว'); header('Location: ' . $go); exit; }
if (mb_strlen($body) > 400) { flash('err', 'ข้อความรีวิวยาวได้ไม่เกิน 400 ตัวอักษร'); header('Location: ' . $go); exit; }

// Eligibility is checked here, not only by hiding the form
$stmt = mysqli_prepare($conn, "SELECT 1 FROM lib_loans WHERE user_id = ? AND book_id = ? AND status = 'returned' LIMIT 1");
mysqli_stmt_bind_param($stmt, 'ii', $uid, $bookId);
mysqli_stmt_execute($stmt);
mysqli_stmt_store_result($stmt);
$eligible = mysqli_stmt_num_rows($stmt) > 0;
mysqli_stmt_close($stmt);
if (!$eligible) { flash('err', 'รีวิวได้เมื่อเคยเช่าและส่งคืนหนังสือเล่มนี้แล้ว'); header('Location: ' . $go); exit; }

$stmt = mysqli_prepare($conn, 'INSERT INTO lib_reviews (book_id, user_id, rating, body) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE rating = VALUES(rating), body = VALUES(body), created_at = NOW()');
mysqli_stmt_bind_param($stmt, 'iiis', $bookId, $uid, $rating, $body);
$okSave = mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);
flash($okSave ? 'ok' : 'err', $okSave ? 'บันทึกรีวิวแล้ว ขอบคุณที่ช่วยกันบอกต่อ' : 'บันทึกรีวิวไม่สำเร็จ ลองใหม่อีกครั้ง');
header('Location: ' . $go); exit;

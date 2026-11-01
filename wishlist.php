<?php
require 'auth.php'; require 'config/db.php'; require_once 'includes/discover.php';
// Toggle one book on the member's "want to read" shelf. POST only. A fetch from discover.js gets JSON, a plain form gets a redirect.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: shelf.php'); exit; }
verify_csrf();
$ajax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';
if (empty($_SESSION['user_id'])) {
    if ($ajax) { http_response_code(401); header('Content-Type: application/json'); echo '{"login":true}'; exit; }
    require_login();
}
$uid = (int)$_SESSION['user_id'];
$bookId = filter_var($_POST['book_id'] ?? null, FILTER_VALIDATE_INT);
$back = (string)($_POST['back'] ?? '');
$go = preg_match(BACK_PATTERN, $back) ? $back : 'index.php';

$wished = null;
if ($bookId !== false && $bookId > 0) {
    // Remove first; if nothing was there, add. The book must exist (checked by the foreign key as well).
    $stmt = mysqli_prepare($conn, 'DELETE FROM lib_wishlist WHERE user_id = ? AND book_id = ?');
    mysqli_stmt_bind_param($stmt, 'ii', $uid, $bookId);
    mysqli_stmt_execute($stmt);
    $removed = mysqli_stmt_affected_rows($stmt) > 0;
    mysqli_stmt_close($stmt);
    if ($removed) $wished = false;
    else {
        $stmt = mysqli_prepare($conn, 'INSERT IGNORE INTO lib_wishlist (user_id, book_id) SELECT ?, book_id FROM lib_books WHERE book_id = ?');
        mysqli_stmt_bind_param($stmt, 'ii', $uid, $bookId);
        mysqli_stmt_execute($stmt);
        $added = mysqli_stmt_affected_rows($stmt) > 0;
        mysqli_stmt_close($stmt);
        if ($added) $wished = true;
    }
}
if ($ajax) {
    header('Content-Type: application/json'); header('Cache-Control: no-store');
    if ($wished === null) { http_response_code(404); echo '{"error":"not_found"}'; exit; }
    echo json_encode(['wished' => $wished]); exit;
}
if ($wished === null) flash('err', 'ไม่พบหนังสือเล่มนี้');
else flash('ok', $wished ? 'บันทึกไว้ในรายการอยากอ่านแล้ว' : 'เอาออกจากรายการอยากอ่านแล้ว');
header('Location: ' . $go); exit;

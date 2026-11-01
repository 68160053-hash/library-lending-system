<?php
require 'auth.php'; require 'config/db.php';
// Type-ahead for the search boxes: ?q= (2-60 characters) -> up to 6 matching books as JSON. Public, read-only.
session_write_close();   // no session lock for a request that only reads
header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store');
$q = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
$out = [];
$len = mb_strlen($q);
if ($len >= 2 && $len <= 60) {
    $like = '%' . addcslashes($q, '%_\\') . '%';
    $pre = addcslashes($q, '%_\\') . '%';
    $stmt = mysqli_prepare($conn, 'SELECT book_id, title, author, isbn FROM lib_books WHERE title LIKE ? OR author LIKE ? OR isbn LIKE ? ORDER BY title LIMIT 6');
    mysqli_stmt_bind_param($stmt, 'sss', $like, $like, $pre);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $id, $title, $author, $isbn);
    while (mysqli_stmt_fetch($stmt)) $out[] = ['id' => (int)$id, 'title' => $title, 'author' => $author, 'isbn' => $isbn];
    mysqli_stmt_close($stmt);
}
echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

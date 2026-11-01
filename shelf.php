<?php
require 'auth.php'; require 'config/db.php'; require_once 'includes/discover.php';
// My shelf: books I want to read, and books I have rented before. Always the signed-in member's own (no user id in the URL).
require_login();
$uid = (int)$_SESSION['user_id'];
$tab = (($_GET['tab'] ?? '') === 'rented') ? 'rented' : 'wish';

$wished = wished_ids($conn, $uid);
$ids = [];
if ($tab === 'wish') {
    $stmt = mysqli_prepare($conn, 'SELECT book_id FROM lib_wishlist WHERE user_id = ? ORDER BY added_at DESC');
} else {
    $stmt = mysqli_prepare($conn, "SELECT book_id FROM lib_loans WHERE user_id = ? AND status <> 'pending' GROUP BY book_id ORDER BY MAX(borrowed_at) DESC, book_id DESC");
}
mysqli_stmt_bind_param($stmt, 'i', $uid);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $bid);
while (mysqli_stmt_fetch($stmt)) $ids[] = (int)$bid;
mysqli_stmt_close($stmt);
$books = books_by_ids($conn, array_slice($ids, 0, 60));

$pageTitle = 'ชั้นหนังสือของฉัน';
$pageCss = ['discover']; $pageJs = ['discover'];
require 'includes/header.php';
?>
<div class="page-head"><div><h1>ชั้นหนังสือของฉัน</h1><p>เก็บเล่มที่อยากอ่านไว้ และเช่าเล่มที่เคยอ่านซ้ำได้ในคลิกเดียว</p></div></div>
<nav class="tabs" aria-label="ชั้นหนังสือ">
  <a href="shelf.php"<?= $tab === 'wish' ? ' class="on" aria-current="page"' : '' ?>>อยากอ่าน (<?= count($wished) ?>)</a>
  <a href="shelf.php?tab=rented"<?= $tab === 'rented' ? ' class="on" aria-current="page"' : '' ?>>เคยเช่า</a>
</nav>
<?php if (!$books): ?>
<div class="card empty">
  <?php if ($tab === 'wish'): ?>
  <h3>ยังไม่มีเล่มที่อยากอ่าน</h3><p>กดรูปหัวใจบนปกหนังสือเพื่อเก็บไว้ที่นี่ แล้วค่อยกลับมาเช่าทีหลัง</p><a class="btn btn-primary" href="index.php">ไปเดินดูหนังสือ</a>
  <?php else: ?>
  <h3>ยังไม่เคยเช่าหนังสือ</h3><p>เล่มที่ร้านส่งให้คุณแล้วจะมาอยู่ที่นี่ พร้อมปุ่มเช่าอีกครั้ง</p><a class="btn btn-primary" href="index.php">เลือกเล่มแรกของคุณ</a>
  <?php endif; ?>
</div>
<?php else: ?>
<div class="grid-cards">
  <?php foreach ($books as $b) echo book_card($b, $wished, $tab === 'wish' ? ['remove' => true] : ['cart_label' => 'เช่าอีกครั้ง']); ?>
</div>
<?php endif; ?>
<?php require 'includes/footer.php'; ?>

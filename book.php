<?php
require 'auth.php'; require 'config/db.php';
// Book detail page: ?id=<book id>. Anyone can look; adding to the cart is a POST to cart.php.
$bookId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if ($bookId === false || $bookId < 1) { http_response_code(404); exit('ไม่พบหนังสือเล่มนี้ <a href="index.php">กลับหน้าร้าน</a>'); }

$stmt = mysqli_prepare($conn, 'SELECT b.title, b.author, b.isbn, b.description, b.copies_total, b.copies_available, b.rent_per_day, b.deposit, c.category_id, c.name
                               FROM lib_books b JOIN lib_categories c ON c.category_id = b.category_id WHERE b.book_id = ?');
mysqli_stmt_bind_param($stmt, 'i', $bookId);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $title, $author, $isbn, $desc, $total, $left, $rent, $deposit, $catId, $catName);
$found = mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);
if (!$found) { http_response_code(404); exit('ไม่พบหนังสือเล่มนี้ <a href="index.php">กลับหน้าร้าน</a>'); }

// Other books of the same category
$stmt = mysqli_prepare($conn, 'SELECT book_id, title, author, isbn, rent_per_day FROM lib_books WHERE category_id = ? AND book_id <> ? ORDER BY copies_available > 0 DESC, book_id DESC LIMIT 4');
mysqli_stmt_bind_param($stmt, 'ii', $catId, $bookId);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $rId, $rTitle, $rAuthor, $rIsbn, $rRent);
$related = [];
while (mysqli_stmt_fetch($stmt)) $related[] = [$rId, $rTitle, $rAuthor, $rIsbn, $rRent];
mysqli_stmt_close($stmt);

$inCart = in_array((int)$bookId, cart_ids(), true);
$pageTitle = $title;
require 'includes/header.php';
?>
<nav class="crumbs" aria-label="เส้นทาง"><a href="index.php">หนังสือ</a> / <a href="index.php?category=<?= (int)$catId ?>"><?= h($catName) ?></a> / <?= h($title) ?></nav>
<div class="detail">
  <div><?= cover_html($isbn, $title, $bookId) ?></div>
  <div>
    <span class="badge b-info"><?= h($catName) ?></span>
    <h1 style="margin-top:.5rem"><?= h($title) ?></h1>
    <p class="muted">โดย <?= h($author) ?></p>
    <p><?php if ($left > 0): ?><span class="badge b-ok">ว่างให้เช่า <?= (int)$left ?> จาก <?= (int)$total ?> เล่ม</span><?php else: ?><span class="badge b-bad">ถูกเช่าหมดแล้ว</span><?php endif; ?></p>
    <p class="desc"><?= $desc ? h($desc) : '<span class="muted">ยังไม่มีเรื่องย่อของหนังสือเล่มนี้</span>' ?></p>
    <dl class="facts"><dt>ISBN</dt><dd><?= h($isbn) ?></dd><dt>ผู้แต่ง</dt><dd><?= h($author) ?></dd><dt>หมวด</dt><dd><?= h($catName) ?></dd></dl>
    <div class="card buybox">
      <h3>เลือกระยะเวลาเช่า</h3>
      <div class="plans"><?php foreach ([7, 14, 30] as $d): ?>
        <div class="plan<?= $d === 14 ? ' pop' : '' ?>"><span class="days-label"><?= (int)$d ?> วัน</span><b>฿<?= h(money($rent * $d + $deposit)) ?></b><small>ค่าเช่า ฿<?= h(money($rent * $d)) ?> + มัดจำ ฿<?= h(money($deposit)) ?></small></div>
      <?php endforeach; ?></div>
      <p class="muted small">มัดจำคืนให้เมื่อร้านได้รับหนังสือ ค่าเช่า ฿<?= h(money($rent)) ?> ต่อวัน เกินกำหนดคิดวันละเท่าราคาเช่า หักจากมัดจำ</p>
      <?php if ($left > 0 && !$inCart): ?>
      <form action="cart.php" method="post"><?php csrf_field(); ?><input type="hidden" name="action" value="add"><input type="hidden" name="book_id" value="<?= (int)$bookId ?>"><input type="hidden" name="then" value="cart">
        <button type="submit" class="btn btn-accent">ใส่ตะกร้าและไปชำระเงิน</button></form>
      <?php elseif ($inCart): ?>
      <a class="btn btn-primary" href="cart.php">อยู่ในตะกร้าแล้ว ไปที่ตะกร้า</a>
      <?php else: ?><button class="btn btn-outline" type="button" disabled>ถูกเช่าหมดแล้ว</button><?php endif; ?>
    </div>
  </div>
</div>
<?php if ($related): ?>
<h2 style="margin-top:2rem">หนังสือหมวดเดียวกัน</h2>
<div class="grid-cards">
  <?php foreach ($related as $r): ?>
  <article class="book-card">
    <a class="cover-link" href="book.php?id=<?= (int)$r[0] ?>" tabindex="-1" aria-hidden="true"><?= cover_html($r[3], $r[1], $r[0], false) ?></a>
    <div class="body"><h3><a href="book.php?id=<?= (int)$r[0] ?>"><?= h($r[1]) ?></a></h3><span class="author"><?= h($r[2]) ?></span><span class="price">฿<?= h(rtrim(rtrim(money($r[4]), '0'), '.')) ?><small>/วัน</small></span></div>
  </article>
  <?php endforeach; ?>
</div>
<?php endif; ?>
<?php require 'includes/footer.php'; ?>

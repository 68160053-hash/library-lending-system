<?php
require 'auth.php'; require 'config/db.php';
require_staff(); // server-side role check: members get 403
$q = mb_substr(trim(get_str('q')), 0, 100);
$like = '%' . addcslashes($q, '%_\\') . '%';

$stmt = mysqli_prepare($conn, 'SELECT b.book_id, b.title, b.author, c.name, b.category_id, b.copies_total, b.copies_available, b.rent_per_day, b.deposit, b.isbn, b.featured,
        (SELECT AVG(r.rating) FROM lib_reviews r WHERE r.book_id = b.book_id), (SELECT COUNT(*) FROM lib_reviews r WHERE r.book_id = b.book_id)
        FROM lib_books b JOIN lib_categories c ON c.category_id = b.category_id
        WHERE CHAR_LENGTH(?) = 0 OR b.title LIKE ? OR b.author LIKE ? OR b.isbn LIKE ?
        ORDER BY b.book_id DESC LIMIT 300');
mysqli_stmt_bind_param($stmt, 'ssss', $q, $like, $like, $like);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $bookId, $title, $author, $cat, $catId, $total, $avail, $rent, $deposit, $isbn, $featured, $avg, $reviews);
$rows = [];
while (mysqli_stmt_fetch($stmt)) $rows[] = compact('bookId', 'title', 'author', 'cat', 'catId', 'total', 'avail', 'rent', 'deposit', 'isbn', 'featured', 'avg', 'reviews');
mysqli_stmt_close($stmt);

$pageTitle = 'จัดการหนังสือ'; $pageCss = ['staff'];
require 'includes/header.php';
?>
<div class="page-head"><div><h1>จัดการหนังสือ</h1><p>เพิ่ม แก้ไข ตั้งราคาเช่าและค่ามัดจำ</p></div>
  <div class="head-actions">
    <form action="books.php" method="get" class="search"><label class="sr-only" for="bq">ค้นหา</label><?= icon('search', 18) ?><input type="text" id="bq" name="q" value="<?= h($q) ?>" maxlength="100" placeholder="ชื่อ ผู้แต่ง หรือ ISBN"><button type="submit">ค้นหา</button></form>
    <a class="btn btn-primary" href="book-form.php"><?= icon('plus', 18) ?>เพิ่มหนังสือ</a></div></div>
<div class="card"><div class="tbl-wrap"><table class="tbl stack">
  <caption class="sr-only">หนังสือทั้งหมด</caption>
  <thead><tr><th>หนังสือ</th><th>โซน</th><th class="num">ว่าง/ทั้งหมด</th><th class="num">เช่า/วัน</th><th class="num">มัดจำ</th><th>รีวิว</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $b): ?>
  <tr class="<?= h(zone_class($b['catId'])) ?>">
    <td class="full"><div class="bookcell"><span class="thumb"><?= cover_html($b['isbn'], $b['title'], $b['bookId'], false) ?></span><span><a href="book.php?id=<?= (int)$b['bookId'] ?>"><strong><?= h($b['title']) ?></strong></a><?= $b['featured'] ? ' <span class="badge b-info" title="แสดงในแถวเจ้าหน้าที่คัดสรรหน้าแรก">' . icon('star-fill', 12) . 'คัดสรร</span>' : '' ?><br><span class="muted small"><?= h($b['author']) ?> &middot; <?= h($b['isbn']) ?></span></span></div></td>
    <td data-label="โซน"><span class="zchip"><?= h(zone_letter($b['catId'])) ?></span> <?= h($b['cat']) ?></td>
    <td class="num" data-label="ว่าง/ทั้งหมด"><span class="badge <?= $b['avail'] < 1 ? 'b-bad' : ($b['avail'] <= 1 ? 'b-warn' : 'b-ok') ?>"><?= (int)$b['avail'] ?>/<?= (int)$b['total'] ?></span></td>
    <td class="num" data-label="เช่า/วัน">฿<?= h(money($b['rent'])) ?></td><td class="num" data-label="มัดจำ">฿<?= h(money($b['deposit'])) ?></td>
    <td data-label="รีวิว"><?= (int)$b['reviews'] > 0 ? icon('star-fill', 14) . ' ' . h(number_format((float)$b['avg'], 1)) . ' <span class="muted small">(' . (int)$b['reviews'] . ')</span>' : '<span class="muted small">ยังไม่มี</span>' ?></td>
    <td class="full actions"><a class="btn btn-outline btn-sm" href="book-form.php?id=<?= (int)$b['bookId'] ?>"><?= icon('edit', 16) ?>แก้ไข</a>
      <form action="book-delete.php" method="post" class="inline-form"><?php csrf_field(); ?><input type="hidden" name="book_id" value="<?= (int)$b['bookId'] ?>"><button type="submit" class="btn btn-danger btn-sm" data-confirm="ลบหนังสือเล่มนี้? (ลบไม่ได้ถ้ามีประวัติการเช่า)"><?= icon('trash', 16) ?>ลบ</button></form></td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="7" class="center empty">ไม่พบหนังสือ</td></tr><?php endif; ?>
  </tbody>
</table></div></div>
<?php require 'includes/footer.php'; ?>

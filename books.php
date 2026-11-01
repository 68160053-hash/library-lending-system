<?php
require 'auth.php'; require 'config/db.php';
require_staff(); // server-side role check: members get 403
$q = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 100);
$like = '%' . addcslashes($q, '%_\\') . '%';

$stmt = mysqli_prepare($conn, 'SELECT b.book_id, b.title, b.author, c.name, b.copies_total, b.copies_available, b.rent_per_day, b.deposit, b.isbn
        FROM lib_books b JOIN lib_categories c ON c.category_id = b.category_id
        WHERE CHAR_LENGTH(?) = 0 OR b.title LIKE ? OR b.author LIKE ? OR b.isbn LIKE ?
        ORDER BY b.book_id DESC LIMIT 300');
mysqli_stmt_bind_param($stmt, 'ssss', $q, $like, $like, $like);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $bookId, $title, $author, $cat, $total, $avail, $rent, $deposit, $isbn);
$rows = [];
while (mysqli_stmt_fetch($stmt)) $rows[] = compact('bookId', 'title', 'author', 'cat', 'total', 'avail', 'rent', 'deposit', 'isbn');
mysqli_stmt_close($stmt);

$pageTitle = 'จัดการหนังสือ';
require 'includes/header.php';
?>
<div class="page-head"><div><h1>จัดการหนังสือ</h1><p>เพิ่ม แก้ไข ตั้งราคาเช่าและค่ามัดจำ</p></div>
  <div style="display:flex;gap:.6rem;flex-wrap:wrap">
    <form action="books.php" method="get" class="search"><label class="sr-only" for="bq">ค้นหา</label><input type="text" id="bq" name="q" value="<?= h($q) ?>" maxlength="100" placeholder="ชื่อ ผู้แต่ง หรือ ISBN"><button type="submit">ค้นหา</button></form>
    <a class="btn btn-primary" href="book-form.php">+ เพิ่มหนังสือ</a></div></div>
<div class="card"><div class="tbl-wrap"><table class="tbl">
  <caption class="sr-only">หนังสือทั้งหมด</caption>
  <thead><tr><th>หนังสือ</th><th>หมวด</th><th class="num">ว่าง/ทั้งหมด</th><th class="num">เช่า/วัน</th><th class="num">มัดจำ</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $b): ?>
  <tr>
    <td style="display:flex;gap:.7rem;align-items:center"><span style="width:38px;flex:none"><?= cover_html($b['isbn'], $b['title'], $b['bookId'], false) ?></span><span><a href="book.php?id=<?= (int)$b['bookId'] ?>"><strong><?= h($b['title']) ?></strong></a><br><span class="muted small"><?= h($b['author']) ?> &middot; <?= h($b['isbn']) ?></span></span></td>
    <td><?= h($b['cat']) ?></td>
    <td class="num"><span class="badge <?= $b['avail'] < 1 ? 'b-bad' : ($b['avail'] <= 1 ? 'b-warn' : 'b-ok') ?>"><?= (int)$b['avail'] ?>/<?= (int)$b['total'] ?></span></td>
    <td class="num">฿<?= h(money($b['rent'])) ?></td><td class="num">฿<?= h(money($b['deposit'])) ?></td>
    <td><a class="btn btn-outline btn-sm" href="book-form.php?id=<?= (int)$b['bookId'] ?>">แก้ไข</a>
      <form action="book-delete.php" method="post" class="inline-form"><?php csrf_field(); ?><input type="hidden" name="book_id" value="<?= (int)$b['bookId'] ?>"><button type="submit" class="btn btn-danger btn-sm" data-confirm="ลบหนังสือเล่มนี้? (ลบไม่ได้ถ้ามีประวัติการเช่า)">ลบ</button></form></td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="6" class="center muted" style="padding:2rem">ไม่พบหนังสือ</td></tr><?php endif; ?>
  </tbody>
</table></div></div>
<?php require 'includes/footer.php'; ?>

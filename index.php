<?php
require 'auth.php'; require 'config/db.php';
// Home = the catalogue. Filters come from the URL (?q=&category=&avail=1&sort=&page=), all read with prepared statements.
$q = is_string($_GET['q'] ?? '') ? mb_substr(trim($_GET['q'] ?? ''), 0, 100) : '';
$cat = max(0, (int)($_GET['category'] ?? 0));
$avail = (($_GET['avail'] ?? '') === '1') ? 1 : 0;
$sorts = ['new' => ['ใหม่ล่าสุด', 'b.book_id DESC'], 'price_asc' => ['ราคาเช่าต่ำ → สูง', 'b.rent_per_day ASC, b.title'],
          'price_desc' => ['ราคาเช่าสูง → ต่ำ', 'b.rent_per_day DESC, b.title'], 'title' => ['ชื่อ A → Z', 'b.title ASC']];
$sortIn = is_string($_GET['sort'] ?? '') ? ($_GET['sort'] ?? '') : '';   // ?sort[]=x must not reach the array lookup
$sort = isset($sorts[$sortIn]) ? $sortIn : 'new';   // only a key from the list above ever reaches the SQL text
$perPage = 12;
$page = max(1, (int)($_GET['page'] ?? 1));
$like = '%' . addcslashes($q, '%_\\') . '%';                           // "%" and "_" typed by the user stay plain characters

// Categories with the number of books in each (for the side menu)
$cats = [];
$res = mysqli_query($conn, 'SELECT c.category_id, c.name, COUNT(b.book_id) FROM lib_categories c LEFT JOIN lib_books b ON b.category_id = c.category_id GROUP BY c.category_id, c.name ORDER BY c.category_id');
while ($res && ($row = mysqli_fetch_row($res))) $cats[] = $row;

// How many books match (for the page numbers)
$where = '(CHAR_LENGTH(?) = 0 OR b.title LIKE ? OR b.author LIKE ?) AND (? = 0 OR b.category_id = ?) AND (? = 0 OR b.copies_available > 0)';
$stmt = mysqli_prepare($conn, "SELECT COUNT(*) FROM lib_books b WHERE $where");
mysqli_stmt_bind_param($stmt, 'sssiii', $q, $like, $like, $cat, $cat, $avail);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $total);
mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);
$pages = max(1, (int)ceil($total / $perPage));
if ($page > $pages) $page = $pages;
$offset = ($page - 1) * $perPage;

// One page of books
$sql = 'SELECT b.book_id, b.title, b.author, b.isbn, b.rent_per_day, b.copies_available, c.name
        FROM lib_books b JOIN lib_categories c ON c.category_id = b.category_id
        WHERE ' . $where . ' ORDER BY ' . $sorts[$sort][1] . ' LIMIT ? OFFSET ?';
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, 'sssiiiii', $q, $like, $like, $cat, $cat, $avail, $perPage, $offset);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $bookId, $title, $author, $isbn, $rent, $left, $catName);
$books = [];
while (mysqli_stmt_fetch($stmt)) $books[] = ['id' => $bookId, 'title' => $title, 'author' => $author, 'isbn' => $isbn, 'rent' => $rent, 'left' => $left, 'cat' => $catName];
mysqli_stmt_close($stmt);

// A link to this page with one filter changed
function url_with($changes) {
    global $q, $cat, $avail, $sort;
    $p = array_merge(['q' => $q, 'category' => $cat, 'avail' => $avail, 'sort' => $sort === 'new' ? '' : $sort], $changes);
    return 'index.php?' . http_build_query(array_filter($p, function ($v) { return $v !== '' && $v !== 0 && $v !== '0'; }));
}
$filtered = $q !== '' || $cat > 0 || $avail;
$pageTitle = 'หนังสือทั้งหมด';
require 'includes/header.php';
?>
<?php if (!$filtered && $page === 1): ?>
<section class="hero" aria-label="แนะนำร้าน">
  <div><h1>เช่าหนังสือออนไลน์ ส่งถึงบ้าน</h1><p>เลือกหนังสือที่อยากอ่าน เช่า 7, 14 หรือ 30 วัน จ่ายค่าเช่าและมัดจำครั้งเดียว ส่งหนังสือกลับแล้วรับมัดจำคืน</p></div>
  <ul><li>ค่าเช่าเริ่มวันละไม่กี่บาท</li><li>มัดจำคืนเมื่อร้านได้รับหนังสือ</li><li>ติดตามสถานะและเลขพัสดุได้ในหน้า "การเช่าของฉัน"</li></ul>
</section>
<?php endif; ?>

<div class="layout-2">
  <aside class="card filters" aria-label="ตัวกรอง">
    <form action="index.php" method="get">
      <h3>ค้นหา</h3>
      <div class="field"><label class="sr-only" for="fq">ค้นหา</label><input type="text" id="fq" name="q" maxlength="100" value="<?= h($q) ?>" placeholder="ชื่อหนังสือหรือผู้แต่ง"></div>
      <h3>หมวดหมู่</h3>
      <ul class="cat-list">
        <li><a href="<?= h(url_with(['category' => '', 'page' => ''])) ?>" class="<?= $cat === 0 ? 'on' : '' ?>">ทุกหมวด</a></li>
        <?php foreach ($cats as $c): ?><li><a href="<?= h(url_with(['category' => $c[0], 'page' => ''])) ?>" class="<?= $cat === (int)$c[0] ? 'on' : '' ?>"><span><?= h($c[1]) ?></span><span class="muted"><?= (int)$c[2] ?></span></a></li><?php endforeach; ?>
      </ul>
      <h3>สถานะ</h3>
      <label><input type="checkbox" name="avail" value="1"<?= $avail ? ' checked' : '' ?>> เฉพาะเล่มที่ว่าง</label>
      <input type="hidden" name="category" value="<?= (int)$cat ?>"><input type="hidden" name="sort" value="<?= h($sort) ?>">
      <p style="margin-top:1rem"><button type="submit" class="btn btn-primary btn-block">ใช้ตัวกรอง</button></p>
      <?php if ($filtered): ?><p class="center small"><a href="index.php">ล้างตัวกรองทั้งหมด</a></p><?php endif; ?>
    </form>
  </aside>

  <section aria-labelledby="list-title">
    <div class="toolbar">
      <div><h2 id="list-title" style="margin:0"><?= $filtered ? 'ผลการค้นหา' : 'หนังสือทั้งหมด' ?></h2><span class="muted small">พบ <?= (int)$total ?> เล่ม</span></div>
      <form action="index.php" method="get" class="inline-form">
        <input type="hidden" name="q" value="<?= h($q) ?>"><input type="hidden" name="category" value="<?= (int)$cat ?>"><input type="hidden" name="avail" value="<?= (int)$avail ?>">
        <label class="sr-only" for="sort">เรียงตาม</label>
        <select id="sort" name="sort" data-autosubmit><?php foreach ($sorts as $key => $s): ?><option value="<?= h($key) ?>"<?= $sort === $key ? ' selected' : '' ?>><?= h($s[0]) ?></option><?php endforeach; ?></select>
      </form>
    </div>
    <?php if (!$books): ?>
    <div class="card empty"><h3>ไม่พบหนังสือที่ตรงกัน</h3><p>ลองเปลี่ยนคำค้นหรือล้างตัวกรอง</p><a class="btn btn-outline" href="index.php">ดูหนังสือทั้งหมด</a></div>
    <?php else: ?>
    <div class="grid-cards">
      <?php foreach ($books as $b): ?>
      <article class="book-card">
        <a class="cover-link" href="book.php?id=<?= (int)$b['id'] ?>" tabindex="-1" aria-hidden="true"><?= cover_html($b['isbn'], $b['title'], $b['id'], false) ?></a>
        <div class="body">
          <span class="muted small"><?= h($b['cat']) ?></span>
          <h3><a href="book.php?id=<?= (int)$b['id'] ?>"><?= h($b['title']) ?></a></h3>
          <span class="author"><?= h($b['author']) ?></span>
          <div class="foot">
            <span class="price">฿<?= h(rtrim(rtrim(money($b['rent']), '0'), '.')) ?><small>/วัน</small></span>
            <?php if ($b['left'] > 0): ?>
            <form action="cart.php" method="post" class="inline-form"><?php csrf_field(); ?><input type="hidden" name="action" value="add"><input type="hidden" name="book_id" value="<?= (int)$b['id'] ?>"><input type="hidden" name="back" value="<?= h('index.php' . ($_SERVER['QUERY_STRING'] !== '' ? '?' . $_SERVER['QUERY_STRING'] : '')) ?>">
              <button type="submit" class="btn btn-outline btn-sm" aria-label="ใส่ตะกร้า <?= h($b['title']) ?>">+ ตะกร้า</button></form>
            <?php else: ?><span class="badge b-bad">ถูกเช่าหมด</span><?php endif; ?>
          </div>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
    <?php if ($pages > 1): ?>
    <nav class="pager" aria-label="เลือกหน้า">
      <?php if ($page > 1): ?><a href="<?= h(url_with(['page' => $page - 1])) ?>">ก่อนหน้า</a><?php endif; ?>
      <?php for ($i = max(1, $page - 2); $i <= min($pages, $page + 2); $i++): ?>
        <?php if ($i === $page): ?><span class="on" aria-current="page"><?= (int)$i ?></span><?php else: ?><a href="<?= h(url_with(['page' => $i])) ?>"><?= (int)$i ?></a><?php endif; ?>
      <?php endfor; ?>
      <?php if ($page < $pages): ?><a href="<?= h(url_with(['page' => $page + 1])) ?>">ถัดไป</a><?php endif; ?>
    </nav>
    <?php endif; ?>
    <?php endif; ?>
  </section>
</div>
<?php require 'includes/footer.php'; ?>

<?php
require 'auth.php'; require 'config/db.php'; require_once 'includes/discover.php';
// Home = the fair hall: banner, floor map of five zones, curated rows, then the full catalogue.
// Filters come from the URL (?q=&category=&avail=1&sort=&maxprice=&page=), all read with prepared statements.
$q = mb_substr(trim(get_str('q')), 0, 100);
$cat = max(0, (int)get_str('category'));
$avail = (($_GET['avail'] ?? '') === '1') ? 1 : 0;
$maxIn = $_GET['maxprice'] ?? '';
$maxprice = (is_string($maxIn) && is_numeric($maxIn) && $maxIn > 0) ? round(min((float)$maxIn, 9999), 2) : 0.0;   // garbage or arrays = no limit
$maxText = $maxprice > 0 ? rtrim(rtrim(number_format($maxprice, 2, '.', ''), '0'), '.') : '';
$sorts = ['new' => ['ใหม่ล่าสุด', 'b.book_id DESC'], 'price_asc' => ['ราคาเช่าต่ำ → สูง', 'b.rent_per_day ASC, b.title'],
          'price_desc' => ['ราคาเช่าสูง → ต่ำ', 'b.rent_per_day DESC, b.title'], 'title' => ['ชื่อ A → Z', 'b.title ASC'],
          'rating' => ['รีวิวดีที่สุด', 'COALESCE(r.a, 0) DESC, COALESCE(r.n, 0) DESC, b.title'], 'rented' => ['เช่าบ่อยที่สุด', 'COALESCE(lc.n, 0) DESC, b.title']];
$sortIn = get_str('sort');   // ?sort[]=x must not reach the array lookup
$sort = isset($sorts[$sortIn]) ? $sortIn : 'new';   // only a key from the list above ever reaches the SQL text
$perPage = 12;
$page = max(1, (int)get_str('page'));
$like = '%' . addcslashes($q, '%_\\') . '%';                           // "%" and "_" typed by the user stay plain characters

// Categories with the number of books in each (the five zones of the hall)
$cats = [];
$res = mysqli_query($conn, 'SELECT c.category_id, c.name, COUNT(b.book_id) FROM lib_categories c LEFT JOIN lib_books b ON b.category_id = c.category_id GROUP BY c.category_id, c.name ORDER BY c.category_id');
while ($res && ($row = mysqli_fetch_row($res))) $cats[] = $row;
$minRent = 0;
$res = mysqli_query($conn, 'SELECT MIN(rent_per_day) FROM lib_books');
if ($res && ($row = mysqli_fetch_row($res))) $minRent = (float)$row[0];

// How many books match (for the page numbers)
$where = '(CHAR_LENGTH(?) = 0 OR b.title LIKE ? OR b.author LIKE ?) AND (? = 0 OR b.category_id = ?) AND (? = 0 OR b.copies_available > 0) AND (? = 0 OR b.rent_per_day <= ?)';
$types = 'sssiiidd';
$params = [$q, $like, $like, $cat, $cat, $avail, $maxprice, $maxprice];
$stmt = mysqli_prepare($conn, "SELECT COUNT(*) FROM lib_books b WHERE $where");
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $total);
mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);
$pages = max(1, (int)ceil($total / $perPage));
if ($page > $pages) $page = $pages;
$books = discover_books($conn, $where, $types, $params, $sorts[$sort][1], $perPage, ($page - 1) * $perPage);
$wished = wished_ids($conn, $_SESSION['user_id'] ?? 0);

// A link to this page with one filter changed
function url_with($changes) {
    global $q, $cat, $avail, $sort, $maxText;
    $p = array_merge(['q' => $q, 'category' => $cat, 'avail' => $avail, 'sort' => $sort === 'new' ? '' : $sort, 'maxprice' => $maxText], $changes);
    return 'index.php?' . http_build_query(array_filter($p, function ($v) { return $v !== '' && $v !== 0 && $v !== '0'; }));
}
$filtered = $q !== '' || $cat > 0 || $avail || $maxprice > 0;
$curated = [];
if (!$filtered && $page === 1) {
    $feat = discover_books($conn, 'b.featured = 1', '', [], 'b.book_id DESC', 6);
    if ($feat) $curated[] = ['เจ้าหน้าที่คัดสรร', $feat, ''];
    $curated[] = ['มาใหม่', discover_books($conn, '1 = 1', '', [], 'b.book_id DESC', 6), 'index.php?sort=new#catalogue'];
    $ids = [];
    $res = mysqli_query($conn, "SELECT book_id FROM lib_loans WHERE status <> 'pending' AND borrowed_at >= CURDATE() - INTERVAL 90 DAY GROUP BY book_id ORDER BY COUNT(*) DESC, book_id DESC LIMIT 6");
    while ($res && ($row = mysqli_fetch_row($res))) $ids[] = (int)$row[0];
    if ($ids) $curated[] = ['ยอดนิยม', books_by_ids($conn, $ids), 'index.php?sort=rented#catalogue'];
}
$pageTitle = 'หนังสือทั้งหมด';
$pageCss = ['discover']; $pageJs = ['discover'];
require 'includes/header.php';
?>
<?php if (!$filtered && $page === 1): ?>
<section class="banner" aria-labelledby="hall-title">
  <h1 id="hall-title">เช่าหนังสือเล่มโปรด ส่งถึงหน้าบ้าน</h1>
  <p>เลือกเช่า 7, 14 หรือ 30 วัน โอนครั้งเดียวทั้งค่าเช่าและมัดจำ อ่านจบแล้วส่งคืน</p>
  <form class="desk" action="index.php" method="get" role="search">
    <label class="sr-only" for="dq">ค้นหาหนังสือ</label>
    <?= icon('search', 22) ?>
    <input type="text" id="dq" name="q" placeholder="ชื่อหนังสือหรือผู้แต่ง" maxlength="100" autocomplete="off" data-suggest>
    <button type="submit" class="btn btn-yellow">ค้นหา</button>
  </form>
  <ul class="perks">
    <li><?= icon('tag', 18) ?>เช่าเริ่มวันละ ฿<?= h(rtrim(rtrim(money($minRent), '0'), '.')) ?></li>
    <li><?= icon('shield', 18) ?>มัดจำคืนเต็มจำนวนเมื่อส่งหนังสือกลับ</li>
    <li><?= icon('truck', 18) ?>ส่งถึงบ้านพร้อมเลขพัสดุ</li>
  </ul>
</section>
<?php endif; ?>

<nav aria-label="ผังงานหนังสือ แบ่งตามหมวด">
  <div class="floor">
    <?php foreach ($cats as $c): $on = $cat === (int)$c[0]; ?>
    <a class="zone <?= zone_class($c[0]) ?><?= $on ? ' on' : '' ?>" href="<?= $on ? 'index.php#catalogue' : 'index.php?category=' . (int)$c[0] . '#catalogue' ?>"<?= $on ? ' aria-current="true"' : '' ?>>
      <span class="zl" aria-hidden="true"><?= h(zone_letter($c[0])) ?></span>
      <span class="zn"><?= h($c[1]) ?></span>
      <span class="zc"><span><?= (int)$c[2] ?> เล่ม</span><?= icon($on ? 'x' : 'chevron', 18) ?></span>
    </a>
    <?php endforeach; ?>
  </div>
</nav>

<?php foreach ($curated as $row): if (!$row[1]) continue; ?>
<section class="shelf-row" aria-label="<?= h($row[0]) ?>">
  <div class="row-head"><h2><?= h($row[0]) ?></h2><?php if ($row[2] !== ''): ?><a href="<?= h($row[2]) ?>">ดูทั้งหมด<?= icon('chevron', 16) ?></a><?php endif; ?></div>
  <div class="rail"><?php foreach ($row[1] as $b) echo book_card($b, $wished); ?></div>
</section>
<?php endforeach; ?>

<div class="layout-2" id="catalogue">
  <aside class="card filters" aria-label="ตัวกรอง">
    <form action="index.php" method="get">
      <h3>ค้นหา</h3>
      <div class="field"><label class="sr-only" for="fq">ค้นหา</label><input type="text" id="fq" name="q" maxlength="100" autocomplete="off" value="<?= h($q) ?>" placeholder="ชื่อหนังสือหรือผู้แต่ง" data-suggest></div>
      <h3>หมวดหมู่</h3>
      <ul class="cat-list">
        <li><a href="<?= h(url_with(['category' => '', 'page' => ''])) ?>" class="<?= $cat === 0 ? 'on' : '' ?>">ทุกหมวด</a></li>
        <?php foreach ($cats as $c): ?><li><a href="<?= h(url_with(['category' => $c[0], 'page' => ''])) ?>" class="<?= $cat === (int)$c[0] ? 'on' : '' ?>"><span><?= h($c[1]) ?></span><span class="muted"><?= (int)$c[2] ?></span></a></li><?php endforeach; ?>
      </ul>
      <h3>ค่าเช่า</h3>
      <div class="field"><label for="mp" class="small muted">ไม่เกินวันละ (บาท)</label><input type="number" id="mp" name="maxprice" min="1" max="9999" step="1" inputmode="numeric" value="<?= h($maxText) ?>" placeholder="เช่น 10"></div>
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
        <input type="hidden" name="q" value="<?= h($q) ?>"><input type="hidden" name="category" value="<?= (int)$cat ?>"><input type="hidden" name="avail" value="<?= (int)$avail ?>"><input type="hidden" name="maxprice" value="<?= h($maxText) ?>">
        <label class="sr-only" for="sort">เรียงตาม</label>
        <select id="sort" name="sort" data-autosubmit><?php foreach ($sorts as $key => $s): ?><option value="<?= h($key) ?>"<?= $sort === $key ? ' selected' : '' ?>><?= h($s[0]) ?></option><?php endforeach; ?></select>
      </form>
    </div>
    <?php if (!$books): ?>
    <div class="card empty"><h3>ไม่พบหนังสือที่ตรงกัน</h3><p>ลองเปลี่ยนคำค้นหรือล้างตัวกรอง</p><a class="btn btn-outline" href="index.php">ดูหนังสือทั้งหมด</a></div>
    <?php else: ?>
    <div class="grid-cards">
      <?php foreach ($books as $b) echo book_card($b, $wished); ?>
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

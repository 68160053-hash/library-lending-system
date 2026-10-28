<?php
// Home = the shop: shelves are built from lib_books. Read-only queries, all with prepared statements.
require 'auth.php'; require 'config/db.php';

$q = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 100);
$catName = mb_substr(trim((string)($_GET['category'] ?? '')), 0, 80);

// Categories for the search box and the shelf titles
$cats = [];
$res = mysqli_query($conn, 'SELECT category_id, name FROM lib_categories ORDER BY category_id');
while ($res && ($row = mysqli_fetch_assoc($res))) $cats[] = $row;

// Books: the filters are bound as parameters; "%" and "_" typed by the user are escaped so they stay plain characters
$like = '%' . addcslashes($q, '%_\\') . '%';
$sql = 'SELECT b.book_id, b.category_id, c.name, b.title, b.author, b.copies_total, b.copies_available, b.sale_price
        FROM lib_books b JOIN lib_categories c ON c.category_id = b.category_id
        WHERE (CHAR_LENGTH(?) = 0 OR b.title LIKE ? OR b.author LIKE ?) AND (CHAR_LENGTH(?) = 0 OR c.name = ?)
        ORDER BY b.category_id, b.title
        LIMIT 300';
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, 'sssss', $q, $like, $like, $catName, $catName);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $bookId, $catId, $category, $title, $author, $total, $avail, $price);

// Cover colour and emblem come from the book id, so every book looks different without extra DB columns
$colors = ['#2b2f6b', '#5a3a22', '#3b1f3a', '#1f3b4d', '#4a1a1a', '#3d2a5c', '#243049', '#1f4a3a'];
$emblems = ['e-tower', 'e-potion', 'e-moon', 'e-cloud', 'e-key', 'e-lantern', 'e-prism', 'e-leaf', 'e-database', 'e-star', 'e-compass', 'e-flame', 'e-spiral', 'e-wave', 'e-clock', 'e-eye', 'e-feather'];
$shelves = []; $picks = []; $shown = 0; $saleCount = 0;
while (mysqli_stmt_fetch($stmt)) {
    $b = ['id' => $bookId, 'title' => $title, 'author' => $author, 'category' => $category, 'total' => $total, 'avail' => $avail,
          'sale' => $price === null ? '' : rtrim(rtrim(number_format((float)$price, 2, '.', ''), '0'), '.'),
          'color' => $colors[$bookId % count($colors)], 'emb' => $emblems[$bookId % count($emblems)],
          'blurb' => 'หนังสือหมวด' . $category . ' โดย ' . $author];
    $shelves[$catId][] = $b;
    $shown++;
    if ($b['sale'] !== '') $saleCount++;
    if ($avail > 0 && count($picks) < 3) $picks[] = $b;
}
mysqli_stmt_close($stmt);
$catById = [];
foreach ($cats as $c) $catById[$c['category_id']] = $c['name'];

$pageTitle = 'หนังสือทั้งหมด';
require 'includes/header.php';
?>
<main id="main">
  <svg class="sprite" width="0" height="0" aria-hidden="true" focusable="false"><defs>
  <symbol id="e-tower" viewBox="0 0 48 48"><path d="M14 44V16M34 44V16M14 16l-4-8h8l2 4 4-6 4 6 2-4h8l-4 8M20 44V30a4 4 0 0 1 8 0v14"/></symbol>
  <symbol id="e-potion" viewBox="0 0 48 48"><path d="M20 6h8M22 6v12L10 38a4 4 0 0 0 4 6h20a4 4 0 0 0 4-6L26 18V6M14 32h20"/></symbol>
  <symbol id="e-moon" viewBox="0 0 48 48"><path d="M30 8a17 17 0 1 0 10 28A14 14 0 0 1 30 8zM38 10v6M35 13h6"/></symbol>
  <symbol id="e-cloud" viewBox="0 0 48 48"><path d="M14 36a9 9 0 0 1 0-18 12 12 0 0 1 23 3 8 8 0 0 1-2 15z"/></symbol>
  <symbol id="e-key" viewBox="0 0 48 48"><circle cx="16" cy="16" r="8"/><path d="M22 22 40 40M33 33l5-5M37 37l4-4"/></symbol>
  <symbol id="e-lantern" viewBox="0 0 48 48"><path d="M20 10V6h8v4M16 14h16l2 22H14zM12 40h24M24 18v14"/></symbol>
  <symbol id="e-prism" viewBox="0 0 48 48"><path d="M24 6 42 38H6zM2 26l15-2M27 24l19-8M28 28l18 4"/></symbol>
  <symbol id="e-leaf" viewBox="0 0 48 48"><path d="M8 40C8 20 20 8 42 6 42 28 30 40 8 40zM8 40 30 18"/></symbol>
  <symbol id="e-database" viewBox="0 0 48 48"><ellipse cx="24" cy="12" rx="16" ry="6"/><path d="M8 12v24c0 3.3 7 6 16 6s16-2.7 16-6V12M8 24c0 3.3 7 6 16 6s16-2.7 16-6"/></symbol>
  <symbol id="e-star" viewBox="0 0 48 48"><path d="M24 5l5.5 12.5 13.5 1.3-10.2 9 3.1 13.2L24 34 12.1 41l3.1-13.2-10.2-9 13.5-1.3z"/></symbol>
  <symbol id="e-compass" viewBox="0 0 48 48"><circle cx="24" cy="24" r="18"/><path d="M30 18l-4 8-8 4 4-8zM24 3v4M24 41v4M3 24h4M41 24h4"/></symbol>
  <symbol id="e-flame" viewBox="0 0 48 48"><path d="M24 5c8 9 14 15 14 24a14 14 0 0 1-28 0c0-6 4-9 6-14 2 4 4 5 5 7 2-6 1-10 3-17z"/></symbol>
  <symbol id="e-spiral" viewBox="0 0 48 48"><circle cx="24" cy="24" r="18"/><circle cx="24" cy="24" r="11"/><circle cx="24" cy="24" r="4"/></symbol>
  <symbol id="e-wave" viewBox="0 0 48 48"><path d="M4 18c5-6 9 6 14 0s9 6 14 0 9 6 12 2M4 30c5-6 9 6 14 0s9 6 14 0 9 6 12 2"/></symbol>
  <symbol id="e-clock" viewBox="0 0 48 48"><circle cx="24" cy="24" r="18"/><path d="M24 12v12l8 5M24 3v3M24 42v3"/></symbol>
  <symbol id="e-eye" viewBox="0 0 48 48"><path d="M3 24C11 11 37 11 45 24 37 37 11 37 3 24z"/><circle cx="24" cy="24" r="6"/><circle cx="24" cy="24" r="1.5"/></symbol>
  <symbol id="e-feather" viewBox="0 0 48 48"><path d="M40 6C22 8 10 20 8 40M40 6C38 24 28 36 8 40M8 40 22 26M20 22l10 2M16 30l10 2"/></symbol>
</defs></svg>
  <div class="container">
    <section class="hero" id="hero" aria-labelledby="hero-title">
      <div class="shop" id="shop">
        <svg class="px-roofs" viewBox="0 0 1000 160" preserveAspectRatio="none" aria-hidden="true"><path d="M0 160V110h50V80l30-40 30 40v30h70V90h40V60l35-35 35 35v30h60v30h60V70l40-50 40 50v50h70V95h50V65l30-35 30 35v30h80v-20h40V50l35-40 35 40v70h90v40z"/><path class="win" d="M70 120v-14a6 6 0 0 1 12 0v14zM300 130v-14a6 6 0 0 1 12 0v14zM560 128v-14a6 6 0 0 1 12 0v14zM820 120v-14a6 6 0 0 1 12 0v14z"/></svg>
        <div class="facade">
          <div class="roof" aria-hidden="true"><div class="fascia"></div><div class="awning"></div></div>
          <div class="sign"><div class="sign-swing"><span class="chain"></span><span class="chain"></span>
            <div class="board"><p class="eyebrow">OPEN AFTER MIDNIGHT</p><h1 id="hero-title"><span class="h1-latin">Book Lending Shop</span><span class="h1-th">ร้านยืมหนังสือ</span></h1></div></div></div>
          <div class="front">
            <div class="shop-window" aria-hidden="true">
              <div class="win-back"></div>
              <span class="win-lantern" style="--x:9%;--d:0s"><svg viewBox="0 0 30 56" aria-hidden="true"><path d="M15 0v12" class="ln"/><path d="M9 14h12l2-4H7z" class="lg"/><path d="M8 14h14l2 26H6z" class="lg lg-glass"/><path class="lf" d="M15 20c4 5 4 9 0 13-4-4-4-8 0-13z"/><path d="M5 40h20v4H5z" class="lg"/></svg></span><span class="win-lantern" style="--x:88%;--d:-2.2s"><svg viewBox="0 0 30 56" aria-hidden="true"><path d="M15 0v12" class="ln"/><path d="M9 14h12l2-4H7z" class="lg"/><path d="M8 14h14l2 26H6z" class="lg lg-glass"/><path class="lf" d="M15 20c4 5 4 9 0 13-4-4-4-8 0-13z"/><path d="M5 40h20v4H5z" class="lg"/></svg></span>
              <div class="display"><span class="mini" style="--c:#2b2f6b;--r:-4deg;--k:1"><svg class="emb" viewBox="0 0 48 48" aria-hidden="true"><use href="#e-star"/></svg></span><span class="mini" style="--c:#4a1a1a;--r:3deg;--k:1.15"><svg class="emb" viewBox="0 0 48 48" aria-hidden="true"><use href="#e-moon"/></svg></span><span class="mini" style="--c:#1f4a3a;--r:-2deg;--k:1.05"><svg class="emb" viewBox="0 0 48 48" aria-hidden="true"><use href="#e-key"/></svg></span><span class="stack"><i style="--c:#4a1a1a;--w:7.5em"></i><i style="--c:#1f4a3a;--w:6.5em;--o:.6em"></i><i style="--c:#2b2f6b;--w:7em;--o:.2em"></i></span><span class="mini" style="--c:#3d2a5c;--r:4deg;--k:1.15"><svg class="emb" viewBox="0 0 48 48" aria-hidden="true"><use href="#e-eye"/></svg></span><span class="mini" style="--c:#5a3a22;--r:-3deg;--k:1"><svg class="emb" viewBox="0 0 48 48" aria-hidden="true"><use href="#e-feather"/></svg></span></div>
              <div class="win-floor"></div>
              <span class="mullion mullion--v" style="--x:33%"></span><span class="mullion mullion--v" style="--x:66%"></span><span class="mullion mullion--h"></span>
            </div>
            <a class="door" href="#shelves" aria-label="เข้าร้าน ไปที่ชั้นหนังสือ">
              <svg class="bell" viewBox="0 0 40 52" aria-hidden="true"><path d="M20 0v10" class="ln"/><path d="M8 38c2-4 3-8 3-14a9 9 0 0 1 18 0c0 6 1 10 3 14z" class="lg"/><circle cx="20" cy="43" r="4" class="lg"/></svg>
              <span class="ting" aria-hidden="true">ติ๊ง</span><span class="door-inset" aria-hidden="true"></span>
            </a>
          </div>
          <div class="step" aria-hidden="true"></div>
        </div>
      </div>
      <p class="tagline">ค้นหาตำราและเรื่องราวที่รอคุณอยู่บนชั้นหนังสือ แล้วยืมกลับบ้านได้ในไม่กี่คลิก</p>
    </section>

    <form class="ask frame-silver reveal" action="index.php" method="get" aria-label="ค้นหาหนังสือ">
      <p class="eyebrow">Ask the shopkeeper</p>
      <div class="field">
        <label for="q">ค้นหาหนังสือ</label>
        <input type="text" id="q" name="q" placeholder="ชื่อหนังสือ หรือผู้แต่ง" autocomplete="off" maxlength="100" value="<?= h($q) ?>">
      </div>
      <div class="field">
        <label for="category">หมวดหมู่</label>
        <select id="category" name="category"><option value="">ทุกหมวด</option><?php foreach ($cats as $c): ?><option<?= $c['name'] === $catName ? ' selected' : '' ?>><?= h($c['name']) ?></option><?php endforeach; ?></select>
      </div>
      <button type="submit" class="btn btn-primary btn-magic">ค้นหา</button>
    </form>

    <div class="section-head reveal">
      <p class="eyebrow">The Display Shelves</p>
      <h2 id="catalogue-title">ชั้นวางหน้าร้าน</h2>
    </div>
    <div class="sign-rail reveal" role="group" aria-label="เลือกหมวดหนังสือ">
      <button type="button" class="sign-tab" data-filter="all" aria-pressed="true">ทั้งหมด</button>
      <?php foreach ($shelves as $cid => $list): ?><button type="button" class="sign-tab" data-filter="c<?= (int)$cid ?>" aria-pressed="false"><?= h($catById[$cid] ?? '') ?></button><?php endforeach; ?>
      <?php if ($saleCount): ?><button type="button" class="sign-tab" data-filter="sale" aria-pressed="false">ซื้อได้</button><?php endif; ?>
    </div>
    <p class="shelf-status" id="status" aria-live="polite"><?= $shown ? 'แสดงหนังสือ ' . (int)$shown . ' เล่ม' : 'ยังไม่มีหนังสือที่ตรงกัน ลองคำอื่นดูนะ' ?></p>

    <div id="shelves">
      <?php foreach ($shelves as $cid => $list): ?>
      <section class="shelf-row reveal" data-group="c<?= (int)$cid ?>" aria-labelledby="shelf-<?= (int)$cid ?>">
        <div class="lanterns" aria-hidden="true"><span class="lantern" style="--x:34%;--d:0s"><svg viewBox="0 0 30 56" aria-hidden="true"><path d="M15 0v12" class="ln"/><path d="M9 14h12l2-4H7z" class="lg"/><path d="M8 14h14l2 26H6z" class="lg lg-glass"/><path class="lf" d="M15 20c4 5 4 9 0 13-4-4-4-8 0-13z"/><path d="M5 40h20v4H5z" class="lg"/></svg></span><span class="lantern" style="--x:62%;--d:-1.7s"><svg viewBox="0 0 30 56" aria-hidden="true"><path d="M15 0v12" class="ln"/><path d="M9 14h12l2-4H7z" class="lg"/><path d="M8 14h14l2 26H6z" class="lg lg-glass"/><path class="lf" d="M15 20c4 5 4 9 0 13-4-4-4-8 0-13z"/><path d="M5 40h20v4H5z" class="lg"/></svg></span><span class="lantern" style="--x:90%;--d:-3.1s"><svg viewBox="0 0 30 56" aria-hidden="true"><path d="M15 0v12" class="ln"/><path d="M9 14h12l2-4H7z" class="lg"/><path d="M8 14h14l2 26H6z" class="lg lg-glass"/><path class="lf" d="M15 20c4 5 4 9 0 13-4-4-4-8 0-13z"/><path d="M5 40h20v4H5z" class="lg"/></svg></span></div>
        <header class="shelf-head"><h2 id="shelf-<?= (int)$cid ?>"><?= h($catById[$cid] ?? '') ?></h2><p class="row-count"><?= count($list) ?> เล่ม</p></header>
        <ul class="books">
          <?php foreach ($list as $i => $b):
            $left = (int)$b['avail'];
            $label = $b['title'] . ' — ' . $b['author'] . ' (' . ($left > 0 ? 'ว่าง ' . $left . ' จาก ' . (int)$b['total'] . ' เล่ม' : 'ถูกยืมหมด') . ($b['sale'] !== '' ? ', ซื้อได้ ฿' . $b['sale'] : '') . ')';
            $isPick = false;
            foreach ($picks as $p) if ($p['id'] === $b['id']) $isPick = true;
          ?>
          <li style="--i:<?= (int)$i ?>"><button type="button" class="book<?= $left > 0 ? '' : ' is-out' ?>" style="--c:<?= h($b['color']) ?>" aria-label="<?= h($label) ?>" data-id="<?= (int)$b['id'] ?>" data-title="<?= h($b['title']) ?>" data-author="<?= h($b['author']) ?>" data-category="<?= h($b['category']) ?>" data-available="<?= $left ?>" data-total="<?= (int)$b['total'] ?>" data-sale="<?= h($b['sale']) ?>" data-blurb="<?= h($b['blurb']) ?>"<?= $isPick ? ' data-pick' : '' ?>><span class="pages"></span><span class="face"><svg class="emb" viewBox="0 0 48 48" aria-hidden="true"><use href="#<?= h($b['emb']) ?>"/></svg><span class="c-title"><?= h($b['title']) ?></span><span class="c-author"><?= h($b['author']) ?></span></span></button><span class="hang" aria-hidden="true"><span class="hang-tag"><i><?= $left > 0 ? 'ว่าง ' . $left . '/' . (int)$b['total'] : 'ถูกยืมหมด' ?></i></span><?php if ($b['sale'] !== ''): ?><span class="hang-tag hang-tag--sale"><i>ซื้อได้ ฿<?= h($b['sale']) ?></i></span><?php endif; ?></span></li>
          <?php endforeach; ?>
        </ul>
      </section>
      <?php endforeach; ?>
    </div>

    <?php if ($picks): ?>
    <section class="feature reveal" aria-labelledby="ft-head">
      <div class="section-head"><p class="eyebrow">Tonight's pick</p><h2 id="ft-head">หนังสือแนะนำคืนนี้</h2></div>
      <div class="ft-wrap">
        <button type="button" class="ft-arrow" id="ft-prev" aria-label="เล่มแนะนำก่อนหน้า"><svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M15 5l-7 7 7 7"/></svg></button>
        <div class="reading">
          <span class="dust" style="--x:18%;--s:3px;--t:7s;--d:0s;--dx:12px"></span><span class="dust" style="--x:34%;--s:2px;--t:9s;--d:-3s;--dx:-10px"></span><span class="dust" style="--x:52%;--s:4px;--t:8s;--d:-5s;--dx:14px"></span><span class="dust" style="--x:66%;--s:2px;--t:10s;--d:-1s;--dx:-16px"></span><span class="dust" style="--x:80%;--s:3px;--t:7.5s;--d:-6s;--dx:8px"></span><span class="dust" style="--x:44%;--s:2px;--t:11s;--d:-8s;--dx:-6px"></span>
          <div class="ft-book">
            <div class="ft-page ft-left" id="ft-art" style="--c:<?= h($picks[0]['color']) ?>" aria-hidden="true"><div class="ft-cover"><svg class="emb" viewBox="0 0 48 48"><use id="ft-use" href="#<?= h($picks[0]['emb']) ?>"/></svg><span id="ft-cover-title"><?= h($picks[0]['title']) ?></span></div></div>
            <div class="ft-page ft-right paper">
              <p class="eyebrow" id="ft-count">เล่มที่ 1 จาก <?= count($picks) ?></p>
              <div aria-live="polite" aria-atomic="true"><h3 id="ft-title"><?= h($picks[0]['title']) ?></h3><p class="meta" id="ft-author"><?= h($picks[0]['author']) ?></p><p id="ft-blurb"><?= h($picks[0]['blurb']) ?></p></div>
              <div class="actions"><button type="button" class="btn btn-primary btn-magic" id="ft-borrow">ดูรายละเอียดและยืม</button></div>
            </div>
          </div>
          <div class="ft-stand" aria-hidden="true"></div>
        </div>
        <button type="button" class="ft-arrow" id="ft-next" aria-label="เล่มแนะนำถัดไป"><svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M9 5l7 7-7 7"/></svg></button>
      </div>
    </section>
    <?php endif; ?>
  </div>

<dialog class="book-dialog" aria-labelledby="bd-title">
  <div class="tome"><!-- หนังสือที่เปิดอยู่ตรงหน้า: ปกหนังหมุนเปิดรอบสันหนังสือ -->
    <div class="tome-board" aria-hidden="true"></div>
    <div class="tome-page tome-left" aria-hidden="true"></div><!-- ที่ว่างฝั่งซ้าย: หน้าซ้ายจริงคือด้านในของปก (.cover-back) -->

    <section class="tome-page tome-right paper">
      <p class="eyebrow">Book Lending Shop</p>
      <h2 id="bd-title"></h2>
      <p class="meta" id="bd-author"></p>
      <p id="bd-blurb"></p>
      <p><span class="stamp stamp--ok" id="bd-stamp"></span> <span class="bd-price" id="bd-price" hidden></span></p>
      <div class="actions">
        <button type="button" class="btn btn-primary btn-magic" id="bd-borrow" data-soon title="ระบบยืมกำลังพัฒนา (งานของเพื่อน B)">ยืมหนังสือ</button>
        <button type="button" class="btn btn-outline" id="bd-buy" hidden>ซื้อเล่มนี้</button>
        <button type="button" class="btn btn-outline" id="bd-close">ปิด</button>
      </div>
    </section>
    <div class="tome-cover" aria-hidden="true">
      <div class="cover-front"><span id="bd-front-title"></span></div>
      <div class="cover-back"><!-- ด้านในของปก = หน้าซ้ายของหนังสือที่เปิดแล้ว -->
        <div class="tome-art">
          <svg class="emb" viewBox="0 0 24 24"><path d="M4 5h6a2 2 0 0 1 2 2v12a2 2 0 0 0-2-2H4zM20 5h-6a2 2 0 0 0-2 2v12a2 2 0 0 1 2-2h6z"/></svg>
          <span id="bd-cover-title"></span>
        </div>
        <p class="tome-hand">อ่านแล้วอย่าลืมคืนนะ</p>
        <p class="tome-flourish">❦</p>
      </div>
    </div>
    <span class="tome-ribbon" aria-hidden="true"></span>
  </div>
</dialog>
</main>
<?php require 'includes/footer.php'; ?>

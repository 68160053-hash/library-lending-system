<?php
require 'auth.php'; require 'config/db.php';
http_response_code(404);
$pageTitle = 'ไม่พบหน้านี้'; $pageCss = ['platform'];
require 'includes/header.php';
?>
<div class="banner lost-banner">
  <p class="lost-code" aria-hidden="true">404</p>
  <h1>หาหน้านี้ไม่เจอ</h1>
  <p>ลิงก์อาจพิมพ์ผิดหรือหน้านี้ถูกย้ายไปแล้ว ลองค้นหาหนังสือจากโต๊ะประชาสัมพันธ์ หรือกลับไปเดินดูในฮอลล์</p>
  <form class="desk" action="index.php" method="get" role="search">
    <?= icon('search', 22) ?><label class="sr-only" for="nf-q">ค้นหาหนังสือ</label>
    <input type="text" id="nf-q" name="q" placeholder="ค้นหาชื่อหนังสือหรือผู้แต่ง" maxlength="100" autocomplete="off">
    <button type="submit" class="btn btn-yellow">ค้นหา</button>
  </form>
  <div class="lost-links"><a class="btn btn-yellow btn-sm" href="index.php">กลับหน้าร้าน</a><a class="btn btn-sm" style="background:rgba(255,255,255,.14);color:#fff" href="faq.php">คำถามที่พบบ่อย</a></div>
</div>
<?php require 'includes/footer.php'; ?>

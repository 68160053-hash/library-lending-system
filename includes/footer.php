<?php if (!function_exists('h')) { http_response_code(403); exit; } // never run on its own: only included by a page that loaded auth.php ?>
</main>
<footer class="footer">
  <div class="footer-in">
    <div><h4>Book Lending Shop</h4><p>ร้านเช่าหนังสือออนไลน์ เช่า 7 / 14 / 30 วัน ส่งถึงบ้านพร้อมเลขพัสดุ</p><p><a href="about.php">เกี่ยวกับร้าน</a> &middot; <a href="faq.php">คำถามที่พบบ่อย</a></p></div>
    <div><h4>วิธีเช่า</h4><p>1. เลือกหนังสือใส่ตะกร้า<br>2. กรอกที่อยู่ ชำระค่าเช่าและมัดจำ<br>3. รอรับพัสดุ อ่านให้จบ แล้วส่งคืน</p></div>
    <div><h4>เงื่อนไข</h4><p>มัดจำคืนเมื่อร้านได้รับหนังสือ หักเฉพาะค่าปรับ<br>เกินกำหนดคิดค่าเช่าวันละเท่าราคาเช่า<br>หนังสือสูญหายไม่คืนมัดจำ</p></div>
  </div>
</footer>
<script src="assets/js/site.js?v=<?= (int)@filemtime(__DIR__ . '/../assets/js/site.js') ?>"></script>
<?php foreach ((array)($pageJs ?? []) as $js): $js = preg_replace('/[^a-z0-9-]/', '', $js); ?>
<script src="assets/js/<?= h($js) ?>.js?v=<?= (int)@filemtime(__DIR__ . '/../assets/js/' . $js . '.js') ?>"></script>
<?php endforeach; ?>
</body>
</html>

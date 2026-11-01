<?php
require 'auth.php'; require 'config/db.php';
$cats = [];
$res = mysqli_query($conn, 'SELECT c.category_id, c.name, COUNT(b.book_id) FROM lib_categories c LEFT JOIN lib_books b ON b.category_id = c.category_id GROUP BY c.category_id, c.name ORDER BY c.category_id');
while ($res && ($row = mysqli_fetch_row($res))) $cats[] = $row;
$late = AUTO_LOST_DAYS;
$pageTitle = 'เกี่ยวกับร้าน'; $pageCss = ['platform'];
require 'includes/header.php';
?>
<div class="info-page">
  <div class="banner">
    <h1>อ่านให้หนำใจ จ่ายตามวันที่อ่านจริง</h1>
    <p>Book Lending Shop เป็นร้านเช่าหนังสือออนไลน์ เช่าเป็นรายวันตั้งแต่ไม่กี่บาท จ่ายมัดจำที่คืนเต็มจำนวนเมื่อส่งหนังสือกลับ และส่งถึงบ้านพร้อมเลขพัสดุ</p>
    <ul class="perks">
      <li><?= icon('calendar', 18) ?>เช่า 7 / 14 / 30 วัน</li>
      <li><?= icon('shield', 18) ?>มัดจำคืนเต็มจำนวน</li>
      <li><?= icon('truck', 18) ?>ส่งถึงบ้าน มีเลขพัสดุ</li>
    </ul>
  </div>

  <section class="sec z1" aria-labelledby="s-how"><span class="sec-mark" aria-hidden="true">A</span><h2 id="s-how">เช่าอย่างไร</h2>
    <div class="sec-body"><ol class="ticket-steps">
      <li><b>เลือกหนังสือ</b>ค้นหาหรือเดินตามโซนหมวด ใส่ตะกร้าได้สูงสุด 5 เล่ม</li>
      <li><b>เลือกจำนวนวัน</b>7, 14 หรือ 30 วัน เห็นยอดรวมแยกค่าเช่ากับมัดจำก่อนสั่ง</li>
      <li><b>โอนเงินแล้วแจ้งชำระ</b>โอนค่าเช่ากับมัดจำ แล้วกดแจ้งชำระในหน้าคำสั่งเช่า เจ้าหน้าที่ตรวจและยืนยัน</li>
      <li><b>รับ อ่าน ส่งคืน</b>ร้านส่งพัสดุพร้อมเลขติดตาม อ่านให้จบ แล้วส่งกลับก่อนครบกำหนด</li>
    </ol></div>
  </section>

  <section class="sec z2" aria-labelledby="s-rules"><span class="sec-mark" aria-hidden="true">B</span><h2 id="s-rules">กติกาที่ควรรู้</h2>
    <div class="sec-body"><ul class="rules">
      <li><b>มัดจำ</b> คืนเต็มจำนวนเมื่อร้านได้รับหนังสือคืน หักเฉพาะค่าปรับ (ถ้ามี)</li>
      <li><b>ค่าปรับ</b> จำนวนวันที่เกินกำหนด x ค่าเช่ารายวันของเล่มนั้น หักจากมัดจำ</li>
      <li><b>หนังสือสูญหาย</b> ถ้าเกินกำหนด <?= (int)$late ?> วัน ระบบบันทึกว่าสูญหายอัตโนมัติ และร้านเก็บมัดจำไว้</li>
      <li><b>ต่ออายุ</b> ต่อได้ 1 ครั้ง ครั้งละ +7 วัน</li>
      <li><b>เช่าหลายเล่ม</b> 3 เล่มลดค่าเช่า 10% ตั้งแต่ 4 เล่มลด 15% (ลดเฉพาะค่าเช่า ไม่ลดมัดจำ)</li>
      <li><b>โค้ดส่วนลด</b> ใช้ได้ 1 โค้ดต่อ 1 คำสั่งเช่า</li>
    </ul></div>
  </section>

  <section class="sec z3" aria-labelledby="s-pay"><span class="sec-mark" aria-hidden="true">C</span><h2 id="s-pay">ชำระเงินและจัดส่ง</h2>
    <div class="sec-body">
      <p>ชำระด้วยการโอนเงิน ร้านยังไม่มีระบบตัดบัตร หลังโอนให้กดแจ้งชำระในหน้าคำสั่งเช่า เจ้าหน้าที่จะตรวจและยืนยัน คำสั่งเช่าที่ไม่ชำระภายใน 48 ชั่วโมงจะถูกยกเลิกเพื่อไม่ให้หนังสือถูกจองค้าง</p>
      <p>เมื่อจัดส่ง หน้าคำสั่งเช่าจะแสดงเลขพัสดุและสถานะทุกขั้น และมีการแจ้งเตือนในเว็บเมื่อมีความเคลื่อนไหว</p>
    </div>
  </section>

  <section class="sec z4" aria-labelledby="s-books"><span class="sec-mark" aria-hidden="true">D</span><h2 id="s-books">ในฮอลล์มีอะไร</h2>
    <div class="sec-body">
      <p>หนังสือคัดมาให้อ่านเอง มีปกจริงและคำอธิบายจริง แบ่งเป็นโซนตามหมวด กดเพื่อดูหนังสือในโซนนั้น</p>
      <?php if ($cats): ?><ul class="zone-links">
      <?php foreach ($cats as $c): ?><li><a class="<?= h(zone_class($c[0])) ?>" href="index.php?category=<?= (int)$c[0] ?>"><b><?= h(zone_letter($c[0])) ?></b><?= h($c[1]) ?> (<?= (int)$c[2] ?>)</a></li><?php endforeach; ?>
      </ul><?php endif; ?>
    </div>
  </section>

  <section class="sec z5" aria-labelledby="s-note"><span class="sec-mark" aria-hidden="true">E</span><h2 id="s-note">เกี่ยวกับเว็บนี้</h2>
    <div class="sec-body">
      <p class="note">เว็บนี้ทำขึ้นเป็นโครงงานวิชา Database Management มหาวิทยาลัยบูรพา ราคาเช่า มัดจำ และจำนวนเล่มในสต็อกเป็นค่าตัวอย่างเพื่อสาธิตการทำงาน</p>
      <p><a class="btn btn-primary" href="faq.php">ดูคำถามที่พบบ่อย</a></p>
    </div>
  </section>
</div>
<?php require 'includes/footer.php'; ?>

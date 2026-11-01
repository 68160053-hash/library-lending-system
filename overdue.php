<?php
require 'auth.php'; require 'config/db.php';
require_staff(); // server-side role check: members get 403

// All unreturned loans past their due date. No user input here, so mysqli_query is safe.
// Only parcels already shipped can be overdue. Fee = late days x the daily rate saved on the rental.
$sql = "SELECT u.full_name, b.title, l.due_date,
               DATEDIFF(CURDATE(), l.due_date) AS days_late,
               DATEDIFF(CURDATE(), l.due_date) * l.daily_rate AS fine
        FROM lib_loans l
        JOIN lib_users u ON u.user_id = l.user_id
        JOIN lib_books b ON b.book_id = l.book_id
        WHERE l.status = 'shipped' AND l.due_date < CURDATE()
        ORDER BY l.due_date";
$result = mysqli_query($conn, $sql);
$count = $result ? mysqli_num_rows($result) : 0;

$pageTitle = 'รายการเกินกำหนด';
require 'includes/header.php';
?>
<main id="main">
  <div class="container">
    <p class="eyebrow">OVERDUE NOTICES</p>
    <h1 class="page-title">รายการเกินกำหนดคืน</h1>
    <p class="margin-note">สำหรับเจ้าหน้าที่ &middot; ค่าปรับ = วันที่เกินกำหนด x ราคาเช่าต่อวันของเล่มนั้น</p>
    <p class="count-line">เกินกำหนด <?= (int)$count ?> รายการ</p>
    <div class="letters">
      <?php if ($result): while ($row = mysqli_fetch_assoc($result)): ?>
      <article class="red-letter paper">
        <span class="seal seal--sm" aria-hidden="true">B</span>
        <h2 class="member"><?= h($row['full_name']) ?></h2>
        <p class="book-name"><?= h($row['title']) ?></p>
        <p class="late-info">กำหนดคืน <span class="typed"><?= h(date('d/m/Y', strtotime($row['due_date']))) ?></span> &middot; <span class="stamp stamp--late">เกิน <?= h($row['days_late']) ?> วัน</span></p>
        <p class="fine"><span class="fine-label">ค่าปรับ</span> <?= h(number_format($row['fine'], 2)) ?> บาท</p>
        <a class="btn btn-primary" href="rentals.php?status=shipped">ไปหน้ารับคืน</a>
      </article>
      <?php endwhile; endif; ?>
    </div>
  </div>
</main>
<?php require 'includes/footer.php'; ?>

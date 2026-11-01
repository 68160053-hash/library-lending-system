<?php
require 'auth.php'; require 'config/db.php';
require_staff(); // server-side role check: members get 403

// Which rentals to show: a fixed list of allowed values, so nothing from the URL reaches the SQL text.
$filters = ['pending' => 'รอส่งพัสดุ', 'shipped' => 'ส่งแล้ว', 'returned' => 'คืนแล้ว', 'lost' => 'สูญหาย'];
$status = (string)($_GET['status'] ?? 'pending');
if (!isset($filters[$status])) $status = 'pending';

// Late fee = days past the due date x the daily rate saved on the rental. The refund is the deposit minus that fee (never below 0).
// Nothing is stored for these amounts: they are worked out from the dates every time the page is shown.
$sql = "SELECT l.loan_id, u.full_name, b.title, l.ship_address, l.status, l.borrowed_at, l.due_date, l.deposit, l.daily_rate,
               CASE WHEN l.status = 'shipped' THEN GREATEST(0, DATEDIFF(CURDATE(), l.due_date))
                    WHEN l.status = 'returned' THEN GREATEST(0, DATEDIFF(l.returned_at, l.due_date)) ELSE 0 END AS late_days
        FROM lib_loans l
        JOIN lib_users u ON u.user_id = l.user_id
        JOIN lib_books b ON b.book_id = l.book_id
        WHERE l.status = ?
        ORDER BY l.loan_id DESC LIMIT 200";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, 's', $status);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $loanId, $member, $title, $address, $st, $borrowedAt, $dueDate, $deposit, $rate, $lateDays);

$pageTitle = 'จัดการการเช่า';
require 'includes/header.php';
?>
<main id="main">
  <div class="container">
    <section class="panel frame-silver" aria-labelledby="rent-title">
    <p class="eyebrow">RENTAL DESK</p>
    <h1 class="page-title" id="rent-title">จัดการการเช่า</h1>
    <?php if (isset($_GET['done'])): ?><p class="hint">บันทึกเรียบร้อย</p><?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
    <div class="alert" role="alert"><svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v6M12 16.5v.5"/></svg><span>ทำรายการไม่ได้ รายการนี้อยู่ในขั้นตอนอื่นแล้ว</span></div>
    <?php endif; ?>
    <div class="sign-rail" role="group" aria-label="กรองตามสถานะ">
      <?php foreach ($filters as $key => $label): ?>
      <a class="sign-tab" href="rentals.php?status=<?= h($key) ?>" aria-pressed="<?= $key === $status ? 'true' : 'false' ?>"><?= h($label) ?></a>
      <?php endforeach; ?>
    </div>
    <div class="checkout-card paper frame">
      <div class="table-wrap">
        <table>
          <caption>รายการเช่า: <?= h($filters[$status]) ?></caption>
          <thead><tr><th scope="col">ผู้เช่า</th><th scope="col">หนังสือ</th><th scope="col">ที่อยู่จัดส่ง</th><th scope="col">กำหนดคืน</th><th scope="col" class="num">ค่าปรับ</th><th scope="col" class="num">มัดจำ</th><th scope="col">ดำเนินการ</th></tr></thead>
          <tbody>
            <?php $count = 0; while (mysqli_stmt_fetch($stmt)): $count++; $fine = $lateDays * $rate; ?>
            <tr>
              <td data-label="ผู้เช่า"><?= h($member) ?></td>
              <td data-label="หนังสือ" class="book-title"><?= h($title) ?></td>
              <td data-label="ที่อยู่จัดส่ง"><?= h($address) ?></td>
              <td data-label="กำหนดคืน"><span class="typed"><?= h(date('d/m/Y', strtotime($dueDate))) ?></span><?= $lateDays > 0 ? ' <span class="stamp stamp--late">เกิน ' . (int)$lateDays . ' วัน</span>' : '' ?></td>
              <td data-label="ค่าปรับ" class="num typed"><?= h(number_format($fine, 2)) ?> บาท</td>
              <td data-label="มัดจำ" class="num typed"><?= h(number_format($deposit, 2)) ?> บาท<?= $st === 'returned' ? '<br>คืนให้ ' . h(number_format(max(0, $deposit - $fine), 2)) : '' ?><?= $st === 'lost' ? '<br>ไม่คืน' : '' ?></td>
              <td data-label="ดำเนินการ">
                <?php if ($st === 'pending'): ?>
                <form action="rental-action.php" method="post"><?php csrf_field(); ?>
                  <input type="hidden" name="loan_id" value="<?= (int)$loanId ?>"><input type="hidden" name="action" value="ship">
                  <button type="submit" class="btn btn-primary btn-magic">ส่งพัสดุแล้ว</button>
                </form>
                <?php elseif ($st === 'shipped'): ?>
                <form action="rental-action.php" method="post"><?php csrf_field(); ?>
                  <input type="hidden" name="loan_id" value="<?= (int)$loanId ?>"><input type="hidden" name="action" value="receive">
                  <button type="submit" class="btn btn-primary btn-magic">รับคืนแล้ว</button>
                </form>
                <form action="rental-action.php" method="post"><?php csrf_field(); ?>
                  <input type="hidden" name="loan_id" value="<?= (int)$loanId ?>"><input type="hidden" name="action" value="lost">
                  <button type="submit" class="btn btn-outline">แจ้งสูญหาย</button>
                </form>
                <?php else: ?>-<?php endif; ?>
              </td>
            </tr>
            <?php endwhile; ?>
            <?php if ($count === 0): ?><tr><td colspan="7">ไม่มีรายการในสถานะนี้</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    </section>
  </div>
</main>
<?php mysqli_stmt_close($stmt); require 'includes/footer.php'; ?>

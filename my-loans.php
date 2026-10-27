<?php
require 'auth.php'; require 'config/db.php';
require_login();
$userId = (int)$_SESSION['user_id'];

// Step 1: only the CURRENT user's loans (user_id comes from the session, not from the URL).
// The fine (5 baht/day late) is calculated in SQL; days_late > 0 means overdue.
$sql = 'SELECT l.loan_id, b.title, l.borrowed_at, l.due_date, l.returned_at, l.renew_count,
               DATEDIFF(CURDATE(), l.due_date) AS days_late,
               GREATEST(0, DATEDIFF(IFNULL(l.returned_at, CURDATE()), l.due_date)) * 5 AS fine
        FROM lib_loans l JOIN lib_books b ON b.book_id = l.book_id
        WHERE l.user_id = ?
        ORDER BY l.returned_at IS NULL DESC, l.due_date';
// Step 2: bind, execute, bind the columns to variables.
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, 'i', $userId);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $loanId, $title, $borrowedAt, $dueDate, $returnedAt, $renewCount, $daysLate, $fine);

$count = 0;
$pageTitle = 'การยืมของฉัน';
require 'includes/header.php';
?>
<main id="main">
  <div class="container">
    <section class="panel frame-silver" aria-labelledby="loans-title">
    <p class="eyebrow">MY BORROWING</p>
    <h1 class="page-title" id="loans-title">การยืมของฉัน</h1>
    <?php if (isset($_GET['renewed'])): ?><p class="hint">ต่ออายุเรียบร้อย (+7 วัน)</p><?php endif; ?>
    <?php if (isset($_GET['renew_error'])): ?>
    <div class="alert" role="alert"><svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v6M12 16.5v.5"/></svg><span>ต่ออายุไม่ได้: ต่อไปแล้ว เกินกำหนด หรือไม่ใช่รายการของคุณ</span></div>
    <?php endif; ?>
    <div class="checkout-card paper frame" role="group" aria-label="บัตรยืมหนังสือ">
      <p class="card-head"><span>บัตรยืมหนังสือ</span> &middot; <span>Book Lending Shop</span></p>
      <p class="margin-note">ยืมได้ 7 วัน ต่ออายุได้ 1 ครั้ง ค่าปรับ 5 บาท/วัน</p>
      <div class="table-wrap">
        <table>
          <caption>ประวัติการยืมของฉัน</caption>
          <thead><tr><th scope="col">ชื่อหนังสือ</th><th scope="col">วันยืม</th><th scope="col">กำหนดคืน</th><th scope="col">สถานะ</th><th scope="col" class="num">ค่าปรับ</th><th scope="col">ต่ออายุ</th></tr></thead>
          <tbody>
            <?php while (mysqli_stmt_fetch($stmt)): ?>
            <?php
              $count++;
              // Step 3: status from the numbers SQL gave us
              if ($returnedAt !== null)  { $label = 'คืนแล้ว';       $cls = 'stamp--ok'; }
              elseif ($daysLate > 0)     { $label = 'เกินกำหนด';     $cls = 'stamp--late'; }
              elseif ($daysLate >= -2)   { $label = 'ใกล้ครบกำหนด';  $cls = 'stamp--soon'; }
              else                       { $label = 'ปกติ';          $cls = 'stamp--ok'; }
              $canRenew = $returnedAt === null && $renewCount < 1 && $daysLate <= 0;
            ?>
            <tr style="--i:<?= (int)$count ?>">
              <td data-label="ชื่อหนังสือ" class="book-title"><?= h($title) ?></td>
              <td data-label="วันยืม"><span class="typed"><?= h(date('d/m/Y', strtotime($borrowedAt))) ?></span></td>
              <td data-label="กำหนดคืน"><span class="stamp-date <?= $daysLate > 0 && $returnedAt === null ? 'stamp-date--late' : 'stamp-date--navy' ?>"><?= h(date('d/m/Y', strtotime($dueDate))) ?></span></td>
              <td data-label="สถานะ"><span class="stamp <?= $cls ?>"><?= h($label) ?></span></td>
              <td data-label="ค่าปรับ" class="num typed"><?= h($fine) ?> บาท</td>
              <td data-label="ต่ออายุ">
                <?php if ($returnedAt !== null): ?>-
                <?php elseif ($canRenew): ?>
                <form action="renew.php" method="post">
                  <?php csrf_field(); ?>
                  <input type="hidden" name="loan_id" value="<?= h($loanId) ?>">
                  <button type="submit" class="btn btn-outline btn-magic">ต่ออายุ</button>
                </form>
                <?php else: ?>
                <button type="button" class="btn btn-outline" disabled title="<?= $renewCount >= 1 ? 'ต่ออายุครบ 1 ครั้งแล้ว' : 'เกินกำหนดแล้ว ต่ออายุไม่ได้' ?>">ต่ออายุ</button>
                <?php endif; ?>
              </td>
            </tr>
            <?php endwhile; ?>
            <?php if ($count === 0): ?>
            <tr><td colspan="6">ยังไม่มีรายการยืม ลองเลือกหนังสือเล่มแรกดูนะ</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    </section>
  </div>
</main>
<?php mysqli_stmt_close($stmt); require 'includes/footer.php'; ?>

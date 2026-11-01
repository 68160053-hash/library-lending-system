<?php
require 'auth.php'; require 'config/db.php';
require_staff(); // server-side role check: members get 403

// All books with their category, newest first. No user input here, so mysqli_query is safe.
$sql = 'SELECT b.book_id, b.title, b.author, c.name, b.copies_total, b.copies_available, b.rent_per_day, b.deposit
        FROM lib_books b JOIN lib_categories c ON c.category_id = b.category_id
        ORDER BY b.book_id DESC LIMIT 500';
$result = mysqli_query($conn, $sql);

$pageTitle = 'จัดการหนังสือ';
require 'includes/header.php';
?>
<main id="main">
  <div class="container">
    <section class="panel frame-silver" aria-labelledby="books-title">
    <p class="eyebrow">THE STOCKROOM</p>
    <h1 class="page-title" id="books-title">จัดการหนังสือ</h1>
    <?php if (isset($_GET['saved'])): ?><p class="hint">บันทึกเรียบร้อย</p><?php endif; ?>
    <?php if (isset($_GET['deleted'])): ?><p class="hint">ลบหนังสือเรียบร้อย</p><?php endif; ?>
    <?php if (isset($_GET['delete_error'])): ?>
    <div class="alert" role="alert"><svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v6M12 16.5v.5"/></svg><span>ลบไม่ได้ เพราะหนังสือเล่มนี้มีประวัติการเช่าอยู่แล้ว</span></div>
    <?php endif; ?>
    <p><a class="btn btn-primary btn-magic" href="book-form.php">+ เพิ่มหนังสือ</a></p>
    <div class="checkout-card paper frame">
      <div class="table-wrap">
        <table>
          <caption>หนังสือทั้งหมด</caption>
          <thead><tr><th scope="col">ชื่อเรื่อง</th><th scope="col">ผู้แต่ง</th><th scope="col">หมวด</th><th scope="col" class="num">ว่าง/ทั้งหมด</th><th scope="col" class="num">เช่า/วัน</th><th scope="col" class="num">มัดจำ</th><th scope="col">จัดการ</th></tr></thead>
          <tbody>
            <?php $count = 0; if ($result): while ($row = mysqli_fetch_assoc($result)): $count++; ?>
            <tr>
              <td data-label="ชื่อเรื่อง" class="book-title"><?= h($row['title']) ?></td>
              <td data-label="ผู้แต่ง"><?= h($row['author']) ?></td>
              <td data-label="หมวด"><?= h($row['name']) ?></td>
              <td data-label="ว่าง/ทั้งหมด" class="num"><?= (int)$row['copies_available'] ?>/<?= (int)$row['copies_total'] ?></td>
              <td data-label="เช่า/วัน" class="num typed"><?= h(number_format($row['rent_per_day'], 2)) ?></td>
              <td data-label="มัดจำ" class="num typed"><?= h(number_format($row['deposit'], 2)) ?></td>
              <td data-label="จัดการ">
                <a class="btn btn-outline" href="book-form.php?id=<?= (int)$row['book_id'] ?>">แก้ไข</a>
                <form action="book-delete.php" method="post" style="display:inline"><?php csrf_field(); ?>
                  <input type="hidden" name="book_id" value="<?= (int)$row['book_id'] ?>">
                  <button type="submit" class="btn btn-outline">ลบ</button>
                </form>
              </td>
            </tr>
            <?php endwhile; endif; ?>
            <?php if ($count === 0): ?><tr><td colspan="7">ยังไม่มีหนังสือ กด "เพิ่มหนังสือ" ได้เลย</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    </section>
  </div>
</main>
<?php require 'includes/footer.php'; ?>

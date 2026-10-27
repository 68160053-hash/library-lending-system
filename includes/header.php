<?php
// Shared page top: <head>, night-sky background, site header + nav. Set $pageTitle before including.
// Every dynamic value goes through h() (from auth.php).
$navRole = $_SESSION['role'] ?? '';
$roleLabels = ['member' => 'สมาชิก', 'staff' => 'เจ้าหน้าที่', 'admin' => 'แอดมิน'];
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h($pageTitle ?? 'Book Lending Shop') ?> | Book Lending Shop</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Almendra+Display&amp;family=Almendra+SC&amp;family=Charm:wght@400;700&amp;family=IM+Fell+English+SC&amp;family=Special+Elite&amp;family=Srisakdi:wght@400;700&amp;family=Trirong:wght@300;400;500;700&amp;display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<a class="skip-link" href="#main">ข้ามไปยังเนื้อหา</a>
<div class="atmos" aria-hidden="true">
  <div class="stars"></div><div class="stars stars--far"></div><div class="moonbeam"></div>
  <span class="mote" style="--x:8%;--s:3px;--t:38s;--d:-4s;--dx:30px"></span>
  <span class="mote" style="--x:18%;--s:2px;--t:46s;--d:-20s;--dx:-20px"></span>
  <span class="mote" style="--x:30%;--s:3px;--t:52s;--d:-33s;--dx:40px"></span>
  <span class="mote" style="--x:42%;--s:2px;--t:41s;--d:-9s;--dx:-30px"></span>
  <span class="mote" style="--x:55%;--s:4px;--t:58s;--d:-45s;--dx:20px"></span>
  <span class="mote" style="--x:66%;--s:2px;--t:44s;--d:-15s;--dx:50px"></span>
  <span class="mote" style="--x:76%;--s:3px;--t:49s;--d:-27s;--dx:-40px"></span>
  <span class="mote" style="--x:86%;--s:2px;--t:55s;--d:-38s;--dx:25px"></span>
  <span class="mote" style="--x:93%;--s:3px;--t:40s;--d:-6s;--dx:-15px"></span>
  <span class="mote" style="--x:24%;--s:2px;--t:60s;--d:-50s;--dx:35px"></span>
  <svg class="rune" style="--x:6%;--y:30%;--s:70px" viewBox="0 0 64 64"><circle cx="32" cy="32" r="26"/><path d="M32 8 53 44H11z"/><circle cx="32" cy="35" r="3"/></svg>
  <svg class="rune" style="--x:84%;--y:18%;--s:56px;animation-delay:-20s" viewBox="0 0 64 64"><path d="M32 6 54 19v26L32 58 10 45V19z"/><path d="M32 6v52M10 19l44 26M54 19 10 45"/></svg>
  <svg class="rune" style="--x:72%;--y:68%;--s:64px;animation-delay:-40s" viewBox="0 0 64 64"><path d="M40 8a24 24 0 1 0 0 48 18 18 0 1 1 0-48z"/><path d="M18 32h14M25 25v14"/></svg>
  <svg class="rune" style="--x:20%;--y:76%;--s:60px;animation-delay:-55s" viewBox="0 0 64 64"><path d="M32 6 58 32 32 58 6 32z"/><path d="M32 18 46 32 32 46 18 32z"/><path d="M32 6v12M32 46v12"/></svg>
</div>
<header class="site-header">
  <div class="container nav-bar">
    <a class="logo" href="index.php"><svg class="logo-mark" viewBox="0 0 32 32" aria-hidden="true"><path d="M16 8C12 5.5 7 5.5 3 7v17c4-1.5 9-1.5 13 1 4-2.5 9-2.5 13-1V7c-4-1.5-9-1.5-13 1z"/><path d="M16 8v17M16 2.5v2.5M11.5 3.5l1 1.8M20.5 3.5l-1 1.8"/></svg><span class="logo-text">Book Lending Shop<small>ร้านยืมหนังสือ</small></span></a>
    <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="nav-menu" aria-label="เปิด/ปิดเมนู"><svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg></button>
    <nav aria-label="เมนูหลัก">
      <ul class="nav-menu" id="nav-menu">
        <li><a href="index.php">หนังสือทั้งหมด</a></li>
        <?php if ($navRole === ''): ?>
        <li><a href="login.php">เข้าสู่ระบบ</a></li>
        <li><a href="register.php">สมัครสมาชิก</a></li>
        <?php else: ?>
        <li><a href="my-loans.php">การยืมของฉัน</a></li>
        <?php if ($navRole === 'staff' || $navRole === 'admin'): ?>
        <li><a href="overdue.php">รายการเกินกำหนด</a></li>
        <?php endif; ?>
        <li><span class="user-name"><?= h($_SESSION['full_name'] ?? '') ?> <span class="role-badge"><?= h($roleLabels[$navRole] ?? $navRole) ?></span></span></li>
        <li>
          <?php /* logout is a POST form + CSRF token, never a plain link */ ?>
          <form action="logout.php" method="post" style="display:inline">
            <?php csrf_field(); ?>
            <button type="submit" class="btn btn-outline">ออกจากระบบ</button>
          </form>
        </li>
        <?php endif; ?>
      </ul>
    </nav>
  </div>
</header>


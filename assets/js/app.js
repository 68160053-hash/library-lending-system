// app.js : เมนูมือถือ / ประกาย / กล่องรายละเอียด / ปกเปิด+เอียง / แสงตะเกียง / กรองหนังสือ / เล่มแนะนำ / ลูกเล่นเลื่อนหน้า

// ผู้ใช้ที่ตั้งค่า "ลดการเคลื่อนไหว" จะไม่เห็นลูกเล่นเคลื่อนไหวใดๆ
var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
// เมาส์จริง (ไม่ใช่จอสัมผัส) ถึงจะเอียงปก / ดึงปุ่ม / ส่องแสงตามเคอร์เซอร์
var canHover = window.matchMedia('(hover: hover) and (pointer: fine)').matches;
function $(id) { return document.getElementById(id); }

// ----- 1) เมนูมือถือ: กดปุ่มแฮมเบอร์เกอร์ = สลับ class และ aria-expanded -----
var toggle = document.querySelector('.nav-toggle');
var menu = $('nav-menu');
if (toggle && menu) {
  toggle.addEventListener('click', function () {
    var open = menu.classList.toggle('is-open');
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
  });
}

// ----- 1.5) ฟอร์มต้นแบบ: GitHub Pages รับ POST ไม่ได้ จึงดัก submit แล้วไปหน้าถัดไปเฉยๆ (ไม่ส่งข้อมูลไปไหน) -----
document.querySelectorAll('form[data-demo-next]').forEach(function (f) {
  f.addEventListener('submit', function (e) {
    e.preventDefault();
    location.href = f.dataset.demoNext;
  });
});

// ----- 2) ประกายเวทมนตร์ (ทอง/ม่วงสลับกัน) -----
document.addEventListener('click', function (e) {
  var btn = e.target.closest('.btn-magic');
  if (!btn || btn.disabled || reduceMotion) return; // ไม่ preventDefault: ฟอร์มยัง submit ได้ปกติ

  var x = e.clientX, y = e.clientY;
  if (!x && !y) { // กดด้วยคีย์บอร์ด จะไม่มีพิกัดเมาส์ ให้ใช้กลางปุ่มแทน
    var r = btn.getBoundingClientRect();
    x = r.left + r.width / 2;
    y = r.top + r.height / 2;
  }

  // dialog อยู่ชั้นบนสุดของหน้า ประกายต้องใส่ไว้ใน dialog ไม่งั้นจะถูกบัง
  var holder = btn.closest('dialog') || document.body;
  for (var i = 0; i < 12; i++) {
    var s = document.createElement('span');
    s.className = i % 2 ? 'spark spark--violet' : 'spark';
    s.setAttribute('aria-hidden', 'true');
    var angle = (i / 12) * 2 * Math.PI;
    var dist = 30 + Math.random() * 30;
    s.style.left = x + 'px';
    s.style.top = y + 'px';
    s.style.setProperty('--dx', Math.cos(angle) * dist + 'px');
    s.style.setProperty('--dy', Math.sin(angle) * dist + 'px');
    s.addEventListener('animationend', function () { this.remove(); }); // ลบเมื่อแอนิเมชันจบ
    holder.appendChild(s);
  }
});

// ----- 3) กล่องรายละเอียด: คลิกปก = ปกเปิดออก (450ms) แล้วค่อยเปิด dialog -----
var dlg = document.querySelector('.book-dialog');
var lastBook = null;

function showBook(book) {
  book.classList.remove('is-open');
  if (dlg.open) return;
  lastBook = book; // จำเล่มล่าสุดไว้คืน focus
  var d = book.dataset;
  var left = Number(d.available);
  $('bd-title').textContent = d.title;
  $('bd-cover-title').textContent = d.title;
  $('bd-author').textContent = d.author + ' · หมวด: ' + d.category;
  $('bd-blurb').textContent = d.blurb || '';
  $('bd-stamp').textContent = left > 0 ? 'ว่าง ' + left + '/' + d.total : 'ถูกยืมหมด';
  $('bd-stamp').className = left > 0 ? 'stamp stamp--ok' : 'stamp stamp--out';
  $('bd-borrow').disabled = left === 0; // หมดแล้วยืมไม่ได้ (แต่ซื้อได้ถ้ามีราคาขาย)
  $('bd-price').hidden = $('bd-buy').hidden = !d.sale; // มีราคาขายถึงโชว์ราคาและปุ่มซื้อ
  $('bd-price').textContent = d.sale ? 'ซื้อได้ ฿' + d.sale : '';
  dlg.style.setProperty('--c', book.style.getPropertyValue('--c')); // ปกใช้สีเดียวกับเล่ม
  dlg.showModal(); // กด Esc ปิดได้เองอยู่แล้ว
}

if (dlg) {
  document.addEventListener('click', function (e) {
    var book = e.target.closest('.book');
    if (!book) return;
    if (reduceMotion) { showBook(book); return; } // ลดการเคลื่อนไหว: เปิดทันที ไม่รอปกเปิด
    book.classList.add('is-open');
    setTimeout(function () { showBook(book); }, 450);
  });
  $('bd-close').addEventListener('click', function () { dlg.close(); });
  dlg.addEventListener('click', function (e) { if (e.target === dlg) dlg.close(); }); // คลิกฉากมืดด้านนอกก็ปิด
  dlg.addEventListener('close', function () { if (lastBook) lastBook.focus(); }); // คืน focus ให้เล่มที่กด
}

// ----- 4) เอียงปกตามเมาส์ (3D): เฉพาะเมาส์จริง -----
if (canHover && !reduceMotion) {
  var tiltEvt = null;
  document.addEventListener('pointermove', function (e) {
    if (!tiltEvt) requestAnimationFrame(applyTilt); // ทำงานไม่เกิน 1 ครั้งต่อเฟรม ไม่ให้หน่วง
    tiltEvt = e;
  });
  function applyTilt() {
    var e = tiltEvt;
    tiltEvt = null;
    var b = e.target.closest('.book');
    if (b) {
      var r = b.getBoundingClientRect();
      b.style.setProperty('--ry', ((e.clientX - r.left) / r.width - 0.5) * 16 + 'deg');
      b.style.setProperty('--rx', (0.5 - (e.clientY - r.top) / r.height) * 16 + 'deg');
    }
  }
  document.addEventListener('pointerout', function (e) { // เมาส์ออกจากปก = คืนค่าเดิม
    var el = e.target.closest('.book');
    if (el && !el.contains(e.relatedTarget)) ['--rx', '--ry'].forEach(function (v) { el.style.removeProperty(v); });
  });
}

// ----- 5) หน้าร้าน: แสง Lumos ตามเคอร์เซอร์ + พารัลแลกซ์ตอนเลื่อน (ตั้งตัวแปร CSS แล้วให้ CSS ขยับ) -----
var hero = $('hero');
var shop = $('shop');
if (hero && !reduceMotion) {
  if (canHover) {
    hero.addEventListener('pointermove', function (e) {
      var r = shop.getBoundingClientRect();
      shop.style.setProperty('--mx', e.clientX - r.left + 'px');
      shop.style.setProperty('--my', e.clientY - r.top + 'px');
      shop.classList.add('is-lit');
    });
    hero.addEventListener('pointerleave', function () { shop.classList.remove('is-lit'); });
  }
  var lastSy = -1, syQueued = false;
  window.addEventListener('scroll', function () { // ตัวเลขเดียว: ยิ่งเลื่อนมาก ชั้นไกลยิ่งขยับน้อยกว่าชั้นใกล้ (ตั้งใน CSS)
    if (syQueued) return;
    syQueued = true;
    requestAnimationFrame(function () {
      syQueued = false;
      var sy = Math.min(window.scrollY, 600);
      if (sy !== lastSy) { lastSy = sy; hero.style.setProperty('--sy', sy); } // เลื่อนพ้นหน้าร้านแล้วไม่ต้องอัปเดต
    });
  }, { passive: true });
}

// ----- 6) กรองหนังสือ: ป้ายหมวด + พิมพ์ค้นหาสด + หมวดใน select -----
// หมายเหตุ: PHP จริงจะกรองด้วย GET (WHERE ... LIKE) ตรงนี้เป็นแค่ progressive enhancement
var items = Array.prototype.slice.call(document.querySelectorAll('.books li'));
var rows = document.querySelectorAll('.shelf-row');
var tabs = document.querySelectorAll('.sign-tab');
var group = 'all';
var timer;

function applyFilter() {
  var text = $('q').value.trim().toLowerCase();
  var cat = $('category').value;
  items.forEach(function (li) {
    var d = li.querySelector('.book').dataset;
    var g = li.closest('.shelf-row').dataset.group;
    var ok = (group === 'all' || (group === 'sale' ? !!d.sale : g === group)) &&
             (!cat || d.category === cat) &&
             (!text || (d.title + ' ' + d.author).toLowerCase().indexOf(text) > -1);
    if (ok) li.hidden = false;
    li.classList.toggle('is-leaving', !ok); // เล่มที่ไม่ตรง: จางหาย แล้วค่อยซ่อนจริง
  });
  clearTimeout(timer);
  timer = setTimeout(settle, reduceMotion ? 0 : 180);
}

function settle() { // ซ่อนจริงหลังจางเสร็จ แล้วอัปเดตจำนวนและข้อความแจ้ง (aria-live)
  var shown = 0;
  items.forEach(function (li) {
    li.hidden = li.classList.contains('is-leaving');
    if (!li.hidden) shown++;
  });
  rows.forEach(function (row) {
    var n = row.querySelectorAll('li:not([hidden])').length;
    row.hidden = n === 0;
    row.querySelector('.row-count').textContent = n + ' เล่ม';
  });
  $('status').textContent = shown ? 'พบหนังสือ ' + shown + ' เล่ม' : 'ยังไม่มีหนังสือที่ตรงกัน ลองคำอื่นดูนะ';
  $('status').classList.toggle('is-empty', !shown);
}

if (items.length) {
  tabs.forEach(function (tab) {
    tab.addEventListener('click', function () {
      group = tab.dataset.filter;
      tabs.forEach(function (t) { t.setAttribute('aria-pressed', t === tab ? 'true' : 'false'); });
      applyFilter();
    });
  });
  $('q').addEventListener('input', applyFilter);
  $('category').addEventListener('change', applyFilter);
  $('q').form.addEventListener('submit', function (e) { // ต้นแบบนี้กรองสดอยู่แล้ว จึงไม่ต้องโหลดหน้าใหม่
    e.preventDefault();
    applyFilter();
    $('shelves').scrollIntoView({ behavior: reduceMotion ? 'auto' : 'smooth' });
  });
}

// ----- 7) หนังสือแนะนำคืนนี้: ลูกศรสลับ 3 เล่ม (เล่มที่มี data-pick) -----
var picks = document.querySelectorAll('.book[data-pick]');
var pi = 0;
function showPick(step) {
  pi = (pi + step + picks.length) % picks.length;
  var b = picks[pi];
  var d = b.dataset;
  $('ft-title').textContent = d.title;
  $('ft-cover-title').textContent = d.title;
  $('ft-author').textContent = d.author;
  $('ft-blurb').textContent = d.blurb;
  $('ft-count').textContent = 'เล่มที่ ' + (pi + 1) + ' จาก ' + picks.length;
  $('ft-art').style.setProperty('--c', b.style.getPropertyValue('--c'));
  $('ft-use').setAttribute('href', b.querySelector('use').getAttribute('href'));
}
if (picks.length && $('ft-prev')) {
  $('ft-prev').addEventListener('click', function () { showPick(-1); });
  $('ft-next').addEventListener('click', function () { showPick(1); });
  $('ft-borrow').addEventListener('click', function () { showBook(picks[pi]); });
}

// ----- 8) ลอยขึ้นตอนเลื่อนมาถึง: IntersectionObserver ใส่ class is-in (ใช้ transform อย่างเดียว) -----
var reveals = document.querySelectorAll('.reveal');
if (reveals.length && 'IntersectionObserver' in window && !reduceMotion) {
  document.documentElement.classList.add('js'); // ถ้าไม่มี JS ไม่มี class นี้ เนื้อหาอยู่ที่เดิมเลย
  var io = new IntersectionObserver(function (entries) {
    entries.forEach(function (en) {
      if (en.isIntersecting) { en.target.classList.add('is-in'); io.unobserve(en.target); }
    });
  }, { threshold: 0.12 });
  reveals.forEach(function (el) { io.observe(el); });
}

// ----- 9) สุ่มเล่มหนึ่งให้ขยับเองทุก 6-9 วินาที -----
function wiggle() {
  var books = document.querySelectorAll('.books li:not([hidden]) .book:not(.is-out)');
  if (books.length && !document.hidden && !(dlg && dlg.open)) {
    var s = books[Math.floor(Math.random() * books.length)];
    s.classList.add('is-wiggle');
    s.addEventListener('animationend', function () { s.classList.remove('is-wiggle'); }, { once: true });
  }
  setTimeout(wiggle, 6000 + Math.random() * 3000);
}
if (!reduceMotion && document.querySelector('.book')) setTimeout(wiggle, 4000);

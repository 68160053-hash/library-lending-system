// app.js : เมนูมือถือ / ประกายตอนกดปุ่ม .btn-magic / กล่องรายละเอียดหนังสือ / สันหนังสือขยับเอง

// ผู้ใช้ที่ตั้งค่า "ลดการเคลื่อนไหว" จะไม่เห็นประกายและสันหนังสือขยับเอง
var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

// ----- 1) เมนูมือถือ: กดปุ่มแฮมเบอร์เกอร์ = สลับ class และ aria-expanded -----
var toggle = document.querySelector('.nav-toggle');
var menu = document.getElementById('nav-menu');
if (toggle && menu) {
  toggle.addEventListener('click', function () {
    var open = menu.classList.toggle('is-open');
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
  });
}

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

// ----- 3) หน้าแรก: คลิกสันหนังสือ = เปิด dialog แสดงรายละเอียดจาก data-* -----
var dlg = document.querySelector('.book-dialog');
var lastSpine = null;
function $(id) { return document.getElementById(id); }

if (dlg) {
  document.addEventListener('click', function (e) {
    var spine = e.target.closest('.spine');
    if (!spine) return;
    lastSpine = spine;
    var d = spine.dataset;
    var left = Number(d.available);

    $('bd-title').textContent = d.title;
    $('bd-cover-title').textContent = d.title;
    $('bd-author').textContent = d.author + ' · หมวด: ' + d.category;
    $('bd-blurb').textContent = d.blurb || '';
    $('bd-stamp').textContent = left > 0 ? 'ว่าง ' + left + '/' + d.total : 'ถูกยืมหมด';
    $('bd-stamp').className = left > 0 ? 'stamp stamp--ok' : 'stamp stamp--out';
    $('bd-borrow').disabled = left === 0; // หมดแล้วยืมไม่ได้
    dlg.style.setProperty('--c', spine.style.getPropertyValue('--c')); // ปกใช้สีเดียวกับสัน
    dlg.showModal(); // กด Esc ปิดได้เองอยู่แล้ว
  });

  $('bd-close').addEventListener('click', function () { dlg.close(); });
  dlg.addEventListener('click', function (e) { if (e.target === dlg) dlg.close(); }); // คลิกฉากมืดด้านนอกก็ปิด
  dlg.addEventListener('close', function () { if (lastSpine) lastSpine.focus(); }); // คืน focus ให้สันที่กด
}

// ----- 4) สุ่มสันหนังสือเล่มหนึ่งให้ขยับเองทุก 6-9 วินาที (ลูกเล่นแปลกๆ) -----
function wiggle() {
  var spines = document.querySelectorAll('.spine:not(.is-out)');
  if (spines.length && !document.hidden && !(dlg && dlg.open)) {
    var s = spines[Math.floor(Math.random() * spines.length)];
    s.classList.add('is-wiggle');
    s.addEventListener('animationend', function () { s.classList.remove('is-wiggle'); }, { once: true });
  }
  setTimeout(wiggle, 6000 + Math.random() * 3000);
}
if (!reduceMotion && document.querySelector('.spine')) setTimeout(wiggle, 4000);

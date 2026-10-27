// app.js : มีแค่ 2 อย่าง เมนูมือถือ และประกายทอง-แดงตอนกดปุ่ม .btn-magic

// ----- 1) เมนูมือถือ: กดปุ่มแฮมเบอร์เกอร์ = สลับ class และ aria-expanded -----
var toggle = document.querySelector('.nav-toggle');
var menu = document.getElementById('nav-menu');
if (toggle && menu) {
  toggle.addEventListener('click', function () {
    var open = menu.classList.toggle('is-open');
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
  });
}

// ----- 2) ประกายเวทมนตร์ -----
// ผู้ใช้ที่ตั้งค่า "ลดการเคลื่อนไหว" จะไม่เห็นเอฟเฟกต์นี้
var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

document.addEventListener('click', function (e) {
  var btn = e.target.closest('.btn-magic');
  if (!btn || btn.disabled || reduceMotion) return; // ไม่ preventDefault: ฟอร์มยัง submit ได้ปกติ

  var x = e.clientX, y = e.clientY;
  if (!x && !y) { // กดด้วยคีย์บอร์ด จะไม่มีพิกัดเมาส์ ให้ใช้กลางปุ่มแทน
    var r = btn.getBoundingClientRect();
    x = r.left + r.width / 2;
    y = r.top + r.height / 2;
  }

  for (var i = 0; i < 12; i++) {
    var s = document.createElement('span');
    s.className = i % 2 ? 'spark spark--red' : 'spark'; // สลับสีทอง/แดงเข้ม
    s.setAttribute('aria-hidden', 'true');
    var angle = (i / 12) * 2 * Math.PI;
    var dist = 30 + Math.random() * 30;
    s.style.left = x + 'px';
    s.style.top = y + 'px';
    s.style.setProperty('--dx', Math.cos(angle) * dist + 'px');
    s.style.setProperty('--dy', Math.sin(angle) * dist + 'px');
    s.addEventListener('animationend', function () { this.remove(); }); // ลบเมื่อแอนิเมชันจบ
    document.body.appendChild(s);
  }
});

// site.js : small helpers (no libraries). Runs on every page.

// small authored motion: Web Animations, skipped for people who ask for reduced motion
var calm = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
function play(el, frames, ms) {
  if (calm || !el.animate) return;
  el.animate(frames, { duration: ms, easing: 'cubic-bezier(.2,.8,.2,1)' });
}

// mobile menu
var toggle = document.querySelector('.nav-toggle');
var nav = document.getElementById('nav');
if (toggle && nav) {
  toggle.addEventListener('click', function () {
    var open = nav.classList.toggle('is-open');
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (open) play(nav, [{ opacity: 0, transform: 'translateY(-8px)' }, { opacity: 1, transform: 'none' }], 200);
  });
}

// show / hide password
document.addEventListener('click', function (e) {
  var btn = e.target.closest('[data-pw-toggle]');
  if (!btn) return;
  var input = document.getElementById(btn.dataset.pwToggle);
  var show = input.type === 'password';
  input.type = show ? 'text' : 'password';
  btn.textContent = show ? 'ซ่อน' : 'แสดง';
  btn.setAttribute('aria-pressed', show ? 'true' : 'false');
});

// ask "are you sure?" before dangerous buttons (data-confirm="message")
document.addEventListener('submit', function (e) {
  var msg = e.submitter && e.submitter.dataset.confirm;
  if (msg && !window.confirm(msg)) e.preventDefault();
});

// choosing rental days on the cart page reloads the totals (the form is a normal GET form: works without JS too)
document.querySelectorAll('[data-autosubmit]').forEach(function (el) {
  el.addEventListener('change', function () { el.form.submit(); });
});

// flash messages settle in, and the theme button turns once when pressed
document.querySelectorAll('main > .alert').forEach(function (a) {
  play(a, [{ opacity: 0, transform: 'translateY(-10px)' }, { opacity: 1, transform: 'none' }], 320);
});
document.addEventListener('click', function (e) {
  var b = e.target.closest('[data-theme-toggle]');
  var svg = b && b.querySelector('svg');
  if (svg) play(svg, [{ transform: 'rotate(-90deg) scale(.7)' }, { transform: 'none' }], 300);
});

// password strength hint (advice only, the server rules stay the same): inputs marked data-strength
document.querySelectorAll('input[data-strength]').forEach(function (input) {
  var wrap = input.closest('.field');
  var meter = document.createElement('div');
  meter.className = 'pw-meter';
  meter.innerHTML = '<span class="pw-bar"><i></i></span><b></b>';
  wrap.appendChild(meter);
  var label = meter.querySelector('b');
  var names = ['', 'สั้นไปหน่อย', 'พอใช้', 'ดี', 'แข็งแรง'];
  input.addEventListener('input', function () {
    var v = input.value, lvl = 0;
    if (v.length) {
      var kinds = /[a-z]/.test(v) + /[A-Z]/.test(v) + /[0-9]/.test(v) + /[^A-Za-z0-9]/.test(v);
      lvl = v.length < 8 ? 1 : 2 + (v.length >= 12 && kinds >= 2 ? 1 : 0) + (kinds >= 3 && v.length >= 10 ? 1 : 0);
      lvl = Math.min(lvl, 4);
    }
    meter.dataset.level = lvl;
    label.textContent = names[lvl] ? 'ความแข็งแรง: ' + names[lvl] : '';
  });
});

// the service worker and the install button live in pwa.js, loaded only where the browser supports them
if ('serviceWorker' in navigator) {
  var pwa = document.createElement('script');
  pwa.src = 'assets/js/pwa.js';
  document.body.appendChild(pwa);
}

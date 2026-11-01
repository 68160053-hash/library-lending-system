// site.js : small helpers (no libraries). Runs on every page.

// mobile menu
var toggle = document.querySelector('.nav-toggle');
var nav = document.getElementById('nav');
if (toggle && nav) {
  toggle.addEventListener('click', function () {
    var open = nav.classList.toggle('is-open');
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
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

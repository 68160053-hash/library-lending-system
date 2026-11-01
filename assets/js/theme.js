// Light / dark ("the hall by day / after closing"). Loaded in <head> so the saved choice is applied before the page paints.
(function () {
  var root = document.documentElement, saved = null;
  try { saved = localStorage.getItem('bls-theme'); } catch (e) {}
  var mq = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;
  // No saved choice: follow the OS, always through data-theme so every dark rule in app.css applies.
  root.setAttribute('data-theme', saved === 'dark' || saved === 'light' ? saved : (mq && mq.matches ? 'dark' : 'light'));
  if (mq && mq.addEventListener) mq.addEventListener('change', function (e) { if (!saved) root.setAttribute('data-theme', e.matches ? 'dark' : 'light'); });
  function isDark() {
    var t = root.getAttribute('data-theme');
    return t ? t === 'dark' : window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
  }
  document.addEventListener('click', function (e) {
    var b = e.target.closest && e.target.closest('[data-theme-toggle]');
    if (!b) return;
    var next = isDark() ? 'light' : 'dark';
    root.setAttribute('data-theme', next);
    try { localStorage.setItem('bls-theme', next); } catch (err) {}
    b.setAttribute('aria-pressed', next === 'dark' ? 'true' : 'false');
  });
  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-theme-toggle]').forEach(function (b) { b.setAttribute('aria-pressed', isDark() ? 'true' : 'false'); });
  });
})();

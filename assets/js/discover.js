// discover.js: type-ahead for search boxes (input[data-suggest]) and no-reload wishlist hearts. Same-origin only.
(function () {
  'use strict';

  // ---- type-ahead: accessible combobox + listbox ----
  var uid = 0;
  document.querySelectorAll('input[data-suggest]').forEach(function (input) {
    var id = 'sg' + (++uid), list = document.createElement('ul'), status = document.createElement('div');
    var items = [], active = -1, timer = null, ctrl = null;
    list.id = id; list.className = 'suggest'; list.setAttribute('role', 'listbox'); list.setAttribute('aria-label', 'หนังสือที่ตรงกัน'); list.hidden = true;
    status.className = 'sr-only'; status.setAttribute('role', 'status');
    input.setAttribute('role', 'combobox'); input.setAttribute('aria-autocomplete', 'list'); input.setAttribute('aria-expanded', 'false'); input.setAttribute('aria-controls', id);
    input.parentNode.appendChild(list); input.parentNode.appendChild(status);

    function close() { list.hidden = true; input.setAttribute('aria-expanded', 'false'); input.removeAttribute('aria-activedescendant'); active = -1; }
    function mark(i) {
      var nodes = list.children;
      if (active >= 0 && nodes[active]) nodes[active].setAttribute('aria-selected', 'false');
      active = i;
      if (i >= 0 && nodes[i]) { nodes[i].setAttribute('aria-selected', 'true'); input.setAttribute('aria-activedescendant', nodes[i].id); nodes[i].scrollIntoView({ block: 'nearest' }); }
      else input.removeAttribute('aria-activedescendant');
    }
    function go(i) { if (items[i]) window.location.href = 'book.php?id=' + encodeURIComponent(items[i].id); }
    function show(data) {
      items = data; list.textContent = ''; active = -1;
      if (!data.length) { close(); status.textContent = ''; return; }
      data.forEach(function (b, i) {
        var li = document.createElement('li'), t = document.createElement('span'), s = document.createElement('span');
        li.id = id + '-' + i; li.setAttribute('role', 'option'); li.setAttribute('aria-selected', 'false');
        t.className = 's-title'; t.textContent = b.title; s.className = 's-sub'; s.textContent = b.author;
        li.appendChild(t); li.appendChild(s);
        li.addEventListener('mousedown', function (e) { e.preventDefault(); go(i); });
        list.appendChild(li);
      });
      list.hidden = false; input.setAttribute('aria-expanded', 'true');
      status.textContent = 'พบ ' + data.length + ' รายการ ใช้ลูกศรขึ้นลงเพื่อเลือก';
    }
    function ask() {
      var q = input.value.trim();
      if (q.length < 2) { close(); return; }
      if (ctrl) ctrl.abort();
      ctrl = window.AbortController ? new AbortController() : null;
      fetch('suggest.php?q=' + encodeURIComponent(q.slice(0, 60)), { credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' }, signal: ctrl ? ctrl.signal : undefined })
        .then(function (r) { return r.ok ? r.json() : []; })
        .then(function (d) { if (input.value.trim() === q && document.activeElement === input) show(Array.isArray(d) ? d : []); })
        .catch(function () {});
    }
    input.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(ask, 200); });
    input.addEventListener('blur', function () { setTimeout(close, 120); });
    input.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') { if (!list.hidden) { close(); e.preventDefault(); } return; }
      if (list.hidden || !items.length) return;
      if (e.key === 'ArrowDown') { e.preventDefault(); mark(active + 1 >= items.length ? 0 : active + 1); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); mark(active <= 0 ? items.length - 1 : active - 1); }
      else if (e.key === 'Enter' && active >= 0) { e.preventDefault(); go(active); }
    });
  });

  // ---- wishlist heart: toggle without reload; the plain form still works without JavaScript ----
  document.addEventListener('submit', function (e) {
    var form = e.target.closest ? e.target.closest('form.heart-form') : null;
    if (!form || !window.fetch) return;
    e.preventDefault();
    var btn = form.querySelector('.heart-btn');
    fetch(form.action, { method: 'POST', body: new FormData(form), credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } })
      .then(function (r) { if (!r.ok) throw new Error('x'); return r.json(); })
      .then(function (d) {
        var on = !!d.wished;
        btn.setAttribute('aria-pressed', on ? 'true' : 'false');
        btn.setAttribute('aria-label', btn.getAttribute(on ? 'data-t-on' : 'data-t-off'));
        var use = btn.querySelector('use');
        if (use) use.setAttribute('href', 'assets/icons.svg#' + (on ? 'heart-fill' : 'heart'));
        if (!on && form.hasAttribute('data-remove')) {
          var card = form.closest('.book-card'); if (card) card.remove();
          if (!document.querySelector('.book-card')) window.location.reload();
        }
      })
      .catch(function () { form.submit(); });
  });
})();

// Book page: the rental length picked in the tiles is also sent by the phone bar's add-to-cart form.
(function () {
  var follow = document.querySelectorAll('[data-days-follow]');
  if (!follow.length) return;
  document.addEventListener('change', function (e) {
    if (!e.target.matches || !e.target.matches('[data-plan]')) return;
    follow.forEach(function (f) { f.value = e.target.value; });
  });
})();

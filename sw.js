// Service worker: static files cache-first, pages network-first with an offline page. HTML is never cached (it can hold a session).
var CACHE = 'bls-static-v3';
var PRECACHE = ['offline.html', 'assets/css/app.css', 'assets/icons.svg', 'assets/icons/icon-192.png'];
var STATIC = /\/assets\/(css|js|icons|covers|fonts)\/|\/assets\/icons\.svg$/;

self.addEventListener('install', function (e) {
  e.waitUntil(caches.open(CACHE).then(function (c) { return c.addAll(PRECACHE); }).then(function () { return self.skipWaiting(); }));
});

self.addEventListener('activate', function (e) {
  e.waitUntil(caches.keys().then(function (keys) {
    return Promise.all(keys.filter(function (k) { return k !== CACHE; }).map(function (k) { return caches.delete(k); }));
  }).then(function () { return self.clients.claim(); }));
});

self.addEventListener('fetch', function (e) {
  var req = e.request, url = new URL(req.url);
  if (req.method !== 'GET' || url.origin !== self.location.origin) return; // never touch POSTs or other sites
  if (req.mode === 'navigate') {
    e.respondWith(fetch(req).catch(function () { return caches.match('offline.html'); }));
    return;
  }
  if (STATIC.test(url.pathname)) {
    e.respondWith(caches.match(req).then(function (hit) {
      return hit || fetch(req).then(function (res) {
        if (res.ok) {
          var copy = res.clone();
          caches.open(CACHE).then(function (c) {
            c.put(req, copy);
            // each release has a new ?v= address: drop older copies of the same file so the cache does not grow for ever
            c.keys().then(function (ks) { ks.forEach(function (k) { var u = new URL(k.url); if (u.pathname === url.pathname && u.search !== url.search) c.delete(k); }); });
          });
        }
        return res;
      });
    }));
  }
});

// pwa.js: registers the service worker and offers an "install app" button when the browser allows it.
(function () {
  if ('serviceWorker' in navigator && location.protocol !== 'file:') {
    window.addEventListener('load', function () { navigator.serviceWorker.register('sw.js').catch(function () {}); });
  }
  var deferred = null, btn = null;
  window.addEventListener('beforeinstallprompt', function (e) {
    e.preventDefault(); deferred = e;
    var host = document.querySelector('.footer-in > div');
    if (!host || btn) return;
    btn = document.createElement('button');
    btn.type = 'button'; btn.className = 'btn btn-yellow btn-sm'; btn.textContent = 'ติดตั้งแอป';
    btn.style.marginTop = '.5rem';
    btn.addEventListener('click', function () {
      if (!deferred) return;
      deferred.prompt(); deferred = null; btn.hidden = true;
    });
    host.appendChild(btn);
  });
  window.addEventListener('appinstalled', function () { if (btn) btn.hidden = true; });
})();

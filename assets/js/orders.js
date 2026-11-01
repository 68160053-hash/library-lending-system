// Orders pages: draw the PromptPay QR from data-payload (qrcode.js), let people save it, recalc checkout when the days change.
(function () {
  var cv = document.querySelector('canvas.qr[data-payload]');
  if (cv && window.qrcode) {
    var qr = window.qrcode(0, 'M');
    qr.addData(cv.getAttribute('data-payload'));
    qr.make();
    var n = qr.getModuleCount(), quiet = 4, cell = 6, size = (n + quiet * 2) * cell;
    cv.width = size; cv.height = size;
    var ctx = cv.getContext('2d');
    ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, size, size);
    ctx.fillStyle = '#000';
    for (var r = 0; r < n; r++) for (var c = 0; c < n; c++) if (qr.isDark(r, c)) ctx.fillRect((c + quiet) * cell, (r + quiet) * cell, cell, cell);
    var save = document.querySelector('[data-qr-save]');
    if (save) {
      save.hidden = false;
      save.addEventListener('click', function () {
        var a = document.createElement('a');
        a.href = cv.toDataURL('image/png'); a.download = 'promptpay-qr.png';
        document.body.appendChild(a); a.click(); a.remove();
      });
    }
  }
  // checkout: changing the rental days recalculates the price (the "apply coupon" button posts the same form)
  var apply = document.querySelector('button[name="do"][value="apply"]');
  document.querySelectorAll('input[data-recalc]').forEach(function (el) {
    el.addEventListener('change', function () { if (apply && el.form.requestSubmit) el.form.requestSubmit(apply); });
  });
})();

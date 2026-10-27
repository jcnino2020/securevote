(function () {
  'use strict';
  document.addEventListener('DOMContentLoaded', function () {
    var code = document.getElementById('verification-code');
    if (code) code.addEventListener('input', function () {
      code.value = code.value.replace(/[^0-9]/g, '').slice(0, 6);
    });

    var resend = document.querySelector('[data-resend-code]');
    if (!resend) return;
    var remaining = Math.max(0, parseInt(resend.dataset.wait || '0', 10) || 0);
    if (!remaining) return;
    var originalLabel = resend.textContent;
    var countdownLabel = resend.dataset.countdownLabel || 'Resend in %d seconds';
    function update() {
      resend.disabled = remaining > 0;
      resend.textContent = remaining > 0 ? countdownLabel.replace('%d', String(remaining)) : originalLabel;
    }
    update();
    var timer = window.setInterval(function () {
      remaining--;
      update();
      if (remaining <= 0) window.clearInterval(timer);
    }, 1000);
  });
}());

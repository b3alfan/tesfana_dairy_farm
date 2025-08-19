 (function (Drupal) {
  'use strict';

  function updateTotal() {
    var am = parseFloat(document.querySelector('[data-am]')?.value || '0') || 0;
    var pm = parseFloat(document.querySelector('[data-pm]')?.value || '0') || 0;
    var totalEl = document.querySelector('[data-total]');
    if (totalEl) totalEl.textContent = (am + pm).toFixed(2);
  }

  Drupal.behaviors.tesfanaQuickMilk = {
    attach: function (context) {
      var am = context.querySelector?.('[data-am]');
      var pm = context.querySelector?.('[data-pm]');
      if (am) am.addEventListener('input', updateTotal);
      if (pm) pm.addEventListener('input', updateTotal);
      // initial
      updateTotal();
    }
  };
})(Drupal);

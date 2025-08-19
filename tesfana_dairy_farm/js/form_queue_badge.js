(function (Drupal) {
  'use strict';

  Drupal.behaviors.tesfanaFormQueueBadge = {
    attach: function (context) {
      var onceMarker = 'tesfana-form-queue-badge-init';
      if (context[onceMarker]) return;
      context[onceMarker] = true;

      try {
        var keys = Object.keys(localStorage || {});
        // Heuristics for your existing queue keys; adjust if needed.
        var queueKeys = keys.filter(function (k) {
          return k.indexOf('tesfana_form_queue') === 0 || k.indexOf('formQueue') === 0;
        });

        var total = 0;
        queueKeys.forEach(function (k) {
          try {
            var arr = JSON.parse(localStorage.getItem(k) || '[]');
            if (Array.isArray(arr)) total += arr.length;
          } catch (e) {
            // Not JSON array? Count as 1.
            total += 1;
          }
        });

        var nodes = (context.querySelectorAll ? context.querySelectorAll('[data-form-queue-badge]') : []);
        if (!nodes || !nodes.length) return;

        Array.prototype.forEach.call(nodes, function (el) {
          el.textContent = String(total);
          if (total > 0) {
            el.classList.add('has-queue');
          } else {
            el.classList.remove('has-queue');
          }
        });
      } catch (e) {
        // Soft fail; no console noise in production.
      }
    }
  };
})(Drupal);

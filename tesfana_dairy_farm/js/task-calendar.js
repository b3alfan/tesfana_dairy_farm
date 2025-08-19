(function (Drupal, drupalSettings) {
  Drupal.behaviors.tesfanaCalendar = {
    attach: function (context) {
      const els = context.querySelectorAll('#tesfana-calendar');
      if (!els.length || typeof FullCalendar === 'undefined') return;

      const events = (drupalSettings.tesfana && drupalSettings.tesfana.calendar && drupalSettings.tesfana.calendar.events) || [];
      const colors = (drupalSettings.tesfana && drupalSettings.tesfana.calendar && drupalSettings.tesfana.calendar.colors) || {};

      els.forEach(function (el) {
        if (el.dataset.fcRendered) return;
        el.dataset.fcRendered = '1';

        const calendar = new FullCalendar.Calendar(el, {
          initialView: 'dayGridMonth',
          headerToolbar: {
            start: 'title',
            center: '',
            end: 'prev,next today',
          },
          events: events.map(function (e) {
            // Accept either {title,start,bundle} or minimal {title,start}.
            const bundle = (e.bundle || '').toLowerCase();
            const color = colors[bundle] || colors.__default || '#64748b';
            return {
              title: e.title,
              start: e.start,
              end: e.end || null,
              backgroundColor: color,
              borderColor: color,
              textColor: '#fff',
              extendedProps: { bundle: bundle }
            };
          }),
          eventDidMount: function (info) {
            // If no bundle passed, try to infer from title keywords (fallback).
            if (!info.event.extendedProps.bundle) {
              const t = (info.event.title || '').toLowerCase();
              let guess = null;
              if (t.includes('milk')) guess = 'milk';
              else if (t.includes('bcs') || t.includes('observe')) guess = 'observation';
              else if (t.includes('lab') || t.includes('quality') || t.includes('scc')) guess = 'lab_test';
              else if (t.includes('task') || t.includes('check')) guess = 'activity';
              if (guess) {
                const c = colors[guess] || colors.__default || '#64748b';
                info.el.style.backgroundColor = c;
                info.el.style.borderColor = c;
                info.el.style.color = '#fff';
              }
            }
          }
        });

        calendar.render();
      });
    }
  };
})(Drupal, drupalSettings);

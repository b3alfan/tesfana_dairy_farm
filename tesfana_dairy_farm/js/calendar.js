(function (Drupal, drupalSettings) {
  'use strict';

  const ensureFullCalendar = () =>
    new Promise((resolve) => {
      if (window.FullCalendar) return resolve();

      const css = document.createElement('link');
      css.rel = 'stylesheet';
      css.href = 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css';
      document.head.appendChild(css);

      const s = document.createElement('script');
      s.src = 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js';
      s.onload = () => resolve();
      document.head.appendChild(s);
    });

  function renderCalendar() {
    const el = document.getElementById('task-calendar');
    if (!el) return;

    const ds = (drupalSettings && drupalSettings.tesfana && drupalSettings.tesfana.dashboard) || {};
    const events = Array.isArray(ds.calendarEvents) ? ds.calendarEvents : [];

    const calendar = new window.FullCalendar.Calendar(el, {
      initialView: 'dayGridMonth',
      height: 340,
      headerToolbar: {
        left: 'prev,next today',
        center: 'title',
        right: ''
      },
      events: events
    });
    calendar.render();
  }

  Drupal.behaviors.tesfanaCalendar = {
    attach: function () {
      ensureFullCalendar().then(renderCalendar);
    }
  };

})(Drupal, drupalSettings);

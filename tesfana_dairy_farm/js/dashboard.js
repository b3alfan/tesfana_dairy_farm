(function (Drupal, drupalSettings) {
  function loadScript(src) {
    return new Promise(function (resolve, reject) {
      var s = document.createElement('script');
      s.src = src; s.async = true; s.onload = resolve; s.onerror = reject;
      document.head.appendChild(s);
    });
  }

  async function ensureApexCharts(dsVendors) {
    if (window.ApexCharts) return true;
    try {
      if (dsVendors && dsVendors.apexcharts) {
        await loadScript(dsVendors.apexcharts);
        if (window.ApexCharts) return true;
      }
    } catch (e) {}
    try {
      await loadScript('https://cdn.jsdelivr.net/npm/apexcharts@3.46.0/dist/apexcharts.min.js');
      return !!window.ApexCharts;
    } catch (e) {
      return false;
    }
  }

  async function ensureFullCalendar(dsVendors) {
    if (window.FullCalendar) return true;
    try {
      if (dsVendors && dsVendors.fullcalendar) {
        await loadScript(dsVendors.fullcalendar);
        if (window.FullCalendar) return true;
      }
    } catch (e) {}
    try {
      await loadScript('https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js');
      return !!window.FullCalendar;
    } catch (e) {
      return false;
    }
  }

  Drupal.behaviors.tesfanaDashboard = {
    attach: async function (context) {
      const onceKey = 'tesfana-dashboard-init';
      if (context.body && context.body.dataset[onceKey]) return;
      if (context.body) context.body.dataset[onceKey] = '1';

      const ds = (drupalSettings && drupalSettings.tesfana && drupalSettings.tesfana.dashboard) || {};
      const vendors = ds.vendors || {};

      // --- Chart ---
      const chartEl = document.querySelector('#milk-chart');
      const chartData = Array.isArray(ds.milkChart) ? ds.milkChart.map(r => ({ x: r.date, y: Number(r.total || 0) })) : [];
      if (chartEl) {
        if (await ensureApexCharts(vendors)) {
          const chart = new ApexCharts(chartEl, {
            chart: { type: 'line', height: 280, animations: { enabled: true } },
            series: [{ name: 'Total Output (L)', data: chartData }],
            xaxis: { type: 'category', labels: { rotate: -45 } },
            yaxis: { min: 0 },
            stroke: { width: 3 },
            dataLabels: { enabled: false },
            noData: { text: 'No milk data' }
          });
          chart.render();
        } else {
          chartEl.innerHTML = '<div class="chart-fallback">Chart library not available.</div>';
        }
      }

      // --- Tasks (calendar if lib exists; otherwise list) ---
      const tasks = Array.isArray(ds.tasks) ? ds.tasks : [];
      const calEl = document.querySelector('#task-calendar');
      if (calEl) {
        if (tasks.length && await ensureFullCalendar(vendors)) {
          const events = tasks.map(t => ({
            title: t.title || '',
            start: t.start ? new Date(t.start * 1000) : undefined,
            end:   t.end   ? new Date(t.end   * 1000) : undefined,
          }));
          const calendar = new FullCalendar.Calendar(calEl, { initialView: 'dayGridMonth', events });
          calendar.render();
        } else {
          calEl.innerHTML = '<h3>Upcoming Tasks</h3>';
          if (!tasks.length) {
            calEl.innerHTML += '<p class="muted">No tasks available.</p>';
          } else {
            const ul = document.createElement('ul');
            ul.className = 'task-fallback-list';
            tasks.forEach((t) => {
              const li = document.createElement('li');
              const start = t.start ? new Date(t.start * 1000).toLocaleString() : '';
              li.innerHTML = `<strong>${t.title || ''}</strong> <em>${start}</em> <span>${t.status || ''}</span>`;
              ul.appendChild(li);
            });
            calEl.appendChild(ul);
          }
        }
      }
    }
  };
})(Drupal, drupalSettings);

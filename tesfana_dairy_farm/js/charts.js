(function (Drupal, drupalSettings) {
  'use strict';

  const ensureApex = () =>
    new Promise((resolve) => {
      if (window.ApexCharts) return resolve();
      const s = document.createElement('script');
      s.src = 'https://cdn.jsdelivr.net/npm/apexcharts';
      s.onload = () => resolve();
      document.head.appendChild(s);
    });

  function renderMilkChart() {
    const target = document.getElementById('milk-output-chart');
    if (!target) return;

    const ds = (drupalSettings && drupalSettings.tesfana && drupalSettings.tesfana.dashboard) || {};
    const pairs = Array.isArray(ds.milkChartXY) ? ds.milkChartXY : null;
    const simple = Array.isArray(ds.milkChart) ? ds.milkChart : null;

    let categories = [];
    let series = [];

    if (pairs && pairs.length) {
      categories = pairs.map(p => p.x);
      series = [{ name: 'Milk (L)', data: pairs.map(p => p.y) }];
    } else if (simple && simple.length) {
      categories = simple.map((_, i) => String(i + 1));
      series = [{ name: 'Milk (L)', data: simple }];
    } else {
      target.innerHTML = '<div class="chart-fallback">No milk data yet.</div>';
      return;
    }

    const options = {
      chart: { type: 'area', height: 340, toolbar: { show: false } },
      stroke: { curve: 'smooth', width: 2 },
      dataLabels: { enabled: false },
      xaxis: { categories },
      series,
      tooltip: { y: { formatter: (v) => `${v} L` } },
      fill: { opacity: 0.25 }
    };

    const chart = new window.ApexCharts(target, options);
    chart.render();
  }

  Drupal.behaviors.tesfanaCharts = {
    attach: function () {
      ensureApex().then(renderMilkChart);
    }
  };

})(Drupal, drupalSettings);

(function (Drupal, drupalSettings) {
  function renderReportChart(node, series) {
    if (!node || typeof ApexCharts === 'undefined') return;
    const categories = (series || []).map(d => d.date);
    const values = (series || []).map(d => d.total);
    const opts = {
      chart: { type: 'area', height: 320, toolbar: { show: false } },
      series: [{ name: 'Milk (L)', data: values }],
      xaxis: { categories, tickAmount: Math.min(10, categories.length) },
      yaxis: { min: 0, forceNiceScale: true },
      dataLabels: { enabled: false },
      stroke: { width: 2 },
      fill: { opacity: 0.2 },
      noData: { text: 'No data.' }
    };
    const chart = new ApexCharts(node, opts);
    chart.render();
  }

  Drupal.behaviors.tesfanaReportCharts = {
    attach: function (context) {
      const el = context.querySelector('#report-milk-chart[data-chart="report-milk"]');
      if (!el || !drupalSettings.tesfana || !drupalSettings.tesfana.reports) return;
      renderReportChart(el, drupalSettings.tesfana.reports.series || []);
    }
  };
})(Drupal, drupalSettings);

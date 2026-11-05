(function () {
  if (!window.Chart || !window.CHARTS) return;
  var C = window.CHARTS, dark = window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches;
  var text = dark ? '#93a0b8' : '#6b7385', grid = dark ? '#263149' : '#e3e8f0';
  var palette = ['#309afd', '#16a34a', '#f59e0b', '#ef4444', '#8b5cf6', '#14b8a6', '#ec4899', '#64748b'];
  Chart.defaults.color = text;
  new Chart(document.getElementById('cDay'), {
    type: 'line',
    data: {labels: C.labels, datasets: [
      {label: 'Clicks', data: C.clicks, borderColor: '#309afd', backgroundColor: 'rgba(48,154,253,.15)', fill: true, tension: .3},
      {label: 'Unique', data: C.uniques, borderColor: '#16a34a', tension: .3}
    ]},
    options: {maintainAspectRatio: false, scales: {y: {beginAtZero: true, ticks: {precision: 0}, grid: {color: grid}}, x: {grid: {display: false}}}}
  });
  [['cDevice', 'device'], ['cBrowser', 'browser'], ['cOs', 'os']].forEach(function (p) {
    var d = C[p[1]], el = document.getElementById(p[0]);
    if (!d.labels.length) { el.parentNode.innerHTML = '<p class="muted">Abhi data nahi hai.</p>'; return; }
    new Chart(el, {type: 'doughnut', data: {labels: d.labels, datasets: [{data: d.data, backgroundColor: palette, borderWidth: 0}]},
      options: {maintainAspectRatio: false, plugins: {legend: {position: 'bottom', labels: {boxWidth: 10}}}}});
  });
})();

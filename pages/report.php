<?php
$key = preg_replace('/[^a-z_]/', '', (string)($_GET['r'] ?? ''));
$def = App\Reports\Catalog::get($key);
if (!$def) { echo '<div class="card"><div class="card-body empty"><i class="bi bi-search"></i>Report not found. <a href="index.php?p=reports">All reports</a></div></div>'; return; }
if (!empty($def['financial']) && !can('reports.financial')) { require __DIR__ . '/403.php'; return; }
$pageTitle = $def['title'];
$f = $def['filters'];
?>
<div class="page-head">
  <div class="title"><a href="index.php?p=reports" class="btn btn-light btn-icon me-1"><i class="bi bi-arrow-left"></i></a><div class="ico"><i class="bi <?= e($def['icon']) ?>"></i></div>
    <div><h2><?= e($def['title']) ?></h2><div class="sub"><?= e($def['desc']) ?></div></div></div>
  <div class="actions">
    <button class="btn btn-light" id="csvBtn"><i class="bi bi-filetype-csv me-1"></i>Export CSV</button>
    <a class="btn btn-green" id="printBtn" target="_blank" href="#"><i class="bi bi-printer me-1"></i>Print / PDF</a>
  </div>
</div>
<?php if ($f): ?>
<div class="card mb-3"><div class="card-body py-2"><form class="row g-2 align-items-end" id="rf">
  <?php if (in_array('date', $f, true)): ?>
    <div class="col-6 col-md-2"><label class="form-label">From</label><input type="date" class="form-control form-control-sm" name="from" value="<?= date('Y-01-01') ?>"></div>
    <div class="col-6 col-md-2"><label class="form-label">To</label><input type="date" class="form-control form-control-sm" name="to" value="<?= date('Y-m-d') ?>"></div>
  <?php endif; ?>
  <?php foreach (['customer' => ['customer_id', 'customers', 'Customer'], 'farm' => ['farm_id', 'farms', 'Farm'], 'agent' => ['agent_id', 'shipping_agents', 'Shipping agent']] as $k => [$name, $src, $label]): if (in_array($k, $f, true)): ?>
    <div class="col-12 col-md-3"><label class="form-label"><?= $label ?></label><select class="form-select form-select-sm" name="<?= $name ?>" data-src="<?= $src ?>"></select></div>
  <?php endif; endforeach; ?>
  <div class="col-12 col-md-auto"><button class="btn btn-primary btn-sm"><i class="bi bi-play-fill me-1"></i>Run</button></div>
</form></div></div>
<?php endif; ?>
<div class="card mb-3 d-none" id="chartCard"><div class="card-body"><div style="height:320px"><canvas id="rChart"></canvas></div></div></div>
<div class="card"><div class="card-body" id="rBody"><?= str_repeat('<div class="skeleton mb-2" style="height:30px"></div>', 6) ?></div></div>
<?php ob_start(); ?>
(function () {
  const { get, esc, fmt, select2, table, stagePill, palette } = App;
  const KEY = <?= json_encode($key) ?>;
  $('#rf [data-src]').each(function () { select2(this, $(this).data('src'), { placeholder: 'All' }); });
  let chart = null, last = null;
  const f = (t, v) => {
    if (v === null || v === undefined || v === '') return '';
    switch (t) { case 'money': return fmt.money(v); case 'money4': return fmt.money(v, 4); case 'int': return fmt.int(v); case 'qty': return fmt.qty(v);
      case 'pct': return fmt.pct(+v); case 'date': return fmt.date(v); case 'rate': return fmt.rate(v); case 'stage': return stagePill(v); default: return esc(v); }
  };
  const params = () => Object.fromEntries(new FormData(document.getElementById('rf') || document.createElement('form')).entries());
  async function run() {
    $('#rBody').html(App.loader('Running report…'));
    const p = params();
    $('#printBtn').attr('href', 'print.php?' + new URLSearchParams(Object.assign({ doc: 'report', key: KEY }, p)));
    const d = last = await get('reports.run', Object.assign({ key: KEY }, p), { quiet: true });
    const num = (c) => ['money', 'money4', 'int', 'qty', 'pct', 'rate'].includes(c[2]);
    $('#rBody').html(`<table class="table table-hover table-sm w-100" id="rTable"><thead><tr>${d.columns.map(c => `<th class="${num(c) ? 'num' : ''}">${esc(c[1])}</th>`).join('')}</tr></thead>
      ${d.totals.length ? `<tfoot><tr>${d.columns.map((c, i) => `<th class="${num(c) ? 'num' : ''}">${d.totals.includes(c[0]) ? f(c[2], d.rows.reduce((s, r) => s + (+r[c[0]] || 0), 0)) : (i === 0 ? 'Total' : '')}</th>`).join('')}</tr></tfoot>` : ''}</table>`);
    table('#rTable', {
      data: d.rows, pageLength: 50,
      columns: d.columns.map(c => ({ data: c[0], defaultContent: '', className: num(c) ? 'num' : '',
        render: (v, t, r) => t === 'display' ? (d.link[c[0]] === 'order' && r.order_no ? `<a href="index.php?p=order_journey&no=${encodeURIComponent(v)}">${esc(v)}</a>` : f(c[2], v)) : v }))
    });
    // chart
    if (chart) { chart.destroy(); chart = null; }
    if (d.chart && d.rows.length) {
      const [type, labelCol, valueCols, limit, stacked] = d.chart;
      let rows = d.rows; if (limit) rows = rows.slice(0, limit);
      const labelOf = (r) => { const c = d.columns.find(x => x[0] === labelCol); return c && c[2] === 'stage' ? ((App.MG.stages[r[labelCol]] || [r[labelCol]])[0]) : (c && c[2] === 'date' ? fmt.date(r[labelCol]) : r[labelCol]); };
      const titleOf = (k) => (d.columns.find(c => c[0] === k) || [k, k])[1];
      $('#chartCard').removeClass('d-none');
      chart = new Chart(document.getElementById('rChart'), {
        type, data: { labels: rows.map(labelOf), datasets: valueCols.map((k, i) => ({ label: titleOf(k), data: rows.map(r => +r[k] || 0),
          backgroundColor: type === 'doughnut' ? palette : palette[i % palette.length] + (type === 'line' ? '22' : 'cc'), borderColor: type === 'doughnut' ? '#fff' : palette[i % palette.length],
          borderWidth: type === 'line' ? 2.5 : (type === 'doughnut' ? 2 : 0), borderRadius: type === 'bar' ? 6 : 0, fill: type === 'line', tension: .35, pointRadius: type === 'line' ? 3 : 0 })) },
        options: { plugins: { legend: { display: valueCols.length > 1 || type === 'doughnut', position: 'bottom' } },
          scales: type === 'doughnut' ? {} : { x: { stacked: !!stacked, grid: { display: false } }, y: { stacked: !!stacked, beginAtZero: true, ticks: { callback: (v) => fmt.qty(v) } } } }
      });
    } else $('#chartCard').addClass('d-none');
  }
  $('#rf').on('submit', (e) => { e.preventDefault(); run(); });
  $('#csvBtn').on('click', () => {
    if (!last) return;
    const q = (v) => '"' + String(v ?? '').replace(/"/g, '""') + '"';
    const csv = [last.columns.map(c => q(c[1])).join(','), ...last.rows.map(r => last.columns.map(c => q(r[c[0]])).join(','))].join('\r\n');
    const a = document.createElement('a'); a.href = URL.createObjectURL(new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8' }));
    a.download = KEY + '_' + App.today() + '.csv'; a.click(); URL.revokeObjectURL(a.href);
  });
  run();
})();
<?php $pageInline = ob_get_clean(); ?>

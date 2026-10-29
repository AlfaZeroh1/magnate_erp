<?php
$jid = (int)($_GET['id'] ?? 0);
if (!$jid && !empty($_GET['no'])) $jid = (int)App\Core\DB::val('SELECT id FROM orders WHERE order_no = :n', ['n' => (string)$_GET['no']]);
$jlabel = $jid ? App\Core\DB::val("SELECT CONCAT(o.order_no, ' · ', c.name) FROM orders o JOIN customers c ON c.id = o.customer_id WHERE o.id = :id", ['id' => $jid]) : null;
?>
<div class="page-head">
  <div class="title"><a href="index.php?p=reports" class="btn btn-light btn-icon me-1"><i class="bi bi-arrow-left"></i></a><div class="ico"><i class="bi bi-signpost-split"></i></div>
    <div><h2>Order Journey</h2><div class="sub">The full story of one order — steps, quantities, money and time.</div></div></div>
  <div class="actions" style="min-width:min(100%,360px)">
    <select class="form-select" id="jOrder"></select>
    <a class="btn btn-green d-none" id="jPrint" target="_blank"><i class="bi bi-printer"></i></a>
  </div>
</div>
<div id="jBody"><div class="card"><div class="card-body empty"><i class="bi bi-signpost-split"></i>Choose an order to see its journey.</div></div></div>
<?php ob_start(); ?>
(function () {
  const { get, esc, fmt, num, select2, setSelect, stagePill, palette, MG } = App;
  const B = MG.base.code;
  const charts = [];
  const $s = $('#jOrder'); select2($s, 'orders', { placeholder: 'Search an order number or customer…' });
  $s.on('change', () => { if ($s.val()) load($s.val()); });

  async function load(id) {
    history.replaceState(null, '', 'index.php?p=order_journey&id=' + id);
    $('#jPrint').removeClass('d-none').attr('href', 'print.php?doc=journey&id=' + id);
    $('#jBody').html(App.loader('Tracing the journey…'));
    const d = await get('reports.journey', { id }, { quiet: true });
    charts.forEach(c => c.destroy()); charts.length = 0;
    const o = d.order, m = d.financials, q = m.qty;
    const invoiced = ['invoiced', 'closed'].includes(o.stage) ? q.received : 0;
    const steps = [['Ordered', q.ordered], ['Asked of farms', q.allocated], ['Confirmed', q.confirmed], ['Received', q.received], ['Invoiced', invoiced]];
    const max = Math.max(1, ...steps.map(s => num(s[1])));
    const farms = d.farm_orders;

    $('#jBody').html(`
      <div class="card mb-3 fade-in"><div class="card-body d-flex flex-wrap gap-3 align-items-center">
        <div class="me-auto"><div class="d-flex align-items-center gap-2"><h3 class="h4 fw-bold mb-0">${esc(o.order_no)}</h3>${stagePill(o.stage)}</div>
          <div class="small text-secondary mt-1">${esc(o.customer_name)} · ordered ${fmt.date(o.order_date)} · departs ${fmt.date(o.departure_date)} · ${esc(o.currency_code)} · ${esc(o.shipping_agent || 'no agent')}</div></div>
        <a class="btn btn-light" href="index.php?p=order&id=${o.id}"><i class="bi bi-box-arrow-up-right me-1"></i>Open order</a>
      </div></div>

      <div class="row g-3 mb-3">
        <div class="col-lg-7"><div class="card h-100"><div class="card-header"><h3><i class="bi bi-stars text-primary me-1"></i>What happened</h3></div><div class="card-body">
          ${d.insights.map(i => `<div class="insight ${i.level}"><i class="bi ${i.icon}"></i><div>${esc(i.text)}</div></div>`).join('') || '<div class="text-secondary small">Not enough activity yet.</div>'}
        </div></div></div>
        <div class="col-lg-5"><div class="card h-100"><div class="card-header"><h3><i class="bi bi-funnel me-1"></i>Quantity funnel (stems)</h3></div><div class="card-body funnel">
          ${steps.map(([l, v]) => `<div class="row-f"><span class="text-secondary">${l}</span><div><div class="bar" style="width:${Math.max(4, num(v) / max * 100)}%">${num(v) ? fmt.int(v) : ''}</div></div><span class="text-end small">${q.ordered ? fmt.pct(num(v) / q.ordered * 100, 0) : ''}</span></div>`).join('')}
          ${q.rejected ? `<div class="small text-danger mt-2"><i class="bi bi-x-circle me-1"></i>${fmt.int(q.rejected)} stems rejected at the delivery check</div>` : ''}
        </div></div></div>
      </div>

      ${d.financial_access ? `<div class="row g-3 mb-3">
        ${[['Revenue', m.total_revenue_base, 'ico-green', 'bi-cash-stack'], ['Farm cost', m.farm_cost_base, 'ico-rose', 'bi-tree'], ['Other costs & expenses', num(m.other_cost_base) + num(m.expenses_base), 'ico-amber', 'bi-receipt'],
           [m.projected ? 'Projected profit' : 'Profit', m.profit_base, m.profit_base >= 0 ? 'ico-green' : 'ico-rose', 'bi-piggy-bank']].map(([l, v, c, i], k) => `
          <div class="col-6 col-xl-3"><div class="card kpi h-100"><div class="card-body"><div class="ico ${c}"><i class="bi ${i}"></i></div><div class="label">${l}</div>
          <div class="value ${k === 3 ? (v >= 0 ? 'text-success' : 'text-danger') : ''}">${B} ${fmt.money(v, 0)}</div>
          <div class="meta">${k === 3 ? 'Margin ' + fmt.pct(m.margin_pct) + (d.bench && d.bench.avg_margin !== null ? ` · avg ${fmt.pct(d.bench.avg_margin)}` : '') : (k === 0 ? esc(m.revenue_source) : '')}</div></div></div></div>`).join('')}
      </div>
      <div class="row g-3 mb-3">
        <div class="col-lg-6"><div class="card h-100"><div class="card-header"><h3>Where the money went</h3></div><div class="card-body"><div style="height:260px"><canvas id="jWater"></canvas></div></div></div></div>
        <div class="col-lg-6"><div class="card h-100"><div class="card-header"><h3>Time per step</h3></div><div class="card-body">${d.legs.length ? '<div style="height:260px"><canvas id="jLegs"></canvas></div>' : '<div class="text-secondary small">Not enough steps completed yet.</div>'}</div></div></div>
      </div>` : ''}

      <div class="row g-3 mb-3">
        <div class="col-lg-6"><div class="card h-100"><div class="card-header"><h3><i class="bi bi-tree me-1"></i>Sourcing farms</h3></div><div class="card-body table-responsive">
          <table class="table table-sm"><thead><tr><th>Farm</th><th class="num">Asked</th><th class="num">Confirmed</th><th class="num">Received</th><th class="num">Rejected</th>${d.financial_access ? '<th class="num">Bill</th>' : ''}</tr></thead><tbody>
          ${farms.map(f => `<tr><td>${esc(f.farm)}<div class="small text-secondary">${esc(f.farm_order_no)}</div></td><td class="num">${fmt.int(f.allocated)}</td><td class="num">${f.confirmed !== null ? fmt.int(f.confirmed) : '—'}</td>
            <td class="num ${f.received !== null && num(f.received) < num(f.confirmed) ? 'variance-neg' : ''}">${f.received !== null ? fmt.int(f.received) : '—'}</td><td class="num">${fmt.int(f.rejected)}</td>
            ${d.financial_access ? `<td class="num">${num(f.cost_amount) ? esc(f.currency_code) + ' ' + fmt.money(f.farm_invoice_amount ?? f.cost_amount) : ''}</td>` : ''}</tr>`).join('') || '<tr><td colspan="6" class="text-secondary">Not sent to farms yet.</td></tr>'}
          </tbody></table></div></div></div>
        <div class="col-lg-6"><div class="card h-100"><div class="card-header"><h3><i class="bi bi-flower3 me-1"></i>Lines</h3></div><div class="card-body table-responsive">
          <table class="table table-sm"><thead><tr><th>Variety</th><th>Len</th><th class="num">Ordered</th><th class="num">Confirmed</th><th class="num">Received</th><th class="num">Price</th></tr></thead><tbody>
          ${d.lines.map(l => { const c = l.allocations.reduce((s, a) => s + num(a.confirmed_stems), 0), r = l.allocations.reduce((s, a) => s + num(a.received_stems), 0);
            return `<tr><td>${esc(l.variety)}</td><td>${esc(l.length)}</td><td class="num">${fmt.int(l.stems)}</td><td class="num">${fmt.int(c)}</td><td class="num ${r && r < l.stems ? 'variance-neg' : ''}">${fmt.int(r)}</td><td class="num">${fmt.money(l.selling_price, 4)}</td></tr>`; }).join('')}
          </tbody></table></div></div></div>
      </div>

      <div class="card"><div class="card-header"><h3><i class="bi bi-clock-history me-1"></i>Timeline</h3></div><div class="card-body">
        <div class="timeline">${d.timeline.map(t => `<div class="tl-item"><div class="tl-dot" style="background:${t.action.startsWith('stage:') ? '#e11d48' : (t.action === 'create' ? '#14532d' : '#94a3b8')}"><i class="bi ${t.action.startsWith('stage:') ? 'bi-flag-fill' : 'bi-dot'}"></i></div>
          <div class="tl-title">${esc(t.description)}</div><div class="tl-meta">${fmt.datetime(t.created_at)} · ${esc(t.user || 'System')}</div></div>`).join('') || '<div class="text-secondary small">No activity recorded.</div>'}</div>
      </div></div>`);

    if (d.financial_access) {
      const wf = [['Revenue', num(m.revenue_base)], ['Other charges', num(m.other_revenue_base)], ['Farm cost', -num(m.farm_cost_base)], ['Other costs', -num(m.other_cost_base)], ['Expenses', -num(m.expenses_base)]];
      let run = 0; const bars = wf.map(([l, v]) => { const s = run; run += v; return [l, [Math.min(s, run), Math.max(s, run)], v]; });
      bars.push(['Profit', [0, run], run]);
      charts.push(new Chart(document.getElementById('jWater'), { type: 'bar', data: { labels: bars.map(b => b[0]), datasets: [{ data: bars.map(b => b[1]), borderRadius: 6,
        backgroundColor: bars.map((b, i) => i === bars.length - 1 ? (b[2] >= 0 ? '#16a34a' : '#dc2626') : (b[2] >= 0 ? '#14532d' : '#e11d48')) }] },
        options: { plugins: { legend: { display: false }, tooltip: { callbacks: { label: (c) => B + ' ' + fmt.money(bars[c.dataIndex][2], 0) } } }, scales: { y: { ticks: { callback: (v) => fmt.qty(v) } }, x: { grid: { display: false } } } } }));
      if (d.legs.length) charts.push(new Chart(document.getElementById('jLegs'), { type: 'bar', data: { labels: d.legs.map(l => l.to), datasets: [{ data: d.legs.map(l => l.hours), backgroundColor: palette.slice(0, d.legs.length), borderRadius: 6 }] },
        options: { indexAxis: 'y', plugins: { legend: { display: false }, tooltip: { callbacks: { label: (c) => fmt.qty(c.raw) + ' hours from "' + d.legs[c.dataIndex].from + '"' } } }, scales: { x: { title: { display: true, text: 'hours' } } } } }));
    }
  }
  <?php if ($jid): ?>setSelect($s, 'orders', <?= $jid ?>, <?= json_encode($jlabel) ?>);<?php endif; ?>
})();
<?php $pageInline = ob_get_clean(); ?>

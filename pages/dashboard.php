<?php $first = explode(' ', $user['full_name'])[0]; $h = (int)date('G'); ?>
<div class="page-head">
  <div class="title">
    <div><h2>Good <?= $h < 12 ? 'morning' : ($h < 17 ? 'afternoon' : 'evening') ?>, <?= e($first) ?> 🌷</h2>
      <div class="sub"><?= date('l, j F Y') ?> · here's how the business is blooming.</div></div>
  </div>
  <div class="actions">
    <?php if (can('orders.create')): ?><a class="btn btn-primary" href="index.php?p=orders&new=1"><i class="bi bi-plus-lg me-1"></i>New order</a><?php endif; ?>
    <?php if (can('customer_payments.create')): ?><a class="btn btn-light" href="index.php?p=customer_payments"><i class="bi bi-cash-coin me-1"></i>Record payment</a><?php endif; ?>
  </div>
</div>
<div class="row g-3 mb-3" id="kpis"><?= str_repeat('<div class="col-6 col-xl-3"><div class="card"><div class="card-body"><div class="skeleton" style="height:70px"></div></div></div></div>', 4) ?></div>
<div class="row g-3 mb-3">
  <div class="col-xl-8"><div class="card h-100"><div class="card-header d-flex align-items-center"><h3 class="me-auto">Sales vs costs — last 12 months</h3><span class="small text-secondary"><?= e(App\Core\Fx::baseCode()) ?></span></div>
    <div class="card-body"><div style="height:300px"><canvas id="trendChart"></canvas></div></div></div></div>
  <div class="col-xl-4"><div class="card h-100"><div class="card-header"><h3>Order pipeline</h3></div><div class="card-body" id="pipeline"></div></div></div>
</div>
<div class="row g-3 mb-3">
  <div class="col-xl-7"><div class="card h-100"><div class="card-header d-flex"><h3 class="me-auto">Next departures</h3><a class="small" href="index.php?p=orders">All orders</a></div><div class="card-body p-0" id="upcoming"></div></div></div>
  <div class="col-xl-5"><div class="card h-100"><div class="card-header"><h3><i class="bi bi-stars text-primary me-1"></i>Insights</h3></div><div class="card-body" id="dashInsights"><?= str_repeat('<div class="skeleton mb-2" style="height:38px"></div>', 3) ?></div></div></div>
</div>
<div class="row g-3">
  <div class="col-md-6 col-xl-4"><div class="card h-100"><div class="card-header"><h3>Top customers (90 days)</h3></div><div class="card-body" id="topCust"></div></div></div>
  <div class="col-md-6 col-xl-4"><div class="card h-100"><div class="card-header"><h3>Top varieties (90 days)</h3></div><div class="card-body"><div style="height:220px"><canvas id="varChart"></canvas></div></div></div></div>
  <div class="col-xl-4"><div class="card h-100"><div class="card-header"><h3>Recent activity</h3></div><div class="card-body" id="activity"></div></div></div>
</div>
<?php ob_start(); ?>
(function () {
  const { get, esc, fmt, stagePill, palette, MG, can } = App;
  const B = MG.base.code;
  get('dashboard.summary', {}, { quiet: true }).then(d => {
    const k = d.kpis;
    const ch = k.sales_last_month ? (k.sales_month - k.sales_last_month) / k.sales_last_month * 100 : null;
    const card = (label, value, icon, cls, meta = '', href = '') => `<div class="col-6 col-xl-3"><${href ? `a href="${href}"` : 'div'} class="card kpi h-100 text-body d-block"><div class="card-body"><div class="ico ${cls}"><i class="bi ${icon}"></i></div><div class="label">${label}</div><div class="value">${value}</div><div class="meta">${meta}</div></div></${href ? 'a' : 'div'}></div>`;
    $('#kpis').html(d.financial
      ? card('Sales this month', B + ' ' + fmt.money(k.sales_month, 0), 'bi-graph-up-arrow', 'ico-green', ch !== null ? `<span class="${ch >= 0 ? 'text-success' : 'text-danger'}"><i class="bi bi-arrow-${ch >= 0 ? 'up' : 'down'}-right"></i> ${fmt.pct(Math.abs(ch), 0)}</span> vs last month` : fmt.int(k.stems_month) + ' stems', 'index.php?p=report&r=sales_by_month')
        + card('Customers owe', B + ' ' + fmt.money(k.receivable, 0), 'bi-hourglass-split', 'ico-amber', B + ' ' + fmt.money(k.receipts_month, 0) + ' received this month', 'index.php?p=report&r=receivables_aging')
        + card('We owe farms', B + ' ' + fmt.money(k.payable_farms, 0), 'bi-tree', 'ico-rose', 'from delivery-checked orders', 'index.php?p=statements&type=farm')
        + card('Active orders', fmt.int(k.active_orders), 'bi-flower1', 'ico-blue', fmt.int(k.stems_month) + ' stems invoiced this month', 'index.php?p=orders')
      : card('Active orders', fmt.int(k.active_orders), 'bi-flower1', 'ico-blue', '', 'index.php?p=orders') + card('Stems invoiced (month)', fmt.int(k.stems_month), 'bi-flower2', 'ico-green'));

    // pipeline
    const order = ['draft', 'sourcing', 'confirmed', 'received', 'packing', 'invoiced'];
    const total = Math.max(1, order.reduce((s, x) => s + (d.stages[x] || 0), 0));
    $('#pipeline').html(order.map(s => { const m = MG.stages[s]; const n = d.stages[s] || 0;
      return `<a href="index.php?p=orders" class="d-flex align-items-center gap-2 mb-2 text-body"><i class="bi ${m[2]} text-${m[1] === 'teal' ? 'teal' : m[1]}"></i><span class="small flex-grow-1">${m[0]}</span>
        <div class="progress flex-grow-1" style="height:8px;max-width:45%"><div class="progress-bar" style="width:${n / total * 100}%;background:var(--mg-rose)"></div></div><span class="fw-bold small" style="width:24px;text-align:right">${n}</span></a>`; }).join(''));

    // trend chart
    if (d.trend.length) {
      new Chart(document.getElementById('trendChart'), { data: { labels: d.trend.map(t => t.month), datasets: [
        { type: 'bar', label: 'Flower sales', data: d.trend.map(t => +t.flowers), backgroundColor: '#e11d48cc', borderRadius: 6, stack: 's' },
        { type: 'bar', label: 'Other & freight', data: d.trend.map(t => +t.other), backgroundColor: '#f59e0bcc', borderRadius: 6, stack: 's' },
        { type: 'line', label: 'Farm cost', data: d.trend.map(t => +t.farm_cost), borderColor: '#14532d', backgroundColor: '#14532d', tension: .35, borderWidth: 2.5, pointRadius: 3 },
        { type: 'line', label: 'Expenses', data: d.trend.map(t => +t.expenses), borderColor: '#64748b', backgroundColor: '#64748b', tension: .35, borderWidth: 2, borderDash: [5, 4], pointRadius: 2 }] },
        options: { plugins: { legend: { position: 'bottom' } }, scales: { x: { stacked: true, grid: { display: false } }, y: { stacked: false, beginAtZero: true, ticks: { callback: v => fmt.qty(v) } } } } });
    } else $('#trendChart').parent().html(`<div class="empty"><i class="bi bi-graph-up"></i>${d.financial ? 'Sales appear here once you start invoicing.' : 'Financial figures are hidden for your role.'}</div>`);

    // upcoming
    $('#upcoming').html(d.upcoming.length ? `<div class="table-responsive"><table class="table table-hover mb-0"><tbody>${d.upcoming.map(o => {
      const days = Math.round((new Date(o.departure_date) - new Date(App.today())) / 864e5);
      return `<tr style="cursor:pointer" onclick="location.href='index.php?p=order&id=${o.id}'"><td><strong>${esc(o.order_no)}</strong><div class="small text-secondary">${esc(o.customer)}</div></td>
        <td>${stagePill(o.stage)}</td><td class="num">${fmt.int(o.stems)} stems</td>
        <td class="text-end"><span class="${days <= 1 ? 'text-danger fw-semibold' : ''}">${fmt.date(o.departure_date)}</span><div class="small text-secondary">${days < 0 ? Math.abs(days) + 'd overdue' : (days === 0 ? 'today' : 'in ' + days + 'd')}</div></td></tr>`; }).join('')}</tbody></table></div>`
      : '<div class="empty"><i class="bi bi-airplane"></i>No open orders.</div>');

    $('#topCust').html(d.top_customers.length ? d.top_customers.map((c, i) => { const max = +d.top_customers[0].v || 1;
      return `<div class="mb-2"><div class="d-flex justify-content-between small"><span>${esc(c.name)}</span><strong>${B} ${fmt.money(c.v, 0)}</strong></div><div class="progress" style="height:6px"><div class="progress-bar" style="width:${c.v / max * 100}%;background:${palette[i]}"></div></div></div>`; }).join('')
      : '<div class="text-secondary small">No data.</div>');
    if (d.top_varieties.length) new Chart(document.getElementById('varChart'), { type: 'doughnut', data: { labels: d.top_varieties.map(v => v.name), datasets: [{ data: d.top_varieties.map(v => +v.stems), backgroundColor: palette, borderWidth: 2 }] }, options: { cutout: '62%', plugins: { legend: { position: 'right' } } } });
    else $('#varChart').parent().html('<div class="text-secondary small">No invoiced stems yet.</div>');
    $('#activity').html(d.activity.length ? `<div class="timeline">${d.activity.map(a => `<div class="tl-item"><div class="tl-dot" style="background:#e11d48"><i class="bi bi-dot"></i></div><div class="tl-title small">${a.order_id ? `<a href="index.php?p=order&id=${a.order_id}">${esc(a.description)}</a>` : esc(a.description)}</div><div class="tl-meta">${fmt.ago(a.created_at)} · ${esc(a.user || 'System')}</div></div>`).join('')}</div>` : '<div class="text-secondary small">Nothing yet.</div>');
  });
  if (can('reports.view')) get('reports.insights', {}, { quiet: true }).then(list => $('#dashInsights').html(list.slice(0, 6).map(i => `<div class="insight ${i.level}"><i class="bi ${i.icon}"></i><div class="flex-grow-1 small">${esc(i.text)}</div>${i.link ? `<a href="${i.link}" class="small"><i class="bi bi-chevron-right"></i></a>` : ''}</div>`).join(''))).catch(() => {});
  else $('#dashInsights').closest('.col-xl-5').remove();
})();
<?php $pageInline = ob_get_clean(); ?>

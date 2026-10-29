<div class="page-head">
  <div class="title"><div class="ico"><i class="bi bi-bar-chart-line"></i></div>
    <div><h2>Reports</h2><div class="sub">Every process in the system has a report. Start with the Order Journey, or check the smart insights.</div></div></div>
</div>
<div class="row g-3 mb-4">
  <div class="col-lg-5">
    <a class="card report-tile featured" href="index.php?p=order_journey">
      <div class="card-body p-4">
        <div class="d-flex align-items-center gap-3 mb-3"><div class="ico" style="background:rgba(255,255,255,.12)"><i class="bi bi-signpost-split"></i></div><span class="badge text-bg-light text-danger">Featured</span></div>
        <h3 class="h4 fw-bold font-brand">Order Journey</h3>
        <p class="text-secondary mb-0">Pick any order and see its full story: every step and who did it, the quantity funnel from ordered to received, where flowers fell short, costs, expenses, profit, lead times and how it compares with your other orders.</p>
      </div>
    </a>
  </div>
  <div class="col-lg-7">
    <div class="card h-100"><div class="card-header d-flex align-items-center"><h3 class="me-auto"><i class="bi bi-stars text-primary me-1"></i>Smart insights</h3><span class="small text-secondary">Computed from your live data</span></div>
      <div class="card-body" id="insights"><?= str_repeat('<div class="skeleton mb-2" style="height:42px"></div>', 4) ?></div></div>
  </div>
</div>
<div id="catalog"></div>
<?php ob_start(); ?>
(function () {
  const { get, esc } = App;
  get('reports.insights', {}, { quiet: true }).then(list => {
    $('#insights').html(list.map(i => `<div class="insight ${i.level}"><i class="bi ${i.icon}"></i><div class="flex-grow-1">${esc(i.text)}</div>${i.link ? `<a href="${i.link}" class="small text-nowrap">View <i class="bi bi-chevron-right"></i></a>` : ''}</div>`).join(''));
  }).catch(() => $('#insights').html('<div class="text-secondary small">Insights unavailable.</div>'));
  get('reports.catalog', {}, { quiet: true }).then(c => {
    $('#catalog').html(Object.entries(c.groups).map(([g, icon]) => {
      const rs = c.reports.filter(r => r.group === g); if (!rs.length) return '';
      return `<h3 class="h6 text-uppercase text-secondary fw-bold mt-4 mb-3" style="letter-spacing:.08em"><i class="bi ${icon} me-1"></i>${esc(g)}</h3>
        <div class="row g-3">${rs.map(r => `<div class="col-sm-6 col-lg-4 col-xxl-3"><a class="card report-tile" href="index.php?p=report&r=${r.key}"><div class="card-body d-flex gap-3">
          <div class="ico ico-rose flex-shrink-0"><i class="bi ${r.icon}"></i></div><div><div class="fw-semibold">${esc(r.title)}</div><div class="small text-secondary">${esc(r.desc)}</div></div></div></a></div>`).join('')}</div>`;
    }).join(''));
  });
})();
<?php $pageInline = ob_get_clean(); ?>

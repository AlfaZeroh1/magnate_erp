<div class="page-head">
  <div class="title"><div class="ico"><i class="bi bi-currency-exchange"></i></div>
    <div><h2>Exchange Rates</h2><div class="sub">Rates are <strong><?= e(App\Core\Fx::baseCode()) ?> per 1 unit</strong>. A rate applies from its date until you set a newer one, so each transaction uses the rate valid on its own date.</div></div></div>
  <div class="actions">
    <?php if (can('exchange_rates.edit')): ?>
      <button class="btn btn-light" data-fx-prompt><i class="bi bi-calendar-check me-1"></i>Set this month's rates</button>
      <button class="btn btn-primary" id="setRate"><i class="bi bi-plus-lg me-1"></i>Set a rate</button>
    <?php endif; ?>
  </div>
</div>
<div class="row g-3 mb-3" id="fxCards"><?= str_repeat('<div class="col-md-4"><div class="card"><div class="card-body"><div class="skeleton" style="height:90px"></div></div></div></div>', 3) ?></div>
<div class="card"><div class="card-header d-flex align-items-center"><h3 class="me-auto">History</h3><select class="form-select form-select-sm" id="fCur" style="width:auto"></select></div>
  <div class="card-body"><table class="table table-hover w-100" id="fxTable"><thead><tr><th>Effective from</th><th>Currency</th><th class="num">Rate</th><th>Notes</th><th>Set by</th><th></th></tr></thead></table></div>
</div>
<?php ob_start(); ?>
(function () {
  const { get, api, esc, fmt, table, modalForm, confirm, can, MG } = App;
  const charts = [];
  const loadCards = async () => {
    const rows = await get('fx.overview', {}, { quiet: true });
    charts.forEach(c => c.destroy()); charts.length = 0;
    $('#fCur').html('<option value="">All currencies</option>' + rows.filter(r => !+r.is_base).map(r => `<option value="${r.id}">${esc(r.code)}</option>`).join(''));
    $('#fxCards').html(rows.filter(r => !+r.is_base).map(r => {
      const l = r.latest, p = r.previous; const ch = l && p ? (l.rate - p.rate) / p.rate * 100 : null;
      return `<div class="col-sm-6 col-xl-3"><div class="card kpi h-100 ${r.stale ? 'border-warning' : ''}"><div class="card-body">
        <div class="ico ico-${r.stale ? 'amber' : 'green'}"><i class="bi ${r.stale ? 'bi-exclamation-triangle' : 'bi-check2-circle'}"></i></div>
        <div class="label">${esc(r.code)} · ${esc(r.name)}</div>
        <div class="value">${l ? fmt.rate(l.rate) : '—'} <span class="fs-6 text-secondary">${esc(MG.base.code)}</span></div>
        <div class="meta">${l ? 'since ' + fmt.date(l.effective_date) : 'never set'} ${ch !== null ? `<span class="${ch >= 0 ? 'text-success' : 'text-danger'} ms-1"><i class="bi bi-arrow-${ch >= 0 ? 'up' : 'down'}-right"></i>${fmt.pct(Math.abs(ch), 2)}</span>` : ''}</div>
        ${r.stale ? '<div class="small text-warning-emphasis mt-1">Not set this month</div>' : ''}
        <div style="height:50px" class="mt-2"><canvas data-spark="${r.id}"></canvas></div>
        ${can('exchange_rates.edit') ? `<button class="btn btn-sm btn-outline-primary mt-2" data-quick="${r.id}" data-code="${esc(r.code)}">Update ${esc(r.code)}</button>` : ''}
      </div></div></div>`;
    }).join('') || '<div class="col-12"><div class="card"><div class="card-body empty"><i class="bi bi-coin"></i>Add foreign currencies under Setup Lists → Currencies.</div></div></div>');
    rows.filter(r => !+r.is_base && r.series.length > 1).forEach(r => {
      const el = document.querySelector(`[data-spark="${r.id}"]`);
      charts.push(new Chart(el, { type: 'line', data: { labels: r.series.map(s => s.effective_date), datasets: [{ data: r.series.map(s => +s.rate), borderColor: '#e11d48', borderWidth: 2, pointRadius: 0, tension: .35, fill: true, backgroundColor: 'rgba(225,29,72,.08)' }] },
        options: { plugins: { legend: { display: false }, tooltip: { enabled: true } }, scales: { x: { display: false }, y: { display: false } } } }));
    });
  };
  const dt = table('#fxTable', {
    ajax: (d, cb) => get('fx.history', { currency_id: $('#fCur').val() }, { quiet: true }).then(r => cb({ data: r })).catch(() => cb({ data: [] })),
    columns: [
      { data: 'effective_date', render: (v, t) => t === 'display' ? fmt.date(v) : v },
      { data: 'code', render: (v, t, r) => t === 'display' ? `<strong>${esc(v)}</strong> <span class="text-secondary small">${esc(r.name)}</span>` : v },
      { data: 'rate', className: 'num', render: (v, t) => t === 'display' ? fmt.rate(v) : +v },
      { data: 'notes', defaultContent: '' },
      { data: 'created_by_name', defaultContent: '' },
      { data: null, orderable: false, className: 'actions', render: (r) => can('exchange_rates.edit') ? `<button class="btn btn-sm btn-light text-danger" data-del="${r.id}"><i class="bi bi-trash"></i></button>` : '' }
    ], order: [[0, 'desc']]
  });
  $('#fCur').on('change', () => dt.ajax.reload());
  const open = (curId, code) => modalForm({
    title: 'Set exchange rate', icon: 'bi-currency-exchange', size: 'md',
    fields: [
      { name: 'currency_id', label: 'Currency', type: 'select', source: 'currencies', required: true, col: 12 },
      { name: 'effective_date', label: 'Effective from', type: 'date', required: true, default: 'today' },
      { name: 'rate', label: `${MG.base.code} per 1 unit`, type: 'rate', required: true },
      { name: 'notes', label: 'Notes / source', type: 'text', col: 12 }
    ], values: curId ? { currency_id: curId, currency_id__label: code } : {},
    onSubmit: async (d) => { await api('fx.set', d); dt.ajax.reload(); loadCards(); }
  });
  $('#setRate').on('click', () => open());
  $('#fxCards').on('click', '[data-quick]', function () { open($(this).data('quick'), $(this).data('code')); });
  $('#fxTable').on('click', '[data-del]', async function () {
    if (await confirm({ title: 'Remove this rate?', text: 'Transactions already saved keep the rate they used.', icon: 'warning', danger: true, confirmText: 'Remove' })) { await api('fx.delete', { id: $(this).data('del') }); dt.ajax.reload(); loadCards(); }
  });
  document.addEventListener('mg:rates-updated', () => { dt.ajax.reload(); loadCards(); });
  loadCards();
})();
<?php $pageInline = ob_get_clean(); ?>

<div class="page-head">
  <div class="title"><div class="ico"><i class="bi bi-receipt"></i></div>
    <div><h2>Invoices</h2><div class="sub">Order invoices, other sales and freight invoices share one number series.</div></div></div>
</div>
<div class="card mb-3"><div class="card-body py-2">
  <div class="row g-2 align-items-end">
    <div class="col-6 col-md-2"><label class="form-label">Type</label><select class="form-select form-select-sm" id="fKind"><option value="">All</option><option value="order">Order</option><option value="other_sale">Other sale</option><option value="freight">Freight</option></select></div>
    <div class="col-6 col-md-2"><label class="form-label">Status</label><select class="form-select form-select-sm" id="fStatus"><option value="open">Open</option><option value="void">Void</option><option value="">All</option></select></div>
    <div class="col-6 col-md-2"><label class="form-label">From</label><input type="date" class="form-control form-control-sm" id="fFrom"></div>
    <div class="col-6 col-md-2"><label class="form-label">To</label><input type="date" class="form-control form-control-sm" id="fTo"></div>
    <div class="col-12 col-md-4"><label class="form-label">Bill-to customer</label><select class="form-select form-select-sm" id="fCust"></select></div>
  </div>
</div></div>
<div class="card"><div class="card-body">
  <table class="table table-hover w-100" id="invTable"><thead><tr>
    <th>Invoice</th><th>Type</th><th>Date</th><th>Bill to</th><th>Reference</th><th class="num">Amount</th><th class="num">Other costs</th><th class="num">Base (<?= e(App\Core\Fx::baseCode()) ?>)</th><th>Status</th><th></th>
  </tr></thead>
  <tfoot><tr><th colspan="7">Total (base currency, open)</th><th class="num" id="tBase"></th><th colspan="2"></th></tr></tfoot></table>
</div></div>
<?php ob_start(); ?>
(function () {
  const { get, api, esc, fmt, table, select2, confirm, can } = App;
  const kinds = { order: ['Order', 'primary'], other_sale: ['Other sale', 'warning'], freight: ['Freight', 'info'] };
  select2('#fCust', 'customers', { placeholder: 'All customers' });
  const dt = table('#invTable', {
    ajax: (d, cb) => get('invoices.list', { kind: $('#fKind').val(), status: $('#fStatus').val(), from: $('#fFrom').val(), to: $('#fTo').val(), customer_id: $('#fCust').val() }, { quiet: true }).then(r => cb({ data: r })).catch(() => cb({ data: [] })),
    columns: [
      { data: 'invoice_no', render: (v, t, r) => t === 'display' ? `<a class="fw-semibold" target="_blank" href="print.php?doc=invoice&id=${r.id}">${esc(v)}</a>` : v },
      { data: 'kind', render: (v, t) => t === 'display' ? `<span class="pill pill-${kinds[v][1]}">${kinds[v][0]}</span>` : v },
      { data: 'invoice_date', render: (v, t) => t === 'display' ? fmt.date(v) : v },
      { data: 'bill_to', render: (v, t, r) => t === 'display' ? esc(v) + (r.customer !== v ? `<div class="small text-secondary">for ${esc(r.customer)}</div>` : '') : v },
      { data: null, render: (r) => r.order_no ? `<a href="index.php?p=order&id=${r.order_id}">${esc(r.order_no)}</a>` : (r.booking_no ? esc(r.booking_no) : '') },
      { data: 'total', className: 'num', render: (v, t, r) => t === 'display' ? `<span class="small text-secondary">${esc(r.currency)}</span> ${fmt.money(v)}` : v },
      { data: 'other_costs', className: 'num', render: (v, t, r) => +v ? `${fmt.money(v)}` : '' },
      { data: 'base_total', className: 'num', render: (v, t) => t === 'display' ? fmt.money(v) : v },
      { data: 'status', render: (v) => v === 'open' ? '<span class="pill pill-success">Open</span>' : '<span class="pill pill-danger">Void</span>' },
      { data: null, orderable: false, className: 'actions', render: (r) => `<a class="btn btn-sm btn-light" target="_blank" href="print.php?doc=invoice&id=${r.id}" title="Print"><i class="bi bi-printer"></i></a>
          ${r.status === 'open' && can('invoices.void') ? `<button class="btn btn-sm btn-light text-danger" data-void="${r.id}" data-no="${esc(r.invoice_no)}" title="Void"><i class="bi bi-x-circle"></i></button>` : ''}` }
    ],
    order: [[2, 'desc']],
    footerCallback: function () {
      const rows = this.api().rows({ search: 'applied' }).data().toArray().filter(r => r.status === 'open');
      $('#tBase').text(fmt.money(rows.reduce((s, r) => s + (+r.base_total || 0), 0)));
    }
  });
  $('#fKind,#fStatus,#fFrom,#fTo,#fCust').on('change', () => dt.ajax.reload());
  $('#invTable').on('click', '[data-void]', async function () {
    const reason = await confirm({ title: 'Void invoice ' + $(this).data('no') + '?', text: 'An order invoice sends the order back to packing so it can be corrected and re-invoiced. The number is not reused.', input: 'text', inputLabel: 'Reason', icon: 'warning', confirmText: 'Void invoice', danger: true });
    if (reason) { await api('invoices.void', { id: $(this).data('void'), reason }).catch(() => {}); dt.ajax.reload(); }
  });
})();
<?php $pageInline = ob_get_clean(); ?>

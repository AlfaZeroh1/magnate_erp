<div class="page-head">
  <div class="title"><div class="ico"><i class="bi bi-box-seam"></i></div>
    <div><h2>Packing Lists</h2><div class="sub">Built from delivery-checked quantities. Open an order to edit packing details or invoice it.</div></div></div>
  <div class="actions">
    <select class="form-select" id="fStatus" style="width:auto"><option value="">All</option><option value="open" selected>Open (not invoiced)</option><option value="invoiced">Invoiced</option></select>
  </div>
</div>
<div class="card"><div class="card-body">
  <table class="table table-hover w-100" id="plTable"><thead><tr>
    <th>Packing list</th><th>Consignment</th><th>Order</th><th>Customer</th><th>Departure</th><th>Drop point</th><th>AWB</th><th class="num">Stems</th><th class="num">Boxes</th><th>Invoice</th><th></th>
  </tr></thead></table>
</div></div>
<?php ob_start(); ?>
(function () {
  const { get, esc, fmt, table } = App;
  const dt = table('#plTable', {
    ajax: (d, cb) => get('packing.list', { status: $('#fStatus').val() }, { quiet: true }).then(r => cb({ data: r })).catch(() => cb({ data: [] })),
    columns: [
      { data: 'packing_list_no', render: (v, t, r) => t === 'display' ? `<a class="fw-semibold" href="index.php?p=order&id=${r.order_id}#packing">${esc(v)}</a>` : v },
      { data: 'consignment_no' },
      { data: 'order_no', render: (v, t, r) => t === 'display' ? `<a href="index.php?p=order&id=${r.order_id}">${esc(v)}</a>` : v },
      { data: 'customer' },
      { data: 'departure_date', render: (v, t) => t === 'display' ? fmt.date(v) : v },
      { data: 'drop_point', defaultContent: '' },
      { data: 'awb_no', defaultContent: '' },
      { data: 'stems', className: 'num', render: (v) => fmt.int(v) },
      { data: 'boxes', className: 'num', render: (v) => fmt.qty(v) },
      { data: 'invoice_no', render: (v, t, r) => v ? `<a href="print.php?doc=invoice&id=${r.invoice_id}" target="_blank" class="badge text-bg-success-subtle text-success-emphasis">${esc(v)}</a>` : '<span class="pill pill-warning">To invoice</span>' },
      { data: null, orderable: false, className: 'actions', render: (r) => `<a class="btn btn-sm btn-light" target="_blank" href="print.php?doc=packing_list&id=${r.order_id}" title="Print"><i class="bi bi-printer"></i></a>` }
    ], order: [[4, 'desc']]
  });
  $('#fStatus').on('change', () => dt.ajax.reload());
})();
<?php $pageInline = ob_get_clean(); ?>

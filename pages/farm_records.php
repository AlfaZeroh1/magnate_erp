<div class="page-head">
  <div class="title"><div class="ico"><i class="bi bi-journal-text"></i></div>
    <div><h2>Farm Records</h2><div class="sub">Each farm's part of an order: farm invoice, amount, phyto weight and AWB. These are the farm bills on farm statements.</div></div></div>
</div>
<div class="card mb-3"><div class="card-body py-2">
  <div class="row g-2 align-items-end">
    <div class="col-12 col-md-3"><label class="form-label">Farm</label><select class="form-select form-select-sm" id="fFarm"></select></div>
    <div class="col-12 col-md-3"><label class="form-label">Order</label><select class="form-select form-select-sm" id="fOrder"></select></div>
    <div class="col-6 col-md-2"><label class="form-label">Departure from</label><input type="date" class="form-control form-control-sm" id="fFrom"></div>
    <div class="col-6 col-md-2"><label class="form-label">to</label><input type="date" class="form-control form-control-sm" id="fTo"></div>
    <div class="col-12 col-md-2"><div class="form-check mb-1"><input class="form-check-input" type="checkbox" id="fMissing"><label class="form-check-label small" for="fMissing">Missing farm invoice</label></div></div>
  </div>
</div></div>
<div class="card"><div class="card-body">
  <div class="small text-secondary mb-2"><i class="bi bi-pencil-square me-1"></i>Click into a cell to edit — changes save automatically when you leave the field.</div>
  <table class="table table-hover table-sm w-100" id="frTable"><thead><tr>
    <th>Farm</th><th>Order / Invoice</th><th>Departure</th><th class="num">Stems</th><th class="num">Boxes</th><th class="num">Computed</th>
    <th>Farm invoice no</th><th class="num">Farm invoice amount</th><th class="num">Phyto kg</th><th>Agent AWB</th><th>Comments</th>
  </tr></thead></table>
</div></div>
<?php ob_start(); ?>
(function () {
  const { get, api, esc, fmt, table, select2, can, toast } = App;
  const editable = can('farm_records.edit');
  select2('#fFarm', 'farms', { placeholder: 'All farms' });
  select2('#fOrder', 'orders', { placeholder: 'All orders' });
  const cell = (r, field, type = 'text', w = 120) => {
    const v = r[field] ?? '';
    if (!editable || r.status !== 'received') return esc(type === 'number' && v !== '' ? fmt.money(v) : v);
    return `<input class="form-control form-control-sm fr-in" style="min-width:${w}px" type="${type}" ${type === 'number' ? 'step="0.01" min="0"' : ''} data-id="${r.id}" data-field="${field}" value="${esc(v)}">`;
  };
  const dt = table('#frTable', {
    responsive: false, scrollX: true,
    ajax: (d, cb) => get('farmrec.list', { farm_id: $('#fFarm').val(), order_id: $('#fOrder').val(), from: $('#fFrom').val(), to: $('#fTo').val(), missing: $('#fMissing').is(':checked') ? 1 : '' }, { quiet: true }).then(r => cb({ data: r })).catch(() => cb({ data: [] })),
    columns: [
      { data: 'farm', render: (v, t, r) => t === 'display' ? `<strong>${esc(v)}</strong><div class="small text-secondary">${esc(r.farm_order_no)} · ${esc(r.status)}</div>` : v },
      { data: 'order_no', render: (v, t, r) => t === 'display' ? `<a href="index.php?p=order&id=${r.order_id}">${esc(v)}</a><div class="small text-secondary">${esc(r.customer)}${r.customer_invoice_no ? ' · ' + esc(r.customer_invoice_no) : ''}</div>` : v },
      { data: 'departure_date', render: (v, t) => t === 'display' ? fmt.date(v) : v },
      { data: 'stems', className: 'num', render: (v) => fmt.int(v) },
      { data: 'boxes', className: 'num', render: (v) => fmt.qty(v) },
      { data: 'cost_amount', className: 'num', render: (v, t, r) => t === 'display' ? `<span class="small text-secondary">${esc(r.currency)}</span> ${fmt.money(v)}` : v },
      { data: 'farm_invoice_no', orderable: false, render: (v, t, r) => cell(r, 'farm_invoice_no', 'text', 130) },
      { data: 'farm_invoice_amount', orderable: false, render: (v, t, r) => cell(r, 'farm_invoice_amount', 'number', 110) },
      { data: 'phyto_kg', orderable: false, render: (v, t, r) => cell(r, 'phyto_kg', 'number', 90) },
      { data: 'agent_awb', orderable: false, render: (v, t, r) => cell(r, 'agent_awb', 'text', 140) },
      { data: 'comments', orderable: false, render: (v, t, r) => cell(r, 'comments', 'text', 180) },
    ], order: [[2, 'desc']]
  });
  $('#fFarm,#fOrder,#fFrom,#fTo,#fMissing').on('change', () => dt.ajax.reload());
  $('#frTable').on('focus', '.fr-in', function () { $(this).data('orig', this.value); });
  $('#frTable').on('change', '.fr-in', async function () {
    const $i = $(this);
    try {
      await api('farmrec.update', { id: $i.data('id'), field: $i.data('field'), value: $i.val() }, { quiet: true, silentSuccess: true });
      $i.addClass('is-valid'); setTimeout(() => $i.removeClass('is-valid'), 1200);
    } catch (e) { $i.addClass('is-invalid').val($i.data('orig')); }
  });
  $('#frTable').on('keydown', '.fr-in', function (e) { if (e.key === 'Enter') { e.preventDefault(); $(this).trigger('change').blur(); } });
})();
<?php $pageInline = ob_get_clean(); ?>

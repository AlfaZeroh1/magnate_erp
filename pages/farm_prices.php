<div class="page-head">
  <div class="title"><div class="ico"><i class="bi bi-tags"></i></div>
    <div><h2>Farm Prices</h2><div class="sub"><strong>Buying price</strong> is what the farm charges (used on farm orders and farm bills). <strong>Selling price</strong> is an optional farm-specific price to the customer; otherwise the general selling price is used.</div></div></div>
  <div class="actions">
    <?php if (can('farm_prices.edit')): ?>
      <button class="btn btn-light" id="copyBtn"><i class="bi bi-copy me-1"></i>Copy farm list</button>
      <button class="btn btn-primary" id="addBtn"><i class="bi bi-plus-lg me-1"></i>Add price</button>
    <?php endif; ?>
  </div>
</div>
<div class="card mb-3"><div class="card-body py-2"><div class="row g-2 align-items-end">
  <div class="col-12 col-md-3"><label class="form-label">Farm</label><select class="form-select form-select-sm" id="fFarm"></select></div>
  <div class="col-12 col-md-3"><label class="form-label">Variety</label><select class="form-select form-select-sm" id="fVar"></select></div>
  <div class="col-6 col-md-2"><label class="form-label">Length</label><select class="form-select form-select-sm" id="fLen"></select></div>
</div></div></div>
<div class="card"><div class="card-body">
  <?php if (can('farm_prices.edit')): ?>
  <div class="d-flex flex-wrap gap-2 mb-2 align-items-center" id="bulkBar" style="display:none!important">
    <span class="small fw-semibold" id="selCount"></span>
    <button class="btn btn-sm btn-outline-primary" id="adjustBtn"><i class="bi bi-percent me-1"></i>Adjust selected</button>
    <button class="btn btn-sm btn-outline-danger" id="delSelBtn"><i class="bi bi-trash me-1"></i>Delete selected</button>
  </div>
  <?php endif; ?>
  <table class="table table-hover w-100" id="fpTable"><thead><tr>
    <th style="width:28px"><input type="checkbox" class="form-check-input" id="selAll"></th>
    <th>Farm</th><th>Variety</th><th>Length</th><th class="num">Buying</th><th class="num">Farm selling</th><th class="num">General selling</th><th class="num">Margin</th><th>Status</th><th>Updated</th><th></th>
  </tr></thead></table>
</div></div>
<?php ob_start(); ?>
(function () {
  const { get, api, esc, fmt, table, select2, modalForm, confirm, can } = App;
  const editable = can('farm_prices.edit');
  select2('#fFarm', 'farms', { placeholder: 'All farms' }); select2('#fVar', 'varieties', { placeholder: 'All varieties' }); select2('#fLen', 'lengths', { placeholder: 'All' });
  const sel = new Set();
  const dt = table('#fpTable', {
    ajax: (d, cb) => get('pricing.farm_list', { farm_id: $('#fFarm').val(), variety_id: $('#fVar').val(), length_id: $('#fLen').val() }, { quiet: true }).then(r => cb({ data: r })).catch(() => cb({ data: [] })),
    columns: [
      { data: null, orderable: false, render: (r) => editable ? `<input type="checkbox" class="form-check-input row-sel" value="${r.id}" ${sel.has(+r.id) ? 'checked' : ''}>` : '' },
      { data: 'farm' },
      { data: 'variety', render: (v, t, r) => t === 'display' ? `${esc(v)}<div class="small text-secondary">${esc(r.category || '')}</div>` : v },
      { data: 'length' },
      { data: 'buying_price', className: 'num', render: (v, t, r) => t === 'display' ? `<span class="small text-secondary">${esc(r.currency)}</span> ${fmt.money(v, 4)}` : +v },
      { data: 'selling_price', className: 'num', render: (v, t, r) => v === null ? '<span class="text-secondary">—</span>' : (t === 'display' ? `<span class="small text-secondary">${esc(r.currency)}</span> ${fmt.money(v, 4)}` : +v) },
      { data: 'general_selling', className: 'num', render: (v, t, r) => v === null ? '<span class="text-secondary">—</span>' : (t === 'display' ? `<span class="small text-secondary">${esc(r.general_currency)}</span> ${fmt.money(v, 4)}` : +v) },
      { data: null, className: 'num', render: (r, t) => {
          const sp = r.selling_price !== null ? +r.selling_price : (r.general_currency === r.currency ? +r.general_selling : null);
          if (sp === null || !+sp) return t === 'display' ? '<span class="text-secondary small">n/a</span>' : -999;
          const m = (sp - r.buying_price) / sp * 100;
          return t === 'display' ? `<span class="${m < 0 ? 'text-danger' : (m < 15 ? 'text-warning' : 'text-success')} fw-semibold">${fmt.pct(m)}</span>` : m; } },
      { data: 'is_active', render: (v) => +v ? '<span class="pill pill-success">Active</span>' : '<span class="pill pill-secondary">Off</span>' },
      { data: 'updated_at', render: (v, t) => t === 'display' ? `<span class="small">${fmt.date(v)}</span>` : v },
      { data: null, orderable: false, className: 'actions', render: (r) => editable ? `<button class="btn btn-sm btn-light" data-edit="${r.id}"><i class="bi bi-pencil"></i></button><button class="btn btn-sm btn-light text-danger" data-del="${r.id}"><i class="bi bi-trash"></i></button>` : '' }
    ], order: [[1, 'asc'], [2, 'asc']]
  });
  $('#fFarm,#fVar,#fLen').on('change', () => { sel.clear(); syncBar(); dt.ajax.reload(); });

  const syncBar = () => { $('#bulkBar').attr('style', sel.size ? '' : 'display:none!important'); $('#selCount').text(sel.size + ' selected'); };
  $('#fpTable').on('change', '.row-sel', function () { this.checked ? sel.add(+this.value) : sel.delete(+this.value); syncBar(); });
  $('#selAll').on('change', function () { dt.rows({ search: 'applied' }).data().each(r => this.checked ? sel.add(+r.id) : sel.delete(+r.id)); $('.row-sel').prop('checked', this.checked); syncBar(); });

  const fields = (editing) => [
    { name: 'farm_id', label: 'Farm', type: 'select', source: 'farms', required: true, fills: { currency_id: 'currency_id' } },
    { name: 'variety_id', label: 'Variety', type: 'select', source: 'varieties', required: true },
    editing ? { name: 'length_id', label: 'Length', type: 'select', source: 'lengths', required: true } : { name: 'length_ids', label: 'Length(s)', type: 'select', source: 'lengths', required: true, multiple: true, help: 'Pick several lengths to price them all at once' },
    { name: 'currency_id', label: 'Currency', type: 'select', source: 'currencies', required: true },
    { name: 'buying_price', label: 'Buying price (per stem)', type: 'number', required: true, col: 6 },
    { name: 'selling_price', label: 'Farm selling price (optional)', type: 'number', col: 6, help: 'Leave blank to use the general selling price' },
    { name: 'is_active', label: 'Active', type: 'switch', default: 1 }
  ];
  const open = (row) => modalForm({
    title: row ? 'Edit farm price' : 'Add farm price', icon: 'bi-tags', fields: fields(!!row),
    values: row ? Object.assign({}, row, { farm_id__label: row.farm, variety_id__label: row.variety, length_id__label: row.length, currency_id__label: row.currency }) : {},
    onSubmit: async (d) => { await api('pricing.farm_save', Object.assign({ id: row ? row.id : 0 }, d, row ? { length_ids: [d.length_id] } : {})); dt.ajax.reload(null, false); }
  });
  $('#addBtn').on('click', () => open(null));
  $('#fpTable').on('click', '[data-edit]', function () { open(dt.rows().data().toArray().find(r => +r.id === +$(this).data('edit'))); });
  $('#fpTable').on('click', '[data-del]', async function () {
    if (await confirm({ title: 'Delete this price?', icon: 'warning', danger: true, confirmText: 'Delete' })) { await api('pricing.farm_delete', { id: $(this).data('del') }); dt.ajax.reload(null, false); }
  });
  $('#delSelBtn').on('click', async () => {
    if (await confirm({ title: `Delete ${sel.size} price(s)?`, icon: 'warning', danger: true, confirmText: 'Delete' })) { await api('pricing.farm_delete', { ids: [...sel] }); sel.clear(); syncBar(); dt.ajax.reload(null, false); }
  });
  $('#adjustBtn').on('click', () => modalForm({
    title: `Adjust ${sel.size} price(s)`, icon: 'bi-percent', size: 'md', submitText: 'Apply',
    fields: [
      { name: 'field', label: 'Price', type: 'combo', options: ['buying_price', 'selling_price'], required: true, col: 12, help: 'buying_price or selling_price' },
      { name: 'mode', label: 'How', type: 'combo', options: ['percent', 'amount', 'set'], required: true, col: 6, help: 'percent: +5 / -3 · amount: +0.02 · set: exact' },
      { name: 'value', label: 'Value', type: 'text', required: true, col: 6 }
    ], values: { field: 'buying_price', mode: 'percent' },
    onSubmit: async (d) => { await api('pricing.farm_adjust', Object.assign({ ids: [...sel] }, d)); dt.ajax.reload(null, false); }
  }));
  $('#copyBtn').on('click', () => modalForm({
    title: 'Copy a farm\'s price list', icon: 'bi-copy', size: 'md', submitText: 'Copy prices',
    fields: [{ name: 'from_farm_id', label: 'From farm', type: 'select', source: 'farms', required: true, col: 12 }, { name: 'to_farm_id', label: 'To farm', type: 'select', source: 'farms', required: true, col: 12, help: 'Existing prices on the target farm are kept' }],
    onSubmit: async (d) => { await api('pricing.farm_copy', d); dt.ajax.reload(); }
  }));
})();
<?php $pageInline = ob_get_clean(); ?>

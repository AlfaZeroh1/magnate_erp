<div class="page-head">
  <div class="title"><div class="ico"><i class="bi bi-currency-dollar"></i></div>
    <div><h2>Selling Prices</h2><div class="sub">General customer price per variety and length — e.g. <em>Explorer, 50 cm = USD 0.50</em>. New order lines start from this price (converted to the order currency).</div></div></div>
</div>
<div class="row g-3">
  <?php if (can('selling_prices.edit')): ?>
  <div class="col-lg-4">
    <div class="card"><div class="card-header"><h3><i class="bi bi-lightning-charge me-1 text-primary"></i>Set a selling price</h3></div>
      <div class="card-body">
        <form id="spForm" class="row g-3" novalidate>
          <div class="col-12"><label class="form-label">Variety <span class="req">*</span></label><select class="form-select" name="variety_id"></select></div>
          <div class="col-12"><label class="form-label">Length(s) <span class="req">*</span></label><select class="form-select" name="length_ids" multiple></select></div>
          <div class="col-5"><label class="form-label">Currency</label><select class="form-select" name="currency_id"></select></div>
          <div class="col-7"><label class="form-label">Price per stem <span class="req">*</span></label><input type="number" min="0" step="0.0001" class="form-control form-control-lg tabular" name="price"></div>
          <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="sync" name="sync_farms"><label class="form-check-label small" for="sync">Also overwrite the farm-specific selling price on every farm that prices this variety/length in the same currency</label></div></div>
          <div class="col-12" id="spContext"></div>
          <div class="col-12"><button class="btn btn-primary w-100"><i class="bi bi-check2 me-1"></i>Save price</button></div>
        </form>
      </div>
    </div>
  </div>
  <?php endif; ?>
  <div class="<?= can('selling_prices.edit') ? 'col-lg-8' : 'col-12' ?>">
    <div class="card"><div class="card-body">
      <table class="table table-hover w-100" id="spTable"><thead><tr><th>Variety</th><th>Length</th><th class="num">Selling price</th><th class="num">Farm buying range</th><th class="num">Margin</th><th class="num">Farms</th><th>Updated</th><th></th></tr></thead></table>
    </div></div>
  </div>
</div>
<?php ob_start(); ?>
(function () {
  const { get, api, esc, fmt, table, select2, setSelect, confirm, can, busy, MG } = App;
  const dt = table('#spTable', {
    ajax: (d, cb) => get('pricing.selling_list', {}, { quiet: true }).then(r => cb({ data: r })).catch(() => cb({ data: [] })),
    columns: [
      { data: 'variety', render: (v, t, r) => t === 'display' ? `<strong>${esc(v)}</strong><div class="small text-secondary">${esc(r.category || '')}</div>` : v },
      { data: 'length' },
      { data: 'price', className: 'num', render: (v, t, r) => t === 'display' ? `<span class="small text-secondary">${esc(r.currency)}</span> <strong>${fmt.money(v, 4)}</strong>` : +v },
      { data: null, className: 'num', render: (r) => r.min_buy !== null ? `${fmt.money(r.min_buy, 4)} – ${fmt.money(r.max_buy, 4)}` : '<span class="text-secondary small">no farms in ' + esc(r.currency) + '</span>' },
      { data: null, className: 'num', render: (r, t) => { if (r.max_buy === null || !+r.price) return ''; const m = (r.price - r.max_buy) / r.price * 100; return t === 'display' ? `<span class="${m < 0 ? 'text-danger' : (m < 15 ? 'text-warning' : 'text-success')} fw-semibold" title="Against the most expensive farm">≥ ${fmt.pct(m)}</span>` : m; } },
      { data: 'farms', className: 'num' },
      { data: 'updated_at', render: (v, t, r) => t === 'display' ? `<span class="small">${fmt.date(v)}<br><span class="text-secondary">${esc(r.updated_by_name || '')}</span></span>` : v },
      { data: null, orderable: false, className: 'actions', render: (r) => can('selling_prices.edit') ? `<button class="btn btn-sm btn-light" data-use='${esc(JSON.stringify({ variety_id: r.variety_id, variety: r.variety, length_id: r.length_id, length: r.length, currency_id: r.currency_id, price: r.price }))}' title="Edit"><i class="bi bi-pencil"></i></button><button class="btn btn-sm btn-light text-danger" data-del="${r.id}"><i class="bi bi-trash"></i></button>` : '' }
    ], order: [[0, 'asc']]
  });
  const $f = $('#spForm'); if (!$f.length) return;
  const F = (n) => $f.find(`[name="${n}"]`);
  select2(F('variety_id'), 'varieties', { required: true }); select2(F('length_ids'), 'lengths', {}); select2(F('currency_id'), 'currencies', { required: true });
  const usd = (MG.currencies || []).find(c => c.code === 'USD') || MG.currencies[0];
  setSelect(F('currency_id'), 'currencies', usd.id, usd.code);
  F('variety_id').on('change', () => {
    const v = F('variety_id').val(); if (!v) { $('#spContext').html(''); return; }
    const rows = dt.rows().data().toArray().filter(r => +r.variety_id === +v);
    $('#spContext').html(rows.length ? `<div class="small p-2 rounded" style="background:var(--mg-muted-bg)"><strong>Current:</strong> ${rows.map(r => `${esc(r.length)} → ${esc(r.currency)} ${fmt.money(r.price, 4)}`).join(' · ')}</div>` : '<div class="small text-secondary">No general prices yet for this variety.</div>');
  });
  $f.on('submit', async function (e) {
    e.preventDefault();
    const $b = $f.find('button'); busy($b, true, 'Saving…');
    try { await api('pricing.selling_save', { variety_id: F('variety_id').val(), length_ids: F('length_ids').val(), currency_id: F('currency_id').val(), price: F('price').val(), sync_farms: $('#sync').is(':checked') ? 1 : 0 }); F('price').val(''); dt.ajax.reload(null, false); F('variety_id').trigger('change'); }
    catch (err) { } finally { busy($b, false); }
  });
  $('#spTable').on('click', '[data-use]', async function () {
    const r = JSON.parse($(this).attr('data-use'));
    await setSelect(F('variety_id'), 'varieties', r.variety_id, r.variety);
    F('length_ids').empty().append(new Option(r.length, r.length_id, true, true)).trigger('change');
    await setSelect(F('currency_id'), 'currencies', r.currency_id);
    F('price').val(r.price).trigger('focus'); window.scrollTo({ top: 0, behavior: 'smooth' });
  });
  $('#spTable').on('click', '[data-del]', async function () {
    if (await confirm({ title: 'Remove this selling price?', icon: 'warning', danger: true, confirmText: 'Remove' })) { await api('pricing.selling_delete', { id: $(this).data('del') }); dt.ajax.reload(null, false); }
  });
})();
<?php $pageInline = ob_get_clean(); ?>

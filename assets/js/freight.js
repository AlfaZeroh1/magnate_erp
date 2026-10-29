/* Freight space: bookings list + booking detail. */
(function () {
  const { get, api, esc, fmt, num, table, modalForm, confirm, can, busy, MG } = App;
  const B = MG.base.code;

  const bookingForm = (values = {}, onSaved) => modalForm({
    title: values.id ? 'Edit booking ' + values.booking_no : 'Book freight space', icon: 'bi-airplane', values,
    fields: [
      { name: 'shipping_agent_id', label: 'Booked with (agent / airline)', type: 'select', source: 'shipping_agents', required: true, col: 12, fills: { currency_id: 'currency_id' } },
      { name: 'airline', label: 'Airline', type: 'text', col: 4 },
      { name: 'flight_no', label: 'Flight no', type: 'text', col: 4 },
      { name: 'awb_no', label: 'Master AWB', type: 'text', col: 4 },
      { name: 'origin', label: 'Origin', type: 'text', col: 3, default: 'NBO' },
      { name: 'destination', label: 'Destination', type: 'text', required: true, col: 3 },
      { name: 'departure_date', label: 'Departure', type: 'date', required: true, col: 6 },
      { name: 'capacity_kg', label: 'Capacity booked (kg)', type: 'number', required: true, col: 4 },
      { name: 'cost_per_kg', label: 'Cost per kg', type: 'money', required: true, col: 4 },
      { name: 'other_costs', label: 'Handling / other costs', type: 'money', col: 4, default: 0 },
      { name: 'currency_id', label: 'Currency', type: 'select', source: 'currencies', required: true, col: 6 },
      { name: 'exchange_rate', label: 'Exchange rate', type: 'rate', col: 6 },
      { name: 'notes', label: 'Notes', type: 'textarea', col: 12 }
    ],
    fx: { currency: 'currency_id', rate: 'exchange_rate', date: 'departure_date', amounts: {} },
    onReady: ($m, F) => {
      const $hint = $('<div class="col-12"><div class="p-2 rounded small" style="background:var(--mg-muted-bg)" id="bkTotal"></div></div>').appendTo($m.find('.row.g-3'));
      const upd = () => { const t = num(F('capacity_kg').val()) * num(F('cost_per_kg').val()) + num(F('other_costs').val()); const r = num(F('exchange_rate').val());
        $hint.find('#bkTotal').html(`Total cost <strong>${fmt.money(t)}</strong>${r ? ` ≈ ${B} ${fmt.money(t * r)}` : ''}${num(F('capacity_kg').val()) ? ` · <span class="text-secondary">${fmt.money(t / num(F('capacity_kg').val()), 4)} per kg all-in</span>` : ''}`); };
      $m.on('input change', 'input', upd); upd();
    },
    onSubmit: async (d) => { const r = await api('freight.save', Object.assign({ id: values.id || 0 }, d)); onSaved && onSaved(r); }
  });

  // ------------------------------------------------------------------ list
  if ($('#fbTable').length) {
    const util = (r) => { const u = +r.utilization; const c = u >= 90 ? 'success' : (u >= 60 ? 'warning' : 'danger');
      return `<div class="d-flex align-items-center gap-2" style="min-width:140px"><div class="progress flex-grow-1" style="height:8px"><div class="progress-bar bg-${c}" style="width:${Math.min(100, u)}%"></div></div><span class="small fw-semibold">${fmt.pct(u, 0)}</span></div><div class="small text-secondary">${fmt.qty(r.sold_kg)} kg sold</div>`; };
    const dt = table('#fbTable', {
      ajax: (d, cb) => get('freight.list', { status: $('#fStatus').val() }, { quiet: true }).then(r => { kpis(r); cb({ data: r }); }).catch(() => cb({ data: [] })),
      columns: [
        { data: 'booking_no', render: (v, t, r) => t === 'display' ? `<a class="fw-semibold" href="index.php?p=freight_booking&id=${r.id}">${esc(v)}</a>${r.awb_no ? `<div class="small text-secondary">AWB ${esc(r.awb_no)}</div>` : ''}` : v },
        { data: 'departure_date', render: (v, t) => t === 'display' ? fmt.date(v) : v },
        { data: null, render: (r) => `<strong>${esc(r.origin)} → ${esc(r.destination)}</strong><div class="small text-secondary">${esc(r.airline || '')} ${esc(r.flight_no || '')}</div>` },
        { data: 'agent' },
        { data: 'capacity_kg', className: 'num', render: (v) => fmt.qty(v) + ' kg' },
        { data: 'utilization', render: (v, t, r) => t === 'display' ? util(r) : +v },
        { data: 'base_cost', className: 'num', render: (v) => fmt.money(v, 0) },
        { data: 'sales_base', className: 'num', render: (v) => fmt.money(v, 0) },
        { data: 'margin_base', className: 'num', render: (v, t) => t === 'display' ? `<span class="fw-semibold ${v >= 0 ? 'text-success' : 'text-danger'}">${fmt.money(v, 0)}</span>` : +v },
        { data: 'status', render: (v) => `<span class="pill pill-${({ open: 'info', closed: 'success', cancelled: 'danger' })[v]}">${esc(v)}</span>` }
      ], order: [[1, 'desc']]
    });
    const kpis = (rows) => {
      const live = rows.filter(r => r.status !== 'cancelled');
      const cap = live.reduce((s, r) => s + num(r.capacity_kg), 0), sold = live.reduce((s, r) => s + num(r.sold_kg), 0);
      const margin = live.reduce((s, r) => s + num(r.margin_base), 0), sales = live.reduce((s, r) => s + num(r.sales_base), 0);
      const k = (label, val, icon, cls, meta = '') => `<div class="col-6 col-lg-3"><div class="card kpi h-100"><div class="card-body"><div class="ico ${cls}"><i class="bi ${icon}"></i></div><div class="label">${label}</div><div class="value">${val}</div><div class="meta">${meta}</div></div></div></div>`;
      $('#frKpis').html(k('Bookings', live.length, 'bi-airplane', 'ico-blue') + k('Space sold', fmt.qty(sold) + ' kg', 'bi-box2', 'ico-violet', `of ${fmt.qty(cap)} kg (${fmt.pct(cap ? sold / cap * 100 : 0, 0)})`)
        + k('Freight sales', B + ' ' + fmt.money(sales, 0), 'bi-cash-stack', 'ico-green') + k('Margin', B + ' ' + fmt.money(margin, 0), 'bi-graph-up-arrow', margin >= 0 ? 'ico-green' : 'ico-rose', sales ? fmt.pct(margin / sales * 100) + ' of sales' : ''));
    };
    $('#fStatus').on('change', () => dt.ajax.reload());
    $('#newBooking').on('click', () => bookingForm({}, (r) => location.href = 'index.php?p=freight_booking&id=' + r.id));
  }

  // ------------------------------------------------------------------ detail
  const $app = $('#bookingApp');
  if (!$app.length) return;
  const ID = +$app.data('id');
  let D;
  const load = async () => { D = await get('freight.get', { id: ID }, { quiet: true }); render(); };

  function render() {
    const b = D.booking; const open = b.status === 'open';
    const free = num(b.capacity_kg) - num(b.sold_kg);
    const byCust = {};
    D.allocations.forEach(a => { if (!a.invoice_id) (byCust[a.customer_id] = byCust[a.customer_id] || { name: a.customer, kg: 0, amt: 0, cur: a.currency }), byCust[a.customer_id].kg += num(a.chargeable_kg), byCust[a.customer_id].amt += num(a.amount); });
    $app.html(`
      <div class="card mb-3"><div class="card-body">
        <div class="d-flex flex-wrap gap-3 align-items-start">
          <div class="me-auto">
            <div class="d-flex align-items-center gap-2"><a href="index.php?p=freight" class="btn btn-light btn-sm btn-icon"><i class="bi bi-arrow-left"></i></a>
              <h2 class="h4 fw-bold mb-0">${esc(b.booking_no)}</h2><span class="pill pill-${({ open: 'info', closed: 'success', cancelled: 'danger' })[b.status]}">${esc(b.status)}</span></div>
            <div class="mt-2 small d-flex flex-wrap gap-3">
              <span><i class="bi bi-geo text-secondary me-1"></i><strong>${esc(b.origin)} → ${esc(b.destination)}</strong></span>
              <span><i class="bi bi-calendar-event text-secondary me-1"></i>${fmt.date(b.departure_date)}</span>
              <span><i class="bi bi-airplane text-secondary me-1"></i>${esc(b.airline || '')} ${esc(b.flight_no || '')}</span>
              <span><i class="bi bi-send-check text-secondary me-1"></i>${esc(b.agent)}</span>
              ${b.awb_no ? `<span>AWB <strong>${esc(b.awb_no)}</strong></span>` : ''}
            </div>
          </div>
          <div class="d-flex gap-2 flex-wrap">
            ${can('freight.edit') ? `<button class="btn btn-light" data-act="edit"><i class="bi bi-pencil me-1"></i>Edit</button>` : ''}
            ${can('freight.edit') && open ? `<button class="btn btn-light" data-act="close"><i class="bi bi-lock me-1"></i>Close</button>` : ''}
            ${can('freight.edit') && !open ? `<button class="btn btn-light" data-act="reopen"><i class="bi bi-unlock me-1"></i>Re-open</button>` : ''}
            ${can('freight.edit') && open ? `<button class="btn btn-primary" data-act="alloc"><i class="bi bi-plus-lg me-1"></i>Sell space</button>` : ''}
          </div>
        </div>
        <div class="row g-2 mt-3">
          ${[['Capacity', fmt.qty(b.capacity_kg) + ' kg'], ['Sold', fmt.qty(b.sold_kg) + ' kg'], ['Free', `<span class="${free > 0 ? 'text-warning' : 'text-success'}">${fmt.qty(free)} kg</span>`],
             ['Cost', b.currency + ' ' + fmt.money(b.total_cost) + `<div class="small text-secondary">${B} ${fmt.money(b.base_cost, 0)}</div>`],
             ['Sales', B + ' ' + fmt.money(b.sales_base, 0)], ['Margin', `<span class="${b.margin_base >= 0 ? 'text-success' : 'text-danger'}">${B} ${fmt.money(b.margin_base, 0)}</span>` + (b.break_even_kg ? `<div class="small text-secondary">break-even ${fmt.qty(b.break_even_kg)} kg</div>` : '')]]
            .map(([l, v]) => `<div class="col-6 col-md-4 col-xl-2"><div class="p-3 rounded-3 h-100" style="background:var(--mg-muted-bg)"><div class="small text-secondary">${l}</div><div class="fw-bold fs-6 tabular">${v}</div></div></div>`).join('')}
        </div>
        <div class="progress mt-3" style="height:10px"><div class="progress-bar bg-success" style="width:${Math.min(100, b.utilization)}%"></div></div>
        <div class="small text-secondary mt-1">${fmt.pct(b.utilization, 1)} utilised</div>
      </div></div>

      ${Object.keys(byCust).length && can('freight.invoice') ? `<div class="alert alert-warning d-flex flex-wrap gap-2 align-items-center"><i class="bi bi-receipt me-1"></i><span class="me-auto">Space not yet invoiced:</span>
        ${Object.entries(byCust).map(([cid, c]) => `<button class="btn btn-sm btn-warning" data-inv="${cid}"><i class="bi bi-receipt me-1"></i>Invoice ${esc(c.name)} · ${fmt.qty(c.kg)} kg · ${esc(c.cur)} ${fmt.money(c.amt)}</button>`).join('')}</div>` : ''}

      <div class="card mb-3"><div class="card-header"><h3>Space sold</h3></div><div class="card-body">
        ${D.allocations.length ? `<div class="table-responsive"><table class="table table-sm table-hover"><thead><tr><th>Customer</th><th>Goods / HAWB</th><th class="num">Boxes</th><th class="num">Gross kg</th><th class="num">Vol. kg</th><th class="num">Chargeable</th><th class="num">Rate/kg</th><th class="num">Extras</th><th class="num">Amount</th><th>Invoice</th><th></th></tr></thead><tbody>
          ${D.allocations.map(a => `<tr><td>${esc(a.customer)}</td><td>${esc(a.goods_description || '')}<div class="small text-secondary">${esc(a.hawb_no || '')}</div></td>
            <td class="num">${fmt.int(a.boxes)}</td><td class="num">${fmt.qty(a.gross_kg)}</td><td class="num">${fmt.qty(a.volume_kg)}</td><td class="num fw-semibold">${fmt.qty(a.chargeable_kg)}</td>
            <td class="num">${fmt.money(a.rate_per_kg, 4)}</td><td class="num">${fmt.money(a.extra_charges)}</td><td class="num">${esc(a.currency)} ${fmt.money(a.amount)}</td>
            <td>${a.invoice_no ? `<a target="_blank" href="print.php?doc=invoice&id=${a.invoice_id}" class="badge text-bg-success-subtle text-success-emphasis">${esc(a.invoice_no)}</a>` : '<span class="pill pill-warning">Open</span>'}</td>
            <td class="actions">${!a.invoice_id && can('freight.edit') && open ? `<button class="btn btn-sm btn-light" data-edit="${a.id}"><i class="bi bi-pencil"></i></button><button class="btn btn-sm btn-light text-danger" data-del="${a.id}"><i class="bi bi-trash"></i></button>` : ''}</td></tr>`).join('')}
        </tbody></table></div>` : '<div class="empty"><i class="bi bi-box2"></i>No space sold yet.</div>'}
      </div></div>
      <div class="card"><div class="card-header d-flex"><h3 class="me-auto">Extra costs on this booking</h3>${can('expenses.create') ? '<button class="btn btn-sm btn-outline-primary" data-act="expense"><i class="bi bi-plus-lg me-1"></i>Add expense</button>' : ''}</div><div class="card-body">
        ${D.expenses.length ? `<table class="table table-sm"><thead><tr><th>Date</th><th>Type</th><th>Description</th><th class="num">Amount</th><th class="num">${B}</th></tr></thead><tbody>${D.expenses.map(e => `<tr><td>${fmt.date(e.expense_date)}</td><td>${esc(e.type)}</td><td>${esc(e.description)}</td><td class="num">${esc(e.currency)} ${fmt.money(e.amount)}</td><td class="num">${fmt.money(e.base_amount)}</td></tr>`).join('')}</tbody></table>` : '<div class="text-secondary small">Trucking to the airport, screening, documentation… link them here so the margin is real.</div>'}
      </div></div>`);
  }

  const allocForm = (a = {}) => modalForm({
    title: a.id ? 'Edit space sale' : 'Sell space on ' + D.booking.booking_no, icon: 'bi-box2', values: Object.assign({ currency_id: D.booking.currency_id }, a, a.id ? { customer_id__label: a.customer } : {}),
    fields: [
      { name: 'customer_id', label: 'Customer', type: 'select', source: 'customers', required: true, col: 8, fills: { currency_id: 'currency_id' } },
      { name: 'hawb_no', label: 'House AWB', type: 'text', col: 4 },
      { name: 'goods_description', label: 'Goods', type: 'text', col: 12, help: 'e.g. Fresh cut roses, 40 boxes' },
      { name: 'boxes', label: 'Boxes', type: 'number', col: 3 },
      { name: 'dims', label: 'Box L×W×H cm (optional)', type: 'text', col: 5, help: 'Fills volumetric weight' },
      { name: 'gross_kg', label: 'Gross kg', type: 'number', col: 2 },
      { name: 'volume_kg', label: 'Vol. kg', type: 'number', col: 2 },
      { name: 'rate_per_kg', label: 'Rate per kg', type: 'money', required: true, col: 4 },
      { name: 'extra_charges', label: 'Handling / docs', type: 'money', col: 4, default: 0 },
      { name: 'currency_id', label: 'Currency', type: 'select', source: 'currencies', required: true, col: 4 },
    ],
    onReady: ($m, F) => {
      const $sum = $('<div class="col-12"><div class="p-2 rounded small" style="background:var(--mg-muted-bg)" id="alSum"></div></div>').appendTo($m.find('.row.g-3'));
      const upd = () => {
        const dims = (F('dims').val() || '').split(/[x×*\s]+/).map(num).filter(Boolean);
        if (dims.length === 3 && num(F('boxes').val())) F('volume_kg').val((dims[0] * dims[1] * dims[2] / D.divisor * num(F('boxes').val())).toFixed(2));
        const ch = Math.max(num(F('gross_kg').val()), num(F('volume_kg').val()));
        const amt = ch * num(F('rate_per_kg').val()) + num(F('extra_charges').val());
        const free = num(D.booking.capacity_kg) - num(D.booking.sold_kg) + num(a.chargeable_kg || 0);
        $sum.find('#alSum').html(`Chargeable <strong>${fmt.qty(ch)} kg</strong> · Amount <strong>${fmt.money(amt)}</strong> · <span class="${ch > free ? 'text-danger fw-semibold' : 'text-secondary'}">${fmt.qty(free)} kg free</span>`);
      };
      $m.on('input change', 'input', upd); upd();
    },
    onSubmit: async (d) => { delete d.dims; await api('freight.alloc_save', Object.assign({ id: a.id || 0, booking_id: ID }, d)); load(); }
  });

  $app.on('click', '[data-act]', async function () {
    const act = $(this).data('act'), b = D.booking;
    try {
      if (act === 'edit') bookingForm(Object.assign({}, b, { shipping_agent_id__label: b.agent, currency_id__label: b.currency }), load);
      if (act === 'alloc') allocForm();
      if (act === 'close' && await confirm({ title: 'Close booking?', text: 'No more space can be sold on it.', confirmText: 'Close' })) { await api('freight.status', { id: ID, status: 'closed' }); load(); }
      if (act === 'reopen') { await api('freight.status', { id: ID, status: 'open' }); load(); }
      if (act === 'expense') {
        const meta = await get('resource.meta', { _resource: 'expenses' });
        modalForm({ title: 'Expense on ' + b.booking_no, icon: 'bi-credit-card-2-back', fields: meta.fields, fx: meta.fx,
          values: { freight_booking_id: ID, freight_booking_id__label: b.booking_no, shipping_agent_id: b.shipping_agent_id, shipping_agent_id__label: b.agent },
          onSubmit: async (d) => { await api('resource.save', Object.assign({}, d, { _resource: 'expenses', id: 0 })); load(); } });
      }
    } catch (e) { }
  });
  $app.on('click', '[data-edit]', function () { allocForm(D.allocations.find(a => +a.id === +$(this).data('edit'))); });
  $app.on('click', '[data-del]', async function () { if (await confirm({ title: 'Remove this space sale?', icon: 'warning', danger: true, confirmText: 'Remove' })) { await api('freight.alloc_delete', { id: $(this).data('del') }); load(); } });
  $app.on('click', '[data-inv]', async function () {
    if (!(await confirm({ title: 'Create freight invoice?', text: 'All un-invoiced space for this customer on this booking is billed.', confirmText: 'Create invoice' }))) return;
    busy(this, true, 'Invoicing…');
    try { const r = await api('freight.invoice', { booking_id: ID, customer_id: $(this).data('inv') }); window.open('print.php?doc=invoice&id=' + r.invoice_id, '_blank'); load(); } catch (e) { busy(this, false); }
  });
  load();
})();

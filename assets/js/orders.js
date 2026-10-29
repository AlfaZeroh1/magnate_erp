/* Orders list + "new order" modal (shared with the order workspace via App.orderForm). */
(function () {
  const { api, get, esc, fmt, modalForm, stagePill, select2, table, can, MG } = App;

  /** Header form for create & edit. */
  App.orderForm = function (values = {}, onSaved) {
    const fields = [
      { name: 'customer_id', label: 'Customer', type: 'select', source: 'customers', required: true, col: 12, fills: { currency_id: 'currency_id', drop_point_id: 'drop_point_id' } },
      { name: 'order_date', label: 'Order date', type: 'date', required: true, default: 'today' },
      { name: 'departure_date', label: 'Departure date', type: 'date', required: true },
      { name: 'currency_id', label: 'Currency', type: 'select', source: 'currencies', required: true, help: 'Defaults to the customer\'s currency' },
      { name: 'box_type_id', label: 'Box type', type: 'select', source: 'box_types' },
      { name: 'shipping_agent_id', label: 'Shipping agent', type: 'select', source: 'shipping_agents' },
      { name: 'drop_point_id', label: 'Drop point', type: 'select', source: 'drop_points' },
      { name: 'customer_ref', label: 'Customer reference / PO', type: 'text', col: 12 },
      { name: 'remarks', label: 'Remarks', type: 'textarea', col: 12 }
    ];
    modalForm({
      title: values.id ? `Edit order ${values.order_no}` : 'New order', icon: 'bi-flower1', fields, values,
      submitText: values.id ? 'Save changes' : 'Create order',
      onSubmit: async (data) => { const r = await api('orders.save', Object.assign({ id: values.id || 0 }, data)); onSaved && onSaved(r); }
    });
  };

  const $t = $('#ordersTable');
  if (!$t.length) return;

  let stage = 'active';
  const params = new URLSearchParams(location.search);
  if (params.get('due')) $('#fDue').prop('checked', true);

  select2('#fCustomer', 'customers', { placeholder: 'All customers' });

  const dt = table($t, {
    ajax: (d, cb) => get('orders.list', { stage, from: $('#fFrom').val(), to: $('#fTo').val(), customer_id: $('#fCustomer').val(), due: $('#fDue').is(':checked') ? 1 : '' }, { quiet: true })
      .then(rows => cb({ data: rows })).catch(() => cb({ data: [] })),
    search: { search: params.get('q') || '' },
    columns: [
      { data: 'order_no', render: (v, t, r) => t === 'display' ? `<a class="fw-semibold" href="index.php?p=order&id=${r.id}">${esc(v)}</a>${r.customer_ref ? `<div class="small text-secondary">${esc(r.customer_ref)}</div>` : ''}` : v },
      { data: 'customer', render: (v, t, r) => t === 'display' ? `${esc(v)}<div class="small text-secondary">${esc(r.shipping_agent || '')}</div>` : v },
      { data: 'order_date', render: (v, t) => t === 'display' ? fmt.date(v) : v },
      { data: 'departure_date', render: (v, t) => t === 'display' ? `<span class="fw-semibold">${fmt.date(v)}</span>` : v },
      { data: 'stage', render: (v, t) => t === 'display' ? stagePill(v) : v },
      { data: 'lines_count', className: 'num' },
      { data: 'stems', className: 'num', render: (v, t, r) => t === 'display' ? `${fmt.int(v)}${+r.received ? `<div class="small text-secondary">${fmt.int(r.received)} rcvd</div>` : ''}` : v },
      { data: 'value', className: 'num', render: (v, t, r) => t === 'display' ? `<span class="small text-secondary">${esc(r.currency)}</span> ${fmt.money(r.invoice_total ?? v)}` : (r.invoice_total ?? v) },
      { data: null, orderable: false, render: (r) => [r.packing_list_no ? `<span class="badge text-bg-light">${esc(r.packing_list_no)}</span>` : '', r.invoice_no ? `<span class="badge text-bg-success-subtle text-success-emphasis">${esc(r.invoice_no)}</span>` : ''].join(' ') },
      { data: null, orderable: false, className: 'actions', render: (r) => `
          <a class="btn btn-sm btn-light" href="index.php?p=order&id=${r.id}" title="Open"><i class="bi bi-arrow-right-circle"></i></a>
          ${can('reports.view') ? `<a class="btn btn-sm btn-light" href="index.php?p=order_journey&id=${r.id}" title="Journey report"><i class="bi bi-signpost-split"></i></a>` : ''}` }
    ],
    order: [[3, 'desc']]
  });

  const loadCounts = () => get('orders.counts', {}, { quiet: true }).then(c => {
    Object.entries(c).forEach(([k, n]) => $(`[data-count="${k}"]`).text(n || ''));
    $('[data-count="active"]').text(['draft', 'sourcing', 'confirmed', 'received', 'packing'].reduce((s, k) => s + (c[k] || 0), 0) || '');
  });
  loadCounts();

  $('#stageTabs').on('click', '[data-stage]', function (e) {
    e.preventDefault();
    $('#stageTabs .nav-link').removeClass('active'); $(this).addClass('active');
    stage = $(this).data('stage'); dt.ajax.reload();
  });
  $('#fFrom,#fTo,#fCustomer,#fDue').on('change', () => dt.ajax.reload());
  $('#newOrder').on('click', () => App.orderForm({}, (r) => location.href = 'index.php?p=order&id=' + r.id));
  if (params.get('new') && can('orders.create')) $('#newOrder').trigger('click');
  if (params.get('stage')) $(`#stageTabs [data-stage="${params.get('stage')}"]`).trigger('click');
})();

/* Order workspace: the whole pipeline for one order on one page. */
(function () {
  const { api, get, esc, fmt, num, modalForm, stagePill, select2, setSelect, confirm, busy, can, toast, MG } = App;
  const $app = $('#orderApp');
  const ORDER_ID = +$app.data('id');
  const FLOW = ['draft', 'sourcing', 'confirmed', 'received', 'packing', 'invoiced', 'closed'];
  let D = null;              // last orders.get payload
  let activeTab = location.hash ? location.hash.slice(1) : null;

  const idx = (s) => FLOW.indexOf(s);
  const doc = (name, extra = '') => `print.php?doc=${name}&id=${ORDER_ID}${extra}`;
  const cur = () => D.order.currency_code;

  async function load() {
    try { D = await get('orders.get', { id: ORDER_ID }, { quiet: true }); render(); }
    catch (e) { $app.html(`<div class="card"><div class="card-body empty"><i class="bi bi-exclamation-triangle"></i>${esc(e.message || 'Could not load order')}</div></div>`); }
  }

  // ------------------------------------------------------------------
  //  Render
  // ------------------------------------------------------------------
  function render() {
    const o = D.order, s = idx(o.stage);
    if (!activeTab) activeTab = ({ draft: 'lines', sourcing: 'confirm', confirmed: 'delivery', received: 'packing', packing: 'packing', invoiced: 'money', closed: 'money', cancelled: 'lines' })[o.stage] || 'lines';

    const docs = [
      ['customer_order', 'Customer order', 'bi-file-earmark-text', true],
      ['farm_order', 'Farm orders (all farms)', 'bi-files', s >= 1],
      ['confirmed_order', 'Confirmed order', 'bi-patch-check', s >= 2],
      ['delivery_note', 'Delivery check note', 'bi-clipboard2-check', s >= 3],
      ['packing_list', 'Packing list', 'bi-box-seam', s >= 4],
    ].filter(d => d[3]).map(d => `<li><a class="dropdown-item" target="_blank" href="${doc(d[0])}"><i class="bi ${d[2]} me-2"></i>${d[1]}</a></li>`).join('')
      + (D.invoice && D.invoice.status === 'open' ? `<li><a class="dropdown-item" target="_blank" href="print.php?doc=invoice&id=${D.invoice.id}"><i class="bi bi-receipt me-2"></i>Invoice ${esc(D.invoice.invoice_no)}</a></li>` : '');

    const steps = [
      ['draft', 'Captured', 'bi-pencil-square', o.created_at], ['sourcing', 'Sent to farms', 'bi-send', o.sourcing_at],
      ['confirmed', 'Farms confirmed', 'bi-patch-check', o.confirmed_at], ['received', 'Delivery checked', 'bi-clipboard2-check', o.received_at],
      ['packing', 'Packed', 'bi-box-seam', o.packed_at], ['invoiced', 'Invoiced', 'bi-receipt', o.invoiced_at], ['closed', 'Closed', 'bi-lock', o.closed_at]
    ].map(([k, label, icon, when], i) => {
      const cls = o.stage === 'cancelled' ? '' : (i < s ? 'done' : (i === s ? 'current' : ''));
      return `<div class="step ${cls}"><div class="dot"><i class="bi ${i < s && o.stage !== 'cancelled' ? 'bi-check-lg' : icon}"></i></div><div class="label">${label}</div><div class="when">${when ? fmt.date(when) : ''}</div></div>`;
    }).join('');

    const lineCount = D.lines.length;
    const tabs = [
      ['lines', 'Lines & sourcing', 'bi-list-ul', lineCount],
      ['farms', 'Farm orders', 'bi-tree', D.farm_orders.length],
      ['confirm', 'Farm confirmation', 'bi-patch-check', null],
      ['delivery', 'Delivery check', 'bi-clipboard2-check', null],
      ['packing', 'Packing & invoice', 'bi-box-seam', null],
      ['money', 'Costs & profit', 'bi-graph-up-arrow', null],
      ['timeline', 'Timeline', 'bi-clock-history', null],
    ];

    $app.html(`
      <div class="card mb-3 fade-in">
        <div class="card-body">
          <div class="d-flex flex-wrap gap-3 align-items-start">
            <div class="me-auto">
              <div class="d-flex align-items-center gap-2 flex-wrap">
                <a href="index.php?p=orders" class="btn btn-light btn-sm btn-icon" title="Back"><i class="bi bi-arrow-left"></i></a>
                <h2 class="h4 fw-bold mb-0">${esc(o.order_no)}</h2>${stagePill(o.stage)}
                ${o.customer_ref ? `<span class="badge text-bg-light">Ref ${esc(o.customer_ref)}</span>` : ''}
              </div>
              <div class="mt-2 d-flex flex-wrap gap-3 small">
                <span><i class="bi bi-person text-secondary me-1"></i><strong>${esc(o.customer_name)}</strong> ${o.country ? '· ' + esc(o.country) : ''}</span>
                <span><i class="bi bi-calendar-event text-secondary me-1"></i>Ordered ${fmt.date(o.order_date)}</span>
                <span><i class="bi bi-airplane text-secondary me-1"></i>Departs <strong>${fmt.date(o.departure_date)}</strong></span>
                <span><i class="bi bi-cash text-secondary me-1"></i>${esc(o.currency_code)} @ ${fmt.rate(o.exchange_rate)}</span>
                ${o.shipping_agent ? `<span><i class="bi bi-send-check text-secondary me-1"></i>${esc(o.shipping_agent)}</span>` : ''}
                ${o.box_type ? `<span><i class="bi bi-box text-secondary me-1"></i>${esc(o.box_type)}</span>` : ''}
                ${o.drop_point ? `<span><i class="bi bi-geo-alt text-secondary me-1"></i>${esc(o.drop_point)}</span>` : ''}
              </div>
              ${o.remarks ? `<div class="small text-secondary mt-1"><i class="bi bi-chat-left-text me-1"></i>${esc(o.remarks)}</div>` : ''}
            </div>
            <div class="d-flex flex-wrap gap-2">
              <div class="dropdown">
                <button class="btn btn-green dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-printer me-1"></i>Documents</button>
                <ul class="dropdown-menu dropdown-menu-end">${docs}</ul>
              </div>
              <div class="dropdown">
                <button class="btn btn-light btn-icon" data-bs-toggle="dropdown" title="More"><i class="bi bi-three-dots-vertical"></i></button>
                <ul class="dropdown-menu dropdown-menu-end">
                  ${D.can.edit && s <= 3 && o.stage !== 'cancelled' ? '<li><a class="dropdown-item" href="#" data-act="edit"><i class="bi bi-pencil me-2"></i>Edit details</a></li>' : ''}
                  ${can('orders.create') ? '<li><a class="dropdown-item" href="#" data-act="duplicate"><i class="bi bi-copy me-2"></i>Repeat this order</a></li>' : ''}
                  ${can('reports.view') ? `<li><a class="dropdown-item" href="index.php?p=order_journey&id=${ORDER_ID}"><i class="bi bi-signpost-split me-2"></i>Order journey report</a></li>` : ''}
                  ${D.can.reopen && ['sourcing', 'confirmed', 'received', 'packing', 'closed'].includes(o.stage) ? '<li><a class="dropdown-item" href="#" data-act="reopen"><i class="bi bi-arrow-counterclockwise me-2"></i>Re-open one step</a></li>' : ''}
                  ${D.can.reopen && s < 5 && o.stage !== 'cancelled' ? '<li><a class="dropdown-item text-warning" href="#" data-act="cancel"><i class="bi bi-x-octagon me-2"></i>Cancel order</a></li>' : ''}
                  ${D.can.delete && ['draft', 'sourcing', 'cancelled'].includes(o.stage) ? '<li><hr class="dropdown-divider"></li><li><a class="dropdown-item text-danger" href="#" data-act="delete"><i class="bi bi-trash me-2"></i>Delete order</a></li>' : ''}
                </ul>
              </div>
            </div>
          </div>
          <div class="stepper mt-4">${steps}</div>
          ${nextStepBanner()}
        </div>
      </div>

      <div class="card">
        <div class="card-header pb-0 pt-1">
          <ul class="nav nav-tabs-modern border-0">
            ${tabs.map(([k, label, icon, badge]) => `<li class="nav-item"><a class="nav-link ${k === activeTab ? 'active' : ''}" href="#${k}" data-tab="${k}"><i class="bi ${icon} me-1"></i>${label}${badge ? ` <span class="badge rounded-pill text-bg-light">${badge}</span>` : ''}</a></li>`).join('')}
          </ul>
        </div>
        <div class="card-body" id="tabBody"></div>
      </div>`);
    renderTab();
  }

  function nextStepBanner() {
    const o = D.order;
    const map = {
      draft: ['bi-1-circle', 'Add the lines the customer ordered and split each across farms. When every line is fully allocated, send the farm orders.', D.can.edit && D.lines.length ? '<button class="btn btn-primary btn-sm" data-act="send"><i class="bi bi-send me-1"></i>Send to farms</button>' : ''],
      sourcing: ['bi-2-circle', 'Farms are reviewing their orders. Record what each farm confirms it can supply, then complete the confirmation.', D.can.confirm ? '<button class="btn btn-primary btn-sm" data-goto="confirm">Record confirmations</button>' : ''],
      confirmed: ['bi-3-circle', 'Flowers are arriving. Count what each farm actually delivered against what it confirmed — this checksum drives the packing list, invoice and farm bills.', D.can.receive ? '<button class="btn btn-primary btn-sm" data-goto="delivery">Start delivery check</button>' : ''],
      received: ['bi-4-circle', 'Delivery is checked. Create the packing list from the received quantities.', D.can.pack ? '<button class="btn btn-primary btn-sm" data-act="pack"><i class="bi bi-box-seam me-1"></i>Create packing list</button>' : ''],
      packing: ['bi-5-circle', 'Complete the packing details (AWB, weights, drop point) and raise the customer invoice.', D.can.invoice ? '<button class="btn btn-primary btn-sm" data-goto="packing">Go to packing &amp; invoice</button>' : ''],
      invoiced: ['bi-6-circle', 'Invoiced. Record extra costs and expenses against this order to see the final profit, then close it.', D.can.invoice ? '<button class="btn btn-outline-primary btn-sm" data-act="close"><i class="bi bi-lock me-1"></i>Close order</button>' : ''],
    };
    const m = map[o.stage];
    if (!m) return o.stage === 'cancelled' ? '<div class="alert alert-danger mt-3 mb-0 small"><i class="bi bi-x-octagon me-1"></i>This order was cancelled.</div>' : '';
    return `<div class="alert alert-light border mt-3 mb-0 d-flex align-items-center gap-3 flex-wrap"><i class="bi ${m[0]} fs-4 text-primary"></i><div class="small flex-grow-1" style="min-width:220px">${m[1]}</div>${m[2]}</div>`;
  }

  function renderTab() {
    const $b = $('#tabBody');
    ({ lines: tabLines, farms: tabFarms, confirm: tabConfirm, delivery: tabDelivery, packing: tabPacking, money: tabMoney, timeline: tabTimeline }[activeTab] || tabLines)($b);
  }

  // ------------------------------------------------------------------
  //  TAB: Lines
  // ------------------------------------------------------------------
  function tabLines($b) {
    const editable = D.editable;
    let rows = '';
    let tStems = 0, tValue = 0;
    D.lines.forEach(l => {
      const value = num(l.stems) * num(l.selling_price); tStems += num(l.stems); tValue += value;
      rows += `<tr class="line-row">
        <td>${esc(l.variety)} <span class="text-secondary fw-normal small">${esc(l.category || '')}</span></td><td>${esc(l.length)}</td><td>${esc(l.order_by || '')}</td>
        <td class="num">${fmt.int(l.stems)}</td><td class="num">${fmt.money(l.selling_price, 4)}</td><td class="num">${fmt.money(value)}</td>
        <td class="num" colspan="2"></td>
        <td class="actions">${editable ? `<button class="btn btn-sm btn-light btn-icon" data-line-edit="${l.id}" title="Edit"><i class="bi bi-pencil"></i></button><button class="btn btn-sm btn-light btn-icon text-danger" data-line-del="${l.id}" title="Delete"><i class="bi bi-trash"></i></button>` : ''}</td></tr>`;
      l.allocations.forEach(a => {
        rows += `<tr class="alloc-row small"><td colspan="3"><i class="bi bi-arrow-return-right text-secondary me-1"></i>${esc(a.farm)}</td>
          <td class="num">${fmt.int(a.allocated_stems)}</td><td class="num text-secondary">${esc(a.currency_code)} ${fmt.money(a.buying_price, 4)}</td><td></td>
          <td class="num">${a.confirmed_stems !== null ? fmt.int(a.confirmed_stems) : '<span class="text-secondary">—</span>'}</td>
          <td class="num">${a.received_stems !== null ? fmt.int(a.received_stems) : '<span class="text-secondary">—</span>'}</td><td></td></tr>`;
      });
    });
    $b.html(`
      <div class="d-flex flex-wrap gap-2 mb-3 align-items-center">
        <div class="small text-secondary me-auto">${D.lines.length} line(s) · ${fmt.int(tStems)} stems · ${esc(cur())} ${fmt.money(tValue)}</div>
        ${editable ? '<button class="btn btn-primary btn-sm" id="addLine"><i class="bi bi-plus-lg me-1"></i>Add line</button>' : ''}
      </div>
      ${D.lines.length ? `<div class="table-responsive"><table class="table table-sm">
        <thead><tr><th>Variety</th><th>Length</th><th>Order by</th><th class="num">Stems</th><th class="num">Price (${esc(cur())}) / Buy</th><th class="num">Value</th><th class="num">Confirmed</th><th class="num">Received</th><th></th></tr></thead>
        <tbody>${rows}</tbody>
        <tfoot><tr><th colspan="3">Total</th><th class="num">${fmt.int(tStems)}</th><th></th><th class="num">${fmt.money(tValue)}</th><th class="num">${fmt.int(D.lines.reduce((s, l) => s + l.allocations.reduce((x, a) => x + num(a.confirmed_stems), 0), 0))}</th><th class="num">${fmt.int(D.lines.reduce((s, l) => s + l.allocations.reduce((x, a) => x + num(a.received_stems), 0), 0))}</th><th></th></tr></tfoot>
      </table></div>` : `<div class="empty"><i class="bi bi-flower3"></i>No lines yet.${editable ? ' Click <strong>Add line</strong> to capture what the customer ordered.' : ''}</div>`}`);
  }

  /** Line modal with farm allocation grid. */
  function lineModal(line = null) {
    const o = D.order;
    const html = `
      <div class="modal fade" tabindex="-1" data-bs-backdrop="static"><div class="modal-dialog modal-xl modal-dialog-scrollable modal-fullscreen-md-down"><form class="modal-content" novalidate>
        <div class="modal-header"><h5 class="modal-title"><i class="bi bi-flower3 text-primary"></i>${line ? 'Edit line' : 'Add line'}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-4"><label class="form-label">Variety <span class="req">*</span></label><select class="form-select" name="variety_id"></select></div>
            <div class="col-md-2"><label class="form-label">Length <span class="req">*</span></label><select class="form-select" name="length_id"></select></div>
            <div class="col-md-2"><label class="form-label">Order by</label><select class="form-select" name="order_by_id"></select></div>
            <div class="col-md-2"><label class="form-label">Stems <span class="req">*</span></label><input type="number" min="1" step="1" class="form-control tabular" name="stems" value="${line ? line.stems : ''}"></div>
            <div class="col-md-2"><label class="form-label">Selling price (${esc(o.currency_code)})</label><input type="number" min="0" step="0.0001" class="form-control tabular" name="selling_price" value="${line ? line.selling_price : ''}"><div class="form-text small" id="spSource"></div></div>
            <div class="col-12"><label class="form-label">Remarks</label><input class="form-control" name="remarks" value="${line ? esc(line.remarks || '') : ''}"></div>
          </div>
          <hr>
          <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
            <h6 class="fw-bold mb-0 me-auto">Allocate to farms</h6>
            <button type="button" class="btn btn-sm btn-light" id="splitEven"><i class="bi bi-distribute-horizontal me-1"></i>Split evenly</button>
            <button type="button" class="btn btn-sm btn-light" id="fillCheapest"><i class="bi bi-sort-numeric-down me-1"></i>All to cheapest</button>
          </div>
          <div class="table-responsive"><table class="table table-sm align-middle" id="allocTable">
            <thead><tr><th>Farm</th><th class="num">Buying price</th><th class="num">Allocated stems</th><th class="num">Cost</th><th></th></tr></thead><tbody></tbody>
          </table></div>
          <div class="d-flex flex-wrap gap-2 align-items-center">
            <div style="min-width:260px" class="flex-grow-1"><select class="form-select form-select-sm" id="addFarm"></select></div>
            <div class="small" id="allocStatus"></div>
          </div>
          <div class="invalid-feedback d-block" data-err="allocations"></div>
          <div class="mt-3 p-2 rounded small" style="background:var(--mg-muted-bg)" id="marginHint"></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary px-4" type="submit"><i class="bi bi-check2 me-1"></i>Save line</button></div>
      </form></div></div>`;
    const $m = $(html).appendTo('body'); const modal = new bootstrap.Modal($m[0]); const $f = $m.find('form');
    const F = (n) => $f.find(`[name="${n}"]`);
    select2(F('variety_id'), 'varieties', { required: true, parent: $m });
    select2(F('length_id'), 'lengths', { required: true, parent: $m });
    select2(F('order_by_id'), 'order_by', { parent: $m });
    select2('#addFarm', 'farms', { parent: $m, placeholder: '+ Add another farm (not on the price list)' });
    let farms = [];   // [{farm_id, farm, buying_price, currency_id, currency_code, allocated}]

    const drawAlloc = () => {
      const stems = num(F('stems').val());
      $m.find('#allocTable tbody').html(farms.map((f, i) => `<tr>
        <td>${esc(f.farm)}</td>
        <td class="num"><div class="input-group input-group-sm justify-content-end"><span class="input-group-text">${esc(f.currency_code || '')}</span><input type="number" step="0.0001" min="0" class="form-control cell-input" data-bp="${i}" value="${f.buying_price ?? ''}"></div></td>
        <td class="num"><input type="number" step="1" min="0" class="form-control form-control-sm cell-input ms-auto" data-al="${i}" value="${f.allocated || ''}"></td>
        <td class="num small">${f.allocated ? esc(f.currency_code || '') + ' ' + fmt.money(num(f.allocated) * num(f.buying_price)) : ''}</td>
        <td class="text-end"><button type="button" class="btn btn-sm btn-light btn-icon" data-rm="${i}"><i class="bi bi-x"></i></button></td></tr>`).join('')
        || '<tr><td colspan="5" class="text-center text-secondary small py-3">Choose variety and length to see farms that supply it.</td></tr>');
      drawStatus();
    };
    const drawStatus = () => {
      const stems = num(F('stems').val());
      const total = farms.reduce((s, f) => s + num(f.allocated), 0);
      const ok = stems > 0 && total === stems;
      $m.find('#allocStatus').html(`<span class="${ok ? 'text-success' : 'text-danger'} fw-semibold"><i class="bi ${ok ? 'bi-check-circle' : 'bi-exclamation-circle'} me-1"></i>${fmt.int(total)} of ${fmt.int(stems)} stems allocated</span>`);
      // margin preview in order currency (approx — uses buying price only when currencies match)
      const sp = num(F('selling_price').val());
      if (sp > 0 && total > 0) {
        const same = farms.filter(f => f.currency_code === D.order.currency_code);
        if (same.length === farms.length) {
          const cost = farms.reduce((s, f) => s + num(f.allocated) * num(f.buying_price), 0);
          const rev = sp * total; const m = rev - cost;
          $m.find('#marginHint').html(`<i class="bi bi-graph-up-arrow me-1 text-success"></i>Revenue ${esc(cur())} ${fmt.money(rev)} · Cost ${fmt.money(cost)} · <strong>Gross margin ${fmt.money(m)} (${fmt.pct(rev ? m / rev * 100 : 0)})</strong>`).show();
        } else $m.find('#marginHint').html('<i class="bi bi-info-circle me-1"></i>Farms price in a different currency — the margin is shown on the Costs & profit tab.').show();
      } else $m.find('#marginHint').hide();
    };

    const loadFarms = async (keep = false) => {
      const v = F('variety_id').val(), l = F('length_id').val();
      if (!v || !l) { drawAlloc(); return; }
      const h = await get('orders.price_hint', { variety_id: v, length_id: l, currency_id: D.order.currency_id, date: D.order.order_date }, { quiet: true });
      if (!keep) {
        farms = h.farms.map(f => ({ farm_id: +f.farm_id, farm: f.farm, buying_price: f.buying_price, currency_id: f.currency_id, currency_code: f.currency_code, allocated: 0 }));
        if (h.selling_price !== null && !F('selling_price').val()) F('selling_price').val(h.selling_price);
      } else {
        // editing: merge price list farms that are not yet in the line
        h.farms.forEach(f => { if (!farms.find(x => x.farm_id === +f.farm_id)) farms.push({ farm_id: +f.farm_id, farm: f.farm, buying_price: f.buying_price, currency_id: f.currency_id, currency_code: f.currency_code, allocated: 0 }); });
      }
      $m.find('#spSource').text(h.source || (h.farms.length ? '' : 'No price list for this variety/length'));
      if (farms.length === 1 && !keep && num(F('stems').val())) farms[0].allocated = num(F('stems').val());
      drawAlloc();
    };

    if (line) {
      farms = line.allocations.map(a => ({ farm_id: +a.farm_id, farm: a.farm, buying_price: a.buying_price, currency_id: a.currency_id, currency_code: a.currency_code, allocated: +a.allocated_stems }));
      Promise.all([setSelect(F('variety_id'), 'varieties', line.variety_id, line.variety), setSelect(F('length_id'), 'lengths', line.length_id, line.length),
        line.order_by_id ? setSelect(F('order_by_id'), 'order_by', line.order_by_id, line.order_by) : null]).then(() => loadFarms(true));
    }
    F('variety_id').on('select2:select', () => { F('selling_price').val(''); loadFarms(); });
    F('length_id').on('select2:select', () => { F('selling_price').val(''); loadFarms(); });
    F('stems').on('input', () => { if (farms.length === 1) { farms[0].allocated = num(F('stems').val()); $m.find('[data-al="0"]').val(farms[0].allocated || ''); } drawStatus(); });
    F('selling_price').on('input', drawStatus);
    $m.on('input', '[data-al]', function () { farms[+$(this).data('al')].allocated = Math.max(0, Math.round(num(this.value))); drawStatus(); });
    $m.on('change', '[data-al]', drawAlloc);
    $m.on('change', '[data-bp]', function () { farms[+$(this).data('bp')].buying_price = this.value; drawAlloc(); });
    $m.on('click', '[data-rm]', function () { farms.splice(+$(this).data('rm'), 1); drawAlloc(); });
    $m.find('#addFarm').on('select2:select', (e) => {
      const d = e.params.data;
      if (!farms.find(f => f.farm_id === +d.id)) farms.push({ farm_id: +d.id, farm: d.text, buying_price: 0, currency_id: d.currency_id, currency_code: App.currencyCode(d.currency_id), allocated: 0 });
      $(e.target).val(null).trigger('change'); drawAlloc();
    });
    $m.find('#splitEven').on('click', () => {
      const stems = num(F('stems').val()); if (!farms.length || !stems) return;
      const base = Math.floor(stems / farms.length); let rest = stems - base * farms.length;
      farms.forEach(f => { f.allocated = base + (rest-- > 0 ? 1 : 0); }); drawAlloc();
    });
    $m.find('#fillCheapest').on('click', () => {
      const stems = num(F('stems').val()); if (!farms.length || !stems) return;
      const sorted = [...farms].sort((a, b) => num(a.buying_price) - num(b.buying_price));
      farms.forEach(f => f.allocated = 0); sorted[0].allocated = stems; drawAlloc();
    });

    $f.on('submit', async (e) => {
      e.preventDefault();
      $f.find('.is-invalid').removeClass('is-invalid'); $f.find('[data-err]').text('');
      const payload = {
        order_id: ORDER_ID, line_id: line ? line.id : 0,
        variety_id: F('variety_id').val(), length_id: F('length_id').val(), order_by_id: F('order_by_id').val(),
        stems: F('stems').val(), selling_price: F('selling_price').val() || 0, remarks: F('remarks').val(),
        allocations: farms.filter(f => num(f.allocated) > 0).map(f => ({ farm_id: f.farm_id, allocated_stems: f.allocated, buying_price: f.buying_price || 0, currency_id: f.currency_id || 0 }))
      };
      const $btn = $f.find('[type=submit]'); busy($btn, true, 'Saving…');
      try { await api('orders.line_save', payload); modal.hide(); load(); }
      catch (err) { if (err.errors) Object.entries(err.errors).forEach(([k, v]) => { F(k).addClass('is-invalid'); $f.find(`[data-err="${k}"]`).text(v); if (k !== 'allocations') toast(v, 'error'); }); }
      finally { busy($btn, false); }
    });
    $m.on('hidden.bs.modal', () => $m.remove());
    modal.show(); drawAlloc();
  }

  // ------------------------------------------------------------------
  //  TAB: Farm orders
  // ------------------------------------------------------------------
  function tabFarms($b) {
    if (!D.farm_orders.length) {
      $b.html(`<div class="empty"><i class="bi bi-tree"></i>Farm orders are generated when you <strong>send the order to farms</strong>.<br>${D.order.stage === 'draft' && D.can.edit && D.lines.length ? '<button class="btn btn-primary btn-sm mt-3" data-act="send"><i class="bi bi-send me-1"></i>Send to farms now</button>' : ''}</div>`);
      return;
    }
    $b.html(`<div class="row g-3">${D.farm_orders.map(f => `
      <div class="col-md-6 col-xl-4"><div class="card h-100 shadow-none">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start">
            <div><div class="fw-bold">${esc(f.farm)}</div><div class="small text-secondary">${esc(f.farm_order_no)}</div></div>
            <span class="pill pill-${({ sent: 'info', confirmed: 'primary', received: 'success', cancelled: 'danger', pending: 'secondary' })[f.status]}">${esc(f.status)}</span>
          </div>
          <div class="row text-center mt-3 g-0 small">
            <div class="col"><div class="text-secondary">Asked</div><div class="fw-bold tabular">${fmt.int(f.allocated)}</div></div>
            <div class="col"><div class="text-secondary">Confirmed</div><div class="fw-bold tabular">${f.confirmed !== null ? fmt.int(f.confirmed) : '—'}</div></div>
            <div class="col"><div class="text-secondary">Received</div><div class="fw-bold tabular">${f.received !== null ? fmt.int(f.received) : '—'}</div></div>
          </div>
          <div class="small text-secondary mt-3">${f.contact_person ? '<i class="bi bi-person me-1"></i>' + esc(f.contact_person) : ''} ${f.phone ? ' · ' + esc(f.phone) : ''}</div>
          <div class="small mt-1">Value: <strong>${esc(f.currency_code)} ${fmt.money(+f.cost_amount ? f.cost_amount : f.allocated_value)}</strong> ${+f.cost_amount ? '<span class="text-secondary">(received)</span>' : '<span class="text-secondary">(ordered)</span>'}</div>
          <div class="d-flex gap-2 mt-3">
            <a class="btn btn-sm btn-outline-primary" target="_blank" href="${doc('farm_order', '&farm=' + f.farm_id)}"><i class="bi bi-printer me-1"></i>Farm order</a>
            ${f.email ? `<a class="btn btn-sm btn-light" href="mailto:${esc(f.email)}?subject=${encodeURIComponent('Order ' + f.farm_order_no + ' — ' + D.order.customer_name + ' — departs ' + fmt.date(D.order.departure_date))}"><i class="bi bi-envelope me-1"></i>Email</a>` : ''}
          </div>
        </div></div></div>`).join('')}</div>`);
  }

  // ------------------------------------------------------------------
  //  Grouping helper for confirmation & delivery grids (by farm)
  // ------------------------------------------------------------------
  function byFarm() {
    const g = {};
    D.lines.forEach(l => l.allocations.forEach(a => {
      (g[a.farm_id] = g[a.farm_id] || { farm: a.farm, farm_id: a.farm_id, rows: [] }).rows.push(Object.assign({}, a, { variety: l.variety, length: l.length, order_by: l.order_by }));
    }));
    return Object.values(g).sort((a, b) => a.farm.localeCompare(b.farm));
  }

  // ------------------------------------------------------------------
  //  TAB: Farm confirmation
  // ------------------------------------------------------------------
  function tabConfirm($b) {
    const s = idx(D.order.stage);
    if (s < 1) { $b.html('<div class="empty"><i class="bi bi-patch-check"></i>Send the order to farms first.</div>'); return; }
    const editable = D.order.stage === 'sourcing' && D.can.confirm;
    const groups = byFarm();
    let body = '';
    groups.forEach(g => {
      body += `<tr class="line-row"><td colspan="7"><i class="bi bi-tree me-1"></i>${esc(g.farm)} ${editable ? `<button type="button" class="btn btn-sm btn-link py-0" data-fill-farm="${g.farm_id}">all as asked</button>` : ''}</td></tr>`;
      g.rows.forEach(a => {
        const inp = (cls, val, step) => editable ? `<input type="number" min="0" step="${step}" class="form-control form-control-sm cell-input ms-auto ${cls}" value="${val ?? ''}">` : (val !== null && val !== undefined ? fmt.qty(val) : '—');
        body += `<tr class="alloc-row" data-id="${a.id}" data-farm="${a.farm_id}" data-alloc="${a.allocated_stems}">
          <td>${esc(a.variety)}</td><td>${esc(a.length)}</td><td>${esc(a.order_by || '')}</td>
          <td class="num">${fmt.int(a.allocated_stems)}</td>
          <td class="num">${inp('c-conf', a.confirmed_stems, 1)}<div class="invalid-feedback" data-err="c_${a.id}"></div></td>
          <td class="num">${inp('c-rate', a.pack_rate, 'any')}</td>
          <td class="num">${inp('c-box', a.confirmed_boxes, 'any')}</td></tr>`;
      });
    });
    const f = D.financials;
    $b.html(`
      ${editable ? '<div class="alert alert-info small"><i class="bi bi-info-circle me-1"></i>Enter what each farm confirms. Pack rate (stems per box) and boxes calculate each other. Enter <strong>0</strong> where a farm cannot supply.</div>' : ''}
      <div class="table-responsive"><table class="table table-sm" id="confTable">
        <thead><tr><th>Variety</th><th>Length</th><th>Order by</th><th class="num">Asked</th><th class="num">Confirmed</th><th class="num">Pack rate</th><th class="num">Boxes</th></tr></thead>
        <tbody>${body}</tbody>
        <tfoot><tr><th colspan="3">Total</th><th class="num" id="tAlloc"></th><th class="num" id="tConf"></th><th></th><th class="num" id="tBox"></th></tr></tfoot>
      </table></div>
      ${editable ? `<div class="sticky-actions d-flex flex-wrap gap-2 justify-content-end mt-3 rounded">
          <button class="btn btn-light" id="confSave"><i class="bi bi-save me-1"></i>Save progress</button>
          <button class="btn btn-primary" id="confDone"><i class="bi bi-patch-check me-1"></i>Save &amp; complete confirmation</button></div>`
        : (s >= 2 ? `<div class="mt-3"><a class="btn btn-green btn-sm" target="_blank" href="${doc('confirmed_order')}"><i class="bi bi-printer me-1"></i>Confirmed order document</a></div>` : '')}`);
    const totals = () => {
      let a = 0, c = 0, bx = 0;
      $('#confTable tbody tr[data-id]').each(function () {
        a += num($(this).data('alloc'));
        c += editable ? num($(this).find('.c-conf').val()) : num((D.lines.flatMap(l => l.allocations).find(x => +x.id === +$(this).data('id')) || {}).confirmed_stems);
        bx += editable ? num($(this).find('.c-box').val()) : num((D.lines.flatMap(l => l.allocations).find(x => +x.id === +$(this).data('id')) || {}).confirmed_boxes);
      });
      $('#tAlloc').text(fmt.int(a)); $('#tConf').html(`${fmt.int(c)} <span class="small ${c < a ? 'text-danger' : 'text-success'}">(${fmt.pct(a ? c / a * 100 : 0, 0)})</span>`); $('#tBox').text(fmt.qty(bx));
    };
    totals();
    if (!editable) return;
    const $t = $('#confTable');
    // confirmed/pack rate/boxes interplay
    $t.on('change', '.c-conf,.c-rate,.c-box', function () {
      const $r = $(this).closest('tr'); const c = num($r.find('.c-conf').val()), pr = num($r.find('.c-rate').val()), bx = num($r.find('.c-box').val());
      if ($(this).hasClass('c-conf') || $(this).hasClass('c-rate')) { if (pr > 0 && $r.find('.c-conf').val() !== '') $r.find('.c-box').val(+(c / pr).toFixed(2)); }
      else if ($(this).hasClass('c-box')) { if (bx > 0 && c > 0) $r.find('.c-rate').val(+(c / bx).toFixed(2)); else if (bx > 0 && pr > 0) $r.find('.c-conf').val(Math.round(bx * pr)); }
      totals();
    });
    $t.on('input', '.c-conf', totals);
    $t.on('click', '[data-fill-farm]', function () {
      $t.find(`tr[data-farm="${$(this).data('fill-farm')}"]`).each(function () { $(this).find('.c-conf').val($(this).data('alloc')).trigger('change'); });
    });
    const rows = () => $t.find('tbody tr[data-id]').map(function () {
      return { id: $(this).data('id'), confirmed_stems: $(this).find('.c-conf').val(), pack_rate: $(this).find('.c-rate').val(), confirmed_boxes: $(this).find('.c-box').val() };
    }).get();
    const save = async (btn) => {
      busy(btn, true, 'Saving…');
      try { await api('orders.confirm_save', { id: ORDER_ID, rows: rows() }); return true; }
      catch (e) { if (e.errors) Object.entries(e.errors).forEach(([k, v]) => { $t.find(`[data-err="${k}"]`).text(v).prev('input').addClass('is-invalid'); }); return false; }
      finally { busy(btn, false); }
    };
    $('#confSave').on('click', async function () { if (await save(this)) load(); });
    $('#confDone').on('click', async function () {
      const blanks = rows().filter(r => r.confirmed_stems === '').length;
      if (blanks && !(await confirm({ title: 'Some lines are blank', text: `${blanks} farm line(s) have no confirmed quantity. Fill them in (use 0 for none) before completing.`, icon: 'warning', confirmText: 'OK' }))) return;
      if (blanks) return;
      if (!(await save(this))) return;
      if (!(await confirm({ title: 'Complete confirmation?', text: 'Quantities will be locked and the confirmed order document becomes available.', confirmText: 'Complete' }))) { load(); return; }
      await api('orders.confirm_complete', { id: ORDER_ID }).catch(() => null); activeTab = 'delivery'; load();
    });
  }

  // ------------------------------------------------------------------
  //  TAB: Delivery check (NEW checksum step)
  // ------------------------------------------------------------------
  function tabDelivery($b) {
    const s = idx(D.order.stage);
    if (s < 2) { $b.html('<div class="empty"><i class="bi bi-clipboard2-check"></i>The delivery check opens once farm confirmations are complete.</div>'); return; }
    const editable = D.order.stage === 'confirmed' && D.can.receive;
    let body = '';
    byFarm().forEach(g => {
      body += `<tr class="line-row"><td colspan="9"><i class="bi bi-tree me-1"></i>${esc(g.farm)} ${editable ? `<button type="button" class="btn btn-sm btn-link py-0" data-fill-farm="${g.farm_id}">received = confirmed</button>` : ''}</td></tr>`;
      g.rows.forEach(a => {
        const c = num(a.confirmed_stems);
        const inp = (cls, val, step) => editable ? `<input type="number" min="0" step="${step}" class="form-control form-control-sm cell-input ms-auto ${cls}" value="${val ?? ''}">` : (val !== null && val !== undefined ? fmt.qty(val) : '—');
        body += `<tr class="alloc-row" data-id="${a.id}" data-farm="${a.farm_id}" data-conf="${c}" data-cbox="${num(a.confirmed_boxes)}">
          <td>${esc(a.variety)}</td><td>${esc(a.length)}</td>
          <td class="num">${fmt.int(c)}</td><td class="num">${fmt.qty(a.confirmed_boxes)}</td>
          <td class="num">${inp('r-rec', a.received_stems, 1)}</td>
          <td class="num">${inp('r-box', a.received_boxes, 'any')}</td>
          <td class="num">${inp('r-rej', a.rejected_stems || (editable ? '' : 0), 1)}</td>
          <td class="num r-var"></td>
          <td>${editable ? `<input class="form-control form-control-sm r-why" style="min-width:160px" placeholder="Reason if different" value="${esc(a.variance_reason || '')}" list="whyList">` : esc(a.variance_reason || '')}<div class="invalid-feedback" data-err="r_${a.id}"></div></td></tr>`;
      });
    });
    $b.html(`
      <div class="alert alert-warning small"><i class="bi bi-clipboard2-check me-1"></i><strong>Delivery checksum.</strong> Record what each farm actually delivered (good stems) against what it confirmed. The packing list, the customer invoice and the farm bills all use the <strong>received</strong> quantities.</div>
      <datalist id="whyList"><option value="Short shipped by farm"><option value="Quality rejects"><option value="Damaged in transit"><option value="Farm sent extra"><option value="Wrong length delivered"></datalist>
      <div class="table-responsive"><table class="table table-sm" id="recTable">
        <thead><tr><th>Variety</th><th>Length</th><th class="num">Confirmed</th><th class="num">Conf. boxes</th><th class="num">Received</th><th class="num">Rcvd boxes</th><th class="num">Rejected</th><th class="num">Variance</th><th>Reason</th></tr></thead>
        <tbody>${body}</tbody>
        <tfoot><tr><th colspan="2">Total</th><th class="num" id="rtConf"></th><th></th><th class="num" id="rtRec"></th><th class="num" id="rtBox"></th><th class="num" id="rtRej"></th><th class="num" id="rtVar"></th><th></th></tr></tfoot>
      </table></div>
      ${editable ? `<div class="sticky-actions d-flex flex-wrap gap-2 justify-content-end mt-3 rounded">
        <button class="btn btn-light" id="recAll"><i class="bi bi-check2-all me-1"></i>Everything arrived as confirmed</button>
        <button class="btn btn-light" id="recSave"><i class="bi bi-save me-1"></i>Save progress</button>
        <button class="btn btn-primary" id="recDone"><i class="bi bi-clipboard2-check me-1"></i>Save &amp; complete delivery check</button></div>`
        : (s >= 3 ? `<div class="mt-3"><a class="btn btn-green btn-sm" target="_blank" href="${doc('delivery_note')}"><i class="bi bi-printer me-1"></i>Delivery check note</a></div>` : '')}`);
    const $t = $('#recTable');
    const allocById = Object.fromEntries(D.lines.flatMap(l => l.allocations).map(a => [a.id, a]));
    const recompute = () => {
      let tc = 0, tr = 0, tb = 0, tj = 0;
      $t.find('tbody tr[data-id]').each(function () {
        const $r = $(this); const a = allocById[$r.data('id')] || {};
        const conf = num($r.data('conf'));
        const recRaw = editable ? $r.find('.r-rec').val() : a.received_stems;
        const rec = num(recRaw);
        tc += conf; tr += rec; tb += num(editable ? $r.find('.r-box').val() : a.received_boxes); tj += num(editable ? $r.find('.r-rej').val() : a.rejected_stems);
        const v = rec - conf;
        $r.find('.r-var').html(recRaw === '' || recRaw === null || recRaw === undefined ? '' : (v === 0 ? '<span class="text-success"><i class="bi bi-check2"></i></span>' : `<span class="${v < 0 ? 'variance-neg' : 'variance-pos'}">${v > 0 ? '+' : ''}${fmt.int(v)}</span>`));
      });
      $('#rtConf').text(fmt.int(tc)); $('#rtRec').text(fmt.int(tr)); $('#rtBox').text(fmt.qty(tb)); $('#rtRej').text(fmt.int(tj));
      const v = tr - tc; $('#rtVar').html(`<span class="${v < 0 ? 'variance-neg' : (v > 0 ? 'variance-pos' : '')}">${v > 0 ? '+' : ''}${fmt.int(v)} (${fmt.pct(tc ? tr / tc * 100 : 0, 1)})</span>`);
    };
    recompute();
    if (!editable) return;
    const fillRow = ($r) => { $r.find('.r-rec').val($r.data('conf')); if ($r.find('.r-box').val() === '') $r.find('.r-box').val($r.data('cbox')); };
    $t.on('input change', 'input', recompute);
    $t.on('click', '[data-fill-farm]', function () { $t.find(`tr[data-farm="${$(this).data('fill-farm')}"]`).each(function () { fillRow($(this)); }); recompute(); });
    $('#recAll').on('click', () => { $t.find('tbody tr[data-id]').each(function () { fillRow($(this)); }); recompute(); });
    const rows = () => $t.find('tbody tr[data-id]').map(function () {
      return { id: $(this).data('id'), received_stems: $(this).find('.r-rec').val(), received_boxes: $(this).find('.r-box').val(), rejected_stems: $(this).find('.r-rej').val(), variance_reason: $(this).find('.r-why').val() };
    }).get();
    const save = async (btn) => {
      $t.find('.is-invalid').removeClass('is-invalid');
      busy(btn, true, 'Saving…');
      try { await api('orders.receive_save', { id: ORDER_ID, rows: rows() }); return true; }
      catch (e) { if (e.errors) Object.entries(e.errors).forEach(([k, v]) => { $t.find(`[data-err="${k}"]`).text(v).prev('input').addClass('is-invalid'); }); return false; }
      finally { busy(btn, false); }
    };
    $('#recSave').on('click', async function () { if (await save(this)) load(); });
    $('#recDone').on('click', async function () {
      if (!(await save(this))) return;
      if (!(await confirm({ title: 'Complete the delivery check?', text: 'Received quantities will be locked and the farm bills posted to farm statements.', confirmText: 'Complete' }))) { load(); return; }
      await api('orders.receive_complete', { id: ORDER_ID }).catch(() => null); activeTab = 'packing'; load();
    });
  }

  // ------------------------------------------------------------------
  //  TAB: Packing & invoice
  // ------------------------------------------------------------------
  function tabPacking($b) {
    const s = idx(D.order.stage), pl = D.packing, inv = D.invoice;
    if (s < 3) { $b.html('<div class="empty"><i class="bi bi-box-seam"></i>Packing opens after the delivery check is complete.</div>'); return; }
    if (!pl) {
      $b.html(`<div class="empty"><i class="bi bi-box-seam"></i>Ready to pack ${fmt.int(D.financials ? D.financials.qty.received : 0)} received stems.<br>${D.can.pack && D.order.stage === 'received' ? '<button class="btn btn-primary mt-3" data-act="pack"><i class="bi bi-box-seam me-1"></i>Create packing list</button>' : ''}</div>`);
      return;
    }
    const plEditable = can('packing.edit') && pl.status === 'open';
    // summary by line (received)
    const sum = D.lines.map(l => ({ v: l.variety, len: l.length, by: l.order_by, stems: l.allocations.reduce((s, a) => s + num(a.received_stems), 0), boxes: l.allocations.reduce((s, a) => s + num(a.received_boxes), 0), price: l.selling_price })).filter(x => x.stems > 0);
    $b.html(`
      <div class="row g-4">
        <div class="col-lg-5">
          <h6 class="fw-bold mb-3"><i class="bi bi-box-seam me-1"></i>Packing list ${esc(pl.packing_list_no)} <span class="text-secondary fw-normal">· Consignment ${esc(pl.consignment_no)}</span></h6>
          <form id="plForm" class="row g-2">
            <div class="col-12"><label class="form-label">Drop point</label><select class="form-select" name="drop_point_id" ${plEditable ? '' : 'disabled'}></select></div>
            <div class="col-6"><label class="form-label">AWB number</label><input class="form-control" name="awb_no" value="${esc(pl.awb_no || '')}" ${plEditable ? '' : 'disabled'}></div>
            <div class="col-6"><label class="form-label">Flight</label><input class="form-control" name="flight_no" value="${esc(pl.flight_no || '')}" ${plEditable ? '' : 'disabled'}></div>
            <div class="col-6"><label class="form-label">Gross weight (kg)</label><input type="number" step="0.01" min="0" class="form-control" name="gross_weight_kg" value="${pl.gross_weight_kg ?? ''}" ${plEditable ? '' : 'disabled'}></div>
            <div class="col-6"><label class="form-label">Volume weight (kg)</label><input type="number" step="0.01" min="0" class="form-control" name="volume_weight_kg" value="${pl.volume_weight_kg ?? ''}" ${plEditable ? '' : 'disabled'}></div>
            <div class="col-12"><label class="form-label">Notes</label><textarea class="form-control" name="notes" rows="2" ${plEditable ? '' : 'disabled'}>${esc(pl.notes || '')}</textarea></div>
            <div class="col-12 d-flex gap-2 flex-wrap">
              ${plEditable ? '<button class="btn btn-light" type="submit"><i class="bi bi-save me-1"></i>Save packing details</button>' : ''}
              <a class="btn btn-green" target="_blank" href="${doc('packing_list')}"><i class="bi bi-printer me-1"></i>Packing list</a>
            </div>
          </form>
        </div>
        <div class="col-lg-7">
          <h6 class="fw-bold mb-3"><i class="bi bi-receipt me-1"></i>Invoice</h6>
          <div class="table-responsive"><table class="table table-sm">
            <thead><tr><th>Variety</th><th>Length</th><th class="num">Stems</th><th class="num">Boxes</th><th class="num">Price</th><th class="num">Amount (${esc(cur())})</th></tr></thead>
            <tbody>${sum.map(x => `<tr><td>${esc(x.v)}</td><td>${esc(x.len)}</td><td class="num">${fmt.int(x.stems)}</td><td class="num">${fmt.qty(x.boxes)}</td><td class="num">${fmt.money(x.price, 4)}</td><td class="num">${fmt.money(x.stems * num(x.price))}</td></tr>`).join('')}</tbody>
            <tfoot><tr><th colspan="2">Total</th><th class="num">${fmt.int(sum.reduce((s, x) => s + x.stems, 0))}</th><th class="num">${fmt.qty(sum.reduce((s, x) => s + x.boxes, 0))}</th><th></th><th class="num">${fmt.money(sum.reduce((s, x) => s + x.stems * num(x.price), 0))}</th></tr></tfoot>
          </table></div>
          ${inv && inv.status === 'open'
            ? `<div class="alert alert-success d-flex align-items-center gap-3 flex-wrap mb-0"><i class="bi bi-receipt fs-4"></i><div class="me-auto"><strong>${esc(inv.invoice_no)}</strong> issued ${fmt.date(inv.invoice_date)} · ${esc(cur())} ${fmt.money(inv.total)}<div class="small">Due ${fmt.date(inv.due_date)}</div></div><a class="btn btn-success btn-sm" target="_blank" href="print.php?doc=invoice&id=${inv.id}"><i class="bi bi-printer me-1"></i>Print invoice</a></div>`
            : (D.can.invoice && D.order.stage === 'packing'
              ? `<div class="d-flex flex-wrap gap-2 align-items-end"><div><label class="form-label">Invoice date</label><input type="date" class="form-control" id="invDate" value="${App.today()}"></div><button class="btn btn-primary" id="mkInvoice"><i class="bi bi-receipt me-1"></i>Create invoice</button></div>
                 <div class="form-text">Billed on received stems at each line's selling price. Exchange rate is taken on the invoice date.</div>`
              : '<div class="text-secondary small">No invoice yet.</div>')}
        </div>
      </div>`);
    const $dp = $('#plForm [name="drop_point_id"]');
    select2($dp, 'drop_points');
    if (pl.drop_point_id) setSelect($dp, 'drop_points', pl.drop_point_id, pl.drop_point);
    $('#plForm').on('submit', async function (e) {
      e.preventDefault();
      const data = Object.fromEntries(new FormData(this).entries()); data.id = pl.id;
      const $btn = $(this).find('[type=submit]'); busy($btn, true, 'Saving…');
      try { await api('packing.update', data); load(); } catch (err) {} finally { busy($btn, false); }
    });
    $('#mkInvoice').on('click', async function () {
      if (!(await confirm({ title: 'Create the invoice?', text: `Invoice ${esc(D.order.customer_name)} for the received stems in ${esc(cur())}.`, confirmText: 'Create invoice' }))) return;
      busy(this, true, 'Creating…');
      try { const r = await api('orders.invoice', { id: ORDER_ID, invoice_date: $('#invDate').val() }); window.open('print.php?doc=invoice&id=' + r.invoice_id, '_blank'); activeTab = 'money'; load(); }
      catch (e) { busy(this, false); }
    });
  }

  // ------------------------------------------------------------------
  //  TAB: Costs & profit
  // ------------------------------------------------------------------
  function tabMoney($b) {
    const f = D.financials;
    const B = MG.base.code;
    const k = (label, val, cls = '', sub = '') => `<div class="col-6 col-md-4 col-xl-2"><div class="p-3 rounded-3 h-100" style="background:var(--mg-muted-bg)"><div class="small text-secondary">${label}</div><div class="fw-bold fs-5 tabular ${cls}">${val}</div>${sub ? `<div class="small text-secondary">${sub}</div>` : ''}</div></div>`;
    const kpis = f ? `
      <div class="row g-2 mb-4">
        ${k('Revenue', B + ' ' + fmt.money(f.revenue_base, 0), '', f.projected ? 'projected' : 'invoiced')}
        ${k('Other charges', B + ' ' + fmt.money(f.other_revenue_base, 0))}
        ${k('Farm cost', B + ' ' + fmt.money(f.farm_cost_base, 0), 'text-danger')}
        ${k('Other costs', B + ' ' + fmt.money(f.other_cost_base, 0), 'text-danger')}
        ${k('Expenses', B + ' ' + fmt.money(f.expenses_base, 0), 'text-danger')}
        ${k(f.projected ? 'Projected profit' : 'Profit', B + ' ' + fmt.money(f.profit_base, 0), f.profit_base >= 0 ? 'text-success' : 'text-danger', 'Margin ' + fmt.pct(f.margin_pct))}
      </div>
      ${f.projected ? `<div class="small text-secondary mb-3"><i class="bi bi-info-circle me-1"></i>Figures are ${esc(f.revenue_source)} until the order is invoiced.</div>` : ''}` : '<div class="alert alert-light small">You do not have the right to see financial figures.</div>';
    $b.html(`${kpis}
      <div class="row g-4">
        <div class="col-lg-6">
          <div class="d-flex align-items-center mb-2"><h6 class="fw-bold mb-0 me-auto">Expenses on this order</h6>${D.can.expenses ? '<button class="btn btn-sm btn-outline-primary" id="addExp"><i class="bi bi-plus-lg me-1"></i>Add expense</button>' : ''}</div>
          ${D.expenses.length ? `<table class="table table-sm"><thead><tr><th>Date</th><th>Type</th><th>Description</th><th class="num">Amount</th><th class="num">${B}</th></tr></thead><tbody>
            ${D.expenses.map(e => `<tr><td>${fmt.date(e.expense_date)}</td><td>${esc(e.type)}</td><td>${esc(e.description)}</td><td class="num">${esc(e.currency)} ${fmt.money(e.amount)}</td><td class="num">${fmt.money(e.base_amount)}</td></tr>`).join('')}</tbody></table>`
            : '<div class="text-secondary small">No expenses linked yet (freight, clearing, transport…).</div>'}
        </div>
        <div class="col-lg-6">
          <div class="d-flex align-items-center mb-2"><h6 class="fw-bold mb-0 me-auto">Other costs charged on the invoice</h6>${D.invoice && can('other_costs.create') ? '<button class="btn btn-sm btn-outline-primary" id="addOc"><i class="bi bi-plus-lg me-1"></i>Add other cost</button>' : ''}</div>
          ${D.other_costs.length ? `<table class="table table-sm"><thead><tr><th>Date</th><th>Details</th><th class="num">Charged</th><th class="num">Cost</th></tr></thead><tbody>
            ${D.other_costs.map(c => `<tr><td>${fmt.date(c.cost_date)}</td><td>${esc(c.details)}</td><td class="num">${esc(c.currency)} ${fmt.money(c.selling_price)}</td><td class="num">${esc(c.currency)} ${fmt.money(c.buying_price)}</td></tr>`).join('')}</tbody></table>`
            : `<div class="text-secondary small">${D.invoice ? 'None (data logger, freight, fumigation…).' : 'Available after invoicing.'}</div>`}
        </div>
      </div>`);
    $('#addExp').on('click', async () => {
      const meta = await get('resource.meta', { name: 'expenses' });
      modalForm({ title: 'Add expense to ' + D.order.order_no, icon: 'bi-credit-card-2-back', fields: meta.fields, fx: meta.fx,
        values: { order_id: ORDER_ID, order_id__label: D.order.order_no + ' · ' + D.order.customer_name },
        onSubmit: async (data) => { await api('resource.save', Object.assign({ name: 'expenses', id: 0 }, data)); load(); } });
    });
    $('#addOc').on('click', async () => {
      const meta = await get('resource.meta', { name: 'other_costs' });
      modalForm({ title: 'Add other cost', icon: 'bi-plus-slash-minus', fields: meta.fields, fx: meta.fx,
        values: { invoice_id: D.invoice.id, invoice_id__label: D.invoice.invoice_no + ' · ' + D.order.customer_name, customer_id: D.order.customer_id, customer_id__label: D.order.customer_name, currency_id: D.order.currency_id },
        onSubmit: async (data) => { await api('resource.save', Object.assign({ name: 'other_costs', id: 0 }, data)); load(); } });
    });
  }

  // ------------------------------------------------------------------
  //  TAB: Timeline
  // ------------------------------------------------------------------
  async function tabTimeline($b) {
    $b.html(App.loader('Loading timeline…'));
    const rows = await get('orders.timeline', { id: ORDER_ID }, { quiet: true });
    const icon = (a) => a.startsWith('stage:') ? ['bi-flag-fill', '#e11d48'] : a === 'create' ? ['bi-plus', '#14532d'] : a === 'delete' ? ['bi-trash', '#dc2626'] : a.includes('confirm') ? ['bi-patch-check', '#4338ca'] : a.includes('receive') ? ['bi-clipboard2-check', '#0d9488'] : ['bi-pencil', '#64748b'];
    $b.html(rows.length ? `<div class="timeline">${rows.map(r => { const [i, c] = icon(r.action); return `<div class="tl-item"><div class="tl-dot" style="background:${c}"><i class="bi ${i}"></i></div><div class="tl-title">${esc(r.description)}</div><div class="tl-meta">${fmt.datetime(r.created_at)} · ${esc(r.user || 'System')}</div></div>`; }).join('')}</div>` : '<div class="empty"><i class="bi bi-clock-history"></i>No activity yet.</div>');
  }

  // ------------------------------------------------------------------
  //  Actions
  // ------------------------------------------------------------------
  $app.on('click', '[data-tab]', function (e) { e.preventDefault(); activeTab = $(this).data('tab'); history.replaceState(null, '', '#' + activeTab); $app.find('[data-tab]').removeClass('active'); $(this).addClass('active'); renderTab(); });
  $app.on('click', '[data-goto]', function () { activeTab = $(this).data('goto'); render(); });
  $app.on('click', '#addLine', () => lineModal());
  $app.on('click', '[data-line-edit]', function () { lineModal(D.lines.find(l => +l.id === +$(this).data('line-edit'))); });
  $app.on('click', '[data-line-del]', async function () {
    const l = D.lines.find(x => +x.id === +$(this).data('line-del'));
    if (await confirm({ title: 'Remove line?', text: `${esc(l.variety)} ${esc(l.length)} — ${fmt.int(l.stems)} stems`, icon: 'warning', confirmText: 'Remove', danger: true })) { await api('orders.line_delete', { id: l.id }); load(); }
  });
  $app.on('click', '[data-act]', async function (e) {
    e.preventDefault();
    const act = $(this).data('act'); const o = D.order;
    try {
      if (act === 'edit') App.orderForm(Object.assign({}, o, { customer_id__label: o.customer_name, currency_id__label: o.currency_code, box_type_id__label: o.box_type, shipping_agent_id__label: o.shipping_agent, drop_point_id__label: o.drop_point }), () => load());
      if (act === 'send' && await confirm({ title: 'Send to farms?', text: 'Farm orders will be generated — one per farm — ready to print or email.', confirmText: 'Generate farm orders' })) { await api('orders.send', { id: ORDER_ID }); activeTab = 'farms'; load(); }
      if (act === 'pack' && await confirm({ title: 'Create packing list?', text: 'A packing list and consignment number will be issued from the received quantities.', confirmText: 'Create' })) { await api('orders.pack', { id: ORDER_ID }); activeTab = 'packing'; load(); }
      if (act === 'close' && await confirm({ title: 'Close this order?', text: 'Closed orders are final. You can still re-open one step if needed.', confirmText: 'Close order' })) { await api('orders.close', { id: ORDER_ID }); load(); }
      if (act === 'reopen') { const why = await confirm({ title: 'Re-open one step?', text: 'This undoes the last stage (e.g. removes an un-invoiced packing list).', input: 'text', inputLabel: 'Reason', confirmText: 'Re-open' }); if (why !== null) { await api('orders.reopen', { id: ORDER_ID, reason: why }); activeTab = null; load(); } }
      if (act === 'cancel') { const why = await confirm({ title: 'Cancel order?', text: 'Farm orders are marked cancelled.', input: 'text', inputLabel: 'Reason', confirmText: 'Cancel order', icon: 'warning', danger: true }); if (why !== null) { await api('orders.cancel', { id: ORDER_ID, reason: why }); load(); } }
      if (act === 'delete' && await confirm({ title: 'Delete order permanently?', text: `${esc(o.order_no)} and all its lines will be removed.`, icon: 'warning', confirmText: 'Delete', danger: true })) { await api('orders.delete', { id: ORDER_ID }); location.href = 'index.php?p=orders'; }
      if (act === 'duplicate') {
        modalForm({ title: 'Repeat ' + o.order_no, icon: 'bi-copy', size: 'md', submitText: 'Create repeat order',
          fields: [{ name: 'order_date', label: 'Order date', type: 'date', required: true, default: 'today' }, { name: 'departure_date', label: 'Departure date', type: 'date', required: true }],
          onSubmit: async (d) => { const r = await api('orders.duplicate', Object.assign({ id: ORDER_ID }, d)); location.href = 'index.php?p=order&id=' + r.id; } });
      }
    } catch (err) { /* toast already shown */ }
  });
  document.addEventListener('mg:rates-updated', load);

  load();
})();

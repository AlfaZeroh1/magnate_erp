<?php
$type = in_array($_GET['type'] ?? '', ['customer', 'farm', 'agent'], true) ? $_GET['type'] : 'customer';
$preId = (int)($_GET['id'] ?? 0);
?>
<div class="page-head">
  <div class="title"><div class="ico"><i class="bi bi-file-earmark-ruled"></i></div>
    <div><h2>Statements</h2><div class="sub">Customer, farm and shipping-agent accounts with running balance and ageing. Pick an account, or start from the balances list.</div></div></div>
</div>
<ul class="nav nav-tabs-modern mb-3" id="stTypes">
  <li class="nav-item"><a class="nav-link" href="#" data-type="customer"><i class="bi bi-people me-1"></i>Customers</a></li>
  <li class="nav-item"><a class="nav-link" href="#" data-type="farm"><i class="bi bi-tree me-1"></i>Farms</a></li>
  <li class="nav-item"><a class="nav-link" href="#" data-type="agent"><i class="bi bi-send-check me-1"></i>Shipping agents</a></li>
</ul>
<div class="card mb-3"><div class="card-body py-2"><div class="row g-2 align-items-end">
  <div class="col-12 col-md-4"><label class="form-label">Account</label><select class="form-select" id="stParty"></select></div>
  <div class="col-6 col-md-2"><label class="form-label">From</label><input type="date" class="form-control" id="stFrom"></div>
  <div class="col-6 col-md-2"><label class="form-label">To</label><input type="date" class="form-control" id="stTo"></div>
  <div class="col-6 col-md-2"><label class="form-label">Currency</label><select class="form-select" id="stCur"><option value="">Account currency</option></select></div>
  <div class="col-6 col-md-2 d-grid"><button class="btn btn-primary" id="stRun"><i class="bi bi-play-fill me-1"></i>Show</button></div>
</div></div></div>
<div id="stBody"></div>
<?php ob_start(); ?>
(function () {
  const { get, esc, fmt, select2, setSelect, MG, loader } = App;
  let type = <?= json_encode($type) ?>;
  const src = { customer: 'customers', farm: 'farms', agent: 'shipping_agents' };
  $('#stCur').append((MG.currencies || []).map(c => `<option value="${c.id}">${esc(c.code)}</option>`).join(''));
  const $p = $('#stParty');
  const initParty = () => { if ($p.data('select2')) $p.select2('destroy'); $p.empty(); select2($p, src[type], { placeholder: 'Choose an account…' }); };
  const setType = (t) => { type = t; $('#stTypes .nav-link').removeClass('active'); $(`#stTypes [data-type="${t}"]`).addClass('active'); initParty(); balances(); };

  async function balances() {
    $('#stBody').html(loader('Calculating balances…'));
    const rows = await get('statements.balances', { type }, { quiet: true });
    $('#stBody').html(`<div class="card"><div class="card-header"><h3>${type === 'customer' ? 'Customer balances (receivable)' : (type === 'farm' ? 'Farm balances (payable)' : 'Agent balances (payable)')}</h3></div><div class="card-body">
      ${rows.length ? `<div class="table-responsive"><table class="table table-hover table-sm"><thead><tr><th>Account</th><th class="num">${type === 'customer' ? 'Invoiced' : 'Billed'}</th><th class="num">${type === 'customer' ? 'Received' : 'Paid'}</th><th class="num">Balance</th><th class="num">0–30</th><th class="num">31–60</th><th class="num">61–90</th><th class="num">91–120</th><th class="num">120+</th><th>Last activity</th></tr></thead><tbody>
        ${rows.map(r => `<tr style="cursor:pointer" data-open="${r.id}" data-name="${esc(r.name)}"><td class="fw-semibold">${esc(r.name)}</td><td class="num">${fmt.money(r.charges)}</td><td class="num">${fmt.money(r.payments)}</td>
          <td class="num fw-bold ${r.balance > 0 ? 'text-danger' : 'text-success'}"><span class="small text-secondary fw-normal">${esc(r.currency)}</span> ${fmt.money(r.balance)}</td>
          ${['current', 'd30', 'd60', 'd90', 'over90'].map(k => `<td class="num ${k !== 'current' && r.aging[k] > 0 ? 'text-danger' : ''}">${r.aging[k] ? fmt.money(r.aging[k], 0) : ''}</td>`).join('')}
          <td class="small">${fmt.date(r.last)}</td></tr>`).join('')}
      </tbody></table></div>` : '<div class="empty"><i class="bi bi-inbox"></i>No balances yet.</div>'}
    </div></div>`);
  }

  async function run() {
    const id = $p.val(); if (!id) { balances(); return; }
    $('#stBody').html(loader('Building statement…'));
    try {
      const s = await get('statements.get', { type, id, from: $('#stFrom').val(), to: $('#stTo').val(), currency_id: $('#stCur').val() });
      const q = new URLSearchParams({ doc: 'statement', type, id, from: $('#stFrom').val(), to: $('#stTo').val(), currency_id: $('#stCur').val() });
      const docLink = (l) => l.doc === 'invoice' ? `<a target="_blank" href="print.php?doc=invoice&id=${l.doc_id}">${esc(l.ref)}</a>` : (l.doc === 'receipt' ? `<a target="_blank" href="print.php?doc=receipt&id=${l.doc_id}">${esc(l.ref)}</a>` : (l.doc === 'farm_order' ? `<a href="index.php?p=order&id=${l.doc_id}">${esc(l.ref)}</a>` : esc(l.ref)));
      $('#stBody').html(`
        <div class="row g-3 mb-3">
          ${[['Opening', s.opening], [s.labels.charge, s.charges], [s.labels.payment, s.payments], [s.labels.balance, s.closing]].map(([l, v], i) => `<div class="col-6 col-lg-3"><div class="card kpi"><div class="card-body"><div class="label">${esc(l)}</div><div class="value ${i === 3 ? (v > 0 ? 'text-danger' : 'text-success') : ''}">${esc(s.currency)} ${fmt.money(v)}</div></div></div></div>`).join('')}
        </div>
        <div class="card"><div class="card-header d-flex align-items-center flex-wrap gap-2"><h3 class="me-auto">${esc(s.title)} — ${esc(s.party.name)}</h3>
          <a class="btn btn-green btn-sm" target="_blank" href="print.php?${q}"><i class="bi bi-printer me-1"></i>Print / PDF</a></div>
          <div class="card-body"><div class="table-responsive"><table class="table table-sm table-hover">
            <thead><tr><th>Date</th><th>Reference</th><th>Description</th><th class="num">${esc(s.labels.charge)}</th><th class="num">${esc(s.labels.payment)}</th><th class="num">Balance</th></tr></thead>
            <tbody>${s.from ? `<tr class="text-secondary"><td>${fmt.date(s.from)}</td><td></td><td>Opening balance</td><td></td><td></td><td class="num">${fmt.money(s.opening)}</td></tr>` : ''}
              ${s.lines.map(l => `<tr><td>${fmt.date(l.date)}</td><td>${docLink(l)}</td><td>${esc(l.description)}${l.original ? `<div class="small text-secondary">orig. ${esc(l.original)}</div>` : ''}</td>
                <td class="num">${l.charge !== null ? fmt.money(l.charge) : ''}</td><td class="num text-success">${l.payment !== null ? fmt.money(l.payment) : ''}</td><td class="num fw-semibold">${fmt.money(l.balance)}</td></tr>`).join('') || '<tr><td colspan="6" class="text-center text-secondary py-4">No transactions in this period.</td></tr>'}
            </tbody>
            <tfoot><tr><th colspan="3">Totals</th><th class="num">${fmt.money(s.charges)}</th><th class="num">${fmt.money(s.payments)}</th><th class="num">${esc(s.currency)} ${fmt.money(s.closing)}</th></tr></tfoot>
          </table></div>
          <div class="row g-2 mt-3 text-center small">${[['Current', 'current'], ['31–60 days', 'd30'], ['61–90 days', 'd60'], ['91–120 days', 'd90'], ['120+ days', 'over90']].map(([l, k]) => `<div class="col"><div class="p-2 rounded" style="background:var(--mg-muted-bg)"><div class="text-secondary">${l}</div><div class="fw-bold ${k !== 'current' && s.aging[k] > 0 ? 'text-danger' : ''}">${fmt.money(s.aging[k])}</div></div></div>`).join('')}</div>
        </div></div>`);
    } catch (e) { $('#stBody').html(''); }
  }

  $('#stTypes').on('click', '[data-type]', function (e) { e.preventDefault(); setType($(this).data('type')); });
  $('#stRun').on('click', run);
  $p.on('change', () => { if ($p.val()) run(); });
  $('#stBody').on('click', '[data-open]', async function () { await setSelect($p, src[type], $(this).data('open'), $(this).data('name')); });
  setType(type);
  <?php if ($preId): ?>setSelect($p, src[type], <?= $preId ?>);<?php endif; ?>
})();
<?php $pageInline = ob_get_clean(); ?>

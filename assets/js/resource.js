/* Generic list + modal CRUD for any resource declared in app/Core/Resources.php */
(function () {
  const { get, api, esc, fmt, modalForm, table, confirm, MG } = App;

  function cellRender(f) {
    return (v, t, r) => {
      if (t !== 'display') return f.type === 'select' ? r[f.name + '__label'] : v;
      switch (f.type) {
        case 'select': return esc(r[f.name + '__label'] || '');
        case 'money': return v === null || v === undefined ? '' : fmt.money(v);
        case 'rate': return fmt.rate(v);
        case 'date': return fmt.date(v);
        case 'switch': return +v ? '<span class="pill pill-success">Active</span>' : '<span class="pill pill-secondary">Inactive</span>';
        case 'static': return f.format === 'yesno' ? (+v ? '<i class="bi bi-check-circle-fill text-success"></i>' : '') : esc(v);
        default: return esc(v);
      }
    };
  }

  /** Mounts a full resource UI into $root. */
  App.mountResource = async function ($root, name, { compact = false } = {}) {
    $root.html(App.skeletonRows(6));
    const meta = await get('resource.meta', { _resource: name }, { quiet: true });
    let listFields = meta.fields.filter(f => f.list !== false && f.type !== 'textarea');
    if (meta.listOrder) listFields = meta.listOrder.map(n => meta.fields.find(f => f.name === n)).filter(Boolean);
    const tid = 'rt_' + name;
    const hasDate = !!meta.dateFilter;
    const totals = meta.totals || [];

    $root.html(`
      ${compact ? '' : `<div class="page-head">
        <div class="title"><div class="ico"><i class="bi ${meta.icon}"></i></div><div><h2>${esc(meta.title)}</h2></div></div>
        <div class="actions">${meta.can.create ? `<button class="btn btn-primary" data-add><i class="bi bi-plus-lg me-1"></i>Add ${esc(meta.singular.toLowerCase())}</button>` : ''}</div>
      </div>`}
      ${hasDate ? `<div class="card mb-3"><div class="card-body py-2"><div class="row g-2 align-items-end">
        <div class="col-6 col-md-2"><label class="form-label">From</label><input type="date" class="form-control form-control-sm" data-from></div>
        <div class="col-6 col-md-2"><label class="form-label">To</label><input type="date" class="form-control form-control-sm" data-to></div>
        <div class="col-12 col-md-auto"><div class="btn-group btn-group-sm" role="group">
          <button class="btn btn-light" data-range="month">This month</button><button class="btn btn-light" data-range="lastmonth">Last month</button>
          <button class="btn btn-light" data-range="year">This year</button><button class="btn btn-light" data-range="all">All</button></div></div>
      </div></div></div>` : ''}
      <div class="card"><div class="card-body">
        ${compact && meta.can.create ? `<div class="d-flex mb-2"><button class="btn btn-primary btn-sm ms-auto" data-add><i class="bi bi-plus-lg me-1"></i>Add</button></div>` : ''}
        <table class="table table-hover w-100" id="${tid}">
          <thead><tr>${listFields.map(f => `<th class="${['money', 'rate', 'number'].includes(f.type) ? 'num' : ''}">${esc(f.listLabel || f.label)}</th>`).join('')}<th></th></tr></thead>
          ${totals.length ? `<tfoot><tr>${listFields.map((f, i) => `<th class="${totals.includes(f.name) ? 'num' : ''}" data-total="${f.name}">${i === 0 ? 'Total' : ''}</th>`).join('')}<th></th></tr></tfoot>` : ''}
        </table>
      </div></div>`);

    const range = { from: '', to: '' };
    const $t = $root.find('#' + tid);
    const dt = table($t, {
      ajax: (d, cb) => get('resource.list', { _resource: name, from: range.from, to: range.to }, { quiet: true }).then(rows => cb({ data: rows })).catch(() => cb({ data: [] })),
      columns: [
        ...listFields.map(f => ({ data: f.name, defaultContent: '', className: ['money', 'rate', 'number'].includes(f.type) ? 'num' : '', render: cellRender(f) })),
        { data: null, orderable: false, className: 'actions', render: (r) => `
          ${Object.entries(meta.rowDocs || {}).map(([d, label]) => `<a class="btn btn-sm btn-light" target="_blank" href="print.php?doc=${d}&id=${r.id}&src=${name}" title="${esc(label)}"><i class="bi bi-printer"></i></a>`).join('')}
          ${meta.statement ? `<a class="btn btn-sm btn-light" href="index.php?p=statements&type=${meta.statement}&id=${r.id}" title="Statement"><i class="bi bi-file-earmark-ruled"></i></a>` : ''}
          ${meta.can.edit ? `<button class="btn btn-sm btn-light" data-edit="${r.id}" title="Edit"><i class="bi bi-pencil"></i></button>` : ''}
          ${meta.can.delete ? `<button class="btn btn-sm btn-light text-danger" data-del="${r.id}" title="Delete"><i class="bi bi-trash"></i></button>` : ''}` }
      ],
      footerCallback: totals.length ? function () {
        const rows = this.api().rows({ search: 'applied' }).data().toArray();
        totals.forEach(k => $root.find(`[data-total="${k}"]`).text(fmt.money(rows.reduce((s, r) => s + (+r[k] || 0), 0))));
      } : undefined
    });

    const open = async (id) => {
      const values = id ? await get('resource.get', { _resource: name, id }) : {};
      modalForm({
        title: (id ? 'Edit ' : 'Add ') + meta.singular.toLowerCase(), icon: meta.icon, fields: meta.fields, values, fx: meta.fx,
        onSubmit: async (data) => { await api('resource.save', Object.assign({}, data, { _resource: name, id: id || 0 })); dt.ajax.reload(null, false); }
      });
    };
    $root.on('click', '[data-add]', () => open(0));
    $root.on('click', '[data-edit]', function () { open($(this).data('edit')); });
    $root.on('click', '[data-del]', async function () {
      if (!(await confirm({ title: `Delete this ${meta.singular.toLowerCase()}?`, text: 'This cannot be undone.', icon: 'warning', confirmText: 'Delete', danger: true }))) return;
      await api('resource.delete', { _resource: name, id: $(this).data('del') }).catch(() => {}); dt.ajax.reload(null, false);
    });
    if (hasDate) {
      const iso = (d) => { d.setMinutes(d.getMinutes() - d.getTimezoneOffset()); return d.toISOString().slice(0, 10); };
      const setRange = (k) => {
        const n = new Date();
        if (k === 'month') { range.from = iso(new Date(n.getFullYear(), n.getMonth(), 1)); range.to = ''; }
        else if (k === 'lastmonth') { range.from = iso(new Date(n.getFullYear(), n.getMonth() - 1, 1)); range.to = iso(new Date(n.getFullYear(), n.getMonth(), 0)); }
        else if (k === 'year') { range.from = iso(new Date(n.getFullYear(), 0, 1)); range.to = ''; }
        else { range.from = ''; range.to = ''; }
        $root.find('[data-from]').val(range.from); $root.find('[data-to]').val(range.to);
        $root.find('[data-range]').removeClass('active'); $root.find(`[data-range="${k}"]`).addClass('active');
        dt.ajax.reload();
      };
      $root.on('click', '[data-range]', function () { setRange($(this).data('range')); });
      $root.on('change', '[data-from],[data-to]', () => { range.from = $root.find('[data-from]').val(); range.to = $root.find('[data-to]').val(); $root.find('[data-range]').removeClass('active'); dt.ajax.reload(); });
      setRange('year');
    }
    return dt;
  };

  const $page = $('#resourcePage');
  if ($page.length) App.mountResource($page, $page.data('name'));
})();

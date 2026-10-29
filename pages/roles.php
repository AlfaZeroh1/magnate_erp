<div class="page-head">
  <div class="title"><div class="ico"><i class="bi bi-shield-lock"></i></div>
    <div><h2>Roles &amp; Rights</h2><div class="sub">Tick what each role may do. Users get their role's rights; individual exceptions are set on the Users page.</div></div></div>
  <div class="actions"><?php if (can('roles.edit')): ?><button class="btn btn-light" id="newRole"><i class="bi bi-plus-lg me-1"></i>New role</button><?php endif; ?></div>
</div>
<div class="row g-3">
  <div class="col-lg-3"><div class="card"><div class="list-group list-group-flush" id="roleList"></div></div></div>
  <div class="col-lg-9"><div class="card"><div class="card-header d-flex flex-wrap gap-2 align-items-center" id="roleHead"></div><div class="card-body p-0" id="matrix"><?= App\Core\Auth::check() ? '<div class="p-4">' . str_repeat('<div class="skeleton mb-2" style="height:28px"></div>', 8) . '</div>' : '' ?></div>
    <div class="sticky-actions d-flex gap-2 justify-content-end" id="roleActions"></div></div></div>
</div>
<?php ob_start(); ?>
(function () {
  const { get, api, esc, confirm, can, busy } = App;
  let roles = [], reg = null, current = null;
  const editable = can('roles.edit');
  async function load(selectId) {
    [roles, reg] = await Promise.all([get('admin.roles', {}, { quiet: true }), reg ? Promise.resolve(reg) : get('admin.registry', {}, { quiet: true })]);
    $('#roleList').html(roles.map(r => `<a href="#" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" data-role="${r.id}">
      <span><i class="bi ${+r.is_super ? 'bi-star-fill text-warning' : 'bi-shield'} me-2"></i>${esc(r.name)}</span><span class="badge rounded-pill text-bg-light">${r.users}</span></a>`).join(''));
    show(selectId || (current && current.id) || (roles[0] && roles[0].id));
  }
  function show(id) {
    current = roles.find(r => +r.id === +id); if (!current) return;
    $('#roleList a').removeClass('active'); $(`#roleList [data-role="${id}"]`).addClass('active');
    const sup = +current.is_super; const has = new Set(current.permissions);
    const actions = Object.keys(reg.labels);
    $('#roleHead').html(`<div class="me-auto"><input class="form-control form-control-sm fw-bold" id="rName" value="${esc(current.name)}" ${editable && !+current.is_system ? '' : 'readonly'} style="max-width:260px"><input class="form-control form-control-sm mt-1" id="rDesc" value="${esc(current.description || '')}" placeholder="Description" ${editable ? '' : 'readonly'}></div>
      ${editable && !sup ? '<button class="btn btn-sm btn-light" data-all="1">Tick all</button><button class="btn btn-sm btn-light" data-all="0">Clear</button><button class="btn btn-sm btn-light" data-all="view">View only</button>' : ''}`);
    if (sup) { $('#matrix').html('<div class="empty"><i class="bi bi-star"></i>Administrators have every right automatically.</div>'); }
    else {
      let html = `<div class="table-responsive"><table class="table table-sm matrix mb-0"><thead><tr><th>Module</th>${actions.map(a => `<th class="small">${esc(reg.labels[a])}</th>`).join('')}</tr></thead><tbody>`;
      Object.entries(reg.registry).forEach(([g, mods]) => {
        html += `<tr class="grp"><td colspan="${actions.length + 1}">${esc(g)}</td></tr>`;
        Object.entries(mods).forEach(([k, [label, acts]]) => {
          html += `<tr><td><label class="d-flex align-items-center gap-2"><input type="checkbox" class="form-check-input row-all" data-mod="${k}" ${editable ? '' : 'disabled'}>${esc(label)}</label></td>${actions.map(a => acts.includes(a)
            ? `<td><input type="checkbox" class="form-check-input pchk" data-mod="${k}" value="${k}.${a}" ${has.has(k + '.' + a) ? 'checked' : ''} ${editable ? '' : 'disabled'}></td>` : '<td></td>').join('')}</tr>`;
        });
      });
      $('#matrix').html(html + '</tbody></table></div>');
      syncRows();
    }
    $('#roleActions').html(editable ? `${!+current.is_system ? '<button class="btn btn-outline-danger me-auto" id="delRole"><i class="bi bi-trash me-1"></i>Delete role</button>' : ''}<button class="btn btn-primary" id="saveRole"><i class="bi bi-check2 me-1"></i>Save role</button>` : '');
  }
  const syncRows = () => $('.row-all').each(function () { const m = $(this).data('mod'); const boxes = $(`.pchk[data-mod="${m}"]`); this.checked = boxes.length && boxes.filter(':checked').length === boxes.length; });
  $('#matrix').on('change', '.row-all', function () { $(`.pchk[data-mod="${$(this).data('mod')}"]`).prop('checked', this.checked); });
  $('#matrix').on('change', '.pchk', function () {
    // granting anything implies view
    if (this.checked && !this.value.endsWith('.view')) $(`.pchk[value="${$(this).data('mod')}.view"]`).prop('checked', true);
    syncRows();
  });
  $('#roleHead').on('click', '[data-all]', function () { const v = $(this).data('all'); $('.pchk').each(function () { this.checked = v === 'view' ? this.value.endsWith('.view') : !!+v; }); syncRows(); });
  $('#roleList').on('click', '[data-role]', function (e) { e.preventDefault(); show($(this).data('role')); });
  $('#roleActions').on('click', '#saveRole', async function () {
    busy(this, true, 'Saving…');
    try { const r = await api('admin.role_save', { id: current.id, name: $('#rName').val(), description: $('#rDesc').val(), permissions: $('.pchk:checked').map(function () { return this.value; }).get() }); await load(r.id); }
    catch (e) { } finally { busy(this, false); }
  });
  $('#roleActions').on('click', '#delRole', async () => { if (await confirm({ title: `Delete role "${esc(current.name)}"?`, icon: 'warning', danger: true, confirmText: 'Delete' })) { await api('admin.role_delete', { id: current.id }).catch(() => {}); current = null; load(); } });
  $('#newRole').on('click', async () => {
    const name = await confirm({ title: 'New role', input: 'text', inputLabel: 'Role name', confirmText: 'Create' });
    if (name) { const r = await api('admin.role_save', { id: 0, name, permissions: ['dashboard.view'] }); load(r.id); }
  });
  load();
})();
<?php $pageInline = ob_get_clean(); ?>

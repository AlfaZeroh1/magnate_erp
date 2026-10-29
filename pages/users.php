<div class="page-head">
  <div class="title"><div class="ico"><i class="bi bi-person-gear"></i></div>
    <div><h2>Users</h2><div class="sub">Who can sign in, their role, and any personal rights on top of the role.</div></div></div>
  <div class="actions"><?php if (can('users.create')): ?><button class="btn btn-primary" id="addUser"><i class="bi bi-person-plus me-1"></i>Add user</button><?php endif; ?></div>
</div>
<div class="card"><div class="card-body">
  <table class="table table-hover w-100" id="uTable"><thead><tr><th>User</th><th>Username</th><th>Role</th><th>Status</th><th>Last sign-in</th><th></th></tr></thead></table>
</div></div>
<?php ob_start(); ?>
(function () {
  const { get, api, esc, fmt, table, modalForm, confirm, can, MG } = App;
  const dt = table('#uTable', {
    ajax: (d, cb) => get('admin.users', {}, { quiet: true }).then(r => cb({ data: r })).catch(() => cb({ data: [] })),
    columns: [
      { data: 'full_name', render: (v, t, r) => t === 'display' ? `<div class="d-flex align-items-center gap-2">${r.avatar ? `<img class="mg-avatar" src="uploads/avatars/${encodeURIComponent(r.avatar)}">` : `<span class="mg-avatar">${esc(v.split(' ').map(x => x[0]).slice(0, 2).join('').toUpperCase())}</span>`}<div><div class="fw-semibold">${esc(v)}</div><div class="small text-secondary">${esc(r.email)}${r.job_title ? ' · ' + esc(r.job_title) : ''}</div></div></div>` : v },
      { data: 'username' },
      { data: 'role_name', render: (v, t, r) => `<span class="pill pill-primary">${esc(v)}</span>${+r.overrides ? ` <span class="badge text-bg-warning" title="Has personal rights">+${r.overrides}</span>` : ''}` },
      { data: 'status', render: (v, t, r) => (v === 'active' ? '<span class="pill pill-success">Active</span>' : '<span class="pill pill-secondary">Inactive</span>') + (+r.must_change_password ? ' <span class="pill pill-warning">Must change password</span>' : '') },
      { data: 'last_login_at', render: (v, t) => t === 'display' ? (v ? fmt.datetime(v) : '<span class="text-secondary">never</span>') : v },
      { data: null, orderable: false, className: 'actions', render: (r) => `<div class="dropdown"><button class="btn btn-sm btn-light" data-bs-toggle="dropdown"><i class="bi bi-three-dots"></i></button><ul class="dropdown-menu dropdown-menu-end">
        ${can('users.edit') ? `<li><a class="dropdown-item" href="#" data-edit="${r.id}"><i class="bi bi-pencil me-2"></i>Edit</a></li>` : ''}
        ${can('roles.view') ? `<li><a class="dropdown-item" href="#" data-rights="${r.id}"><i class="bi bi-shield-lock me-2"></i>Personal rights</a></li>` : ''}
        ${can('users.edit') ? `<li><a class="dropdown-item" href="#" data-reset="${r.id}"><i class="bi bi-key me-2"></i>Reset password</a></li>
          <li><a class="dropdown-item" href="#" data-status="${r.id}" data-to="${r.status === 'active' ? 'inactive' : 'active'}"><i class="bi ${r.status === 'active' ? 'bi-person-slash' : 'bi-person-check'} me-2"></i>${r.status === 'active' ? 'Deactivate' : 'Activate'}</a></li>` : ''}
        ${can('users.delete') ? `<li><hr class="dropdown-divider"></li><li><a class="dropdown-item text-danger" href="#" data-del="${r.id}"><i class="bi bi-trash me-2"></i>Delete</a></li>` : ''}
      </ul></div>` }
    ]
  });
  const rows = () => dt.rows().data().toArray();
  const form = (u) => modalForm({
    title: u ? 'Edit ' + u.full_name : 'Add user', icon: 'bi-person-gear',
    fields: [
      { name: 'full_name', label: 'Full name', type: 'text', required: true },
      { name: 'job_title', label: 'Job title', type: 'text' },
      { name: 'username', label: 'Username', type: 'text', required: true },
      { name: 'email', label: 'Email', type: 'email', required: true },
      { name: 'phone', label: 'Phone', type: 'tel' },
      { name: 'role_id', label: 'Role', type: 'select', source: 'roles', required: true },
      { name: 'password', label: u ? 'New password (leave blank to keep)' : 'Initial password', type: 'password', required: !u },
      ...(u ? [] : [{ name: 'must_change', label: 'Ask them to change it at first sign-in', type: 'switch', default: 1 }])
    ],
    values: u ? Object.assign({}, u, { role_id__label: u.role_name }) : {},
    onSubmit: async (d) => { await api('admin.user_save', Object.assign({ id: u ? u.id : 0 }, d)); dt.ajax.reload(null, false); }
  });
  $('#addUser').on('click', () => form(null));
  const $t = $('#uTable');
  $t.on('click', '[data-edit]', function (e) { e.preventDefault(); form(rows().find(r => +r.id === +$(this).data('edit'))); });
  $t.on('click', '[data-status]', async function (e) { e.preventDefault(); await api('admin.user_status', { id: $(this).data('status'), status: $(this).data('to') }).catch(() => {}); dt.ajax.reload(null, false); });
  $t.on('click', '[data-del]', async function (e) { e.preventDefault(); if (await confirm({ title: 'Delete user?', text: 'Users with history should be deactivated instead.', icon: 'warning', danger: true, confirmText: 'Delete' })) { await api('admin.user_delete', { id: $(this).data('del') }).catch(() => {}); dt.ajax.reload(null, false); } });
  $t.on('click', '[data-reset]', async function (e) {
    e.preventDefault();
    if (!(await confirm({ title: 'Reset password?', text: 'A temporary password is generated. The user must change it at next sign-in.', confirmText: 'Reset' }))) return;
    const r = await api('admin.user_reset', { id: $(this).data('reset') });
    Swal.fire({ title: 'Temporary password', html: `<p class="small text-secondary">Share it securely. It is shown only once.</p><div class="fs-4 fw-bold font-monospace user-select-all p-2 rounded" style="background:var(--mg-muted-bg)">${esc(r.temporary_password)}</div>`, icon: 'success' });
    dt.ajax.reload(null, false);
  });
  $t.on('click', '[data-rights]', async function (e) {
    e.preventDefault();
    const [d, reg] = await Promise.all([get('admin.user_perms', { id: $(this).data('rights') }), get('admin.registry')]);
    if (+d.user.is_super) { Swal.fire('Administrator', 'Administrators already have every right.', 'info'); return; }
    const over = Object.fromEntries(d.overrides.map(o => [o.permission, +o.allow]));
    const role = new Set(d.role_perms);
    let html = `<p class="small text-secondary text-start">Role <strong>${esc(d.user.role_name)}</strong> gives the rights marked <i class="bi bi-check text-success"></i>. Use the dropdowns to grant or revoke individual rights for <strong>${esc(d.user.full_name)}</strong> only.</p><div class="table-responsive text-start" style="max-height:55vh"><table class="table table-sm"><tbody>`;
    Object.entries(reg.registry).forEach(([g, mods]) => {
      html += `<tr class="table-light"><td colspan="3" class="fw-bold small text-uppercase">${esc(g)}</td></tr>`;
      Object.entries(mods).forEach(([k, [label, acts]]) => acts.forEach(a => {
        const p = k + '.' + a, v = over[p];
        html += `<tr><td class="small">${esc(label)} — ${esc(reg.labels[a] || a)}</td><td class="text-center">${role.has(p) ? '<i class="bi bi-check text-success"></i>' : ''}</td>
          <td><select class="form-select form-select-sm up-sel" data-p="${p}"><option value="">Follow role</option><option value="1" ${v === 1 ? 'selected' : ''}>Grant</option><option value="0" ${v === 0 ? 'selected' : ''}>Revoke</option></select></td></tr>`;
      }));
    });
    html += '</tbody></table></div>';
    const r = await Swal.fire({ title: 'Personal rights', html, width: 720, showCancelButton: true, confirmButtonText: 'Save rights', showConfirmButton: can('roles.edit'),
      preConfirm: () => { const o = {}; document.querySelectorAll('.up-sel').forEach(s => { if (s.value !== '') o[s.dataset.p] = +s.value; }); return o; } });
    if (r.isConfirmed) { await api('admin.user_perms_save', { id: d.user.id, overrides: r.value }); dt.ajax.reload(null, false); }
  });
})();
<?php $pageInline = ob_get_clean(); ?>

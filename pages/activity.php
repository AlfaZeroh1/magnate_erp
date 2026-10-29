<div class="page-head">
  <div class="title"><div class="ico"><i class="bi bi-clock-history"></i></div>
    <div><h2>Activity Log</h2><div class="sub">Every change made in the system, who made it and when.</div></div></div>
</div>
<div class="card mb-3"><div class="card-body py-2"><div class="row g-2 align-items-end">
  <div class="col-12 col-md-3"><label class="form-label">User</label><select class="form-select form-select-sm" id="aUser"><option value="">Everyone</option>
    <?php foreach (App\Core\DB::all('SELECT id, full_name FROM users ORDER BY full_name') as $u): ?><option value="<?= (int)$u['id'] ?>"><?= e($u['full_name']) ?></option><?php endforeach; ?></select></div>
  <div class="col-6 col-md-2"><label class="form-label">Area</label><select class="form-select form-select-sm" id="aEntity"><option value="">All</option>
    <?php foreach (App\Core\DB::col('SELECT DISTINCT entity FROM activity_log ORDER BY entity') as $en): ?><option><?= e($en) ?></option><?php endforeach; ?></select></div>
  <div class="col-6 col-md-2"><label class="form-label">From</label><input type="date" class="form-control form-control-sm" id="aFrom" value="<?= date('Y-m-d', strtotime('-30 days')) ?>"></div>
  <div class="col-6 col-md-2"><label class="form-label">To</label><input type="date" class="form-control form-control-sm" id="aTo"></div>
</div></div></div>
<div class="card"><div class="card-body">
  <table class="table table-hover table-sm w-100" id="aTable"><thead><tr><th>When</th><th>User</th><th>Area</th><th>Action</th><th>What happened</th><th>IP</th></tr></thead></table>
</div></div>
<?php ob_start(); ?>
(function () {
  const { get, esc, fmt, table } = App;
  const dt = table('#aTable', {
    ajax: (d, cb) => get('admin.activity', { user_id: $('#aUser').val(), entity: $('#aEntity').val(), from: $('#aFrom').val(), to: $('#aTo').val() }, { quiet: true }).then(r => cb({ data: r })).catch(() => cb({ data: [] })),
    columns: [
      { data: 'created_at', render: (v, t) => t === 'display' ? fmt.datetime(v) : v },
      { data: 'user', defaultContent: 'System' },
      { data: 'entity', render: (v) => `<span class="badge text-bg-light">${esc(v)}</span>` },
      { data: 'action' },
      { data: 'description', render: (v, t, r) => r.order_id ? `<a href="index.php?p=order&id=${r.order_id}">${esc(v)}</a>` : esc(v) },
      { data: 'ip', defaultContent: '' }
    ], order: [[0, 'desc']]
  });
  $('#aUser,#aEntity,#aFrom,#aTo').on('change', () => dt.ajax.reload());
})();
<?php $pageInline = ob_get_clean(); ?>

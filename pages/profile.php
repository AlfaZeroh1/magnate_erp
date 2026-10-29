<?php $avatar = avatar_url($user); $forced = (int)$user['must_change_password'] === 1; ?>
<?php if ($forced): ?>
  <div class="alert alert-warning d-flex gap-2 align-items-center"><i class="bi bi-shield-exclamation fs-5"></i><div><strong>Please set a new password.</strong> Your password was reset by an administrator — choose your own before continuing.</div></div>
<?php endif; ?>
<div class="row g-3">
  <div class="col-lg-4">
    <div class="card mb-3"><div class="card-body text-center py-4">
      <div class="position-relative d-inline-block mb-3">
        <?php if ($avatar): ?><img class="mg-avatar lg" id="avImg" src="<?= e($avatar) ?>" alt=""><?php else: ?><span class="mg-avatar lg" id="avImg"><?= e(initials($user['full_name'])) ?></span><?php endif; ?>
        <label class="btn btn-primary btn-sm btn-icon rounded-circle position-absolute" style="right:-4px;bottom:-4px" title="Change photo"><i class="bi bi-camera"></i><input type="file" accept="image/png,image/jpeg,image/webp" hidden id="avFile"></label>
      </div>
      <h3 class="h5 fw-bold mb-0"><?= e($user['full_name']) ?></h3>
      <div class="text-secondary small"><?= e($user['job_title'] ?: $user['role_name']) ?></div>
      <div class="mt-2"><span class="pill pill-primary"><?= e($user['role_name']) ?></span></div>
      <?php if ($avatar): ?><button class="btn btn-link btn-sm text-danger mt-2" id="avRemove">Remove photo</button><?php endif; ?>
      <hr>
      <div class="small text-secondary text-start">
        <div class="d-flex justify-content-between"><span>Username</span><strong class="text-body"><?= e($user['username']) ?></strong></div>
        <div class="d-flex justify-content-between"><span>Last sign-in</span><span><?= e(fdate($user['last_login_at'], 'd M Y H:i')) ?></span></div>
        <div class="d-flex justify-content-between"><span>Password changed</span><span><?= e(fdate($user['password_changed_at']) ?: '—') ?></span></div>
      </div>
    </div></div>
    <div class="card"><div class="card-header"><h3>Appearance</h3></div><div class="card-body">
      <div class="btn-group w-100" role="group">
        <?php foreach (['light' => 'bi-sun', 'dark' => 'bi-moon-stars', 'auto' => 'bi-circle-half'] as $t => $i): ?>
          <input type="radio" class="btn-check" name="theme" id="th_<?= $t ?>" value="<?= $t ?>" <?= $user['theme'] === $t ? 'checked' : '' ?>>
          <label class="btn btn-outline-secondary" for="th_<?= $t ?>"><i class="bi <?= $i ?> me-1"></i><?= ucfirst($t) ?></label>
        <?php endforeach; ?>
      </div>
    </div></div>
  </div>
  <div class="col-lg-8">
    <div class="card mb-3"><div class="card-header"><h3><i class="bi bi-person me-1"></i>Personal details</h3></div><div class="card-body">
      <form id="pf" class="row g-3" novalidate>
        <div class="col-md-6"><label class="form-label">Full name</label><input class="form-control" name="full_name" value="<?= e($user['full_name']) ?>"><div class="invalid-feedback" data-err="full_name"></div></div>
        <div class="col-md-6"><label class="form-label">Job title</label><input class="form-control" name="job_title" value="<?= e($user['job_title']) ?>"></div>
        <div class="col-md-6"><label class="form-label">Email</label><input class="form-control" type="email" name="email" value="<?= e($user['email']) ?>"><div class="invalid-feedback" data-err="email"></div></div>
        <div class="col-md-6"><label class="form-label">Phone</label><input class="form-control" type="tel" name="phone" value="<?= e($user['phone']) ?>"></div>
        <div class="col-12"><button class="btn btn-primary"><i class="bi bi-check2 me-1"></i>Save details</button></div>
      </form>
    </div></div>
    <div class="card mb-3" id="password"><div class="card-header"><h3><i class="bi bi-key me-1"></i>Change password</h3></div><div class="card-body">
      <form id="pw" class="row g-3" novalidate autocomplete="off">
        <div class="col-md-4"><label class="form-label">Current password</label><input class="form-control" type="password" name="current_password" autocomplete="current-password"><div class="invalid-feedback" data-err="current_password"></div></div>
        <div class="col-md-4"><label class="form-label">New password</label><input class="form-control" type="password" name="new_password" autocomplete="new-password"><div class="invalid-feedback" data-err="new_password"></div>
          <div class="progress mt-1" style="height:4px"><div class="progress-bar" id="pwStrength" style="width:0"></div></div></div>
        <div class="col-md-4"><label class="form-label">Confirm new password</label><input class="form-control" type="password" name="confirm_password" autocomplete="new-password"><div class="invalid-feedback" data-err="confirm_password"></div></div>
        <div class="col-12 small text-secondary">At least 8 characters with letters and a number.</div>
        <div class="col-12"><button class="btn btn-primary"><i class="bi bi-shield-check me-1"></i>Update password</button></div>
      </form>
    </div></div>
    <div class="card"><div class="card-header"><h3><i class="bi bi-clock-history me-1"></i>My recent activity</h3></div><div class="card-body" id="myAct"></div></div>
  </div>
</div>
<?php ob_start(); ?>
(function () {
  const { api, get, esc, fmt, busy, toast } = App;
  const forced = <?= $forced ? 'true' : 'false' ?>;
  const submit = (formId, route, after) => $(formId).on('submit', async function (e) {
    e.preventDefault();
    const $f = $(this); $f.find('.is-invalid').removeClass('is-invalid');
    const $b = $f.find('button[type!=button]').last(); busy($b, true, 'Saving…');
    try { await api(route, Object.fromEntries(new FormData(this).entries())); after && after($f); }
    catch (err) { if (err.errors) Object.entries(err.errors).forEach(([k, v]) => { $f.find(`[name="${k}"]`).addClass('is-invalid'); $f.find(`[data-err="${k}"]`).text(v); }); }
    finally { busy($b, false); }
  });
  submit('#pf', 'profile.update', () => setTimeout(() => location.reload(), 600));
  submit('#pw', 'profile.password', ($f) => { $f[0].reset(); $('#pwStrength').css('width', 0); if (forced) setTimeout(() => location.href = 'index.php', 800); });
  $('#pw [name="new_password"]').on('input', function () {
    const v = this.value; let s = 0; if (v.length >= 8) s++; if (v.length >= 12) s++; if (/\d/.test(v) && /[A-Za-z]/.test(v)) s++; if (/[^A-Za-z0-9]/.test(v)) s++; if (/[A-Z]/.test(v) && /[a-z]/.test(v)) s++;
    $('#pwStrength').css('width', s * 20 + '%').attr('class', 'progress-bar bg-' + (s < 2 ? 'danger' : (s < 4 ? 'warning' : 'success')));
  });
  $('#avFile').on('change', async function () {
    if (!this.files[0]) return;
    const fd = new FormData(); fd.append('avatar', this.files[0]);
    try { await api('profile.avatar', fd); location.reload(); } catch (e) { }
  });
  $('#avRemove').on('click', async () => { await api('profile.avatar_remove', {}); location.reload(); });
  $('input[name=theme]').on('change', async function () { await api('profile.theme', { theme: this.value }, { silentSuccess: true }); location.reload(); });
  get('profile.activity', {}, { quiet: true }).then(rows => $('#myAct').html(rows.length ? `<div class="timeline">${rows.map(r => `<div class="tl-item"><div class="tl-dot" style="background:#e11d48"><i class="bi bi-dot"></i></div><div class="tl-title small">${r.order_id ? `<a href="index.php?p=order&id=${r.order_id}">${esc(r.description)}</a>` : esc(r.description)}</div><div class="tl-meta">${fmt.datetime(r.created_at)}</div></div>`).join('')}</div>` : '<div class="text-secondary small">No activity yet.</div>'));
  if (location.hash === '#password' || forced) setTimeout(() => $('#pw [name=current_password]').trigger('focus'), 300);
})();
<?php $pageInline = ob_get_clean(); ?>

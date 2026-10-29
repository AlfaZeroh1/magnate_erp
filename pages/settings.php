<?php $pageScripts = ['resource.js']; $edit = can('settings.edit'); ?>
<div class="page-head">
  <div class="title"><div class="ico"><i class="bi bi-building-gear"></i></div>
    <div><h2>Company Settings</h2><div class="sub">Details printed on invoices, packing lists, statements and every other document.</div></div></div>
</div>
<div class="row g-3">
  <div class="col-lg-8">
    <div class="card mb-3"><div class="card-header"><h3>Company</h3></div><div class="card-body">
      <form id="sf" class="row g-3"><fieldset class="row g-3 m-0 p-0" <?= $edit ? '' : 'disabled' ?>>
        <?php foreach ([
          ['company_name', 'Company name', 6], ['company_tagline', 'Tagline', 6], ['company_address', 'Address', 12], ['company_email', 'Email', 4], ['company_phone', 'Phone', 4],
          ['company_website', 'Website', 4], ['company_pin', 'KRA PIN / VAT no', 4], ['default_origin', 'Default airport of origin', 4], ['volumetric_divisor', 'Volumetric divisor (cm³/kg)', 4],
          ['doc_accent', 'Document accent colour', 4],
        ] as [$k, $label, $col]): ?>
          <div class="col-md-<?= $col ?>"><label class="form-label"><?= e($label) ?></label>
            <input class="form-control" name="<?= $k ?>" <?= $k === 'doc_accent' ? 'type="color" style="height:38px"' : '' ?> value="<?= e(setting($k)) ?>"></div>
        <?php endforeach; ?>
        <div class="col-12"><label class="form-label">Invoice payment terms text</label><textarea class="form-control" name="invoice_terms" rows="2"><?= e(setting('invoice_terms')) ?></textarea></div>
        <div class="col-12"><label class="form-label">Origin declaration (printed on invoices)</label><textarea class="form-control" name="origin_declaration" rows="2"><?= e(setting('origin_declaration')) ?></textarea></div>
        <?php if ($edit): ?><div class="col-12"><button class="btn btn-primary"><i class="bi bi-check2 me-1"></i>Save settings</button></div><?php endif; ?>
      </fieldset></form>
    </div></div>
  </div>
  <div class="col-lg-4">
    <div class="card mb-3"><div class="card-header"><h3>Logo</h3></div><div class="card-body text-center">
      <div class="p-3 rounded mb-3" style="background:#fff;border:1px dashed var(--mg-border)"><img src="<?= asset('img/logo.png') ?>" id="logoPreview" alt="Logo" style="max-width:100%;max-height:90px"></div>
      <p class="small text-secondary">Used on all documents as <code>assets/img/logo.png</code>. A wide PNG with a transparent background looks best (e.g. 1440×360).</p>
      <?php if ($edit): ?><label class="btn btn-light"><i class="bi bi-upload me-1"></i>Upload new PNG<input type="file" hidden accept="image/png" id="logoFile"></label><?php endif; ?>
    </div></div>
  </div>
  <div class="col-12"><div id="banks"></div></div>
</div>
<?php ob_start(); ?>
(function () {
  const { api, busy } = App;
  $('#sf').on('submit', async function (e) { e.preventDefault(); const $b = $(this).find('button'); busy($b, true, 'Saving…'); try { await api('admin.settings_save', Object.fromEntries(new FormData(this).entries())); } catch (e) { } finally { busy($b, false); } });
  $('#logoFile').on('change', async function () { if (!this.files[0]) return; const fd = new FormData(); fd.append('logo', this.files[0]); try { const r = await api('admin.logo_upload', fd); $('#logoPreview').attr('src', r.url + '&t=' + Date.now()); } catch (e) { } });
  App.mountResource($('#banks'), 'bank_accounts');
})();
<?php $pageInline = ob_get_clean(); ?>

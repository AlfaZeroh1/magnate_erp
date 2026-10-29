/* =====================================================================
   MAGNATE — front-end core
   window.MG is injected by the layout: { csrf, user, perms, base, currencies, missingRates }
   ===================================================================== */
(function () {
  'use strict';
  const MG = window.MG || {};

  // ------------------------------------------------------------------
  //  Progress bar
  // ------------------------------------------------------------------
  const bar = document.getElementById('mg-progress');
  let inflight = 0, barTimer = null;
  const progress = {
    start() {
      inflight++;
      if (!bar || inflight > 1) return;
      bar.classList.add('on'); bar.style.width = '15%';
      clearInterval(barTimer);
      barTimer = setInterval(() => {
        const w = parseFloat(bar.style.width) || 0;
        bar.style.width = Math.min(90, w + (90 - w) * 0.12) + '%';
      }, 200);
    },
    done() {
      inflight = Math.max(0, inflight - 1);
      if (!bar || inflight > 0) return;
      clearInterval(barTimer);
      bar.style.width = '100%';
      setTimeout(() => { bar.classList.remove('on'); bar.style.width = '0'; }, 300);
    }
  };

  // ------------------------------------------------------------------
  //  Helpers
  // ------------------------------------------------------------------
  const esc = (s) => String(s ?? '').replace(/[&<>"'`]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;', '`': '&#96;' }[c]));
  const num = (v) => { const n = parseFloat(String(v ?? '').replace(/,/g, '')); return isNaN(n) ? 0 : n; };
  // 'YYYY-MM-DD' must be read as a local date, not UTC midnight
  const toDate = (d) => new Date(/^\d{4}-\d{2}-\d{2}$/.test(String(d)) ? d + 'T00:00:00' : String(d).replace(' ', 'T'));
  const fmt = {
    money: (n, dp = 2) => (n === null || n === undefined || n === '') ? '' : num(n).toLocaleString('en-US', { minimumFractionDigits: dp, maximumFractionDigits: dp }),
    int: (n) => (n === null || n === undefined || n === '') ? '' : Math.round(num(n)).toLocaleString('en-US'),
    qty: (n) => { if (n === null || n === undefined || n === '') return ''; const v = num(n); return Number.isInteger(v) ? v.toLocaleString('en-US') : v.toLocaleString('en-US', { maximumFractionDigits: 2 }); },
    rate: (n) => (n === null || n === undefined || n === '') ? '' : num(n).toLocaleString('en-US', { maximumFractionDigits: 6 }),
    pct: (n, dp = 1) => (n === null || n === undefined || !isFinite(n)) ? '—' : num(n).toFixed(dp) + '%',
    date: (d) => {
      if (!d) return '';
      const t = toDate(d);
      return isNaN(t) ? d : t.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
    },
    datetime: (d) => {
      if (!d) return '';
      const t = toDate(d);
      return isNaN(t) ? d : t.toLocaleString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
    },
    ago: (d) => {
      const t = toDate(d); const s = (Date.now() - t) / 1000;
      if (isNaN(s)) return '';
      if (s < 60) return 'just now'; if (s < 3600) return Math.floor(s / 60) + 'm ago';
      if (s < 86400) return Math.floor(s / 3600) + 'h ago'; if (s < 604800) return Math.floor(s / 86400) + 'd ago';
      return fmt.date(d);
    }
  };
  const today = () => { const d = new Date(); d.setMinutes(d.getMinutes() - d.getTimezoneOffset()); return d.toISOString().slice(0, 10); };
  const can = (p) => (MG.perms || []).includes(p) || !!(MG.user && MG.user.is_super);
  const currencyCode = (id) => { const c = (MG.currencies || []).find(x => +x.id === +id); return c ? c.code : ''; };
  const loader = (text = 'Loading…') => `<div class="mg-loader"><img class="mg-bloom" src="assets/img/favicon.svg" alt=""><span>${esc(text)}</span></div>`;
  const skeletonRows = (n = 5) => Array.from({ length: n }, () => '<div class="skeleton mb-2" style="height:38px"></div>').join('');
  const debounce = (fn, ms = 250) => { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); }; };

  const STAGES = MG.stages || {};
  const stagePill = (s) => { const m = STAGES[s] || [s, 'secondary']; return `<span class="pill pill-${m[1]}">${esc(m[0])}</span>`; };

  // ------------------------------------------------------------------
  //  API
  // ------------------------------------------------------------------
  async function api(route, data = {}, opts = {}) {
    const method = opts.method || 'POST';
    let url = 'api.php?r=' + encodeURIComponent(route);
    const init = { method, headers: { 'X-CSRF-Token': MG.csrf || '', 'Accept': 'application/json' }, credentials: 'same-origin' };
    if (method === 'GET') {
      const qs = new URLSearchParams();
      Object.entries(data || {}).forEach(([k, v]) => { if (v !== undefined && v !== null && v !== '') qs.append(k, v); });
      const s = qs.toString(); if (s) url += '&' + s;
    } else if (data instanceof FormData) {
      init.body = data;
    } else {
      init.headers['Content-Type'] = 'application/json';
      init.body = JSON.stringify(data || {});
    }
    if (!opts.quiet) progress.start();
    try {
      const res = await fetch(url, init);
      let body;
      try { body = await res.json(); } catch (e) { body = { ok: false, message: 'Unexpected server response (' + res.status + ').' }; }
      if (res.status === 401) {
        toast('Your session ended — please sign in again.', 'warning');
        setTimeout(() => location.href = 'login.php', 1200);
        throw body;
      }
      if (!body.ok) {
        if (body.errors && body.errors._fx && !opts.noFxPrompt) fxQuickSet(body.errors._fx, body.message);
        else if (!opts.silent) toast(body.message || 'Request failed.', 'error');
        if (body.debug) console.warn('[server]', body.debug);
        throw body;
      }
      if (body.message && !opts.silentSuccess) toast(body.message, 'success');
      return body.data;
    } catch (err) {
      if (err instanceof TypeError) { toast('Network error — check your connection.', 'error'); throw { ok: false, message: 'Network error' }; }
      throw err;
    } finally {
      if (!opts.quiet) progress.done();
    }
  }
  const get = (route, data = {}, opts = {}) => api(route, data, Object.assign({ method: 'GET' }, opts));

  // ------------------------------------------------------------------
  //  Notifications
  // ------------------------------------------------------------------
  const Toast = window.Swal ? Swal.mixin({ toast: true, position: 'bottom-end', showConfirmButton: false, timer: 3200, timerProgressBar: true }) : null;
  function toast(msg, icon = 'success') { if (Toast) Toast.fire({ icon, title: msg }); else alert(msg); }

  async function confirm({ title = 'Are you sure?', text = '', confirmText = 'Yes, continue', icon = 'question', danger = false, input = null, inputLabel = '' } = {}) {
    const r = await Swal.fire({
      title, html: text, icon, showCancelButton: true, confirmButtonText: confirmText, cancelButtonText: 'Cancel',
      reverseButtons: true, focusCancel: danger, input: input || undefined, inputLabel: inputLabel || undefined,
      customClass: danger ? { confirmButton: 'swal2-confirm bg-danger' } : {}
    });
    if (input) return r.isConfirmed ? (r.value ?? '') : null;
    return r.isConfirmed;
  }

  function busy(btn, on, text) {
    if (!btn) return;
    const $b = $(btn);
    if (on) { $b.data('html', $b.html()).prop('disabled', true).html(`<span class="spinner-border me-1"></span>${esc(text || 'Working…')}`); }
    else { $b.prop('disabled', false).html($b.data('html')); }
  }

  // ------------------------------------------------------------------
  //  Select2 remote select
  // ------------------------------------------------------------------
  function select2(el, source, opts = {}) {
    const $el = $(el);
    $el.select2({
      theme: 'bootstrap-5', width: '100%', allowClear: !opts.required, placeholder: opts.placeholder || 'Search…',
      dropdownParent: opts.parent ? $(opts.parent) : $el.closest('.modal').length ? $el.closest('.modal') : $(document.body),
      ajax: {
        url: 'api.php', delay: 200, cache: true,
        data: (p) => ({ r: 'resource.options', source, q: p.term || '' }),
        processResults: (res) => ({ results: (res && res.data) || [] })
      },
      templateResult: (d) => d.loading ? d.text : $(`<div>${esc(d.text)}${d.category ? `<div class="small text-secondary">${esc(d.category)}</div>` : ''}</div>`)
    });
    return $el;
  }
  /** Set a select2 value; fetches the label when the option is not loaded yet. */
  async function setSelect(el, source, id, label) {
    const $el = $(el);
    if (!id) { $el.val(null).trigger('change'); return; }
    if (!$el.find(`option[value="${id}"]`).length) {
      if (!label) {
        if (source === 'currencies') label = currencyCode(id);
        else {
          const rows = await get('resource.options', { source, id }, { quiet: true, silent: true }).catch(() => []);
          label = rows && rows[0] ? rows[0].text : ('#' + id);
        }
      }
      $el.append(new Option(label, id, true, true));
    }
    $el.val(String(id)).trigger('change');
  }

  // ------------------------------------------------------------------
  //  Form builder (used by resource pages and custom modals)
  // ------------------------------------------------------------------
  function fieldHtml(f, v, values) {
    const id = 'f_' + f.name + '_' + Math.random().toString(36).slice(2, 7);
    const col = f.col ? `col-md-${f.col}` : (f.type === 'textarea' ? 'col-12' : 'col-md-6');
    const req = f.required ? ' <span class="req">*</span>' : '';
    const lbl = `<label class="form-label" for="${id}">${esc(f.label || f.name)}${req}</label>`;
    const help = f.help ? `<div class="form-text">${esc(f.help)}</div>` : '';
    const ro = f.readonly ? ' disabled' : '';
    const val = v === null || v === undefined ? '' : v;
    let input = '';
    switch (f.type) {
      case 'textarea':
        input = `<textarea class="form-control" id="${id}" name="${f.name}" rows="${f.rows || 2}"${ro}>${esc(val)}</textarea>`; break;
      case 'number': case 'money': case 'rate':
        input = `<input type="number" class="form-control tabular" id="${id}" name="${f.name}" value="${esc(val)}" step="${f.type === 'rate' ? '0.000001' : (f.type === 'money' ? '0.01' : 'any')}" min="0" inputmode="decimal"${f.required ? ' required' : ''}${ro}>`;
        if (f.type === 'money' || f.type === 'rate') input += `<div class="fx-hint d-none" data-hint="${f.name}"></div>`;
        break;
      case 'date':
        input = `<input type="date" class="form-control" id="${id}" name="${f.name}" value="${esc(val)}"${f.required ? ' required' : ''}${ro}>`; break;
      case 'select':
        input = `<select class="form-select" id="${id}" name="${f.name}" data-source="${f.source || ''}"${f.multiple ? ' multiple' : ''}${f.required ? ' required' : ''}${ro}></select>`; break;
      case 'switch':
        return `<div class="${col} d-flex align-items-end"><div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" role="switch" id="${id}" name="${f.name}" ${(val === '' ? f.default : +val) ? 'checked' : ''}${ro}><label class="form-check-label" for="${id}">${esc(f.label)}</label></div></div>`;
      case 'combo':
        input = `<input type="text" class="form-control" id="${id}" name="${f.name}" value="${esc(val)}" list="${id}_dl" autocomplete="off"${f.required ? ' required' : ''}${ro}><datalist id="${id}_dl">${(f.options || []).map(o => `<option value="${esc(o)}">`).join('')}</datalist>`; break;
      case 'static':
        input = `<input type="text" class="form-control-plaintext fw-semibold" id="${id}" value="${esc(val || '')}" placeholder="${esc(f.placeholder || '')}" readonly>`; break;
      case 'password':
        input = `<input type="password" class="form-control" id="${id}" name="${f.name}" autocomplete="new-password"${f.required ? ' required' : ''}>`; break;
      default:
        input = `<input type="${f.type === 'email' || f.type === 'tel' ? f.type : 'text'}" class="form-control" id="${id}" name="${f.name}" value="${esc(val)}" ${f.maxlength ? `maxlength="${f.maxlength}"` : ''}${f.required ? ' required' : ''}${ro}>`;
    }
    return `<div class="${col}">${lbl}${input}<div class="invalid-feedback" data-err="${f.name}"></div>${help}</div>`;
  }

  /**
   * Opens a modal form.
   *   fields: field defs (see Resources.php), values: initial values,
   *   fx: { currency, rate, date, amounts: {amountField: baseField} }
   *   onSubmit(data, $modal) => Promise   (reject with {errors} to show field errors)
   */
  function modalForm({ title, icon = 'bi-pencil-square', fields, values = {}, fx = null, submitText = 'Save', size = 'lg', onSubmit, onReady }) {
    const visible = fields.filter(f => f.form !== false);
    const html = `
      <div class="modal fade" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-${size} modal-dialog-scrollable modal-fullscreen-sm-down">
          <form class="modal-content" novalidate>
            <div class="modal-header"><h5 class="modal-title"><i class="bi ${icon} text-primary"></i>${esc(title)}</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body"><div class="row g-3">${visible.map(f => fieldHtml(f, values[f.name] ?? (f.default === 'today' ? today() : (f.default === 'base' ? (MG.base && MG.base.id) : (f.type === 'switch' ? '' : f.default))), values)).join('')}</div></div>
            <div class="modal-footer">
              <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-primary px-4"><i class="bi bi-check2 me-1"></i>${esc(submitText)}</button>
            </div>
          </form>
        </div>
      </div>`;
    const $m = $(html).appendTo('body');
    const modal = new bootstrap.Modal($m[0]);
    const $f = $m.find('form');
    const field = (name) => $f.find(`[name="${name}"]`);

    // init selects
    const pending = [];
    visible.filter(f => f.type === 'select').forEach(f => {
      const $s = field(f.name);
      select2($s, f.source, { required: f.required, parent: $m });
      if (values[f.name] && !f.multiple) pending.push(setSelect($s, f.source, values[f.name], values[f.name + '__label']));
      else if (f.default === 'base' && MG.base) pending.push(setSelect($s, 'currencies', MG.base.id, MG.base.code));
      if (f.fills) {
        $s.on('select2:select', (e) => {
          const d = e.params.data || {};
          Object.entries(f.fills).forEach(([target, key]) => {
            const tf = fields.find(x => x.name === target);
            if (d[key] === undefined || !tf) return;
            if (tf.type === 'select') setSelect(field(target), tf.source, d[key]);
            else field(target).val(d[key]).trigger('input');
          });
        });
      }
    });

    // FX behaviour
    if (fx) {
      const $cur = field(fx.currency), $rate = field(fx.rate), $date = field(fx.date);
      const baseOf = Object.entries(fx.amounts || {});
      const rateHint = $f.find(`[data-hint="${fx.rate}"]`);
      const isBase = () => +$cur.val() === +(MG.base && MG.base.id);
      const recalc = () => {
        const rate = num($rate.val()); const code = currencyCode($cur.val());
        baseOf.forEach(([amt, base]) => {
          const kes = num(field(amt).val()) * rate;
          const $base = field(base);
          if ($base.length && !$base.is(':focus')) $base.val(field(amt).val() === '' ? '' : kes.toFixed(2));
          const $h = $f.find(`[data-hint="${amt}"]`);
          if (field(amt).val() !== '' && rate > 0 && !isBase()) $h.removeClass('d-none warn').html(`≈ ${MG.base.code} ${fmt.money(kes)} <span class="text-secondary">@ ${fmt.rate(rate)}</span>`);
          else $h.addClass('d-none');
        });
        if (code && !isBase()) rateHint.removeClass('d-none warn').text(`${MG.base.code} per 1 ${code}`);
      };
      const loadRate = async () => {
        if (!$cur.val()) return;
        if (isBase()) { $rate.val(1).prop('readonly', true); rateHint.addClass('d-none'); recalc(); return; }
        $rate.prop('readonly', false);
        const r = await get('fx.rate', { currency_id: $cur.val(), date: $date.val() || today() }, { quiet: true, silent: true }).catch(() => null);
        if (r && r.rate) { $rate.val(r.rate); recalc(); }
        else { $rate.val(''); rateHint.removeClass('d-none').addClass('warn').html(`<i class="bi bi-exclamation-triangle"></i> No ${esc(currencyCode($cur.val()))} rate set for this date — type one, or set it under Exchange Rates.`); recalc(); }
      };
      $cur.on('change', loadRate); $date.on('change', loadRate);
      $f.on('input', [...baseOf.map(([a]) => `[name="${a}"]`), `[name="${fx.rate}"]`].join(','), recalc);
      // typing the KES amount back-computes the rate
      baseOf.forEach(([amt, base]) => field(base).on('input', () => {
        const a = num(field(amt).val()); const b = num(field(base).val());
        if (a > 0 && b > 0) { $rate.val((b / a).toFixed(6)); baseOf.filter(([x]) => x !== amt).forEach(() => recalc()); $f.find(`[data-hint="${amt}"]`).addClass('d-none'); }
      }));
      Promise.all(pending).then(() => { if (!values[fx.rate]) loadRate(); else { if (isBase()) $rate.prop('readonly', true); recalc(); } });
    }

    $f.on('submit', async (e) => {
      e.preventDefault();
      $f.find('.is-invalid').removeClass('is-invalid');
      const data = {};
      visible.forEach(f => {
        if (f.type === 'static' || f.readonly) return;
        const $i = field(f.name);
        if (f.type === 'switch') data[f.name] = $i.is(':checked') ? 1 : 0;
        else data[f.name] = $i.val() ?? '';
      });
      // client-side required check
      let bad = false;
      visible.forEach(f => {
        if (f.required && !f.readonly && f.type !== 'static' && (data[f.name] === '' || data[f.name] === null || (Array.isArray(data[f.name]) && !data[f.name].length))) {
          showErr(f.name, (f.label || f.name) + ' is required.'); bad = true;
        }
      });
      if (bad) return;
      const $btn = $f.find('[type=submit]');
      busy($btn, true, 'Saving…');
      try {
        await onSubmit(data, $m);
        modal.hide();
      } catch (err) {
        if (err && err.errors) Object.entries(err.errors).forEach(([k, v]) => { if (k !== '_fx') showErr(k, v); });
      } finally { busy($btn, false); }
    });
    function showErr(name, msg) {
      const $i = field(name);
      $i.addClass('is-invalid'); $i.next('.select2').find('.select2-selection').addClass('is-invalid');
      $f.find(`[data-err="${name}"]`).text(msg).show();
    }
    $m.on('hidden.bs.modal', () => { $m.find('select').each(function () { if ($(this).data('select2')) $(this).select2('destroy'); }); $m.remove(); });
    $m.on('shown.bs.modal', () => { $f.find('input:not([type=hidden]):not([readonly]),textarea').first().trigger('focus'); });
    modal.show();
    if (onReady) onReady($m, field);
    return { $m, modal, field };
  }

  // ------------------------------------------------------------------
  //  DataTables wrapper
  // ------------------------------------------------------------------
  function table(el, opts = {}) {
    const $t = $(el);
    if ($.fn.dataTable.isDataTable($t)) $t.DataTable().destroy();
    return $t.DataTable(Object.assign({
      responsive: true, autoWidth: false, pageLength: 25, lengthMenu: [10, 25, 50, 100, 500],
      order: [], deferRender: true,
      language: { search: '', searchPlaceholder: 'Search…', emptyTable: '<div class="empty"><i class="bi bi-inbox"></i>Nothing here yet</div>', zeroRecords: 'No matching records' }
    }, opts));
  }

  // ------------------------------------------------------------------
  //  Exchange-rate prompt (on login, or when a save needs a missing rate)
  // ------------------------------------------------------------------
  async function fxPrompt(list, { title = 'Set this month\'s exchange rates', intro } = {}) {
    if (!list || !list.length) return;
    const canEdit = can('exchange_rates.edit');
    const rows = list.map(c => `
      <div class="d-flex align-items-center gap-2 mb-2">
        <div style="width:70px" class="fw-bold text-start">${esc(c.code)}</div>
        <div class="input-group">
          <span class="input-group-text small">1 ${esc(c.code)} =</span>
          <input type="number" step="0.000001" min="0" class="form-control fx-in" data-id="${c.id}" value="" placeholder="${c.last_rate ? fmt.rate(c.last_rate) : ''}" ${canEdit ? '' : 'disabled'}>
          <span class="input-group-text small">${esc(MG.base.code)}</span>
        </div>
      </div>
      ${c.last_rate ? `<div class="text-start small text-secondary mb-2" style="margin-left:78px">Last: ${fmt.rate(c.last_rate)} on ${fmt.date(c.last_date)}</div>` : ''}`).join('');
    const r = await Swal.fire({
      title, icon: 'info', width: 560,
      html: `<p class="text-secondary small">${intro || 'These currencies have no rate set for ' + new Date().toLocaleString('en-GB', { month: 'long', year: 'numeric' }) + '. Rates apply from today until you set a new one.'}</p>${rows}
             ${canEdit ? '' : '<div class="alert alert-warning small mt-2 mb-0">You do not have the right to set rates. Ask an administrator or accountant.</div>'}`,
      showCancelButton: true, confirmButtonText: canEdit ? 'Save rates' : 'OK', cancelButtonText: 'Remind me later',
      showConfirmButton: canEdit,
      preConfirm: () => {
        const rates = [...document.querySelectorAll('.fx-in')].map(i => ({ currency_id: i.dataset.id, rate: i.value || i.placeholder.replace(/,/g, '') })).filter(x => num(x.rate) > 0);
        if (!rates.length) { Swal.showValidationMessage('Enter at least one rate'); return false; }
        return api('fx.set_many', { rates }).catch(e => { Swal.showValidationMessage(e.message || 'Failed'); return false; });
      }
    });
    if (r.isConfirmed) document.dispatchEvent(new CustomEvent('mg:rates-updated'));
  }
  function fxQuickSet(fxErr, message) {
    fxPrompt([{ id: fxErr.currency_id, code: fxErr.code }], { title: 'Exchange rate needed', intro: esc(message) });
  }

  // ------------------------------------------------------------------
  //  Charts defaults
  // ------------------------------------------------------------------
  if (window.Chart) {
    Chart.defaults.font.family = getComputedStyle(document.body).getPropertyValue('--bs-body-font-family') || 'Inter, sans-serif';
    Chart.defaults.color = getComputedStyle(document.body).getPropertyValue('--bs-secondary-color') || '#6b7280';
    Chart.defaults.plugins.legend.labels.usePointStyle = true;
    Chart.defaults.maintainAspectRatio = false;
  }
  const palette = ['#e11d48', '#14532d', '#f59e0b', '#2563eb', '#7c3aed', '#0d9488', '#fb7185', '#65a30d', '#64748b', '#db2777'];

  // ------------------------------------------------------------------
  //  Shell: sidebar, theme, quick nav, notifications
  // ------------------------------------------------------------------
  $(function () {
    $(document).on('click', '[data-toggle-sidebar]', () => document.body.classList.toggle('sidebar-open'));
    $(document).on('click', '.mg-backdrop', () => document.body.classList.remove('sidebar-open'));

    // theme
    const applyTheme = (t) => {
      const mode = t === 'auto' ? (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light') : t;
      document.documentElement.setAttribute('data-bs-theme', mode);
      $('[data-theme-icon]').attr('class', 'bi ' + (mode === 'dark' ? 'bi-sun' : 'bi-moon-stars'));
    };
    applyTheme(MG.user ? MG.user.theme : 'auto');
    $(document).on('click', '[data-theme-toggle]', async () => {
      const next = document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
      applyTheme(next);
      api('profile.theme', { theme: next }, { quiet: true, silentSuccess: true, silent: true }).catch(() => {});
    });

    // page navigation progress
    $(document).on('click', 'a[href]:not([target]):not([href^="#"]):not([data-bs-toggle]):not([download])', function (e) {
      if (e.metaKey || e.ctrlKey || e.shiftKey) return;
      const href = this.getAttribute('href');
      if (href && !href.startsWith('javascript')) progress.start();
    });
    window.addEventListener('pageshow', () => { inflight = 1; progress.done(); });

    // quick navigation (Ctrl/Cmd + K)
    const items = [...document.querySelectorAll('.mg-nav a')].map(a => ({ href: a.getAttribute('href'), text: a.textContent.trim() }));
    const openQuick = async () => {
      const { value } = await Swal.fire({
        title: 'Go to…', input: 'text', inputPlaceholder: 'Type a page or an order / invoice number', showConfirmButton: false, showCloseButton: true,
        html: '<div id="qk-list" class="list-group text-start mt-2" style="max-height:50vh;overflow:auto"></div>',
        didOpen: () => {
          const $in = $(Swal.getInput()); const $l = $('#qk-list');
          const render = (q = '') => {
            const ql = q.toLowerCase();
            const hits = items.filter(i => i.text.toLowerCase().includes(ql)).slice(0, 8);
            let html = hits.map(i => `<a class="list-group-item list-group-item-action" href="${i.href}"><i class="bi bi-arrow-return-right me-2 text-secondary"></i>${esc(i.text)}</a>`).join('');
            if (q.length >= 2) html += `<a class="list-group-item list-group-item-action" href="index.php?p=orders&q=${encodeURIComponent(q)}"><i class="bi bi-search me-2 text-secondary"></i>Search orders for “${esc(q)}”</a>`;
            $l.html(html);
          };
          render();
          $in.on('input', () => render($in.val()));
          $in.on('keydown', (e) => { if (e.key === 'Enter') { const a = $l.find('a').first(); if (a.length) location.href = a.attr('href'); } });
        }
      });
      return value;
    };
    $(document).on('click', '[data-quick-nav]', openQuick);
    document.addEventListener('keydown', (e) => { if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); openQuick(); } });

    // tooltips
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));

    // monthly FX reminder right after sign-in (and a bell badge always)
    if (MG.missingRates && MG.missingRates.length && MG.justLoggedIn) setTimeout(() => fxPrompt(MG.missingRates), 600);
    $(document).on('click', '[data-fx-prompt]', (e) => {
      e.preventDefault();
      const list = (MG.missingRates && MG.missingRates.length) ? MG.missingRates : (MG.currencies || []).filter(c => +c.id !== +MG.base.id);
      fxPrompt(list, MG.missingRates && MG.missingRates.length ? {} : { title: 'Update exchange rates', intro: 'All currencies already have a rate this month. Enter new rates to apply from today.' });
    });
  });

  window.App = { MG, api, get, toast, confirm, busy, esc, num, fmt, today, can, loader, skeletonRows, debounce, select2, setSelect, modalForm, table, fxPrompt, currencyCode, stagePill, palette, progress };
})();

<?php $pageScripts = ['freight.js']; ?>
<div class="page-head">
  <div class="title"><div class="ico"><i class="bi bi-airplane"></i></div>
    <div><h2>Freight Space</h2><div class="sub">Book cargo capacity on flights, resell it per kg to customers who ship their own flowers, invoice them and track how full and how profitable each booking is.</div></div></div>
  <div class="actions">
    <button class="btn btn-light" data-bs-toggle="collapse" data-bs-target="#howItWorks"><i class="bi bi-question-circle me-1"></i>How it works</button>
    <?php if (can('freight.create')): ?><button class="btn btn-primary" id="newBooking"><i class="bi bi-plus-lg me-1"></i>Book space</button><?php endif; ?>
  </div>
</div>
<div class="collapse mb-3" id="howItWorks"><div class="card"><div class="card-body small">
  <div class="row g-3">
    <div class="col-md-3"><div class="fw-bold mb-1"><span class="badge text-bg-primary me-1">1</span>Book space</div>Reserve e.g. 2,000 kg on a flight with an agent/airline at a cost per kg (plus handling). This is what you owe the agent — it shows on the agent statement.</div>
    <div class="col-md-3"><div class="fw-bold mb-1"><span class="badge text-bg-primary me-1">2</span>Sell space</div>Allocate kg to customers who source their own flowers. They pay per <em>chargeable kg</em> — the higher of actual (gross) weight and volumetric weight (L×W×H ÷ <?= e(setting('volumetric_divisor', '6000')) ?>).</div>
    <div class="col-md-3"><div class="fw-bold mb-1"><span class="badge text-bg-primary me-1">3</span>Invoice</div>One click invoices each customer's space (+ handling extras). Freight invoices share the normal invoice numbers and appear on customer statements.</div>
    <div class="col-md-3"><div class="fw-bold mb-1"><span class="badge text-bg-primary me-1">4</span>Track</div>Utilisation, break-even kg and margin per booking. Unsold space is pure cost — the dashboard warns you about bookings departing soon with free space.</div>
  </div>
</div></div></div>
<div class="row g-3 mb-3" id="frKpis"></div>
<div class="card"><div class="card-body">
  <div class="d-flex gap-2 mb-2"><select class="form-select form-select-sm" id="fStatus" style="width:auto"><option value="">All</option><option value="open" selected>Open</option><option value="closed">Closed</option><option value="cancelled">Cancelled</option></select></div>
  <table class="table table-hover w-100" id="fbTable"><thead><tr>
    <th>Booking</th><th>Departure</th><th>Route / flight</th><th>Agent</th><th class="num">Capacity</th><th>Utilisation</th><th class="num">Cost (<?= e(App\Core\Fx::baseCode()) ?>)</th><th class="num">Sales</th><th class="num">Margin</th><th>Status</th>
  </tr></thead></table>
</div></div>

<?php $pageScripts = ['orders.js']; ?>
<div class="page-head">
  <div class="title"><div class="ico"><i class="bi bi-flower1"></i></div>
    <div><h2>Orders</h2><div class="sub">Capture customer orders and move them through sourcing, confirmation, delivery check, packing and invoicing.</div></div>
  </div>
  <div class="actions">
    <?php if (can('orders.create')): ?>
      <button class="btn btn-primary" id="newOrder"><i class="bi bi-plus-lg me-1"></i>New order</button>
    <?php endif; ?>
  </div>
</div>

<div class="card mb-3">
  <div class="card-body py-2">
    <ul class="nav nav-tabs-modern" id="stageTabs">
      <li class="nav-item"><a class="nav-link active" href="#" data-stage="active">Active <span class="badge text-bg-light" data-count="active"></span></a></li>
      <?php foreach (stage_meta() as $k => [$label, $color, $icon]): ?>
        <li class="nav-item"><a class="nav-link" href="#" data-stage="<?= $k ?>"><i class="bi <?= $icon ?> me-1"></i><?= e($label) ?> <span class="badge text-bg-light" data-count="<?= $k ?>"></span></a></li>
      <?php endforeach; ?>
      <li class="nav-item"><a class="nav-link" href="#" data-stage="all">All</a></li>
    </ul>
    <div class="row g-2 mt-1 align-items-end">
      <div class="col-6 col-md-2"><label class="form-label">Departure from</label><input type="date" class="form-control form-control-sm" id="fFrom"></div>
      <div class="col-6 col-md-2"><label class="form-label">to</label><input type="date" class="form-control form-control-sm" id="fTo"></div>
      <div class="col-12 col-md-3"><label class="form-label">Customer</label><select class="form-select form-select-sm" id="fCustomer"></select></div>
      <div class="col-12 col-md-auto"><div class="form-check mb-1"><input class="form-check-input" type="checkbox" id="fDue"><label class="form-check-label small" for="fDue">Departing within 24h, not invoiced</label></div></div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-body">
    <table class="table table-hover w-100" id="ordersTable">
      <thead><tr>
        <th>Order</th><th>Customer</th><th>Order date</th><th>Departure</th><th>Stage</th>
        <th class="num">Lines</th><th class="num">Stems</th><th class="num">Value</th><th>Docs</th><th></th>
      </tr></thead>
    </table>
  </div>
</div>

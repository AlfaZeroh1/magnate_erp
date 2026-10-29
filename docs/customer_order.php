<?php
/** Customer order — what the customer asked for, at the agreed selling prices. */
use App\Services\OrderService as OS;

$o = OS::find($id);
$lines = OS::lines($id);
$docTitle = 'Customer Order'; $docNo = $o['order_no'];
doc_open($company, 'Customer Order', $o['order_no'], fdate($o['order_date']), ['Departure' => fdate($o['departure_date']), 'Ref' => $o['customer_ref']], '', $o['stage'] === 'cancelled');
$total = 0; $stems = 0;
?>
<div class="grid">
  <div class="box"><h4>Consignee</h4><div class="big"><?= e($o['customer_name']) ?></div><div class="muted"><?= e($o['country']) ?><?= $o['truck'] ? ' · Truck ' . e($o['truck']) : '' ?></div></div>
  <div class="box"><h4>Shipment</h4><div class="kv">
    <span>Shipping agent</span><span><?= e($o['shipping_agent'] ?: '—') ?></span>
    <span>Box type</span><span><?= e($o['box_type'] ?: '—') ?></span>
    <span>Drop point</span><span><?= e($o['drop_point'] ?: '—') ?></span>
    <span>Terms</span><span><?= e($o['terms'] ?: '—') ?></span>
    <span>Currency</span><span><?= e($o['currency_code']) ?></span>
  </div></div>
</div>
<div class="table-wrap"><table class="t">
  <thead><tr><th>#</th><th>Variety</th><th>Length</th><th>Order by</th><th class="r">Stems</th><th class="r">Price (<?= e($o['currency_code']) ?>)</th><th class="r">Amount</th></tr></thead>
  <tbody>
  <?php foreach ($lines as $i => $l): $amt = (float)$l['stems'] * (float)$l['selling_price']; $total += $amt; $stems += (int)$l['stems']; ?>
    <tr><td><?= $i + 1 ?></td><td><strong><?= e($l['variety']) ?></strong><?= $l['remarks'] ? '<br><span class="muted">' . e($l['remarks']) . '</span>' : '' ?></td><td><?= e($l['length']) ?> cm</td><td><?= e($l['order_by']) ?></td>
      <td class="r"><?= qty($l['stems']) ?></td><td class="r"><?= money($l['selling_price'], 4) ?></td><td class="r"><?= money($amt) ?></td></tr>
  <?php endforeach; ?>
  <?php if (!$lines): ?><tr><td colspan="7" class="c muted">No lines.</td></tr><?php endif; ?>
  </tbody>
  <tfoot><tr><td colspan="4">Total</td><td class="r"><?= qty($stems) ?></td><td></td><td class="r"><?= e($o['currency_code']) ?> <?= money($total) ?></td></tr></tfoot>
</table></div>
<?php if ($o['remarks']): ?><div class="note"><strong>Remarks:</strong> <?= e($o['remarks']) ?></div><?php endif; ?>
<div class="note">Quantities are subject to farm confirmation. The final invoice is based on stems confirmed and delivered.</div>
<?php doc_close($company, ['Prepared by: ' . ($o['created_by_name'] ?? ''), 'Approved by', 'Customer acceptance']); ?>

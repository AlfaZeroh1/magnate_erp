<?php
/** Confirmed order — what the farms have confirmed, combined per line. ?internal=1 adds the farm breakdown. */
use App\Services\OrderService as OS;

$o = OS::find($id);
if (OS::stageIndex($o['stage']) < 2) throw new App\Core\HttpException('The order has not been confirmed by farms yet.', 409);
$lines = OS::lines($id);
$internal = !empty($_GET['internal']);
$docTitle = 'Confirmed Order'; $docNo = $o['order_no'];
doc_open($company, 'Confirmed Order', $o['order_no'], fdate($o['confirmed_at']), ['Departure' => fdate($o['departure_date']), 'Ref' => $o['customer_ref']]);
$t = ['ordered' => 0, 'confirmed' => 0, 'boxes' => 0, 'amount' => 0];
?>
<div class="grid">
  <div class="box"><h4>Consignee</h4><div class="big"><?= e($o['customer_name']) ?></div><div class="muted"><?= e($o['country']) ?></div></div>
  <div class="box"><h4>Shipment</h4><div class="kv"><span>Agent</span><span><?= e($o['shipping_agent'] ?: '—') ?></span><span>Box type</span><span><?= e($o['box_type'] ?: '—') ?></span><span>Drop point</span><span><?= e($o['drop_point'] ?: '—') ?></span><span>Currency</span><span><?= e($o['currency_code']) ?></span></div></div>
</div>
<div class="table-wrap"><table class="t">
  <thead><tr><th>Variety</th><th>Length</th><th>Mark</th><th class="r">Ordered</th><th class="r">Confirmed</th><th class="r">Fill</th><th class="r">Boxes</th><th class="r">Price</th><th class="r">Amount</th></tr></thead>
  <tbody>
  <?php foreach ($lines as $l):
      $conf = array_sum(array_map(fn($a) => (int)$a['confirmed_stems'], $l['allocations']));
      $box = array_sum(array_map(fn($a) => (float)$a['confirmed_boxes'], $l['allocations']));
      $amt = $conf * (float)$l['selling_price'];
      $t['ordered'] += (int)$l['stems']; $t['confirmed'] += $conf; $t['boxes'] += $box; $t['amount'] += $amt; ?>
    <tr><td><strong><?= e($l['variety']) ?></strong></td><td><?= e($l['length']) ?> cm</td><td><?= e($l['order_by']) ?></td>
      <td class="r"><?= qty($l['stems']) ?></td><td class="r"><strong><?= qty($conf) ?></strong></td>
      <td class="r <?= $conf < (int)$l['stems'] ? 'neg' : 'pos' ?>"><?= $l['stems'] ? round($conf / $l['stems'] * 100) . '%' : '' ?></td>
      <td class="r"><?= qty($box) ?></td><td class="r"><?= money($l['selling_price'], 4) ?></td><td class="r"><?= money($amt) ?></td></tr>
    <?php if ($internal) foreach ($l['allocations'] as $a): ?>
      <tr><td colspan="3" class="muted" style="padding-left:22px">↳ <?= e($a['farm']) ?></td><td class="r muted"><?= qty($a['allocated_stems']) ?></td><td class="r muted"><?= qty($a['confirmed_stems']) ?></td><td></td><td class="r muted"><?= qty($a['confirmed_boxes']) ?> <?= $a['pack_rate'] ? '@' . qty($a['pack_rate']) : '' ?></td><td colspan="2"></td></tr>
    <?php endforeach; ?>
  <?php endforeach; ?>
  </tbody>
  <tfoot><tr><td colspan="3">Total</td><td class="r"><?= qty($t['ordered']) ?></td><td class="r"><?= qty($t['confirmed']) ?></td><td class="r"><?= $t['ordered'] ? round($t['confirmed'] / $t['ordered'] * 100) . '%' : '' ?></td><td class="r"><?= qty($t['boxes']) ?></td><td></td><td class="r"><?= e($o['currency_code']) ?> <?= money($t['amount']) ?></td></tr></tfoot>
</table></div>
<div class="note">Confirmed quantities from our sourcing farms. Final invoiced quantities follow the delivery check at our pack house.</div>
<?php doc_close($company, ['Prepared by', 'Approved by', 'Customer acknowledgement']); ?>

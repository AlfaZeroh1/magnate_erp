<?php
/** Farm order(s) — one page per sourcing farm, with blank columns for the farm to confirm. */
use App\Core\DB;
use App\Services\OrderService as OS;

$o = OS::find($id);
$farmFilter = (int)($_GET['farm'] ?? 0);
$farmOrders = array_values(array_filter(OS::farmOrders($id), fn($f) => !$farmFilter || (int)$f['farm_id'] === $farmFilter));
$docTitle = 'Farm Order'; $docNo = $o['order_no'];
if (!$farmOrders) throw new App\Core\HttpException('No farm orders yet. Send the order to farms first.', 409);

foreach ($farmOrders as $n => $fo):
    $rows = DB::all("SELECT v.name AS variety, l.name AS length, ob.name AS order_by, la.allocated_stems, la.buying_price, la.confirmed_stems, la.pack_rate, la.confirmed_boxes, ol.remarks
                       FROM line_allocations la JOIN order_lines ol ON ol.id = la.order_line_id JOIN varieties v ON v.id = ol.variety_id
                       JOIN lengths l ON l.id = ol.length_id LEFT JOIN order_by ob ON ob.id = ol.order_by_id
                      WHERE ol.order_id = :o AND la.farm_id = :f ORDER BY v.name, l.sort_order", ['o' => $id, 'f' => $fo['farm_id']]);
    doc_open($company, 'Farm Order', $fo['farm_order_no'], fdate($fo['sent_at'] ?: date('Y-m-d')), ['Departure' => fdate($o['departure_date']), 'Customer order' => $o['order_no']], '', $fo['status'] === 'cancelled');
    $tStems = 0; $tVal = 0;
?>
<div class="grid">
  <div class="box"><h4>To farm</h4><div class="big"><?= e($fo['farm']) ?></div><div class="muted"><?= e($fo['contact_person']) ?><?= $fo['phone'] ? ' · ' . e($fo['phone']) : '' ?><?= $fo['email'] ? '<br>' . e($fo['email']) : '' ?></div></div>
  <div class="box"><h4>Ship for</h4><div class="kv">
    <span>Consignee</span><span><?= e($o['customer_name']) ?></span>
    <span>Destination</span><span><?= e($o['country'] ?: '—') ?></span>
    <span>Box type</span><span><?= e($o['box_type'] ?: '—') ?></span>
    <span>Shipping agent</span><span><?= e($o['shipping_agent'] ?: '—') ?></span>
    <span>Deliver by</span><span><?= fdate($o['departure_date'], 'D d M Y') ?></span>
  </div></div>
</div>
<div class="table-wrap"><table class="t">
  <thead><tr><th>Variety</th><th>Length</th><th>Mark</th><th class="r">Stems ordered</th><th class="r">Price (<?= e($fo['currency_code']) ?>)</th><th class="r">Value</th><th class="c">Stems confirmed</th><th class="c">Pack rate</th><th class="c">Boxes</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): $v = (float)$r['allocated_stems'] * (float)$r['buying_price']; $tStems += (int)$r['allocated_stems']; $tVal += $v; ?>
    <tr><td><strong><?= e($r['variety']) ?></strong></td><td><?= e($r['length']) ?> cm</td><td><?= e($r['order_by']) ?></td>
      <td class="r"><?= qty($r['allocated_stems']) ?></td><td class="r"><?= money($r['buying_price'], 4) ?></td><td class="r"><?= money($v) ?></td>
      <td class="c"><?= $r['confirmed_stems'] !== null ? qty($r['confirmed_stems']) : '<span class="fill"></span>' ?></td>
      <td class="c"><?= $r['pack_rate'] !== null ? qty($r['pack_rate']) : '<span class="fill"></span>' ?></td>
      <td class="c"><?= $r['confirmed_boxes'] !== null ? qty($r['confirmed_boxes']) : '<span class="fill"></span>' ?></td></tr>
  <?php endforeach; ?>
  </tbody>
  <tfoot><tr><td colspan="3">Total</td><td class="r"><?= qty($tStems) ?></td><td></td><td class="r"><?= e($fo['currency_code']) ?> <?= money($tVal) ?></td><td colspan="3"></td></tr></tfoot>
</table></div>
<div class="note">Please confirm the stems you can supply, your pack rate (stems per box) and number of boxes, and return this order. Label every box with the mark shown and our order number <strong><?= e($fo['farm_order_no']) ?></strong>. Invoice us on what is delivered and accepted.</div>
<?php doc_close($company, ['Issued by ' . $company['name'], 'Confirmed by farm (name & signature)', 'Date']); ?>
<?php if ($n < count($farmOrders) - 1): ?><div class="pagebreak"></div><?php endif; ?>
<?php endforeach; ?>

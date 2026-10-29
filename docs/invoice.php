<?php
/** Invoice — order invoices, other sales and freight invoices. Includes other costs charged on it. */
use App\Core\DB;

$inv = DB::one("SELECT i.*, cur.code AS currency, cur.symbol, bc.name AS bill_to, bc.address AS bill_to_address, bco.name AS bill_to_country, bc.terms,
                       c.name AS consignee, cco.name AS consignee_country, c.truck,
                       o.order_no, o.customer_ref, o.departure_date, bt.name AS box_type, sa.name AS agent,
                       pl.consignment_no, pl.awb_no, pl.flight_no, pl.gross_weight_kg, pl.volume_weight_kg, dp.name AS drop_point,
                       fb.booking_no, fb.origin, fb.destination, fb.airline, fb.flight_no AS fb_flight, fb.awb_no AS fb_awb, fb.departure_date AS fb_departure
                  FROM invoices i
                  JOIN currencies cur ON cur.id = i.currency_id
                  JOIN customers bc ON bc.id = i.bill_to_customer_id LEFT JOIN countries bco ON bco.id = bc.country_id
                  JOIN customers c ON c.id = i.customer_id LEFT JOIN countries cco ON cco.id = c.country_id
                  LEFT JOIN orders o ON o.id = i.order_id LEFT JOIN box_types bt ON bt.id = o.box_type_id LEFT JOIN shipping_agents sa ON sa.id = o.shipping_agent_id
                  LEFT JOIN packing_lists pl ON pl.id = i.packing_list_id LEFT JOIN drop_points dp ON dp.id = pl.drop_point_id
                  LEFT JOIN freight_bookings fb ON fb.id = i.freight_booking_id
                 WHERE i.id = :id", ['id' => $id]);
if (!$inv) throw new App\Core\HttpException('Invoice not found.', 404);
$lines = DB::all('SELECT * FROM invoice_lines WHERE invoice_id = :id ORDER BY sort_order, id', ['id' => $id]);
$charges = DB::all('SELECT coc.*, c.code AS currency FROM customer_other_costs coc JOIN currencies c ON c.id = coc.currency_id WHERE coc.invoice_id = :id ORDER BY coc.cost_date', ['id' => $id]);

$kindTitle = ['order' => 'Invoice', 'other_sale' => 'Invoice', 'freight' => 'Freight Invoice'][$inv['kind']];
$docTitle = $kindTitle; $docNo = $inv['invoice_no'];
$void = $inv['status'] === 'void';
doc_open($company, $kindTitle, $inv['invoice_no'], fdate($inv['invoice_date']), ['Due' => fdate($inv['due_date']), 'Currency' => $inv['currency']], $void ? 'Void' : '', $void);

$sub = 0.0;
$isFlowers = $inv['kind'] === 'order';
?>
<div class="grid">
  <div class="box"><h4>Bill to</h4><div class="big"><?= e($inv['bill_to']) ?></div><div class="muted"><?= nl2br(e($inv['bill_to_address'])) ?><?= $inv['bill_to_country'] ? '<br>' . e($inv['bill_to_country']) : '' ?></div>
    <?php if ($inv['consignee'] !== $inv['bill_to']): ?><h4 style="margin-top:8px">Consignee</h4><div><?= e($inv['consignee']) ?><?= $inv['consignee_country'] ? ' · ' . e($inv['consignee_country']) : '' ?></div><?php endif; ?></div>
  <div class="box"><h4>Shipment</h4><div class="kv">
    <?php if ($inv['kind'] === 'order'): ?>
      <span>Order</span><span><?= e($inv['order_no']) ?><?= $inv['customer_ref'] ? ' / ' . e($inv['customer_ref']) : '' ?></span>
      <span>Consignment</span><span><?= e($inv['consignment_no']) ?></span>
      <span>Departure</span><span><?= fdate($inv['departure_date']) ?></span>
      <span>Agent / AWB</span><span><?= e($inv['agent'] ?: '—') ?><?= $inv['awb_no'] ? ' · ' . e($inv['awb_no']) : '' ?></span>
      <span>Drop-off</span><span><?= e($inv['drop_point'] ?: '—') ?></span>
      <span>Boxes / type</span><span><?= qty($inv['boxes']) ?> · <?= e($inv['box_type'] ?: '—') ?></span>
      <?php if ($inv['gross_weight_kg']): ?><span>Weight</span><span><?= qty($inv['gross_weight_kg']) ?> kg gross<?= $inv['volume_weight_kg'] ? ' · ' . qty($inv['volume_weight_kg']) . ' kg vol.' : '' ?></span><?php endif; ?>
      <span>Terms</span><span><?= e($inv['terms'] ?: '—') ?></span>
    <?php elseif ($inv['kind'] === 'freight'): ?>
      <span>Booking</span><span><?= e($inv['booking_no']) ?></span>
      <span>Route</span><span><?= e($inv['origin']) ?> → <?= e($inv['destination']) ?></span>
      <span>Flight</span><span><?= e(trim(($inv['airline'] ?? '') . ' ' . ($inv['fb_flight'] ?? ''))) ?: '—' ?></span>
      <span>Master AWB</span><span><?= e($inv['fb_awb'] ?: '—') ?></span>
      <span>Departure</span><span><?= fdate($inv['fb_departure']) ?></span>
    <?php else: ?>
      <span>Shipment</span><span><?= e($inv['shipment_details'] ?: '—') ?></span>
      <span>S number</span><span><?= e($inv['s_number'] ?: '—') ?></span>
      <span>Boxes</span><span><?= qty($inv['boxes']) ?></span>
    <?php endif; ?>
  </div></div>
</div>

<div class="table-wrap"><table class="t">
  <thead><tr>
    <?php if ($isFlowers): ?><th>Variety</th><th>Length</th><th>Mark</th><th class="r">Boxes</th><th class="r">Pack rate</th><th class="r">Stems</th>
    <?php else: ?><th>Description</th><th class="r">Boxes</th><th class="r">Quantity</th><?php endif; ?>
    <th class="r">Unit price</th><th class="r">Amount (<?= e($inv['currency']) ?>)</th></tr></thead>
  <tbody>
  <?php foreach ($lines as $l): $sub += (float)$l['amount']; ?>
    <tr>
      <?php if ($isFlowers): ?><td><strong><?= e($l['description']) ?></strong></td><td><?= e($l['length_label']) ?> cm</td><td><?= e($l['order_by_label']) ?></td>
        <td class="r"><?= qty($l['boxes']) ?></td><td class="r"><?= qty($l['pack_rate']) ?></td><td class="r"><?= qty($l['quantity']) ?></td>
      <?php else: ?><td><?= e($l['description']) ?></td><td class="r"><?= $l['boxes'] ? qty($l['boxes']) : '' ?></td><td class="r"><?= qty($l['quantity']) ?> <?= e($l['unit']) ?></td><?php endif; ?>
      <td class="r"><?= money($l['unit_price'], $isFlowers ? 4 : 2) ?></td><td class="r"><?= money($l['amount']) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
  <?php if ($isFlowers): ?>
  <tfoot><tr><td colspan="3">Total</td><td class="r"><?= qty(array_sum(array_column($lines, 'boxes'))) ?></td><td></td><td class="r"><?= qty(array_sum(array_column($lines, 'quantity'))) ?></td><td></td><td class="r"><?= money($sub) ?></td></tr></tfoot>
  <?php endif; ?>
</table></div>

<?php
$chargesTotal = 0.0;
$foreignCharges = false;
if ($charges): ?>
<table class="t">
  <thead><tr><th>Additional charges</th><th>Date</th><th class="r">Amount</th></tr></thead>
  <tbody><?php foreach ($charges as $c):
      $amt = (int)$c['currency_id'] === (int)$inv['currency_id'] ? (float)$c['selling_price'] : round((float)$c['selling_base'] / max(0.000001, (float)$inv['exchange_rate']), 2);
      if ((int)$c['currency_id'] !== (int)$inv['currency_id']) $foreignCharges = true;
      $chargesTotal += $amt; ?>
    <tr><td><?= e($c['details']) ?></td><td><?= fdate($c['cost_date']) ?></td><td class="r"><?= money($amt) ?><?= (int)$c['currency_id'] !== (int)$inv['currency_id'] ? ' <span class="muted">(' . e($c['currency']) . ' ' . money($c['selling_price']) . ')</span>' : '' ?></td></tr>
  <?php endforeach; ?></tbody>
</table>
<?php endif; ?>

<div class="totals">
  <div><span>Goods / services</span><strong><?= money($sub) ?></strong></div>
  <?php if ($charges): ?><div><span>Additional charges</span><strong><?= money($chargesTotal) ?></strong></div><?php endif; ?>
  <div class="grand"><span>Total due</span><span><?= e($inv['currency']) ?> <?= money($sub + $chargesTotal) ?></span></div>
</div>
<?php if ($foreignCharges): ?><div class="words">Charges in other currencies converted at <?= rtrim(rtrim(number_format((float)$inv['exchange_rate'], 6), '0'), '.') ?> <?= e(App\Core\Fx::baseCode()) ?>/<?= e($inv['currency']) ?>.</div><?php endif; ?>
<?php if ($inv['notes']): ?><div class="note"><?= nl2br(e($inv['notes'])) ?></div><?php endif; ?>
<?php if ($isFlowers && setting('origin_declaration')): ?><div class="note"><?= e(setting('origin_declaration')) ?><?= $company['pin'] ? ' (Exporter PIN ' . e($company['pin']) . ')' : '' ?></div><?php endif; ?>
<?php doc_close($company, ['Authorised signature', 'Company stamp'], $banks, (string)setting('invoice_terms')); ?>

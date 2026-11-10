<?php
/**
 * Invoice — order invoices (laid out as the company's Commercial Invoice), other sales and freight invoices.
 * Includes other costs charged on it and, for flower orders, the varieties that were ordered but not supplied.
 */
use App\Core\DB;

$inv = DB::one("SELECT i.*, cur.code AS currency, cur.symbol, bc.name AS bill_to, bc.address AS bill_to_address, bco.name AS bill_to_country, bc.terms,
                       c.name AS consignee, c.address AS consignee_address, cco.name AS consignee_country, c.truck,
                       o.order_no, o.customer_ref, o.departure_date, bt.name AS box_type, sa.name AS agent,
                       pl.consignment_no, pl.awb_no, pl.airline AS pl_airline, pl.flight_no, pl.gross_weight_kg, pl.volume_weight_kg, pl.net_weight_kg AS pl_net_kg, dp.name AS entry,
                       fb.booking_no, fb.origin, fb.destination, fb.airline, fb.flight_no AS fb_flight, fb.awb_no AS fb_awb, fb.departure_date AS fb_departure
                  FROM invoices i
                  JOIN currencies cur ON cur.id = i.currency_id
                  JOIN customers bc ON bc.id = i.bill_to_customer_id LEFT JOIN countries bco ON bco.id = bc.country_id
                  JOIN customers c ON c.id = i.customer_id LEFT JOIN countries cco ON cco.id = c.country_id
                  LEFT JOIN orders o ON o.id = i.order_id LEFT JOIN box_types bt ON bt.id = o.box_type_id LEFT JOIN shipping_agents sa ON sa.id = o.shipping_agent_id
                  LEFT JOIN packing_lists pl ON pl.id = i.packing_list_id LEFT JOIN drop_points dp ON dp.id = COALESCE(pl.drop_point_id, o.drop_point_id)
                  LEFT JOIN freight_bookings fb ON fb.id = i.freight_booking_id
                 WHERE i.id = :id", ['id' => $id]);
if (!$inv) throw new App\Core\HttpException('Invoice not found.', 404);
$lines = DB::all('SELECT il.*, COALESCE(il.colour, v.colour) AS colour, COALESCE(il.category_label, pc.name) AS category, v.image
                    FROM invoice_lines il LEFT JOIN varieties v ON v.id = il.variety_id LEFT JOIN product_categories pc ON pc.id = v.category_id
                   WHERE il.invoice_id = :id ORDER BY il.sort_order, il.id', ['id' => $id]);
$charges = DB::all('SELECT coc.*, c.code AS currency FROM customer_other_costs coc JOIN currencies c ON c.id = coc.currency_id WHERE coc.invoice_id = :id ORDER BY coc.cost_date', ['id' => $id]);

$isFlowers = $inv['kind'] === 'order';
$kindTitle = ['order' => 'Commercial Invoice', 'other_sale' => 'Invoice', 'freight' => 'Freight Invoice'][$inv['kind']];
$docTitle = $kindTitle; $docNo = $inv['invoice_no'];
$void = $inv['status'] === 'void';
$netKg = $inv['net_weight_kg'] ?? $inv['pl_net_kg'];
$country = $inv['consignee_country'] ?: $inv['bill_to_country'];
doc_open($company, $kindTitle, $inv['invoice_no'], fdate($inv['invoice_date']), $isFlowers
    ? ['Country' => $country, 'Entry' => $inv['entry'], 'Kilogrammes' => $netKg !== null ? qty($netKg) : '', 'Due' => fdate($inv['due_date'])]
    : ['Due' => fdate($inv['due_date']), 'Currency' => $inv['currency']], $void ? 'Void' : '', $void);

$sub = 0.0;
$multiMark = $isFlowers && count(array_unique(array_map(fn($l) => (string)$l['order_by_label'], $lines))) > 1;

// Ordered but not supplied: what each farm was asked for and did not deliver, plus anything never sourced.
$short = [];
if ($isFlowers && $inv['order_id']) {
    $short = DB::all("SELECT v.name AS variety, v.colour, v.image, f.name AS farm, l.name AS length, la.pack_rate,
                             la.allocated_stems - COALESCE(la.received_stems, 0) AS stems, la.variance_reason AS reason
                        FROM line_allocations la JOIN order_lines ol ON ol.id = la.order_line_id JOIN varieties v ON v.id = ol.variety_id
                        JOIN lengths l ON l.id = ol.length_id JOIN farms f ON f.id = la.farm_id
                       WHERE ol.order_id = :o AND la.allocated_stems > COALESCE(la.received_stems, 0)
                      UNION ALL
                      SELECT v.name, v.colour, v.image, NULL, l.name, NULL, ol.stems - COALESCE(SUM(la.allocated_stems), 0), 'Not sourced'
                        FROM order_lines ol JOIN varieties v ON v.id = ol.variety_id JOIN lengths l ON l.id = ol.length_id
                        LEFT JOIN line_allocations la ON la.order_line_id = ol.id
                       WHERE ol.order_id = :o2
                       GROUP BY ol.id, v.name, v.colour, v.image, l.name, ol.stems
                      HAVING ol.stems - COALESCE(SUM(la.allocated_stems), 0) > 0
                       ORDER BY variety, length", ['o' => $inv['order_id'], 'o2' => $inv['order_id']]);
}
?>
<div class="grid">
  <div class="box"><h4><?= $isFlowers ? 'Invoice / Ship to' : 'Bill to' ?></h4><div class="big"><?= e($inv['bill_to']) ?></div><div class="muted"><?= nl2br(e($inv['bill_to_address'])) ?><?= $inv['bill_to_country'] ? '<br>' . e($inv['bill_to_country']) : '' ?></div>
    <?php if ($inv['consignee'] !== $inv['bill_to']): ?><h4 style="margin-top:8px">Consignee</h4><div><?= e($inv['consignee']) ?><?= $inv['consignee_country'] ? ' · ' . e($inv['consignee_country']) : '' ?></div><?php endif; ?></div>
  <?php if ($isFlowers): ?>
  <div class="box"><h4>Shipment</h4><div class="kv">
      <span>Total boxes</span><span><?= qty(array_sum(array_column($lines, 'boxes'))) ?><?= $inv['box_type'] ? ' · ' . e($inv['box_type']) : '' ?></span>
      <span>Total stems</span><span><?= qty(array_sum(array_column($lines, 'quantity'))) ?></span>
      <span>Net weight</span><span><?= $netKg !== null ? qty($netKg) . ' kg' : '—' ?></span>
      <?php if ($inv['gross_weight_kg']): ?><span>Gross weight</span><span><?= qty($inv['gross_weight_kg']) ?> kg<?= $inv['volume_weight_kg'] ? ' · ' . qty($inv['volume_weight_kg']) . ' kg vol.' : '' ?></span><?php endif; ?>
      <span>Terms</span><span><?= e($inv['terms'] ?: '—') ?></span>
      <span>Entry</span><span><?= e($inv['entry'] ?: '—') ?></span>
      <span>Order</span><span><?= e($inv['order_no']) ?><?= $inv['customer_ref'] ? ' / ' . e($inv['customer_ref']) : '' ?> · <?= e($inv['consignment_no']) ?></span>
      <span>Departure</span><span><?= fdate($inv['departure_date']) ?></span>
      <span>Agent / AWB</span><span><?= e($inv['agent'] ?: '—') ?><?= $inv['awb_no'] ? ' · ' . e(trim(($inv['pl_airline'] ?? '') . ' ' . $inv['awb_no'])) : '' ?></span>
  </div></div>
  <?php else: ?>
  <div class="box"><h4>Shipment</h4><div class="kv">
    <?php if ($inv['kind'] === 'freight'): ?>
      <span>Booking</span><span><?= e($inv['booking_no']) ?></span>
      <span>Route</span><span><?= e($inv['origin']) ?> → <?= e($inv['destination']) ?></span>
      <span>Flight</span><span><?= e(trim(($inv['airline'] ?? '') . ' ' . ($inv['fb_flight'] ?? ''))) ?: '—' ?></span>
      <span>Master AWB</span><span><?= e($inv['fb_awb'] ?: '—') ?></span>
      <span>Departure</span><span><?= fdate($inv['fb_departure']) ?></span>
    <?php else: ?>
      <span>Shipment</span><span><?= e($inv['shipment_details'] ?: '—') ?></span>
      <span>S number</span><span><?= e($inv['s_number'] ?: '—') ?></span>
      <span>Boxes</span><span><?= qty($inv['boxes']) ?></span>
      <?php if ($netKg !== null): ?><span>Net weight</span><span><?= qty($netKg) ?> kg</span><?php endif; ?>
    <?php endif; ?>
  </div></div>
  <?php endif; ?>
</div>

<div class="table-wrap"><table class="t">
  <thead><tr>
    <?php if ($isFlowers): ?><th>Variety</th><th>Colour</th><th>Company</th><th class="r">Stems cm</th><th class="r">Nr. of boxes</th><th class="r">Stems per box</th><th class="r">Total stems</th>
    <?php else: ?><th>Description</th><th class="r">Boxes</th><th class="r">Quantity</th><?php endif; ?>
    <th class="r">Price</th><th class="r">Amount (<?= e($inv['currency']) ?>)</th></tr></thead>
  <tbody>
  <?php
  $group = null;
  $hasGroups = $isFlowers && count(array_unique(array_map(fn($l) => doc_group($l['category']), $lines))) > 1;
  foreach ($lines as $l): $sub += (float)$l['amount'];
    if ($hasGroups && doc_group($l['category']) !== $group): $group = doc_group($l['category']); ?>
      <tr class="group"><td colspan="9"><?= e($group) ?></td></tr>
    <?php endif; ?>
    <tr>
      <?php if ($isFlowers): ?>
        <td><?= variety_img($l['image']) ?><strong><?= e($l['description']) ?></strong><?= $multiMark && $l['order_by_label'] ? ' <span class="muted">· ' . e($l['order_by_label']) . '</span>' : '' ?></td>
        <td><?= e($l['colour']) ?></td><td><?= e($l['farm_label'] ?? '') ?></td><td class="r"><?= e($l['length_label']) ?></td>
        <td class="r"><?= qty($l['boxes']) ?></td><td class="r"><?= $l['pack_rate'] !== null ? qty(round((float)$l['pack_rate'])) : '' ?></td><td class="r"><?= qty($l['quantity']) ?></td>
      <?php else: ?><td><?= e($l['description']) ?></td><td class="r"><?= $l['boxes'] ? qty($l['boxes']) : '' ?></td><td class="r"><?= qty($l['quantity']) ?> <?= e($l['unit']) ?></td><?php endif; ?>
      <td class="r"><?= $isFlowers ? price_fmt($l['unit_price']) : money($l['unit_price']) ?></td><td class="r"><?= money($l['amount']) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
  <?php if ($isFlowers): ?>
  <tfoot><tr><td colspan="4">Total</td><td class="r"><?= qty(array_sum(array_column($lines, 'boxes'))) ?></td><td></td><td class="r"><?= qty(array_sum(array_column($lines, 'quantity'))) ?></td><td></td><td class="r"><?= money($sub) ?></td></tr></tfoot>
  <?php endif; ?>
</table></div>

<?php if ($short): ?>
<table class="t dense">
  <thead><tr><th>Varieties we did not supply</th><th>Colour</th><th>Company</th><th class="r">Stems cm</th><th class="r">Nr. of boxes</th><th class="r">Stems per box</th><th class="r">Total stems</th><th>Reason</th></tr></thead>
  <tbody><?php foreach ($short as $s): ?>
    <tr><td><?= variety_img($s['image'], 20) ?><?= e($s['variety']) ?></td><td><?= e($s['colour']) ?></td><td><?= e($s['farm'] ?: '—') ?></td><td class="r"><?= e($s['length']) ?></td>
      <td class="r"><?= (float)$s['pack_rate'] > 0 ? qty(round((int)$s['stems'] / (float)$s['pack_rate'], 1)) : '' ?></td><td class="r"><?= (float)$s['pack_rate'] > 0 ? qty($s['pack_rate']) : '' ?></td>
      <td class="r"><?= qty($s['stems']) ?></td><td class="muted"><?= e($s['reason']) ?></td></tr>
  <?php endforeach; ?></tbody>
  <tfoot><tr><td colspan="6">Total not supplied</td><td class="r"><?= qty(array_sum(array_column($short, 'stems'))) ?></td><td></td></tr></tfoot>
</table>
<?php endif; ?>

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
  <div class="grand"><span>Total due<?= $isFlowers && $inv['terms'] ? ' (' . e($inv['terms']) . ')' : '' ?></span><span><?= e($inv['currency']) ?> <?= money($sub + $chargesTotal) ?></span></div>
</div>
<?php if ($foreignCharges): ?><div class="words">Charges in other currencies converted at <?= rtrim(rtrim(number_format((float)$inv['exchange_rate'], 6), '0'), '.') ?> <?= e(App\Core\Fx::baseCode()) ?>/<?= e($inv['currency']) ?>.</div><?php endif; ?>
<?php if ($inv['notes']): ?><div class="note"><?= nl2br(e($inv['notes'])) ?></div><?php endif; ?>
<?php if ($isFlowers && setting('origin_declaration')): ?><div class="note"><?= e(setting('origin_declaration')) ?><?= $company['pin'] ? ' (Exporter PIN ' . e($company['pin']) . ')' : '' ?></div><?php endif; ?>
<?php doc_close($company, ['Authorised signature', 'Company stamp'], $banks, (string)setting('invoice_terms')); ?>

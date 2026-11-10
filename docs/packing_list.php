<?php
/**
 * Packing list — built from delivery-checked (received) quantities. id = order id.
 * Layout follows the company's own sheet: supplier, variety, colour, what was ordered, then boxes / pack rate / stems
 * per stem length, grand total, boxes supplied and the balance not supplied, grouped by category.
 */
use App\Core\DB;
use App\Services\OrderService as OS;

$o = OS::find($id);
$pl = DB::one('SELECT pl.*, dp.name AS entry FROM packing_lists pl LEFT JOIN drop_points dp ON dp.id = pl.drop_point_id WHERE pl.order_id = :o', ['o' => $id]);
if (!$pl) throw new App\Core\HttpException('No packing list for this order yet.', 409);

// every order line with its farm allocations (lines that were never allocated still show, as "not supplied")
$rows = DB::all("SELECT ol.id AS line_id, ol.stems AS ordered, ol.remarks, v.name AS variety, v.colour, v.image, pc.name AS category,
                        l.id AS length_id, l.name AS length, l.sort_order AS lsort, ob.name AS mark,
                        f.name AS farm, la.received_stems AS stems, la.received_boxes AS boxes, la.pack_rate, la.variance_reason
                   FROM order_lines ol
                   JOIN varieties v ON v.id = ol.variety_id
                   LEFT JOIN product_categories pc ON pc.id = v.category_id
                   JOIN lengths l ON l.id = ol.length_id
                   LEFT JOIN order_by ob ON ob.id = ol.order_by_id
                   LEFT JOIN line_allocations la ON la.order_line_id = ol.id
                   LEFT JOIN farms f ON f.id = la.farm_id
                  WHERE ol.order_id = :o
                  ORDER BY pc.name IS NULL, pc.name, v.name, l.sort_order, ol.id, (COALESCE(la.received_stems,0) > 0) DESC, f.name", ['o' => $id]);

// stem lengths that carry stock become column groups (like the 40 / 50 / 60 cm blocks on the sheet)
$lengths = [];
foreach ($rows as $r) if ((int)$r['stems'] > 0) $lengths[$r['length_id']] = ['name' => $r['length'], 'sort' => (int)$r['lsort']];
if (!$lengths) foreach ($rows as $r) $lengths[$r['length_id']] = ['name' => $r['length'], 'sort' => (int)$r['lsort']];
uasort($lengths, fn($a, $b) => $a['sort'] <=> $b['sort'] ?: strnatcmp($a['name'], $b['name']));

// group: category → order line → farm rows
$cats = [];
foreach ($rows as $r) $cats[doc_group($r['category'])][$r['line_id']][] = $r;
$multiMark = count(array_unique(array_map(fn($r) => (string)$r['mark'], $rows))) > 1;

$docTitle = 'Packing List'; $docNo = $pl['packing_list_no'];
$landscape = true;
doc_open($company, 'Packing List', $pl['packing_list_no'], fdate($o['departure_date']), ['Consignment' => $pl['consignment_no'], 'AWB' => $pl['awb_no'], 'Flight' => $pl['flight_no'], 'Entry' => $pl['entry']]);

// "The shipment will arrive on Monday evening (19 Oct 2026) on MS AWB 077 5756 5255"
$arrive = [];
if ($pl['arrival_date'] || $pl['arrival_note']) {
    $when = trim(($pl['arrival_note'] ?: ($pl['arrival_date'] ? date('l', strtotime($pl['arrival_date'])) : '')) . ($pl['arrival_date'] ? ' (' . fdate($pl['arrival_date'], 'jS F Y') . ')' : ''));
    $arrive[] = 'The shipment will arrive on ' . $when;
}
if ($pl['awb_no']) $arrive[] = ($arrive ? 'on ' : 'Shipped on ') . trim(($pl['airline'] ?? '') . ' AWB ' . $pl['awb_no']);
$nL = count($lengths);
$cols = 4 + 3 * $nL + 4;
$T = ['ordered' => 0, 'stems' => 0, 'boxes' => 0, 'balance' => 0, 'len' => array_fill_keys(array_keys($lengths), ['b' => 0, 's' => 0])];
?>
<?php if ($arrive): ?><div class="alert-note"><?= e(implode(' ', $arrive)) ?></div><?php endif; ?>
<div class="grid g3">
  <div class="box"><h4>Consignee</h4><div class="big"><?= e($o['customer_name']) ?></div><div class="muted"><?= nl2br(e($o['customer_address'] ?? '')) ?><?= $o['country'] ? '<br>' . e($o['country']) : '' ?><?= $o['truck'] ? '<br>Truck ' . e($o['truck']) : '' ?></div></div>
  <div class="box"><h4>Logistics</h4><div class="kv"><span>Order</span><span><?= e($o['order_no']) ?><?= $o['customer_ref'] ? ' / ' . e($o['customer_ref']) : '' ?></span><span>Agent</span><span><?= e($o['shipping_agent'] ?: '—') ?></span><span>Entry</span><span><?= e($pl['entry'] ?: '—') ?></span><span>Box type</span><span><?= e($o['box_type'] ?: '—') ?></span></div></div>
  <div class="box"><h4>Weights</h4><div class="kv">
    <span>Net</span><span><?= $pl['net_weight_kg'] !== null ? qty($pl['net_weight_kg']) . ' kg' : '<span class="fill"></span>' ?></span>
    <span>Gross</span><span><?= $pl['gross_weight_kg'] !== null ? qty($pl['gross_weight_kg']) . ' kg' : '<span class="fill"></span>' ?></span>
    <span>Volume</span><span><?= $pl['volume_weight_kg'] !== null ? qty($pl['volume_weight_kg']) . ' kg' : '<span class="fill"></span>' ?></span></div></div>
</div>
<div class="table-wrap"><table class="t dense">
  <thead>
    <tr><th rowspan="2">Supplier</th><th rowspan="2">Variety</th><th rowspan="2">Colour</th><th rowspan="2" class="r">Ordered stems</th>
      <?php foreach ($lengths as $L): ?><th colspan="3" class="c"><?= e($L['name']) ?> cm</th><?php endforeach; ?>
      <th rowspan="2" class="r">Grand total stems</th><th rowspan="2" class="r">Boxes supplied</th><th rowspan="2" class="r">Not supplied (balance)</th><th rowspan="2">Remarks</th></tr>
    <tr class="sub"><?php foreach ($lengths as $L): ?><th class="r">Boxes</th><th class="r">Pack rate</th><th class="r">Stems</th><?php endforeach; ?></tr>
  </thead>
  <tbody>
  <?php foreach ($cats as $cat => $lines): ?>
    <?php if (count($cats) > 1 || $cat !== doc_group(null)): ?><tr class="group"><td colspan="<?= $cols ?>"><?= e($cat) ?></td></tr><?php endif; ?>
    <?php foreach ($lines as $list):
        $first = $list[0];
        $supplied = array_sum(array_map(fn($r) => (int)$r['stems'], $list));
        $balance = max(0, (int)$first['ordered'] - $supplied);
        $rates = array_filter(array_map(fn($r) => (float)$r['boxes'] > 0 ? (int)$r['stems'] / (float)$r['boxes'] : (float)$r['pack_rate'], $list));
        $balBoxes = $balance > 0 && $rates ? $balance / (array_sum($rates) / count($rates)) : null;
        $T['ordered'] += (int)$first['ordered']; $T['balance'] += $balance;
        $reasons = array_unique(array_filter(array_map(fn($r) => $r['variance_reason'], $list)));
        $remarks = implode(' · ', array_filter([$multiMark && $first['mark'] ? 'Mark ' . $first['mark'] : '', $first['remarks'], implode(', ', $reasons)]));
        foreach ($list as $i => $r):
          $s = (int)$r['stems']; $b = (float)$r['boxes'];
          if ($s <= 0 && $i > 0) continue;   // farms that sent nothing add no row of their own
          $T['stems'] += $s; $T['boxes'] += $b;
          if (isset($T['len'][$r['length_id']])) { $T['len'][$r['length_id']]['b'] += $b; $T['len'][$r['length_id']]['s'] += $s; } ?>
      <tr>
        <td><?= e($s > 0 ? $r['farm'] : ($r['farm'] ?: '—')) ?></td>
        <td><?= variety_img($r['image'], 22) ?><strong><?= e($r['variety']) ?></strong></td>
        <td><?= e($r['colour']) ?></td>
        <td class="r"><?= $i === 0 ? qty($first['ordered']) : '' ?></td>
        <?php foreach ($lengths as $lid => $L): $here = (int)$lid === (int)$r['length_id'] && $s > 0; ?>
          <td class="r lenbox"><?= $here ? qty($b) : '' ?></td>
          <td class="r"><?= $here ? qty($b > 0 ? round($s / $b) : $r['pack_rate']) : '' ?></td>
          <td class="r"><?= $here ? qty($s) : '' ?></td>
        <?php endforeach; ?>
        <td class="r"><strong><?= qty($s) ?></strong></td>
        <td class="r"><?= qty($b) ?></td>
        <td class="r <?= $i === 0 && $balance > 0 ? 'neg' : '' ?>"><?= $i === 0 ? ($balance > 0 ? qty($balance) . ' st' . ($balBoxes ? ' (≈' . qty(round($balBoxes, 1)) . ' bx)' : '') : '0') : '' ?></td>
        <td class="muted"><?= $i === 0 ? e($remarks) : '' ?></td>
      </tr>
    <?php endforeach; endforeach; ?>
  <?php endforeach; ?>
  </tbody>
  <tfoot><tr><td colspan="3">Total</td><td class="r"><?= qty($T['ordered']) ?></td>
    <?php foreach ($T['len'] as $x): ?><td class="r"><?= qty($x['b']) ?></td><td></td><td class="r"><?= qty($x['s']) ?></td><?php endforeach; ?>
    <td class="r"><?= qty($T['stems']) ?></td><td class="r"><?= qty($T['boxes']) ?></td><td class="r"><?= qty($T['balance']) ?></td><td></td></tr></tfoot>
</table></div>
<?php if ($pl['notes']): ?><div class="note"><strong>Notes:</strong> <?= e($pl['notes']) ?></div><?php endif; ?>
<?php doc_close($company, ['Packed by', 'Checked by', 'Received by agent']); ?>

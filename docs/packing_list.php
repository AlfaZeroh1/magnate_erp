<?php
/** Packing list — built from delivery-checked (received) quantities. id = order id. */
use App\Core\DB;
use App\Services\OrderService as OS;

$o = OS::find($id);
$pl = DB::one('SELECT pl.*, dp.name AS drop_point FROM packing_lists pl LEFT JOIN drop_points dp ON dp.id = pl.drop_point_id WHERE pl.order_id = :o', ['o' => $id]);
if (!$pl) throw new App\Core\HttpException('No packing list for this order yet.', 409);
$rows = DB::all("SELECT v.name AS variety, pc.name AS category, l.name AS length, ob.name AS order_by, f.name AS farm,
                        la.received_stems AS stems, la.received_boxes AS boxes, la.pack_rate
                   FROM line_allocations la JOIN order_lines ol ON ol.id = la.order_line_id JOIN varieties v ON v.id = ol.variety_id
                   LEFT JOIN product_categories pc ON pc.id = v.category_id JOIN lengths l ON l.id = ol.length_id
                   LEFT JOIN order_by ob ON ob.id = ol.order_by_id JOIN farms f ON f.id = la.farm_id
                  WHERE ol.order_id = :o AND la.received_stems > 0 ORDER BY ob.name, v.name, l.sort_order, f.name", ['o' => $id]);
$docTitle = 'Packing List'; $docNo = $pl['packing_list_no'];
doc_open($company, 'Packing List', $pl['packing_list_no'], fdate($o['departure_date']), ['Consignment' => $pl['consignment_no'], 'AWB' => $pl['awb_no'], 'Flight' => $pl['flight_no']]);
$T = ['stems' => 0, 'boxes' => 0];
$byMark = [];
foreach ($rows as $r) $byMark[$r['order_by'] ?: '—'][] = $r;
?>
<div class="grid g3">
  <div class="box"><h4>Consignee</h4><div class="big"><?= e($o['customer_name']) ?></div><div class="muted"><?= e($o['country']) ?><?= $o['truck'] ? '<br>Truck ' . e($o['truck']) : '' ?></div></div>
  <div class="box"><h4>Logistics</h4><div class="kv"><span>Agent</span><span><?= e($o['shipping_agent'] ?: '—') ?></span><span>Drop-off</span><span><?= e($pl['drop_point'] ?: '—') ?></span><span>Box type</span><span><?= e($o['box_type'] ?: '—') ?></span></div></div>
  <div class="box"><h4>Weights</h4><div class="kv"><span>Gross</span><span><?= $pl['gross_weight_kg'] !== null ? qty($pl['gross_weight_kg']) . ' kg' : '<span class="fill"></span>' ?></span><span>Volume</span><span><?= $pl['volume_weight_kg'] !== null ? qty($pl['volume_weight_kg']) . ' kg' : '<span class="fill"></span>' ?></span><span>Order</span><span><?= e($o['order_no']) ?></span></div></div>
</div>
<div class="table-wrap"><table class="t">
  <thead><tr><th>Variety</th><th>Category</th><th>Length</th><th>Farm</th><th class="r">Pack rate</th><th class="r">Boxes</th><th class="r">Stems</th></tr></thead>
  <tbody>
  <?php foreach ($byMark as $mark => $list): $mb = 0; $ms = 0; ?>
    <?php if (count($byMark) > 1): ?><tr class="group"><td colspan="7">Mark: <?= e($mark) ?></td></tr><?php endif; ?>
    <?php foreach ($list as $r): $T['stems'] += (int)$r['stems']; $T['boxes'] += (float)$r['boxes']; $mb += (float)$r['boxes']; $ms += (int)$r['stems']; ?>
      <tr><td><strong><?= e($r['variety']) ?></strong></td><td class="muted"><?= e($r['category']) ?></td><td><?= e($r['length']) ?> cm</td><td><?= e($r['farm']) ?></td>
        <td class="r"><?= (float)$r['boxes'] > 0 ? qty(round($r['stems'] / $r['boxes'], 1)) : qty($r['pack_rate']) ?></td><td class="r"><?= qty($r['boxes']) ?></td><td class="r"><?= qty($r['stems']) ?></td></tr>
    <?php endforeach; ?>
    <?php if (count($byMark) > 1): ?><tr><td colspan="5" class="r muted">Subtotal <?= e($mark) ?></td><td class="r"><?= qty($mb) ?></td><td class="r"><?= qty($ms) ?></td></tr><?php endif; ?>
  <?php endforeach; ?>
  </tbody>
  <tfoot><tr><td colspan="5">Total</td><td class="r"><?= qty($T['boxes']) ?> boxes</td><td class="r"><?= qty($T['stems']) ?> stems</td></tr></tfoot>
</table></div>
<?php if ($pl['notes']): ?><div class="note"><strong>Notes:</strong> <?= e($pl['notes']) ?></div><?php endif; ?>
<?php doc_close($company, ['Packed by', 'Checked by', 'Received by agent']); ?>

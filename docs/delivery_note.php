<?php
/** Delivery check note — received vs confirmed per farm (the checksum before packing). */
use App\Services\OrderService as OS;

$o = OS::find($id);
if (OS::stageIndex($o['stage']) < 2) throw new App\Core\HttpException('The delivery check opens after farm confirmation.', 409);
$lines = OS::lines($id);
$byFarm = [];
foreach ($lines as $l) foreach ($l['allocations'] as $a) $byFarm[$a['farm']][] = $a + ['variety' => $l['variety'], 'length' => $l['length']];
ksort($byFarm);
$docTitle = 'Delivery Check Note'; $docNo = $o['order_no'];
$draft = OS::stageIndex($o['stage']) < 3;
doc_open($company, 'Delivery Check', $o['order_no'] . '-DC', fdate($o['received_at'] ?: date('Y-m-d')), ['Customer' => $o['customer_name'], 'Departure' => fdate($o['departure_date'])], $draft ? 'Draft' : '');
$T = ['c' => 0, 'r' => 0, 'j' => 0, 'cb' => 0, 'rb' => 0];
?>
<div class="table-wrap"><table class="t">
  <thead><tr><th>Variety</th><th>Length</th><th class="r">Confirmed</th><th class="r">Conf. boxes</th><th class="r">Received</th><th class="r">Rcvd boxes</th><th class="r">Rejected</th><th class="r">Variance</th><th>Reason</th></tr></thead>
  <tbody>
  <?php foreach ($byFarm as $farm => $rows): ?>
    <tr class="group"><td colspan="9"><?= e($farm) ?></td></tr>
    <?php foreach ($rows as $a):
        $c = (int)$a['confirmed_stems']; $r = $a['received_stems'] === null ? null : (int)$a['received_stems']; $v = $r === null ? null : $r - $c;
        $T['c'] += $c; $T['r'] += (int)$r; $T['j'] += (int)$a['rejected_stems']; $T['cb'] += (float)$a['confirmed_boxes']; $T['rb'] += (float)$a['received_boxes']; ?>
      <tr><td><?= e($a['variety']) ?></td><td><?= e($a['length']) ?></td><td class="r"><?= qty($c) ?></td><td class="r"><?= qty($a['confirmed_boxes']) ?></td>
        <td class="r"><strong><?= $r === null ? '<span class="fill"></span>' : qty($r) ?></strong></td><td class="r"><?= $a['received_boxes'] === null ? '<span class="fill"></span>' : qty($a['received_boxes']) ?></td>
        <td class="r"><?= qty($a['rejected_stems']) ?></td>
        <td class="r <?= $v === null ? '' : ($v < 0 ? 'neg' : ($v > 0 ? 'pos' : '')) ?>"><?= $v === null ? '' : ($v > 0 ? '+' : '') . qty($v) ?></td>
        <td class="muted"><?= e($a['variance_reason']) ?></td></tr>
    <?php endforeach; ?>
  <?php endforeach; ?>
  </tbody>
  <tfoot><tr><td colspan="2">Total</td><td class="r"><?= qty($T['c']) ?></td><td class="r"><?= qty($T['cb']) ?></td><td class="r"><?= qty($T['r']) ?></td><td class="r"><?= qty($T['rb']) ?></td><td class="r"><?= qty($T['j']) ?></td>
    <td class="r <?= $T['r'] - $T['c'] < 0 ? 'neg' : '' ?>"><?= ($T['r'] - $T['c'] > 0 ? '+' : '') . qty($T['r'] - $T['c']) ?></td><td><?= $T['c'] ? round($T['r'] / $T['c'] * 100, 1) . '% of confirmed' : '' ?></td></tr></tfoot>
</table></div>
<div class="note">Received = good stems accepted into the pack house. Packing list, customer invoice and farm bills are based on received quantities.</div>
<?php doc_close($company, ['Received by', 'Checked by (QC)', 'Farm / driver']); ?>

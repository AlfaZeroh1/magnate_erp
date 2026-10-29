<?php
/** Printable Order Journey report (re-uses the API logic). */
use App\Api\ReportsApi;
use App\Core\Fx;
use App\Core\Request;

$_GET['id'] = $id;
$api = new ReportsApi(new Request());
$d = $api->dispatch('journey', 'GET');
$o = $d['order']; $m = $d['financials']; $q = $m['qty'];
$B = Fx::baseCode();
$docTitle = 'Order Journey'; $docNo = $o['order_no'];
doc_open($company, 'Order Journey', $o['order_no'], date('d M Y'), ['Customer' => $o['customer_name'], 'Stage' => stage_meta()[$o['stage']][0]]);
$steps = ['Ordered' => $q['ordered'], 'Asked of farms' => $q['allocated'], 'Confirmed' => $q['confirmed'], 'Received' => $q['received']];
$max = max(1, ...array_values($steps));
?>
<div class="grid">
  <div class="box"><h4>What happened</h4>
    <?php foreach ($d['insights'] as $i): ?><div style="margin-bottom:5px">• <?= e($i['text']) ?></div><?php endforeach; ?>
    <?php if (!$d['insights']): ?><span class="muted">Not enough activity yet.</span><?php endif; ?></div>
  <div class="box"><h4>Quantity funnel</h4>
    <?php foreach ($steps as $label => $v): ?>
      <div style="display:flex;align-items:center;gap:8px;margin:4px 0"><span style="width:90px" class="muted"><?= e($label) ?></span>
        <div style="flex:1;background:#f1f5f9;border-radius:6px"><div style="width:<?= max(3, $v / $max * 100) ?>%;background:var(--accent);color:#fff;border-radius:6px;padding:2px 6px;font-size:10px;font-weight:700"><?= qty($v) ?></div></div></div>
    <?php endforeach; ?>
    <?php if ($q['rejected']): ?><div class="neg" style="font-size:10.5px;margin-top:4px"><?= qty($q['rejected']) ?> stems rejected at delivery check</div><?php endif; ?></div>
</div>
<?php if ($d['financial_access']): ?>
<div class="grid g3">
  <div class="box"><h4>Revenue</h4><div class="big"><?= $B ?> <?= money($m['total_revenue_base'], 0) ?></div><div class="muted"><?= e($m['revenue_source']) ?></div></div>
  <div class="box"><h4>Costs</h4><div class="kv"><span>Farm cost</span><span><?= money($m['farm_cost_base'], 0) ?></span><span>Other costs</span><span><?= money($m['other_cost_base'], 0) ?></span><span>Expenses</span><span><?= money($m['expenses_base'], 0) ?></span></div></div>
  <div class="box"><h4><?= $m['projected'] ? 'Projected profit' : 'Profit' ?></h4><div class="big <?= $m['profit_base'] >= 0 ? 'pos' : 'neg' ?>"><?= $B ?> <?= money($m['profit_base'], 0) ?></div><div class="muted">Margin <?= $m['margin_pct'] !== null ? $m['margin_pct'] . '%' : '—' ?><?= $d['bench'] && $d['bench']['avg_margin'] !== null ? ' · business avg ' . $d['bench']['avg_margin'] . '%' : '' ?></div></div>
</div>
<?php endif; ?>
<table class="t"><thead><tr><th>Farm</th><th class="r">Asked</th><th class="r">Confirmed</th><th class="r">Received</th><th class="r">Rejected</th></tr></thead><tbody>
<?php foreach ($d['farm_orders'] as $f): ?><tr><td><?= e($f['farm']) ?></td><td class="r"><?= qty($f['allocated']) ?></td><td class="r"><?= qty($f['confirmed']) ?></td><td class="r"><?= qty($f['received']) ?></td><td class="r"><?= qty($f['rejected']) ?></td></tr><?php endforeach; ?>
</tbody></table>
<table class="t"><thead><tr><th style="width:130px">When</th><th>Step</th><th>By</th></tr></thead><tbody>
<?php foreach ($d['timeline'] as $t): ?><tr><td><?= fdate($t['created_at'], 'd M Y H:i') ?></td><td><?= e($t['description']) ?></td><td class="muted"><?= e($t['user'] ?? 'System') ?></td></tr><?php endforeach; ?>
</tbody></table>
<?php doc_close($company); ?>

<?php
/** Account statement (customer / farm / shipping agent). */
use App\Services\StatementService;

$type = (string)($_GET['type'] ?? 'customer');
$from = !empty($_GET['from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from']) ? $_GET['from'] : null;
$to = !empty($_GET['to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to']) ? $_GET['to'] : null;
$s = StatementService::build($type, $id, $from, $to, ((int)($_GET['currency_id'] ?? 0)) ?: null);
$docTitle = $s['title']; $docNo = $s['party']['name'];
doc_open($company, 'Statement', '', fdate($s['to']), ['Period' => ($s['from'] ? fdate($s['from']) : 'Start') . ' – ' . fdate($s['to']), 'Currency' => $s['currency']]);
?>
<div class="grid">
  <div class="box"><h4><?= e(ucfirst($type)) ?></h4><div class="big"><?= e($s['party']['name']) ?></div><div class="muted"><?= e($s['party']['code'] ?? '') ?> <?= nl2br(e($s['party']['address'] ?? '')) ?><?= !empty($s['party']['email']) ? '<br>' . e($s['party']['email']) : '' ?></div></div>
  <div class="box"><h4>Summary</h4><div class="kv">
    <span>Opening balance</span><span><?= money($s['opening']) ?></span>
    <span><?= e($s['labels']['charge']) ?></span><span><?= money($s['charges']) ?></span>
    <span><?= e($s['labels']['payment']) ?></span><span><?= money($s['payments']) ?></span>
    <span><?= e($s['labels']['balance']) ?></span><span style="color:var(--accent);font-size:13px"><?= e($s['currency']) ?> <?= money($s['closing']) ?></span>
  </div></div>
</div>
<div class="table-wrap"><table class="t">
  <thead><tr><th>Date</th><th>Reference</th><th>Description</th><th class="r"><?= e($s['labels']['charge']) ?></th><th class="r"><?= e($s['labels']['payment']) ?></th><th class="r">Balance</th></tr></thead>
  <tbody>
    <?php if ($s['from']): ?><tr><td><?= fdate($s['from']) ?></td><td></td><td class="muted">Opening balance</td><td></td><td></td><td class="r"><?= money($s['opening']) ?></td></tr><?php endif; ?>
    <?php foreach ($s['lines'] as $l): ?>
      <tr><td><?= fdate($l['date']) ?></td><td><?= e($l['ref']) ?></td><td><?= e($l['description']) ?><?= $l['original'] ? '<br><span class="muted">orig. ' . e($l['original']) . '</span>' : '' ?></td>
        <td class="r"><?= $l['charge'] !== null ? money($l['charge']) : '' ?></td><td class="r"><?= $l['payment'] !== null ? money($l['payment']) : '' ?></td><td class="r"><strong><?= money($l['balance']) ?></strong></td></tr>
    <?php endforeach; ?>
    <?php if (!$s['lines']): ?><tr><td colspan="6" class="c muted">No transactions in this period.</td></tr><?php endif; ?>
  </tbody>
  <tfoot><tr><td colspan="3">Closing balance</td><td class="r"><?= money($s['charges']) ?></td><td class="r"><?= money($s['payments']) ?></td><td class="r"><?= e($s['currency']) ?> <?= money($s['closing']) ?></td></tr></tfoot>
</table></div>
<table class="t" style="margin-top:4px"><thead><tr><th class="r">Current (0–30)</th><th class="r">31–60</th><th class="r">61–90</th><th class="r">91–120</th><th class="r">120+</th></tr></thead>
  <tbody><tr><?php foreach (['current', 'd30', 'd60', 'd90', 'over90'] as $k): ?><td class="r <?= $k !== 'current' && $s['aging'][$k] > 0 ? 'neg' : '' ?>"><?= money($s['aging'][$k]) ?></td><?php endforeach; ?></tr></tbody></table>
<?php doc_close($company, [], $type === 'customer' ? $banks : [], $type === 'customer' ? 'Please contact us within 14 days if any item on this statement is incorrect.' : ''); ?>

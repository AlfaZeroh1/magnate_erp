<?php
/** Printable version of any catalogue report. */
use App\Api\ReportsApi;
use App\Core\Auth;
use App\Core\Request;
use App\Reports\Catalog;

$r = Catalog::get(preg_replace('/[^a-z_]/', '', (string)($_GET['key'] ?? '')));
if (!$r) throw new App\Core\HttpException('Unknown report.', 404);
if (!empty($r['financial'])) Auth::require('reports.financial');
$f = ReportsApi::filters(new Request());
$rows = ReportsApi::execute($r, $f);
$landscape = count($r['columns']) > 7;
$docTitle = $r['title'];
$fmt = function (string $t, $v): string {
    if ($v === null || $v === '') return '';
    return match ($t) {
        'money' => money($v), 'money4' => money($v, 4), 'int' => number_format((float)$v), 'qty' => qty($v),
        'pct' => number_format((float)$v, 1) . '%', 'date' => fdate((string)$v), 'rate' => rtrim(rtrim(number_format((float)$v, 6), '0'), '.'),
        'stage' => stage_meta()[$v][0] ?? (string)$v, default => e($v),
    };
};
$num = fn($c) => in_array($c[2] ?? 'text', ['money', 'money4', 'int', 'qty', 'pct', 'rate'], true);
doc_open($company, $r['title'], '', date('d M Y'), in_array('date', $r['filters'], true) ? ['Period' => fdate($f['from']) . ' – ' . fdate($f['to'])] : []);
?>
<div class="note" style="margin-top:0"><?= e($r['desc']) ?> · <?= count($rows) ?> row(s)</div>
<div class="table-wrap"><table class="t">
  <thead><tr><?php foreach ($r['columns'] as $c): ?><th class="<?= $num($c) ? 'r' : '' ?>"><?= e($c[1]) ?></th><?php endforeach; ?></tr></thead>
  <tbody><?php foreach ($rows as $row): ?><tr><?php foreach ($r['columns'] as $c): ?><td class="<?= $num($c) ? 'r' : '' ?>"><?= $fmt($c[2] ?? 'text', $row[$c[0]] ?? null) ?></td><?php endforeach; ?></tr><?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="<?= count($r['columns']) ?>" class="c muted">No data for this period.</td></tr><?php endif; ?></tbody>
  <?php if (!empty($r['totals']) && $rows): ?><tfoot><tr><?php foreach ($r['columns'] as $i => $c): ?><td class="<?= $num($c) ? 'r' : '' ?>"><?= in_array($c[0], $r['totals'], true) ? $fmt($c[2], array_sum(array_map(fn($x) => (float)($x[$c[0]] ?? 0), $rows))) : ($i === 0 ? 'Total' : '') ?></td><?php endforeach; ?></tr></tfoot><?php endif; ?>
</table></div>
<?php doc_close($company); ?>

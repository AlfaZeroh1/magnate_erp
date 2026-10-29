<?php
/** Payment receipt for a customer payment. */
use App\Core\DB;
use App\Services\StatementService;

$p = DB::one('SELECT p.*, c.name AS customer, c.address, cur.code AS currency, u.full_name AS received_by
                FROM customer_payments p JOIN customers c ON c.id = p.customer_id JOIN currencies cur ON cur.id = p.currency_id
                LEFT JOIN users u ON u.id = p.created_by WHERE p.id = :id', ['id' => $id]);
if (!$p) throw new App\Core\HttpException('Payment not found.', 404);
$bal = StatementService::build('customer', (int)$p['customer_id'], null, $p['payment_date']);
$no = 'RCT-' . str_pad((string)$p['id'], 5, '0', STR_PAD_LEFT);
$docTitle = 'Receipt'; $docNo = $no;
doc_open($company, 'Payment Receipt', $no, fdate($p['payment_date']), ['Method' => $p['method'], 'Ref' => $p['reference']]);
?>
<div class="grid">
  <div class="box"><h4>Received from</h4><div class="big"><?= e($p['customer']) ?></div><div class="muted"><?= nl2br(e($p['address'])) ?></div></div>
  <div class="box"><h4>Amount received</h4><div style="font:700 28px 'Fraunces',serif;color:var(--green)"><?= e($p['currency']) ?> <?= money($p['amount']) ?></div>
    <?php if ((float)$p['exchange_rate'] !== 1.0): ?><div class="muted">≈ <?= e(App\Core\Fx::baseCode()) ?> <?= money($p['base_amount']) ?> @ <?= rtrim(rtrim(number_format((float)$p['exchange_rate'], 6), '0'), '.') ?></div><?php endif; ?></div>
</div>
<?php if ($p['details']): ?><div class="box" style="margin-bottom:12px"><h4>Details</h4><?= e($p['details']) ?></div><?php endif; ?>
<div class="totals"><div><span>Account balance after this payment</span><strong><?= e($bal['currency']) ?> <?= money($bal['closing']) ?></strong></div></div>
<div class="note">Thank you for your payment.</div>
<?php doc_close($company, ['Received by: ' . ($p['received_by'] ?? ''), 'Signature & stamp']); ?>

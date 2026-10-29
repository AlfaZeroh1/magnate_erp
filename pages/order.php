<?php
$orderId = (int)($_GET['id'] ?? 0);
$row = App\Core\DB::one('SELECT order_no FROM orders WHERE id = :id', ['id' => $orderId]);
if (!$row) { echo '<div class="card"><div class="card-body empty"><i class="bi bi-search"></i>Order not found. <a href="index.php?p=orders">Back to orders</a></div></div>'; return; }
$pageTitle = 'Order ' . $row['order_no'];
$pageScripts = ['orders.js', 'order.js'];
?>
<div id="orderApp" data-id="<?= $orderId ?>">
  <div class="card mb-3"><div class="card-body"><?= str_repeat('<div class="skeleton mb-2" style="height:22px"></div>', 4) ?></div></div>
</div>

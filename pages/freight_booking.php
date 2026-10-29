<?php
$bid = (int)($_GET['id'] ?? 0);
$no = App\Core\DB::val('SELECT booking_no FROM freight_bookings WHERE id = :id', ['id' => $bid]);
if (!$no) { echo '<div class="card"><div class="card-body empty"><i class="bi bi-search"></i>Booking not found. <a href="index.php?p=freight">Back</a></div></div>'; return; }
$pageTitle = 'Booking ' . $no;
$pageScripts = ['freight.js'];
?>
<div id="bookingApp" data-id="<?= $bid ?>"><div class="card"><div class="card-body"><?= str_repeat('<div class="skeleton mb-2" style="height:24px"></div>', 5) ?></div></div></div>

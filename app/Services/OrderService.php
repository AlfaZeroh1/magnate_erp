<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\DB;
use App\Core\Fx;
use App\Core\HttpException;
use App\Core\Log;
use App\Core\Seq;

/**
 * Order pipeline rules in one place (used by the API, documents and reports).
 *
 *   draft ──send──▶ sourcing ──confirm──▶ confirmed ──delivery check──▶ received ──pack──▶ packing ──invoice──▶ invoiced ──▶ closed
 */
final class OrderService
{
    public const FLOW = ['draft', 'sourcing', 'confirmed', 'received', 'packing', 'invoiced', 'closed'];

    public static function find(int $id): array
    {
        $o = DB::one("SELECT o.*, c.name AS customer_name, c.code AS customer_code, c.terms, c.credit_days, c.truck,
                             co.name AS country, cur.code AS currency_code, cur.symbol AS currency_symbol,
                             bt.name AS box_type, sa.name AS shipping_agent, dp.name AS drop_point,
                             u.full_name AS created_by_name
                        FROM orders o
                        JOIN customers c ON c.id = o.customer_id
                        LEFT JOIN countries co ON co.id = c.country_id
                        JOIN currencies cur ON cur.id = o.currency_id
                        LEFT JOIN box_types bt ON bt.id = o.box_type_id
                        LEFT JOIN shipping_agents sa ON sa.id = o.shipping_agent_id
                        LEFT JOIN drop_points dp ON dp.id = o.drop_point_id
                        LEFT JOIN users u ON u.id = o.created_by
                       WHERE o.id = :id", ['id' => $id]);
        if (!$o) throw new HttpException('Order not found.', 404);
        return $o;
    }

    public static function stageIndex(string $stage): int
    {
        $i = array_search($stage, self::FLOW, true);
        return $i === false ? -1 : (int)$i;
    }

    public static function requireStage(array $order, array $stages, string $doing): void
    {
        if (!in_array($order['stage'], $stages, true)) {
            $label = stage_meta()[$order['stage']][0] ?? $order['stage'];
            throw new HttpException("Cannot {$doing}: order {$order['order_no']} is at stage \"{$label}\".", 409);
        }
    }

    /** Lines with nested allocations, one query each (no N+1). */
    public static function lines(int $orderId): array
    {
        $lines = DB::all("SELECT ol.*, v.name AS variety, pc.name AS category, l.name AS length, ob.name AS order_by
                            FROM order_lines ol
                            JOIN varieties v ON v.id = ol.variety_id
                            LEFT JOIN product_categories pc ON pc.id = v.category_id
                            JOIN lengths l ON l.id = ol.length_id
                            LEFT JOIN order_by ob ON ob.id = ol.order_by_id
                           WHERE ol.order_id = :o
                           ORDER BY v.name, l.sort_order, l.name, ol.id", ['o' => $orderId]);
        $allocs = DB::all("SELECT la.*, f.name AS farm, f.code AS farm_code, cur.code AS currency_code
                             FROM line_allocations la
                             JOIN order_lines ol ON ol.id = la.order_line_id
                             JOIN farms f ON f.id = la.farm_id
                             JOIN currencies cur ON cur.id = la.currency_id
                            WHERE ol.order_id = :o ORDER BY f.name, la.id", ['o' => $orderId]);
        $by = [];
        foreach ($allocs as $a) $by[$a['order_line_id']][] = $a;
        foreach ($lines as &$l) $l['allocations'] = $by[$l['id']] ?? [];
        return $lines;
    }

    public static function farmOrders(int $orderId): array
    {
        return DB::all("SELECT fo.*, f.name AS farm, f.code AS farm_code, f.email, f.phone, f.contact_person, cur.code AS currency_code,
                               SUM(la.allocated_stems) AS allocated, SUM(la.confirmed_stems) AS confirmed, SUM(la.received_stems) AS received,
                               SUM(la.confirmed_boxes) AS confirmed_boxes, SUM(la.received_boxes) AS received_boxes, SUM(la.rejected_stems) AS rejected,
                               SUM(la.allocated_stems * la.buying_price) AS allocated_value
                          FROM farm_orders fo
                          JOIN farms f ON f.id = fo.farm_id
                          JOIN currencies cur ON cur.id = fo.currency_id
                          LEFT JOIN order_lines ol ON ol.order_id = fo.order_id
                          LEFT JOIN line_allocations la ON la.order_line_id = ol.id AND la.farm_id = fo.farm_id
                         WHERE fo.order_id = :o
                         GROUP BY fo.id ORDER BY f.name", ['o' => $orderId]);
    }

    /** Keep one farm order per allocated farm (created on send; kept in sync while sourcing). */
    public static function syncFarmOrders(array $order): void
    {
        if (self::stageIndex($order['stage']) < 1) return;
        $farms = DB::all("SELECT DISTINCT la.farm_id, f.code, f.currency_id
                            FROM line_allocations la JOIN order_lines ol ON ol.id = la.order_line_id JOIN farms f ON f.id = la.farm_id
                           WHERE ol.order_id = :o", ['o' => $order['id']]);
        $keep = [];
        foreach ($farms as $f) {
            $keep[] = (int)$f['farm_id'];
            $exists = DB::val('SELECT id FROM farm_orders WHERE order_id = :o AND farm_id = :f', ['o' => $order['id'], 'f' => $f['farm_id']]);
            if (!$exists) {
                $cur = (int)(DB::val("SELECT la.currency_id FROM line_allocations la JOIN order_lines ol ON ol.id = la.order_line_id
                                        WHERE ol.order_id = :o AND la.farm_id = :f LIMIT 1", ['o' => $order['id'], 'f' => $f['farm_id']]) ?: $f['currency_id']);
                DB::insert('farm_orders', [
                    'order_id' => $order['id'], 'farm_id' => $f['farm_id'],
                    'farm_order_no' => $order['order_no'] . '-' . strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $f['code'])),
                    'status' => 'sent', 'currency_id' => $cur, 'sent_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }
        $params = ['o' => $order['id']];
        $notIn = '';
        if ($keep) {
            $notIn = ' AND farm_id NOT IN (' . implode(',', array_map('intval', $keep)) . ')';
        }
        DB::run("DELETE FROM farm_orders WHERE order_id = :o AND status IN ('pending','sent')" . $notIn, $params);
    }

    /**
     * Money summary in the base currency (KES).
     * Before invoicing, revenue/cost are projected from the furthest quantity checkpoint reached.
     */
    public static function financials(array $order): array
    {
        $oid = (int)$order['id'];
        $stageIdx = self::stageIndex($order['stage']);
        $basis = $stageIdx >= 3 ? 'received' : ($stageIdx >= 2 ? 'confirmed' : 'allocated');

        $q = DB::one("SELECT COALESCE(SUM(ol.stems),0) AS ordered FROM order_lines ol WHERE ol.order_id = :o", ['o' => $oid]);
        $a = DB::one("SELECT COALESCE(SUM(la.allocated_stems),0) AS allocated,
                             COALESCE(SUM(la.confirmed_stems),0) AS confirmed,
                             COALESCE(SUM(la.received_stems),0) AS received,
                             COALESCE(SUM(la.rejected_stems),0) AS rejected,
                             COALESCE(SUM(la.confirmed_boxes),0) AS confirmed_boxes,
                             COALESCE(SUM(la.received_boxes),0) AS received_boxes
                        FROM line_allocations la JOIN order_lines ol ON ol.id = la.order_line_id
                       WHERE ol.order_id = :o", ['o' => $oid]);

        $qtyCol = ['allocated' => 'la.allocated_stems', 'confirmed' => 'COALESCE(la.confirmed_stems,0)', 'received' => 'COALESCE(la.received_stems,0)'][$basis];

        // Revenue
        $inv = DB::one("SELECT id, invoice_no, total, base_total, exchange_rate FROM invoices WHERE order_id = :o AND kind = 'order' AND status = 'open' ORDER BY id DESC LIMIT 1", ['o' => $oid]);
        if ($inv) {
            $revenue = (float)$inv['total'];
            $revenueBase = (float)$inv['base_total'];
            $revenueSource = 'invoice';
        } else {
            $revenue = (float)DB::val("SELECT COALESCE(SUM(ol.selling_price * $qtyCol),0)
                                        FROM order_lines ol JOIN line_allocations la ON la.order_line_id = ol.id WHERE ol.order_id = :o", ['o' => $oid]);
            $revenueBase = $revenue * (float)$order['exchange_rate'];
            $revenueSource = 'projected from ' . $basis . ' stems';
        }

        // Farm cost: after the delivery check use the farm bill (actual invoice if recorded), else project.
        $farmCostBase = 0.0;
        if ($stageIdx >= 3) {
            foreach (DB::all("SELECT COALESCE(farm_invoice_amount, cost_amount) AS amt, exchange_rate FROM farm_orders WHERE order_id = :o AND status <> 'cancelled'", ['o' => $oid]) as $fo) {
                $farmCostBase += (float)$fo['amt'] * (float)$fo['exchange_rate'];
            }
        } else {
            foreach (DB::all("SELECT la.currency_id, SUM(la.buying_price * $qtyCol) AS amt
                                FROM line_allocations la JOIN order_lines ol ON ol.id = la.order_line_id
                               WHERE ol.order_id = :o GROUP BY la.currency_id", ['o' => $oid]) as $r) {
                $rate = Fx::rate((int)$r['currency_id'], $order['order_date']) ?? 0;
                $farmCostBase += (float)$r['amt'] * $rate;
            }
        }

        $other = DB::one("SELECT COALESCE(SUM(coc.selling_base),0) AS rev, COALESCE(SUM(coc.buying_base),0) AS cost
                            FROM customer_other_costs coc JOIN invoices i ON i.id = coc.invoice_id
                           WHERE i.order_id = :o", ['o' => $oid]);
        $expenses = (float)DB::val('SELECT COALESCE(SUM(base_amount),0) FROM expenses WHERE order_id = :o', ['o' => $oid]);

        $totalRev = $revenueBase + (float)$other['rev'];
        $totalCost = $farmCostBase + (float)$other['cost'] + $expenses;
        $profit = $totalRev - $totalCost;

        return [
            'basis' => $basis,
            'qty' => ['ordered' => (int)$q['ordered']] + array_map('floatval', $a),
            'revenue' => round($revenue, 2), 'revenue_base' => round($revenueBase, 2), 'revenue_source' => $revenueSource,
            'other_revenue_base' => round((float)$other['rev'], 2),
            'farm_cost_base' => round($farmCostBase, 2),
            'other_cost_base' => round((float)$other['cost'], 2),
            'expenses_base' => round($expenses, 2),
            'total_revenue_base' => round($totalRev, 2),
            'total_cost_base' => round($totalCost, 2),
            'profit_base' => round($profit, 2),
            'margin_pct' => $totalRev > 0 ? round($profit / $totalRev * 100, 2) : null,
            'invoice' => $inv,
            'projected' => !$inv,
        ];
    }

    public static function setStage(array $order, string $stage, string $logText, ?string $stampCol = null): void
    {
        $data = ['stage' => $stage, 'updated_by' => Auth::id()];
        if ($stampCol) $data[$stampCol] = date('Y-m-d H:i:s');
        DB::update('orders', $data, 'id = :id', ['id' => $order['id']]);
        Log::write('order', (int)$order['id'], 'stage:' . $stage, $logText, (int)$order['id']);
    }

    // ------------------------------------------------------------------
    //  Pipeline transitions
    // ------------------------------------------------------------------
    public static function sendToFarms(array $order): void
    {
        self::requireStage($order, ['draft'], 'send to farms');
        $n = (int)DB::val('SELECT COUNT(*) FROM order_lines WHERE order_id = :o', ['o' => $order['id']]);
        if ($n === 0) throw new HttpException('Add at least one line before sending to farms.', 422);
        $bad = DB::val("SELECT COUNT(*) FROM order_lines ol
                         WHERE ol.order_id = :o AND ol.stems <> (SELECT COALESCE(SUM(allocated_stems),0) FROM line_allocations WHERE order_line_id = ol.id)",
            ['o' => $order['id']]);
        if ($bad > 0) throw new HttpException("$bad line(s) are not fully allocated to farms.", 422);
        DB::tx(function () use ($order) {
            $order['stage'] = 'sourcing';
            self::setStage($order, 'sourcing', 'Farm orders generated and sent to farms', 'sourcing_at');
            self::syncFarmOrders($order);
        });
    }

    public static function completeConfirmation(array $order): void
    {
        self::requireStage($order, ['sourcing'], 'complete confirmation');
        $missing = (int)DB::val("SELECT COUNT(*) FROM line_allocations la JOIN order_lines ol ON ol.id = la.order_line_id
                                  WHERE ol.order_id = :o AND la.confirmed_stems IS NULL", ['o' => $order['id']]);
        if ($missing > 0) throw new HttpException("$missing farm line(s) still have no confirmed quantity. Enter 0 if a farm cannot supply.", 422);
        DB::tx(function () use ($order) {
            DB::run("UPDATE farm_orders SET status = 'confirmed', confirmed_at = NOW() WHERE order_id = :o AND status IN ('pending','sent')", ['o' => $order['id']]);
            $f = self::financials(array_merge($order, ['stage' => 'confirmed']));
            self::setStage($order, 'confirmed', sprintf('Farm confirmations completed: %s of %s stems confirmed',
                number_format($f['qty']['confirmed']), number_format($f['qty']['ordered'])), 'confirmed_at');
        });
    }

    /** The delivery check: what actually arrived vs what farms confirmed. Locks farm bills. */
    public static function completeDelivery(array $order): void
    {
        self::requireStage($order, ['confirmed'], 'complete the delivery check');
        $missing = (int)DB::val("SELECT COUNT(*) FROM line_allocations la JOIN order_lines ol ON ol.id = la.order_line_id
                                  WHERE ol.order_id = :o AND la.received_stems IS NULL AND COALESCE(la.confirmed_stems,0) > 0", ['o' => $order['id']]);
        if ($missing > 0) throw new HttpException("$missing farm line(s) have no received quantity yet.", 422);

        DB::tx(function () use ($order) {
            DB::run("UPDATE line_allocations la JOIN order_lines ol ON ol.id = la.order_line_id
                        SET la.received_stems = COALESCE(la.received_stems, 0), la.received_boxes = COALESCE(la.received_boxes, 0)
                      WHERE ol.order_id = :o", ['o' => $order['id']]);
            foreach (self::farmOrders((int)$order['id']) as $fo) {
                $cost = (float)DB::val("SELECT COALESCE(SUM(la.received_stems * la.buying_price),0)
                                          FROM line_allocations la JOIN order_lines ol ON ol.id = la.order_line_id
                                         WHERE ol.order_id = :o AND la.farm_id = :f", ['o' => $order['id'], 'f' => $fo['farm_id']]);
                DB::update('farm_orders', [
                    'cost_amount' => round($cost, 2), 'status' => 'received', 'received_at' => date('Y-m-d H:i:s'),
                    'exchange_rate' => Fx::rateOrFail((int)$fo['currency_id'], $order['departure_date']),
                ], 'id = :id', ['id' => $fo['id']]);
            }
            $q = self::financials(array_merge($order, ['stage' => 'received']))['qty'];
            $var = $q['received'] - $q['confirmed'];
            self::setStage($order, 'received', sprintf('Delivery check completed: %s stems received vs %s confirmed (%s%s), %s rejected',
                number_format($q['received']), number_format($q['confirmed']), $var >= 0 ? '+' : '', number_format($var), number_format($q['rejected'])), 'received_at');
        });
    }

    public static function pushToPacking(array $order): int
    {
        self::requireStage($order, ['received'], 'create the packing list');
        if (DB::val('SELECT id FROM packing_lists WHERE order_id = :o', ['o' => $order['id']])) {
            throw new HttpException('This order already has a packing list.', 409);
        }
        $received = (int)DB::val("SELECT COALESCE(SUM(la.received_stems),0) FROM line_allocations la JOIN order_lines ol ON ol.id = la.order_line_id WHERE ol.order_id = :o", ['o' => $order['id']]);
        if ($received <= 0) throw new HttpException('Nothing was received for this order — there is nothing to pack.', 422);

        return DB::tx(function () use ($order) {
            $no = Seq::next('packing', $order['departure_date']);
            $id = DB::insert('packing_lists', [
                'order_id' => $order['id'], 'packing_list_no' => $no, 'consignment_no' => 'GL' . substr($no, 2),
                'drop_point_id' => $order['drop_point_id'], 'created_by' => Auth::id(),
            ]);
            self::setStage($order, 'packing', "Packing list {$no} created", 'packed_at');
            return $id;
        });
    }

    public static function createInvoice(array $order, ?string $invoiceDate = null): int
    {
        self::requireStage($order, ['packing'], 'invoice');
        if (DB::val("SELECT id FROM invoices WHERE order_id = :o AND kind = 'order' AND status = 'open'", ['o' => $order['id']])) {
            throw new HttpException('This order already has an open invoice.', 409);
        }
        $pl = DB::one('SELECT * FROM packing_lists WHERE order_id = :o', ['o' => $order['id']]);
        if (!$pl) throw new HttpException('Create the packing list first.', 409);

        $date = $invoiceDate ?: date('Y-m-d');
        $rate = Fx::rateOrFail((int)$order['currency_id'], $date);

        // Invoice per line on RECEIVED stems at the customer selling price.
        $lines = DB::all("SELECT ol.id, v.name AS variety, ol.variety_id, l.name AS length, ob.name AS order_by, ol.selling_price,
                                 SUM(COALESCE(la.received_stems,0)) AS stems, SUM(COALESCE(la.received_boxes,0)) AS boxes
                            FROM order_lines ol
                            JOIN varieties v ON v.id = ol.variety_id
                            JOIN lengths l ON l.id = ol.length_id
                            LEFT JOIN order_by ob ON ob.id = ol.order_by_id
                            JOIN line_allocations la ON la.order_line_id = ol.id
                           WHERE ol.order_id = :o
                           GROUP BY ol.id, v.name, ol.variety_id, l.name, ob.name, ol.selling_price, l.sort_order
                          HAVING stems > 0
                           ORDER BY v.name, l.sort_order", ['o' => $order['id']]);
        if (!$lines) throw new HttpException('No received stems to invoice.', 422);
        $zero = array_filter($lines, fn($l) => (float)$l['selling_price'] <= 0);
        if ($zero) throw new HttpException('Set a selling price on: ' . implode(', ', array_map(fn($l) => $l['variety'] . ' ' . $l['length'], $zero)), 422);

        return DB::tx(function () use ($order, $pl, $lines, $date, $rate) {
            $total = 0.0;
            foreach ($lines as $l) $total += round((float)$l['stems'] * (float)$l['selling_price'], 2);
            $boxes = array_sum(array_map(fn($l) => (float)$l['boxes'], $lines));
            $no = Seq::next('invoice', $date);
            $invId = DB::insert('invoices', [
                'invoice_no' => $no, 'kind' => 'order', 'order_id' => $order['id'], 'packing_list_id' => $pl['id'],
                'customer_id' => $order['customer_id'], 'bill_to_customer_id' => $order['customer_id'],
                'invoice_date' => $date, 'due_date' => date('Y-m-d', strtotime($date . ' +' . (int)$order['credit_days'] . ' days')),
                'currency_id' => $order['currency_id'], 'exchange_rate' => $rate,
                'total' => round($total, 2), 'base_total' => round($total * $rate, 2), 'boxes' => $boxes,
                'shipment_details' => trim(($order['shipping_agent'] ?? '') . ' ' . ($pl['awb_no'] ? 'AWB ' . $pl['awb_no'] : '')),
                'created_by' => Auth::id(),
            ]);
            foreach ($lines as $i => $l) {
                DB::insert('invoice_lines', [
                    'invoice_id' => $invId, 'description' => $l['variety'], 'variety_id' => $l['variety_id'],
                    'length_label' => $l['length'], 'order_by_label' => $l['order_by'],
                    'quantity' => $l['stems'], 'unit' => 'stems', 'boxes' => $l['boxes'],
                    'pack_rate' => (float)$l['boxes'] > 0 ? round((float)$l['stems'] / (float)$l['boxes'], 2) : null,
                    'unit_price' => $l['selling_price'], 'amount' => round((float)$l['stems'] * (float)$l['selling_price'], 2), 'sort_order' => $i,
                ]);
            }
            DB::update('packing_lists', ['status' => 'invoiced'], 'id = :id', ['id' => $pl['id']]);
            self::setStage($order, 'invoiced', sprintf('Invoice %s issued: %s %s', $no, $order['currency_code'], number_format($total, 2)), 'invoiced_at');
            return $invId;
        });
    }

    /** Step back one stage, undoing what that stage created. */
    public static function reopen(array $order, string $reason): void
    {
        $back = ['sourcing' => 'draft', 'confirmed' => 'sourcing', 'received' => 'confirmed', 'packing' => 'received', 'closed' => 'invoiced'];
        if (!isset($back[$order['stage']])) {
            throw new HttpException($order['stage'] === 'invoiced'
                ? 'Void the invoice (Invoices page) to re-open an invoiced order.'
                : 'This order cannot be re-opened from its current stage.', 409);
        }
        $to = $back[$order['stage']];
        DB::tx(function () use ($order, $to, $reason) {
            switch ($order['stage']) {
                case 'sourcing':
                    DB::run("DELETE FROM farm_orders WHERE order_id = :o", ['o' => $order['id']]);
                    break;
                case 'confirmed':
                    DB::run("UPDATE farm_orders SET status = 'sent', confirmed_at = NULL WHERE order_id = :o", ['o' => $order['id']]);
                    break;
                case 'received':
                    DB::run("UPDATE farm_orders SET status = 'confirmed', received_at = NULL, cost_amount = 0 WHERE order_id = :o", ['o' => $order['id']]);
                    break;
                case 'packing':
                    DB::run("DELETE FROM packing_lists WHERE order_id = :o AND status = 'open'", ['o' => $order['id']]);
                    break;
            }
            self::setStage($order, $to, 'Re-opened to "' . (stage_meta()[$to][0]) . '"' . ($reason ? ': ' . $reason : ''));
        });
    }
}

<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Core\Fx;
use App\Core\HttpException;

/**
 * Account statements for customers (receivable), farms and shipping agents (payable).
 * Every transaction is converted to the statement currency through the base currency,
 * using the rate stored on the transaction and the statement currency's rate on that date.
 *
 * "charge" increases the balance (invoice to a customer / bill from a farm or agent),
 * "payment" reduces it. A positive closing balance = they owe us (customer) or we owe them (farm/agent).
 */
final class StatementService
{
    public const TYPES = [
        'customer' => ['customers', 'Customer statement', 'Invoiced', 'Received', 'Balance due to us'],
        'farm'     => ['farms', 'Farm statement', 'Billed', 'Paid', 'Balance we owe'],
        'agent'    => ['shipping_agents', 'Shipping agent statement', 'Billed', 'Paid', 'Balance we owe'],
    ];

    public static function party(string $type, int $id): array
    {
        if (!isset(self::TYPES[$type])) throw new HttpException('Unknown statement type.', 422);
        $table = self::TYPES[$type][0];
        $p = DB::one("SELECT t.*, c.code AS currency_code FROM `$table` t LEFT JOIN currencies c ON c.id = t.currency_id WHERE t.id = :id", ['id' => $id]);
        if (!$p) throw new HttpException('Account not found.', 404);
        return $p;
    }

    /** Raw transactions: [date, ref, description, kind charge|payment, amount, currency_id, currency, rate(base per unit)] */
    private static function transactions(string $type, int $id, ?string $to): array
    {
        $p = ['id' => $id, 'to' => $to ?? '9999-12-31'];
        if ($type === 'customer') {
            return DB::all("
                SELECT i.invoice_date AS d, i.invoice_no AS ref,
                       CONCAT(CASE i.kind WHEN 'order' THEN 'Invoice' WHEN 'freight' THEN 'Freight invoice' ELSE 'Sale' END,
                              COALESCE(CONCAT(' · ', o.order_no), ''), COALESCE(CONCAT(' · ', NULLIF(i.shipment_details, '')), '')) AS description,
                       'charge' AS kind, i.total AS amount, i.currency_id, c.code AS currency, i.exchange_rate AS rate, i.id AS doc_id, 'invoice' AS doc
                  FROM invoices i JOIN currencies c ON c.id = i.currency_id LEFT JOIN orders o ON o.id = i.order_id
                 WHERE i.bill_to_customer_id = :id AND i.status = 'open' AND i.invoice_date <= :to
                UNION ALL
                SELECT coc.cost_date, i.invoice_no, CONCAT('Other cost · ', coc.details), 'charge', coc.selling_price, coc.currency_id, c.code, coc.exchange_rate, NULL, NULL
                  FROM customer_other_costs coc JOIN invoices i ON i.id = coc.invoice_id JOIN currencies c ON c.id = coc.currency_id
                 WHERE coc.customer_id = :id AND i.status = 'open' AND coc.cost_date <= :to
                UNION ALL
                SELECT cp.payment_date, COALESCE(cp.reference, CONCAT('PMT-', cp.id)), CONCAT('Payment received', COALESCE(CONCAT(' · ', cp.details), '')),
                       'payment', cp.amount, cp.currency_id, c.code, cp.exchange_rate, cp.id, 'receipt'
                  FROM customer_payments cp JOIN currencies c ON c.id = cp.currency_id
                 WHERE cp.customer_id = :id AND cp.payment_date <= :to
                ORDER BY d, kind, ref", $p);
        }
        if ($type === 'farm') {
            return DB::all("
                SELECT o.departure_date AS d, COALESCE(fo.farm_invoice_no, fo.farm_order_no) AS ref,
                       CONCAT('Flowers for ', o.order_no, ' · ', cu.name) AS description, 'charge' AS kind,
                       COALESCE(fo.farm_invoice_amount, fo.cost_amount) AS amount, fo.currency_id, c.code AS currency, fo.exchange_rate AS rate, o.id AS doc_id, 'farm_order' AS doc
                  FROM farm_orders fo JOIN orders o ON o.id = fo.order_id JOIN customers cu ON cu.id = o.customer_id JOIN currencies c ON c.id = fo.currency_id
                 WHERE fo.farm_id = :id AND fo.status = 'received' AND o.departure_date <= :to
                UNION ALL
                SELECT fp.payment_date, COALESCE(fp.reference, CONCAT('PMT-', fp.id)), CONCAT('Payment', COALESCE(CONCAT(' · ', fp.details), '')),
                       'payment', fp.amount, fp.currency_id, c.code, fp.exchange_rate, NULL, NULL
                  FROM farm_payments fp JOIN currencies c ON c.id = fp.currency_id
                 WHERE fp.farm_id = :id AND fp.payment_date <= :to
                ORDER BY d, kind, ref", $p);
        }
        return DB::all("
            SELECT b.departure_date AS d, b.booking_no AS ref,
                   CONCAT('Freight space ', FORMAT(b.capacity_kg, 0), ' kg ', b.origin, '→', b.destination, COALESCE(CONCAT(' · AWB ', b.awb_no), '')) AS description,
                   'charge' AS kind, b.total_cost AS amount, b.currency_id, c.code AS currency, b.exchange_rate AS rate, NULL AS doc_id, NULL AS doc
              FROM freight_bookings b JOIN currencies c ON c.id = b.currency_id
             WHERE b.shipping_agent_id = :id AND b.status <> 'cancelled' AND b.departure_date <= :to
            UNION ALL
            SELECT e.expense_date, COALESCE(e.reference, CONCAT('EXP-', e.id)), CONCAT(e.description, COALESCE(CONCAT(' · ', o.order_no), '')),
                   'charge', e.amount, e.currency_id, c.code, e.exchange_rate, NULL, NULL
              FROM expenses e JOIN currencies c ON c.id = e.currency_id LEFT JOIN orders o ON o.id = e.order_id
             WHERE e.shipping_agent_id = :id AND e.expense_date <= :to
            UNION ALL
            SELECT ap.payment_date, COALESCE(ap.reference, CONCAT('PMT-', ap.id)), CONCAT('Payment', COALESCE(CONCAT(' · ', ap.details), '')),
                   'payment', ap.amount, ap.currency_id, c.code, ap.exchange_rate, NULL, NULL
              FROM agent_payments ap JOIN currencies c ON c.id = ap.currency_id
             WHERE ap.shipping_agent_id = :id AND ap.payment_date <= :to
            ORDER BY d, kind, ref", $p);
    }

    public static function build(string $type, int $id, ?string $from, ?string $to, ?int $currencyId = null): array
    {
        $party = self::party($type, $id);
        $currencyId = $currencyId ?: (int)($party['currency_id'] ?? 0) ?: Fx::baseId();
        $code = (string)DB::val('SELECT code FROM currencies WHERE id = :id', ['id' => $currencyId]);
        $to = $to ?: date('Y-m-d');

        $opening = 0.0;
        $lines = [];
        $balance = 0.0;
        $charges = 0.0; $payments = 0.0;
        $openCharges = [];   // for FIFO ageing
        foreach (self::transactions($type, $id, $to) as $t) {
            $amt = self::toCurrency((float)$t['amount'], (int)$t['currency_id'], (float)$t['rate'], $currencyId, $t['d']);
            $signed = $t['kind'] === 'charge' ? $amt : -$amt;
            if ($t['kind'] === 'charge') $openCharges[] = ['d' => $t['d'], 'left' => $amt];
            else self::applyPayment($openCharges, $amt);

            if ($from && $t['d'] < $from) { $opening += $signed; continue; }
            if (!$lines && $from) $balance = $opening;
            $balance += $signed;
            if ($t['kind'] === 'charge') $charges += $amt; else $payments += $amt;
            $lines[] = [
                'date' => $t['d'], 'ref' => $t['ref'], 'description' => $t['description'],
                'charge' => $t['kind'] === 'charge' ? round($amt, 2) : null, 'payment' => $t['kind'] === 'payment' ? round($amt, 2) : null,
                'original' => (int)$t['currency_id'] !== $currencyId ? $t['currency'] . ' ' . number_format((float)$t['amount'], 2) : null,
                'balance' => round($balance, 2), 'doc' => $t['doc'], 'doc_id' => $t['doc_id'],
            ];
        }
        if (!$lines) $balance = $opening;

        // Ageing of what is still open (FIFO: oldest charges are settled first)
        $aging = ['current' => 0.0, 'd30' => 0.0, 'd60' => 0.0, 'd90' => 0.0, 'over90' => 0.0];
        $asOf = strtotime($to);
        foreach ($openCharges as $c) {
            if ($c['left'] <= 0.005) continue;
            $days = (int)floor(($asOf - strtotime($c['d'])) / 86400);
            $bucket = $days <= 30 ? 'current' : ($days <= 60 ? 'd30' : ($days <= 90 ? 'd60' : ($days <= 120 ? 'd90' : 'over90')));
            $aging[$bucket] += $c['left'];
        }

        [$table, $title, $chargeLabel, $paymentLabel, $balanceLabel] = self::TYPES[$type];
        return [
            'type' => $type, 'title' => $title, 'party' => $party, 'currency_id' => $currencyId, 'currency' => $code,
            'from' => $from, 'to' => $to, 'opening' => round($opening, 2), 'closing' => round($balance, 2),
            'charges' => round($charges, 2), 'payments' => round($payments, 2), 'lines' => $lines,
            'aging' => array_map(fn($v) => round($v, 2), $aging),
            'labels' => ['charge' => $chargeLabel, 'payment' => $paymentLabel, 'balance' => $balanceLabel],
        ];
    }

    private static function toCurrency(float $amount, int $fromId, float $rate, int $toId, string $date): float
    {
        if ($fromId === $toId) return $amount;
        $base = $amount * ($rate > 0 ? $rate : (Fx::rate($fromId, $date) ?? 0));
        $target = Fx::rate($toId, $date) ?? Fx::rate($toId) ?? 0;
        return $target > 0 ? $base / $target : 0.0;
    }

    private static function applyPayment(array &$open, float $amt): void
    {
        foreach ($open as &$c) {
            if ($amt <= 0) break;
            $take = min($c['left'], $amt);
            $c['left'] -= $take;
            $amt -= $take;
        }
    }

    /** Balance summary for every party of a type (used by the statements index & reports). */
    public static function balances(string $type, ?string $asOf = null): array
    {
        $table = self::TYPES[$type][0];
        $out = [];
        foreach (DB::all("SELECT id, name FROM `$table` ORDER BY name") as $p) {
            $s = self::build($type, (int)$p['id'], null, $asOf);
            if (abs($s['closing']) < 0.005 && !$s['lines']) continue;
            $out[] = ['id' => (int)$p['id'], 'name' => $p['name'], 'currency' => $s['currency'], 'charges' => $s['charges'],
                      'payments' => $s['payments'], 'balance' => $s['closing'], 'aging' => $s['aging'],
                      'last' => $s['lines'] ? end($s['lines'])['date'] : null];
        }
        return $out;
    }
}

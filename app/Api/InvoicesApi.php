<?php
declare(strict_types=1);

namespace App\Api;

use App\Core\DB;
use App\Core\HttpException;
use App\Core\Log;

final class InvoicesApi extends BaseApi
{
    protected function rules(): array
    {
        return ['list' => 'invoices.view', 'void' => 'invoices.void'];
    }

    protected function readOnly(): array
    {
        return ['list'];
    }

    protected function doList(): array
    {
        $where = ['1=1'];
        $p = [];
        if ($k = $this->req->str('kind')) { $where[] = 'i.kind = :k'; $p['k'] = $k; }
        if ($s = $this->req->str('status')) { $where[] = 'i.status = :s'; $p['s'] = $s; }
        if ($f = $this->req->date('from')) { $where[] = 'i.invoice_date >= :f'; $p['f'] = $f; }
        if ($t = $this->req->date('to')) { $where[] = 'i.invoice_date <= :t'; $p['t'] = $t; }
        if ($c = $this->req->intOrNull('customer_id')) { $where[] = 'i.bill_to_customer_id = :c'; $p['c'] = $c; }
        return DB::all("SELECT i.id, i.invoice_no, i.kind, i.invoice_date, i.due_date, i.total, i.base_total, i.status, i.boxes,
                               cur.code AS currency, bc.name AS bill_to, c.name AS customer, o.id AS order_id, o.order_no, fb.booking_no,
                               (SELECT COALESCE(SUM(selling_price),0) FROM customer_other_costs WHERE invoice_id = i.id) AS other_costs
                          FROM invoices i
                          JOIN customers bc ON bc.id = i.bill_to_customer_id
                          JOIN customers c ON c.id = i.customer_id
                          JOIN currencies cur ON cur.id = i.currency_id
                          LEFT JOIN orders o ON o.id = i.order_id
                          LEFT JOIN freight_bookings fb ON fb.id = i.freight_booking_id
                         WHERE " . implode(' AND ', $where) . " ORDER BY i.invoice_date DESC, i.id DESC LIMIT 5000", $p);
    }

    /** Voiding an order invoice puts the order back to "packing" so it can be corrected and re-invoiced. */
    protected function doVoid(): array
    {
        $inv = DB::one('SELECT * FROM invoices WHERE id = :id', ['id' => $this->req->int('id')]);
        if (!$inv) throw new HttpException('Invoice not found.', 404);
        if ($inv['status'] === 'void') throw new HttpException('Already void.', 409);
        $reason = $this->req->str('reason');
        if ($reason === '') throw HttpException::validation(['reason' => 'Give a reason.'], 'A reason is required to void an invoice.');
        if (DB::val('SELECT COUNT(*) FROM customer_other_costs WHERE invoice_id = :id', ['id' => $inv['id']]) > 0) {
            throw new HttpException('Remove the other costs recorded on this invoice first.', 409);
        }
        DB::tx(function () use ($inv, $reason) {
            DB::update('invoices', ['status' => 'void', 'notes' => trim(($inv['notes'] ?? '') . "\nVOID: " . $reason)], 'id = :id', ['id' => $inv['id']]);
            DB::run('UPDATE freight_allocations SET invoice_id = NULL WHERE invoice_id = :id', ['id' => $inv['id']]);
            if ($inv['kind'] === 'order' && $inv['order_id']) {
                DB::update('packing_lists', ['status' => 'open'], 'order_id = :o', ['o' => $inv['order_id']]);
                DB::run("UPDATE orders SET stage = 'packing', invoiced_at = NULL, closed_at = NULL WHERE id = :o", ['o' => $inv['order_id']]);
            }
            Log::write('invoice', (int)$inv['id'], 'void', "Invoice {$inv['invoice_no']} voided: $reason", $inv['order_id'] ? (int)$inv['order_id'] : null);
        });
        return ['_message' => "Invoice {$inv['invoice_no']} voided."];
    }
}

<?php
declare(strict_types=1);

namespace App\Api;

use App\Core\Auth;
use App\Core\DB;
use App\Core\Fx;
use App\Core\HttpException;
use App\Core\Log;
use App\Core\Seq;

/**
 * Freight space brokerage.
 *  1. Book capacity on a flight with an agent/airline (kg × cost per kg + handling) -> payable to the agent.
 *  2. Sell parts of that space to customers who ship their own flowers, charged per CHARGEABLE kg
 *     (the higher of gross and volumetric weight) plus extras.
 *  3. Invoice the customer (shares the invoice number series).
 *  Margin = sales − (booking cost + linked expenses). Unsold kg is wasted cost.
 */
final class FreightApi extends BaseApi
{
    protected function rules(): array
    {
        return [
            'list' => 'freight.view', 'get' => 'freight.view',
            'save' => fn($r) => Auth::require($r->int('id') ? 'freight.edit' : 'freight.create'),
            'delete' => 'freight.delete', 'status' => 'freight.edit',
            'alloc_save' => 'freight.edit', 'alloc_delete' => 'freight.edit',
            'invoice' => 'freight.invoice',
        ];
    }

    protected function readOnly(): array
    {
        return ['list', 'get'];
    }

    private const SUMMARY = "SELECT b.*, a.name AS agent, c.code AS currency,
               COALESCE(s.sold_kg,0) AS sold_kg, COALESCE(s.sales_base,0) AS sales_base, COALESCE(s.customers,0) AS customers, COALESCE(s.uninvoiced,0) AS uninvoiced,
               COALESCE((SELECT SUM(e.base_amount) FROM expenses e WHERE e.freight_booking_id = b.id),0) AS expenses_base
          FROM freight_bookings b
          JOIN shipping_agents a ON a.id = b.shipping_agent_id
          JOIN currencies c ON c.id = b.currency_id
          LEFT JOIN (SELECT booking_id, SUM(chargeable_kg) AS sold_kg, SUM(base_amount) AS sales_base, COUNT(DISTINCT customer_id) AS customers,
                            SUM(invoice_id IS NULL) AS uninvoiced
                       FROM freight_allocations GROUP BY booking_id) s ON s.booking_id = b.id";

    private function withMetrics(array $b): array
    {
        $b['utilization'] = (float)$b['capacity_kg'] > 0 ? round((float)$b['sold_kg'] / (float)$b['capacity_kg'] * 100, 1) : 0;
        $b['margin_base'] = round((float)$b['sales_base'] - (float)$b['base_cost'] - (float)$b['expenses_base'], 2);
        $b['break_even_kg'] = (float)$b['sold_kg'] > 0 && (float)$b['sales_base'] > 0
            ? round(((float)$b['base_cost'] + (float)$b['expenses_base']) / ((float)$b['sales_base'] / (float)$b['sold_kg']), 1) : null;
        return $b;
    }

    protected function doList(): array
    {
        $w = ['1=1']; $p = [];
        if ($s = $this->req->str('status')) { $w[] = 'b.status = :s'; $p['s'] = $s; }
        if ($f = $this->req->date('from')) { $w[] = 'b.departure_date >= :f'; $p['f'] = $f; }
        if ($t = $this->req->date('to')) { $w[] = 'b.departure_date <= :t'; $p['t'] = $t; }
        $rows = DB::all(self::SUMMARY . ' WHERE ' . implode(' AND ', $w) . ' ORDER BY b.departure_date DESC, b.id DESC LIMIT 2000', $p);
        return array_map(fn($b) => $this->withMetrics($b), $rows);
    }

    protected function doGet(): array
    {
        $b = DB::one(self::SUMMARY . ' WHERE b.id = :id', ['id' => $this->req->int('id')]);
        if (!$b) throw new HttpException('Booking not found.', 404);
        $allocs = DB::all('SELECT fa.*, c.name AS customer, cur.code AS currency, i.invoice_no, i.status AS invoice_status
                             FROM freight_allocations fa
                             JOIN customers c ON c.id = fa.customer_id
                             JOIN currencies cur ON cur.id = fa.currency_id
                             LEFT JOIN invoices i ON i.id = fa.invoice_id
                            WHERE fa.booking_id = :b ORDER BY fa.id', ['b' => $b['id']]);
        $expenses = DB::all('SELECT e.expense_date, e.description, et.name AS type, e.amount, c.code AS currency, e.base_amount
                               FROM expenses e JOIN expense_types et ON et.id = e.expense_type_id JOIN currencies c ON c.id = e.currency_id
                              WHERE e.freight_booking_id = :b ORDER BY e.expense_date', ['b' => $b['id']]);
        return ['booking' => $this->withMetrics($b), 'allocations' => $allocs, 'expenses' => $expenses,
                'divisor' => (float)setting('volumetric_divisor', '6000')];
    }

    protected function doSave(): array
    {
        $id = $this->req->int('id');
        $d = [
            'shipping_agent_id' => $this->req->intOrNull('shipping_agent_id'),
            'airline' => $this->req->strOrNull('airline'), 'flight_no' => $this->req->strOrNull('flight_no'), 'awb_no' => $this->req->strOrNull('awb_no'),
            'origin' => $this->req->str('origin') ?: setting('default_origin', 'NBO'), 'destination' => $this->req->str('destination'),
            'departure_date' => $this->req->date('departure_date'),
            'capacity_kg' => $this->req->num('capacity_kg'), 'cost_per_kg' => $this->req->num('cost_per_kg'), 'other_costs' => $this->req->num('other_costs'),
            'currency_id' => $this->req->intOrNull('currency_id'), 'notes' => $this->req->strOrNull('notes'),
        ];
        $err = [];
        if (!$d['shipping_agent_id']) $err['shipping_agent_id'] = 'Choose the agent / airline you booked with.';
        if ($d['destination'] === '') $err['destination'] = 'Destination is required.';
        if (!$d['departure_date']) $err['departure_date'] = 'Departure date is required.';
        if ($d['capacity_kg'] <= 0) $err['capacity_kg'] = 'Capacity must be more than zero.';
        if (!$d['currency_id']) $err['currency_id'] = 'Currency is required.';
        if ($err) throw HttpException::validation($err);

        $rate = $this->req->numOrNull('exchange_rate') ?: Fx::rateOrFail($d['currency_id'], $d['departure_date']);
        if ($d['currency_id'] === Fx::baseId()) $rate = 1.0;
        $d['exchange_rate'] = $rate;
        $d['total_cost'] = round($d['capacity_kg'] * $d['cost_per_kg'] + $d['other_costs'], 2);
        $d['base_cost'] = round($d['total_cost'] * $rate, 2);

        if ($id) {
            $sold = (float)DB::val('SELECT COALESCE(SUM(chargeable_kg),0) FROM freight_allocations WHERE booking_id = :b', ['b' => $id]);
            if ($d['capacity_kg'] < $sold) throw HttpException::validation(['capacity_kg' => "Already sold $sold kg."]);
            DB::update('freight_bookings', $d, 'id = :id', ['id' => $id]);
            Log::write('freight_booking', $id, 'update', 'Freight booking updated');
            return ['id' => $id, '_message' => 'Booking updated.'];
        }
        $id = DB::tx(function () use ($d) {
            $d['booking_no'] = Seq::next('freight', $d['departure_date']);
            $d['created_by'] = Auth::id();
            $id = DB::insert('freight_bookings', $d);
            Log::write('freight_booking', $id, 'create', "Booked {$d['capacity_kg']} kg to {$d['destination']} ({$d['booking_no']})");
            return $id;
        });
        return ['id' => $id, '_message' => 'Space booked.'];
    }

    protected function doDelete(): array
    {
        $id = $this->req->int('id');
        if (DB::val('SELECT COUNT(*) FROM freight_allocations WHERE booking_id = :b AND invoice_id IS NOT NULL', ['b' => $id]) > 0) {
            throw new HttpException('Some space on this booking is invoiced. Cancel it instead.', 409);
        }
        DB::delete('freight_bookings', 'id = :id', ['id' => $id]);
        Log::write('freight_booking', $id, 'delete', 'Freight booking deleted');
        return ['_message' => 'Booking deleted.'];
    }

    protected function doStatus(): array
    {
        $s = $this->req->str('status');
        if (!in_array($s, ['open', 'closed', 'cancelled'], true)) throw new HttpException('Invalid status.', 422);
        DB::update('freight_bookings', ['status' => $s], 'id = :id', ['id' => $this->req->int('id')]);
        Log::write('freight_booking', $this->req->int('id'), 'status', "Booking marked $s");
        return ['_message' => "Booking marked $s."];
    }

    protected function doAllocSave(): array
    {
        $b = DB::one('SELECT * FROM freight_bookings WHERE id = :id', ['id' => $this->req->int('booking_id')]);
        if (!$b) throw new HttpException('Booking not found.', 404);
        if ($b['status'] !== 'open') throw new HttpException('This booking is ' . $b['status'] . '.', 409);
        $id = $this->req->int('id');
        if ($id && DB::val('SELECT invoice_id FROM freight_allocations WHERE id = :id', ['id' => $id])) {
            throw new HttpException('This allocation is already invoiced.', 409);
        }
        $d = [
            'booking_id' => (int)$b['id'], 'customer_id' => $this->req->intOrNull('customer_id'),
            'goods_description' => $this->req->strOrNull('goods_description'), 'hawb_no' => $this->req->strOrNull('hawb_no'),
            'boxes' => $this->req->int('boxes'), 'gross_kg' => $this->req->num('gross_kg'), 'volume_kg' => $this->req->num('volume_kg'),
            'rate_per_kg' => $this->req->num('rate_per_kg'), 'extra_charges' => $this->req->num('extra_charges'),
            'currency_id' => $this->req->intOrNull('currency_id'),
        ];
        $err = [];
        if (!$d['customer_id']) $err['customer_id'] = 'Choose the customer.';
        if ($d['gross_kg'] <= 0 && $d['volume_kg'] <= 0) $err['gross_kg'] = 'Enter gross or volumetric weight.';
        if ($d['rate_per_kg'] <= 0) $err['rate_per_kg'] = 'Enter the selling rate per kg.';
        if (!$d['currency_id']) $err['currency_id'] = 'Currency is required.';
        if ($err) throw HttpException::validation($err);

        $d['chargeable_kg'] = max($d['gross_kg'], $d['volume_kg']);
        $sold = (float)DB::val('SELECT COALESCE(SUM(chargeable_kg),0) FROM freight_allocations WHERE booking_id = :b AND id <> :id', ['b' => $b['id'], 'id' => $id]);
        if ($sold + $d['chargeable_kg'] > (float)$b['capacity_kg'] && !$this->req->bool('allow_over')) {
            throw HttpException::validation(['gross_kg' => sprintf('Only %s kg left on this booking.', number_format((float)$b['capacity_kg'] - $sold, 2))],
                'This would exceed the booked capacity. Reduce the weight or increase the booking.');
        }
        $rate = $d['currency_id'] === Fx::baseId() ? 1.0 : Fx::rateOrFail($d['currency_id'], $b['departure_date']);
        $d['exchange_rate'] = $rate;
        $d['amount'] = round($d['chargeable_kg'] * $d['rate_per_kg'] + $d['extra_charges'], 2);
        $d['base_amount'] = round($d['amount'] * $rate, 2);
        if ($id) {
            DB::update('freight_allocations', $d, 'id = :id', ['id' => $id]);
        } else {
            $d['created_by'] = Auth::id();
            $id = DB::insert('freight_allocations', $d);
        }
        $cust = DB::val('SELECT name FROM customers WHERE id = :id', ['id' => $d['customer_id']]);
        Log::write('freight_allocation', $id, 'save', "Sold {$d['chargeable_kg']} kg on {$b['booking_no']} to $cust");
        return ['id' => $id, '_message' => 'Space allocated.'];
    }

    protected function doAllocDelete(): array
    {
        $a = DB::one('SELECT * FROM freight_allocations WHERE id = :id', ['id' => $this->req->int('id')]);
        if (!$a) throw new HttpException('Not found.', 404);
        if ($a['invoice_id']) throw new HttpException('Void the invoice first.', 409);
        DB::delete('freight_allocations', 'id = :id', ['id' => $a['id']]);
        Log::write('freight_allocation', (int)$a['id'], 'delete', 'Removed a space allocation');
        return ['_message' => 'Allocation removed.'];
    }

    /** Invoice selected (or all un-invoiced) allocations of one customer on a booking. */
    protected function doInvoice(): array
    {
        $b = DB::one('SELECT b.*, a.name AS agent FROM freight_bookings b JOIN shipping_agents a ON a.id = b.shipping_agent_id WHERE b.id = :id', ['id' => $this->req->int('booking_id')]);
        if (!$b) throw new HttpException('Booking not found.', 404);
        $customer = $this->req->int('customer_id');
        $rows = DB::all('SELECT * FROM freight_allocations WHERE booking_id = :b AND customer_id = :c AND invoice_id IS NULL', ['b' => $b['id'], 'c' => $customer]);
        if (!$rows) throw new HttpException('Nothing left to invoice for this customer.', 422);
        $currencies = array_unique(array_column($rows, 'currency_id'));
        if (count($currencies) > 1) throw new HttpException('These allocations use different currencies — invoice them separately.', 422);
        $date = $this->req->date('invoice_date') ?? date('Y-m-d');
        $curId = (int)$currencies[0];
        $rate = $curId === Fx::baseId() ? 1.0 : Fx::rateOrFail($curId, $date);
        $credit = (int)DB::val('SELECT credit_days FROM customers WHERE id = :id', ['id' => $customer]);

        $invId = DB::tx(function () use ($b, $rows, $customer, $date, $curId, $rate, $credit) {
            $total = array_sum(array_map(fn($r) => (float)$r['amount'], $rows));
            $no = Seq::next('invoice', $date);
            $invId = DB::insert('invoices', [
                'invoice_no' => $no, 'kind' => 'freight', 'freight_booking_id' => $b['id'],
                'customer_id' => $customer, 'bill_to_customer_id' => $customer, 'invoice_date' => $date,
                'due_date' => date('Y-m-d', strtotime("$date +$credit days")), 'currency_id' => $curId, 'exchange_rate' => $rate,
                'total' => round($total, 2), 'base_total' => round($total * $rate, 2),
                'boxes' => array_sum(array_map(fn($r) => (int)$r['boxes'], $rows)),
                'shipment_details' => trim("{$b['origin']} → {$b['destination']} · " . ($b['airline'] ?? '') . ' ' . ($b['flight_no'] ?? '') . ' · ' . date('d M Y', strtotime($b['departure_date']))),
                's_number' => $b['awb_no'], 'created_by' => Auth::id(),
            ]);
            $i = 0;
            foreach ($rows as $r) {
                DB::insert('invoice_lines', [
                    'invoice_id' => $invId, 'description' => 'Air freight space' . ($r['goods_description'] ? ' — ' . $r['goods_description'] : '') . ($r['hawb_no'] ? ' (HAWB ' . $r['hawb_no'] . ')' : ''),
                    'quantity' => $r['chargeable_kg'], 'unit' => 'kg', 'boxes' => $r['boxes'], 'unit_price' => $r['rate_per_kg'],
                    'amount' => round((float)$r['chargeable_kg'] * (float)$r['rate_per_kg'], 2), 'sort_order' => $i++,
                ]);
                if ((float)$r['extra_charges'] > 0) {
                    DB::insert('invoice_lines', ['invoice_id' => $invId, 'description' => 'Handling & documentation', 'quantity' => 1, 'unit' => 'lot',
                        'unit_price' => $r['extra_charges'], 'amount' => $r['extra_charges'], 'sort_order' => $i++]);
                }
                DB::update('freight_allocations', ['invoice_id' => $invId], 'id = :id', ['id' => $r['id']]);
            }
            Log::write('invoice', $invId, 'create', "Freight invoice $no for booking {$b['booking_no']}");
            return $invId;
        });
        return ['invoice_id' => $invId, '_message' => 'Freight invoice created.'];
    }
}

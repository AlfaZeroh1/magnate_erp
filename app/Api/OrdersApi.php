<?php
declare(strict_types=1);

namespace App\Api;

use App\Core\Auth;
use App\Core\DB;
use App\Core\Fx;
use App\Core\HttpException;
use App\Core\Log;
use App\Core\Seq;
use App\Services\OrderService as OS;

final class OrdersApi extends BaseApi
{
    protected function rules(): array
    {
        return [
            'list' => 'orders.view', 'get' => 'orders.view', 'counts' => 'orders.view', 'timeline' => 'orders.view',
            'price_hint' => 'orders.view',
            'save' => fn($r) => \App\Core\Auth::require($r->int('id') ? 'orders.edit' : 'orders.create'),
            'delete' => 'orders.delete',
            'duplicate' => 'orders.create',
            'line_save' => 'orders.edit', 'line_delete' => 'orders.edit',
            'send' => 'orders.edit',
            'confirm_save' => 'orders.confirm', 'confirm_complete' => 'orders.confirm',
            'receive_save' => 'orders.receive', 'receive_complete' => 'orders.receive',
            'pack' => 'orders.pack',
            'invoice' => 'orders.invoice',
            'close' => 'orders.invoice',
            'reopen' => 'orders.reopen', 'cancel' => 'orders.reopen',
        ];
    }

    protected function readOnly(): array
    {
        return ['list', 'get', 'counts', 'timeline', 'price_hint'];
    }

    // ------------------------------------------------------------------
    protected function doList(): array
    {
        $where = ['1=1'];
        $p = [];
        $stage = $this->req->str('stage');
        if ($stage === 'active') $where[] = "o.stage NOT IN ('invoiced','closed','cancelled')";
        elseif ($stage !== '' && $stage !== 'all') { $where[] = 'o.stage = :stage'; $p['stage'] = $stage; }
        if ($this->req->bool('due')) $where[] = "o.stage IN ('sourcing','confirmed','received','packing') AND o.departure_date <= CURDATE() + INTERVAL 1 DAY";
        if ($from = $this->req->date('from')) { $where[] = 'o.departure_date >= :from'; $p['from'] = $from; }
        if ($to = $this->req->date('to')) { $where[] = 'o.departure_date <= :to'; $p['to'] = $to; }
        if ($c = $this->req->intOrNull('customer_id')) { $where[] = 'o.customer_id = :c'; $p['c'] = $c; }

        return DB::all("SELECT o.id, o.order_no, o.order_date, o.departure_date, o.stage, o.customer_ref,
                               c.name AS customer, sa.name AS shipping_agent, bt.name AS box_type, cur.code AS currency,
                               (SELECT COALESCE(SUM(stems),0) FROM order_lines WHERE order_id = o.id) AS stems,
                               (SELECT COUNT(*) FROM order_lines WHERE order_id = o.id) AS lines_count,
                               (SELECT COALESCE(SUM(la.received_stems),0) FROM line_allocations la JOIN order_lines ol ON ol.id = la.order_line_id WHERE ol.order_id = o.id) AS received,
                               (SELECT COALESCE(SUM(ol.stems * ol.selling_price),0) FROM order_lines ol WHERE ol.order_id = o.id) AS value,
                               i.invoice_no, i.total AS invoice_total, pl.packing_list_no
                          FROM orders o
                          JOIN customers c ON c.id = o.customer_id
                          JOIN currencies cur ON cur.id = o.currency_id
                          LEFT JOIN shipping_agents sa ON sa.id = o.shipping_agent_id
                          LEFT JOIN box_types bt ON bt.id = o.box_type_id
                          LEFT JOIN packing_lists pl ON pl.order_id = o.id
                          LEFT JOIN invoices i ON i.order_id = o.id AND i.kind = 'order' AND i.status = 'open'
                         WHERE " . implode(' AND ', $where) . "
                         ORDER BY o.departure_date DESC, o.id DESC LIMIT 5000", $p);
    }

    protected function doCounts(): array
    {
        $rows = DB::all('SELECT stage, COUNT(*) n FROM orders GROUP BY stage');
        $out = array_fill_keys(array_keys(stage_meta()), 0);
        foreach ($rows as $r) $out[$r['stage']] = (int)$r['n'];
        return $out;
    }

    protected function doGet(): array
    {
        $order = OS::find($this->req->int('id'));
        $stageIdx = OS::stageIndex($order['stage']);
        return [
            'order'       => $order,
            'lines'       => OS::lines((int)$order['id']),
            'farm_orders' => OS::farmOrders((int)$order['id']),
            'packing'     => DB::one('SELECT pl.*, dp.name AS drop_point FROM packing_lists pl LEFT JOIN drop_points dp ON dp.id = pl.drop_point_id WHERE pl.order_id = :o', ['o' => $order['id']]),
            'invoice'     => DB::one("SELECT * FROM invoices WHERE order_id = :o AND kind = 'order' ORDER BY status = 'open' DESC, id DESC LIMIT 1", ['o' => $order['id']]),
            'financials'  => Auth::can('reports.financial') || Auth::can('orders.invoice') ? OS::financials($order) : null,
            'expenses'    => DB::all('SELECT e.expense_date, e.description, et.name AS type, e.amount, c.code AS currency, e.base_amount
                                        FROM expenses e JOIN expense_types et ON et.id = e.expense_type_id JOIN currencies c ON c.id = e.currency_id
                                       WHERE e.order_id = :o ORDER BY e.expense_date', ['o' => $order['id']]),
            'other_costs' => DB::all('SELECT coc.cost_date, coc.details, coc.selling_price, coc.buying_price, c.code AS currency, coc.selling_base, coc.buying_base, i.invoice_no
                                        FROM customer_other_costs coc JOIN invoices i ON i.id = coc.invoice_id JOIN currencies c ON c.id = coc.currency_id
                                       WHERE i.order_id = :o ORDER BY coc.cost_date', ['o' => $order['id']]),
            'editable'    => $stageIdx <= 1 && Auth::can('orders.edit'),
            'can' => [
                'edit' => Auth::can('orders.edit'), 'confirm' => Auth::can('orders.confirm'), 'receive' => Auth::can('orders.receive'),
                'pack' => Auth::can('orders.pack'), 'invoice' => Auth::can('orders.invoice'), 'reopen' => Auth::can('orders.reopen'),
                'delete' => Auth::can('orders.delete'), 'expenses' => Auth::can('expenses.create'),
            ],
        ];
    }

    protected function doTimeline(): array
    {
        return DB::all("SELECT al.created_at, al.action, al.description, al.entity, u.full_name AS user
                          FROM activity_log al LEFT JOIN users u ON u.id = al.user_id
                         WHERE al.order_id = :o ORDER BY al.created_at, al.id", ['o' => $this->req->int('id')]);
    }

    /** Default selling price (converted to the order currency) and farms that price this variety/length. */
    protected function doPriceHint(): array
    {
        $variety = $this->req->int('variety_id');
        $length = $this->req->int('length_id');
        $orderCur = $this->req->int('currency_id') ?: Fx::baseId();
        $date = $this->req->date('date') ?? date('Y-m-d');

        $sp = DB::one('SELECT sp.price, sp.currency_id, c.code FROM selling_prices sp JOIN currencies c ON c.id = sp.currency_id
                        WHERE sp.variety_id = :v AND sp.length_id = :l', ['v' => $variety, 'l' => $length]);
        $farms = DB::all('SELECT fp.farm_id, f.name AS farm, fp.buying_price, fp.selling_price, fp.currency_id, c.code AS currency_code
                            FROM farm_prices fp JOIN farms f ON f.id = fp.farm_id JOIN currencies c ON c.id = fp.currency_id
                           WHERE fp.variety_id = :v AND fp.length_id = :l AND fp.is_active = 1 AND f.is_active = 1
                           ORDER BY fp.buying_price', ['v' => $variety, 'l' => $length]);

        $selling = null; $source = null;
        try {
            if ($sp) {
                $selling = Fx::convert((float)$sp['price'], (int)$sp['currency_id'], $orderCur, $date);
                $source = 'General selling price ' . $sp['code'] . ' ' . rtrim(rtrim(number_format((float)$sp['price'], 4), '0'), '.');
            } else {
                foreach ($farms as $f) {
                    if ($f['selling_price'] !== null) {
                        $selling = Fx::convert((float)$f['selling_price'], (int)$f['currency_id'], $orderCur, $date);
                        $source = 'Farm selling price (' . $f['farm'] . ')';
                        break;
                    }
                }
            }
        } catch (HttpException $e) {
            $source = $e->getMessage();
        }
        return ['selling_price' => $selling !== null ? round($selling, 4) : null, 'source' => $source, 'farms' => $farms];
    }

    // ------------------------------------------------------------------
    protected function doSave(): array
    {
        $id = $this->req->int('id');
        $errors = [];
        $data = [
            'customer_id'       => $this->req->intOrNull('customer_id'),
            'order_date'        => $this->req->date('order_date'),
            'departure_date'    => $this->req->date('departure_date'),
            'box_type_id'       => $this->req->intOrNull('box_type_id'),
            'shipping_agent_id' => $this->req->intOrNull('shipping_agent_id'),
            'drop_point_id'     => $this->req->intOrNull('drop_point_id'),
            'currency_id'       => $this->req->intOrNull('currency_id'),
            'customer_ref'      => $this->req->strOrNull('customer_ref'),
            'remarks'           => $this->req->strOrNull('remarks'),
        ];
        foreach (['customer_id' => 'Customer', 'order_date' => 'Order date', 'departure_date' => 'Departure date', 'currency_id' => 'Currency'] as $k => $label) {
            if (!$data[$k]) $errors[$k] = "$label is required.";
        }
        if ($data['order_date'] && $data['departure_date'] && $data['departure_date'] < $data['order_date']) {
            $errors['departure_date'] = 'Departure cannot be before the order date.';
        }
        if ($errors) throw HttpException::validation($errors);
        $data['exchange_rate'] = Fx::rateOrFail($data['currency_id'], $data['order_date']);

        if ($id) {
            $order = OS::find($id);
            if (OS::stageIndex($order['stage']) > 3) throw new HttpException('Packed or invoiced orders cannot be edited.', 409);
            if ((int)$order['currency_id'] !== $data['currency_id'] && OS::stageIndex($order['stage']) > 0) {
                throw new HttpException('Currency can only be changed while the order is a draft.', 409);
            }
            $data['updated_by'] = Auth::id();
            DB::update('orders', $data, 'id = :id', ['id' => $id]);
            Log::write('order', $id, 'update', 'Order details updated', $id);
            return ['id' => $id, '_message' => 'Order updated.'];
        }

        $id = DB::tx(function () use ($data) {
            $data['order_no'] = Seq::next('order');
            $data['created_by'] = Auth::id();
            $data['stage'] = 'draft';
            $id = DB::insert('orders', $data);
            Log::write('order', $id, 'create', "Order {$data['order_no']} captured", $id);
            return $id;
        });
        return ['id' => $id, '_message' => 'Order created. Now add the lines.'];
    }

    protected function doDelete(): array
    {
        $order = OS::find($this->req->int('id'));
        if (!in_array($order['stage'], ['draft', 'sourcing', 'cancelled'], true)) {
            throw new HttpException('Only draft, sourcing or cancelled orders can be deleted. Cancel it first.', 409);
        }
        if (DB::val('SELECT COUNT(*) FROM invoices WHERE order_id = :o', ['o' => $order['id']]) > 0) {
            throw new HttpException('This order has invoices and cannot be deleted.', 409);
        }
        DB::tx(function () use ($order) {
            DB::run('UPDATE expenses SET order_id = NULL WHERE order_id = :o', ['o' => $order['id']]);
            DB::delete('orders', 'id = :id', ['id' => $order['id']]);
            Log::write('order', (int)$order['id'], 'delete', "Order {$order['order_no']} deleted", null, ['customer' => $order['customer_name']]);
        });
        return ['_message' => "Order {$order['order_no']} deleted."];
    }

    /** Repeat an order: same customer & lines, new dates, back to draft. */
    protected function doDuplicate(): array
    {
        $src = OS::find($this->req->int('id'));
        $orderDate = $this->req->date('order_date') ?? date('Y-m-d');
        $departure = $this->req->date('departure_date') ?? date('Y-m-d', strtotime('+3 days'));
        $newId = DB::tx(function () use ($src, $orderDate, $departure) {
            $no = Seq::next('order');
            $id = DB::insert('orders', [
                'order_no' => $no, 'customer_id' => $src['customer_id'], 'order_date' => $orderDate, 'departure_date' => $departure,
                'box_type_id' => $src['box_type_id'], 'shipping_agent_id' => $src['shipping_agent_id'], 'drop_point_id' => $src['drop_point_id'],
                'currency_id' => $src['currency_id'], 'exchange_rate' => Fx::rateOrFail((int)$src['currency_id'], $orderDate),
                'stage' => 'draft', 'remarks' => $src['remarks'], 'created_by' => Auth::id(),
            ]);
            foreach (OS::lines((int)$src['id']) as $l) {
                $lineId = DB::insert('order_lines', [
                    'order_id' => $id, 'variety_id' => $l['variety_id'], 'length_id' => $l['length_id'], 'order_by_id' => $l['order_by_id'],
                    'stems' => $l['stems'], 'selling_price' => $l['selling_price'], 'remarks' => $l['remarks'], 'created_by' => Auth::id(),
                ]);
                foreach ($l['allocations'] as $a) {
                    $fp = DB::one('SELECT buying_price, currency_id FROM farm_prices WHERE farm_id = :f AND variety_id = :v AND length_id = :l',
                        ['f' => $a['farm_id'], 'v' => $l['variety_id'], 'l' => $l['length_id']]);
                    DB::insert('line_allocations', [
                        'order_line_id' => $lineId, 'farm_id' => $a['farm_id'], 'allocated_stems' => $a['allocated_stems'],
                        'buying_price' => $fp['buying_price'] ?? $a['buying_price'], 'currency_id' => $fp['currency_id'] ?? $a['currency_id'],
                    ]);
                }
            }
            Log::write('order', $id, 'create', "Order {$no} created as a repeat of {$src['order_no']}", $id);
            return $id;
        });
        return ['id' => $newId, '_message' => 'Repeat order created.'];
    }

    // ------------------------------------------------------------------
    //  Lines & allocations
    // ------------------------------------------------------------------
    protected function doLineSave(): array
    {
        $order = OS::find($this->req->int('order_id'));
        OS::requireStage($order, ['draft', 'sourcing'], 'change lines');
        $lineId = $this->req->int('line_id');
        $errors = [];
        $line = [
            'variety_id'    => $this->req->intOrNull('variety_id'),
            'length_id'     => $this->req->intOrNull('length_id'),
            'order_by_id'   => $this->req->intOrNull('order_by_id'),
            'stems'         => $this->req->int('stems'),
            'selling_price' => $this->req->num('selling_price'),
            'remarks'       => $this->req->strOrNull('remarks'),
        ];
        if (!$line['variety_id']) $errors['variety_id'] = 'Choose a variety.';
        if (!$line['length_id']) $errors['length_id'] = 'Choose a length.';
        if ($line['stems'] <= 0) $errors['stems'] = 'Stems must be more than zero.';
        if ($line['selling_price'] < 0) $errors['selling_price'] = 'Price cannot be negative.';

        $allocs = [];
        $seen = [];
        foreach ($this->req->arr('allocations') as $a) {
            $farm = (int)($a['farm_id'] ?? 0);
            $qty = (int)($a['allocated_stems'] ?? 0);
            if ($farm <= 0 || $qty <= 0) continue;
            if (isset($seen[$farm])) { $errors['allocations'] = 'Each farm can appear only once per line.'; break; }
            $seen[$farm] = true;
            $allocs[] = ['farm_id' => $farm, 'allocated_stems' => $qty, 'buying_price' => max(0, (float)($a['buying_price'] ?? 0)), 'currency_id' => (int)($a['currency_id'] ?? 0)];
        }
        $sum = array_sum(array_column($allocs, 'allocated_stems'));
        if (!$allocs) $errors['allocations'] = 'Allocate the stems to at least one farm.';
        elseif ($sum !== $line['stems']) $errors['allocations'] = "Allocated stems ($sum) must equal ordered stems ({$line['stems']}).";
        if ($errors) throw HttpException::validation($errors);

        $id = DB::tx(function () use ($order, $lineId, $line, $allocs) {
            if ($lineId) {
                $exists = DB::val('SELECT id FROM order_lines WHERE id = :id AND order_id = :o', ['id' => $lineId, 'o' => $order['id']]);
                if (!$exists) throw new HttpException('Line not found.', 404);
                DB::update('order_lines', $line, 'id = :id', ['id' => $lineId]);
                $old = [];
                foreach (DB::all('SELECT * FROM line_allocations WHERE order_line_id = :l', ['l' => $lineId]) as $o) $old[$o['farm_id']] = $o;
                DB::delete('line_allocations', 'order_line_id = :l', ['l' => $lineId]);
            } else {
                $line['order_id'] = $order['id'];
                $line['created_by'] = Auth::id();
                $lineId = DB::insert('order_lines', $line);
                $old = [];
            }
            foreach ($allocs as $a) {
                if ($a['currency_id'] <= 0) {
                    $a['currency_id'] = (int)(DB::val('SELECT currency_id FROM farm_prices WHERE farm_id = :f AND variety_id = :v AND length_id = :l',
                        ['f' => $a['farm_id'], 'v' => $line['variety_id'], 'l' => $line['length_id']])
                        ?: DB::val('SELECT currency_id FROM farms WHERE id = :f', ['f' => $a['farm_id']]));
                }
                $prev = $old[$a['farm_id']] ?? null;   // keep confirmations already captured for this farm
                DB::insert('line_allocations', $a + [
                    'order_line_id' => $lineId,
                    'confirmed_stems' => $prev['confirmed_stems'] ?? null, 'pack_rate' => $prev['pack_rate'] ?? null, 'confirmed_boxes' => $prev['confirmed_boxes'] ?? null,
                ]);
            }
            OS::syncFarmOrders($order);
            $v = DB::val('SELECT name FROM varieties WHERE id = :id', ['id' => $line['variety_id']]);
            $len = DB::val('SELECT name FROM lengths WHERE id = :id', ['id' => $line['length_id']]);
            Log::write('order_line', $lineId, 'save', sprintf('Line %s %s: %s stems across %d farm(s)', $v, $len, number_format($line['stems']), count($allocs)), (int)$order['id']);
            return $lineId;
        });
        return ['id' => $id, '_message' => 'Line saved.'];
    }

    protected function doLineDelete(): array
    {
        $line = DB::one('SELECT ol.*, v.name AS variety, l.name AS length FROM order_lines ol JOIN varieties v ON v.id = ol.variety_id JOIN lengths l ON l.id = ol.length_id WHERE ol.id = :id', ['id' => $this->req->int('id')]);
        if (!$line) throw new HttpException('Line not found.', 404);
        $order = OS::find((int)$line['order_id']);
        OS::requireStage($order, ['draft', 'sourcing'], 'delete lines');
        DB::tx(function () use ($line, $order) {
            DB::delete('order_lines', 'id = :id', ['id' => $line['id']]);
            OS::syncFarmOrders($order);
            Log::write('order_line', (int)$line['id'], 'delete', "Removed line {$line['variety']} {$line['length']} ({$line['stems']} stems)", (int)$order['id']);
        });
        return ['_message' => 'Line removed.'];
    }

    protected function doSend(): array
    {
        OS::sendToFarms(OS::find($this->req->int('id')));
        return ['_message' => 'Farm orders generated. Print or send them to each farm.'];
    }

    // ------------------------------------------------------------------
    //  Farm confirmation  (confirmed stems / pack rate / boxes)
    // ------------------------------------------------------------------
    private function allocationsOf(array $order): array
    {
        $ids = DB::col('SELECT la.id FROM line_allocations la JOIN order_lines ol ON ol.id = la.order_line_id WHERE ol.order_id = :o', ['o' => $order['id']]);
        return array_flip(array_map('intval', $ids));
    }

    protected function doConfirmSave(): array
    {
        $order = OS::find($this->req->int('id'));
        OS::requireStage($order, ['sourcing'], 'save confirmations');
        $valid = $this->allocationsOf($order);
        $rows = $this->req->arr('rows');
        $n = 0;
        DB::tx(function () use ($rows, $valid, &$n) {
            foreach ($rows as $r) {
                $id = (int)($r['id'] ?? 0);
                if (!isset($valid[$id])) continue;
                $confirmed = ($r['confirmed_stems'] ?? '') === '' ? null : max(0, (int)$r['confirmed_stems']);
                $alloc = (int)DB::val('SELECT allocated_stems FROM line_allocations WHERE id = :id', ['id' => $id]);
                if ($confirmed !== null && $confirmed > $alloc) {
                    throw HttpException::validation(["c_$id" => "Confirmed ($confirmed) is more than allocated ($alloc)."], 'A farm cannot confirm more than it was asked for. Increase the allocation on the line first.');
                }
                $packRate = ($r['pack_rate'] ?? '') === '' ? null : max(0, (float)$r['pack_rate']);
                $boxes = ($r['confirmed_boxes'] ?? '') === '' ? null : max(0, (float)$r['confirmed_boxes']);
                if ($boxes === null && $packRate && $confirmed !== null) $boxes = round($confirmed / $packRate, 2);
                DB::update('line_allocations', [
                    'confirmed_stems' => $confirmed, 'pack_rate' => $packRate, 'confirmed_boxes' => $boxes,
                    'confirmed_at' => $confirmed === null ? null : date('Y-m-d H:i:s'),
                ], 'id = :id', ['id' => $id]);
                $n++;
            }
        });
        Log::write('order', (int)$order['id'], 'confirm:save', "Farm confirmations updated ($n line(s))", (int)$order['id']);
        return ['_message' => 'Confirmations saved.'];
    }

    protected function doConfirmComplete(): array
    {
        OS::completeConfirmation(OS::find($this->req->int('id')));
        return ['_message' => 'Order confirmed. The confirmed order document is ready.'];
    }

    // ------------------------------------------------------------------
    //  Delivery check (received vs confirmed)
    // ------------------------------------------------------------------
    protected function doReceiveSave(): array
    {
        $order = OS::find($this->req->int('id'));
        OS::requireStage($order, ['confirmed'], 'record deliveries');
        $valid = $this->allocationsOf($order);
        $rows = $this->req->arr('rows');
        DB::tx(function () use ($rows, $valid) {
            foreach ($rows as $r) {
                $id = (int)($r['id'] ?? 0);
                if (!isset($valid[$id])) continue;
                $received = ($r['received_stems'] ?? '') === '' ? null : max(0, (int)$r['received_stems']);
                $rejected = max(0, (int)($r['rejected_stems'] ?? 0));
                $reason = trim((string)($r['variance_reason'] ?? ''));
                $conf = (int)DB::val('SELECT COALESCE(confirmed_stems,0) FROM line_allocations WHERE id = :id', ['id' => $id]);
                if ($received !== null && $received !== $conf && $reason === '') {
                    throw HttpException::validation(["r_$id" => 'Give a reason for the difference.'], 'Every farm line where received differs from confirmed needs a short reason (e.g. "short shipped", "quality rejects").');
                }
                DB::update('line_allocations', [
                    'received_stems' => $received,
                    'received_boxes' => ($r['received_boxes'] ?? '') === '' ? null : max(0, (float)$r['received_boxes']),
                    'rejected_stems' => $rejected,
                    'variance_reason' => $reason === '' ? null : mb_substr($reason, 0, 255),
                    'received_at' => $received === null ? null : date('Y-m-d H:i:s'),
                ], 'id = :id', ['id' => $id]);
            }
        });
        Log::write('order', (int)$order['id'], 'receive:save', 'Delivery quantities recorded', (int)$order['id']);
        return ['_message' => 'Delivery check saved.'];
    }

    protected function doReceiveComplete(): array
    {
        OS::completeDelivery(OS::find($this->req->int('id')));
        return ['_message' => 'Delivery check complete. Farm bills are now posted — you can create the packing list.'];
    }

    // ------------------------------------------------------------------
    protected function doPack(): array
    {
        $id = OS::pushToPacking(OS::find($this->req->int('id')));
        return ['packing_list_id' => $id, '_message' => 'Packing list created.'];
    }

    protected function doInvoice(): array
    {
        $id = OS::createInvoice(OS::find($this->req->int('id')), $this->req->date('invoice_date'));
        return ['invoice_id' => $id, '_message' => 'Invoice created.'];
    }

    protected function doClose(): array
    {
        $order = OS::find($this->req->int('id'));
        OS::requireStage($order, ['invoiced'], 'close');
        OS::setStage($order, 'closed', 'Order closed', 'closed_at');
        return ['_message' => 'Order closed.'];
    }

    protected function doReopen(): array
    {
        OS::reopen(OS::find($this->req->int('id')), $this->req->str('reason'));
        return ['_message' => 'Order re-opened one step.'];
    }

    protected function doCancel(): array
    {
        $order = OS::find($this->req->int('id'));
        if (OS::stageIndex($order['stage']) >= 5) throw new HttpException('Invoiced orders cannot be cancelled — void the invoice first.', 409);
        if ($order['stage'] === 'cancelled') throw new HttpException('Already cancelled.', 409);
        DB::tx(function () use ($order) {
            DB::run("UPDATE farm_orders SET status = 'cancelled' WHERE order_id = :o", ['o' => $order['id']]);
            DB::run("DELETE FROM packing_lists WHERE order_id = :o AND status = 'open'", ['o' => $order['id']]);
            OS::setStage($order, 'cancelled', 'Order cancelled' . ($this->req->str('reason') ? ': ' . $this->req->str('reason') : ''));
        });
        return ['_message' => 'Order cancelled.'];
    }
}

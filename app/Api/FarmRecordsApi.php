<?php
declare(strict_types=1);

namespace App\Api;

use App\Core\DB;
use App\Core\HttpException;
use App\Core\Log;

/**
 * Farm records = the farm side of each order after the delivery check:
 * farm invoice number & amount, phyto weight, agent AWB, comments.
 * These rows are the farm bills that feed farm statements.
 */
final class FarmRecordsApi extends BaseApi
{
    private const EDITABLE = ['farm_invoice_no' => 'strOrNull', 'farm_invoice_amount' => 'numOrNull', 'phyto_kg' => 'numOrNull', 'agent_awb' => 'strOrNull', 'comments' => 'strOrNull'];

    protected function rules(): array
    {
        return ['list' => 'farm_records.view', 'update' => 'farm_records.edit'];
    }

    protected function readOnly(): array
    {
        return ['list'];
    }

    protected function doList(): array
    {
        $where = ["fo.status <> 'cancelled'"];
        $p = [];
        if ($f = $this->req->intOrNull('farm_id')) { $where[] = 'fo.farm_id = :f'; $p['f'] = $f; }
        if ($o = $this->req->intOrNull('order_id')) { $where[] = 'fo.order_id = :o'; $p['o'] = $o; }
        if ($from = $this->req->date('from')) { $where[] = 'o.departure_date >= :from'; $p['from'] = $from; }
        if ($to = $this->req->date('to')) { $where[] = 'o.departure_date <= :to'; $p['to'] = $to; }
        if ($this->req->bool('missing')) $where[] = "fo.status = 'received' AND (fo.farm_invoice_no IS NULL OR fo.farm_invoice_no = '')";
        return DB::all("SELECT fo.*, f.name AS farm, o.order_no, o.departure_date, o.stage, c.name AS customer, cur.code AS currency,
                               sa.name AS agent, i.invoice_no AS customer_invoice_no,
                               (SELECT COALESCE(SUM(la.received_stems),0) FROM line_allocations la JOIN order_lines ol ON ol.id = la.order_line_id
                                 WHERE ol.order_id = fo.order_id AND la.farm_id = fo.farm_id) AS stems,
                               (SELECT COALESCE(SUM(la.received_boxes),0) FROM line_allocations la JOIN order_lines ol ON ol.id = la.order_line_id
                                 WHERE ol.order_id = fo.order_id AND la.farm_id = fo.farm_id) AS boxes
                          FROM farm_orders fo
                          JOIN farms f ON f.id = fo.farm_id
                          JOIN orders o ON o.id = fo.order_id
                          JOIN customers c ON c.id = o.customer_id
                          JOIN currencies cur ON cur.id = fo.currency_id
                          LEFT JOIN shipping_agents sa ON sa.id = o.shipping_agent_id
                          LEFT JOIN invoices i ON i.order_id = o.id AND i.kind = 'order' AND i.status = 'open'
                         WHERE " . implode(' AND ', $where) . " ORDER BY o.departure_date DESC, f.name LIMIT 5000", $p);
    }

    /** Inline single-field update from the grid. */
    protected function doUpdate(): array
    {
        $field = $this->req->str('field');
        if (!isset(self::EDITABLE[$field])) throw new HttpException('This field cannot be edited.', 422);
        $fo = DB::one('SELECT fo.*, f.name AS farm FROM farm_orders fo JOIN farms f ON f.id = fo.farm_id WHERE fo.id = :id', ['id' => $this->req->int('id')]);
        if (!$fo) throw new HttpException('Record not found.', 404);
        $fn = self::EDITABLE[$field];
        $value = $this->req->$fn('value');
        if (is_float($value) && $value < 0) throw new HttpException('Value cannot be negative.', 422);
        DB::update('farm_orders', [$field => $value], 'id = :id', ['id' => $fo['id']]);
        Log::write('farm_order', (int)$fo['id'], 'update', sprintf('%s: %s set to "%s"', $fo['farm'], str_replace('_', ' ', $field), (string)$value), (int)$fo['order_id']);
        return ['ok' => true];
    }
}

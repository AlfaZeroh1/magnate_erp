<?php
declare(strict_types=1);

namespace App\Api;

use App\Core\DB;
use App\Core\HttpException;
use App\Core\Log;

final class PackingApi extends BaseApi
{
    protected function rules(): array
    {
        return ['list' => 'packing.view', 'update' => 'packing.edit'];
    }

    protected function readOnly(): array
    {
        return ['list'];
    }

    protected function doList(): array
    {
        $where = '1=1';
        $p = [];
        if ($s = $this->req->str('status')) { $where .= ' AND pl.status = :s'; $p['s'] = $s; }
        return DB::all("SELECT pl.*, o.order_no, o.departure_date, o.stage, c.name AS customer, dp.name AS drop_point,
                               (SELECT COALESCE(SUM(la.received_stems),0) FROM line_allocations la JOIN order_lines ol ON ol.id = la.order_line_id WHERE ol.order_id = o.id) AS stems,
                               (SELECT COALESCE(SUM(la.received_boxes),0) FROM line_allocations la JOIN order_lines ol ON ol.id = la.order_line_id WHERE ol.order_id = o.id) AS boxes,
                               i.id AS invoice_id, i.invoice_no
                          FROM packing_lists pl
                          JOIN orders o ON o.id = pl.order_id
                          JOIN customers c ON c.id = o.customer_id
                          LEFT JOIN drop_points dp ON dp.id = pl.drop_point_id
                          LEFT JOIN invoices i ON i.packing_list_id = pl.id AND i.status = 'open'
                         WHERE $where ORDER BY o.departure_date DESC, pl.id DESC LIMIT 5000", $p);
    }

    protected function doUpdate(): array
    {
        $pl = DB::one('SELECT * FROM packing_lists WHERE id = :id', ['id' => $this->req->int('id')]);
        if (!$pl) throw new HttpException('Packing list not found.', 404);
        $data = [];
        foreach (['drop_point_id' => 'intOrNull', 'awb_no' => 'strOrNull', 'flight_no' => 'strOrNull', 'gross_weight_kg' => 'numOrNull', 'volume_weight_kg' => 'numOrNull', 'notes' => 'strOrNull'] as $k => $fn) {
            if ($this->req->has($k)) $data[$k] = $this->req->$fn($k);
        }
        if ($pl['status'] === 'invoiced') {
            // after invoicing only logistics references can change, never quantities
            $data = array_intersect_key($data, array_flip(['awb_no', 'flight_no', 'notes', 'drop_point_id']));
        }
        DB::update('packing_lists', $data, 'id = :id', ['id' => $pl['id']]);
        Log::write('packing_list', (int)$pl['id'], 'update', "Packing list {$pl['packing_list_no']} details updated", (int)$pl['order_id']);
        return ['_message' => 'Packing details saved.'];
    }
}

<?php
declare(strict_types=1);

namespace App\Api;

use App\Core\Auth;
use App\Core\DB;
use App\Core\Fx;
use App\Core\HttpException;
use App\Core\Log;
use App\Core\Request;
use App\Core\Resources;
use App\Core\Seq;

/** Generic CRUD engine for everything declared in App\Core\Resources. */
final class ResourceApi extends BaseApi
{
    protected function rules(): array
    {
        $need = fn(string $act) => function (Request $r) use ($act) {
            Auth::require(Resources::get($r->str('_resource'))['perm'][$act]);
        };
        return [
            'meta'    => $need('view'),
            'list'    => $need('view'),
            'get'     => $need('view'),
            'save'    => function (Request $r) {
                $res = Resources::get($r->str('_resource'));
                Auth::require($res['perm'][$r->int('id') > 0 ? 'edit' : 'create']);
            },
            'delete'  => $need('delete'),
            'options' => 'auth',   // lookups for selects; only id + label are exposed
        ];
    }

    protected function readOnly(): array
    {
        return ['meta', 'list', 'get', 'options'];
    }

    // ------------------------------------------------------------------
    protected function doMeta(): array
    {
        $r = Resources::get($this->req->str('_resource'));
        $fields = [];
        foreach ($r['fields'] as $key => $f) {
            unset($f['unique']);
            $f['name'] = $key;
            if (($f['peek'] ?? null)) $f['placeholder'] = 'Next: ' . Seq::peek($f['peek']);
            $fields[] = $f;
        }
        return [
            'name'       => $r['name'],
            'title'      => $r['title'],
            'singular'   => $r['singular'],
            'icon'       => $r['icon'],
            'fields'     => $fields,
            'fx'         => $r['fx'] ?? null,
            'dateFilter' => $r['dateFilter'] ?? null,
            'totals'     => $r['totals'] ?? [],
            'rowDocs'    => $r['rowDocs'] ?? [],
            'listOrder'  => $r['listOrder'] ?? null,
            'statement'  => $r['statement'] ?? null,
            'baseCurrency' => ['id' => Fx::baseId(), 'code' => Fx::baseCode()],
            'can' => [
                'create' => Auth::can($r['perm']['create']),
                'edit'   => Auth::can($r['perm']['edit']),
                'delete' => Auth::can($r['perm']['delete']),
            ],
        ];
    }

    /** SELECT t.*, plus "<field>__label" for every select field via LEFT JOINs. */
    private function baseQuery(array $r): array
    {
        $select = ['t.*'];
        $joins = [];
        $labelSql = [
            'customers' => ['customers', 'name'], 'farms' => ['farms', 'name'], 'shipping_agents' => ['shipping_agents', 'name'],
            'currencies' => ['currencies', 'code'], 'countries' => ['countries', 'name'], 'drop_points' => ['drop_points', 'name'],
            'categories' => ['product_categories', 'name'], 'expense_types' => ['expense_types', 'name'], 'lengths' => ['lengths', 'name'],
            'order_by' => ['order_by', 'name'], 'box_types' => ['box_types', 'name'], 'varieties' => ['varieties', 'name'],
            'orders' => ['orders', 'order_no'], 'invoices' => ['invoices', 'invoice_no'], 'freight_bookings' => ['freight_bookings', 'booking_no'],
            'roles' => ['roles', 'name'],
        ];
        foreach ($r['fields'] as $key => $f) {
            if (($f['type'] ?? '') !== 'select' || empty($f['source'])) continue;
            [$table, $col] = $labelSql[$f['source']];
            $alias = 'j_' . $key;
            $joins[] = "LEFT JOIN `$table` $alias ON $alias.id = t.`$key`";
            $select[] = "$alias.`$col` AS `{$key}__label`";
        }
        return [$select, $joins];
    }

    protected function doList(): array
    {
        $r = Resources::get($this->req->str('_resource'));
        [$select, $joins] = $this->baseQuery($r);
        $where = [$r['where'] ?? '1=1'];
        $params = [];
        if (!empty($r['dateFilter'])) {
            if ($from = $this->req->date('from')) { $where[] = "t.`{$r['dateFilter']}` >= :from"; $params['from'] = $from; }
            if ($to = $this->req->date('to'))     { $where[] = "t.`{$r['dateFilter']}` <= :to";   $params['to'] = $to; }
        }
        $sql = sprintf('SELECT %s FROM `%s` t %s WHERE %s ORDER BY %s LIMIT 10000',
            implode(', ', $select), $r['table'], implode(' ', $joins), implode(' AND ', $where), $r['order'] ?? 't.id DESC');
        return DB::all($sql, $params);
    }

    protected function doGet(): array
    {
        $r = Resources::get($this->req->str('_resource'));
        [$select, $joins] = $this->baseQuery($r);
        $row = DB::one(sprintf('SELECT %s FROM `%s` t %s WHERE t.id = :id AND %s',
            implode(', ', $select), $r['table'], implode(' ', $joins), $r['where'] ?? '1=1'), ['id' => $this->req->int('id')]);
        if (!$row) throw new HttpException('Record not found.', 404);
        return $row;
    }

    protected function doSave(): array
    {
        $r = Resources::get($this->req->str('_resource'));
        $id = $this->req->int('id');
        $existing = null;
        if ($id > 0) {
            $existing = DB::one(sprintf('SELECT * FROM `%s` t WHERE t.id = :id AND %s', $r['table'], $r['where'] ?? '1=1'), ['id' => $id]);
            if (!$existing) throw new HttpException('Record not found.', 404);
        }

        [$data, $errors] = $this->collect($r);
        if ($errors) throw HttpException::validation($errors);

        // FX: rate falls back to the stored rate for the date; base amounts are always recomputed server-side.
        if (!empty($r['fx'])) {
            $fx = $r['fx'];
            $cur = (int)$data[$fx['currency']];
            $rate = (float)($data[$fx['rate']] ?? 0);
            if ($rate <= 0) $rate = Fx::rateOrFail($cur, $data[$fx['date']] ?? null);
            if ($cur === Fx::baseId()) $rate = 1.0;
            $data[$fx['rate']] = $rate;
            foreach ($fx['amounts'] as $amountField => $baseField) {
                $data[$baseField] = round((float)($data[$amountField] ?? 0) * $rate, 2);
            }
        }

        $id = DB::tx(function () use ($r, $data, $existing, $id) {
            // inside the transaction so a reserved document number is never wasted on a failed save
            if (isset($r['beforeSave'])) $data = ($r['beforeSave'])($data, $existing);
            if ($existing) {
                DB::update($r['table'], $data, 'id = :id', ['id' => $id]);
            } else {
                $id = DB::insert($r['table'], $data);
            }
            if (isset($r['afterSave'])) ($r['afterSave'])($id, $data);
            $orderId = isset($r['orderOf']) ? ($r['orderOf'])($data) : null;
            Log::write($r['name'], $id, $existing ? 'update' : 'create',
                ($existing ? 'Updated ' : 'Created ') . strtolower($r['singular']) . ' ' . $this->describe($r, $data),
                $orderId ? (int)$orderId : null);
            return $id;
        });

        return ['id' => $id, '_message' => $r['singular'] . ($existing ? ' updated.' : ' saved.')];
    }

    protected function doDelete(): array
    {
        $r = Resources::get($this->req->str('_resource'));
        $id = $this->req->int('id');
        $row = DB::one(sprintf('SELECT * FROM `%s` t WHERE t.id = :id AND %s', $r['table'], $r['where'] ?? '1=1'), ['id' => $id]);
        if (!$row) throw new HttpException('Record not found.', 404);
        if (isset($r['protect']) && ($why = ($r['protect'])($row))) throw new HttpException($why, 409);
        if ($r['table'] === 'invoices') {
            if (DB::val('SELECT COUNT(*) FROM customer_other_costs WHERE invoice_id = :id', ['id' => $id]) > 0) {
                throw new HttpException('This invoice has other costs recorded against it. Remove them first.', 409);
            }
        }
        $orderId = isset($r['orderOf']) ? ($r['orderOf'])($row) : null;
        DB::tx(function () use ($r, $id, $row, $orderId) {
            DB::delete($r['table'], 'id = :id', ['id' => $id]);
            Log::write($r['name'], $id, 'delete', 'Deleted ' . strtolower($r['singular']) . ' ' . $this->describe($r, $row), $orderId ? (int)$orderId : null, $row);
        });
        return ['_message' => $r['singular'] . ' deleted.'];
    }

    protected function doOptions(): array
    {
        $sources = Resources::sources();
        $key = $this->req->str('source');
        if (!isset($sources[$key])) throw new HttpException('Unknown source.', 404);
        $s = $sources[$key];
        $sql = $s['sql'];
        $params = [];
        if ($id = $this->req->intOrNull('id')) {
            // label lookup for an already-selected value (ignores the active filter)
            $alias = explode('.', $s['search'][0])[0];
            $sql = preg_replace('/WHERE .*$/s', "WHERE $alias.id = :id", $sql);
            return DB::all($sql, ['id' => $id]);
        }
        $q = $this->req->str('q');
        if ($q !== '') {
            $sql .= ' AND (' . implode(' OR ', array_map(fn($c) => "$c LIKE :q", $s['search'])) . ')';
            $params['q'] = '%' . $q . '%';
        }
        $sql .= ' ORDER BY ' . $s['order'] . ' LIMIT ' . ($q === '' ? 200 : 50);
        return DB::all($sql, $params);
    }

    // ------------------------------------------------------------------
    /** Validate and normalise posted fields according to the definition. */
    private function collect(array $r): array
    {
        $data = [];
        $errors = [];
        foreach ($r['fields'] as $key => $f) {
            $type = $f['type'] ?? 'text';
            if ($type === 'static' || !empty($f['readonly'])) continue;
            if (($f['form'] ?? true) === false) continue;
            $label = $f['label'] ?? $key;

            switch ($type) {
                case 'switch':
                    $val = $this->req->bool($key) ? 1 : 0;
                    break;
                case 'number': case 'money': case 'rate':
                    $val = $this->req->numOrNull($key);
                    if ($val !== null && $val < 0) $errors[$key] = "$label cannot be negative.";
                    break;
                case 'select':
                    $val = $this->req->intOrNull($key);
                    break;
                case 'date':
                    $raw = $this->req->str($key);
                    $val = $this->req->date($key);
                    if ($raw !== '' && $val === null) $errors[$key] = "$label is not a valid date.";
                    break;
                case 'email':
                    $val = $this->req->strOrNull($key);
                    if ($val !== null && !filter_var($val, FILTER_VALIDATE_EMAIL)) $errors[$key] = 'Enter a valid email address.';
                    break;
                default:
                    $val = $this->req->strOrNull($key);
                    if ($val !== null && !empty($f['upper'])) $val = mb_strtoupper($val);
                    if ($val !== null && !empty($f['maxlength']) && mb_strlen($val) > $f['maxlength']) $errors[$key] = "$label is too long.";
            }

            if (!empty($f['required']) && ($val === null || $val === '') && ($f['role'] ?? '') !== 'base') {
                $errors[$key] = "$label is required.";
            }
            if (!empty($f['unique']) && $val !== null) {
                $dupe = DB::val(sprintf('SELECT id FROM `%s` WHERE `%s` = :v AND id <> :id LIMIT 1', $r['table'], $key),
                    ['v' => $val, 'id' => $this->req->int('id')]);
                if ($dupe) $errors[$key] = "$label \"$val\" is already in use.";
            }
            $data[$key] = $val;
        }
        // base-role money fields are always computed, never trusted from the client
        foreach ($r['fields'] as $key => $f) {
            if (($f['role'] ?? '') === 'base') unset($data[$key]);
        }
        return [$data, $errors];
    }

    private function describe(array $r, array $data): string
    {
        foreach (['name', 'invoice_no', 'code', 'description', 'details', 'bank_name'] as $k) {
            if (!empty($data[$k])) return '"' . $data[$k] . '"';
        }
        foreach (['amount', 'total', 'selling_price'] as $k) {
            if (isset($data[$k])) return 'of ' . number_format((float)$data[$k], 2);
        }
        return '#' . ($data['id'] ?? '');
    }
}

<?php
declare(strict_types=1);

namespace App\Api;

use App\Core\Auth;
use App\Core\DB;
use App\Core\HttpException;
use App\Core\Log;

/** Farm price list (buying + optional farm selling price) and general selling prices. */
final class PricingApi extends BaseApi
{
    protected function rules(): array
    {
        return [
            'farm_list' => 'farm_prices.view', 'farm_save' => 'farm_prices.edit', 'farm_delete' => 'farm_prices.edit', 'farm_adjust' => 'farm_prices.edit',
            'farm_copy' => 'farm_prices.edit',
            'selling_list' => 'selling_prices.view', 'selling_save' => 'selling_prices.edit', 'selling_delete' => 'selling_prices.edit',
        ];
    }

    protected function readOnly(): array
    {
        return ['farm_list', 'selling_list'];
    }

    // ---------------- Farm prices ----------------
    protected function doFarmList(): array
    {
        $w = ['1=1']; $p = [];
        foreach (['farm_id' => 'fp.farm_id', 'variety_id' => 'fp.variety_id', 'length_id' => 'fp.length_id'] as $k => $col) {
            if ($v = $this->req->intOrNull($k)) { $w[] = "$col = :$k"; $p[$k] = $v; }
        }
        return DB::all("SELECT fp.*, f.name AS farm, v.name AS variety, pc.name AS category, l.name AS length, c.code AS currency,
                               sp.price AS general_selling, spc.code AS general_currency
                          FROM farm_prices fp
                          JOIN farms f ON f.id = fp.farm_id
                          JOIN varieties v ON v.id = fp.variety_id
                          LEFT JOIN product_categories pc ON pc.id = v.category_id
                          JOIN lengths l ON l.id = fp.length_id
                          JOIN currencies c ON c.id = fp.currency_id
                          LEFT JOIN selling_prices sp ON sp.variety_id = fp.variety_id AND sp.length_id = fp.length_id
                          LEFT JOIN currencies spc ON spc.id = sp.currency_id
                         WHERE " . implode(' AND ', $w) . " ORDER BY f.name, v.name, l.sort_order LIMIT 20000", $p);
    }

    protected function doFarmSave(): array
    {
        $id = $this->req->int('id');
        $d = [
            'farm_id' => $this->req->intOrNull('farm_id'), 'variety_id' => $this->req->intOrNull('variety_id'),
            'currency_id' => $this->req->intOrNull('currency_id'),
            'buying_price' => $this->req->numOrNull('buying_price'), 'selling_price' => $this->req->numOrNull('selling_price'),
            'is_active' => $this->req->has('is_active') ? ($this->req->bool('is_active') ? 1 : 0) : 1,
            'updated_by' => Auth::id(),
        ];
        $lengths = array_values(array_filter(array_map('intval', (array)($this->req->get('length_ids') ?: [$this->req->int('length_id')]))));
        $err = [];
        foreach (['farm_id' => 'Farm', 'variety_id' => 'Variety', 'currency_id' => 'Currency'] as $k => $l) if (!$d[$k]) $err[$k] = "$l is required.";
        if ($d['buying_price'] === null || $d['buying_price'] < 0) $err['buying_price'] = 'Enter the buying price.';
        if ($d['selling_price'] !== null && $d['selling_price'] < 0) $err['selling_price'] = 'Cannot be negative.';
        if (!$lengths) $err['length_ids'] = 'Choose at least one length.';
        if ($err) throw HttpException::validation($err);

        DB::tx(function () use ($id, $d, $lengths) {
            if ($id) {
                DB::update('farm_prices', $d + ['length_id' => $lengths[0]], 'id = :id', ['id' => $id]);
            } else {
                foreach ($lengths as $len) {
                    DB::run('INSERT INTO farm_prices (farm_id, variety_id, length_id, buying_price, selling_price, currency_id, is_active, updated_by)
                             VALUES (:f, :v, :l, :b, :s, :c, :a, :u)
                             ON DUPLICATE KEY UPDATE buying_price = VALUES(buying_price), selling_price = VALUES(selling_price),
                                                     currency_id = VALUES(currency_id), is_active = VALUES(is_active), updated_by = VALUES(updated_by)',
                        ['f' => $d['farm_id'], 'v' => $d['variety_id'], 'l' => $len, 'b' => $d['buying_price'], 's' => $d['selling_price'],
                         'c' => $d['currency_id'], 'a' => $d['is_active'], 'u' => $d['updated_by']]);
                }
            }
            $names = DB::one('SELECT f.name AS farm, v.name AS variety FROM farms f, varieties v WHERE f.id = :f AND v.id = :v', ['f' => $d['farm_id'], 'v' => $d['variety_id']]);
            Log::write('farm_price', $id ?: null, 'save', sprintf('Farm price %s / %s (%d length(s)): buy %s%s', $names['farm'] ?? '', $names['variety'] ?? '',
                count($lengths), $d['buying_price'], $d['selling_price'] !== null ? ', sell ' . $d['selling_price'] : ''));
        });
        return ['_message' => 'Farm price saved.'];
    }

    protected function doFarmDelete(): array
    {
        $ids = array_filter(array_map('intval', (array)($this->req->get('ids') ?: [$this->req->int('id')])));
        if (!$ids) throw new HttpException('Nothing selected.', 422);
        DB::run('DELETE FROM farm_prices WHERE id IN (' . implode(',', $ids) . ')');
        Log::write('farm_price', null, 'delete', 'Deleted ' . count($ids) . ' farm price(s)');
        return ['_message' => count($ids) . ' price(s) deleted.'];
    }

    /** Bulk change selected rows: set or adjust by % / amount, on buying or selling. */
    protected function doFarmAdjust(): array
    {
        $ids = array_filter(array_map('intval', (array)$this->req->get('ids')));
        $field = $this->req->str('field') === 'selling_price' ? 'selling_price' : 'buying_price';
        $mode = $this->req->str('mode');
        $value = $this->req->num('value');
        if (!$ids) throw new HttpException('Select at least one price.', 422);
        $expr = match ($mode) {
            'set'     => ':v',
            'percent' => "ROUND(COALESCE($field,0) * (1 + :v / 100), 4)",
            'amount'  => "GREATEST(0, COALESCE($field,0) + :v)",
            default   => throw new HttpException('Unknown adjustment.', 422),
        };
        $n = DB::run("UPDATE farm_prices SET $field = $expr, updated_by = :u WHERE id IN (" . implode(',', $ids) . ')', ['v' => $value, 'u' => Auth::id()])->rowCount();
        Log::write('farm_price', null, 'bulk', "Bulk $mode of $field by $value on " . count($ids) . ' price(s)');
        return ['_message' => "$n price(s) updated."];
    }

    /** Copy one farm's price list to another farm (skips existing). */
    protected function doFarmCopy(): array
    {
        $from = $this->req->int('from_farm_id'); $to = $this->req->int('to_farm_id');
        if (!$from || !$to || $from === $to) throw new HttpException('Choose two different farms.', 422);
        $n = DB::run('INSERT IGNORE INTO farm_prices (farm_id, variety_id, length_id, buying_price, selling_price, currency_id, is_active, updated_by)
                      SELECT :to, variety_id, length_id, buying_price, selling_price, currency_id, is_active, :u FROM farm_prices WHERE farm_id = :from',
            ['to' => $to, 'from' => $from, 'u' => Auth::id()])->rowCount();
        Log::write('farm_price', null, 'copy', "Copied $n price(s) between farms");
        return ['_message' => "$n price(s) copied."];
    }

    // ---------------- General selling prices ----------------
    protected function doSellingList(): array
    {
        $w = ['1=1']; $p = [];
        if ($v = $this->req->intOrNull('variety_id')) { $w[] = 'sp.variety_id = :v'; $p['v'] = $v; }
        return DB::all("SELECT sp.*, v.name AS variety, pc.name AS category, l.name AS length, c.code AS currency, u.full_name AS updated_by_name,
                               (SELECT COUNT(*) FROM farm_prices fp WHERE fp.variety_id = sp.variety_id AND fp.length_id = sp.length_id AND fp.is_active = 1) AS farms,
                               (SELECT MIN(fp.buying_price) FROM farm_prices fp WHERE fp.variety_id = sp.variety_id AND fp.length_id = sp.length_id AND fp.currency_id = sp.currency_id AND fp.is_active = 1) AS min_buy,
                               (SELECT MAX(fp.buying_price) FROM farm_prices fp WHERE fp.variety_id = sp.variety_id AND fp.length_id = sp.length_id AND fp.currency_id = sp.currency_id AND fp.is_active = 1) AS max_buy
                          FROM selling_prices sp
                          JOIN varieties v ON v.id = sp.variety_id
                          LEFT JOIN product_categories pc ON pc.id = v.category_id
                          JOIN lengths l ON l.id = sp.length_id
                          JOIN currencies c ON c.id = sp.currency_id
                          LEFT JOIN users u ON u.id = sp.updated_by
                         WHERE " . implode(' AND ', $w) . " ORDER BY v.name, l.sort_order", $p);
    }

    /** e.g. "Explorer at 50cm = USD 0.50": one variety, one or more lengths. Optionally pushes to farm-specific selling prices. */
    protected function doSellingSave(): array
    {
        $variety = $this->req->int('variety_id');
        $currency = $this->req->int('currency_id');
        $price = $this->req->numOrNull('price');
        $lengths = array_values(array_filter(array_map('intval', (array)($this->req->get('length_ids') ?: [$this->req->int('length_id')]))));
        $err = [];
        if (!$variety) $err['variety_id'] = 'Choose a variety.';
        if (!$lengths) $err['length_ids'] = 'Choose at least one length.';
        if (!$currency) $err['currency_id'] = 'Choose a currency.';
        if ($price === null || $price < 0) $err['price'] = 'Enter a price.';
        if ($err) throw HttpException::validation($err);
        $sync = $this->req->bool('sync_farms');

        $touched = 0;
        DB::tx(function () use ($variety, $lengths, $price, $currency, $sync, &$touched) {
            foreach ($lengths as $len) {
                DB::run('INSERT INTO selling_prices (variety_id, length_id, price, currency_id, updated_by) VALUES (:v, :l, :p, :c, :u)
                         ON DUPLICATE KEY UPDATE price = VALUES(price), currency_id = VALUES(currency_id), updated_by = VALUES(updated_by)',
                    ['v' => $variety, 'l' => $len, 'p' => $price, 'c' => $currency, 'u' => Auth::id()]);
                if ($sync) {
                    // farm-specific selling prices are in the farm price currency; only sync rows in the same currency
                    $touched += DB::run('UPDATE farm_prices SET selling_price = :p, updated_by = :u WHERE variety_id = :v AND length_id = :l AND currency_id = :c',
                        ['p' => $price, 'u' => Auth::id(), 'v' => $variety, 'l' => $len, 'c' => $currency])->rowCount();
                }
            }
            $v = DB::val('SELECT name FROM varieties WHERE id = :id', ['id' => $variety]);
            $ls = implode(', ', DB::col('SELECT name FROM lengths WHERE id IN (' . implode(',', $lengths) . ') ORDER BY sort_order'));
            $code = DB::val('SELECT code FROM currencies WHERE id = :id', ['id' => $currency]);
            Log::write('selling_price', $variety, 'set', "Selling price $v @ $ls set to $code $price" . ($sync ? " (synced to $touched farm price(s))" : ''));
        });
        return ['_message' => 'Selling price saved' . ($sync ? " and applied to $touched farm price(s)." : '.')];
    }

    protected function doSellingDelete(): array
    {
        DB::delete('selling_prices', 'id = :id', ['id' => $this->req->int('id')]);
        Log::write('selling_price', $this->req->int('id'), 'delete', 'Removed a general selling price');
        return ['_message' => 'Selling price removed.'];
    }
}

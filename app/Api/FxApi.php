<?php
declare(strict_types=1);

namespace App\Api;

use App\Core\Auth;
use App\Core\DB;
use App\Core\Fx;
use App\Core\HttpException;
use App\Core\Log;

final class FxApi extends BaseApi
{
    protected function rules(): array
    {
        return [
            'rate'     => 'auth',                 // form helper: rate for currency on date
            'missing'  => 'auth',
            'overview' => 'exchange_rates.view',
            'history'  => 'exchange_rates.view',
            'set'      => 'exchange_rates.edit',
            'set_many' => 'exchange_rates.edit',
            'delete'   => 'exchange_rates.edit',
        ];
    }

    protected function readOnly(): array
    {
        return ['rate', 'missing', 'overview', 'history'];
    }

    protected function doRate(): array
    {
        $cur = $this->req->int('currency_id');
        $date = $this->req->date('date') ?? date('Y-m-d');
        $rate = Fx::rate($cur, $date);
        return ['currency_id' => $cur, 'date' => $date, 'rate' => $rate, 'is_base' => $cur === Fx::baseId()];
    }

    protected function doMissing(): array
    {
        return ['missing' => Fx::missingThisMonth(), 'can_edit' => Auth::can('exchange_rates.edit')];
    }

    /** One card per currency: latest rate, previous rate, change, last 12 points for a sparkline. */
    protected function doOverview(): array
    {
        $rows = DB::all('SELECT c.id, c.code, c.name, c.symbol, c.is_base FROM currencies c WHERE c.is_active = 1 ORDER BY c.is_base DESC, c.code');
        foreach ($rows as &$c) {
            $pts = DB::all('SELECT rate, effective_date FROM exchange_rates WHERE currency_id = :c ORDER BY effective_date DESC LIMIT 12', ['c' => $c['id']]);
            $c['latest'] = $pts[0] ?? null;
            $c['previous'] = $pts[1] ?? null;
            $c['series'] = array_reverse($pts);
            $c['stale'] = !$c['is_base'] && (!$c['latest'] || substr($c['latest']['effective_date'], 0, 7) < date('Y-m'));
        }
        return $rows;
    }

    protected function doHistory(): array
    {
        $params = [];
        $where = '1=1';
        if ($c = $this->req->intOrNull('currency_id')) { $where .= ' AND er.currency_id = :c'; $params['c'] = $c; }
        return DB::all("SELECT er.*, c.code, c.name, u.full_name AS created_by_name
                          FROM exchange_rates er JOIN currencies c ON c.id = er.currency_id
                          LEFT JOIN users u ON u.id = er.created_by
                         WHERE $where AND c.is_base = 0 ORDER BY er.effective_date DESC, c.code LIMIT 1000", $params);
    }

    private function upsert(int $currencyId, float $rate, string $date, ?string $notes): void
    {
        if ($currencyId === Fx::baseId()) throw new HttpException('The base currency is always 1.', 422);
        if ($rate <= 0) throw HttpException::validation(['rate' => 'Rate must be greater than zero.']);
        $code = DB::val('SELECT code FROM currencies WHERE id = :id', ['id' => $currencyId]);
        if (!$code) throw new HttpException('Unknown currency.', 404);
        DB::run('INSERT INTO exchange_rates (currency_id, rate, effective_date, notes, created_by)
                 VALUES (:c, :r, :d, :n, :u)
                 ON DUPLICATE KEY UPDATE rate = VALUES(rate), notes = VALUES(notes), created_by = VALUES(created_by)',
            ['c' => $currencyId, 'r' => $rate, 'd' => $date, 'n' => $notes, 'u' => Auth::id()]);
        Log::write('exchange_rate', $currencyId, 'set', "Set 1 {$code} = " . rtrim(rtrim(number_format($rate, 6, '.', ''), '0'), '.') . ' ' . Fx::baseCode() . " from {$date}");
    }

    protected function doSet(): array
    {
        $date = $this->req->date('effective_date') ?? date('Y-m-d');
        $this->upsert($this->req->int('currency_id'), $this->req->num('rate'), $date, $this->req->strOrNull('notes'));
        return ['_message' => 'Exchange rate saved.'];
    }

    /** Used by the login prompt: [{currency_id, rate}] all effective today. */
    protected function doSetMany(): array
    {
        $date = $this->req->date('effective_date') ?? date('Y-m-d');
        $rows = $this->req->arr('rates');
        $n = 0;
        DB::tx(function () use ($rows, $date, &$n) {
            foreach ($rows as $r) {
                $rate = (float)($r['rate'] ?? 0);
                if ($rate <= 0) continue;
                $this->upsert((int)$r['currency_id'], $rate, $date, 'Set from monthly prompt');
                $n++;
            }
        });
        if ($n === 0) throw new HttpException('Enter at least one rate.', 422);
        return ['_message' => "$n exchange rate(s) saved."];
    }

    protected function doDelete(): array
    {
        $row = DB::one('SELECT er.*, c.code FROM exchange_rates er JOIN currencies c ON c.id = er.currency_id WHERE er.id = :id', ['id' => $this->req->int('id')]);
        if (!$row) throw new HttpException('Not found.', 404);
        if ((int)$row['currency_id'] === Fx::baseId()) throw new HttpException('The base currency rate cannot be removed.', 409);
        DB::delete('exchange_rates', 'id = :id', ['id' => $row['id']]);
        Log::write('exchange_rate', (int)$row['currency_id'], 'delete', "Removed {$row['code']} rate of {$row['effective_date']}");
        return ['_message' => 'Rate removed.'];
    }
}

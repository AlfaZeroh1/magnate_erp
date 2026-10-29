<?php
declare(strict_types=1);

namespace App\Api;

use App\Core\Auth;
use App\Core\DB;
use App\Core\HttpException;
use App\Reports\Catalog;
use App\Services\InsightService;
use App\Services\OrderService;

final class ReportsApi extends BaseApi
{
    protected function rules(): array
    {
        return ['catalog' => 'reports.view', 'run' => 'reports.view', 'journey' => 'reports.view', 'insights' => 'reports.view'];
    }

    protected function readOnly(): array
    {
        return ['catalog', 'run', 'journey', 'insights'];
    }

    protected function doCatalog(): array
    {
        $out = [];
        foreach (Catalog::all() as $k => $r) {
            if (!empty($r['financial']) && !Auth::can('reports.financial')) continue;
            $out[] = ['key' => $k, 'group' => $r['group'], 'title' => $r['title'], 'icon' => $r['icon'], 'desc' => $r['desc']];
        }
        return ['groups' => Catalog::groups(), 'reports' => $out];
    }

    public static function filters(\App\Core\Request $req): array
    {
        return [
            'from' => $req->date('from') ?? date('Y-01-01'),
            'to' => $req->date('to') ?? date('Y-m-d'),
            'customer_id' => $req->intOrNull('customer_id'),
            'farm_id' => $req->intOrNull('farm_id'),
            'agent_id' => $req->intOrNull('agent_id'),
        ];
    }

    public static function execute(array $r, array $f): array
    {
        if (isset($r['php'])) return ($r['php'])($f);
        $params = [];
        foreach ($f as $k => $v) if (str_contains($r['sql'], ':' . $k)) $params[$k] = $v;
        return DB::all($r['sql'], $params);
    }

    protected function doRun(): array
    {
        $r = Catalog::get($this->req->str('key'));
        if (!$r) throw new HttpException('Unknown report.', 404);
        if (!empty($r['financial'])) Auth::require('reports.financial');
        $f = self::filters($this->req);
        return [
            'key' => $r['key'], 'title' => $r['title'], 'desc' => $r['desc'], 'filters' => $r['filters'], 'applied' => $f,
            'columns' => $r['columns'], 'totals' => $r['totals'] ?? [], 'chart' => $r['chart'] ?? null, 'link' => $r['link'] ?? [],
            'rows' => self::execute($r, $f),
        ];
    }

    /** Everything about one order, start to finish. */
    protected function doJourney(): array
    {
        $o = OrderService::find($this->req->int('id'));
        $oid = (int)$o['id'];
        $fin = Auth::can('reports.financial');
        $m = OrderService::financials($o);
        if (!$fin) {
            foreach (['revenue_base', 'other_revenue_base', 'farm_cost_base', 'other_cost_base', 'expenses_base', 'total_revenue_base', 'total_cost_base', 'profit_base', 'margin_pct'] as $k) $m[$k] = null;
        }
        $timeline = DB::all('SELECT al.created_at, al.action, al.description, u.full_name AS user FROM activity_log al LEFT JOIN users u ON u.id = al.user_id
                              WHERE al.order_id = :o ORDER BY al.created_at, al.id', ['o' => $oid]);

        // Lead times between milestones (hours)
        $ms = ['Captured' => $o['created_at'], 'Sent to farms' => $o['sourcing_at'], 'Farms confirmed' => $o['confirmed_at'],
               'Delivery checked' => $o['received_at'], 'Packed' => $o['packed_at'], 'Invoiced' => $o['invoiced_at'], 'Closed' => $o['closed_at']];
        $legs = [];
        $prev = null; $prevLabel = null;
        foreach ($ms as $label => $ts) {
            if (!$ts) continue;
            if ($prev) $legs[] = ['from' => $prevLabel, 'to' => $label, 'hours' => round((strtotime($ts) - strtotime($prev)) / 3600, 1)];
            $prev = $ts; $prevLabel = $label;
        }

        // Benchmarks: averages for the same customer and for the business (last 180 days)
        $bench = null;
        if ($fin) {
            $peers = DB::col("SELECT id FROM orders WHERE stage IN ('invoiced','closed') AND id <> :o AND invoiced_at >= NOW() - INTERVAL 180 DAY ORDER BY id DESC LIMIT 150", ['o' => $oid]);
            $all = []; $same = [];
            foreach ($peers as $pid) {
                $po = OrderService::find((int)$pid);
                $pm = OrderService::financials($po);
                if ($pm['margin_pct'] === null) continue;
                $all[] = $pm['margin_pct'];
                if ((int)$po['customer_id'] === (int)$o['customer_id']) $same[] = $pm['margin_pct'];
            }
            $bench = ['avg_margin' => $all ? round(array_sum($all) / count($all), 1) : null, 'customer_avg_margin' => $same ? round(array_sum($same) / count($same), 1) : null,
                      'sample' => count($all), 'customer_sample' => count($same)];
        }

        // Narrative insights for this order
        $q = $m['qty'];
        $ins = [];
        if ($q['allocated'] > 0 && OrderService::stageIndex($o['stage']) >= 2) {
            $cr = $q['confirmed'] / $q['allocated'] * 100;
            $ins[] = [$cr >= 98 ? 'good' : ($cr >= 85 ? 'warn' : 'bad'), 'bi-patch-check', sprintf('Farms confirmed %.1f%% of the stems requested (%s of %s).', $cr, number_format($q['confirmed']), number_format($q['allocated']))];
        }
        if ($q['confirmed'] > 0 && OrderService::stageIndex($o['stage']) >= 3) {
            $dr = $q['received'] / $q['confirmed'] * 100;
            $ins[] = [$dr >= 99 ? 'good' : ($dr >= 95 ? 'warn' : 'bad'), 'bi-clipboard2-check', sprintf('Delivery check: %.1f%% of confirmed stems arrived in good order%s.', $dr, $q['rejected'] > 0 ? ', ' . number_format($q['rejected']) . ' rejected' : '')];
            $worst = DB::one("SELECT f.name, SUM(la.confirmed_stems - la.received_stems) short FROM line_allocations la JOIN order_lines ol ON ol.id = la.order_line_id JOIN farms f ON f.id = la.farm_id
                               WHERE ol.order_id = :o GROUP BY f.id, f.name HAVING short > 0 ORDER BY short DESC LIMIT 1", ['o' => $oid]);
            if ($worst) $ins[] = ['warn', 'bi-tree', sprintf('Biggest shortfall came from %s (%s stems short).', $worst['name'], number_format((float)$worst['short']))];
        }
        if ($q['ordered'] > 0 && $q['received'] > 0) {
            $fill = $q['received'] / $q['ordered'] * 100;
            $ins[] = [$fill >= 98 ? 'good' : 'warn', 'bi-bullseye', sprintf('Overall the customer received %.1f%% of what they ordered.', $fill)];
        }
        if ($fin && $m['margin_pct'] !== null && $bench && $bench['avg_margin'] !== null) {
            $diff = $m['margin_pct'] - $bench['avg_margin'];
            $ins[] = [$diff >= 0 ? 'good' : 'warn', 'bi-piggy-bank', sprintf('%s margin of %.1f%% is %s the business average of %.1f%% (%d orders, 180 days)%s.',
                $m['projected'] ? 'Projected' : 'Final', $m['margin_pct'], $diff >= 0 ? sprintf('%.1f pts above', $diff) : sprintf('%.1f pts below', abs($diff)), $bench['avg_margin'], $bench['sample'],
                $bench['customer_avg_margin'] !== null ? sprintf('; this customer usually gives %.1f%%', $bench['customer_avg_margin']) : '')];
        }
        if ($legs) {
            $total = array_sum(array_column($legs, 'hours'));
            $slow = array_reduce($legs, fn($c, $l) => !$c || $l['hours'] > $c['hours'] ? $l : $c);
            $ins[] = ['info', 'bi-stopwatch', sprintf('From capture to %s took %s. The longest step was "%s → %s" (%s).', strtolower(end($legs)['to']), self::dur($total), $slow['from'], $slow['to'], self::dur($slow['hours']))];
        }
        $days = (strtotime($o['departure_date']) - strtotime(date('Y-m-d'))) / 86400;
        if (OrderService::stageIndex($o['stage']) >= 0 && OrderService::stageIndex($o['stage']) < 4 && $days <= 1) {
            $ins[] = ['bad', 'bi-alarm', 'Departure is ' . ($days < 0 ? 'past' : ($days == 0 ? 'today' : 'tomorrow')) . ' and the order is not packed yet.'];
        }

        return [
            'order' => $o, 'lines' => OrderService::lines($oid), 'farm_orders' => OrderService::farmOrders($oid),
            'packing' => DB::one('SELECT * FROM packing_lists WHERE order_id = :o', ['o' => $oid]),
            'invoices' => DB::all("SELECT id, invoice_no, invoice_date, total, status FROM invoices WHERE order_id = :o ORDER BY id", ['o' => $oid]),
            'expenses' => $fin ? DB::all('SELECT e.expense_date, et.name AS type, e.description, e.base_amount FROM expenses e JOIN expense_types et ON et.id = e.expense_type_id WHERE e.order_id = :o ORDER BY e.expense_date', ['o' => $oid]) : [],
            'other_costs' => $fin ? DB::all('SELECT coc.cost_date, coc.details, coc.selling_base, coc.buying_base FROM customer_other_costs coc JOIN invoices i ON i.id = coc.invoice_id WHERE i.order_id = :o', ['o' => $oid]) : [],
            'financials' => $m, 'financial_access' => $fin, 'timeline' => $timeline, 'legs' => $legs, 'bench' => $bench,
            'insights' => array_map(fn($i) => ['level' => $i[0], 'icon' => $i[1], 'text' => $i[2]], $ins),
        ];
    }

    private static function dur(float $hours): string
    {
        if ($hours < 1) return round($hours * 60) . ' min';
        if ($hours < 48) return round($hours, 1) . ' h';
        return round($hours / 24, 1) . ' days';
    }

    protected function doInsights(): array
    {
        return InsightService::all();
    }
}

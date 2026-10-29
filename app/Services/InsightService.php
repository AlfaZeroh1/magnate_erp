<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\DB;
use App\Core\Fx;

/**
 * "Smart insights": rule-based business intelligence computed from live data.
 * Each insight: [level good|warn|bad|info, icon, text, link?]
 */
final class InsightService
{
    public static function all(): array
    {
        $out = [];
        $B = Fx::baseCode();
        $fin = Auth::can('reports.financial');

        // Sales momentum: month-to-date vs the same days last month
        $mtd = (float)DB::val("SELECT COALESCE(SUM(base_total),0) FROM invoices WHERE status='open' AND invoice_date BETWEEN DATE_FORMAT(CURDATE(),'%Y-%m-01') AND CURDATE()");
        $lmtd = (float)DB::val("SELECT COALESCE(SUM(base_total),0) FROM invoices WHERE status='open'
                                 AND invoice_date BETWEEN DATE_FORMAT(CURDATE() - INTERVAL 1 MONTH,'%Y-%m-01') AND CURDATE() - INTERVAL 1 MONTH");
        if ($mtd > 0 || $lmtd > 0) {
            $ch = $lmtd > 0 ? ($mtd - $lmtd) / $lmtd * 100 : 100;
            $out[] = [$ch >= 0 ? 'good' : 'warn', $ch >= 0 ? 'bi-graph-up-arrow' : 'bi-graph-down-arrow',
                sprintf('Sales month-to-date are %s %s, %s%.0f%% vs the same point last month.', $B, number_format($mtd), $ch >= 0 ? 'up ' : 'down ', abs($ch)), 'index.php?p=report&r=sales_by_month'];
        }

        // Concentration risk
        $top = DB::one("SELECT c.name, SUM(i.base_total) v FROM invoices i JOIN customers c ON c.id = i.bill_to_customer_id
                         WHERE i.status='open' AND i.invoice_date >= CURDATE() - INTERVAL 90 DAY GROUP BY c.id, c.name ORDER BY v DESC LIMIT 1");
        $tot90 = (float)DB::val("SELECT COALESCE(SUM(base_total),0) FROM invoices WHERE status='open' AND invoice_date >= CURDATE() - INTERVAL 90 DAY");
        if ($top && $tot90 > 0) {
            $share = (float)$top['v'] / $tot90 * 100;
            $out[] = [$share > 40 ? 'warn' : 'info', 'bi-pie-chart', sprintf('%s is %.0f%% of sales in the last 90 days%s', $top['name'], $share, $share > 40 ? ' — high dependency on one customer.' : '.'), 'index.php?p=report&r=sales_by_customer'];
        }

        // Customers going quiet
        $quiet = DB::all("SELECT c.name, MAX(o.order_date) last, COUNT(*) n FROM orders o JOIN customers c ON c.id = o.customer_id
                           WHERE o.stage <> 'cancelled' GROUP BY c.id, c.name HAVING n >= 3 AND last < CURDATE() - INTERVAL 30 DAY ORDER BY n DESC LIMIT 3");
        foreach ($quiet as $q) {
            $out[] = ['warn', 'bi-person-dash', sprintf('%s has ordered %d times but nothing since %s. Worth a call?', $q['name'], $q['n'], date('d M', strtotime($q['last']))), null];
        }

        // Farm reliability (last 90 days)
        foreach (DB::all("SELECT f.name, SUM(la.allocated_stems) asked, SUM(la.confirmed_stems) conf,
                                 SUM(CASE WHEN la.received_stems IS NOT NULL THEN la.confirmed_stems END) conf_r, SUM(la.received_stems) rec
                            FROM line_allocations la JOIN order_lines ol ON ol.id = la.order_line_id JOIN orders o ON o.id = ol.order_id JOIN farms f ON f.id = la.farm_id
                           WHERE o.stage NOT IN ('draft','sourcing','cancelled') AND o.departure_date >= CURDATE() - INTERVAL 90 DAY
                           GROUP BY f.id, f.name HAVING asked >= 1000") as $f) {
            $cr = $f['asked'] > 0 ? $f['conf'] / $f['asked'] * 100 : 100;
            $dr = $f['conf_r'] > 0 ? $f['rec'] / $f['conf_r'] * 100 : 100;
            if ($cr < 85) $out[] = ['bad', 'bi-tree', sprintf('%s confirms only %.0f%% of what you ask (90 days). Consider splitting orders to backup farms.', $f['name'], $cr), 'index.php?p=report&r=farm_performance'];
            if ($dr < 95) $out[] = ['warn', 'bi-clipboard2-x', sprintf('%s delivered %.0f%% of what it confirmed (90 days).', $f['name'], $dr), 'index.php?p=report&r=delivery_variance'];
        }

        // Orders about to depart that are stuck early in the pipeline
        $stuck = (int)DB::val("SELECT COUNT(*) FROM orders WHERE stage IN ('draft','sourcing') AND departure_date <= CURDATE() + INTERVAL 2 DAY");
        if ($stuck) $out[] = ['bad', 'bi-alarm', "$stuck order(s) depart within 2 days but are still not confirmed by farms.", 'index.php?p=orders&stage=sourcing'];

        // Overdue receivables
        $over = DB::one("SELECT COUNT(*) n, COALESCE(SUM(base_total),0) v FROM invoices WHERE status='open' AND due_date < CURDATE() - INTERVAL 30 DAY");
        if ($over && (int)$over['n'] > 0) {
            $paidAll = (float)DB::val('SELECT COALESCE(SUM(base_amount),0) FROM customer_payments');
            $billedAll = (float)DB::val("SELECT COALESCE(SUM(base_total),0) FROM invoices WHERE status='open'") + (float)DB::val('SELECT COALESCE(SUM(selling_base),0) FROM customer_other_costs');
            if ($billedAll - $paidAll > 1) {
                $out[] = ['warn', 'bi-hourglass-split', sprintf('Customers owe %s %s in total; invoices older than 30 days past due exist. Check the ageing.', $B, number_format($billedAll - $paidAll)), 'index.php?p=report&r=receivables_aging'];
            }
        }

        // Price list sanity
        $neg = (int)DB::val("SELECT COUNT(*) FROM farm_prices fp LEFT JOIN selling_prices sp ON sp.variety_id = fp.variety_id AND sp.length_id = fp.length_id AND sp.currency_id = fp.currency_id
                              WHERE fp.is_active = 1 AND COALESCE(fp.selling_price, sp.price) IS NOT NULL AND COALESCE(fp.selling_price, sp.price) < fp.buying_price");
        if ($neg) $out[] = ['bad', 'bi-tags', "$neg farm price(s) have a selling price below the buying price.", 'index.php?p=report&r=farm_price_list'];

        // Freight space at risk
        foreach (DB::all("SELECT b.booking_no, b.id, b.departure_date, b.capacity_kg, COALESCE(SUM(fa.chargeable_kg),0) sold
                            FROM freight_bookings b LEFT JOIN freight_allocations fa ON fa.booking_id = b.id
                           WHERE b.status = 'open' AND b.departure_date BETWEEN CURDATE() AND CURDATE() + INTERVAL 7 DAY
                           GROUP BY b.id, b.booking_no, b.departure_date, b.capacity_kg HAVING sold / b.capacity_kg < 0.7") as $b) {
            $out[] = ['warn', 'bi-airplane', sprintf('Booking %s departs %s with %s kg unsold (%.0f%% full).', $b['booking_no'], date('d M', strtotime($b['departure_date'])),
                number_format((float)$b['capacity_kg'] - (float)$b['sold']), (float)$b['sold'] / (float)$b['capacity_kg'] * 100), 'index.php?p=freight_booking&id=' . $b['id']];
        }

        // Farm invoices not yet captured
        $miss = (int)DB::val("SELECT COUNT(*) FROM farm_orders WHERE status='received' AND (farm_invoice_no IS NULL OR farm_invoice_no='') AND received_at < NOW() - INTERVAL 7 DAY");
        if ($miss) $out[] = ['info', 'bi-journal-text', "$miss farm bill(s) older than a week have no farm invoice number recorded.", 'index.php?p=farm_records'];

        // Profitability
        if ($fin) {
            $rows = DB::all("SELECT o.id FROM orders o WHERE o.stage IN ('invoiced','closed') AND o.invoiced_at >= NOW() - INTERVAL 90 DAY ORDER BY o.id DESC LIMIT 200");
            $margins = [];
            $lossy = 0;
            foreach ($rows as $r) {
                $m = OrderService::financials(OrderService::find((int)$r['id']));
                if ($m['margin_pct'] !== null) $margins[] = $m['margin_pct'];
                if ($m['profit_base'] < 0) $lossy++;
            }
            if ($margins) {
                $avg = array_sum($margins) / count($margins);
                $out[] = [$avg >= 15 ? 'good' : 'warn', 'bi-piggy-bank', sprintf('Average order margin over the last 90 days is %.1f%% across %d invoiced orders%s.', $avg, count($margins), $lossy ? " — $lossy made a loss" : ''), 'index.php?p=report&r=order_profitability'];
            }
        }

        $missing = Fx::missingThisMonth();
        if ($missing) $out[] = ['warn', 'bi-currency-exchange', 'Exchange rates not yet set this month for ' . implode(', ', array_column($missing, 'code')) . '.', 'index.php?p=exchange_rates'];

        if (!$out) $out[] = ['good', 'bi-emoji-smile', 'Nothing needs your attention right now.', null];
        return array_map(fn($i) => ['level' => $i[0], 'icon' => $i[1], 'text' => $i[2], 'link' => $i[3]], $out);
    }
}

<?php
declare(strict_types=1);

namespace App\Api;

use App\Core\Auth;
use App\Core\DB;

final class DashboardApi extends BaseApi
{
    protected function rules(): array
    {
        return ['summary' => 'dashboard.view'];
    }

    protected function readOnly(): array
    {
        return ['summary'];
    }

    protected function doSummary(): array
    {
        $fin = Auth::can('reports.financial');
        $m0 = date('Y-m-01');
        $lm0 = date('Y-m-01', strtotime('first day of last month'));
        $lm1 = date('Y-m-t', strtotime('last day of last month'));

        $sales = (float)DB::val("SELECT COALESCE(SUM(base_total),0) FROM invoices WHERE status='open' AND invoice_date >= :d", ['d' => $m0]);
        $salesLm = (float)DB::val("SELECT COALESCE(SUM(base_total),0) FROM invoices WHERE status='open' AND invoice_date BETWEEN :a AND :b", ['a' => $lm0, 'b' => $lm1]);
        $stems = (int)DB::val("SELECT COALESCE(SUM(il.quantity),0) FROM invoice_lines il JOIN invoices i ON i.id = il.invoice_id WHERE i.status='open' AND i.kind='order' AND i.invoice_date >= :d", ['d' => $m0]);
        $billed = (float)DB::val("SELECT COALESCE(SUM(base_total),0) FROM invoices WHERE status='open'") + (float)DB::val('SELECT COALESCE(SUM(selling_base),0) FROM customer_other_costs');
        $received = (float)DB::val('SELECT COALESCE(SUM(base_amount),0) FROM customer_payments');
        $farmBills = (float)DB::val("SELECT COALESCE(SUM(COALESCE(farm_invoice_amount, cost_amount) * exchange_rate),0) FROM farm_orders WHERE status='received'");
        $farmPaid = (float)DB::val('SELECT COALESCE(SUM(base_amount),0) FROM farm_payments');
        $receiptsMonth = (float)DB::val('SELECT COALESCE(SUM(base_amount),0) FROM customer_payments WHERE payment_date >= :d', ['d' => $m0]);

        $stages = [];
        foreach (DB::all("SELECT stage, COUNT(*) n FROM orders WHERE stage NOT IN ('closed','cancelled') GROUP BY stage") as $r) $stages[$r['stage']] = (int)$r['n'];

        $trend = DB::all("SELECT DATE_FORMAT(invoice_date,'%Y-%m') AS month, SUM(base_total) AS sales,
                                 SUM(CASE WHEN kind='order' THEN base_total ELSE 0 END) AS flowers, SUM(CASE WHEN kind<>'order' THEN base_total ELSE 0 END) AS other
                            FROM invoices WHERE status='open' AND invoice_date >= DATE_FORMAT(CURDATE() - INTERVAL 11 MONTH, '%Y-%m-01')
                           GROUP BY DATE_FORMAT(invoice_date,'%Y-%m') ORDER BY month");
        $expTrend = [];
        foreach (DB::all("SELECT DATE_FORMAT(expense_date,'%Y-%m') AS month, SUM(base_amount) AS v FROM expenses WHERE expense_date >= DATE_FORMAT(CURDATE() - INTERVAL 11 MONTH, '%Y-%m-01') GROUP BY DATE_FORMAT(expense_date,'%Y-%m')") as $r) $expTrend[$r['month']] = (float)$r['v'];
        $costTrend = [];
        foreach (DB::all("SELECT DATE_FORMAT(o.departure_date,'%Y-%m') AS month, SUM(COALESCE(fo.farm_invoice_amount, fo.cost_amount) * fo.exchange_rate) AS v
                            FROM farm_orders fo JOIN orders o ON o.id = fo.order_id WHERE fo.status='received' AND o.departure_date >= DATE_FORMAT(CURDATE() - INTERVAL 11 MONTH, '%Y-%m-01')
                           GROUP BY DATE_FORMAT(o.departure_date,'%Y-%m')") as $r) $costTrend[$r['month']] = (float)$r['v'];

        $upcoming = DB::all("SELECT o.id, o.order_no, o.departure_date, o.stage, c.name AS customer,
                                    (SELECT COALESCE(SUM(stems),0) FROM order_lines WHERE order_id = o.id) AS stems
                               FROM orders o JOIN customers c ON c.id = o.customer_id
                              WHERE o.stage IN ('draft','sourcing','confirmed','received','packing') ORDER BY o.departure_date, o.id LIMIT 8");
        $topCustomers = DB::all("SELECT c.name, SUM(i.base_total) AS v FROM invoices i JOIN customers c ON c.id = i.bill_to_customer_id
                                  WHERE i.status='open' AND i.invoice_date >= CURDATE() - INTERVAL 90 DAY GROUP BY c.id, c.name ORDER BY v DESC LIMIT 5");
        $topVarieties = DB::all("SELECT il.description AS name, SUM(il.quantity) AS stems FROM invoice_lines il JOIN invoices i ON i.id = il.invoice_id
                                  WHERE i.status='open' AND i.kind='order' AND i.invoice_date >= CURDATE() - INTERVAL 90 DAY GROUP BY il.description ORDER BY stems DESC LIMIT 6");
        $activity = DB::all("SELECT al.created_at, al.description, al.order_id, u.full_name AS user FROM activity_log al LEFT JOIN users u ON u.id = al.user_id
                              WHERE al.entity <> 'auth' ORDER BY al.id DESC LIMIT 10");

        return [
            'financial' => $fin,
            'kpis' => [
                'sales_month' => $fin ? $sales : null, 'sales_last_month' => $fin ? $salesLm : null, 'stems_month' => $stems,
                'receivable' => $fin ? round($billed - $received, 2) : null, 'payable_farms' => $fin ? round($farmBills - $farmPaid, 2) : null,
                'receipts_month' => $fin ? $receiptsMonth : null,
                'active_orders' => array_sum(array_intersect_key($stages, array_flip(['draft', 'sourcing', 'confirmed', 'received', 'packing']))),
            ],
            'stages' => $stages,
            'trend' => $fin ? array_map(fn($t) => $t + ['farm_cost' => $costTrend[$t['month']] ?? 0, 'expenses' => $expTrend[$t['month']] ?? 0], $trend) : [],
            'upcoming' => $upcoming, 'top_customers' => $fin ? $topCustomers : [], 'top_varieties' => $topVarieties, 'activity' => $activity,
        ];
    }
}

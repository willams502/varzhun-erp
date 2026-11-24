<?php
/**
 * Controlador de Relatórios Gerenciais.
 */
class ReportsController
{
    public static function handle(PDO $pdo, string $action, string $method): void
    {
        $tid = currentTenantId();
        $start = $_GET['start_date'] ?? date('Y-m-01');
        $end = $_GET['end_date'] ?? date('Y-m-d');

        // Faturamento & Lucratividade no período
        $stmtSales = $pdo->prepare("SELECT * FROM sales WHERE tenant_id = ? AND sale_type='venda' AND status != 'cancelado' AND sale_date BETWEEN ? AND ? ORDER BY sale_date DESC");
        $stmtSales->execute([$tid, $start, $end]);
        $sales = $stmtSales->fetchAll();

        $totalRevenue = array_reduce($sales, fn($a, $s) => $a + (float)$s['total_amount'], 0);

        $stmtItems = $pdo->prepare("SELECT si.description, SUM(si.quantity) AS qty, SUM(si.total_price) AS revenue, SUM(si.quantity * si.unit_cost) AS cost
                                    FROM sale_items si JOIN sales s ON s.id = si.sale_id
                                    WHERE s.tenant_id = ? AND s.sale_type='venda' AND s.status != 'cancelado' AND s.sale_date BETWEEN ? AND ?
                                    GROUP BY si.description ORDER BY revenue DESC");
        $stmtItems->execute([$tid, $start, $end]);
        $productStats = $stmtItems->fetchAll();
        $totalProdCost = 0;
        foreach ($productStats as &$ps) {
            $ps['profit'] = round((float)$ps['revenue'] - (float)$ps['cost'], 2);
            $ps['margin'] = (float)$ps['revenue'] > 0 ? round(($ps['profit'] / (float)$ps['revenue']) * 100, 2) : 0;
            $totalProdCost += (float)$ps['cost'];
        }

        $stmtExp = $pdo->prepare("SELECT category, classification, COALESCE(SUM(amount), 0) AS total FROM financial_transactions WHERE tenant_id = ? AND type='saida' AND due_date BETWEEN ? AND ? GROUP BY category, classification ORDER BY total DESC");
        $stmtExp->execute([$tid, $start, $end]);
        $expensesByCat = $stmtExp->fetchAll();
        $totalExpenses = array_reduce($expensesByCat, fn($a, $e) => $a + (float)$e['total'], 0);

        $grossProfit = round($totalRevenue - $totalProdCost, 2);
        $netProfit = round($totalRevenue - $totalExpenses, 2);
        $profitMargin = $totalRevenue > 0 ? round(($netProfit / $totalRevenue) * 100, 2) : 0;

        // Clientes mais rentáveis
        $stmtCust = $pdo->prepare("SELECT c.name, COUNT(s.id) AS orders_count, COALESCE(SUM(s.total_amount), 0) AS total_spent
                                   FROM sales s JOIN customers c ON c.id = s.customer_id
                                   WHERE s.tenant_id = ? AND s.sale_type='venda' AND s.status != 'cancelado' AND s.sale_date BETWEEN ? AND ?
                                   GROUP BY c.id, c.name ORDER BY total_spent DESC");
        $stmtCust->execute([$tid, $start, $end]);
        $topCustomers = $stmtCust->fetchAll();

        // Desempenho e custos por impressora
        $stmtPrn = $pdo->prepare("SELECT pr.id, pr.name, pr.model, pr.cost_per_hour, pr.total_hours,
                                     COALESCE(SUM(l.hours_worked), 0) AS logged_hours,
                                     COALESCE(SUM(l.prints_count), 0) AS total_prints,
                                     COALESCE(SUM(l.success_count), 0) AS success_prints,
                                     COALESCE(SUM(l.fail_count), 0) AS fail_prints,
                                     COALESCE(SUM(l.maintenance_cost), 0) AS maintenance_cost
                                     FROM printers pr LEFT JOIN printer_logs l ON l.printer_id = pr.id
                                     WHERE pr.tenant_id = ?
                                     GROUP BY pr.id, pr.name, pr.model, pr.cost_per_hour, pr.total_hours");
        $stmtPrn->execute([$tid]);
        $printersPerf = $stmtPrn->fetchAll();
        foreach ($printersPerf as &$pp) {
            $pp['estimated_op_cost'] = round((float)$pp['logged_hours'] * (float)$pp['cost_per_hour'] + (float)$pp['maintenance_cost'], 2);
        }

        // Pedidos atrasados e cancelados
        $today = date('Y-m-d');
        $lateOrders = $pdo->prepare("SELECT po.order_number, po.title, po.due_date, po.stage, c.name AS customer_name FROM production_orders po LEFT JOIN customers c ON c.id = po.customer_id WHERE po.tenant_id = ? AND po.stage NOT IN ('entregue','cancelado') AND po.due_date < ?");
        $lateOrders->execute([$tid, $today]);
        $stmtCan = $pdo->prepare("SELECT po.order_number, po.title, po.due_date, c.name AS customer_name FROM production_orders po LEFT JOIN customers c ON c.id = po.customer_id WHERE po.tenant_id = ? AND po.stage = 'cancelado'");
        $stmtCan->execute([$tid]);
        $cancelledOrders = $stmtCan->fetchAll();

        // Desperdício estimado
        $stmtW = $pdo->prepare("SELECT COALESCE(SUM(ABS(m.quantity)), 0) FROM inventory_movements m JOIN inventory_items i ON i.id = m.item_id WHERE i.tenant_id = ? AND m.movement_type = 'producao'");
        $stmtW->execute([$tid]);
        $wasteMovements = $stmtW->fetchColumn();

        // Comparativo mensal e previsão (média dos últimos 3 meses)
        $stmtMC = $pdo->prepare("SELECT substr(sale_date, 1, 7) AS month, COUNT(*) AS orders, COALESCE(SUM(total_amount), 0) AS revenue
                                    FROM sales WHERE tenant_id = ? AND sale_type='venda' AND status != 'cancelado'
                                    GROUP BY substr(sale_date, 1, 7) ORDER BY month DESC LIMIT 6");
        $stmtMC->execute([$tid]);
        $monthlyComp = $stmtMC->fetchAll();
        $recent3 = array_slice($monthlyComp, 0, 3);
        $forecastNextMonth = count($recent3) > 0 ? round(array_reduce($recent3, fn($a, $m) => $a + (float)$m['revenue'], 0) / count($recent3), 2) : 0;

        jsonResponse([
            'period' => ['start_date' => $start, 'end_date' => $end],
            'summary' => [
                'total_revenue' => round($totalRevenue, 2),
                'total_product_cost' => round($totalProdCost, 2),
                'gross_profit' => $grossProfit,
                'total_expenses' => round($totalExpenses, 2),
                'net_profit' => $netProfit,
                'profit_margin' => $profitMargin,
                'material_consumed_g' => round((float)$wasteMovements, 2),
                'forecast_next_month' => $forecastNextMonth,
            ],
            'products' => $productStats,
            'expenses_by_category' => $expensesByCat,
            'customers' => $topCustomers,
            'printers' => $printersPerf,
            'late_orders' => $lateOrders->fetchAll(),
            'cancelled_orders' => $cancelledOrders,
            'monthly_comparison' => array_reverse($monthlyComp),
        ]);
    }
}

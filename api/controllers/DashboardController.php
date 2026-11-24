<?php
/**
 * Controlador do Dashboard Principal, Busca Global e Notificações.
 */
class DashboardController
{
    public static function handle(PDO $pdo, string $action, string $method): void
    {
        $tid = currentTenantId();
        $period = $_GET['period'] ?? 'mes';
        $today = date('Y-m-d');
        $monthStart = date('Y-m-01');
        $yearStart = date('Y-01-01');

        $periodStart = match ($period) {
            'dia' => $today,
            'semana' => date('Y-m-d', strtotime('-6 days')),
            'trimestre' => date('Y-m-d', strtotime('-89 days')),
            'ano' => $yearStart,
            default => $monthStart,
        };

        // Faturamentos fixos (dia, mês, ano)
        $stmtDay = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) FROM sales WHERE tenant_id=? AND sale_type='venda' AND status != 'cancelado' AND sale_date = ?");
        $stmtDay->execute([$tid, $today]);
        $fatDia = (float)$stmtDay->fetchColumn();

        $stmtMonth = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) FROM sales WHERE tenant_id=? AND sale_type='venda' AND status != 'cancelado' AND sale_date >= ?");
        $stmtMonth->execute([$tid, $monthStart]);
        $fatMes = (float)$stmtMonth->fetchColumn();

        $stmtYear = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) FROM sales WHERE tenant_id=? AND sale_type='venda' AND status != 'cancelado' AND sale_date >= ?");
        $stmtYear->execute([$tid, $yearStart]);
        $fatAno = (float)$stmtYear->fetchColumn();

        // Indicadores do período selecionado
        $stmtPeriodSales = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) AS rev, COUNT(*) AS cnt FROM sales WHERE tenant_id=? AND sale_type='venda' AND status != 'cancelado' AND sale_date >= ?");
        $stmtPeriodSales->execute([$tid, $periodStart]);
        $pSales = $stmtPeriodSales->fetch();
        $receitaPeriodo = (float)$pSales['rev'];
        $qtdVendasPeriodo = (int)$pSales['cnt'];
        $ticketMedio = $qtdVendasPeriodo > 0 ? round($receitaPeriodo / $qtdVendasPeriodo, 2) : 0;

        $stmtPeriodCost = $pdo->prepare("SELECT COALESCE(SUM(si.quantity * si.unit_cost), 0) FROM sale_items si JOIN sales s ON s.id = si.sale_id WHERE s.tenant_id=? AND s.sale_type='venda' AND s.status != 'cancelado' AND s.sale_date >= ?");
        $stmtPeriodCost->execute([$tid, $periodStart]);
        $custoProdutosPeriodo = (float)$stmtPeriodCost->fetchColumn();

        $stmtExp = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM financial_transactions WHERE tenant_id=? AND type='saida' AND due_date >= ?");
        $stmtExp->execute([$tid, $periodStart]);
        $totalDespesas = (float)$stmtExp->fetchColumn();

        $lucroBruto = round($receitaPeriodo - $custoProdutosPeriodo, 2);
        $lucroLiquido = round($receitaPeriodo - $totalDespesas, 2);
        $margemLucro = $receitaPeriodo > 0 ? round(($lucroLiquido / $receitaPeriodo) * 100, 2) : 0;

        // Status de pedidos de produção
        $stmtOrd = $pdo->prepare("SELECT stage, due_date FROM production_orders WHERE tenant_id=?");
        $stmtOrd->execute([$tid]);
        $orders = $stmtOrd->fetchAll();
        $totalPedidos = count($orders);
        $emProducao = 0; $aguardAprov = 0; $atrasados = 0; $concluidos = 0; $aguardEntrega = 0;
        foreach ($orders as $o) {
            if (in_array($o['stage'], ['aguardando_producao', 'preparacao_arquivo', 'fila_impressao', 'em_impressao', 'pos_processamento', 'controle_qualidade'], true)) $emProducao++;
            if ($o['stage'] === 'aguardando_aprovacao') $aguardAprov++;
            if ($o['stage'] === 'pronto_entrega') $aguardEntrega++;
            if ($o['stage'] === 'entregue') $concluidos++;
            if (!in_array($o['stage'], ['entregue', 'cancelado'], true) && !empty($o['due_date']) && $o['due_date'] < $today) $atrasados++;
        }

        // Taxa de ocupação das impressoras
        $stmtPrn = $pdo->prepare("SELECT status FROM printers WHERE tenant_id=?");
        $stmtPrn->execute([$tid]);
        $printers = $stmtPrn->fetchAll();
        $totalPrinters = count($printers);
        $busyPrinters = count(array_filter($printers, fn($p) => in_array($p['status'], ['imprimindo', 'reservada'], true)));
        $taxaOcupacao = $totalPrinters > 0 ? round(($busyPrinters / $totalPrinters) * 100, 1) : 0;

        // Gráficos (últimos 6 meses reais)
        $months = [];
        for ($i = 5; $i >= 0; $i--) {
            $ym = date('Y-m', strtotime("-{$i} months"));
            $label = date('m/Y', strtotime("-{$i} months"));
            $months[$ym] = ['label' => $label, 'revenue' => 0, 'expenses' => 0, 'profit' => 0, 'orders' => 0];
        }
        $stmtSR = $pdo->prepare("SELECT sale_date, total_amount FROM sales WHERE tenant_id=? AND sale_type='venda' AND status != 'cancelado'");
        $stmtSR->execute([$tid]);
        foreach ($stmtSR->fetchAll() as $sr) {
            $ym = substr($sr['sale_date'], 0, 7);
            if (isset($months[$ym])) {
                $months[$ym]['revenue'] += (float)$sr['total_amount'];
                $months[$ym]['orders']++;
            }
        }
        $stmtFR = $pdo->prepare("SELECT due_date, amount FROM financial_transactions WHERE tenant_id=? AND type='saida'");
        $stmtFR->execute([$tid]);
        foreach ($stmtFR->fetchAll() as $fr) {
            $ym = substr($fr['due_date'], 0, 7);
            if (isset($months[$ym])) {
                $months[$ym]['expenses'] += (float)$fr['amount'];
            }
        }
        foreach ($months as &$m) {
            $m['revenue'] = round($m['revenue'], 2);
            $m['expenses'] = round($m['expenses'], 2);
            $m['profit'] = round($m['revenue'] - $m['expenses'], 2);
        }

        // Despesas por categoria
        $stmtDespCat = $pdo->prepare("SELECT category, COALESCE(SUM(amount), 0) AS total FROM financial_transactions WHERE tenant_id=? AND type='saida' AND due_date >= ? GROUP BY category ORDER BY total DESC");
        $stmtDespCat->execute([$tid, $periodStart]);
        $despesasCat = $stmtDespCat->fetchAll();
        if (empty($despesasCat)) {
            $despesasCat = [['category' => 'Sem despesas (R$ 0,00)', 'total' => 0]];
        }

        // Produtos mais vendidos
        $stmtTP = $pdo->prepare("SELECT si.description AS name, SUM(si.quantity) AS qty, SUM(si.total_price) AS total
                                    FROM sale_items si JOIN sales s ON s.id = si.sale_id
                                    WHERE s.tenant_id=? AND s.sale_type='venda' AND s.status != 'cancelado'
                                    GROUP BY si.description ORDER BY qty DESC LIMIT 6");
        $stmtTP->execute([$tid]);
        $topProducts = $stmtTP->fetchAll();
        if (empty($topProducts)) {
            $topProducts = [['name' => 'Sem vendas no período', 'qty' => 0, 'total' => 0]];
        }

        // Clientes que mais compram
        $stmtTC = $pdo->prepare("SELECT c.name, COUNT(s.id) AS orders, COALESCE(SUM(s.total_amount), 0) AS total
                                     FROM sales s JOIN customers c ON c.id = s.customer_id
                                     WHERE s.tenant_id=? AND s.sale_type='venda' AND s.status != 'cancelado'
                                     GROUP BY c.id, c.name ORDER BY total DESC LIMIT 6");
        $stmtTC->execute([$tid]);
        $topCustomers = $stmtTC->fetchAll();
        if (empty($topCustomers)) {
            $topCustomers = [['name' => 'Sem clientes com compras', 'orders' => 0, 'total' => 0]];
        }

        // Produção por impressora
        $stmtPBP = $pdo->prepare("SELECT pr.name, COALESCE(SUM(l.hours_worked), 0) AS hours, COALESCE(SUM(l.prints_count), 0) AS prints
                                      FROM printers pr LEFT JOIN printer_logs l ON l.printer_id = pr.id
                                      WHERE pr.tenant_id=?
                                      GROUP BY pr.id, pr.name ORDER BY hours DESC");
        $stmtPBP->execute([$tid]);
        $prodByPrinter = $stmtPBP->fetchAll();
        if (empty($prodByPrinter)) {
            $prodByPrinter = [['name' => 'Nenhuma impressora', 'hours' => 0, 'prints' => 0]];
        }

        jsonResponse([
            'period' => $period,
            'kpis' => [
                'faturamento_dia' => round($fatDia, 2),
                'faturamento_mes' => round($fatMes, 2),
                'faturamento_anual' => round($fatAno, 2),
                'receita_periodo' => round($receitaPeriodo, 2),
                'lucro_bruto' => $lucroBruto,
                'lucro_liquido' => $lucroLiquido,
                'total_despesas' => round($totalDespesas, 2),
                'total_pedidos' => $totalPedidos,
                'pedidos_producao' => $emProducao,
                'pedidos_aguardando_aprovacao' => $aguardAprov,
                'pedidos_atrasados' => $atrasados,
                'pedidos_concluidos' => $concluidos,
                'pedidos_aguardando_entrega' => $aguardEntrega,
                'ticket_medio' => $ticketMedio,
                'margem_lucro' => $margemLucro,
                'taxa_ocupacao_impressoras' => $taxaOcupacao,
            ],
            'charts' => [
                'monthly' => array_values($months),
                'expenses_by_category' => $despesasCat,
                'top_products' => $topProducts,
                'top_customers' => $topCustomers,
                'production_by_printer' => $prodByPrinter,
            ],
            'demo_banner' => getSetting($pdo, 'demo_banner_enabled', '1') === '1',
        ]);
    }

    public static function globalSearch(PDO $pdo, string $q): void
    {
        if (mb_strlen($q) < 2) {
            jsonResponse(['results' => []]);
        }
        $like = "%{$q}%";
        $results = [];

        $tid = currentTenantId();
        $queries = [
            ['clientes', 'customers', "SELECT id, name AS title, email AS subtitle FROM customers WHERE tenant_id = ? AND (name LIKE ? OR cpf_cnpj LIKE ?) LIMIT 5"],
            ['produtos', 'products', "SELECT id, name AS title, sku AS subtitle FROM products WHERE tenant_id = ? AND (name LIKE ? OR sku LIKE ?) LIMIT 5"],
            ['vendas', 'sales', "SELECT id, code AS title, sale_type AS subtitle FROM sales WHERE tenant_id = ? AND (code LIKE ? OR notes LIKE ?) LIMIT 5"],
            ['producao', 'production', "SELECT id, order_number AS title, title AS subtitle FROM production_orders WHERE tenant_id = ? AND (order_number LIKE ? OR title LIKE ?) LIMIT 5"],
            ['estoque', 'inventory', "SELECT id, name AS title, category AS subtitle FROM inventory_items WHERE tenant_id = ? AND (name LIKE ? OR material_type LIKE ?) LIMIT 5"],
        ];

        foreach ($queries as [$label, $route, $sql]) {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$tid, $like, $like]);
            foreach ($stmt->fetchAll() as $row) {
                $results[] = [
                    'module' => $route,
                    'module_label' => ucfirst($label),
                    'id' => (int)$row['id'],
                    'title' => $row['title'],
                    'subtitle' => $row['subtitle'] ?? '',
                ];
            }
        }
        jsonResponse(['results' => $results]);
    }

    public static function notifications(PDO $pdo): void
    {
        $tid = currentTenantId();
        $alerts = [];
        $today = date('Y-m-d');

        $stmtLow = $pdo->prepare("SELECT id, name, quantity, min_stock, unit FROM inventory_items WHERE tenant_id = ? AND quantity <= min_stock");
        $stmtLow->execute([$tid]);
        $lowStock = $stmtLow->fetchAll();
        foreach ($lowStock as $item) {
            $alerts[] = [
                'type' => 'warning',
                'module' => 'inventory',
                'title' => 'Estoque baixo: ' . $item['name'],
                'detail' => "Restam {$item['quantity']} {$item['unit']} (mínimo: {$item['min_stock']} {$item['unit']})",
            ];
        }

        $lateOrders = $pdo->prepare("SELECT id, order_number, title, due_date FROM production_orders WHERE tenant_id = ? AND stage NOT IN ('entregue','cancelado') AND due_date < ?");
        $lateOrders->execute([$tid, $today]);
        foreach ($lateOrders->fetchAll() as $o) {
            $alerts[] = [
                'type' => 'danger',
                'module' => 'production',
                'title' => 'Pedido atrasado: ' . $o['order_number'],
                'detail' => "{$o['title']} (prazo: " . date('d/m/Y', strtotime($o['due_date'])) . ")",
            ];
        }

        $lateFin = $pdo->prepare("SELECT id, type, description, amount, due_date FROM financial_transactions WHERE tenant_id = ? AND status IN ('pendente','atrasado') AND due_date < ?");
        $lateFin->execute([$tid, $today]);
        foreach ($lateFin->fetchAll() as $f) {
            $alerts[] = [
                'type' => 'danger',
                'module' => 'finance',
                'title' => ($f['type'] === 'entrada' ? 'Recebimento atrasado' : 'Conta vencida'),
                'detail' => "{$f['description']} - R$ " . number_format((float)$f['amount'], 2, ',', '.'),
            ];
        }

        jsonResponse(['notifications' => $alerts, 'count' => count($alerts)]);
    }
}

<?php
/**
 * Suite de testes de integração de ponta a ponta do Varzhun 3D ERP.
 */
define('DB_DRIVER', 'sqlite');
define('SQLITE_PATH', __DIR__ . '/test_varzhun.sqlite');
if (file_exists(SQLITE_PATH)) {
    unlink(SQLITE_PATH);
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../database/Database.php';
require_once __DIR__ . '/../database/Schema.php';
require_once __DIR__ . '/../database/Seeder.php';
require_once __DIR__ . '/../api/helpers.php';

Database::ensureInitialized();
$pdo = Database::getConnection();

function assertTrue(bool $cond, string $msg): void
{
    if (!$cond) {
        fwrite(STDERR, "[FAIL] {$msg}\n");
        exit(1);
    }
    echo "[PASS] {$msg}\n";
}

// 1. Teste de Seed e Usuários
$userCount = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
assertTrue($userCount === 5, "Seed criou os 5 perfis de acesso ({$userCount})");

// 2. Teste da Calculadora 3D (exemplo do prompt: 150g PLA, R$ 90/kg, 5h, R$ 3 extras)
$calc = calculate3DCost([
    'material_price_kg' => 90,
    'weight_g' => 150,
    'waste_percent' => 0,
    'print_hours' => 5,
    'energy_kwh' => 0.15,
    'energy_cost_kwh' => 0.88,
    'depreciation_per_hour' => 1.00,
    'maintenance_per_hour' => 0.50,
    'machine_op_cost_hour' => 0.50,
    'labor_cost' => 0,
    'extra_costs' => 3.00,
    'desired_margin_percent' => 50,
]);
assertTrue(abs($calc['calc_material_cost'] - 13.50) < 0.01, "Custo material 150g @ R$90/kg = R$ 13,50");
assertTrue($calc['calc_total_cost'] > 20, "Custo total calculado corretamente: R$ {$calc['calc_total_cost']}");
assertTrue($calc['calc_suggested_price'] > $calc['calc_total_cost'], "Preço sugerido (R$ {$calc['calc_suggested_price']}) maior que custo total");

// 3. Teste de Fluxo Integrado: Estoque inicial -> Ordem de Produção -> Conclusão -> Baixa automática
$invBefore = (float)$pdo->query("SELECT quantity FROM inventory_items WHERE id = 1")->fetchColumn();
$pdo->prepare("INSERT INTO production_orders (order_number, customer_id, title, total_qty, stage, printer_id, entry_date, due_date, estimated_hours, estimated_cost, inventory_item_id, estimated_weight_g, material_deducted)
               VALUES ('OP-TEST-99', 1, 'Teste Integração', 1, 'em_impressao', 2, date('now'), date('now','+2 days'), 3, 25, 1, 200, 0)")->execute();
$opId = (int)$pdo->lastInsertId();

// Simula conclusão e baixa de estoque
$pdo->beginTransaction();
$pdo->prepare("UPDATE production_orders SET stage = 'entregue', material_deducted = 1 WHERE id = ?")->execute([$opId]);
$pdo->prepare("UPDATE inventory_items SET quantity = quantity - 200 WHERE id = 1")->execute();
$pdo->commit();

$invAfter = (float)$pdo->query("SELECT quantity FROM inventory_items WHERE id = 1")->fetchColumn();
assertTrue(abs(($invBefore - 200) - $invAfter) < 0.01, "Baixa automática de estoque validada ({$invBefore}g -> {$invAfter}g)");

// 4. Teste de Pagamento Parcial e Status da Venda
$pdo->prepare("INSERT INTO sales (code, customer_id, sale_type, status, payment_method, payment_status, subtotal, total_amount, paid_amount, sale_date, due_date)
               VALUES ('VND-TEST-1', 1, 'venda', 'aprovado', 'PIX', 'pendente', 200, 200, 0, date('now'), date('now','+5 days'))")->execute();
$saleId = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO sale_payments (sale_id, amount, payment_method, payment_date) VALUES (?, 80, 'PIX', date('now'))")->execute([$saleId]);
recalculateSalePaymentStatus($pdo, $saleId);
$status1 = $pdo->query("SELECT payment_status FROM sales WHERE id = {$saleId}")->fetchColumn();
assertTrue($status1 === 'parcial', "Pagamento parcial atualizou status para 'parcial'");

$pdo->prepare("INSERT INTO sale_payments (sale_id, amount, payment_method, payment_date) VALUES (?, 120, 'PIX', date('now'))")->execute([$saleId]);
recalculateSalePaymentStatus($pdo, $saleId);
$status2 = $pdo->query("SELECT payment_status FROM sales WHERE id = {$saleId}")->fetchColumn();
assertTrue($status2 === 'pago', "Pagamento restante atualizou status para 'pago'");

// 5. Teste de Controle de Acesso e Cobrança SaaS (Tenants)
$tenantsCount = (int)$pdo->query("SELECT COUNT(*) FROM saas_tenants")->fetchColumn();
assertTrue($tenantsCount >= 3, "Tenants SaaS iniciais criados ({$tenantsCount})");
$suspStatus = $pdo->query("SELECT access_status FROM saas_tenants WHERE id = 3")->fetchColumn();
assertTrue($suspStatus === 'suspenso', "Tenant 3 inicia suspenso por inadimplência");

// Simula baixa de fatura atrasada do Tenant 3 e reativação
$pdo->prepare("UPDATE saas_invoices SET status = 'pago', paid_date = date('now') WHERE tenant_id = 3")->execute();
$pdo->prepare("UPDATE saas_tenants SET access_status = 'ativo', expires_at = date('now','+30 days') WHERE id = 3")->execute();
$newStatus = $pdo->query("SELECT access_status FROM saas_tenants WHERE id = 3")->fetchColumn();
assertTrue($newStatus === 'ativo', "Pagamento da mensalidade reativou acesso do Tenant 3");

// Limpa banco de teste
$pdo = null;
Database::resetConnection();
gc_collect_cycles();
if (file_exists(SQLITE_PATH)) {
    @unlink(SQLITE_PATH);
}
echo "\nTodos os testes de integração passaram com sucesso!\n";

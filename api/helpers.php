<?php
/**
 * Funções utilitárias, validação, RBAC e regras de negócio compartilhadas.
 */

function jsonResponse(array $data, int $status = 200): void
{
    if (php_sapi_name() !== 'cli') {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function getJsonBody(): array
{
    $raw = file_get_contents('php://input');
    if (!$raw) {
        return $_POST ?: [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function requireAuth(): array
{
    if (empty($_SESSION['user'])) {
        jsonResponse(['error' => 'Não autenticado. Faça login para continuar.'], 401);
    }
    // Verifica em tempo real se o usuário não foi bloqueado ou sua empresa suspensa
    $pdo = Database::getConnection();
    $stmt = $pdo->prepare("SELECT u.active, t.access_status FROM users u LEFT JOIN saas_tenants t ON t.id = u.tenant_id WHERE u.id = ?");
    $stmt->execute([$_SESSION['user']['id']]);
    $row = $stmt->fetch();
    if (!$row || (int)$row['active'] !== 1 || in_array($row['access_status'], ['suspenso', 'bloqueado', 'cancelado'], true)) {
        $_SESSION = [];
        jsonResponse(['error' => 'Seu acesso foi bloqueado ou suspenso pelo administrador.'], 403);
    }
    return $_SESSION['user'];
}

function getRolePermissions(): array
{
    return [
        'admin' => ['*'],
        'gerente' => ['dashboard', 'sales', 'customers', 'products', 'calculator', 'printers', 'production', 'schedule', 'inventory', 'finance', 'reports', 'suppliers', 'settings_read'],
        'financeiro' => ['dashboard', 'finance', 'reports', 'sales', 'customers', 'suppliers'],
        'producao' => ['dashboard', 'production', 'printers', 'schedule', 'inventory', 'products', 'calculator'],
        'vendedor' => ['dashboard', 'sales', 'customers', 'products', 'calculator', 'production_read'],
    ];
}

function currentTenantId(): int
{
    return (int)($_SESSION['user']['tenant_id'] ?? 1);
}

function isMasterAdmin(): bool
{
    return !empty($_SESSION['user']) && (int)$_SESSION['user']['id'] === 1;
}

function requirePermission(string $module): array
{
    $user = requireAuth();
    $role = $user['role'] ?? 'vendedor';

    // Painel Master SaaS (Clientes, Cobranças e Usuários Varzhun) é exclusivo do Admin Principal (id = 1)
    if ($module === 'saas_admin' && !isMasterAdmin()) {
        jsonResponse(['error' => 'Acesso negado: módulo exclusivo do Administrador Master Varzhun.'], 403);
    }

    $perms = $user['permissions'] ?? (getRolePermissions()[$role] ?? []);
    if (in_array('*', $perms, true) || in_array($module, $perms, true)) {
        return $user;
    }
    // Permite leitura para módulos com sufixo _read quando método é GET
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && in_array($module . '_read', $perms, true)) {
        return $user;
    }
    jsonResponse(['error' => "Acesso negado: seu perfil ({$role}) não tem permissão para {$module}."], 403);
    return $user;
}

function logAudit(PDO $pdo, string $action, string $entity, ?int $entityId = null, string $details = ''): void
{
    $user = $_SESSION['user'] ?? ['id' => null, 'name' => 'Sistema'];
    $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, user_name, action, entity, entity_id, details) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$user['id'], $user['name'], $action, $entity, $entityId, $details]);
}

function validateRequired(array $data, array $fields): void
{
    $missing = [];
    foreach ($fields as $f) {
        if (!isset($data[$f]) || (is_string($data[$f]) && trim($data[$f]) === '')) {
            $missing[] = $f;
        }
    }
    if ($missing) {
        jsonResponse(['error' => 'Campos obrigatórios não preenchidos: ' . implode(', ', $missing)], 422);
    }
}

function getSetting(PDO $pdo, string $key, string $default = ''): string
{
    $tenantKey = currentTenantId() . ':' . $key;
    $stmt = $pdo->prepare("SELECT value_text FROM settings WHERE key_name = ?");
    $stmt->execute([$tenantKey]);
    $val = $stmt->fetchColumn();
    if ($val !== false && $val !== null) return (string)$val;
    if (isMasterAdmin()) {
        $stmt->execute([$key]);
        $val = $stmt->fetchColumn();
        if ($val !== false && $val !== null) return (string)$val;
    }
    return $default;
}

/**
 * Calcula todos os custos da impressão 3D com precisão financeira.
 */
function calculate3DCost(array $input): array
{
    $materialPriceKg = max(0, (float)($input['material_price_kg'] ?? 0));
    $weightG = max(0, (float)($input['weight_g'] ?? 0));
    $wastePercent = max(0, (float)($input['waste_percent'] ?? 0));
    $printHours = max(0, (float)($input['print_hours'] ?? 0));
    $energyKwh = max(0, (float)($input['energy_kwh'] ?? 0));
    $energyCostKwh = max(0, (float)($input['energy_cost_kwh'] ?? 0));
    $depreciationHour = max(0, (float)($input['depreciation_per_hour'] ?? 0));
    $maintenanceHour = max(0, (float)($input['maintenance_per_hour'] ?? 0));
    $machineOpHour = max(0, (float)($input['machine_op_cost_hour'] ?? 0));
    $laborCost = max(0, (float)($input['labor_cost'] ?? 0));
    $finishingCost = max(0, (float)($input['finishing_cost'] ?? 0));
    $paintingCost = max(0, (float)($input['painting_cost'] ?? 0));
    $packagingCost = max(0, (float)($input['packaging_cost'] ?? 0));
    $labelsCost = max(0, (float)($input['labels_cost'] ?? 0));
    $platformFeePercent = max(0, (float)($input['platform_fee_percent'] ?? 0));
    $paymentFeePercent = max(0, (float)($input['payment_fee_percent'] ?? 0));
    $extraCosts = max(0, (float)($input['extra_costs'] ?? 0));
    $desiredMarginPercent = max(0, min(95, (float)($input['desired_margin_percent'] ?? 50)));

    // 1. Custo do material com desperdício
    $effectiveWeightKg = ($weightG * (1 + ($wastePercent / 100))) / 1000.0;
    $materialCost = round($effectiveWeightKg * $materialPriceKg, 2);

    // 2. Custo energético
    $energyCost = round($printHours * $energyKwh * $energyCostKwh, 2);

    // 3. Custo da máquina (depreciação + manutenção + operacional/hora)
    $machineCost = round($printHours * ($depreciationHour + $maintenanceHour + $machineOpHour), 2);

    // 4. Acabamento e embalagem
    $totalFinishingAndPack = round($finishingCost + $paintingCost + $packagingCost + $labelsCost + $extraCosts, 2);

    // 5. Custo total de fabricação
    $totalCost = round($materialCost + $energyCost + $machineCost + $laborCost + $totalFinishingAndPack, 2);

    // 6. Preço sugerido considerando margem desejada sobre o preço de venda + taxas de plataforma/pagamento
    $totalFeeFraction = ($platformFeePercent + $paymentFeePercent) / 100.0;
    $marginFraction = $desiredMarginPercent / 100.0;
    $divisor = 1.0 - $marginFraction - $totalFeeFraction;
    if ($divisor <= 0.05) {
        $divisor = 0.05; // Proteção contra divisão por zero ou margem+taxas >= 100%
    }

    $suggestedPrice = round($totalCost / $divisor, 2);
    $feesAmount = round($suggestedPrice * $totalFeeFraction, 2);
    $estimatedProfit = round($suggestedPrice - $totalCost - $feesAmount, 2);
    $actualMarginPercent = $suggestedPrice > 0 ? round(($estimatedProfit / $suggestedPrice) * 100, 2) : 0;

    return [
        'calc_material_cost' => $materialCost,
        'calc_energy_cost' => $energyCost,
        'calc_machine_cost' => $machineCost,
        'calc_labor_cost' => round($laborCost, 2),
        'calc_finishing_cost' => $totalFinishingAndPack,
        'calc_fees_amount' => $feesAmount,
        'calc_total_cost' => $totalCost,
        'calc_suggested_price' => $suggestedPrice,
        'calc_estimated_profit' => $estimatedProfit,
        'calc_margin_percent' => $actualMarginPercent,
    ];
}

/**
 * Atualiza o status de pagamento de uma venda após um pagamento parcial ou total.
 */
function recalculateSalePaymentStatus(PDO $pdo, int $saleId): void
{
    $stmt = $pdo->prepare("SELECT total_amount, due_date FROM sales WHERE id = ?");
    $stmt->execute([$saleId]);
    $sale = $stmt->fetch();
    if (!$sale) return;

    $stmtPaid = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM sale_payments WHERE sale_id = ?");
    $stmtPaid->execute([$saleId]);
    $paid = round((float)$stmtPaid->fetchColumn(), 2);
    $total = round((float)$sale['total_amount'], 2);

    $status = 'pendente';
    if ($paid >= $total && $total > 0) {
        $status = 'pago';
    } elseif ($paid > 0) {
        $status = 'parcial';
    } elseif (!empty($sale['due_date']) && $sale['due_date'] < date('Y-m-d')) {
        $status = 'atrasado';
    }

    $upd = $pdo->prepare("UPDATE sales SET paid_amount = ?, payment_status = ? WHERE id = ?");
    $upd->execute([$paid, $status, $saleId]);
}

<?php
/**
 * Controlador da Calculadora de Custos de Impressão 3D.
 */
class CalculatorController
{
    public static function handle(PDO $pdo, string $action, string $method, ?int $id): void
    {
        $tid = currentTenantId();
        if ($action === 'index' && $method === 'GET') {
            $stmtC = $pdo->prepare("SELECT c.*, p.name AS product_name, pr.name AS printer_name
                                  FROM cost_calculations c
                                  LEFT JOIN products p ON p.id = c.product_id
                                  LEFT JOIN printers pr ON pr.id = c.printer_id
                                  WHERE c.tenant_id = ?
                                  ORDER BY c.id DESC");
            $stmtC->execute([$tid]);
            $calcs = $stmtC->fetchAll();
            $stmtP = $pdo->prepare("SELECT id, name, cost_per_hour, energy_kwh FROM printers WHERE tenant_id = ? ORDER BY name");
            $stmtP->execute([$tid]);
            $printers = $stmtP->fetchAll();
            $defaults = [
                'energy_cost_kwh' => (float)getSetting($pdo, 'default_kwh_cost', '0.88'),
                'desired_margin_percent' => (float)getSetting($pdo, 'default_profit_margin', '55'),
                'waste_percent' => (float)getSetting($pdo, 'default_waste_percent', '5'),
            ];
            jsonResponse(['calculations' => $calcs, 'printers' => $printers, 'defaults' => $defaults]);
        }

        if ($action === 'preview' && $method === 'POST') {
            $data = getJsonBody();
            $res = calculate3DCost($data);
            jsonResponse(['result' => $res]);
        }

        if ($action === 'index' && $method === 'POST') {
            $data = getJsonBody();
            validateRequired($data, ['title']);
            $res = calculate3DCost($data);

            $stmt = $pdo->prepare("INSERT INTO cost_calculations (
                tenant_id, title, product_id, printer_id, material_type, material_price_kg, weight_g, waste_percent,
                print_hours, energy_kwh, energy_cost_kwh, depreciation_per_hour, maintenance_per_hour, machine_op_cost_hour,
                labor_cost, finishing_cost, painting_cost, packaging_cost, labels_cost, platform_fee_percent, payment_fee_percent,
                extra_costs, desired_margin_percent, calc_material_cost, calc_energy_cost, calc_machine_cost,
                calc_labor_cost, calc_finishing_cost, calc_total_cost, calc_suggested_price, calc_estimated_profit, calc_margin_percent
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");

            $stmt->execute([
                $tid,
                trim($data['title']),
                !empty($data['product_id']) ? (int)$data['product_id'] : null,
                !empty($data['printer_id']) ? (int)$data['printer_id'] : null,
                $data['material_type'] ?? 'PLA',
                (float)($data['material_price_kg'] ?? 0),
                (float)($data['weight_g'] ?? 0),
                (float)($data['waste_percent'] ?? 5),
                (float)($data['print_hours'] ?? 0),
                (float)($data['energy_kwh'] ?? 0.15),
                (float)($data['energy_cost_kwh'] ?? 0.88),
                (float)($data['depreciation_per_hour'] ?? 1.0),
                (float)($data['maintenance_per_hour'] ?? 0.5),
                (float)($data['machine_op_cost_hour'] ?? 1.0),
                (float)($data['labor_cost'] ?? 0),
                (float)($data['finishing_cost'] ?? 0),
                (float)($data['painting_cost'] ?? 0),
                (float)($data['packaging_cost'] ?? 0),
                (float)($data['labels_cost'] ?? 0),
                (float)($data['platform_fee_percent'] ?? 0),
                (float)($data['payment_fee_percent'] ?? 0),
                (float)($data['extra_costs'] ?? 0),
                (float)($data['desired_margin_percent'] ?? 50),
                $res['calc_material_cost'],
                $res['calc_energy_cost'],
                $res['calc_machine_cost'],
                $res['calc_labor_cost'],
                $res['calc_finishing_cost'],
                $res['calc_total_cost'],
                $res['calc_suggested_price'],
                $res['calc_estimated_profit'],
                $res['calc_margin_percent'],
            ]);

            $newId = (int)$pdo->lastInsertId();
            logAudit($pdo, 'create', 'cost_calculations', $newId, 'Cálculo salvo: ' . $data['title']);
            jsonResponse(['id' => $newId, 'result' => $res, 'message' => 'Cálculo de custo salvo com sucesso!'], 201);
        }

        if ($action === 'apply_product' && $method === 'POST') {
            $data = getJsonBody();
            validateRequired($data, ['title']);
            $res = calculate3DCost($data);
            $timeMin = (int)round(((float)($data['print_hours'] ?? 1)) * 60);

            if (!empty($data['product_id'])) {
                $stmt = $pdo->prepare("UPDATE products SET manufacturing_cost=?, sale_price=?, profit_margin=?, estimated_weight_g=?, estimated_time_min=?, material=? WHERE id=? AND tenant_id=?");
                $stmt->execute([
                    $res['calc_total_cost'],
                    $res['calc_suggested_price'],
                    $res['calc_margin_percent'],
                    (float)($data['weight_g'] ?? 0),
                    $timeMin,
                    $data['material_type'] ?? 'PLA',
                    (int)$data['product_id'],
                    $tid
                ]);
                jsonResponse(['message' => 'Custos e preço sugerido aplicados ao produto existente!', 'result' => $res]);
            } else {
                $sku = 'VHZ-' . strtoupper(substr(uniqid(), -5));
                $stmt = $pdo->prepare("INSERT INTO products (tenant_id, name, sku, category, estimated_weight_g, estimated_time_min, material, manufacturing_cost, sale_price, profit_margin, product_type) VALUES (?, ?, ?, 'Personalizados Sob Demanda', ?, ?, ?, ?, ?, ?, 'personalizado')");
                $stmt->execute([
                    $tid,
                    trim($data['title']),
                    $sku,
                    (float)($data['weight_g'] ?? 0),
                    $timeMin,
                    $data['material_type'] ?? 'PLA',
                    $res['calc_total_cost'],
                    $res['calc_suggested_price'],
                    $res['calc_margin_percent']
                ]);
                jsonResponse(['id' => (int)$pdo->lastInsertId(), 'message' => 'Novo produto criado no catálogo com os custos calculados!', 'result' => $res], 201);
            }
        }

        if ($action === 'item' && $id && $method === 'DELETE') {
            $pdo->prepare("DELETE FROM cost_calculations WHERE id = ? AND tenant_id = ?")->execute([$id, $tid]);
            jsonResponse(['message' => 'Cálculo excluído com sucesso.']);
        }

        jsonResponse(['error' => 'Rota da calculadora inválida.'], 404);
    }
}

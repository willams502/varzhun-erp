<?php
/**
 * Controlador de Fornecedores e Compras (integra Estoque e Financeiro).
 */
class SuppliersController
{
    public static function handle(PDO $pdo, string $action, string $method, ?int $id): void
    {
        $tid = currentTenantId();
        if ($action === 'index' && $method === 'GET') {
            $stmtS = $pdo->prepare("SELECT s.*,
                (SELECT COUNT(*) FROM supplier_purchases sp WHERE sp.supplier_id = s.id) AS purchases_count,
                (SELECT COALESCE(SUM(sp.total_cost), 0) FROM supplier_purchases sp WHERE sp.supplier_id = s.id) AS total_purchased
                FROM suppliers s WHERE s.tenant_id = ? ORDER BY s.id DESC");
            $stmtS->execute([$tid]);
            $suppliers = $stmtS->fetchAll();
            $stmtP = $pdo->prepare("SELECT sp.*, s.name AS supplier_name, i.name AS inventory_name
                                      FROM supplier_purchases sp
                                      LEFT JOIN suppliers s ON s.id = sp.supplier_id
                                      LEFT JOIN inventory_items i ON i.id = sp.inventory_item_id
                                      WHERE s.tenant_id = ?
                                      ORDER BY sp.id DESC LIMIT 50");
            $stmtP->execute([$tid]);
            $purchases = $stmtP->fetchAll();
            jsonResponse(['suppliers' => $suppliers, 'purchases' => $purchases]);
        }

        if ($action === 'index' && $method === 'POST') {
            $data = getJsonBody();
            validateRequired($data, ['name']);
            $stmt = $pdo->prepare("INSERT INTO suppliers (tenant_id, name, cnpj, contact_name, phone, email, address, products_supplied, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $tid,
                trim($data['name']),
                trim($data['cnpj'] ?? ''),
                trim($data['contact_name'] ?? ''),
                trim($data['phone'] ?? ''),
                trim($data['email'] ?? ''),
                trim($data['address'] ?? ''),
                trim($data['products_supplied'] ?? ''),
                trim($data['notes'] ?? ''),
            ]);
            jsonResponse(['id' => (int)$pdo->lastInsertId(), 'message' => 'Fornecedor cadastrado com sucesso!'], 201);
        }

        if ($action === 'item' && $id && $method === 'PUT') {
            $data = getJsonBody();
            validateRequired($data, ['name']);
            $stmt = $pdo->prepare("UPDATE suppliers SET name=?, cnpj=?, contact_name=?, phone=?, email=?, address=?, products_supplied=?, notes=? WHERE id=? AND tenant_id=?");
            $stmt->execute([
                trim($data['name']),
                trim($data['cnpj'] ?? ''),
                trim($data['contact_name'] ?? ''),
                trim($data['phone'] ?? ''),
                trim($data['email'] ?? ''),
                trim($data['address'] ?? ''),
                trim($data['products_supplied'] ?? ''),
                trim($data['notes'] ?? ''),
                $id,
                $tid
            ]);
            jsonResponse(['message' => 'Fornecedor atualizado!']);
        }

        if ($action === 'purchase' && $id && $method === 'POST') {
            $data = getJsonBody();
            validateRequired($data, ['quantity', 'unit_cost']);
            $qty = max(0.01, (float)$data['quantity']);
            $unitCost = max(0, (float)$data['unit_cost']);
            $totalCost = round($qty * $unitCost, 2);
            $invId = !empty($data['inventory_item_id']) ? (int)$data['inventory_item_id'] : null;
            $itemName = trim($data['item_name'] ?? 'Compra de insumo');
            $purchaseDate = $data['purchase_date'] ?: date('Y-m-d');
            $dueDate = $data['due_date'] ?: $purchaseDate;
            $paid = !empty($data['paid']) ? 1 : 0;

            $user = requireAuth();
            $pdo->beginTransaction();
            try {
                if ($invId) {
                    $stmtInv = $pdo->prepare("SELECT name FROM inventory_items WHERE id = ? AND tenant_id = ?");
                    $stmtInv->execute([$invId, $tid]);
                    $foundName = $stmtInv->fetchColumn();
                    if ($foundName) $itemName = $foundName;

                    $pdo->prepare("UPDATE inventory_items SET quantity = quantity + ?, acquisition_cost = ? WHERE id = ? AND tenant_id = ?")
                        ->execute([$qty, $unitCost, $invId, $tid]);
                    $pdo->prepare("INSERT INTO inventory_movements (item_id, movement_type, quantity, unit_cost, reference_type, notes, user_id) VALUES (?, 'compra', ?, ?, 'supplier_purchase', ?, ?)")
                        ->execute([$invId, $qty, $unitCost, "Compra fornecedor #{$id}", $user['id']]);
                }

                $stmtPur = $pdo->prepare("INSERT INTO supplier_purchases (supplier_id, inventory_item_id, item_name, quantity, unit_cost, total_cost, purchase_date, due_date, paid, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmtPur->execute([$id, $invId, $itemName, $qty, $unitCost, $totalCost, $purchaseDate, $dueDate, $paid, trim($data['notes'] ?? '')]);
                $purId = (int)$pdo->lastInsertId();

                // Lança no financeiro automaticamente
                $supName = $pdo->query("SELECT name FROM suppliers WHERE id = " . (int)$id)->fetchColumn();
                $pdo->prepare("INSERT INTO financial_transactions (tenant_id, type, classification, category, description, amount, status, due_date, paid_date, payment_method, reference_type, reference_id) VALUES (?, 'saida', 'custo_producao', 'Filamentos e Resinas', ?, ?, ?, ?, ?, 'PIX', 'supplier_purchase', ?)")
                    ->execute([$tid, "Compra {$itemName} ({$supName})", $totalCost, $paid ? 'pago' : 'pendente', $dueDate, $paid ? $purchaseDate : null, $purId]);

                $pdo->commit();
                jsonResponse(['message' => 'Compra registrada, estoque atualizado e lançada no financeiro!'], 201);
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
        }

        if ($action === 'item' && $id && $method === 'DELETE') {
            $pdo->prepare("DELETE FROM suppliers WHERE id = ? AND tenant_id = ?")->execute([$id, $tid]);
            jsonResponse(['message' => 'Fornecedor excluído.']);
        }

        jsonResponse(['error' => 'Rota de fornecedores inválida.'], 404);
    }
}

<?php
/**
 * Controlador de Controle de Estoque.
 */
class InventoryController
{
    public static function handle(PDO $pdo, string $action, string $method, ?int $id): void
    {
        $tid = currentTenantId();
        if ($action === 'index' && $method === 'GET') {
            $where = ['i.tenant_id = ?'];
            $params = [$tid];
            if (!empty($_GET['category'])) {
                $where[] = 'i.category = ?';
                $params[] = $_GET['category'];
            }
            if (!empty($_GET['low_stock'])) {
                $where[] = 'i.quantity <= i.min_stock';
            }
            if (!empty($_GET['q'])) {
                $where[] = '(i.name LIKE ? OR i.material_type LIKE ? OR i.color LIKE ?)';
                $like = '%' . $_GET['q'] . '%';
                array_push($params, $like, $like, $like);
            }

            $sql = "SELECT i.*, s.name AS supplier_name,
                    CASE WHEN i.quantity <= i.min_stock THEN 1 ELSE 0 END AS is_low_stock
                    FROM inventory_items i
                    LEFT JOIN suppliers s ON s.id = i.supplier_id
                    WHERE " . implode(' AND ', $where) . "
                    ORDER BY is_low_stock DESC, i.id DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $items = $stmt->fetchAll();

            $stmtMov = $pdo->prepare("SELECT m.*, i.name AS item_name, i.unit, u.name AS user_name
                                      FROM inventory_movements m
                                      LEFT JOIN inventory_items i ON i.id = m.item_id
                                      LEFT JOIN users u ON u.id = m.user_id
                                      WHERE i.tenant_id = ?
                                      ORDER BY m.id DESC LIMIT 50");
            $stmtMov->execute([$tid]);
            $movements = $stmtMov->fetchAll();

            jsonResponse(['items' => $items, 'movements' => $movements]);
        }

        if ($action === 'index' && $method === 'POST') {
            $data = getJsonBody();
            validateRequired($data, ['name', 'category']);
            $qty = max(0, (float)($data['quantity'] ?? 0));
            $cost = max(0, (float)($data['acquisition_cost'] ?? 0));

            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare("INSERT INTO inventory_items (tenant_id, name, category, material_type, color, quantity, unit, acquisition_cost, supplier_id, min_stock, entry_date, notes)
                                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $tid,
                    trim($data['name']),
                    $data['category'],
                    trim($data['material_type'] ?? ''),
                    trim($data['color'] ?? ''),
                    $qty,
                    $data['unit'] ?? 'g',
                    $cost,
                    !empty($data['supplier_id']) ? (int)$data['supplier_id'] : null,
                    max(0, (float)($data['min_stock'] ?? 0)),
                    $data['entry_date'] ?: date('Y-m-d'),
                    trim($data['notes'] ?? ''),
                ]);
                $itemId = (int)$pdo->lastInsertId();

                if ($qty > 0) {
                    $user = requireAuth();
                    $pdo->prepare("INSERT INTO inventory_movements (item_id, movement_type, quantity, unit_cost, notes, user_id) VALUES (?, 'entrada', ?, ?, 'Saldo inicial no cadastro', ?)")
                        ->execute([$itemId, $qty, $cost, $user['id']]);
                }

                $pdo->commit();
                jsonResponse(['id' => $itemId, 'message' => 'Item cadastrado no estoque!'], 201);
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
        }

        if ($action === 'item' && $id && $method === 'PUT') {
            $data = getJsonBody();
            validateRequired($data, ['name', 'category']);
            $stmt = $pdo->prepare("UPDATE inventory_items SET name=?, category=?, material_type=?, color=?, unit=?, acquisition_cost=?, supplier_id=?, min_stock=?, notes=? WHERE id=? AND tenant_id=?");
            $stmt->execute([
                trim($data['name']),
                $data['category'],
                trim($data['material_type'] ?? ''),
                trim($data['color'] ?? ''),
                $data['unit'] ?? 'g',
                max(0, (float)($data['acquisition_cost'] ?? 0)),
                !empty($data['supplier_id']) ? (int)$data['supplier_id'] : null,
                max(0, (float)($data['min_stock'] ?? 0)),
                trim($data['notes'] ?? ''),
                $id,
                $tid
            ]);
            jsonResponse(['message' => 'Item atualizado com sucesso!']);
        }

        if ($action === 'movement' && $id && $method === 'POST') {
            $data = getJsonBody();
            validateRequired($data, ['movement_type', 'quantity']);
            $type = $data['movement_type']; // entrada, saida, ajuste
            $qty = (float)$data['quantity'];
            if ($qty <= 0) jsonResponse(['error' => 'A quantidade deve ser maior que zero.'], 422);

            $stmtItem = $pdo->prepare("SELECT * FROM inventory_items WHERE id = ? AND tenant_id = ?");
            $stmtItem->execute([$id, $tid]);
            $item = $stmtItem->fetch();
            if (!$item) jsonResponse(['error' => 'Item não encontrado.'], 404);

            $delta = $type === 'saida' ? -$qty : $qty;
            if ($type === 'ajuste' && isset($data['new_quantity'])) {
                $delta = (float)$data['new_quantity'] - (float)$item['quantity'];
            }

            if ((float)$item['quantity'] + $delta < 0) {
                jsonResponse(['error' => 'Estoque insuficiente para esta saída.'], 422);
            }

            $user = requireAuth();
            $pdo->beginTransaction();
            try {
                $pdo->prepare("UPDATE inventory_items SET quantity = quantity + ? WHERE id = ?")->execute([$delta, $id]);
                $pdo->prepare("INSERT INTO inventory_movements (item_id, movement_type, quantity, unit_cost, notes, user_id) VALUES (?, ?, ?, ?, ?, ?)")
                    ->execute([$id, $type, $delta, (float)($data['unit_cost'] ?? $item['acquisition_cost']), trim($data['notes'] ?? ''), $user['id']]);
                $pdo->commit();
                jsonResponse(['message' => 'Movimentação registrada no estoque!']);
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
        }

        if ($action === 'item' && $id && $method === 'DELETE') {
            $pdo->prepare("DELETE FROM inventory_movements WHERE item_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM inventory_items WHERE id = ? AND tenant_id = ?")->execute([$id, $tid]);
            jsonResponse(['message' => 'Item excluído do estoque.']);
        }

        jsonResponse(['error' => 'Rota de estoque inválida.'], 404);
    }
}

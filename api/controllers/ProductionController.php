<?php
/**
 * Controlador de Produção (Kanban de 12 etapas).
 */
class ProductionController
{
    public const STAGES = [
        'orcamento' => 'Orçamento',
        'aguardando_aprovacao' => 'Aguardando aprovação',
        'aguardando_pagamento' => 'Aguardando pagamento',
        'aguardando_producao' => 'Aguardando produção',
        'preparacao_arquivo' => 'Preparação do arquivo',
        'fila_impressao' => 'Fila de impressão',
        'em_impressao' => 'Em impressão',
        'pos_processamento' => 'Pós-processamento',
        'controle_qualidade' => 'Controle de qualidade',
        'pronto_entrega' => 'Pronto para entrega',
        'entregue' => 'Entregue',
        'cancelado' => 'Cancelado',
    ];

    public static function handle(PDO $pdo, string $action, string $method, ?int $id): void
    {
        $tid = currentTenantId();
        if ($action === 'index' && $method === 'GET') {
            $where = ['po.tenant_id = ?'];
            $params = [$tid];
            if (!empty($_GET['q'])) {
                $where[] = '(po.order_number LIKE ? OR po.title LIKE ? OR c.name LIKE ? OR po.products_summary LIKE ?)';
                $like = '%' . $_GET['q'] . '%';
                array_push($params, $like, $like, $like, $like);
            }
            if (!empty($_GET['priority'])) {
                $where[] = 'po.priority = ?';
                $params[] = $_GET['priority'];
            }

            $sql = "SELECT po.*, c.name AS customer_name, pr.name AS printer_name, i.name AS inventory_item_name
                    FROM production_orders po
                    LEFT JOIN customers c ON c.id = po.customer_id
                    LEFT JOIN printers pr ON pr.id = po.printer_id
                    LEFT JOIN inventory_items i ON i.id = po.inventory_item_id
                    WHERE " . implode(' AND ', $where) . "
                    ORDER BY CASE po.priority WHEN 'urgente' THEN 1 WHEN 'alta' THEN 2 WHEN 'normal' THEN 3 ELSE 4 END, po.due_date ASC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $orders = $stmt->fetchAll();

            $today = date('Y-m-d');
            $soon = date('Y-m-d', strtotime('+2 days'));
            foreach ($orders as &$o) {
                $done = in_array($o['stage'], ['entregue', 'cancelado'], true);
                $o['is_late'] = !$done && !empty($o['due_date']) && $o['due_date'] < $today;
                $o['is_due_soon'] = !$done && !$o['is_late'] && !empty($o['due_date']) && $o['due_date'] <= $soon;
            }

            jsonResponse(['orders' => $orders, 'stages' => self::STAGES]);
        }

        if ($action === 'item' && $id && $method === 'GET') {
            $stmt = $pdo->prepare("SELECT po.*, c.name AS customer_name, pr.name AS printer_name
                                   FROM production_orders po
                                   LEFT JOIN customers c ON c.id = po.customer_id
                                   LEFT JOIN printers pr ON pr.id = po.printer_id
                                   WHERE po.id = ? AND po.tenant_id = ?");
            $stmt->execute([$id, $tid]);
            $order = $stmt->fetch();
            if (!$order) jsonResponse(['error' => 'Ordem de produção não encontrada.'], 404);

            $stmtHist = $pdo->prepare("SELECT * FROM production_history WHERE order_id = ? ORDER BY id DESC");
            $stmtHist->execute([$id]);
            jsonResponse(['order' => $order, 'history' => $stmtHist->fetchAll()]);
        }

        if ($action === 'index' && $method === 'POST') {
            $data = getJsonBody();
            validateRequired($data, ['title', 'due_date']);
            $stage = $data['stage'] ?? 'aguardando_producao';
            if (!isset(self::STAGES[$stage])) $stage = 'aguardando_producao';

            $user = requireAuth();
            $opNum = 'OP-' . date('Y') . '-' . str_pad((string)random_int(100, 9999), 4, '0', STR_PAD_LEFT);
            $stmt = $pdo->prepare("INSERT INTO production_orders (tenant_id, order_number, customer_id, title, products_summary, total_qty, stage, priority, printer_id, entry_date, due_date, estimated_hours, actual_hours, estimated_cost, actual_cost, responsible_name, inventory_item_id, estimated_weight_g, notes)
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $tid,
                $opNum,
                !empty($data['customer_id']) ? (int)$data['customer_id'] : null,
                trim($data['title']),
                trim($data['products_summary'] ?? $data['title']),
                max(1, (int)($data['total_qty'] ?? 1)),
                $stage,
                $data['priority'] ?? 'normal',
                !empty($data['printer_id']) ? (int)$data['printer_id'] : null,
                $data['entry_date'] ?: date('Y-m-d'),
                $data['due_date'],
                max(0, (float)($data['estimated_hours'] ?? 1)),
                max(0, (float)($data['actual_hours'] ?? 0)),
                max(0, (float)($data['estimated_cost'] ?? 0)),
                max(0, (float)($data['actual_cost'] ?? 0)),
                trim($data['responsible_name'] ?? $user['name']),
                !empty($data['inventory_item_id']) ? (int)$data['inventory_item_id'] : null,
                max(0, (float)($data['estimated_weight_g'] ?? 0)),
                trim($data['notes'] ?? ''),
            ]);
            $newId = (int)$pdo->lastInsertId();
            $pdo->prepare("INSERT INTO production_history (order_id, from_stage, to_stage, notes, user_name) VALUES (?, NULL, ?, 'Ordem de produção criada', ?)")
                ->execute([$newId, $stage, $user['name']]);
            jsonResponse(['id' => $newId, 'order_number' => $opNum, 'message' => "Ordem {$opNum} criada com sucesso!"], 201);
        }

        if ($action === 'item' && $id && $method === 'PUT') {
            $data = getJsonBody();
            validateRequired($data, ['title', 'due_date']);
            $stmt = $pdo->prepare("UPDATE production_orders SET customer_id=?, title=?, products_summary=?, total_qty=?, priority=?, printer_id=?, due_date=?, estimated_hours=?, actual_hours=?, estimated_cost=?, actual_cost=?, responsible_name=?, inventory_item_id=?, estimated_weight_g=?, notes=?, updated_at=CURRENT_TIMESTAMP WHERE id=? AND tenant_id=?");
            $stmt->execute([
                !empty($data['customer_id']) ? (int)$data['customer_id'] : null,
                trim($data['title']),
                trim($data['products_summary'] ?? $data['title']),
                max(1, (int)($data['total_qty'] ?? 1)),
                $data['priority'] ?? 'normal',
                !empty($data['printer_id']) ? (int)$data['printer_id'] : null,
                $data['due_date'],
                max(0, (float)($data['estimated_hours'] ?? 1)),
                max(0, (float)($data['actual_hours'] ?? 0)),
                max(0, (float)($data['estimated_cost'] ?? 0)),
                max(0, (float)($data['actual_cost'] ?? 0)),
                trim($data['responsible_name'] ?? ''),
                !empty($data['inventory_item_id']) ? (int)$data['inventory_item_id'] : null,
                max(0, (float)($data['estimated_weight_g'] ?? 0)),
                trim($data['notes'] ?? ''),
                $id,
                $tid
            ]);
            jsonResponse(['message' => 'Ordem de produção atualizada!']);
        }

        if ($action === 'move' && $id && $method === 'POST') {
            $data = getJsonBody();
            $toStage = $data['stage'] ?? '';
            if (!isset(self::STAGES[$toStage])) {
                jsonResponse(['error' => 'Etapa de produção inválida.'], 422);
            }

            $stmt = $pdo->prepare("SELECT * FROM production_orders WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$id, $tid]);
            $order = $stmt->fetch();
            if (!$order) jsonResponse(['error' => 'Ordem não encontrada.'], 404);

            $user = requireAuth();
            $fromStage = $order['stage'];
            $actualHours = isset($data['actual_hours']) ? (float)$data['actual_hours'] : (float)$order['actual_hours'];
            $actualCost = isset($data['actual_cost']) ? (float)$data['actual_cost'] : (float)$order['actual_cost'];

            $pdo->beginTransaction();
            try {
                $pdo->prepare("UPDATE production_orders SET stage = ?, actual_hours = ?, actual_cost = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")
                    ->execute([$toStage, $actualHours, $actualCost, $id]);

                $pdo->prepare("INSERT INTO production_history (order_id, from_stage, to_stage, notes, user_name) VALUES (?, ?, ?, ?, ?)")
                    ->execute([$id, $fromStage, $toStage, trim($data['notes'] ?? ''), $user['name']]);

                // Atualiza status da impressora vinculada
                if (!empty($order['printer_id'])) {
                    if ($toStage === 'em_impressao') {
                        $pdo->prepare("UPDATE printers SET status = 'imprimindo' WHERE id = ? AND tenant_id = ?")->execute([$order['printer_id'], $tid]);
                    } elseif ($fromStage === 'em_impressao' && $toStage !== 'em_impressao') {
                        $pdo->prepare("UPDATE printers SET status = 'disponivel' WHERE id = ? AND tenant_id = ? AND status = 'imprimindo'")->execute([$order['printer_id'], $tid]);
                    }
                }

                // Baixa automática de material do estoque na conclusão
                $autoDeduct = getSetting($pdo, 'auto_deduct_stock', '1') === '1';
                if (in_array($toStage, ['pronto_entrega', 'entregue'], true) && (int)$order['material_deducted'] === 0) {
                    if ($autoDeduct && !empty($order['inventory_item_id']) && (float)$order['estimated_weight_g'] > 0) {
                        $weight = (float)$order['estimated_weight_g'];
                        $pdo->prepare("UPDATE inventory_items SET quantity = CASE WHEN quantity >= ? THEN quantity - ? ELSE 0 END WHERE id = ?")
                            ->execute([$weight, $weight, $order['inventory_item_id']]);
                        $pdo->prepare("INSERT INTO inventory_movements (item_id, movement_type, quantity, reference_type, reference_id, notes, user_id) VALUES (?, 'producao', ?, 'production_order', ?, ?, ?)")
                            ->execute([$order['inventory_item_id'], -$weight, $id, "Consumo automático da {$order['order_number']}", $user['id']]);
                    }

                    // Registra horas na impressora se informadas
                    $hoursToLog = $actualHours > 0 ? $actualHours : (float)$order['estimated_hours'];
                    if (!empty($order['printer_id']) && $hoursToLog > 0) {
                        $pdo->prepare("UPDATE printers SET total_hours = total_hours + ? WHERE id = ?")->execute([$hoursToLog, $order['printer_id']]);
                        $pdo->prepare("INSERT INTO printer_logs (printer_id, log_type, hours_worked, prints_count, success_count, description, production_order_id, log_date) VALUES (?, 'impressao', ?, ?, ?, ?, ?, ?)")
                            ->execute([$order['printer_id'], $hoursToLog, (int)$order['total_qty'], (int)$order['total_qty'], "Produção {$order['order_number']}", $id, date('Y-m-d')]);
                    }

                    $pdo->prepare("UPDATE production_orders SET material_deducted = 1 WHERE id = ?")->execute([$id]);
                }

                logAudit($pdo, 'move', 'production_orders', $id, "{$order['order_number']}: {$fromStage} -> {$toStage}");
                $pdo->commit();
                jsonResponse(['message' => 'Ordem movida para "' . self::STAGES[$toStage] . '"!']);
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
        }

        if ($action === 'item' && $id && $method === 'DELETE') {
            $pdo->prepare("DELETE FROM production_history WHERE order_id = ? AND order_id IN (SELECT id FROM production_orders WHERE tenant_id = ?)")->execute([$id, $tid]);
            $pdo->prepare("DELETE FROM production_orders WHERE id = ? AND tenant_id = ?")->execute([$id, $tid]);
            jsonResponse(['message' => 'Ordem de produção excluída.']);
        }

        jsonResponse(['error' => 'Rota de produção inválida.'], 404);
    }
}

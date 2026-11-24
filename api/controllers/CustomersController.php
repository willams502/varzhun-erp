<?php
/**
 * Controlador de Clientes (CRM).
 */
class CustomersController
{
    public static function handle(PDO $pdo, string $action, string $method, ?int $id): void
    {
        $tid = currentTenantId();
        if ($action === 'index' && $method === 'GET') {
            $q = trim($_GET['q'] ?? '');
            $where = 'c.tenant_id = ?';
            $params = [$tid];
            if ($q !== '') {
                $where .= ' AND (c.name LIKE ? OR c.cpf_cnpj LIKE ? OR c.email LIKE ? OR c.whatsapp LIKE ?)';
                $like = "%{$q}%";
                array_push($params, $like, $like, $like, $like);
            }

            $sql = "SELECT c.*,
                        (SELECT COUNT(*) FROM sales s WHERE s.customer_id = c.id AND s.sale_type = 'venda') AS orders_count,
                        (SELECT COALESCE(SUM(s.total_amount), 0) FROM sales s WHERE s.customer_id = c.id AND s.sale_type = 'venda') AS total_spent,
                        (SELECT COALESCE(SUM(s.total_amount - s.paid_amount), 0) FROM sales s WHERE s.customer_id = c.id AND s.sale_type = 'venda' AND s.payment_status != 'pago') AS pending_amount,
                        (SELECT MAX(s.sale_date) FROM sales s WHERE s.customer_id = c.id AND s.sale_type = 'venda') AS last_purchase
                    FROM customers c
                    WHERE {$where}
                    ORDER BY c.id DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            jsonResponse(['customers' => $stmt->fetchAll()]);
        }

        if ($action === 'item' && $id && $method === 'GET') {
            $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$id, $tid]);
            $customer = $stmt->fetch();
            if (!$customer) jsonResponse(['error' => 'Cliente não encontrado.'], 404);

            $stmtSales = $pdo->prepare("SELECT * FROM sales WHERE customer_id = ? AND tenant_id = ? ORDER BY id DESC");
            $stmtSales->execute([$id, $tid]);
            $allSales = $stmtSales->fetchAll();

            $compras = array_values(array_filter($allSales, fn($s) => $s['sale_type'] === 'venda'));
            $orcamentos = array_values(array_filter($allSales, fn($s) => $s['sale_type'] === 'orcamento'));

            $stmtOrders = $pdo->prepare("SELECT * FROM production_orders WHERE customer_id = ? AND tenant_id = ? AND stage NOT IN ('entregue','cancelado') ORDER BY id DESC");
            $stmtOrders->execute([$id, $tid]);
            $emAndamento = $stmtOrders->fetchAll();

            $stmtInt = $pdo->prepare("SELECT ci.*, u.name AS user_name FROM customer_interactions ci LEFT JOIN users u ON u.id = ci.user_id WHERE ci.customer_id = ? ORDER BY ci.id DESC");
            $stmtInt->execute([$id]);
            $interactions = $stmtInt->fetchAll();

            $totalSpent = array_reduce($compras, fn($acc, $s) => $acc + (float)$s['total_amount'], 0);
            $totalPending = array_reduce($compras, fn($acc, $s) => $acc + max(0, (float)$s['total_amount'] - (float)$s['paid_amount']), 0);
            $lastPurchase = count($compras) > 0 ? $compras[0]['sale_date'] : null;

            jsonResponse([
                'customer' => $customer,
                'summary' => [
                    'total_spent' => round($totalSpent, 2),
                    'pending_amount' => round($totalPending, 2),
                    'last_purchase' => $lastPurchase,
                    'purchases_count' => count($compras),
                    'quotes_count' => count($orcamentos),
                    'active_orders_count' => count($emAndamento),
                ],
                'purchases' => $compras,
                'quotes' => $orcamentos,
                'active_orders' => $emAndamento,
                'interactions' => $interactions,
            ]);
        }

        if ($action === 'index' && $method === 'POST') {
            $data = getJsonBody();
            validateRequired($data, ['name']);
            $stmt = $pdo->prepare("INSERT INTO customers (tenant_id, name, cpf_cnpj, phone, whatsapp, email, address, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $tid,
                trim($data['name']),
                trim($data['cpf_cnpj'] ?? ''),
                trim($data['phone'] ?? ''),
                trim($data['whatsapp'] ?? ''),
                trim($data['email'] ?? ''),
                trim($data['address'] ?? ''),
                trim($data['notes'] ?? ''),
            ]);
            $newId = (int)$pdo->lastInsertId();
            logAudit($pdo, 'create', 'customers', $newId, 'Cliente cadastrado: ' . $data['name']);
            jsonResponse(['id' => $newId, 'message' => 'Cliente cadastrado com sucesso!'], 201);
        }

        if ($action === 'item' && $id && $method === 'PUT') {
            $data = getJsonBody();
            validateRequired($data, ['name']);
            $stmt = $pdo->prepare("UPDATE customers SET name = ?, cpf_cnpj = ?, phone = ?, whatsapp = ?, email = ?, address = ?, notes = ? WHERE id = ? AND tenant_id = ?");
            $stmt->execute([
                trim($data['name']),
                trim($data['cpf_cnpj'] ?? ''),
                trim($data['phone'] ?? ''),
                trim($data['whatsapp'] ?? ''),
                trim($data['email'] ?? ''),
                trim($data['address'] ?? ''),
                trim($data['notes'] ?? ''),
                $id,
                $tid
            ]);
            logAudit($pdo, 'update', 'customers', $id, 'Cliente atualizado');
            jsonResponse(['message' => 'Cliente atualizado com sucesso!']);
        }

        if ($action === 'interaction' && $id && $method === 'POST') {
            $data = getJsonBody();
            validateRequired($data, ['description']);
            $user = requireAuth();
            $stmt = $pdo->prepare("INSERT INTO customer_interactions (customer_id, type, description, user_id) VALUES (?, ?, ?, ?)");
            $stmt->execute([$id, $data['type'] ?? 'Atendimento', trim($data['description']), $user['id']]);
            jsonResponse(['message' => 'Atendimento registrado no histórico do cliente!'], 201);
        }

        if ($action === 'item' && $id && $method === 'DELETE') {
            $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM sales WHERE customer_id = ?");
            $stmtCheck->execute([$id]);
            if ((int)$stmtCheck->fetchColumn() > 0) {
                jsonResponse(['error' => 'Não é possível excluir este cliente pois existem vendas/orçamentos vinculados.'], 409);
            }
            $pdo->prepare("DELETE FROM customer_interactions WHERE customer_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM customers WHERE id = ? AND tenant_id = ?")->execute([$id, $tid]);
            logAudit($pdo, 'delete', 'customers', $id, 'Cliente excluído');
            jsonResponse(['message' => 'Cliente excluído com sucesso.']);
        }

        jsonResponse(['error' => 'Rota de clientes inválida.'], 404);
    }
}

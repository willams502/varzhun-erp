<?php
/**
 * Controlador do Catálogo de Produtos.
 */
class ProductsController
{
    public static function handle(PDO $pdo, string $action, string $method, ?int $id): void
    {
        $tid = currentTenantId();
        if ($action === 'index' && $method === 'GET') {
            $where = ['p.tenant_id = ?'];
            $params = [$tid];
            if (!empty($_GET['q'])) {
                $where[] = '(p.name LIKE ? OR p.sku LIKE ? OR p.material LIKE ? OR p.category LIKE ?)';
                $like = '%' . $_GET['q'] . '%';
                array_push($params, $like, $like, $like, $like);
            }
            if (!empty($_GET['category'])) {
                $where[] = 'category = ?';
                $params[] = $_GET['category'];
            }
            if (!empty($_GET['product_type'])) {
                $where[] = 'product_type = ?';
                $params[] = $_GET['product_type'];
            }

            $stmt = $pdo->prepare("SELECT p.*, i.name AS inventory_item_name FROM products p LEFT JOIN inventory_items i ON i.id = p.inventory_item_id WHERE " . implode(' AND ', $where) . " ORDER BY p.id DESC");
            $stmt->execute($params);
            jsonResponse(['products' => $stmt->fetchAll()]);
        }

        if ($action === 'index' && $method === 'POST') {
            $data = getJsonBody();
            validateRequired($data, ['name', 'sale_price']);
            $cost = max(0, (float)($data['manufacturing_cost'] ?? 0));
            $price = max(0, (float)$data['sale_price']);
            $margin = $price > 0 ? round((($price - $cost) / $price) * 100, 2) : 0;

            $stmt = $pdo->prepare("INSERT INTO products (tenant_id, name, sku, category, description, photo_url, stl_file, threemf_file, estimated_weight_g, estimated_time_min, material, color, stock_qty, manufacturing_cost, sale_price, profit_margin, avg_production_days, product_type, inventory_item_id)
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $tid,
                trim($data['name']),
                trim($data['sku'] ?? 'VHZ-' . strtoupper(substr(uniqid(), -5))),
                trim($data['category'] ?? 'Geral'),
                trim($data['description'] ?? ''),
                trim($data['photo_url'] ?? ''),
                trim($data['stl_file'] ?? ''),
                trim($data['threemf_file'] ?? ''),
                max(0, (float)($data['estimated_weight_g'] ?? 0)),
                max(0, (int)($data['estimated_time_min'] ?? 0)),
                trim($data['material'] ?? 'PLA'),
                trim($data['color'] ?? ''),
                max(0, (int)($data['stock_qty'] ?? 0)),
                $cost,
                $price,
                $margin,
                max(1, (int)($data['avg_production_days'] ?? 2)),
                ($data['product_type'] ?? 'recorrente') === 'personalizado' ? 'personalizado' : 'recorrente',
                !empty($data['inventory_item_id']) ? (int)$data['inventory_item_id'] : null,
            ]);
            $newId = (int)$pdo->lastInsertId();
            logAudit($pdo, 'create', 'products', $newId, 'Produto cadastrado: ' . $data['name']);
            jsonResponse(['id' => $newId, 'message' => 'Produto cadastrado com sucesso!'], 201);
        }

        if ($action === 'item' && $id && $method === 'PUT') {
            $data = getJsonBody();
            validateRequired($data, ['name', 'sale_price']);
            $cost = max(0, (float)($data['manufacturing_cost'] ?? 0));
            $price = max(0, (float)$data['sale_price']);
            $margin = $price > 0 ? round((($price - $cost) / $price) * 100, 2) : 0;

            $stmt = $pdo->prepare("UPDATE products SET name=?, sku=?, category=?, description=?, photo_url=?, stl_file=?, threemf_file=?, estimated_weight_g=?, estimated_time_min=?, material=?, color=?, stock_qty=?, manufacturing_cost=?, sale_price=?, profit_margin=?, avg_production_days=?, product_type=?, inventory_item_id=? WHERE id=? AND tenant_id=?");
            $stmt->execute([
                trim($data['name']),
                trim($data['sku'] ?? ''),
                trim($data['category'] ?? 'Geral'),
                trim($data['description'] ?? ''),
                trim($data['photo_url'] ?? ''),
                trim($data['stl_file'] ?? ''),
                trim($data['threemf_file'] ?? ''),
                max(0, (float)($data['estimated_weight_g'] ?? 0)),
                max(0, (int)($data['estimated_time_min'] ?? 0)),
                trim($data['material'] ?? 'PLA'),
                trim($data['color'] ?? ''),
                max(0, (int)($data['stock_qty'] ?? 0)),
                $cost,
                $price,
                $margin,
                max(1, (int)($data['avg_production_days'] ?? 2)),
                ($data['product_type'] ?? 'recorrente') === 'personalizado' ? 'personalizado' : 'recorrente',
                !empty($data['inventory_item_id']) ? (int)$data['inventory_item_id'] : null,
                $id,
                $tid
            ]);
            logAudit($pdo, 'update', 'products', $id, 'Produto atualizado: ' . $data['name']);
            jsonResponse(['message' => 'Produto atualizado com sucesso!']);
        }

        if ($action === 'item' && $id && $method === 'DELETE') {
            $pdo->prepare("DELETE FROM products WHERE id = ? AND tenant_id = ?")->execute([$id, $tid]);
            logAudit($pdo, 'delete', 'products', $id, 'Produto excluído');
            jsonResponse(['message' => 'Produto excluído com sucesso.']);
        }

        jsonResponse(['error' => 'Rota de produtos inválida.'], 404);
    }
}

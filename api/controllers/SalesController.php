<?php
/**
 * Controlador de Vendas e Orçamentos.
 */
class SalesController
{
    public static function handle(PDO $pdo, string $action, string $method, ?int $id): void
    {
        $tid = currentTenantId();
        if ($action === 'index' && $method === 'GET') {
            $where = ['s.tenant_id = ?'];
            $params = [$tid];

            if (!empty($_GET['sale_type'])) {
                $where[] = 's.sale_type = ?';
                $params[] = $_GET['sale_type'];
            }
            if (!empty($_GET['payment_status'])) {
                $where[] = 's.payment_status = ?';
                $params[] = $_GET['payment_status'];
            }
            if (!empty($_GET['customer_id'])) {
                $where[] = 's.customer_id = ?';
                $params[] = (int)$_GET['customer_id'];
            }
            if (!empty($_GET['q'])) {
                $where[] = '(s.code LIKE ? OR c.name LIKE ? OR s.notes LIKE ?)';
                $q = '%' . $_GET['q'] . '%';
                $params[] = $q;
                $params[] = $q;
                $params[] = $q;
            }

            $sql = "SELECT s.*, c.name AS customer_name, c.whatsapp AS customer_whatsapp, c.cpf_cnpj AS customer_doc
                    FROM sales s
                    LEFT JOIN customers c ON c.id = s.customer_id
                    WHERE " . implode(' AND ', $where) . "
                    ORDER BY s.id DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $sales = $stmt->fetchAll();

            // Carrega itens resumidos
            $stmtItems = $pdo->prepare("SELECT * FROM sale_items WHERE sale_id = ?");
            foreach ($sales as &$s) {
                $stmtItems->execute([$s['id']]);
                $s['items'] = $stmtItems->fetchAll();
            }

            jsonResponse(['sales' => $sales]);
        }

        if ($action === 'item' && $id && $method === 'GET') {
            $stmt = $pdo->prepare("SELECT s.*, c.name AS customer_name, c.cpf_cnpj, c.phone, c.whatsapp, c.email, c.address
                                   FROM sales s LEFT JOIN customers c ON c.id = s.customer_id WHERE s.id = ? AND s.tenant_id = ?");
            $stmt->execute([$id, $tid]);
            $sale = $stmt->fetch();
            if (!$sale) jsonResponse(['error' => 'Venda/Orçamento não encontrado.'], 404);

            $stmtItems = $pdo->prepare("SELECT * FROM sale_items WHERE sale_id = ?");
            $stmtItems->execute([$id]);
            $sale['items'] = $stmtItems->fetchAll();

            $stmtPay = $pdo->prepare("SELECT * FROM sale_payments WHERE sale_id = ? ORDER BY id DESC");
            $stmtPay->execute([$id]);
            $sale['payments'] = $stmtPay->fetchAll();

            $stmtProd = $pdo->prepare("SELECT id, order_number, stage, due_date FROM production_orders WHERE sale_id = ?");
            $stmtProd->execute([$id]);
            $sale['production_order'] = $stmtProd->fetch() ?: null;

            jsonResponse(['sale' => $sale]);
        }

        if ($action === 'index' && $method === 'POST') {
            $data = getJsonBody();
            validateRequired($data, ['customer_id', 'items']);
            if (!is_array($data['items']) || count($data['items']) === 0) {
                jsonResponse(['error' => 'Adicione pelo menos um produto/item à venda.'], 422);
            }

            $saleType = ($data['sale_type'] ?? 'venda') === 'orcamento' ? 'orcamento' : 'venda';
            $status = $saleType === 'orcamento' ? 'orcamento' : 'aprovado';
            $paymentMethod = $data['payment_method'] ?? 'PIX';
            $discount = max(0, (float)($data['discount_value'] ?? 0));
            $downPayment = max(0, (float)($data['down_payment'] ?? 0));
            $installments = max(1, (int)($data['installments'] ?? 1));
            $saleDate = $data['sale_date'] ?: date('Y-m-d');
            $dueDate = $data['due_date'] ?: date('Y-m-d', strtotime('+7 days'));

            $subtotal = 0;
            $totalCost = 0;
            $totalWeight = 0;
            $totalTimeMin = 0;
            $totalQty = 0;
            $summaryParts = [];
            $primaryInventoryId = null;

            $stmtProdLookup = $pdo->prepare("SELECT * FROM products WHERE id = ?");
            $preparedItems = [];
            foreach ($data['items'] as $item) {
                $qty = max(1, (int)($item['quantity'] ?? 1));
                $unitPrice = max(0, (float)($item['unit_price'] ?? 0));
                $unitCost = max(0, (float)($item['unit_cost'] ?? 0));
                $weightG = max(0, (float)($item['weight_g'] ?? 0));
                $timeMin = max(0, (int)($item['print_time_min'] ?? 0));
                $desc = trim($item['description'] ?? '');
                $prodId = !empty($item['product_id']) ? (int)$item['product_id'] : null;

                if ($prodId) {
                    $stmtProdLookup->execute([$prodId]);
                    $p = $stmtProdLookup->fetch();
                    if ($p) {
                        if ($desc === '') $desc = $p['name'];
                        if ($unitCost == 0) $unitCost = (float)$p['manufacturing_cost'];
                        if ($weightG == 0) $weightG = (float)$p['estimated_weight_g'];
                        if ($timeMin == 0) $timeMin = (int)$p['estimated_time_min'];
                        if (!$primaryInventoryId && !empty($p['inventory_item_id'])) {
                            $primaryInventoryId = (int)$p['inventory_item_id'];
                        }
                    }
                }
                if ($desc === '') {
                    jsonResponse(['error' => 'Todo item precisa de uma descrição.'], 422);
                }

                $lineTotal = round($qty * $unitPrice, 2);
                $subtotal += $lineTotal;
                $totalCost += round($qty * $unitCost, 2);
                $totalWeight += ($qty * $weightG);
                $totalTimeMin += ($qty * $timeMin);
                $totalQty += $qty;
                $summaryParts[] = "{$qty}x {$desc}";

                $preparedItems[] = [
                    'product_id' => $prodId,
                    'description' => $desc,
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'unit_cost' => $unitCost,
                    'total_price' => $lineTotal,
                    'weight_g' => $weightG,
                    'print_time_min' => $timeMin,
                ];
            }

            $totalAmount = max(0, round($subtotal - $discount, 2));
            if ($downPayment > $totalAmount) {
                $downPayment = $totalAmount;
            }

            $prefix = $saleType === 'orcamento' ? 'ORC' : 'VND';
            $code = $prefix . '-' . date('Y') . '-' . str_pad((string)random_int(100, 9999), 4, '0', STR_PAD_LEFT);

            $pdo->beginTransaction();
            try {
                $user = requireAuth();
                $stmt = $pdo->prepare("INSERT INTO sales (tenant_id, code, customer_id, sale_type, status, payment_method, payment_status, subtotal, discount_value, total_amount, down_payment, paid_amount, installments, due_date, sale_date, notes, created_by)
                                       VALUES (?, ?, ?, ?, ?, ?, 'pendente', ?, ?, ?, ?, 0, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $tid,
                    $code,
                    (int)$data['customer_id'],
                    $saleType,
                    $status,
                    $paymentMethod,
                    round($subtotal, 2),
                    round($discount, 2),
                    $totalAmount,
                    $downPayment,
                    $installments,
                    $dueDate,
                    $saleDate,
                    $data['notes'] ?? '',
                    $user['id']
                ]);
                $saleId = (int)$pdo->lastInsertId();

                $stmtItem = $pdo->prepare("INSERT INTO sale_items (sale_id, product_id, description, quantity, unit_price, unit_cost, total_price, weight_g, print_time_min) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                foreach ($preparedItems as $pi) {
                    $stmtItem->execute([$saleId, $pi['product_id'], $pi['description'], $pi['quantity'], $pi['unit_price'], $pi['unit_cost'], $pi['total_price'], $pi['weight_g'], $pi['print_time_min']]);
                }

                // Se for venda aprovada, registra pagamento inicial (se houver) e cria ordem de produção + contas a receber
                if ($saleType === 'venda') {
                    if ($downPayment > 0) {
                        $stmtPay = $pdo->prepare("INSERT INTO sale_payments (sale_id, amount, payment_method, payment_date, notes, created_by) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmtPay->execute([$saleId, $downPayment, $paymentMethod, $saleDate, 'Sinal / Pagamento inicial', $user['id']]);

                        $stmtFin = $pdo->prepare("INSERT INTO financial_transactions (tenant_id, type, classification, category, description, amount, status, due_date, paid_date, payment_method, reference_type, reference_id) VALUES (?, 'entrada', 'receita', 'Vendas', ?, ?, 'pago', ?, ?, ?, 'sale', ?)");
                        $stmtFin->execute([$tid, "Recebimento {$code}", $downPayment, $saleDate, $saleDate, $paymentMethod, $saleId]);
                    }
                    $remaining = round($totalAmount - $downPayment, 2);
                    if ($remaining > 0) {
                        $stmtFinPend = $pdo->prepare("INSERT INTO financial_transactions (tenant_id, type, classification, category, description, amount, status, due_date, payment_method, reference_type, reference_id) VALUES (?, 'entrada', 'receita', 'Vendas', ?, ?, 'pendente', ?, ?, 'sale', ?)");
                        $stmtFinPend->execute([$tid, "Saldo a receber {$code}", $remaining, $dueDate, $paymentMethod, $saleId]);
                    }

                    recalculateSalePaymentStatus($pdo, $saleId);
                    self::createProductionOrderFromSale($pdo, $saleId, (int)$data['customer_id'], $code, implode(', ', $summaryParts), $totalQty, $saleDate, $dueDate, $totalTimeMin / 60.0, $totalCost, $primaryInventoryId, $totalWeight, $user['name']);
                }

                logAudit($pdo, 'create', 'sales', $saleId, "{$saleType} {$code} criada no valor de R$ {$totalAmount}");
                $pdo->commit();
                jsonResponse(['id' => $saleId, 'code' => $code, 'message' => ucfirst($saleType) . " {$code} registrada com sucesso!"], 201);
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
        }

        if ($action === 'convert' && $id && $method === 'POST') {
            $stmt = $pdo->prepare("SELECT * FROM sales WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$id, $tid]);
            $sale = $stmt->fetch();
            if (!$sale) jsonResponse(['error' => 'Orçamento não encontrado.'], 404);
            if ($sale['sale_type'] === 'venda') {
                jsonResponse(['error' => 'Este registro já é um pedido aprovado.'], 422);
            }

            $user = requireAuth();
            $newCode = str_replace('ORC-', 'VND-', $sale['code']);
            if ($newCode === $sale['code']) $newCode = 'VND-' . $sale['code'];

            $stmtItems = $pdo->prepare("SELECT si.*, p.inventory_item_id FROM sale_items si LEFT JOIN products p ON p.id = si.product_id WHERE si.sale_id = ?");
            $stmtItems->execute([$id]);
            $items = $stmtItems->fetchAll();

            $totalQty = 0; $totalCost = 0; $totalTimeMin = 0; $totalWeight = 0; $parts = []; $invId = null;
            foreach ($items as $it) {
                $totalQty += (int)$it['quantity'];
                $totalCost += (float)$it['unit_cost'] * (int)$it['quantity'];
                $totalTimeMin += (int)$it['print_time_min'] * (int)$it['quantity'];
                $totalWeight += (float)$it['weight_g'] * (int)$it['quantity'];
                $parts[] = "{$it['quantity']}x {$it['description']}";
                if (!$invId && !empty($it['inventory_item_id'])) $invId = (int)$it['inventory_item_id'];
            }

            $pdo->beginTransaction();
            try {
                $upd = $pdo->prepare("UPDATE sales SET sale_type = 'venda', status = 'aprovado', code = ? WHERE id = ? AND tenant_id = ?");
                $upd->execute([$newCode, $id, $tid]);

                $stmtFin = $pdo->prepare("INSERT INTO financial_transactions (tenant_id, type, classification, category, description, amount, status, due_date, payment_method, reference_type, reference_id) VALUES (?, 'entrada', 'receita', 'Vendas', ?, ?, 'pendente', ?, ?, 'sale', ?)");
                $stmtFin->execute([$tid, "Pedido convertido {$newCode}", $sale['total_amount'], $sale['due_date'] ?: date('Y-m-d', strtotime('+7 days')), $sale['payment_method'], $id]);

                self::createProductionOrderFromSale($pdo, $id, (int)$sale['customer_id'], $newCode, implode(', ', $parts), $totalQty, date('Y-m-d'), $sale['due_date'] ?: date('Y-m-d', strtotime('+5 days')), $totalTimeMin / 60.0, $totalCost, $invId, $totalWeight, $user['name']);

                logAudit($pdo, 'convert', 'sales', $id, "Orçamento {$sale['code']} convertido em pedido {$newCode}");
                $pdo->commit();
                jsonResponse(['message' => "Orçamento convertido no pedido {$newCode} e enviado para a produção!"]);
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
        }

        if ($action === 'payment' && $id && $method === 'POST') {
            $data = getJsonBody();
            validateRequired($data, ['amount', 'payment_method']);
            $amount = round((float)$data['amount'], 2);
            if ($amount <= 0) jsonResponse(['error' => 'O valor do pagamento deve ser maior que zero.'], 422);

            $stmt = $pdo->prepare("SELECT * FROM sales WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$id, $tid]);
            $sale = $stmt->fetch();
            if (!$sale) jsonResponse(['error' => 'Venda não encontrada.'], 404);

            $remaining = round((float)$sale['total_amount'] - (float)$sale['paid_amount'], 2);
            if ($amount > $remaining + 0.01) {
                jsonResponse(['error' => "Valor excede o saldo pendente (R$ " . number_format($remaining, 2, ',', '.') . ")."], 422);
            }

            $user = requireAuth();
            $payDate = $data['payment_date'] ?: date('Y-m-d');
            $pdo->beginTransaction();
            try {
                $stmtPay = $pdo->prepare("INSERT INTO sale_payments (sale_id, amount, payment_method, payment_date, notes, created_by) VALUES (?, ?, ?, ?, ?, ?)");
                $stmtPay->execute([$id, $amount, $data['payment_method'], $payDate, $data['notes'] ?? 'Pagamento registrado', $user['id']]);

                // Atualiza ou cria lançamento financeiro
                $stmtFin = $pdo->prepare("INSERT INTO financial_transactions (tenant_id, type, classification, category, description, amount, status, due_date, paid_date, payment_method, reference_type, reference_id) VALUES (?, 'entrada', 'receita', 'Vendas', ?, ?, 'pago', ?, ?, ?, 'sale', ?)");
                $stmtFin->execute([$tid, "Pagamento {$sale['code']}", $amount, $payDate, $payDate, $data['payment_method'], $id]);

                // Reduz ou quita pendência financeira vinculada
                $stmtPend = $pdo->prepare("SELECT id, amount FROM financial_transactions WHERE reference_type = 'sale' AND reference_id = ? AND status = 'pendente' ORDER BY id ASC LIMIT 1");
                $stmtPend->execute([$id]);
                if ($pend = $stmtPend->fetch()) {
                    $newPend = round((float)$pend['amount'] - $amount, 2);
                    if ($newPend <= 0.01) {
                        $pdo->prepare("DELETE FROM financial_transactions WHERE id = ?")->execute([$pend['id']]);
                    } else {
                        $pdo->prepare("UPDATE financial_transactions SET amount = ? WHERE id = ?")->execute([$newPend, $pend['id']]);
                    }
                }

                recalculateSalePaymentStatus($pdo, $id);
                logAudit($pdo, 'payment', 'sales', $id, "Pagamento de R$ {$amount} em {$sale['code']}");
                $pdo->commit();
                jsonResponse(['message' => 'Pagamento registrado e financeiro atualizado!']);
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
        }

        if ($action === 'item' && $id && $method === 'DELETE') {
            $stmt = $pdo->prepare("SELECT code FROM sales WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$id, $tid]);
            $sale = $stmt->fetch();
            if (!$sale) jsonResponse(['error' => 'Registro não encontrado.'], 404);

            $pdo->beginTransaction();
            try {
                $pdo->prepare("DELETE FROM sale_payments WHERE sale_id = ?")->execute([$id]);
                $pdo->prepare("DELETE FROM sale_items WHERE sale_id = ?")->execute([$id]);
                $pdo->prepare("DELETE FROM financial_transactions WHERE reference_type = 'sale' AND reference_id = ?")->execute([$id]);
                $pdo->prepare("DELETE FROM production_orders WHERE sale_id = ?")->execute([$id]);
                $pdo->prepare("DELETE FROM sales WHERE id = ?")->execute([$id]);
                logAudit($pdo, 'delete', 'sales', $id, "Excluída venda {$sale['code']}");
                $pdo->commit();
                jsonResponse(['message' => 'Venda/Orçamento excluído com sucesso.']);
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
        }

        jsonResponse(['error' => 'Rota de vendas inválida.'], 404);
    }

    private static function createProductionOrderFromSale(PDO $pdo, int $saleId, int $customerId, string $saleCode, string $summary, int $qty, string $entryDate, string $dueDate, float $estHours, float $estCost, ?int $invId, float $estWeight, string $userName): void
    {
        $opNum = 'OP-' . date('Y') . '-' . str_pad((string)random_int(100, 9999), 4, '0', STR_PAD_LEFT);
        $stmt = $pdo->prepare("INSERT INTO production_orders (tenant_id, order_number, sale_id, customer_id, title, products_summary, total_qty, stage, priority, entry_date, due_date, estimated_hours, estimated_cost, responsible_name, inventory_item_id, estimated_weight_g, notes)
                               VALUES (?, ?, ?, ?, ?, ?, ?, 'aguardando_producao', 'normal', ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            currentTenantId(),
            $opNum,
            $saleId,
            $customerId,
            "Pedido {$saleCode}",
            $summary,
            $qty,
            $entryDate,
            $dueDate,
            round(max(0.5, $estHours), 2),
            round($estCost, 2),
            $userName,
            $invId,
            round($estWeight, 2),
            "Gerado automaticamente pela venda {$saleCode}"
        ]);
        $opId = (int)$pdo->lastInsertId();
        $pdo->prepare("INSERT INTO production_history (order_id, from_stage, to_stage, notes, user_name) VALUES (?, NULL, 'aguardando_producao', 'Ordem criada a partir da venda aprovada', ?)")
            ->execute([$opId, $userName]);
    }
}

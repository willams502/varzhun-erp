<?php
/**
 * Controlador Master Admin: Controle de Acessos e Cobranças de Clientes SaaS (Tenants).
 */
class SaasAdminController
{
    public static function handle(PDO $pdo, string $action, string $method, ?int $id): void
    {
        $today = date('Y-m-d');
        // Marca faturas vencidas como atrasadas automaticamente
        $pdo->prepare("UPDATE saas_invoices SET status = 'atrasado' WHERE status = 'pendente' AND due_date < ?")->execute([$today]);

        if ($action === 'index' && $method === 'GET') {
            $tenants = $pdo->query("SELECT t.*,
                (SELECT COUNT(*) FROM users u WHERE u.tenant_id = t.id) AS users_count,
                (SELECT COALESCE(SUM(i.amount), 0) FROM saas_invoices i WHERE i.tenant_id = t.id AND i.status IN ('pendente','atrasado')) AS pending_amount
                FROM saas_tenants t ORDER BY t.id ASC")->fetchAll();

            $invoices = $pdo->query("SELECT i.*, t.company_name, t.whatsapp
                FROM saas_invoices i
                LEFT JOIN saas_tenants t ON t.id = i.tenant_id
                ORDER BY i.due_date DESC, i.id DESC")->fetchAll();

            $allUsers = $pdo->query("SELECT u.id, u.tenant_id, u.name, u.email, u.role, u.active, u.created_at, t.company_name, t.access_status AS tenant_status
                FROM users u
                LEFT JOIN saas_tenants t ON t.id = u.tenant_id
                ORDER BY u.id ASC")->fetchAll();

            $mrr = 0; $activeCount = 0; $blockedCount = 0;
            foreach ($tenants as $t) {
                if ((int)$t['id'] === 1) continue; // Exclui matriz da contagem comercial
                if (in_array($t['access_status'], ['ativo', 'trial'], true)) {
                    $activeCount++;
                    if ($t['access_status'] === 'ativo') $mrr += (float)$t['monthly_price'];
                } else {
                    $blockedCount++;
                }
            }

            $ym = date('Y-m');
            $receivedMonth = 0; $pendingTotal = 0; $overdueTotal = 0;
            foreach ($invoices as $inv) {
                $amt = (float)$inv['amount'];
                if ($inv['status'] === 'pago' && str_starts_with((string)$inv['paid_date'], $ym)) {
                    $receivedMonth += $amt;
                } elseif ($inv['status'] === 'pendente') {
                    $pendingTotal += $amt;
                } elseif ($inv['status'] === 'atrasado') {
                    $overdueTotal += $amt;
                }
            }

            jsonResponse([
                'summary' => [
                    'mrr' => round($mrr, 2),
                    'received_month' => round($receivedMonth, 2),
                    'pending_total' => round($pendingTotal, 2),
                    'overdue_total' => round($overdueTotal, 2),
                    'active_tenants' => $activeCount,
                    'blocked_tenants' => $blockedCount,
                ],
                'tenants' => $tenants,
                'invoices' => $invoices,
                'users' => $allUsers,
            ]);
        }

        if ($action === 'tenant' && $id && $method === 'GET') {
            $stmt = $pdo->prepare("SELECT * FROM saas_tenants WHERE id = ?");
            $stmt->execute([$id]);
            $tenant = $stmt->fetch();
            if (!$tenant) jsonResponse(['error' => 'Cliente SaaS não encontrado.'], 404);

            $stmtU = $pdo->prepare("SELECT id, name, email, role, active, created_at FROM users WHERE tenant_id = ? ORDER BY id ASC");
            $stmtU->execute([$id]);

            $stmtI = $pdo->prepare("SELECT * FROM saas_invoices WHERE tenant_id = ? ORDER BY due_date DESC, id DESC");
            $stmtI->execute([$id]);

            jsonResponse(['tenant' => $tenant, 'users' => $stmtU->fetchAll(), 'invoices' => $stmtI->fetchAll()]);
        }

        if ($action === 'tenant' && $method === 'POST') {
            $data = getJsonBody();
            validateRequired($data, ['company_name']);
            $stmt = $pdo->prepare("INSERT INTO saas_tenants (company_name, owner_name, document, email, whatsapp, plan_name, monthly_price, billing_day, access_status, expires_at, max_users, notes)
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                trim($data['company_name']),
                trim($data['owner_name'] ?? ''),
                trim($data['document'] ?? ''),
                trim($data['email'] ?? ''),
                trim($data['whatsapp'] ?? ''),
                trim($data['plan_name'] ?? 'Profissional'),
                max(0, (float)($data['monthly_price'] ?? 149.90)),
                max(1, min(28, (int)($data['billing_day'] ?? 10))),
                $data['access_status'] ?? 'ativo',
                !empty($data['expires_at']) ? $data['expires_at'] : date('Y-m-d', strtotime('+30 days')),
                max(1, (int)($data['max_users'] ?? 5)),
                trim($data['notes'] ?? ''),
            ]);
            $newId = (int)$pdo->lastInsertId();
            logAudit($pdo, 'create', 'saas_tenants', $newId, 'Novo cliente SaaS: ' . $data['company_name']);
            jsonResponse(['id' => $newId, 'message' => 'Empresa cliente cadastrada com sucesso!'], 201);
        }

        if ($action === 'tenant' && $id && $method === 'PUT') {
            $data = getJsonBody();
            validateRequired($data, ['company_name']);
            $stmt = $pdo->prepare("UPDATE saas_tenants SET company_name=?, owner_name=?, document=?, email=?, whatsapp=?, plan_name=?, monthly_price=?, billing_day=?, access_status=?, expires_at=?, max_users=?, notes=? WHERE id=?");
            $stmt->execute([
                trim($data['company_name']),
                trim($data['owner_name'] ?? ''),
                trim($data['document'] ?? ''),
                trim($data['email'] ?? ''),
                trim($data['whatsapp'] ?? ''),
                trim($data['plan_name'] ?? 'Profissional'),
                max(0, (float)($data['monthly_price'] ?? 149.90)),
                max(1, min(28, (int)($data['billing_day'] ?? 10))),
                $data['access_status'] ?? 'ativo',
                !empty($data['expires_at']) ? $data['expires_at'] : null,
                max(1, (int)($data['max_users'] ?? 5)),
                trim($data['notes'] ?? ''),
                $id
            ]);
            jsonResponse(['message' => 'Dados da empresa cliente atualizados!']);
        }

        if ($action === 'tenant_status' && $id && $method === 'POST') {
            if ($id === 1) jsonResponse(['error' => 'A conta Matriz Admin não pode ser bloqueada.'], 422);
            $data = getJsonBody();
            $status = $data['access_status'] ?? '';
            if (!in_array($status, ['ativo', 'trial', 'suspenso', 'bloqueado', 'cancelado'], true)) {
                jsonResponse(['error' => 'Status de acesso inválido.'], 422);
            }
            $pdo->prepare("UPDATE saas_tenants SET access_status = ? WHERE id = ?")->execute([$status, $id]);
            logAudit($pdo, 'status', 'saas_tenants', $id, "Status alterado para {$status}");
            jsonResponse(['message' => "Acesso do cliente alterado para: {$status}."]);
        }

        if ($action === 'tenant_user' && $id && $method === 'POST') {
            $data = getJsonBody();
            validateRequired($data, ['name', 'email', 'password', 'role']);
            $targetTenantId = (int)$id;
            // Se um novo usuário comercial for criado sem empresa própria (ou na Matriz), cria uma empresa para ele se solicitado ou usa o tenant selecionado
            if (!empty($data['create_own_company']) || ($targetTenantId === 1 && !empty($data['company_name']))) {
                $compName = trim($data['company_name'] ?? $data['name']);
                $pdo->prepare("INSERT INTO saas_tenants (company_name, owner_name, email, plan_name, monthly_price, billing_day, access_status, expires_at, max_users) VALUES (?, ?, ?, 'Profissional', 149.90, 10, 'ativo', ?, 5)")
                    ->execute([$compName, trim($data['name']), trim($data['email']), date('Y-m-d', strtotime('+30 days'))]);
                $targetTenantId = (int)$pdo->lastInsertId();
            }
            $stmtT = $pdo->prepare("SELECT max_users FROM saas_tenants WHERE id = ?");
            $stmtT->execute([$targetTenantId]);
            $maxUsers = (int)$stmtT->fetchColumn();
            $curUsers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE tenant_id = " . $targetTenantId)->fetchColumn();
            if ($maxUsers > 0 && $curUsers >= $maxUsers) {
                jsonResponse(['error' => "Limite de usuários do plano atingido ({$maxUsers}). Aumente o limite na empresa."], 422);
            }
            $pdo->prepare("INSERT INTO users (tenant_id, name, email, password_hash, role, active) VALUES (?, ?, ?, ?, ?, 1)")
                ->execute([$targetTenantId, trim($data['name']), trim($data['email']), password_hash($data['password'], PASSWORD_DEFAULT), $data['role']]);
            jsonResponse(['message' => 'Usuário vinculado à empresa cliente!'], 201);
        }

        if ($action === 'user_toggle' && $id && $method === 'POST') {
            $pdo->prepare("UPDATE users SET active = CASE WHEN active = 1 THEN 0 ELSE 1 END WHERE id = ? AND id != 1")->execute([$id]);
            jsonResponse(['message' => 'Status do usuário alterado!']);
        }

        if ($action === 'user' && $id && $method === 'PUT') {
            $data = getJsonBody();
            validateRequired($data, ['name', 'email', 'role']);
            $tenantId = !empty($data['tenant_id']) ? (int)$data['tenant_id'] : 1;
            $active = isset($data['active']) ? (int)$data['active'] : 1;
            if ($id === 1) {
                $active = 1; // Protege admin principal
                $data['role'] = 'admin';
            }
            if (!empty($data['password'])) {
                $pdo->prepare("UPDATE users SET tenant_id=?, name=?, email=?, role=?, active=?, password_hash=? WHERE id=?")
                    ->execute([$tenantId, trim($data['name']), trim($data['email']), $data['role'], $active, password_hash($data['password'], PASSWORD_DEFAULT), $id]);
            } else {
                $pdo->prepare("UPDATE users SET tenant_id=?, name=?, email=?, role=?, active=? WHERE id=?")
                    ->execute([$tenantId, trim($data['name']), trim($data['email']), $data['role'], $active, $id]);
            }
            logAudit($pdo, 'update', 'users', $id, 'Usuário administrado pelo Master Admin');
            jsonResponse(['message' => 'Usuário atualizado com sucesso!']);
        }

        if ($action === 'user' && $id && $method === 'DELETE') {
            $current = requireAuth();
            if ($id === 1 || $id === (int)$current['id']) {
                jsonResponse(['error' => 'Não é possível excluir o administrador principal ou sua própria conta.'], 422);
            }
            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
            logAudit($pdo, 'delete', 'users', $id, 'Usuário excluído pelo Master Admin');
            jsonResponse(['message' => 'Usuário excluído com sucesso.']);
        }

        if ($action === 'invoice' && $method === 'POST') {
            $data = getJsonBody();
            if (!empty($data['batch_all'])) {
                $ym = $data['reference_month'] ?? date('Y-m');
                $tenants = $pdo->query("SELECT * FROM saas_tenants WHERE id > 1 AND access_status IN ('ativo','trial') AND monthly_price > 0")->fetchAll();
                $created = 0;
                $stmtChk = $pdo->prepare("SELECT COUNT(*) FROM saas_invoices WHERE tenant_id = ? AND reference_month = ?");
                $stmtIns = $pdo->prepare("INSERT INTO saas_invoices (tenant_id, reference_month, description, amount, due_date, payment_method, status) VALUES (?, ?, ?, ?, ?, 'PIX', 'pendente')");
                foreach ($tenants as $t) {
                    $stmtChk->execute([$t['id'], $ym]);
                    if ((int)$stmtChk->fetchColumn() === 0) {
                        $day = str_pad((string)$t['billing_day'], 2, '0', STR_PAD_LEFT);
                        $stmtIns->execute([$t['id'], $ym, "Mensalidade {$ym} — Plano {$t['plan_name']}", $t['monthly_price'], "{$ym}-{$day}"]);
                        $created++;
                    }
                }
                jsonResponse(['message' => "{$created} cobrança(s) gerada(s) para {$ym}!"], 201);
            }

            validateRequired($data, ['tenant_id', 'amount', 'due_date']);
            $ym = $data['reference_month'] ?? substr($data['due_date'], 0, 7);
            $pdo->prepare("INSERT INTO saas_invoices (tenant_id, reference_month, description, amount, due_date, payment_method, status, notes) VALUES (?, ?, ?, ?, ?, ?, 'pendente', ?)")
                ->execute([
                    (int)$data['tenant_id'],
                    $ym,
                    trim($data['description'] ?? "Mensalidade {$ym}"),
                    max(0.01, (float)$data['amount']),
                    $data['due_date'],
                    $data['payment_method'] ?? 'PIX',
                    trim($data['notes'] ?? ''),
                ]);
            jsonResponse(['message' => 'Cobrança gerada com sucesso!'], 201);
        }

        if ($action === 'invoice_status' && $id && $method === 'POST') {
            $data = getJsonBody();
            $status = $data['status'] ?? '';
            if (!in_array($status, ['pendente', 'atrasado', 'cancelado', 'pago'], true)) {
                jsonResponse(['error' => 'Status de mensalidade inválido.'], 422);
            }
            $pdo->prepare("UPDATE saas_invoices SET status = ? WHERE id = ?")->execute([$status, $id]);
            jsonResponse(['message' => "Mensalidade marcada como: {$status}."]);
        }

        if ($action === 'invoice_pay' && $id && $method === 'POST') {
            $data = getJsonBody();
            $stmt = $pdo->prepare("SELECT * FROM saas_invoices WHERE id = ?");
            $stmt->execute([$id]);
            $inv = $stmt->fetch();
            if (!$inv) jsonResponse(['error' => 'Cobrança não encontrada.'], 404);

            $paidDate = $data['paid_date'] ?: date('Y-m-d');
            $methodPay = $data['payment_method'] ?: ($inv['payment_method'] ?: 'PIX');

            $pdo->beginTransaction();
            try {
                $pdo->prepare("UPDATE saas_invoices SET status = 'pago', paid_date = ?, payment_method = ? WHERE id = ?")
                    ->execute([$paidDate, $methodPay, $id]);

                // Reativa o cliente e estende vencimento em 30 dias automaticamente
                $newExpiry = date('Y-m-d', strtotime('+30 days'));
                $pdo->prepare("UPDATE saas_tenants SET access_status = 'ativo', expires_at = ? WHERE id = ? AND id > 1")
                    ->execute([$newExpiry, $inv['tenant_id']]);

                $pdo->commit();
                jsonResponse(['message' => 'Pagamento confirmado! Acesso do cliente renovado por +30 dias.']);
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
        }

        if ($action === 'tenant' && $id && $method === 'DELETE') {
            if ($id === 1) jsonResponse(['error' => 'Não é possível excluir a Matriz Admin.'], 422);
            $pdo->prepare("DELETE FROM saas_invoices WHERE tenant_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM users WHERE tenant_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM saas_tenants WHERE id = ?")->execute([$id]);
            jsonResponse(['message' => 'Empresa cliente removida.']);
        }

        if ($action === 'invoice' && $id && $method === 'DELETE') {
            $pdo->prepare("DELETE FROM saas_invoices WHERE id = ?")->execute([$id]);
            jsonResponse(['message' => 'Cobrança removida.']);
        }

        jsonResponse(['error' => 'Rota SaaS Admin inválida.'], 404);
    }
}

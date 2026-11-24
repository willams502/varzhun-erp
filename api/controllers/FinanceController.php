<?php
/**
 * Controlador Financeiro (Contas a Receber, Contas a Pagar e Fluxo de Caixa).
 */
class FinanceController
{
    public static function handle(PDO $pdo, string $action, string $method, ?int $id): void
    {
        $tid = currentTenantId();
        if ($action === 'index' && $method === 'GET') {
            $where = ['tenant_id = ?'];
            $params = [$tid];
            if (!empty($_GET['type'])) {
                $where[] = 'type = ?';
                $params[] = $_GET['type'];
            }
            if (!empty($_GET['status'])) {
                $where[] = 'status = ?';
                $params[] = $_GET['status'];
            }
            if (!empty($_GET['classification'])) {
                $where[] = 'classification = ?';
                $params[] = $_GET['classification'];
            }
            if (!empty($_GET['q'])) {
                $where[] = '(description LIKE ? OR category LIKE ?)';
                $like = '%' . $_GET['q'] . '%';
                array_push($params, $like, $like);
            }

            // Atualiza automaticamente títulos vencidos
            $today = date('Y-m-d');
            $pdo->prepare("UPDATE financial_transactions SET status = 'atrasado' WHERE tenant_id = ? AND status = 'pendente' AND due_date < ?")->execute([$tid, $today]);

            $stmt = $pdo->prepare("SELECT * FROM financial_transactions WHERE " . implode(' AND ', $where) . " ORDER BY due_date DESC, id DESC");
            $stmt->execute($params);
            $transactions = $stmt->fetchAll();

            // Resumo financeiro consolidado
            $stmtAll = $pdo->prepare("SELECT * FROM financial_transactions WHERE tenant_id = ?");
            $stmtAll->execute([$tid]);
            $all = $stmtAll->fetchAll();
            $entradasPagas = 0; $saidasPagas = 0;
            $aReceber = 0; $aReceberAtrasado = 0;
            $aPagar = 0; $aPagarAtrasado = 0;
            $custosProd = 0; $despesasOp = 0; $investimentos = 0;

            foreach ($all as $t) {
                $amt = (float)$t['amount'];
                if ($t['type'] === 'entrada') {
                    if ($t['status'] === 'pago') $entradasPagas += $amt;
                    else {
                        $aReceber += $amt;
                        if ($t['status'] === 'atrasado') $aReceberAtrasado += $amt;
                    }
                } else {
                    if ($t['status'] === 'pago') $saidasPagas += $amt;
                    else {
                        $aPagar += $amt;
                        if ($t['status'] === 'atrasado') $aPagarAtrasado += $amt;
                    }
                    if ($t['classification'] === 'custo_producao') $custosProd += $amt;
                    elseif ($t['classification'] === 'despesa_operacional') $despesasOp += $amt;
                    elseif ($t['classification'] === 'investimento') $investimentos += $amt;
                }
            }

            $saldoAtual = round($entradasPagas - $saidasPagas, 2);
            $previsaoSaldo = round($saldoAtual + $aReceber - $aPagar, 2);

            jsonResponse([
                'transactions' => $transactions,
                'summary' => [
                    'entradas_pagas' => round($entradasPagas, 2),
                    'saidas_pagas' => round($saidasPagas, 2),
                    'saldo_atual' => $saldoAtual,
                    'a_receber' => round($aReceber, 2),
                    'a_receber_atrasado' => round($aReceberAtrasado, 2),
                    'a_pagar' => round($aPagar, 2),
                    'a_pagar_atrasado' => round($aPagarAtrasado, 2),
                    'previsao_saldo' => $previsaoSaldo,
                    'custos_producao' => round($custosProd, 2),
                    'despesas_operacionais' => round($despesasOp, 2),
                    'investimentos' => round($investimentos, 2),
                ]
            ]);
        }

        if ($action === 'index' && $method === 'POST') {
            $data = getJsonBody();
            validateRequired($data, ['type', 'classification', 'category', 'description', 'amount', 'due_date']);
            $amount = round((float)$data['amount'], 2);
            if ($amount <= 0) jsonResponse(['error' => 'O valor deve ser maior que zero.'], 422);

            $status = $data['status'] ?? 'pendente';
            $paidDate = $status === 'pago' ? ($data['paid_date'] ?: date('Y-m-d')) : null;

            $stmt = $pdo->prepare("INSERT INTO financial_transactions (tenant_id, type, classification, category, description, amount, status, due_date, paid_date, payment_method, is_recurring, recurrence_interval)
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $tid,
                $data['type'],
                $data['classification'],
                trim($data['category']),
                trim($data['description']),
                $amount,
                $status,
                $data['due_date'],
                $paidDate,
                $data['payment_method'] ?? 'PIX',
                !empty($data['is_recurring']) ? 1 : 0,
                $data['recurrence_interval'] ?? null,
            ]);
            jsonResponse(['id' => (int)$pdo->lastInsertId(), 'message' => 'Lançamento financeiro registrado!'], 201);
        }

        if ($action === 'pay' && $id && $method === 'POST') {
            $data = getJsonBody();
            $paidDate = $data['paid_date'] ?: date('Y-m-d');
            $stmt = $pdo->prepare("UPDATE financial_transactions SET status = 'pago', paid_date = ? WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$paidDate, $id, $tid]);
            jsonResponse(['message' => 'Lançamento baixado como pago!']);
        }

        if ($action === 'item' && $id && $method === 'DELETE') {
            $pdo->prepare("DELETE FROM financial_transactions WHERE id = ? AND tenant_id = ?")->execute([$id, $tid]);
            jsonResponse(['message' => 'Lançamento financeiro excluído.']);
        }

        jsonResponse(['error' => 'Rota financeira inválida.'], 404);
    }
}

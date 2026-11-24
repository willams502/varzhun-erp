<?php
/**
 * Controlador de Impressoras 3D e Histórico de Utilização.
 */
class PrintersController
{
    public static function handle(PDO $pdo, string $action, string $method, ?int $id): void
    {
        $tid = currentTenantId();
        if ($action === 'index' && $method === 'GET') {
            $stmt = $pdo->prepare("SELECT p.*,
                (SELECT COALESCE(SUM(l.prints_count), 0) FROM printer_logs l WHERE l.printer_id = p.id) AS total_prints,
                (SELECT COALESCE(SUM(l.success_count), 0) FROM printer_logs l WHERE l.printer_id = p.id) AS total_success,
                (SELECT COALESCE(SUM(l.fail_count), 0) FROM printer_logs l WHERE l.printer_id = p.id) AS total_fails,
                (SELECT COALESCE(SUM(l.maintenance_cost), 0) FROM printer_logs l WHERE l.printer_id = p.id) AS total_maintenance_cost
                FROM printers p WHERE p.tenant_id = ? ORDER BY p.id DESC");
            $stmt->execute([$tid]);
            jsonResponse(['printers' => $stmt->fetchAll()]);
        }

        if ($action === 'item' && $id && $method === 'GET') {
            $stmt = $pdo->prepare("SELECT * FROM printers WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$id, $tid]);
            $printer = $stmt->fetch();
            if (!$printer) jsonResponse(['error' => 'Impressora não encontrada.'], 404);

            $stmtLogs = $pdo->prepare("SELECT * FROM printer_logs WHERE printer_id = ? ORDER BY log_date DESC, id DESC");
            $stmtLogs->execute([$id]);
            jsonResponse(['printer' => $printer, 'logs' => $stmtLogs->fetchAll()]);
        }

        if ($action === 'index' && $method === 'POST') {
            $data = getJsonBody();
            validateRequired($data, ['name', 'technology']);
            $stmt = $pdo->prepare("INSERT INTO printers (tenant_id, name, brand, model, identifier_code, build_volume, technology, status, cost_per_hour, energy_kwh, acquisition_date, acquisition_value, total_hours, notes)
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $tid,
                trim($data['name']),
                trim($data['brand'] ?? ''),
                trim($data['model'] ?? ''),
                trim($data['identifier_code'] ?? 'IMP-' . random_int(10, 99)),
                trim($data['build_volume'] ?? ''),
                trim($data['technology']),
                $data['status'] ?? 'disponivel',
                max(0, (float)($data['cost_per_hour'] ?? 2.50)),
                max(0, (float)($data['energy_kwh'] ?? 0.15)),
                $data['acquisition_date'] ?: null,
                max(0, (float)($data['acquisition_value'] ?? 0)),
                max(0, (float)($data['total_hours'] ?? 0)),
                trim($data['notes'] ?? ''),
            ]);
            $newId = (int)$pdo->lastInsertId();
            logAudit($pdo, 'create', 'printers', $newId, 'Impressora cadastrada: ' . $data['name']);
            jsonResponse(['id' => $newId, 'message' => 'Impressora cadastrada com sucesso!'], 201);
        }

        if ($action === 'item' && $id && $method === 'PUT') {
            $data = getJsonBody();
            validateRequired($data, ['name', 'technology']);
            $stmt = $pdo->prepare("UPDATE printers SET name=?, brand=?, model=?, identifier_code=?, build_volume=?, technology=?, status=?, cost_per_hour=?, energy_kwh=?, acquisition_date=?, acquisition_value=?, total_hours=?, notes=? WHERE id=? AND tenant_id=?");
            $stmt->execute([
                trim($data['name']),
                trim($data['brand'] ?? ''),
                trim($data['model'] ?? ''),
                trim($data['identifier_code'] ?? ''),
                trim($data['build_volume'] ?? ''),
                trim($data['technology']),
                $data['status'] ?? 'disponivel',
                max(0, (float)($data['cost_per_hour'] ?? 2.50)),
                max(0, (float)($data['energy_kwh'] ?? 0.15)),
                $data['acquisition_date'] ?: null,
                max(0, (float)($data['acquisition_value'] ?? 0)),
                max(0, (float)($data['total_hours'] ?? 0)),
                trim($data['notes'] ?? ''),
                $id,
                $tid
            ]);
            logAudit($pdo, 'update', 'printers', $id, 'Impressora atualizada');
            jsonResponse(['message' => 'Impressora atualizada com sucesso!']);
        }

        if ($action === 'log' && $id && $method === 'POST') {
            $data = getJsonBody();
            $logType = $data['log_type'] ?? 'impressao';
            $hours = max(0, (float)($data['hours_worked'] ?? 0));
            $prints = max(0, (int)($data['prints_count'] ?? 0));
            $success = max(0, (int)($data['success_count'] ?? 0));
            $fails = max(0, (int)($data['fail_count'] ?? 0));
            $maintCost = max(0, (float)($data['maintenance_cost'] ?? 0));
            $logDate = $data['log_date'] ?: date('Y-m-d');

            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare("INSERT INTO printer_logs (printer_id, log_type, hours_worked, prints_count, success_count, fail_count, maintenance_cost, description, log_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$id, $logType, $hours, $prints, $success, $fails, $maintCost, trim($data['description'] ?? ''), $logDate]);

                if ($hours > 0) {
                    $pdo->prepare("UPDATE printers SET total_hours = total_hours + ? WHERE id = ?")->execute([$hours, $id]);
                }

                if ($maintCost > 0) {
                    $printerName = $pdo->query("SELECT name FROM printers WHERE id = " . (int)$id)->fetchColumn();
                    $pdo->prepare("INSERT INTO financial_transactions (type, classification, category, description, amount, status, due_date, paid_date, payment_method, reference_type, reference_id) VALUES ('saida', 'custo_producao', 'Manutenção de Impressoras', ?, ?, 'pago', ?, ?, 'PIX', 'printer', ?)")
                        ->execute(["Manutenção {$printerName}: " . ($data['description'] ?? ''), $maintCost, $logDate, $logDate, $id]);
                }

                $pdo->commit();
                jsonResponse(['message' => 'Registro adicionado ao histórico da impressora!'], 201);
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
        }

        if ($action === 'item' && $id && $method === 'DELETE') {
            $pdo->prepare("DELETE FROM printer_logs WHERE printer_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM printers WHERE id = ? AND tenant_id = ?")->execute([$id, $tid]);
            jsonResponse(['message' => 'Impressora excluída com sucesso.']);
        }

        jsonResponse(['error' => 'Rota de impressoras inválida.'], 404);
    }
}

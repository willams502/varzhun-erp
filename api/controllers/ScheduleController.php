<?php
/**
 * Controlador de Agenda e Planejamento de Produção.
 */
class ScheduleController
{
    public static function handle(PDO $pdo, string $action, string $method, ?int $id): void
    {
        $tid = currentTenantId();
        if ($action === 'index' && $method === 'GET') {
            $where = ['s.tenant_id = ?'];
            $params = [$tid];
            if (!empty($_GET['printer_id'])) {
                $where[] = 's.printer_id = ?';
                $params[] = (int)$_GET['printer_id'];
            }
            if (!empty($_GET['from'])) {
                $where[] = 's.end_datetime >= ?';
                $params[] = $_GET['from'] . ' 00:00:00';
            }
            if (!empty($_GET['to'])) {
                $where[] = 's.start_datetime <= ?';
                $params[] = $_GET['to'] . ' 23:59:59';
            }

            $sql = "SELECT s.*, pr.name AS printer_name, po.order_number, po.due_date AS order_due_date
                    FROM production_schedules s
                    LEFT JOIN printers pr ON pr.id = s.printer_id
                    LEFT JOIN production_orders po ON po.id = s.production_order_id
                    WHERE " . implode(' AND ', $where) . "
                    ORDER BY s.start_datetime ASC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $schedules = $stmt->fetchAll();

            // Capacidade das impressoras nos próximos 7 dias
            $stmtPrn = $pdo->prepare("SELECT id, name, status FROM printers WHERE tenant_id = ? ORDER BY name");
            $stmtPrn->execute([$tid]);
            $printers = $stmtPrn->fetchAll();
            $stmtPendH = $pdo->prepare("SELECT COALESCE(SUM(estimated_hours), 0) FROM production_orders WHERE tenant_id = ? AND stage NOT IN ('entregue','cancelado')");
            $stmtPendH->execute([$tid]);
            $pendingHours = (float)$stmtPendH->fetchColumn();
            $availablePrinters = count(array_filter($printers, fn($p) => in_array($p['status'], ['disponivel', 'imprimindo', 'reservada'], true)));
            $weeklyCapacityHours = $availablePrinters * 7 * 16; // Estimativa realista de 16h úteis/dia por máquina ativa

            jsonResponse([
                'schedules' => $schedules,
                'capacity' => [
                    'active_printers' => $availablePrinters,
                    'weekly_capacity_hours' => $weeklyCapacityHours,
                    'pending_production_hours' => round($pendingHours, 1),
                    'utilization_forecast_percent' => $weeklyCapacityHours > 0 ? min(100, round(($pendingHours / $weeklyCapacityHours) * 100, 1)) : 0,
                ]
            ]);
        }

        if ($action === 'index' && $method === 'POST') {
            $data = getJsonBody();
            validateRequired($data, ['title', 'printer_id', 'start_datetime', 'end_datetime']);

            if ($data['end_datetime'] <= $data['start_datetime']) {
                jsonResponse(['error' => 'A data/hora de término deve ser posterior ao início.'], 422);
            }

            // Verifica conflito de agendamento na mesma impressora
            $stmtConf = $pdo->prepare("SELECT id, title, start_datetime, end_datetime FROM production_schedules
                                       WHERE printer_id = ? AND status != 'cancelado'
                                       AND tenant_id = ?
                                       AND start_datetime < ? AND end_datetime > ?");
            $stmtConf->execute([(int)$data['printer_id'], $tid, $data['end_datetime'], $data['start_datetime']]);
            $conflict = $stmtConf->fetch();
            if ($conflict && empty($data['ignore_conflict'])) {
                jsonResponse([
                    'error' => "Conflito de horário detectado com \"{$conflict['title']}\" ({$conflict['start_datetime']} até {$conflict['end_datetime']}).",
                    'conflict' => $conflict
                ], 409);
            }

            $stmt = $pdo->prepare("INSERT INTO production_schedules (tenant_id, title, schedule_type, production_order_id, printer_id, start_datetime, end_datetime, priority, status, notes)
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $tid,
                trim($data['title']),
                $data['schedule_type'] ?? 'impressao',
                !empty($data['production_order_id']) ? (int)$data['production_order_id'] : null,
                (int)$data['printer_id'],
                $data['start_datetime'],
                $data['end_datetime'],
                $data['priority'] ?? 'normal',
                $data['status'] ?? 'agendado',
                trim($data['notes'] ?? ''),
            ]);
            jsonResponse(['id' => (int)$pdo->lastInsertId(), 'message' => 'Agendamento criado com sucesso!'], 201);
        }

        if ($action === 'item' && $id && $method === 'PUT') {
            $data = getJsonBody();
            validateRequired($data, ['title', 'printer_id', 'start_datetime', 'end_datetime']);
            $stmt = $pdo->prepare("UPDATE production_schedules SET title=?, schedule_type=?, production_order_id=?, printer_id=?, start_datetime=?, end_datetime=?, priority=?, status=?, notes=? WHERE id=? AND tenant_id=?");
            $stmt->execute([
                trim($data['title']),
                $data['schedule_type'] ?? 'impressao',
                !empty($data['production_order_id']) ? (int)$data['production_order_id'] : null,
                (int)$data['printer_id'],
                $data['start_datetime'],
                $data['end_datetime'],
                $data['priority'] ?? 'normal',
                $data['status'] ?? 'agendado',
                trim($data['notes'] ?? ''),
                $id,
                $tid
            ]);
            jsonResponse(['message' => 'Agendamento atualizado!']);
        }

        if ($action === 'item' && $id && $method === 'DELETE') {
            $pdo->prepare("DELETE FROM production_schedules WHERE id = ? AND tenant_id = ?")->execute([$id, $tid]);
            jsonResponse(['message' => 'Agendamento removido.']);
        }

        jsonResponse(['error' => 'Rota de agenda inválida.'], 404);
    }
}

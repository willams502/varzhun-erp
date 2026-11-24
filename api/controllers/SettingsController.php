<?php
/**
 * Controlador de Configurações, Categorias, Usuários, Uploads e Backup.
 */
class SettingsController
{
    public static function handle(PDO $pdo, string $action, string $method, ?int $id): void
    {
        $tid = currentTenantId();
        if ($action === 'index' && $method === 'GET') {
            $rows = $pdo->prepare("SELECT key_name, value_text FROM settings WHERE key_name = ? OR key_name LIKE ?");
            $prefix = $tid . ':';
            $rows->execute([$tid . ':', $prefix . '%']);
            $settings = [];
            foreach ($rows->fetchAll() as $r) {
                $settings[str_starts_with($r['key_name'], $prefix) ? substr($r['key_name'], strlen($prefix)) : $r['key_name']] = $r['value_text'];
            }

            $stmtCat = $pdo->prepare("SELECT * FROM categories WHERE tenant_id = ? ORDER BY type, name");
            $stmtCat->execute([$tid]);
            $categories = $stmtCat->fetchAll();
            jsonResponse(['settings' => $settings, 'categories' => $categories]);
        }

        if ($action === 'index' && $method === 'POST') {
            $data = getJsonBody();
            $stmt = $pdo->prepare("REPLACE INTO settings (key_name, value_text) VALUES (?, ?)");
            foreach ($data as $k => $v) {
                $stmt->execute([$tid . ':' . (string)$k, (string)$v]);
            }
            jsonResponse(['message' => 'Configurações salvas com sucesso!']);
        }

        if ($action === 'category' && $method === 'POST') {
            $data = getJsonBody();
            validateRequired($data, ['type', 'name']);
            $pdo->prepare("INSERT INTO categories (tenant_id, type, name, description) VALUES (?, ?, ?, ?)")
                ->execute([$tid, $data['type'], trim($data['name']), trim($data['description'] ?? '')]);
            jsonResponse(['message' => 'Categoria adicionada!'], 201);
        }

        if ($action === 'category' && $id && $method === 'DELETE') {
            $pdo->prepare("DELETE FROM categories WHERE id = ? AND tenant_id = ?")->execute([$id, $tid]);
            jsonResponse(['message' => 'Categoria removida.']);
        }


        if ($action === 'clear_demo' && $method === 'POST') {
            $tables = ['production_history', 'production_schedules', 'production_orders', 'sale_payments', 'sale_items', 'sales', 'printer_logs', 'printers', 'products', 'inventory_movements', 'inventory_items', 'customer_interactions', 'customers', 'suppliers', 'financial_transactions'];
            foreach ($tables as $t) {
                if (in_array($t, ['production_orders', 'production_schedules', 'sales', 'printers', 'products', 'inventory_items', 'customers', 'suppliers', 'financial_transactions'], true)) {
                    $pdo->prepare("DELETE FROM {$t} WHERE tenant_id = ? AND is_demo = 1")->execute([$tid]);
                }
            }
            $pdo->prepare("REPLACE INTO settings (key_name, value_text) VALUES (?, '0')")->execute([$tid . ':demo_banner_enabled']);
            jsonResponse(['message' => 'Dados demonstrativos removidos! O sistema está limpo para uso real.']);
        }

        if ($action === 'backup' && $method === 'GET') {
            $tables = ['settings', 'categories', 'customers', 'suppliers', 'inventory_items', 'printers', 'products', 'cost_calculations', 'sales', 'sale_items', 'sale_payments', 'production_orders', 'production_schedules', 'financial_transactions'];
            $dump = ['exported_at' => date('c'), 'app' => APP_NAME, 'tables' => []];
            foreach ($tables as $t) {
                if ($t === 'settings') {
                    $prefix = $tid . ':';
                    $stmt = $pdo->prepare("SELECT key_name, value_text FROM settings WHERE key_name LIKE ?");
                    $stmt->execute([$prefix . '%']);
                    $dump['tables'][$t] = $stmt->fetchAll();
                } elseif ($t === 'categories') {
                    $stmt = $pdo->prepare("SELECT * FROM categories WHERE tenant_id = ?");
                    $stmt->execute([$tid]);
                    $dump['tables'][$t] = $stmt->fetchAll();
                } elseif (in_array($t, ['sale_items', 'sale_payments'], true)) {
                    $stmt = $pdo->prepare("SELECT c.* FROM {$t} c JOIN sales s ON s.id = c.sale_id WHERE s.tenant_id = ?");
                    $stmt->execute([$tid]);
                    $dump['tables'][$t] = $stmt->fetchAll();
                } else {
                    try {
                        $stmt = $pdo->prepare("SELECT * FROM {$t} WHERE tenant_id = ?");
                        $stmt->execute([$tid]);
                        $dump['tables'][$t] = $stmt->fetchAll();
                    } catch (Throwable $e) {
                        $dump['tables'][$t] = [];
                    }
                }
            }
            jsonResponse($dump);
        }

        jsonResponse(['error' => 'Rota de configurações inválida.'], 404);
    }

    public static function handleUpload(): void
    {
        if (empty($_FILES['file'])) {
            jsonResponse(['error' => 'Nenhum arquivo enviado.'], 422);
        }
        $file = $_FILES['file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['png', 'jpg', 'jpeg', 'webp', 'stl', '3mf', 'obj'];
        if (!in_array($ext, $allowed, true)) {
            jsonResponse(['error' => 'Formato de arquivo não permitido.'], 422);
        }
        if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0775, true);
        $safeName = date('Ymd_His_') . bin2hex(random_bytes(4)) . '.' . $ext;
        $dest = UPLOAD_DIR . '/' . $safeName;
        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            jsonResponse(['error' => 'Falha ao salvar arquivo no servidor.'], 500);
        }
        jsonResponse(['url' => 'uploads/' . $safeName, 'filename' => $safeName]);
    }
}

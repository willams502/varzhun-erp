<?php
/**
 * Controlador de Autenticação e Sessão.
 */
class AuthController
{
    public static function handle(PDO $pdo, string $action, string $method): void
    {
        if ($action === 'login' && $method === 'POST') {
            $data = getJsonBody();
            validateRequired($data, ['email', 'password']);

            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND active = 1");
            $stmt->execute([trim($data['email'])]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($data['password'], $user['password_hash'])) {
                jsonResponse(['error' => 'E-mail ou senha inválidos.'], 401);
            }

            // Verifica status de acesso da empresa cliente (tenant)
            $tenantId = (int)($user['tenant_id'] ?? 1);
            $stmtTenant = $pdo->prepare("SELECT * FROM saas_tenants WHERE id = ?");
            $stmtTenant->execute([$tenantId]);
            $tenant = $stmtTenant->fetch();
            if ($tenant) {
                if (in_array($tenant['access_status'], ['suspenso', 'bloqueado', 'cancelado'], true)) {
                    jsonResponse(['error' => "Acesso {$tenant['access_status']} para {$tenant['company_name']}. Entre em contato com o administrador Vharzun para regularizar sua assinatura."], 403);
                }
                if (!empty($tenant['expires_at']) && $tenant['expires_at'] < date('Y-m-d') && $tenantId !== 1) {
                    jsonResponse(['error' => "Assinatura expirada em " . date('d/m/Y', strtotime($tenant['expires_at'])) . ". Renove seu plano para continuar."], 403);
                }
            }

            $perms = getRolePermissions()[$user['role']] ?? [];
            // Apenas o Administrador Principal (id = 1) tem acesso total (*) incluindo saas_admin
            if ((int)$user['id'] !== 1 && $user['role'] === 'admin') {
                $perms = ['dashboard', 'sales', 'customers', 'products', 'calculator', 'printers', 'production', 'schedule', 'inventory', 'finance', 'reports', 'suppliers', 'settings'];
            }

            $sessionUser = [
                'id' => (int)$user['id'],
                'tenant_id' => $tenantId,
                'company_name' => $tenant['company_name'] ?? 'Vharzun 3D',
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $user['role'],
                'permissions' => $perms,
            ];
            if (php_sapi_name() !== 'cli') {
                session_regenerate_id(true);
            }
            $_SESSION['user'] = $sessionUser;
            logAudit($pdo, 'login', 'users', (int)$user['id'], 'Login realizado');

            jsonResponse(['user' => $sessionUser]);
        }

        if ($action === 'logout' && $method === 'POST') {
            $_SESSION = [];
            if (ini_get('session.use_cookies') && php_sapi_name() !== 'cli') {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
            }
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_destroy();
            }
            jsonResponse(['ok' => true]);
        }

        if ($action === 'me' && $method === 'GET') {
            if (empty($_SESSION['user'])) {
                jsonResponse(['authenticated' => false, 'user' => null]);
            }
            jsonResponse(['authenticated' => true, 'user' => $_SESSION['user']]);
        }

        if ($action === 'change_password' && $method === 'POST') {
            $user = requireAuth();
            $data = getJsonBody();
            validateRequired($data, ['current_password', 'new_password']);
            if (strlen((string)$data['new_password']) < 6) {
                jsonResponse(['error' => 'A nova senha deve ter no mínimo 6 caracteres.'], 422);
            }
            $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
            $stmt->execute([(int)$user['id']]);
            $hash = $stmt->fetchColumn();
            if (!$hash || !password_verify($data['current_password'], $hash)) {
                jsonResponse(['error' => 'Senha atual incorreta.'], 422);
            }
            $newHash = password_hash($data['new_password'], PASSWORD_DEFAULT);
            $stmtUp = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmtUp->execute([$newHash, (int)$user['id']]);
            logAudit($pdo, 'password_change', 'users', (int)$user['id'], 'Usuário alterou a própria senha');
            jsonResponse(['message' => 'Senha alterada com sucesso!']);
        }

        jsonResponse(['error' => 'Ação de autenticação inválida.'], 404);
    }
}

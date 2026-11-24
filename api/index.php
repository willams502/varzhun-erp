<?php
/**
 * Roteador central da API REST do Varzhun 3D ERP.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../database/Database.php';
require_once __DIR__ . '/../database/Schema.php';
require_once __DIR__ . '/../database/Seeder.php';
require_once __DIR__ . '/helpers.php';

// Carrega controladores
foreach (glob(__DIR__ . '/controllers/*.php') as $ctrl) {
    require_once $ctrl;
}

try {
    Database::ensureInitialized();
    $pdo = Database::getConnection();
} catch (Throwable $e) {
    jsonResponse(['error' => 'Falha na inicialização do banco de dados: ' . $e->getMessage()], 500);
}

$route = $_GET['route'] ?? '';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$parts = array_values(array_filter(explode('/', trim($route, '/'))));
$module = $parts[0] ?? '';
$action = $parts[1] ?? 'index';
$id = isset($parts[2]) && is_numeric($parts[2]) ? (int)$parts[2] : (isset($_GET['id']) ? (int)$_GET['id'] : null);
if (is_numeric($action)) {
    $id = (int)$action;
    $action = 'item';
}

try {
    switch ($module) {
        case 'auth':
            AuthController::handle($pdo, $action, $method);
            break;
        case 'dashboard':
            requirePermission('dashboard');
            DashboardController::handle($pdo, $action, $method);
            break;
        case 'sales':
            requirePermission('sales');
            SalesController::handle($pdo, $action, $method, $id);
            break;
        case 'customers':
            requirePermission('customers');
            CustomersController::handle($pdo, $action, $method, $id);
            break;
        case 'products':
            requirePermission('products');
            ProductsController::handle($pdo, $action, $method, $id);
            break;
        case 'calculator':
            requirePermission('calculator');
            CalculatorController::handle($pdo, $action, $method, $id);
            break;
        case 'printers':
            requirePermission('printers');
            PrintersController::handle($pdo, $action, $method, $id);
            break;
        case 'production':
            requirePermission('production');
            ProductionController::handle($pdo, $action, $method, $id);
            break;
        case 'schedule':
            requirePermission('schedule');
            ScheduleController::handle($pdo, $action, $method, $id);
            break;
        case 'inventory':
            requirePermission('inventory');
            InventoryController::handle($pdo, $action, $method, $id);
            break;
        case 'finance':
            requirePermission('finance');
            FinanceController::handle($pdo, $action, $method, $id);
            break;
        case 'reports':
            requirePermission('reports');
            ReportsController::handle($pdo, $action, $method);
            break;
        case 'suppliers':
            requirePermission('suppliers');
            SuppliersController::handle($pdo, $action, $method, $id);
            break;
        case 'settings':
            requirePermission($method === 'GET' ? 'settings_read' : 'settings');
            SettingsController::handle($pdo, $action, $method, $id);
            break;
        case 'saas_admin':
            requirePermission('saas_admin');
            SaasAdminController::handle($pdo, $action, $method, $id);
            break;
        case 'search':
            requireAuth();
            DashboardController::globalSearch($pdo, trim($_GET['q'] ?? ''));
            break;
        case 'notifications':
            requireAuth();
            DashboardController::notifications($pdo);
            break;
        case 'upload':
            requireAuth();
            SettingsController::handleUpload();
            break;
        default:
            jsonResponse(['error' => 'Endpoint não encontrado: ' . $route], 404);
    }
} catch (Throwable $e) {
    jsonResponse(['error' => 'Erro interno: ' . $e->getMessage()], 500);
}

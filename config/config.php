<?php
/**
 * Configuração central do Varzhun ERP 3D
 * Suporta MySQL (InfinityFree / Produção) e SQLite (Desenvolvimento / Testes locais).
 */

define('APP_NAME', 'Varzhun 3D ERP');
define('APP_VERSION', '1.0.0');
define('BASE_PATH', dirname(__DIR__));
define('UPLOAD_DIR', BASE_PATH . '/uploads');

// Timezone padrão do Brasil
date_default_timezone_set('America/Sao_Paulo');

// Configuração de ambiente:
// Em produção (InfinityFree), utiliza MySQL com as credenciais abaixo.
// Você pode ajustar DB_NAME para o nome exato do banco criado no painel (ex: if0_43425320_erp).
$localOverride = __DIR__ . '/config.local.php';
if (file_exists($localOverride)) {
    require_once $localOverride;
}

if (!defined('DB_DRIVER')) {
    // Detecta automaticamente se estamos rodando em localhost/CLI para usar SQLite caso MySQL não esteja configurado
    $host = $_SERVER['HTTP_HOST'] ?? 'cli';
    $isLocal = php_sapi_name() === 'cli'
        || str_starts_with($host, 'localhost')
        || str_starts_with($host, '127.0.0.1');

    define('DB_DRIVER', $isLocal ? 'sqlite' : 'mysql');
}

if (!defined('DB_HOST')) define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
if (!defined('DB_PORT')) define('DB_PORT', getenv('DB_PORT') ?: '3306');
if (!defined('DB_NAME')) define('DB_NAME', getenv('DB_NAME') ?: 'varzhun_erp');
if (!defined('DB_USER')) define('DB_USER', getenv('DB_USER') ?: 'root');
if (!defined('DB_PASS')) define('DB_PASS', getenv('DB_PASS') ?: '');
if (!defined('SQLITE_PATH')) define('SQLITE_PATH', BASE_PATH . '/database/varzhun_erp.sqlite');

// Sessão segura
if (session_status() === PHP_SESSION_NONE && php_sapi_name() !== 'cli') {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_strict_mode', '1');
    session_start();
}

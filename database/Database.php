<?php
/**
 * Camada de abstração PDO para MySQL e SQLite.
 */
class Database
{
    private static ?PDO $instance = null;
    private static string $driver = 'mysql';

    public static function getConnection(): PDO
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        self::$driver = DB_DRIVER;

        try {
            if (self::$driver === 'sqlite') {
                $dir = dirname(SQLITE_PATH);
                if (!is_dir($dir)) {
                    mkdir($dir, 0775, true);
                }
                $dsn = 'sqlite:' . SQLITE_PATH;
                self::$instance = new PDO($dsn, null, null, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
                self::$instance->exec('PRAGMA foreign_keys = ON;');
                self::$instance->exec('PRAGMA journal_mode = WAL;');
            } else {
                $dsn = sprintf(
                    'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                    DB_HOST,
                    DB_PORT,
                    DB_NAME
                );
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
                ]);
            }
        } catch (PDOException $e) {
            throw new RuntimeException('Erro ao conectar ao banco de dados: ' . $e->getMessage());
        }

        return self::$instance;
    }

    public static function getDriver(): string
    {
        return self::$driver;
    }

    public static function resetConnection(): void
    {
        self::$instance = null;
    }

    public static function ensureInitialized(): void
    {
        $pdo = self::getConnection();
        try {
            $stmt = $pdo->query("SELECT COUNT(*) FROM users");
            $count = (int)$stmt->fetchColumn();
            // Garante migração incremental de tabelas SaaS em bancos já existentes
            self::ensureSaasTables($pdo);
            if ($count === 0) {
                Seeder::seed($pdo);
            }
        } catch (Throwable $e) {
            // Tabela ainda não existe: cria schema e popula dados demonstrativos iniciais
            Schema::createTables($pdo, self::getDriver());
            Seeder::seed($pdo);
        }
    }

    private static function ensureSaasTables(PDO $pdo): void
    {
        try {
            $pdo->query("SELECT 1 FROM saas_tenants LIMIT 1");
        } catch (Throwable $e) {
            Schema::createTables($pdo, self::getDriver());
            Seeder::seedSaas($pdo);
        }

        // Garante coluna tenant_id em todas as tabelas operacionais em bancos já existentes
        $tenantTables = [
            'users', 'categories', 'customers', 'suppliers', 'inventory_items',
            'printers', 'products', 'cost_calculations', 'sales',
            'production_orders', 'production_schedules', 'financial_transactions'
        ];
        foreach ($tenantTables as $tbl) {
            try {
                $pdo->query("SELECT tenant_id FROM {$tbl} LIMIT 1");
            } catch (Throwable $e) {
                try {
                    $pdo->exec("ALTER TABLE {$tbl} ADD COLUMN tenant_id INT NOT NULL DEFAULT 1");
                } catch (Throwable $ignored) {}
            }
        }
    }
}

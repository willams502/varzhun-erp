<?php
/**
 * Definição do schema do banco de dados (compatível com MySQL e SQLite).
 */
class Schema
{
    public static function createTables(PDO $pdo, string $driver): void
    {
        $pk = $driver === 'sqlite'
            ? 'INTEGER PRIMARY KEY AUTOINCREMENT'
            : 'INT AUTO_INCREMENT PRIMARY KEY';

        $dt = $driver === 'sqlite'
            ? "TEXT DEFAULT (datetime('now','localtime'))"
            : 'DATETIME DEFAULT CURRENT_TIMESTAMP';

        $tables = [
            "CREATE TABLE IF NOT EXISTS users (
                id $pk,
                tenant_id INT NOT NULL DEFAULT 1,
                name VARCHAR(120) NOT NULL,
                email VARCHAR(150) NOT NULL UNIQUE,
                password_hash VARCHAR(255) NOT NULL,
                role VARCHAR(30) NOT NULL DEFAULT 'vendedor',
                active TINYINT(1) NOT NULL DEFAULT 1,
                created_at $dt
            )",

            "CREATE TABLE IF NOT EXISTS saas_tenants (
                id $pk,
                company_name VARCHAR(150) NOT NULL,
                owner_name VARCHAR(120),
                document VARCHAR(30),
                email VARCHAR(150),
                whatsapp VARCHAR(40),
                plan_name VARCHAR(60) NOT NULL DEFAULT 'Profissional',
                monthly_price DECIMAL(12,2) NOT NULL DEFAULT 149.90,
                billing_day INT NOT NULL DEFAULT 10,
                access_status VARCHAR(30) NOT NULL DEFAULT 'ativo',
                expires_at DATE,
                max_users INT NOT NULL DEFAULT 5,
                notes TEXT,
                is_demo TINYINT(1) NOT NULL DEFAULT 0,
                created_at $dt
            )",

            "CREATE TABLE IF NOT EXISTS saas_invoices (
                id $pk,
                tenant_id INT NOT NULL,
                reference_month VARCHAR(20) NOT NULL,
                description VARCHAR(200) NOT NULL,
                amount DECIMAL(12,2) NOT NULL,
                due_date DATE NOT NULL,
                paid_date DATE,
                payment_method VARCHAR(40) DEFAULT 'PIX',
                status VARCHAR(30) NOT NULL DEFAULT 'pendente',
                notes TEXT,
                created_at $dt
            )",

            "CREATE TABLE IF NOT EXISTS settings (
                key_name VARCHAR(100) PRIMARY KEY,
                value_text TEXT
            )",

            "CREATE TABLE IF NOT EXISTS categories (
                id $pk,
                tenant_id INT NOT NULL DEFAULT 1,
                type VARCHAR(30) NOT NULL,
                name VARCHAR(100) NOT NULL,
                description VARCHAR(255)
            )",

            "CREATE TABLE IF NOT EXISTS customers (
                id $pk,
                tenant_id INT NOT NULL DEFAULT 1,
                name VARCHAR(150) NOT NULL,
                cpf_cnpj VARCHAR(30),
                phone VARCHAR(40),
                whatsapp VARCHAR(40),
                email VARCHAR(150),
                address TEXT,
                notes TEXT,
                is_demo TINYINT(1) NOT NULL DEFAULT 0,
                created_at $dt
            )",

            "CREATE TABLE IF NOT EXISTS customer_interactions (
                id $pk,
                customer_id INT NOT NULL,
                type VARCHAR(50) NOT NULL DEFAULT 'Atendimento',
                description TEXT NOT NULL,
                user_id INT,
                created_at $dt
            )",

            "CREATE TABLE IF NOT EXISTS suppliers (
                id $pk,
                tenant_id INT NOT NULL DEFAULT 1,
                name VARCHAR(150) NOT NULL,
                cnpj VARCHAR(30),
                contact_name VARCHAR(120),
                phone VARCHAR(40),
                email VARCHAR(150),
                address TEXT,
                products_supplied TEXT,
                notes TEXT,
                is_demo TINYINT(1) NOT NULL DEFAULT 0,
                created_at $dt
            )",

            "CREATE TABLE IF NOT EXISTS inventory_items (
                id $pk,
                tenant_id INT NOT NULL DEFAULT 1,
                name VARCHAR(150) NOT NULL,
                category VARCHAR(60) NOT NULL,
                material_type VARCHAR(60),
                color VARCHAR(60),
                quantity DECIMAL(12,2) NOT NULL DEFAULT 0,
                unit VARCHAR(20) NOT NULL DEFAULT 'g',
                acquisition_cost DECIMAL(12,2) NOT NULL DEFAULT 0,
                supplier_id INT,
                min_stock DECIMAL(12,2) NOT NULL DEFAULT 0,
                entry_date DATE,
                notes TEXT,
                is_demo TINYINT(1) NOT NULL DEFAULT 0,
                created_at $dt
            )",

            "CREATE TABLE IF NOT EXISTS inventory_movements (
                id $pk,
                item_id INT NOT NULL,
                movement_type VARCHAR(30) NOT NULL,
                quantity DECIMAL(12,2) NOT NULL,
                unit_cost DECIMAL(12,2) NOT NULL DEFAULT 0,
                reference_type VARCHAR(50),
                reference_id INT,
                notes TEXT,
                user_id INT,
                created_at $dt
            )",

            "CREATE TABLE IF NOT EXISTS supplier_purchases (
                id $pk,
                supplier_id INT NOT NULL,
                inventory_item_id INT,
                item_name VARCHAR(150),
                quantity DECIMAL(12,2) NOT NULL,
                unit_cost DECIMAL(12,2) NOT NULL,
                total_cost DECIMAL(12,2) NOT NULL,
                purchase_date DATE NOT NULL,
                due_date DATE,
                paid TINYINT(1) NOT NULL DEFAULT 0,
                notes TEXT,
                created_at $dt
            )",

            "CREATE TABLE IF NOT EXISTS printers (
                id $pk,
                tenant_id INT NOT NULL DEFAULT 1,
                name VARCHAR(120) NOT NULL,
                brand VARCHAR(80),
                model VARCHAR(100),
                identifier_code VARCHAR(50),
                build_volume VARCHAR(60),
                technology VARCHAR(40) NOT NULL DEFAULT 'FDM',
                status VARCHAR(30) NOT NULL DEFAULT 'disponivel',
                cost_per_hour DECIMAL(10,2) NOT NULL DEFAULT 2.50,
                energy_kwh DECIMAL(10,3) NOT NULL DEFAULT 0.150,
                acquisition_date DATE,
                acquisition_value DECIMAL(12,2) NOT NULL DEFAULT 0,
                total_hours DECIMAL(12,2) NOT NULL DEFAULT 0,
                notes TEXT,
                is_demo TINYINT(1) NOT NULL DEFAULT 0,
                created_at $dt
            )",

            "CREATE TABLE IF NOT EXISTS printer_logs (
                id $pk,
                printer_id INT NOT NULL,
                log_type VARCHAR(30) NOT NULL DEFAULT 'impressao',
                hours_worked DECIMAL(10,2) NOT NULL DEFAULT 0,
                prints_count INT NOT NULL DEFAULT 0,
                success_count INT NOT NULL DEFAULT 0,
                fail_count INT NOT NULL DEFAULT 0,
                maintenance_cost DECIMAL(12,2) NOT NULL DEFAULT 0,
                description TEXT,
                production_order_id INT,
                log_date DATE NOT NULL,
                created_at $dt
            )",

            "CREATE TABLE IF NOT EXISTS products (
                id $pk,
                tenant_id INT NOT NULL DEFAULT 1,
                name VARCHAR(150) NOT NULL,
                sku VARCHAR(60),
                category VARCHAR(80),
                description TEXT,
                photo_url TEXT,
                stl_file VARCHAR(255),
                threemf_file VARCHAR(255),
                estimated_weight_g DECIMAL(10,2) NOT NULL DEFAULT 0,
                estimated_time_min INT NOT NULL DEFAULT 0,
                material VARCHAR(60),
                color VARCHAR(60),
                stock_qty INT NOT NULL DEFAULT 0,
                manufacturing_cost DECIMAL(12,2) NOT NULL DEFAULT 0,
                sale_price DECIMAL(12,2) NOT NULL DEFAULT 0,
                profit_margin DECIMAL(8,2) NOT NULL DEFAULT 0,
                avg_production_days INT NOT NULL DEFAULT 2,
                product_type VARCHAR(30) NOT NULL DEFAULT 'recorrente',
                inventory_item_id INT,
                is_demo TINYINT(1) NOT NULL DEFAULT 0,
                created_at $dt
            )",

            "CREATE TABLE IF NOT EXISTS cost_calculations (
                id $pk,
                tenant_id INT NOT NULL DEFAULT 1,
                title VARCHAR(150) NOT NULL,
                product_id INT,
                printer_id INT,
                material_type VARCHAR(50),
                material_price_kg DECIMAL(10,2) NOT NULL DEFAULT 0,
                weight_g DECIMAL(10,2) NOT NULL DEFAULT 0,
                waste_percent DECIMAL(6,2) NOT NULL DEFAULT 5,
                print_hours DECIMAL(10,2) NOT NULL DEFAULT 0,
                energy_kwh DECIMAL(10,3) NOT NULL DEFAULT 0.15,
                energy_cost_kwh DECIMAL(10,4) NOT NULL DEFAULT 0.85,
                depreciation_per_hour DECIMAL(10,2) NOT NULL DEFAULT 1.00,
                maintenance_per_hour DECIMAL(10,2) NOT NULL DEFAULT 0.50,
                machine_op_cost_hour DECIMAL(10,2) NOT NULL DEFAULT 1.00,
                labor_cost DECIMAL(10,2) NOT NULL DEFAULT 0,
                finishing_cost DECIMAL(10,2) NOT NULL DEFAULT 0,
                painting_cost DECIMAL(10,2) NOT NULL DEFAULT 0,
                packaging_cost DECIMAL(10,2) NOT NULL DEFAULT 0,
                labels_cost DECIMAL(10,2) NOT NULL DEFAULT 0,
                platform_fee_percent DECIMAL(6,2) NOT NULL DEFAULT 0,
                payment_fee_percent DECIMAL(6,2) NOT NULL DEFAULT 0,
                extra_costs DECIMAL(10,2) NOT NULL DEFAULT 0,
                desired_margin_percent DECIMAL(6,2) NOT NULL DEFAULT 50,
                calc_material_cost DECIMAL(12,2) NOT NULL DEFAULT 0,
                calc_energy_cost DECIMAL(12,2) NOT NULL DEFAULT 0,
                calc_machine_cost DECIMAL(12,2) NOT NULL DEFAULT 0,
                calc_labor_cost DECIMAL(12,2) NOT NULL DEFAULT 0,
                calc_finishing_cost DECIMAL(12,2) NOT NULL DEFAULT 0,
                calc_total_cost DECIMAL(12,2) NOT NULL DEFAULT 0,
                calc_suggested_price DECIMAL(12,2) NOT NULL DEFAULT 0,
                calc_estimated_profit DECIMAL(12,2) NOT NULL DEFAULT 0,
                calc_margin_percent DECIMAL(8,2) NOT NULL DEFAULT 0,
                created_at $dt
            )",

            "CREATE TABLE IF NOT EXISTS sales (
                id $pk,
                tenant_id INT NOT NULL DEFAULT 1,
                code VARCHAR(40) NOT NULL,
                customer_id INT NOT NULL,
                sale_type VARCHAR(20) NOT NULL DEFAULT 'venda',
                status VARCHAR(30) NOT NULL DEFAULT 'aprovado',
                payment_method VARCHAR(40) NOT NULL DEFAULT 'PIX',
                payment_status VARCHAR(30) NOT NULL DEFAULT 'pendente',
                subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
                discount_value DECIMAL(12,2) NOT NULL DEFAULT 0,
                total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
                down_payment DECIMAL(12,2) NOT NULL DEFAULT 0,
                paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
                installments INT NOT NULL DEFAULT 1,
                due_date DATE,
                sale_date DATE NOT NULL,
                notes TEXT,
                is_demo TINYINT(1) NOT NULL DEFAULT 0,
                created_by INT,
                created_at $dt
            )",

            "CREATE TABLE IF NOT EXISTS sale_items (
                id $pk,
                sale_id INT NOT NULL,
                product_id INT,
                description VARCHAR(200) NOT NULL,
                quantity INT NOT NULL DEFAULT 1,
                unit_price DECIMAL(12,2) NOT NULL DEFAULT 0,
                unit_cost DECIMAL(12,2) NOT NULL DEFAULT 0,
                total_price DECIMAL(12,2) NOT NULL DEFAULT 0,
                weight_g DECIMAL(10,2) NOT NULL DEFAULT 0,
                print_time_min INT NOT NULL DEFAULT 0
            )",

            "CREATE TABLE IF NOT EXISTS sale_payments (
                id $pk,
                sale_id INT NOT NULL,
                amount DECIMAL(12,2) NOT NULL,
                payment_method VARCHAR(40) NOT NULL,
                payment_date DATE NOT NULL,
                notes VARCHAR(255),
                created_by INT,
                created_at $dt
            )",

            "CREATE TABLE IF NOT EXISTS production_orders (
                id $pk,
                tenant_id INT NOT NULL DEFAULT 1,
                order_number VARCHAR(40) NOT NULL,
                sale_id INT,
                customer_id INT,
                title VARCHAR(200) NOT NULL,
                products_summary TEXT,
                total_qty INT NOT NULL DEFAULT 1,
                stage VARCHAR(40) NOT NULL DEFAULT 'aguardando_producao',
                priority VARCHAR(20) NOT NULL DEFAULT 'normal',
                printer_id INT,
                entry_date DATE NOT NULL,
                due_date DATE NOT NULL,
                estimated_hours DECIMAL(10,2) NOT NULL DEFAULT 1,
                actual_hours DECIMAL(10,2) NOT NULL DEFAULT 0,
                estimated_cost DECIMAL(12,2) NOT NULL DEFAULT 0,
                actual_cost DECIMAL(12,2) NOT NULL DEFAULT 0,
                responsible_name VARCHAR(120),
                inventory_item_id INT,
                estimated_weight_g DECIMAL(10,2) NOT NULL DEFAULT 0,
                material_deducted TINYINT(1) NOT NULL DEFAULT 0,
                notes TEXT,
                is_demo TINYINT(1) NOT NULL DEFAULT 0,
                created_at $dt,
                updated_at $dt
            )",

            "CREATE TABLE IF NOT EXISTS production_history (
                id $pk,
                order_id INT NOT NULL,
                from_stage VARCHAR(40),
                to_stage VARCHAR(40) NOT NULL,
                notes TEXT,
                user_name VARCHAR(120),
                created_at $dt
            )",

            "CREATE TABLE IF NOT EXISTS production_schedules (
                id $pk,
                tenant_id INT NOT NULL DEFAULT 1,
                title VARCHAR(150) NOT NULL,
                schedule_type VARCHAR(30) NOT NULL DEFAULT 'impressao',
                production_order_id INT,
                printer_id INT NOT NULL,
                start_datetime DATETIME NOT NULL,
                end_datetime DATETIME NOT NULL,
                priority VARCHAR(20) NOT NULL DEFAULT 'normal',
                status VARCHAR(30) NOT NULL DEFAULT 'agendado',
                notes TEXT,
                created_at $dt
            )",

            "CREATE TABLE IF NOT EXISTS financial_transactions (
                id $pk,
                tenant_id INT NOT NULL DEFAULT 1,
                type VARCHAR(20) NOT NULL,
                classification VARCHAR(40) NOT NULL,
                category VARCHAR(80) NOT NULL,
                description VARCHAR(255) NOT NULL,
                amount DECIMAL(12,2) NOT NULL,
                status VARCHAR(30) NOT NULL DEFAULT 'pago',
                due_date DATE NOT NULL,
                paid_date DATE,
                payment_method VARCHAR(40),
                is_recurring TINYINT(1) NOT NULL DEFAULT 0,
                recurrence_interval VARCHAR(30),
                reference_type VARCHAR(50),
                reference_id INT,
                is_demo TINYINT(1) NOT NULL DEFAULT 0,
                created_at $dt
            )",

            "CREATE TABLE IF NOT EXISTS audit_logs (
                id $pk,
                user_id INT,
                user_name VARCHAR(120),
                action VARCHAR(60) NOT NULL,
                entity VARCHAR(60) NOT NULL,
                entity_id INT,
                details TEXT,
                created_at $dt
            )"
        ];

        foreach ($tables as $sql) {
            $pdo->exec($sql);
        }
    }
}

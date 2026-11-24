<?php
/**
 * Popula dados iniciais e registros demonstrativos claramente identificados como [DEMO].
 */
class Seeder
{
    public static function seed(PDO $pdo): void
    {
        self::seedSaas($pdo);

        // 1. Usuários padrão (5 níveis de acesso)
        $users = [
            ['Administrador Varzhun', 'admin@vharzun3d.com.br', 'admin123', 'admin'],
            ['Mariana Gerente', 'gerente@vharzun3d.com.br', 'gerente123', 'gerente'],
            ['Carlos Financeiro', 'financeiro@vharzun3d.com.br', 'financeiro123', 'financeiro'],
            ['Lucas Produção', 'producao@vharzun3d.com.br', 'producao123', 'producao'],
            ['Ana Vendas', 'vendas@vharzun3d.com.br', 'vendas123', 'vendedor'],
        ];
        $stmtUser = $pdo->prepare("INSERT INTO users (name, email, password_hash, role, active) VALUES (?, ?, ?, ?, 1)");
        foreach ($users as $u) {
            $stmtUser->execute([$u[0], $u[1], password_hash($u[2], PASSWORD_DEFAULT), $u[3]]);
        }

        // 2. Configurações da empresa
        $settings = [
            'company_name' => 'Varzhun Impressão 3D Ltda',
            'company_cnpj' => '45.123.456/0001-89',
            'company_phone' => '(11) 99999-3300',
            'company_email' => 'contato@vharzun3d.com.br',
            'company_address' => 'Av. Tecnologia, 350 - São Paulo/SP',
            'company_logo' => '',
            'currency' => 'BRL',
            'default_kwh_cost' => '0.88',
            'default_profit_margin' => '55',
            'default_waste_percent' => '5',
            'auto_deduct_stock' => '1',
            'demo_banner_enabled' => '1',
        ];
        $stmtSet = $pdo->prepare("INSERT INTO settings (key_name, value_text) VALUES (?, ?)");
        foreach ($settings as $k => $v) {
            $stmtSet->execute([$k, $v]);
        }

        // 3. Categorias
        $categories = [
            ['produto', 'Action Figures & Colecionáveis', 'Miniaturas, bustos e peças decorativas'],
            ['produto', 'Peças Técnicas & Engenharia', 'Suportes, engrenagens e protótipos funcionais'],
            ['produto', 'Decoração & Utilidades', 'Vasos, luminárias e organizadores'],
            ['produto', 'Personalizados Sob Demanda', 'Projetos exclusivos enviados por clientes'],
            ['despesa', 'Filamentos e Resinas', 'Compra de matéria-prima para impressão'],
            ['despesa', 'Energia Elétrica', 'Consumo energético do parque de máquinas'],
            ['despesa', 'Manutenção de Impressoras', 'Peças de reposição, bicos, correias e lubrificação'],
            ['despesa', 'Aluguel e Estrutura', 'Aluguel, condomínio e IPTU'],
            ['despesa', 'Internet e Software', 'Link de internet, licenças e hospedagem'],
            ['despesa', 'Salários e Pró-labore', 'Folha de pagamento e encargos'],
            ['despesa', 'Embalagens e Logística', 'Caixas, plástico bolha, etiquetas e frete'],
            ['despesa', 'Investimentos', 'Aquisição de novas impressoras e equipamentos'],
        ];
        $stmtCat = $pdo->prepare("INSERT INTO categories (type, name, description) VALUES (?, ?, ?)");
        foreach ($categories as $c) {
            $stmtCat->execute($c);
        }

        // 4. Fornecedores [DEMO]
        $stmtSup = $pdo->prepare("INSERT INTO suppliers (name, cnpj, contact_name, phone, email, address, products_supplied, notes, is_demo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)");
        $stmtSup->execute(['[DEMO] 3D Lab Filamentos', '12.345.678/0001-10', 'Roberto Silva', '(11) 98888-1122', 'vendas@3dlabdemo.com.br', 'Rua dos Polímeros, 120 - SP', 'PLA, PETG, ABS, TPU', 'Fornecedor demonstrativo principal de filamentos']);
        $stmtSup->execute(['[DEMO] Resina & Cia Tech', '98.765.432/0001-55', 'Fernanda Costa', '(19) 97777-3344', 'contato@resinaciademo.com.br', 'Av. Industrial, 800 - Campinas/SP', 'Resinas UV 405nm, Álcool Isopropílico, Bicos', 'Fornecedor demonstrativo de resinas e peças']);

        // 5. Estoque [DEMO]
        $today = date('Y-m-d');
        $stmtInv = $pdo->prepare("INSERT INTO inventory_items (name, category, material_type, color, quantity, unit, acquisition_cost, supplier_id, min_stock, entry_date, notes, is_demo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
        $stmtInv->execute(['[DEMO] Filamento PLA Preto Fosco 1kg', 'filamento', 'PLA', 'Preto', 4200, 'g', 95.00, 1, 1000, $today, 'Rolo de 1kg (R$ 95,00/kg)']);
        $stmtInv->execute(['[DEMO] Filamento PLA Branco Neve 1kg', 'filamento', 'PLA', 'Branco', 2800, 'g', 90.00, 1, 1000, $today, 'Rolo de 1kg (R$ 90,00/kg)']);
        $stmtInv->execute(['[DEMO] Filamento PETG Cinza Grafite 1kg', 'filamento', 'PETG', 'Cinza', 850, 'g', 115.00, 1, 1000, $today, 'Abaixo do estoque mínimo para demonstrar alerta']);
        $stmtInv->execute(['[DEMO] Resina Standard Cinza 1L', 'resina', 'Resina', 'Cinza', 1800, 'ml', 165.00, 2, 500, $today, 'Frasco 1L para impressoras SLA']);
        $stmtInv->execute(['[DEMO] Bico Hardened Steel 0.4mm', 'peca_reposicao', 'Metal', 'Preto', 6, 'un', 38.00, 2, 3, $today, 'Compatível Bambu Lab / Ender']);
        $stmtInv->execute(['[DEMO] Caixa Kraft 20x20x15cm', 'embalagem', 'Papelão', 'Kraft', 45, 'un', 3.20, 1, 15, $today, 'Embalagem padrão para envio']);

        // 6. Impressoras 3D [DEMO]
        $stmtPrn = $pdo->prepare("INSERT INTO printers (name, brand, model, identifier_code, build_volume, technology, status, cost_per_hour, energy_kwh, acquisition_date, acquisition_value, total_hours, notes, is_demo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
        $stmtPrn->execute(['[DEMO] Bambu Lab X1C #01', 'Bambu Lab', 'X1 Carbon', 'IMP-01', '256x256x256mm', 'FDM', 'imprimindo', 3.20, 0.180, '2025-06-15', 9800.00, 1240.5, 'Alta velocidade com AMS']);
        $stmtPrn->execute(['[DEMO] Bambu Lab A1 #02', 'Bambu Lab', 'A1 Combo', 'IMP-02', '256x256x256mm', 'FDM', 'disponivel', 2.40, 0.140, '2025-11-10', 4200.00, 610.0, 'Ideal para PLA e PETG']);
        $stmtPrn->execute(['[DEMO] Elegoo Saturn 4 #03', 'Elegoo', 'Saturn 4 Ultra', 'IMP-03', '218x122x220mm', 'SLA (Resina)', 'disponivel', 3.80, 0.120, '2026-01-20', 3900.00, 320.0, 'Alta resolução 12K para miniaturas']);
        $stmtPrn->execute(['[DEMO] Creality K1 Max #04', 'Creality', 'K1 Max', 'IMP-04', '300x300x300mm', 'FDM', 'manutencao', 2.80, 0.220, '2025-03-05', 5600.00, 1890.0, 'Troca preventiva de correia']);

        // 7. Clientes [DEMO]
        $stmtCust = $pdo->prepare("INSERT INTO customers (name, cpf_cnpj, phone, whatsapp, email, address, notes, is_demo) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
        $stmtCust->execute(['[DEMO] Studio Geek Brasil', '33.444.555/0001-22', '(11) 3344-5566', '(11) 99123-4567', 'compras@studiogeekdemo.com.br', 'Rua Augusta, 1200 - São Paulo/SP', 'Cliente recorrente de colecionáveis']);
        $stmtCust->execute(['[DEMO] EngenhaTech Soluções', '22.111.333/0001-44', '(11) 4002-8922', '(11) 98234-5678', 'projetos@engenhatechdemo.com.br', 'Av. Paulista, 900 - São Paulo/SP', 'Encomendas de peças técnicas em PETG']);
        $stmtCust->execute(['[DEMO] João Pedro Almeida', '123.456.789-00', '(21) 99876-5432', '(21) 99876-5432', 'joao.demo@email.com', 'Rua das Laranjeiras, 45 - Rio de Janeiro/RJ', 'Cliente pessoa física']);

        // 8. Produtos [DEMO]
        $stmtProd = $pdo->prepare("INSERT INTO products (name, sku, category, description, estimated_weight_g, estimated_time_min, material, color, stock_qty, manufacturing_cost, sale_price, profit_margin, avg_production_days, product_type, inventory_item_id, is_demo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
        $stmtProd->execute(['[DEMO] Busto Darth Vader 20cm', 'VHZ-AF-001', 'Action Figures & Colecionáveis', 'Busto detalhado em PLA Preto Fosco, camada 0.16mm', 180, 360, 'PLA', 'Preto', 4, 34.50, 89.90, 61.62, 2, 'recorrente', 1]);
        $stmtProd->execute(['[DEMO] Suporte Articulado Headset', 'VHZ-DEC-002', 'Decoração & Utilidades', 'Suporte de mesa resistente para fones gamer', 150, 300, 'PLA', 'Preto', 8, 28.00, 69.90, 59.94, 1, 'recorrente', 1]);
        $stmtProd->execute(['[DEMO] Engrenagem Helicoidal Sob Medida', 'VHZ-TEC-003', 'Peças Técnicas & Engenharia', 'Peça funcional em PETG com 60% de preenchimento', 95, 210, 'PETG', 'Cinza', 0, 26.40, 75.00, 64.80, 3, 'personalizado', 3]);
        $stmtProd->execute(['[DEMO] Miniatura Dragão RPG 75mm', 'VHZ-AF-004', 'Action Figures & Colecionáveis', 'Impressão em resina 12K com cura UV', 65, 240, 'Resina', 'Cinza', 2, 24.00, 79.90, 69.96, 2, 'recorrente', 4]);

        // 9. Vendas e Orçamentos [DEMO]
        $m1 = date('Y-m-d', strtotime('-2 days'));
        $m2 = date('Y-m-d', strtotime('-15 days'));
        $m3 = date('Y-m-d', strtotime('-40 days'));
        $dueFuture = date('Y-m-d', strtotime('+5 days'));
        $dueLate = date('Y-m-d', strtotime('-1 day'));

        $stmtSale = $pdo->prepare("INSERT INTO sales (code, customer_id, sale_type, status, payment_method, payment_status, subtotal, discount_value, total_amount, down_payment, paid_amount, installments, due_date, sale_date, notes, is_demo, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1)");
        $stmtSale->execute(['VND-2026-001', 1, 'venda', 'aprovado', 'PIX', 'pago', 449.50, 0, 449.50, 449.50, 449.50, 1, $m2, $m2, '[DEMO] Pedido de 5 bustos para vitrine']);
        $stmtSale->execute(['VND-2026-002', 2, 'venda', 'aprovado', 'Cartão de crédito', 'parcial', 750.00, 50.00, 700.00, 350.00, 350.00, 2, $dueFuture, $m1, '[DEMO] Lote de 10 engrenagens PETG (50% de sinal)']);
        $stmtSale->execute(['ORC-2026-003', 3, 'orcamento', 'orcamento', 'PIX', 'pendente', 159.80, 0, 159.80, 0, 0, 1, $dueFuture, $today, '[DEMO] Orçamento de 2 miniaturas RPG aguardando aprovação']);
        $stmtSale->execute(['VND-2026-004', 1, 'venda', 'aprovado', 'Transferência', 'pago', 349.50, 0, 349.50, 349.50, 349.50, 1, $m3, $m3, '[DEMO] Pedido mês anterior']);

        $stmtSI = $pdo->prepare("INSERT INTO sale_items (sale_id, product_id, description, quantity, unit_price, unit_cost, total_price, weight_g, print_time_min) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmtSI->execute([1, 1, '[DEMO] Busto Darth Vader 20cm', 5, 89.90, 34.50, 449.50, 180, 360]);
        $stmtSI->execute([2, 3, '[DEMO] Engrenagem Helicoidal Sob Medida', 10, 75.00, 26.40, 750.00, 95, 210]);
        $stmtSI->execute([3, 4, '[DEMO] Miniatura Dragão RPG 75mm', 2, 79.90, 24.00, 159.80, 65, 240]);
        $stmtSI->execute([4, 2, '[DEMO] Suporte Articulado Headset', 5, 69.90, 28.00, 349.50, 150, 300]);

        $stmtSP = $pdo->prepare("INSERT INTO sale_payments (sale_id, amount, payment_method, payment_date, notes, created_by) VALUES (?, ?, ?, ?, ?, 1)");
        $stmtSP->execute([1, 449.50, 'PIX', $m2, 'Pagamento integral via PIX']);
        $stmtSP->execute([2, 350.00, 'PIX', $m1, 'Sinal de 50% na aprovação']);
        $stmtSP->execute([4, 349.50, 'Transferência', $m3, 'Pagamento integral']);

        // 10. Ordens de Produção (Kanban) [DEMO]
        $stmtPO = $pdo->prepare("INSERT INTO production_orders (order_number, sale_id, customer_id, title, products_summary, total_qty, stage, priority, printer_id, entry_date, due_date, estimated_hours, actual_hours, estimated_cost, actual_cost, responsible_name, inventory_item_id, estimated_weight_g, material_deducted, notes, is_demo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
        $stmtPO->execute(['OP-2026-001', 1, 1, '[DEMO] Lote 5x Busto Darth Vader', '5x Busto Darth Vader 20cm', 5, 'entregue', 'normal', 1, $m2, date('Y-m-d', strtotime('-10 days')), 30.0, 29.5, 172.50, 169.00, 'Lucas Produção', 1, 900, 1, 'Concluído e entregue sem falhas']);
        $stmtPO->execute(['OP-2026-002', 2, 2, '[DEMO] 10x Engrenagem Helicoidal PETG', '10x Engrenagem Helicoidal Sob Medida', 10, 'em_impressao', 'urgente', 1, $m1, $dueFuture, 35.0, 14.0, 264.00, 110.00, 'Lucas Produção', 3, 950, 0, 'Em produção contínua na X1C']);
        $stmtPO->execute(['OP-2026-003', 3, 3, '[DEMO] 2x Miniatura Dragão RPG', '2x Miniatura Dragão RPG 75mm', 2, 'aguardando_aprovacao', 'normal', 3, $today, $dueFuture, 8.0, 0, 48.00, 0, 'Ana Vendas', 4, 130, 0, 'Aguardando cliente aprovar orçamento']);
        $stmtPO->execute(['OP-2026-004', 4, 1, '[DEMO] Reposição Suporte Headset', '3x Suporte Articulado Headset', 3, 'pos_processamento', 'alta', 2, date('Y-m-d', strtotime('-4 days')), $dueLate, 15.0, 15.5, 84.00, 86.00, 'Lucas Produção', 1, 450, 0, 'Pedido com prazo vencido para demonstrar alerta de atraso']);

        // 11. Agendamentos [DEMO]
        $stmtSch = $pdo->prepare("INSERT INTO production_schedules (title, schedule_type, production_order_id, printer_id, start_datetime, end_datetime, priority, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmtSch->execute(['[DEMO] OP-2026-002 Engrenagens PETG', 'impressao', 2, 1, $today . ' 08:00:00', $today . ' 20:00:00', 'urgente', 'em_andamento', 'Lote em andamento']);
        $stmtSch->execute(['[DEMO] Manutenção Preventiva K1 Max', 'manutencao', null, 4, date('Y-m-d', strtotime('+1 day')) . ' 09:00:00', date('Y-m-d', strtotime('+1 day')) . ' 12:00:00', 'alta', 'agendado', 'Troca de correia e calibração']);

        // 12. Logs de Impressoras [DEMO]
        $stmtPL = $pdo->prepare("INSERT INTO printer_logs (printer_id, log_type, hours_worked, prints_count, success_count, fail_count, maintenance_cost, description, production_order_id, log_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmtPL->execute([1, 'impressao', 29.5, 5, 5, 0, 0, '[DEMO] Produção OP-2026-001 (5x Busto Darth Vader)', 1, $m2]);
        $stmtPL->execute([4, 'manutencao', 0, 0, 0, 0, 145.00, '[DEMO] Compra de correia GT2 e bico hardened', null, $m1]);

        // 13. Financeiro [DEMO]
        $stmtFin = $pdo->prepare("INSERT INTO financial_transactions (type, classification, category, description, amount, status, due_date, paid_date, payment_method, is_recurring, recurrence_interval, reference_type, reference_id, is_demo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
        $stmtFin->execute(['entrada', 'receita', 'Vendas', '[DEMO] Recebimento VND-2026-001 (Studio Geek)', 449.50, 'pago', $m2, $m2, 'PIX', 0, null, 'sale', 1]);
        $stmtFin->execute(['entrada', 'receita', 'Vendas', '[DEMO] Sinal 50% VND-2026-002 (EngenhaTech)', 350.00, 'pago', $m1, $m1, 'PIX', 0, null, 'sale', 2]);
        $stmtFin->execute(['entrada', 'receita', 'Vendas', '[DEMO] Saldo restante VND-2026-002 (EngenhaTech)', 350.00, 'pendente', $dueFuture, null, 'Cartão de crédito', 0, null, 'sale', 2]);
        $stmtFin->execute(['entrada', 'receita', 'Vendas', '[DEMO] Recebimento VND-2026-004 (Mês anterior)', 349.50, 'pago', $m3, $m3, 'Transferência', 0, null, 'sale', 4]);
        $stmtFin->execute(['saida', 'custo_producao', 'Filamentos e Resinas', '[DEMO] Reposição de Filamentos PLA/PETG', 285.00, 'pago', $m2, $m2, 'PIX', 0, null, 'inventory', 1]);
        $stmtFin->execute(['saida', 'despesa_operacional', 'Energia Elétrica', '[DEMO] Conta de Energia Elétrica', 195.40, 'pago', $m1, $m1, 'Débito Automático', 1, 'mensal', null, null]);
        $stmtFin->execute(['saida', 'custo_producao', 'Manutenção de Impressoras', '[DEMO] Peças de manutenção K1 Max', 145.00, 'pago', $m1, $m1, 'PIX', 0, null, 'printer', 4]);
        $stmtFin->execute(['saida', 'despesa_operacional', 'Internet e Software', '[DEMO] Internet Fibra Empresarial', 129.90, 'pendente', $dueFuture, null, 'Boleto', 1, 'mensal', null, null]);
    }

    public static function seedSaas(PDO $pdo): void
    {
        $cnt = (int)$pdo->query("SELECT COUNT(*) FROM saas_tenants")->fetchColumn();
        if ($cnt > 0) return;

        $stmt = $pdo->prepare("INSERT INTO saas_tenants (company_name, owner_name, document, email, whatsapp, plan_name, monthly_price, billing_day, access_status, expires_at, max_users, notes, is_demo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute(['Varzhun Matriz (Admin)', 'Administrador Varzhun', '45.123.456/0001-89', 'admin@vharzun3d.com.br', '(11) 99999-3300', 'Enterprise', 0.00, 1, 'ativo', null, 50, 'Conta principal administradora do sistema', 0]);
        $stmt->execute(['[DEMO] Maker3D Soluções', 'Rafael Mendes', '11.222.333/0001-44', 'contato@maker3ddemo.com.br', '(11) 98765-1111', 'Profissional', 149.90, 10, 'ativo', date('Y-m-d', strtotime('+25 days')), 5, 'Cliente SaaS ativo em dia', 1]);
        $stmt->execute(['[DEMO] PrintLab Protótipos', 'Juliana Rocha', '55.666.777/0001-88', 'financeiro@printlabdemo.com.br', '(21) 97654-2222', 'Starter', 89.90, 5, 'suspenso', date('Y-m-d', strtotime('-5 days')), 2, 'Acesso suspenso por atraso na mensalidade', 1]);

        $ym = date('Y-m');
        $stmtInv = $pdo->prepare("INSERT INTO saas_invoices (tenant_id, reference_month, description, amount, due_date, paid_date, payment_method, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmtInv->execute([2, $ym, "Mensalidade {$ym} — Plano Profissional", 149.90, date('Y-m-10'), date('Y-m-09'), 'PIX', 'pago', 'Pago via PIX']);
        $stmtInv->execute([3, $ym, "Mensalidade {$ym} — Plano Starter", 89.90, date('Y-m-d', strtotime('-5 days')), null, 'PIX', 'atrasado', 'Aguardando regularização']);
    }
}

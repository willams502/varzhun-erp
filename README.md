# 🧊 Varzhun 3D ERP

Sistema ERP completo e multi-empresa para gerenciamento de empresas de impressão 3D, centralizando vendas, orçamentos, CRM, catálogo de produtos, calculadora de custos 3D, parque de impressoras, produção em Kanban, agenda, estoque, financeiro, fornecedores e relatórios gerenciais.

## ✨ Principais Módulos
- **Dashboard Gerencial**: 14 KPIs em tempo real e 6 gráficos interativos com filtros por período (`dia`, `semana`, `mês`, `trimestre`, `ano`).
- **Vendas & Orçamentos**: Criação de pedidos/orçamentos em PDF, pagamentos parciais/sinal e conversão automática em ordem de produção.
- **Calculadora de Custos 3D**: Cálculo automático de filamento/resina, desperdício, energia (kWh), depreciação, manutenção, mão de obra, taxas e margem de lucro.
- **Produção (Kanban 12 Etapas)**: Acompanhamento visual com drag & drop, alerta de atrasos e baixa automática de estoque na conclusão.
- **Financeiro, Estoque & Relatórios**: Contas a pagar/receber, fluxo de caixa, alertas de estoque mínimo e exportação em PDF e Excel.
- **Multi-Empresa & Controle de Acesso**: Isolamento por `tenant_id`, 5 níveis de permissão (RBAC) e painel Master Admin para gestão de clientes, usuários e mensalidades.

## 🛠️ Tecnologias
- **Backend:** PHP 8+, PDO (MySQL & SQLite)
- **Frontend:** JavaScript (SPA), CSS3 (Glassmorphism / Tema Claro e Escuro), Chart.js, jsPDF, SheetJS
- **Testes:** Suite de integração automatizada em PHP (`tests/integration_test.php`)

## 🚀 Como executar localmente
```bash
git clone https://github.com/willams502/varzhun-erp.git
cd varzhun-erp
php -S localhost:8000
```
Acesse `http://localhost:8000` (em ambiente local utiliza SQLite automaticamente e cria os dados iniciais de demonstração).

## 🧪 Testes
```bash
php tests/integration_test.php
```

## 👤 Autor
**Matheus Willams** — [GitHub](https://github.com/willams502) · [LinkedIn](https://www.linkedin.com/in/matheuswillams/)

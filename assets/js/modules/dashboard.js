/**
 * Módulo 1: Dashboard Principal
 */
const DashboardModule = {
    period: 'mes',

    async render() {
        const main = document.getElementById('main-content');
        main.innerHTML = `<div class="page-header"><div><h2>Dashboard Gerencial</h2><p>Visão consolidada da operação em tempo real</p></div>
            <div class="actions-bar">
                <select id="dash-period" onchange="DashboardModule.period=this.value; DashboardModule.render()">
                    <option value="dia" ${this.period==='dia'?'selected':''}>Hoje</option>
                    <option value="semana" ${this.period==='semana'?'selected':''}>Últimos 7 dias</option>
                    <option value="mes" ${this.period==='mes'?'selected':''}>Este Mês</option>
                    <option value="trimestre" ${this.period==='trimestre'?'selected':''}>Trimestre</option>
                    <option value="ano" ${this.period==='ano'?'selected':''}>Este Ano</option>
                </select>
                <button class="btn btn-primary" onclick="App.navigate('sales')"><i data-lucide="plus"></i> Nova Venda</button>
            </div></div><div id="dash-body"><p>Carregando indicadores...</p></div>`;
        if (window.lucide) lucide.createIcons();

        try {
            const data = await API.get(`dashboard&period=${this.period}`);
            const k = data.kpis || {};
            const c = data.charts || { monthly: [], expenses_by_category: [], top_products: [], top_customers: [], production_by_printer: [] };

            const badge = document.getElementById('demo-badge');
            if (badge) badge.classList.toggle('hidden', !data.demo_banner);

            document.getElementById('dash-body').innerHTML = `
                <div class="kpi-grid">
                    <div class="kpi-card"><div class="kpi-label">Faturamento Hoje</div><div class="kpi-value">${fmtBRL(k.faturamento_dia || 0)}</div><div class="kpi-sub">Vendas do dia</div></div>
                    <div class="kpi-card"><div class="kpi-label">Faturamento Mês</div><div class="kpi-value">${fmtBRL(k.faturamento_mes || 0)}</div><div class="kpi-sub">Acumulado mensal</div></div>
                    <div class="kpi-card"><div class="kpi-label">Faturamento Anual</div><div class="kpi-value">${fmtBRL(k.faturamento_anual || 0)}</div><div class="kpi-sub">Acumulado no ano</div></div>
                    <div class="kpi-card"><div class="kpi-label">Lucro Bruto (Período)</div><div class="kpi-value" style="color:var(--info)">${fmtBRL(k.lucro_bruto || 0)}</div><div class="kpi-sub">Receita - custo produto</div></div>
                    <div class="kpi-card"><div class="kpi-label">Lucro Líquido (Período)</div><div class="kpi-value" style="color:${(k.lucro_liquido||0)>=0?'var(--success)':'var(--danger)'}">${fmtBRL(k.lucro_liquido || 0)}</div><div class="kpi-sub">Margem: ${k.margem_lucro || 0}%</div></div>
                    <div class="kpi-card"><div class="kpi-label">Despesas (Período)</div><div class="kpi-value" style="color:var(--danger)">${fmtBRL(k.total_despesas || 0)}</div><div class="kpi-sub">Custos e despesas</div></div>
                    <div class="kpi-card"><div class="kpi-label">Ticket Médio</div><div class="kpi-value">${fmtBRL(k.ticket_medio || 0)}</div><div class="kpi-sub">Média por venda</div></div>
                    <div class="kpi-card"><div class="kpi-label">Ocupação Impressoras</div><div class="kpi-value">${k.taxa_ocupacao_impressoras || 0}%</div><div class="kpi-sub">Parque de máquinas</div></div>
                </div>

                <div class="kpi-grid" style="grid-template-columns: repeat(auto-fill, minmax(165px, 1fr));">
                    <div class="kpi-card"><div class="kpi-label">Total de Pedidos</div><div class="kpi-value">${k.total_pedidos || 0}</div></div>
                    <div class="kpi-card"><div class="kpi-label">Em Produção</div><div class="kpi-value" style="color:var(--primary)">${k.pedidos_producao || 0}</div></div>
                    <div class="kpi-card"><div class="kpi-label">Aguard. Aprovação</div><div class="kpi-value" style="color:var(--warning)">${k.pedidos_aguardando_aprovacao || 0}</div></div>
                    <div class="kpi-card"><div class="kpi-label">Pedidos Atrasados</div><div class="kpi-value" style="color:var(--danger)">${k.pedidos_atrasados || 0}</div></div>
                    <div class="kpi-card"><div class="kpi-label">Aguard. Entrega</div><div class="kpi-value" style="color:var(--info)">${k.pedidos_aguardando_entrega || 0}</div></div>
                    <div class="kpi-card"><div class="kpi-label">Concluídos</div><div class="kpi-value" style="color:var(--success)">${k.pedidos_concluidos || 0}</div></div>
                </div>

                <div class="grid-2">
                    <div class="card"><div class="card-header"><span class="card-title">Faturamento e Lucro por Mês</span></div><div style="height:260px"><canvas id="chart-rev-profit"></canvas></div></div>
                    <div class="card"><div class="card-header"><span class="card-title">Evolução de Pedidos</span></div><div style="height:260px"><canvas id="chart-orders-evo"></canvas></div></div>
                </div>

                <div class="grid-2">
                    <div class="card"><div class="card-header"><span class="card-title">Despesas por Categoria</span></div><div style="height:250px"><canvas id="chart-exp-cat"></canvas></div></div>
                    <div class="card"><div class="card-header"><span class="card-title">Produção por Impressora (Horas)</span></div><div style="height:250px"><canvas id="chart-printers"></canvas></div></div>
                </div>

                <div class="grid-2">
                    <div class="card"><div class="card-header"><span class="card-title">Produtos Mais Vendidos</span></div><div style="height:250px"><canvas id="chart-top-prod"></canvas></div></div>
                    <div class="card"><div class="card-header"><span class="card-title">Clientes que Mais Compram</span></div><div style="height:250px"><canvas id="chart-top-cust"></canvas></div></div>
                </div>
            `;

            ChartManager.render('chart-rev-profit', {
                type: 'bar',
                data: {
                    labels: c.monthly.map(m => m.label),
                    datasets: [
                        { label: 'Faturamento (R$)', data: c.monthly.map(m => m.revenue), backgroundColor: '#4f46e5' },
                        { label: 'Lucro (R$)', data: c.monthly.map(m => m.profit), backgroundColor: '#10b981' },
                    ]
                }
            });

            ChartManager.render('chart-orders-evo', {
                type: 'line',
                data: {
                    labels: c.monthly.map(m => m.label),
                    datasets: [{ label: 'Pedidos', data: c.monthly.map(m => m.orders), borderColor: '#0ea5e9', tension: 0.3, fill: true, backgroundColor: 'rgba(14,165,233,0.12)' }]
                }
            });

            ChartManager.render('chart-exp-cat', {
                type: 'doughnut',
                data: {
                    labels: c.expenses_by_category.map(e => e.category),
                    datasets: [{ data: c.expenses_by_category.map(e => e.total), backgroundColor: ['#ef4444','#f59e0b','#6366f1','#0ea5e9','#10b981','#8b5cf6'] }]
                }
            });

            ChartManager.render('chart-printers', {
                type: 'bar',
                data: {
                    labels: c.production_by_printer.map(p => p.name),
                    datasets: [{ label: 'Horas Trabalhadas', data: c.production_by_printer.map(p => p.hours), backgroundColor: '#8b5cf6' }]
                }
            });

            ChartManager.render('chart-top-prod', {
                type: 'bar',
                data: {
                    labels: c.top_products.map(p => p.name),
                    datasets: [{ label: 'Qtd Vendida', data: c.top_products.map(p => p.qty), backgroundColor: '#10b981' }]
                },
                options: { indexAxis: 'y' }
            });

            ChartManager.render('chart-top-cust', {
                type: 'bar',
                data: {
                    labels: c.top_customers.map(cu => cu.name),
                    datasets: [{ label: 'Total Gasto (R$)', data: c.top_customers.map(cu => cu.total), backgroundColor: '#f59e0b' }]
                },
                options: { indexAxis: 'y' }
            });
        } catch (err) {
            document.getElementById('dash-body').innerHTML = `<div class="card"><p style="color:var(--danger)">${esc(err.message)}</p></div>`;
        }
    }
};

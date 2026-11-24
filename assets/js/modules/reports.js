/**
 * Módulo 11: Relatórios Gerenciais com Exportação PDF e Excel
 */
const ReportsModule = {
    lastData: null,

    async render() {
        const start = new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().slice(0, 10);
        const end = new Date().toISOString().slice(0, 10);
        const main = document.getElementById('main-content');
        main.innerHTML = `<div class="page-header">
            <div><h2>Relatórios Gerenciais</h2><p>Lucratividade, desempenho de máquinas, clientes, desperdício e previsão</p></div>
            <div class="actions-bar">
                <input type="date" id="rep-start" value="${start}">
                <input type="date" id="rep-end" value="${end}">
                <button class="btn btn-secondary" onclick="ReportsModule.load()"><i data-lucide="filter"></i> Filtrar</button>
                <button class="btn btn-primary" onclick="ReportsModule.exportPDF()"><i data-lucide="file-text"></i> Exportar PDF</button>
                <button class="btn btn-success" onclick="ReportsModule.exportExcel()"><i data-lucide="file-spreadsheet"></i> Exportar Excel</button>
            </div>
        </div>
        <div id="rep-body">Carregando relatórios...</div>`;
        if (window.lucide) lucide.createIcons();
        this.load();
    },

    async load() {
        const s = document.getElementById('rep-start').value;
        const e = document.getElementById('rep-end').value;
        const data = await API.get(`reports&start_date=${s}&end_date=${e}`);
        this.lastData = data;
        const sum = data.summary;

        document.getElementById('rep-body').innerHTML = `
            <div class="kpi-grid">
                <div class="kpi-card"><div class="kpi-label">Faturamento Período</div><div class="kpi-value">${fmtBRL(sum.total_revenue)}</div></div>
                <div class="kpi-card"><div class="kpi-label">Lucro Bruto</div><div class="kpi-value">${fmtBRL(sum.gross_profit)}</div></div>
                <div class="kpi-card"><div class="kpi-label">Lucro Líquido</div><div class="kpi-value" style="color:${sum.net_profit>=0?'var(--success)':'var(--danger)'}">${fmtBRL(sum.net_profit)}</div></div>
                <div class="kpi-card"><div class="kpi-label">Margem Líquida</div><div class="kpi-value">${sum.profit_margin}%</div></div>
                <div class="kpi-card"><div class="kpi-label">Material Consumido</div><div class="kpi-value">${sum.material_consumed_g}g</div></div>
                <div class="kpi-card"><div class="kpi-label">Previsão Próx. Mês</div><div class="kpi-value" style="color:var(--primary)">${fmtBRL(sum.forecast_next_month)}</div></div>
            </div>

            <div class="grid-2">
                <div class="card"><div class="card-header"><span class="card-title">Produtos (Lucratividade e Vendas)</span></div>
                    <div class="table-wrap"><table><thead><tr><th>Produto</th><th>Qtd</th><th>Receita</th><th>Custo</th><th>Lucro</th><th>Margem</th></tr></thead>
                    <tbody>${data.products.map(p=>`<tr><td>${esc(p.description)}</td><td>${p.qty}</td><td>${fmtBRL(p.revenue)}</td><td>${fmtBRL(p.cost)}</td><td><strong>${fmtBRL(p.profit)}</strong></td><td>${p.margin}%</td></tr>`).join('') || '<tr><td colspan="6">Sem dados</td></tr>'}</tbody></table></div>
                </div>
                <div class="card"><div class="card-header"><span class="card-title">Desempenho e Custos por Impressora</span></div>
                    <div class="table-wrap"><table><thead><tr><th>Impressora</th><th>Horas</th><th>Sucessos</th><th>Falhas</th><th>Custo Est.</th></tr></thead>
                    <tbody>${data.printers.map(pr=>`<tr><td>${esc(pr.name)}</td><td>${pr.logged_hours}h</td><td>${pr.success_prints}</td><td>${pr.fail_prints}</td><td>${fmtBRL(pr.estimated_op_cost)}</td></tr>`).join('')}</tbody></table></div>
                </div>
            </div>

            <div class="grid-2">
                <div class="card"><div class="card-header"><span class="card-title">Clientes Mais Rentáveis</span></div>
                    <div class="table-wrap"><table><thead><tr><th>Cliente</th><th>Pedidos</th><th>Total</th></tr></thead>
                    <tbody>${data.customers.map(c=>`<tr><td>${esc(c.name)}</td><td>${c.orders_count}</td><td><strong>${fmtBRL(c.total_spent)}</strong></td></tr>`).join('') || '<tr><td colspan="3">Sem dados</td></tr>'}</tbody></table></div>
                </div>
                <div class="card"><div class="card-header"><span class="card-title">Despesas por Categoria</span></div>
                    <div class="table-wrap"><table><thead><tr><th>Categoria</th><th>Classificação</th><th>Total</th></tr></thead>
                    <tbody>${data.expenses_by_category.map(ex=>`<tr><td>${esc(ex.category)}</td><td>${esc(ex.classification)}</td><td>${fmtBRL(ex.total)}</td></tr>`).join('') || '<tr><td colspan="3">Sem dados</td></tr>'}</tbody></table></div>
                </div>
            </div>

            <div class="grid-2">
                <div class="card"><div class="card-header"><span class="card-title">Pedidos Atrasados (${data.late_orders.length})</span></div>
                    <div class="table-wrap"><table><thead><tr><th>OP</th><th>Pedido</th><th>Prazo</th></tr></thead>
                    <tbody>${data.late_orders.map(o=>`<tr><td class="mono">${esc(o.order_number)}</td><td>${esc(o.title)}</td><td style="color:var(--danger)">${fmtDate(o.due_date)}</td></tr>`).join('') || '<tr><td colspan="3">Nenhum pedido atrasado</td></tr>'}</tbody></table></div>
                </div>
                <div class="card"><div class="card-header"><span class="card-title">Comparativo Mensal</span></div>
                    <div class="table-wrap"><table><thead><tr><th>Mês</th><th>Pedidos</th><th>Faturamento</th></tr></thead>
                    <tbody>${data.monthly_comparison.map(m=>`<tr><td>${esc(m.month)}</td><td>${m.orders}</td><td><strong>${fmtBRL(m.revenue)}</strong></td></tr>`).join('') || '<tr><td colspan="3">Sem dados</td></tr>'}</tbody></table></div>
                </div>
            </div>
        `;
    },

    exportPDF() {
        if (!this.lastData) return;
        const d = this.lastData;
        Exporter.toPDF(
            'relatorio-gerencial',
            `Relatório Gerencial (${fmtDate(d.period.start_date)} a ${fmtDate(d.period.end_date)})`,
            ['Produto', 'Qtd', 'Receita', 'Custo', 'Lucro', 'Margem %'],
            d.products.map(p => [p.description, p.qty, fmtBRL(p.revenue), fmtBRL(p.cost), fmtBRL(p.profit), p.margin + '%']),
            [
                `Faturamento: ${fmtBRL(d.summary.total_revenue)} | Lucro Bruto: ${fmtBRL(d.summary.gross_profit)} | Lucro Líquido: ${fmtBRL(d.summary.net_profit)}`,
                `Margem Líquida: ${d.summary.profit_margin}% | Previsão Próximo Mês: ${fmtBRL(d.summary.forecast_next_month)}`
            ]
        );
    },

    exportExcel() {
        if (!this.lastData) return;
        const d = this.lastData;
        Exporter.toExcel('relatorio-varzhun-3d', [
            { name: 'Produtos', rows: d.products },
            { name: 'Impressoras', rows: d.printers },
            { name: 'Clientes', rows: d.customers },
            { name: 'Despesas', rows: d.expenses_by_category }
        ]);
    }
};

/**
 * Módulo 10: Financeiro (Contas a Receber, Contas a Pagar e Fluxo de Caixa)
 */
const FinanceModule = {
    filterType: '',

    async render() {
        const main = document.getElementById('main-content');
        main.innerHTML = `<div class="page-header">
            <div><h2>Financeiro</h2><p>Contas a receber, contas a pagar, fluxo de caixa e classificação de custos</p></div>
            <div class="actions-bar">
                <select onchange="FinanceModule.filterType=this.value; FinanceModule.load()">
                    <option value="">Todas movimentações</option>
                    <option value="entrada" ${this.filterType==='entrada'?'selected':''}>Entradas / A Receber</option>
                    <option value="saida" ${this.filterType==='saida'?'selected':''}>Saídas / A Pagar</option>
                </select>
                <button class="btn btn-primary" onclick="FinanceModule.openForm()"><i data-lucide="plus"></i> Novo Lançamento</button>
            </div>
        </div>
        <div id="fin-summary" class="kpi-grid"></div>
        <div class="card"><div class="table-wrap" id="fin-table">Carregando...</div></div>`;
        if (window.lucide) lucide.createIcons();
        this.load();
    },

    async load() {
        const res = await API.get(`finance${this.filterType ? '&type=' + this.filterType : ''}`);
        const s = res.summary || {};
        document.getElementById('fin-summary').innerHTML = `
            <div class="kpi-card"><div class="kpi-label">Saldo Atual</div><div class="kpi-value" style="color:${s.saldo_atual>=0?'var(--success)':'var(--danger)'}">${fmtBRL(s.saldo_atual)}</div></div>
            <div class="kpi-card"><div class="kpi-label">A Receber</div><div class="kpi-value" style="color:var(--info)">${fmtBRL(s.a_receber)}</div><div class="kpi-sub">Atrasado: ${fmtBRL(s.a_receber_atrasado)}</div></div>
            <div class="kpi-card"><div class="kpi-label">A Pagar</div><div class="kpi-value" style="color:var(--warning)">${fmtBRL(s.a_pagar)}</div><div class="kpi-sub">Vencido: ${fmtBRL(s.a_pagar_atrasado)}</div></div>
            <div class="kpi-card"><div class="kpi-label">Previsão de Saldo</div><div class="kpi-value">${fmtBRL(s.previsao_saldo)}</div></div>
            <div class="kpi-card"><div class="kpi-label">Custos Produção</div><div class="kpi-value">${fmtBRL(s.custos_producao)}</div></div>
            <div class="kpi-card"><div class="kpi-label">Despesas Operacionais</div><div class="kpi-value">${fmtBRL(s.despesas_operacionais)}</div></div>
        `;

        const rows = res.transactions || [];
        document.getElementById('fin-table').innerHTML = rows.length ? `<table>
            <thead><tr><th>Vencimento</th><th>Tipo</th><th>Classificação</th><th>Categoria</th><th>Descrição</th><th>Valor</th><th>Status</th><th>Ações</th></tr></thead>
            <tbody>${rows.map(t => `<tr>
                <td>${fmtDate(t.due_date)}</td>
                <td><span class="badge badge-${t.type==='entrada'?'success':'danger'}">${t.type==='entrada'?'Entrada':'Saída'}</span></td>
                <td>${esc(t.classification)}</td>
                <td>${esc(t.category)}</td>
                <td>${esc(t.description)} ${t.is_recurring?'<span class="badge badge-neutral">Recorrente</span>':''}</td>
                <td><strong>${fmtBRL(t.amount)}</strong></td>
                <td><span class="badge badge-${t.status==='pago'?'success':(t.status==='atrasado'?'danger':'warning')}">${esc(t.status)}</span></td>
                <td class="actions-bar">
                    ${t.status!=='pago' ? `<button class="btn btn-sm btn-success" onclick="API.post('finance/pay/${t.id}',{}).then(()=>{showToast('Baixado!'); FinanceModule.load();})">Baixar</button>` : ''}
                    <button class="btn btn-sm btn-danger" onclick="API.del('finance/${t.id}').then(()=>FinanceModule.load())"><i data-lucide="trash-2"></i></button>
                </td>
            </tr>`).join('')}</tbody>
        </table>` : '<p>Nenhum lançamento encontrado.</p>';
        if (window.lucide) lucide.createIcons();
    },

    openForm() {
        openModal('Novo Lançamento Financeiro', `
            <form id="fin-form">
                <div class="form-grid">
                    <div class="form-group"><label>Tipo</label><select name="type"><option value="saida">Saída (Despesa / Custo)</option><option value="entrada">Entrada (Receita)</option></select></div>
                    <div class="form-group"><label>Classificação</label><select name="classification">
                        <option value="custo_producao">Custo de Produção</option>
                        <option value="despesa_operacional">Despesa Operacional</option>
                        <option value="investimento">Investimento</option>
                        <option value="receita">Receita</option>
                    </select></div>
                    <div class="form-group"><label>Categoria *</label><input name="category" required placeholder="Ex: Energia Elétrica, Aluguel, Vendas"></div>
                    <div class="form-group"><label>Valor (R$) *</label><input type="number" step="0.01" min="0.01" name="amount" required></div>
                    <div class="form-group"><label>Vencimento *</label><input type="date" name="due_date" required value="${new Date().toISOString().slice(0,10)}"></div>
                    <div class="form-group"><label>Status</label><select name="status"><option value="pago">Pago</option><option value="pendente">Pendente</option></select></div>
                    <div class="form-group"><label>Forma Pagamento</label><select name="payment_method"><option>PIX</option><option>Boleto</option><option>Cartão de crédito</option><option>Débito Automático</option><option>Dinheiro</option></select></div>
                    <div class="form-group"><label>Recorrente?</label><select name="is_recurring"><option value="0">Não</option><option value="1">Sim (Mensal)</option></select></div>
                </div>
                <div class="form-group"><label>Descrição *</label><input name="description" required></div>
                <button type="submit" class="btn btn-primary btn-block">Salvar Lançamento</button>
            </form>
        `);
        document.getElementById('fin-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const res = await API.post('finance', Object.fromEntries(new FormData(e.target)));
            closeModal();
            showToast(res.message);
            this.load();
        });
    }
};

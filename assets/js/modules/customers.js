/**
 * Módulo 3: Gestão de Clientes (CRM)
 */
const CustomersModule = {
    async render() {
        const main = document.getElementById('main-content');
        main.innerHTML = `<div class="page-header">
            <div><h2>Clientes (CRM)</h2><p>Cadastro completo, histórico de compras, pedidos e atendimentos</p></div>
            <div class="actions-bar">
                <input type="text" id="cust-search" placeholder="Pesquisar cliente..." oninput="CustomersModule.load(this.value)">
                <button class="btn btn-primary" onclick="CustomersModule.openForm()"><i data-lucide="user-plus"></i> Novo Cliente</button>
            </div>
        </div>
        <div class="card"><div class="table-wrap" id="cust-table">Carregando...</div></div>`;
        if (window.lucide) lucide.createIcons();
        this.load();
    },

    async load(q = '') {
        const res = await API.get(`customers${q ? '&q=' + encodeURIComponent(q) : ''}`);
        const rows = res.customers || [];
        document.getElementById('cust-table').innerHTML = rows.length ? `<table>
            <thead><tr><th>Nome</th><th>CPF / CNPJ</th><th>WhatsApp / Tel</th><th>Total Gasto</th><th>Pendente</th><th>Última Compra</th><th>Ações</th></tr></thead>
            <tbody>${rows.map(c => `<tr>
                <td><strong>${esc(c.name)}</strong><br><small style="color:var(--text-muted)">${esc(c.email)}</small></td>
                <td>${esc(c.cpf_cnpj || '—')}</td>
                <td>${esc(c.whatsapp || c.phone || '—')}</td>
                <td><strong>${fmtBRL(c.total_spent)}</strong></td>
                <td style="color:${Number(c.pending_amount)>0?'var(--danger)':'var(--success)'}">${fmtBRL(c.pending_amount)}</td>
                <td>${fmtDate(c.last_purchase)}</td>
                <td class="actions-bar">
                    <button class="btn btn-sm btn-secondary" onclick="CustomersModule.viewHistory(${c.id})"><i data-lucide="history"></i> Histórico</button>
                    <button class="btn btn-sm btn-secondary" onclick='CustomersModule.openForm(${JSON.stringify(c).replace(/'/g,"&#39;")})'><i data-lucide="edit"></i></button>
                    <button class="btn btn-sm btn-danger" onclick="CustomersModule.remove(${c.id})"><i data-lucide="trash-2"></i></button>
                </td>
            </tr>`).join('')}</tbody>
        </table>` : '<p>Nenhum cliente encontrado.</p>';
        if (window.lucide) lucide.createIcons();
    },

    openForm(c = null) {
        openModal(c ? 'Editar Cliente' : 'Novo Cliente', `
            <form id="cust-form">
                <div class="form-grid">
                    <div class="form-group"><label>Nome Completo / Razão Social *</label><input name="name" required value="${esc(c?.name)}"></div>
                    <div class="form-group"><label>CPF ou CNPJ</label><input name="cpf_cnpj" value="${esc(c?.cpf_cnpj)}"></div>
                    <div class="form-group"><label>Telefone</label><input name="phone" value="${esc(c?.phone)}"></div>
                    <div class="form-group"><label>WhatsApp</label><input name="whatsapp" value="${esc(c?.whatsapp)}"></div>
                    <div class="form-group"><label>E-mail</label><input type="email" name="email" value="${esc(c?.email)}"></div>
                </div>
                <div class="form-group"><label>Endereço</label><input name="address" value="${esc(c?.address)}"></div>
                <div class="form-group"><label>Observações</label><textarea name="notes" rows="2">${esc(c?.notes)}</textarea></div>
                <button type="submit" class="btn btn-primary btn-block">Salvar Cliente</button>
            </form>
        `);
        document.getElementById('cust-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const data = Object.fromEntries(new FormData(e.target));
            try {
                const res = c ? await API.put(`customers/${c.id}`, data) : await API.post('customers', data);
                closeModal();
                showToast(res.message);
                this.load();
            } catch (err) { showToast(err.message, 'error'); }
        });
    },

    async viewHistory(id) {
        const res = await API.get(`customers/${id}`);
        const c = res.customer;
        const s = res.summary;
        openModal(`Histórico — ${c.name}`, `
            <div class="kpi-grid" style="grid-template-columns: repeat(3, 1fr)">
                <div class="kpi-card"><div class="kpi-label">Total Gasto</div><div class="kpi-value">${fmtBRL(s.total_spent)}</div></div>
                <div class="kpi-card"><div class="kpi-label">Pendente</div><div class="kpi-value" style="color:var(--danger)">${fmtBRL(s.pending_amount)}</div></div>
                <div class="kpi-card"><div class="kpi-label">Última Compra</div><div class="kpi-value" style="font-size:16px">${fmtDate(s.last_purchase)}</div></div>
            </div>
            <p><strong>Compras (${res.purchases.length}):</strong> ${res.purchases.map(p => `${p.code} (${fmtBRL(p.total_amount)})`).join(', ') || 'Nenhuma'}</p>
            <p><strong>Pedidos em andamento (${res.active_orders.length}):</strong> ${res.active_orders.map(o => `${o.order_number} [${o.stage}]`).join(', ') || 'Nenhum'}</p>
            <p><strong>Orçamentos (${res.quotes.length}):</strong> ${res.quotes.map(q => `${q.code} (${fmtBRL(q.total_amount)})`).join(', ') || 'Nenhum'}</p>
            <hr style="margin:12px 0; border-color:var(--border)">
            <h4>Registrar Atendimento</h4>
            <form id="int-form" style="display:flex; gap:8px; margin:8px 0">
                <input name="description" required placeholder="Descreva o atendimento ou contato..." style="flex:1">
                <button class="btn btn-primary btn-sm">Registrar</button>
            </form>
            <div>${res.interactions.map(i => `<div style="padding:6px 0; border-bottom:1px solid var(--border)"><small>${fmtDateTime(i.created_at)} • ${esc(i.user_name||'Sistema')}</small><div>${esc(i.description)}</div></div>`).join('') || '<small>Sem atendimentos registrados.</small>'}</div>
        `);
        document.getElementById('int-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const desc = new FormData(e.target).get('description');
            await API.post(`customers/interaction/${id}`, { description: desc });
            showToast('Atendimento registrado!');
            this.viewHistory(id);
        });
    },

    async remove(id) {
        if (!confirm('Excluir este cliente?')) return;
        try {
            await API.del(`customers/${id}`);
            showToast('Cliente excluído.');
            this.load();
        } catch (err) { showToast(err.message, 'error'); }
    }
};

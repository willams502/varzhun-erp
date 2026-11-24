/**
 * Módulo 12: Fornecedores e Registro de Compras
 */
const SuppliersModule = {
    async render() {
        const main = document.getElementById('main-content');
        main.innerHTML = `<div class="page-header">
            <div><h2>Fornecedores</h2><p>Cadastro de fornecedores e compras com atualização automática de estoque</p></div>
            <button class="btn btn-primary" onclick="SuppliersModule.openForm()"><i data-lucide="plus"></i> Novo Fornecedor</button>
        </div>
        <div class="card" style="margin-bottom:16px"><div class="table-wrap" id="sup-table">Carregando...</div></div>
        <div class="card"><div class="card-header"><span class="card-title">Histórico de Compras</span></div><div class="table-wrap" id="sup-purchases"></div></div>`;
        if (window.lucide) lucide.createIcons();
        this.load();
    },

    async load() {
        const res = await API.get('suppliers');
        const rows = res.suppliers || [];
        document.getElementById('sup-table').innerHTML = rows.length ? `<table>
            <thead><tr><th>Fornecedor</th><th>CNPJ</th><th>Contato / Tel</th><th>Produtos Fornecidos</th><th>Total Comprado</th><th>Ações</th></tr></thead>
            <tbody>${rows.map(s => `<tr>
                <td><strong>${esc(s.name)}</strong><br><small>${esc(s.email)}</small></td>
                <td>${esc(s.cnpj || '—')}</td>
                <td>${esc(s.contact_name)} • ${esc(s.phone)}</td>
                <td>${esc(s.products_supplied)}</td>
                <td><strong>${fmtBRL(s.total_purchased)}</strong></td>
                <td class="actions-bar">
                    <button class="btn btn-sm btn-success" onclick="SuppliersModule.openPurchaseModal(${s.id}, '${esc(s.name)}')"><i data-lucide="shopping-bag"></i> Registrar Compra</button>
                    <button class="btn btn-sm btn-secondary" onclick='SuppliersModule.openForm(${JSON.stringify(s).replace(/'/g,"&#39;")})'><i data-lucide="edit"></i></button>
                    <button class="btn btn-sm btn-danger" onclick="API.del('suppliers/${s.id}').then(()=>SuppliersModule.load())"><i data-lucide="trash-2"></i></button>
                </td>
            </tr>`).join('')}</tbody>
        </table>` : '<p>Nenhum fornecedor cadastrado.</p>';

        document.getElementById('sup-purchases').innerHTML = (res.purchases||[]).length ? `<table>
            <thead><tr><th>Data</th><th>Fornecedor</th><th>Item</th><th>Qtd</th><th>Total</th></tr></thead>
            <tbody>${res.purchases.map(p => `<tr><td>${fmtDate(p.purchase_date)}</td><td>${esc(p.supplier_name)}</td><td>${esc(p.inventory_name || p.item_name)}</td><td>${p.quantity}</td><td><strong>${fmtBRL(p.total_cost)}</strong></td></tr>`).join('')}</tbody>
        </table>` : '<p>Nenhuma compra registrada.</p>';
        if (window.lucide) lucide.createIcons();
    },

    openForm(s = null) {
        openModal(s ? 'Editar Fornecedor' : 'Novo Fornecedor', `
            <form id="sup-form">
                <div class="form-grid">
                    <div class="form-group"><label>Nome *</label><input name="name" required value="${esc(s?.name)}"></div>
                    <div class="form-group"><label>CNPJ</label><input name="cnpj" value="${esc(s?.cnpj)}"></div>
                    <div class="form-group"><label>Contato</label><input name="contact_name" value="${esc(s?.contact_name)}"></div>
                    <div class="form-group"><label>Telefone</label><input name="phone" value="${esc(s?.phone)}"></div>
                    <div class="form-group"><label>E-mail</label><input type="email" name="email" value="${esc(s?.email)}"></div>
                    <div class="form-group"><label>Produtos Fornecidos</label><input name="products_supplied" value="${esc(s?.products_supplied)}"></div>
                </div>
                <div class="form-group"><label>Endereço</label><input name="address" value="${esc(s?.address)}"></div>
                <button type="submit" class="btn btn-primary btn-block">Salvar Fornecedor</button>
            </form>
        `);
        document.getElementById('sup-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const data = Object.fromEntries(new FormData(e.target));
            const res = s ? await API.put(`suppliers/${s.id}`, data) : await API.post('suppliers', data);
            closeModal();
            showToast(res.message);
            this.load();
        });
    },

    async openPurchaseModal(id, name) {
        const invRes = await API.get('inventory');
        openModal(`Registrar Compra — ${name}`, `
            <form id="pur-form">
                <div class="form-group"><label>Atualizar Item do Estoque (Opcional)</label>
                    <select name="inventory_item_id"><option value="">-- Compra avulsa --</option>${(invRes.items||[]).map(i=>`<option value="${i.id}">${esc(i.name)} (atual: ${i.quantity} ${i.unit})</option>`).join('')}</select>
                </div>
                <div class="form-grid">
                    <div class="form-group"><label>Quantidade *</label><input type="number" step="0.01" min="0.01" name="quantity" required value="1000"></div>
                    <div class="form-group"><label>Custo Unitário (R$) *</label><input type="number" step="0.01" min="0" name="unit_cost" required value="0.09"></div>
                    <div class="form-group"><label>Data Compra</label><input type="date" name="purchase_date" value="${new Date().toISOString().slice(0,10)}"></div>
                    <div class="form-group"><label>Status Pagamento</label><select name="paid"><option value="1">Pago à vista</option><option value="0">A Pagar (Pendente)</option></select></div>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Registrar Compra e Atualizar Estoque</button>
            </form>
        `);
        document.getElementById('pur-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const res = await API.post(`suppliers/purchase/${id}`, Object.fromEntries(new FormData(e.target)));
            closeModal();
            showToast(res.message);
            this.load();
        });
    }
};

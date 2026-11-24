/**
 * Módulo 9: Controle de Estoque
 */
const InventoryModule = {
    async render() {
        const main = document.getElementById('main-content');
        main.innerHTML = `<div class="page-header">
            <div><h2>Controle de Estoque</h2><p>Filamentos, resinas, peças de reposição, embalagens e alertas</p></div>
            <div class="actions-bar">
                <input type="text" placeholder="Buscar material..." oninput="InventoryModule.load(this.value)">
                <button class="btn btn-primary" onclick="InventoryModule.openForm()"><i data-lucide="plus"></i> Novo Item</button>
            </div>
        </div>
        <div class="card" style="margin-bottom:16px"><div class="table-wrap" id="inv-table">Carregando...</div></div>
        <div class="card"><div class="card-header"><span class="card-title">Últimas Movimentações</span></div><div class="table-wrap" id="inv-movements"></div></div>`;
        if (window.lucide) lucide.createIcons();
        this.load();
    },

    async load(q = '') {
        const res = await API.get(`inventory${q ? '&q=' + encodeURIComponent(q) : ''}`);
        const items = res.items || [];
        document.getElementById('inv-table').innerHTML = items.length ? `<table>
            <thead><tr><th>Item</th><th>Categoria</th><th>Saldo Atual</th><th>Mínimo</th><th>Custo</th><th>Fornecedor</th><th>Ações</th></tr></thead>
            <tbody>${items.map(i => `<tr>
                <td><strong>${esc(i.name)}</strong> ${i.is_low_stock?'<span class="badge badge-danger">ESTOQUE BAIXO</span>':''}<br><small>${esc(i.material_type)} ${esc(i.color)}</small></td>
                <td>${esc(i.category)}</td>
                <td><strong style="color:${i.is_low_stock?'var(--danger)':'inherit'}">${i.quantity} ${esc(i.unit)}</strong></td>
                <td>${i.min_stock} ${esc(i.unit)}</td>
                <td>${fmtBRL(i.acquisition_cost)}</td>
                <td>${esc(i.supplier_name || '—')}</td>
                <td class="actions-bar">
                    <button class="btn btn-sm btn-secondary" onclick="InventoryModule.openMoveModal(${i.id}, '${esc(i.name)}')"><i data-lucide="arrow-up-down"></i> Movimentar</button>
                    <button class="btn btn-sm btn-secondary" onclick='InventoryModule.openForm(${JSON.stringify(i).replace(/'/g,"&#39;")})'><i data-lucide="edit"></i></button>
                    <button class="btn btn-sm btn-danger" onclick="API.del('inventory/${i.id}').then(()=>InventoryModule.load())"><i data-lucide="trash-2"></i></button>
                </td>
            </tr>`).join('')}</tbody>
        </table>` : '<p>Nenhum item no estoque.</p>';

        document.getElementById('inv-movements').innerHTML = (res.movements||[]).length ? `<table>
            <thead><tr><th>Data</th><th>Item</th><th>Tipo</th><th>Qtd</th><th>Observação</th></tr></thead>
            <tbody>${res.movements.map(m => `<tr><td>${fmtDateTime(m.created_at)}</td><td>${esc(m.item_name)}</td><td>${esc(m.movement_type)}</td><td class="mono">${m.quantity} ${esc(m.unit||'')}</td><td>${esc(m.notes)}</td></tr>`).join('')}</tbody>
        </table>` : '<p>Sem movimentações.</p>';
        if (window.lucide) lucide.createIcons();
    },

    async openForm(i = null) {
        const supRes = await API.get('suppliers');
        openModal(i ? 'Editar Item' : 'Novo Item de Estoque', `
            <form id="inv-form">
                <div class="form-grid">
                    <div class="form-group"><label>Nome *</label><input name="name" required value="${esc(i?.name)}"></div>
                    <div class="form-group"><label>Categoria</label><select name="category">
                        <option value="filamento" ${i?.category==='filamento'?'selected':''}>Filamento</option>
                        <option value="resina" ${i?.category==='resina'?'selected':''}>Resina</option>
                        <option value="peca_reposicao" ${i?.category==='peca_reposicao'?'selected':''}>Peça de Reposição</option>
                        <option value="embalagem" ${i?.category==='embalagem'?'selected':''}>Embalagem</option>
                        <option value="insumo" ${i?.category==='insumo'?'selected':''}>Insumo</option>
                        <option value="produto_acabado" ${i?.category==='produto_acabado'?'selected':''}>Produto Acabado</option>
                    </select></div>
                    <div class="form-group"><label>Material</label><input name="material_type" value="${esc(i?.material_type)}"></div>
                    <div class="form-group"><label>Cor</label><input name="color" value="${esc(i?.color)}"></div>
                    ${!i ? `<div class="form-group"><label>Quantidade Inicial</label><input type="number" step="0.01" name="quantity" value="1000"></div>` : ''}
                    <div class="form-group"><label>Unidade</label><select name="unit"><option value="g" ${i?.unit==='g'?'selected':''}>g</option><option value="kg" ${i?.unit==='kg'?'selected':''}>kg</option><option value="ml" ${i?.unit==='ml'?'selected':''}>ml</option><option value="un" ${i?.unit==='un'?'selected':''}>un</option></select></div>
                    <div class="form-group"><label>Custo Aquisição (R$)</label><input type="number" step="0.01" name="acquisition_cost" value="${i?.acquisition_cost ?? 0}"></div>
                    <div class="form-group"><label>Estoque Mínimo</label><input type="number" step="0.01" name="min_stock" value="${i?.min_stock ?? 500}"></div>
                    <div class="form-group"><label>Fornecedor</label><select name="supplier_id"><option value="">-- Nenhum --</option>${(supRes.suppliers||[]).map(s=>`<option value="${s.id}" ${i?.supplier_id==s.id?'selected':''}>${esc(s.name)}</option>`).join('')}</select></div>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Salvar Item</button>
            </form>
        `);
        document.getElementById('inv-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const data = Object.fromEntries(new FormData(e.target));
            const res = i ? await API.put(`inventory/${i.id}`, data) : await API.post('inventory', data);
            closeModal();
            showToast(res.message);
            this.load();
            App.loadNotifications();
        });
    },

    openMoveModal(id, name) {
        openModal(`Movimentar — ${name}`, `
            <form id="imove-form">
                <div class="form-group"><label>Tipo</label><select name="movement_type"><option value="entrada">Entrada (+)</option><option value="saida">Saída (-)</option></select></div>
                <div class="form-group"><label>Quantidade *</label><input type="number" step="0.01" min="0.01" name="quantity" required></div>
                <div class="form-group"><label>Observação</label><input name="notes" placeholder="Motivo da movimentação"></div>
                <button type="submit" class="btn btn-primary btn-block">Confirmar</button>
            </form>
        `);
        document.getElementById('imove-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            try {
                const res = await API.post(`inventory/movement/${id}`, Object.fromEntries(new FormData(e.target)));
                closeModal();
                showToast(res.message);
                this.load();
                App.loadNotifications();
            } catch (err) { showToast(err.message, 'error'); }
        });
    }
};

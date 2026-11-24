/**
 * Módulo 4: Catálogo de Produtos 3D
 */
const ProductsModule = {
    async render() {
        const main = document.getElementById('main-content');
        main.innerHTML = `<div class="page-header">
            <div><h2>Catálogo de Produtos 3D</h2><p>Peças recorrentes, personalizadas, arquivos STL/3MF, custos e margens</p></div>
            <div class="actions-bar">
                <input type="text" placeholder="Buscar produto ou SKU..." oninput="ProductsModule.load(this.value)">
                <button class="btn btn-primary" onclick="ProductsModule.openForm()"><i data-lucide="plus"></i> Novo Produto</button>
            </div>
        </div>
        <div class="card"><div class="table-wrap" id="prod-table">Carregando...</div></div>`;
        if (window.lucide) lucide.createIcons();
        this.load();
    },

    async load(q = '') {
        const res = await API.get(`products${q ? '&q=' + encodeURIComponent(q) : ''}`);
        const rows = res.products || [];
        document.getElementById('prod-table').innerHTML = rows.length ? `<table>
            <thead><tr><th>SKU</th><th>Produto</th><th>Tipo</th><th>Material / Peso / Tempo</th><th>Estoque</th><th>Custo</th><th>Venda</th><th>Margem</th><th>Ações</th></tr></thead>
            <tbody>${rows.map(p => `<tr>
                <td class="mono">${esc(p.sku)}</td>
                <td><strong>${esc(p.name)}</strong><br><small style="color:var(--text-muted)">${esc(p.category)} ${p.stl_file?'• STL':''} ${p.threemf_file?'• 3MF':''}</small></td>
                <td><span class="badge ${p.product_type==='recorrente'?'badge-info':'badge-warning'}">${esc(p.product_type)}</span></td>
                <td>${esc(p.material)} ${esc(p.color)} • ${p.estimated_weight_g}g • ${p.estimated_time_min}min</td>
                <td>${p.stock_qty} un</td>
                <td>${fmtBRL(p.manufacturing_cost)}</td>
                <td><strong>${fmtBRL(p.sale_price)}</strong></td>
                <td><span class="badge badge-success">${p.profit_margin}%</span></td>
                <td class="actions-bar">
                    <button class="btn btn-sm btn-secondary" onclick='ProductsModule.openForm(${JSON.stringify(p).replace(/'/g,"&#39;")})'><i data-lucide="edit"></i></button>
                    <button class="btn btn-sm btn-danger" onclick="ProductsModule.remove(${p.id})"><i data-lucide="trash-2"></i></button>
                </td>
            </tr>`).join('')}</tbody>
        </table>` : '<p>Nenhum produto cadastrado.</p>';
        if (window.lucide) lucide.createIcons();
    },

    async openForm(p = null) {
        const invRes = await API.get('inventory');
        const invItems = invRes.items || [];
        openModal(p ? 'Editar Produto' : 'Novo Produto', `
            <form id="prod-form">
                <div class="form-grid">
                    <div class="form-group"><label>Nome *</label><input name="name" required value="${esc(p?.name)}"></div>
                    <div class="form-group"><label>Código SKU</label><input name="sku" value="${esc(p?.sku)}"></div>
                    <div class="form-group"><label>Categoria</label><input name="category" value="${esc(p?.category || 'Action Figures & Colecionáveis')}"></div>
                    <div class="form-group"><label>Tipo de Fabricação</label>
                        <select name="product_type"><option value="recorrente" ${p?.product_type==='recorrente'?'selected':''}>Recorrente</option><option value="personalizado" ${p?.product_type==='personalizado'?'selected':''}>Personalizado</option></select>
                    </div>
                    <div class="form-group"><label>Material</label><input name="material" value="${esc(p?.material || 'PLA')}"></div>
                    <div class="form-group"><label>Cor</label><input name="color" value="${esc(p?.color)}"></div>
                    <div class="form-group"><label>Peso Estimado (g)</label><input type="number" step="0.1" name="estimated_weight_g" value="${p?.estimated_weight_g ?? 100}"></div>
                    <div class="form-group"><label>Tempo Estimado (min)</label><input type="number" name="estimated_time_min" value="${p?.estimated_time_min ?? 180}"></div>
                    <div class="form-group"><label>Custo Fabricação (R$)</label><input type="number" step="0.01" name="manufacturing_cost" value="${p?.manufacturing_cost ?? 0}"></div>
                    <div class="form-group"><label>Preço de Venda (R$) *</label><input type="number" step="0.01" name="sale_price" required value="${p?.sale_price ?? 0}"></div>
                    <div class="form-group"><label>Qtd em Estoque</label><input type="number" name="stock_qty" value="${p?.stock_qty ?? 0}"></div>
                    <div class="form-group"><label>Prazo Médio (dias)</label><input type="number" name="avg_production_days" value="${p?.avg_production_days ?? 2}"></div>
                    <div class="form-group"><label>Arquivo STL</label><input name="stl_file" placeholder="modelo.stl" value="${esc(p?.stl_file)}"></div>
                    <div class="form-group"><label>Arquivo 3MF</label><input name="threemf_file" placeholder="projeto.3mf" value="${esc(p?.threemf_file)}"></div>
                    <div class="form-group"><label>Matéria-prima (Estoque)</label>
                        <select name="inventory_item_id"><option value="">-- Nenhum --</option>${invItems.map(i => `<option value="${i.id}" ${p?.inventory_item_id==i.id?'selected':''}>${esc(i.name)}</option>`).join('')}</select>
                    </div>
                </div>
                <div class="form-group"><label>Descrição</label><textarea name="description" rows="2">${esc(p?.description)}</textarea></div>
                <button type="submit" class="btn btn-primary btn-block">Salvar Produto</button>
            </form>
        `);
        document.getElementById('prod-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const data = Object.fromEntries(new FormData(e.target));
            try {
                const res = p ? await API.put(`products/${p.id}`, data) : await API.post('products', data);
                closeModal();
                showToast(res.message);
                this.load();
            } catch (err) { showToast(err.message, 'error'); }
        });
    },

    async remove(id) {
        if (!confirm('Excluir este produto?')) return;
        await API.del(`products/${id}`);
        showToast('Produto excluído.');
        this.load();
    }
};

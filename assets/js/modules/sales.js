/**
 * Módulo 2: Gestão de Vendas e Orçamentos
 */
const SalesModule = {
    filterType: '',
    filterStatus: '',

    async render() {
        const main = document.getElementById('main-content');
        main.innerHTML = `<div class="page-header">
            <div><h2>Vendas & Orçamentos</h2><p>Pedidos, orçamentos em PDF, pagamentos parciais e conversão para produção</p></div>
            <div class="actions-bar">
                <select onchange="SalesModule.filterType=this.value; SalesModule.load()">
                    <option value="">Todos os tipos</option>
                    <option value="venda" ${this.filterType==='venda'?'selected':''}>Vendas / Pedidos</option>
                    <option value="orcamento" ${this.filterType==='orcamento'?'selected':''}>Orçamentos</option>
                </select>
                <select onchange="SalesModule.filterStatus=this.value; SalesModule.load()">
                    <option value="">Todos pagamentos</option>
                    <option value="pendente">Pendente</option>
                    <option value="parcial">Parcial</option>
                    <option value="pago">Pago</option>
                    <option value="atrasado">Atrasado</option>
                </select>
                <button class="btn btn-secondary" onclick="SalesModule.openForm('orcamento')"><i data-lucide="file-text"></i> Novo Orçamento</button>
                <button class="btn btn-primary" onclick="SalesModule.openForm('venda')"><i data-lucide="plus"></i> Nova Venda</button>
            </div>
        </div>
        <div class="card"><div class="table-wrap" id="sales-table">Carregando...</div></div>`;
        if (window.lucide) lucide.createIcons();
        this.load();
    },

    async load() {
        try {
            let q = 'sales';
            const params = [];
            if (this.filterType) params.push(`sale_type=${this.filterType}`);
            if (this.filterStatus) params.push(`payment_status=${this.filterStatus}`);
            if (params.length) q += '&' + params.join('&');

            const res = await API.get(q);
            const rows = res.sales || [];
            const badgeMap = { pago: 'success', parcial: 'info', pendente: 'warning', atrasado: 'danger' };

            document.getElementById('sales-table').innerHTML = rows.length ? `<table>
                <thead><tr><th>Código</th><th>Tipo</th><th>Cliente</th><th>Data</th><th>Total</th><th>Pago / Saldo</th><th>Pagamento</th><th>Ações</th></tr></thead>
                <tbody>${rows.map(s => {
                    const saldo = Math.max(0, Number(s.total_amount) - Number(s.paid_amount));
                    return `<tr>
                        <td class="mono"><strong>${esc(s.code)}</strong></td>
                        <td><span class="badge ${s.sale_type==='venda'?'badge-info':'badge-neutral'}">${s.sale_type==='venda'?'Pedido':'Orçamento'}</span></td>
                        <td>${esc(s.customer_name || '—')}</td>
                        <td>${fmtDate(s.sale_date)}</td>
                        <td><strong>${fmtBRL(s.total_amount)}</strong></td>
                        <td>${fmtBRL(s.paid_amount)} <small style="color:var(--text-muted)">/ falta ${fmtBRL(saldo)}</small></td>
                        <td><span class="badge badge-${badgeMap[s.payment_status]||'neutral'}">${esc(s.payment_status)}</span> <small>${esc(s.payment_method)}</small></td>
                        <td class="actions-bar">
                            <button class="btn btn-sm btn-secondary" onclick="SalesModule.viewDetails(${s.id})" title="Detalhes / PDF"><i data-lucide="eye"></i></button>
                            ${s.sale_type==='orcamento' ? `<button class="btn btn-sm btn-success" onclick="SalesModule.convert(${s.id})" title="Aprovar e converter em pedido"><i data-lucide="check"></i> Converter</button>` : ''}
                            ${s.sale_type==='venda' && saldo > 0.01 ? `<button class="btn btn-sm btn-primary" onclick="SalesModule.openPaymentModal(${s.id}, ${saldo})" title="Registrar pagamento"><i data-lucide="dollar-sign"></i> Pagar</button>` : ''}
                            <button class="btn btn-sm btn-danger" onclick="SalesModule.remove(${s.id})"><i data-lucide="trash-2"></i></button>
                        </td>
                    </tr>`;
                }).join('')}</tbody>
            </table>` : '<p>Nenhuma venda ou orçamento encontrado.</p>';
            if (window.lucide) lucide.createIcons();
        } catch (err) { showToast(err.message, 'error'); }
    },

    async openForm(saleType = 'venda') {
        const [custRes, prodRes] = await Promise.all([API.get('customers'), API.get('products')]);
        const customers = custRes.customers || [];
        const products = prodRes.products || [];
        if (!customers.length) return showToast('Cadastre ao menos um cliente antes de criar uma venda.', 'error');

        window._saleProducts = products;
        openModal(saleType === 'orcamento' ? 'Novo Orçamento' : 'Nova Venda / Pedido', `
            <form id="sale-form">
                <input type="hidden" name="sale_type" value="${saleType}">
                <div class="form-grid">
                    <div class="form-group"><label>Cliente *</label>
                        <select name="customer_id" required>${customers.map(c => `<option value="${c.id}">${esc(c.name)}</option>`).join('')}</select>
                    </div>
                    <div class="form-group"><label>Forma de Pagamento</label>
                        <select name="payment_method">
                            <option>PIX</option><option>Dinheiro</option><option>Cartão de crédito</option>
                            <option>Cartão de débito</option><option>Transferência</option><option>Outros</option>
                        </select>
                    </div>
                    <div class="form-group"><label>Data da Venda</label><input type="date" name="sale_date" value="${new Date().toISOString().slice(0,10)}"></div>
                    <div class="form-group"><label>Vencimento / Prazo</label><input type="date" name="due_date" value="${new Date(Date.now()+5*86400000).toISOString().slice(0,10)}"></div>
                </div>

                <div style="margin:10px 0; display:flex; justify-content:space-between; align-items:center">
                    <strong>Itens do Pedido</strong>
                    <button type="button" class="btn btn-sm btn-secondary" onclick="SalesModule.addItemRow()">+ Adicionar Item</button>
                </div>
                <div id="sale-items-container"></div>

                <div class="form-grid" style="margin-top:12px">
                    <div class="form-group"><label>Desconto (R$)</label><input type="number" step="0.01" min="0" name="discount_value" id="sale-discount" value="0" oninput="SalesModule.recalcTotal()"></div>
                    <div class="form-group"><label>Sinal / Entrada Paga (R$)</label><input type="number" step="0.01" min="0" name="down_payment" value="0"></div>
                    <div class="form-group"><label>Parcelas</label><input type="number" min="1" name="installments" value="1"></div>
                </div>
                <div class="form-group"><label>Observações</label><textarea name="notes" rows="2"></textarea></div>
                <div style="display:flex; justify-content:space-between; align-items:center; padding:12px; background:var(--bg-subtle); border-radius:8px; margin-bottom:14px">
                    <span>Total Calculado:</span>
                    <strong id="sale-total-preview" style="font-size:18px; color:var(--primary)">R$ 0,00</strong>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Salvar ${saleType==='orcamento'?'Orçamento':'Venda e Enviar para Produção'}</button>
            </form>
        `);
        this.addItemRow();

        document.getElementById('sale-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const fd = new FormData(e.target);
            const items = Array.from(document.querySelectorAll('.sale-item-row')).map(row => ({
                product_id: row.querySelector('.si-prod').value || null,
                description: row.querySelector('.si-desc').value,
                quantity: Number(row.querySelector('.si-qty').value),
                unit_price: Number(row.querySelector('.si-price').value),
            }));
            try {
                const res = await API.post('sales', {
                    sale_type: fd.get('sale_type'),
                    customer_id: fd.get('customer_id'),
                    payment_method: fd.get('payment_method'),
                    sale_date: fd.get('sale_date'),
                    due_date: fd.get('due_date'),
                    discount_value: fd.get('discount_value'),
                    down_payment: fd.get('down_payment'),
                    installments: fd.get('installments'),
                    notes: fd.get('notes'),
                    items
                });
                closeModal();
                showToast(res.message);
                this.load();
            } catch (err) { showToast(err.message, 'error'); }
        });
    },

    addItemRow() {
        const container = document.getElementById('sale-items-container');
        const prods = window._saleProducts || [];
        const div = document.createElement('div');
        div.className = 'sale-item-row form-grid';
        div.style.cssText = 'border:1px solid var(--border); padding:10px; border-radius:8px; margin-bottom:8px; align-items:end;';
        div.innerHTML = `
            <div class="form-group" style="margin:0"><label>Produto do Catálogo</label>
                <select class="si-prod" onchange="SalesModule.onPickProduct(this)">
                    <option value="">-- Item personalizado --</option>
                    ${prods.map(p => `<option value="${p.id}" data-price="${p.sale_price}" data-name="${esc(p.name)}">${esc(p.name)} (${fmtBRL(p.sale_price)})</option>`).join('')}
                </select>
            </div>
            <div class="form-group" style="margin:0"><label>Descrição *</label><input type="text" class="si-desc" required placeholder="Nome da peça"></div>
            <div class="form-group" style="margin:0"><label>Qtd</label><input type="number" class="si-qty" min="1" value="1" oninput="SalesModule.recalcTotal()"></div>
            <div class="form-group" style="margin:0"><label>Unitário (R$)</label><input type="number" step="0.01" min="0" class="si-price" value="0" oninput="SalesModule.recalcTotal()"></div>
        `;
        container.appendChild(div);
    },

    onPickProduct(sel) {
        const opt = sel.selectedOptions[0];
        const row = sel.closest('.sale-item-row');
        if (opt && opt.value) {
            row.querySelector('.si-desc').value = opt.dataset.name;
            row.querySelector('.si-price').value = opt.dataset.price;
            this.recalcTotal();
        }
    },

    recalcTotal() {
        let sub = 0;
        document.querySelectorAll('.sale-item-row').forEach(r => {
            sub += Number(r.querySelector('.si-qty').value || 0) * Number(r.querySelector('.si-price').value || 0);
        });
        const disc = Number(document.getElementById('sale-discount')?.value || 0);
        document.getElementById('sale-total-preview').textContent = fmtBRL(Math.max(0, sub - disc));
    },

    async viewDetails(id) {
        const res = await API.get(`sales/${id}`);
        const s = res.sale;
        openModal(`${s.sale_type==='orcamento'?'Orçamento':'Pedido'} ${s.code}`, `
            <p><strong>Cliente:</strong> ${esc(s.customer_name)} | <strong>Data:</strong> ${fmtDate(s.sale_date)}</p>
            <p><strong>Total:</strong> ${fmtBRL(s.total_amount)} | <strong>Pago:</strong> ${fmtBRL(s.paid_amount)} | <strong>Status:</strong> ${esc(s.payment_status)}</p>
            <hr style="margin:12px 0; border-color:var(--border)">
            <table><thead><tr><th>Item</th><th>Qtd</th><th>Unit.</th><th>Total</th></tr></thead>
            <tbody>${(s.items||[]).map(i => `<tr><td>${esc(i.description)}</td><td>${i.quantity}</td><td>${fmtBRL(i.unit_price)}</td><td>${fmtBRL(i.total_price)}</td></tr>`).join('')}</tbody></table>
            <div style="margin-top:16px; display:flex; gap:8px; justify-content:flex-end">
                <button class="btn btn-primary" onclick='Exporter.salePDF(${JSON.stringify(s).replace(/'/g,"&#39;")})'><i data-lucide="file-down"></i> Gerar PDF / Comprovante</button>
            </div>
        `);
    },

    async convert(id) {
        if (!confirm('Converter este orçamento em venda aprovada e gerar ordem de produção?')) return;
        try {
            const res = await API.post(`sales/convert/${id}`, {});
            showToast(res.message);
            this.load();
        } catch (err) { showToast(err.message, 'error'); }
    },

    openPaymentModal(id, saldo) {
        openModal('Registrar Pagamento', `
            <form id="pay-form">
                <p style="margin-bottom:12px">Saldo pendente: <strong>${fmtBRL(saldo)}</strong></p>
                <div class="form-group"><label>Valor Recebido (R$)</label><input type="number" step="0.01" max="${saldo}" min="0.01" name="amount" value="${saldo}" required></div>
                <div class="form-group"><label>Forma de Pagamento</label><select name="payment_method"><option>PIX</option><option>Dinheiro</option><option>Cartão de crédito</option><option>Cartão de débito</option><option>Transferência</option></select></div>
                <div class="form-group"><label>Data</label><input type="date" name="payment_date" value="${new Date().toISOString().slice(0,10)}"></div>
                <div class="form-group"><label>Observação</label><input type="text" name="notes" placeholder="Ex: Parcela restante"></div>
                <button type="submit" class="btn btn-primary btn-block">Confirmar Pagamento</button>
            </form>
        `);
        document.getElementById('pay-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const fd = new FormData(e.target);
            try {
                const res = await API.post(`sales/payment/${id}`, Object.fromEntries(fd));
                closeModal();
                showToast(res.message);
                this.load();
            } catch (err) { showToast(err.message, 'error'); }
        });
    },

    async remove(id) {
        if (!confirm('Excluir este registro?')) return;
        try {
            await API.del(`sales/${id}`);
            showToast('Excluído com sucesso.');
            this.load();
        } catch (err) { showToast(err.message, 'error'); }
    }
};

/**
 * Módulo 7: Gerenciamento de Produção (Kanban de 12 etapas com Drag & Drop e indicador de atrasos)
 */
const ProductionModule = {
    async render() {
        const main = document.getElementById('main-content');
        main.innerHTML = `<div class="page-header">
            <div><h2>Produção (Kanban)</h2><p>Acompanhe pedidos da aprovação à entrega com baixa automática de estoque</p></div>
            <div class="actions-bar">
                <input type="text" placeholder="Filtrar OP, cliente..." oninput="ProductionModule.load(this.value)">
                <button class="btn btn-primary" onclick="ProductionModule.openForm()"><i data-lucide="plus"></i> Nova Ordem</button>
            </div>
        </div>
        <div id="kanban-container" class="kanban-board">Carregando...</div>`;
        if (window.lucide) lucide.createIcons();
        this.load();
    },

    async load(q = '') {
        const res = await API.get(`production${q ? '&q=' + encodeURIComponent(q) : ''}`);
        const stages = res.stages || {};
        const orders = res.orders || [];

        const board = document.getElementById('kanban-container');
        board.innerHTML = Object.entries(stages).map(([key, label]) => {
            const colOrders = orders.filter(o => o.stage === key);
            return `<div class="kanban-col" ondragover="event.preventDefault()" ondrop="ProductionModule.onDrop(event, '${key}')">
                <div class="kanban-col-header">
                    <span>${esc(label)}</span>
                    <span class="badge badge-neutral">${colOrders.length}</span>
                </div>
                <div class="kanban-cards">
                    ${colOrders.map(o => `
                        <div class="kanban-card ${o.is_late ? 'late' : (o.priority==='urgente' ? 'urgent' : '')}" draggable="true" ondragstart="event.dataTransfer.setData('text/plain', ${o.id})">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px">
                                <span class="mono" style="font-size:11px; font-weight:700">${esc(o.order_number)}</span>
                                ${o.is_late ? '<span class="badge badge-danger">ATRASADO</span>' : (o.is_due_soon ? '<span class="badge badge-warning">Prazo próx.</span>' : `<span class="badge badge-neutral">${esc(o.priority)}</span>`)}
                            </div>
                            <strong style="font-size:13px">${esc(o.title)}</strong>
                            <div style="font-size:12px; color:var(--text-secondary); margin:4px 0">${esc(o.customer_name || 'Sem cliente')} • ${o.total_qty} pçs</div>
                            <div style="font-size:11px; color:var(--text-muted)">
                                Prazo: ${fmtDate(o.due_date)} | ${esc(o.printer_name || 'Sem impressora')}<br>
                                Est: ${o.estimated_hours}h (${fmtBRL(o.estimated_cost)})
                            </div>
                            <div style="display:flex; gap:4px; margin-top:8px">
                                <select style="flex:1; padding:4px; font-size:11px" onchange="ProductionModule.move(${o.id}, this.value)">
                                    ${Object.entries(stages).map(([sk, sl]) => `<option value="${sk}" ${sk===o.stage?'selected':''}>${esc(sl)}</option>`).join('')}
                                </select>
                                <button class="btn btn-sm btn-secondary" onclick="ProductionModule.openDetails(${o.id})" title="Detalhes / Histórico"><i data-lucide="eye"></i></button>
                            </div>
                        </div>
                    `).join('')}
                </div>
            </div>`;
        }).join('');
        if (window.lucide) lucide.createIcons();
    },

    onDrop(event, stageKey) {
        event.preventDefault();
        const orderId = event.dataTransfer.getData('text/plain');
        if (orderId) this.move(Number(orderId), stageKey);
    },

    async move(id, stage) {
        try {
            const res = await API.post(`production/move/${id}`, { stage });
            showToast(res.message);
            this.load();
            App.loadNotifications();
        } catch (err) { showToast(err.message, 'error'); }
    },

    async openForm() {
        const [custRes, prnRes, invRes] = await Promise.all([API.get('customers'), API.get('printers'), API.get('inventory')]);
        openModal('Nova Ordem de Produção', `
            <form id="op-form">
                <div class="form-grid">
                    <div class="form-group"><label>Título *</label><input name="title" required placeholder="Ex: Lote 10x Suportes"></div>
                    <div class="form-group"><label>Cliente</label><select name="customer_id"><option value="">-- Interno --</option>${(custRes.customers||[]).map(c=>`<option value="${c.id}">${esc(c.name)}</option>`).join('')}</select></div>
                    <div class="form-group"><label>Produtos / Resumo</label><input name="products_summary"></div>
                    <div class="form-group"><label>Quantidade Total</label><input type="number" name="total_qty" value="1" min="1"></div>
                    <div class="form-group"><label>Impressora</label><select name="printer_id"><option value="">-- Definir depois --</option>${(prnRes.printers||[]).map(p=>`<option value="${p.id}">${esc(p.name)}</option>`).join('')}</select></div>
                    <div class="form-group"><label>Matéria-Prima (Estoque)</label><select name="inventory_item_id"><option value="">-- Nenhuma --</option>${(invRes.items||[]).map(i=>`<option value="${i.id}">${esc(i.name)} (${i.quantity}${i.unit})</option>`).join('')}</select></div>
                    <div class="form-group"><label>Peso Estimado (g)</label><input type="number" step="0.1" name="estimated_weight_g" value="100"></div>
                    <div class="form-group"><label>Tempo Estimado (h)</label><input type="number" step="0.1" name="estimated_hours" value="4"></div>
                    <div class="form-group"><label>Custo Previsto (R$)</label><input type="number" step="0.01" name="estimated_cost" value="30"></div>
                    <div class="form-group"><label>Prioridade</label><select name="priority"><option value="baixa">Baixa</option><option value="normal" selected>Normal</option><option value="alta">Alta</option><option value="urgente">Urgente</option></select></div>
                    <div class="form-group"><label>Prazo Entrega *</label><input type="date" name="due_date" required value="${new Date(Date.now()+3*86400000).toISOString().slice(0,10)}"></div>
                    <div class="form-group"><label>Responsável</label><input name="responsible_name" value="${esc(App.user?.name)}"></div>
                </div>
                <div class="form-group"><label>Observações</label><textarea name="notes" rows="2"></textarea></div>
                <button type="submit" class="btn btn-primary btn-block">Criar Ordem</button>
            </form>
        `);
        document.getElementById('op-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const res = await API.post('production', Object.fromEntries(new FormData(e.target)));
            closeModal();
            showToast(res.message);
            this.load();
        });
    },

    async openDetails(id) {
        const res = await API.get(`production/${id}`);
        const o = res.order;
        openModal(`${o.order_number} — ${o.title}`, `
            <p><strong>Cliente:</strong> ${esc(o.customer_name || '—')} | <strong>Impressora:</strong> ${esc(o.printer_name || '—')}</p>
            <p><strong>Resumo:</strong> ${esc(o.products_summary)} | <strong>Prazo:</strong> ${fmtDate(o.due_date)}</p>
            <form id="op-upd-form" class="form-grid" style="margin:12px 0">
                <div class="form-group"><label>Horas Efetivas</label><input type="number" step="0.1" name="actual_hours" value="${o.actual_hours}"></div>
                <div class="form-group"><label>Custo Real (R$)</label><input type="number" step="0.01" name="actual_cost" value="${o.actual_cost}"></div>
                <div class="form-group"><label>Nova Etapa / Obs</label><input name="notes" placeholder="Atualizar apontamento"></div>
                <div style="display:flex; align-items:end; margin-bottom:12px"><button class="btn btn-primary btn-sm">Salvar Apontamento</button></div>
            </form>
            <h4>Histórico de Movimentações</h4>
            <div>${(res.history||[]).map(h => `<div style="padding:6px 0; border-bottom:1px solid var(--border)"><small>${fmtDateTime(h.created_at)} • ${esc(h.user_name)}</small><div>${h.from_stage ? esc(h.from_stage)+' → ' : ''}<strong>${esc(h.to_stage)}</strong> ${h.notes?'— '+esc(h.notes):''}</div></div>`).join('')}</div>
        `);
        document.getElementById('op-upd-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const fd = Object.fromEntries(new FormData(e.target));
            await API.post(`production/move/${id}`, { stage: o.stage, actual_hours: fd.actual_hours, actual_cost: fd.actual_cost, notes: fd.notes || 'Apontamento de horas/custo' });
            showToast('Apontamento salvo!');
            this.openDetails(id);
            this.load();
        });
    }
};

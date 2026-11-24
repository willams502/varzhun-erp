/**
 * Módulo Master Admin: Controle de Acessos e Cobranças dos Clientes Varzhun (SaaS)
 */
const SaasAdminModule = {
    activeTab: 'tenants',
    data: null,

    async render() {
        const main = document.getElementById('main-content');
        main.innerHTML = `<div class="page-header">
            <div><h2>Clientes SaaS & Cobranças</h2><p>Controle de acessos, planos, usuários e mensalidades dos clientes que usam o Varzhun</p></div>
            <div class="actions-bar">
                <button class="btn btn-secondary" onclick="SaasAdminModule.generateBatch()"><i data-lucide="calendar-check"></i> Gerar Mensalidades do Mês</button>
                <button class="btn btn-secondary" onclick="SaasAdminModule.openInvoiceModal()"><i data-lucide="receipt"></i> Nova Cobrança</button>
                <button class="btn btn-primary" onclick="SaasAdminModule.openTenantModal()"><i data-lucide="building-2"></i> Novo Cliente SaaS</button>
            </div>
        </div>
        <div id="saas-summary" class="kpi-grid"></div>
        <div class="tabs">
            <button class="tab-btn ${this.activeTab==='tenants'?'active':''}" onclick="SaasAdminModule.activeTab='tenants'; SaasAdminModule.renderTab()">Empresas Clientes & Acessos</button>
            <button class="tab-btn ${this.activeTab==='users'?'active':''}" onclick="SaasAdminModule.activeTab='users'; SaasAdminModule.renderTab()">Todos os Usuários</button>
            <button class="tab-btn ${this.activeTab==='invoices'?'active':''}" onclick="SaasAdminModule.activeTab='invoices'; SaasAdminModule.renderTab()">Cobranças & Mensalidades</button>
        </div>
        <div class="card"><div class="table-wrap" id="saas-tab-content">Carregando...</div></div>`;
        if (window.lucide) lucide.createIcons();
        this.load();
    },

    async load() {
        try {
            this.data = await API.get('saas_admin');
            const s = this.data.summary || {};
            document.getElementById('saas-summary').innerHTML = `
                <div class="kpi-card"><div class="kpi-label">MRR (Recorrente)</div><div class="kpi-value" style="color:var(--primary)">${fmtBRL(s.mrr)}</div></div>
                <div class="kpi-card"><div class="kpi-label">Recebido no Mês</div><div class="kpi-value" style="color:var(--success)">${fmtBRL(s.received_month)}</div></div>
                <div class="kpi-card"><div class="kpi-label">A Receber</div><div class="kpi-value" style="color:var(--info)">${fmtBRL(s.pending_total)}</div></div>
                <div class="kpi-card"><div class="kpi-label">Inadimplência</div><div class="kpi-value" style="color:var(--danger)">${fmtBRL(s.overdue_total)}</div></div>
                <div class="kpi-card"><div class="kpi-label">Clientes Ativos</div><div class="kpi-value">${s.active_tenants}</div></div>
                <div class="kpi-card"><div class="kpi-label">Suspensos / Bloqueados</div><div class="kpi-value" style="color:var(--warning)">${s.blocked_tenants}</div></div>
            `;
            this.renderTab();
        } catch (err) {
            document.getElementById('saas-tab-content').innerHTML = `<p style="color:var(--danger)">${esc(err.message)}</p>`;
        }
    },

    renderTab() {
        document.querySelectorAll('.tabs .tab-btn').forEach((b, idx) => {
            b.classList.toggle('active',
                (idx === 0 && this.activeTab === 'tenants') ||
                (idx === 1 && this.activeTab === 'users') ||
                (idx === 2 && this.activeTab === 'invoices')
            );
        });
        if (!this.data) return;

        if (this.activeTab === 'tenants') {
            const rows = this.data.tenants || [];
            const statusMap = { ativo: 'success', trial: 'info', suspenso: 'warning', bloqueado: 'danger', cancelado: 'neutral' };
            document.getElementById('saas-tab-content').innerHTML = rows.length ? `<table>
                <thead><tr><th>Empresa / Responsável</th><th>Plano / Valor</th><th>Status Acesso</th><th>Expira em</th><th>Usuários</th><th>Em Aberto</th><th>Ações</th></tr></thead>
                <tbody>${rows.map(t => `<tr>
                    <td><strong>${esc(t.company_name)}</strong><br><small style="color:var(--text-muted)">${esc(t.owner_name)} • ${esc(t.whatsapp || t.email)}</small></td>
                    <td><strong>${esc(t.plan_name)}</strong><br><small>${fmtBRL(t.monthly_price)}/mês (dia ${t.billing_day})</small></td>
                    <td><span class="badge badge-${statusMap[t.access_status]||'neutral'}">${esc(t.access_status.toUpperCase())}</span></td>
                    <td>${fmtDate(t.expires_at)}</td>
                    <td>${t.users_count} / ${t.max_users}</td>
                    <td style="color:${Number(t.pending_amount)>0?'var(--danger)':'var(--success)'}"><strong>${fmtBRL(t.pending_amount)}</strong></td>
                    <td class="actions-bar">
                        ${t.id > 1 ? (['ativo','trial'].includes(t.access_status)
                            ? `<button class="btn btn-sm btn-danger" onclick="SaasAdminModule.setStatus(${t.id}, 'suspenso')" title="Suspender acesso"><i data-lucide="lock"></i> Suspender</button>`
                            : `<button class="btn btn-sm btn-success" onclick="SaasAdminModule.setStatus(${t.id}, 'ativo')" title="Liberar acesso"><i data-lucide="unlock"></i> Ativar</button>`
                        ) : ''}
                        <button class="btn btn-sm btn-secondary" onclick="SaasAdminModule.openUsersModal(${t.id}, '${esc(t.company_name)}')" title="Gerenciar usuários"><i data-lucide="users"></i></button>
                        <button class="btn btn-sm btn-secondary" onclick='SaasAdminModule.openTenantModal(${JSON.stringify(t).replace(/'/g,"&#39;")})' title="Editar"><i data-lucide="edit"></i></button>
                        ${t.id > 1 ? `<button class="btn btn-sm btn-danger" onclick="SaasAdminModule.removeTenant(${t.id})"><i data-lucide="trash-2"></i></button>` : ''}
                    </td>
                </tr>`).join('')}</tbody>
            </table>` : '<p>Nenhuma empresa cliente cadastrada.</p>';
        } else if (this.activeTab === 'users') {
            const users = this.data.users || [];
            const tenants = this.data.tenants || [];
            document.getElementById('saas-tab-content').innerHTML = `
                <div style="display:flex; flex-wrap:wrap; justify-content:space-between; gap:8px; margin-bottom:12px">
                    <div class="actions-bar">
                        <input type="text" id="saas-user-filter" placeholder="Buscar nome ou e-mail..." oninput="SaasAdminModule.filterUsers()">
                        <select id="saas-user-tenant" onchange="SaasAdminModule.filterUsers()">
                            <option value="">Todas as empresas</option>
                            ${tenants.map(t => `<option value="${t.id}">${esc(t.company_name)}</option>`).join('')}
                        </select>
                    </div>
                    <button class="btn btn-primary btn-sm" onclick="SaasAdminModule.openGlobalUserModal()"><i data-lucide="user-plus"></i> Novo Usuário</button>
                </div>
                <div id="saas-users-table">${this.buildUsersTable(users)}</div>
            `;
        } else {
            const invs = this.data.invoices || [];
            const invBadge = { pago: 'success', pendente: 'warning', atrasado: 'danger', cancelado: 'neutral' };
            const filterVal = this.invoiceFilter || '';
            const filteredInvs = filterVal ? invs.filter(i => i.status === filterVal) : invs;
            const today = new Date().toISOString().slice(0, 10);

            document.getElementById('saas-tab-content').innerHTML = `
                <div style="display:flex; gap:8px; margin-bottom:12px; flex-wrap:wrap">
                    <select onchange="SaasAdminModule.invoiceFilter=this.value; SaasAdminModule.renderTab()">
                        <option value="" ${filterVal===''?'selected':''}>Todas as mensalidades</option>
                        <option value="atrasado" ${filterVal==='atrasado'?'selected':''}>Em Atraso</option>
                        <option value="pendente" ${filterVal==='pendente'?'selected':''}>Pendentes</option>
                        <option value="pago" ${filterVal==='pago'?'selected':''}>Pagas</option>
                    </select>
                </div>
                ${filteredInvs.length ? `<table>
                <thead><tr><th>Empresa</th><th>Ref.</th><th>Descrição</th><th>Vencimento</th><th>Valor</th><th>Status</th><th>Ações</th></tr></thead>
                <tbody>${filteredInvs.map(i => {
                    const lateDays = (i.status === 'atrasado' && i.due_date < today)
                        ? Math.floor((new Date(today) - new Date(i.due_date)) / 86400000) : 0;
                    return `<tr>
                        <td><strong>${esc(i.company_name)}</strong></td>
                        <td class="mono">${esc(i.reference_month)}</td>
                        <td>${esc(i.description)}</td>
                        <td>${fmtDate(i.due_date)} ${lateDays > 0 ? `<br><small style="color:var(--danger)"><strong>${lateDays} dia(s) de atraso</strong></small>` : ''}</td>
                        <td><strong>${fmtBRL(i.amount)}</strong></td>
                        <td><span class="badge badge-${invBadge[i.status]||'neutral'}">${i.status==='atrasado'?'ATRASADA':esc(i.status.toUpperCase())}</span></td>
                        <td class="actions-bar">
                            ${i.status !== 'pago' ? `<button class="btn btn-sm btn-success" onclick="SaasAdminModule.payInvoice(${i.id})"><i data-lucide="check-circle"></i> Baixar (Pago)</button>` : `<small style="color:var(--text-muted)">Pago ${fmtDate(i.paid_date)}</small>`}
                            ${i.status === 'pendente' ? `<button class="btn btn-sm btn-warning" onclick="SaasAdminModule.setInvoiceStatus(${i.id}, 'atrasado')">Marcar Atrasada</button>` : ''}
                            ${i.status === 'atrasado' && i.tenant_id > 1 ? `<button class="btn btn-sm btn-danger" onclick="SaasAdminModule.setStatus(${i.tenant_id}, 'suspenso')" title="Suspender acesso da empresa por atraso"><i data-lucide="lock"></i> Suspender Acesso</button>` : ''}
                            <button class="btn btn-sm btn-danger" onclick="SaasAdminModule.removeInvoice(${i.id})"><i data-lucide="trash-2"></i></button>
                        </td>
                    </tr>`;
                }).join('')}</tbody>
            </table>` : '<p>Nenhuma mensalidade encontrada para este filtro.</p>'}`;
        }
        if (window.lucide) lucide.createIcons();
    },

    openTenantModal(t = null) {
        openModal(t ? 'Editar Empresa Cliente' : 'Novo Cliente SaaS (Varzhun)', `
            <form id="tenant-form">
                <div class="form-grid">
                    <div class="form-group"><label>Empresa *</label><input name="company_name" required value="${esc(t?.company_name)}"></div>
                    <div class="form-group"><label>Responsável</label><input name="owner_name" value="${esc(t?.owner_name)}"></div>
                    <div class="form-group"><label>CNPJ / CPF</label><input name="document" value="${esc(t?.document)}"></div>
                    <div class="form-group"><label>E-mail</label><input type="email" name="email" value="${esc(t?.email)}"></div>
                    <div class="form-group"><label>WhatsApp</label><input name="whatsapp" value="${esc(t?.whatsapp)}"></div>
                    <div class="form-group"><label>Plano</label><select name="plan_name">
                        <option ${t?.plan_name==='Starter'?'selected':''}>Starter</option>
                        <option ${(!t || t?.plan_name==='Profissional')?'selected':''}>Profissional</option>
                        <option ${t?.plan_name==='Enterprise'?'selected':''}>Enterprise</option>
                    </select></div>
                    <div class="form-group"><label>Mensalidade (R$)</label><input type="number" step="0.01" name="monthly_price" value="${t?.monthly_price ?? 149.90}"></div>
                    <div class="form-group"><label>Dia Vencimento</label><input type="number" min="1" max="28" name="billing_day" value="${t?.billing_day ?? 10}"></div>
                    <div class="form-group"><label>Status de Acesso</label><select name="access_status">
                        <option value="ativo" ${t?.access_status==='ativo'?'selected':''}>Ativo</option>
                        <option value="trial" ${t?.access_status==='trial'?'selected':''}>Em Teste (Trial)</option>
                        <option value="suspenso" ${t?.access_status==='suspenso'?'selected':''}>Suspenso</option>
                        <option value="bloqueado" ${t?.access_status==='bloqueado'?'selected':''}>Bloqueado</option>
                        <option value="cancelado" ${t?.access_status==='cancelado'?'selected':''}>Cancelado</option>
                    </select></div>
                    <div class="form-group"><label>Validade do Acesso</label><input type="date" name="expires_at" value="${esc(t?.expires_at || new Date(Date.now()+30*86400000).toISOString().slice(0,10))}"></div>
                    <div class="form-group"><label>Limite de Usuários</label><input type="number" min="1" name="max_users" value="${t?.max_users ?? 5}"></div>
                </div>
                <div class="form-group"><label>Observações</label><textarea name="notes" rows="2">${esc(t?.notes)}</textarea></div>
                <button type="submit" class="btn btn-primary btn-block">Salvar Empresa Cliente</button>
            </form>
        `);
        document.getElementById('tenant-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const data = Object.fromEntries(new FormData(e.target));
            try {
                const res = t ? await API.put(`saas_admin/tenant/${t.id}`, data) : await API.post('saas_admin/tenant', data);
                closeModal();
                showToast(res.message);
                this.load();
            } catch (err) { showToast(err.message, 'error'); }
        });
    },

    async setStatus(id, access_status) {
        try {
            const res = await API.post(`saas_admin/tenant_status/${id}`, { access_status });
            showToast(res.message);
            this.load();
        } catch (err) { showToast(err.message, 'error'); }
    },

    async openUsersModal(tenantId, companyName) {
        const res = await API.get(`saas_admin/tenant/${tenantId}`);
        openModal(`Usuários — ${companyName}`, `
            <form id="tuser-form" class="form-grid" style="margin-bottom:14px">
                <div class="form-group"><label>Nome *</label><input name="name" required></div>
                <div class="form-group"><label>E-mail *</label><input type="email" name="email" required></div>
                <div class="form-group"><label>Senha *</label><input type="password" name="password" required></div>
                <div class="form-group"><label>Perfil</label><select name="role"><option value="gerente">Gerente</option><option value="vendedor">Vendedor</option><option value="producao">Produção</option><option value="financeiro">Financeiro</option><option value="admin">Admin</option></select></div>
                <div style="grid-column:1/-1"><button class="btn btn-primary btn-sm">+ Adicionar Usuário à Empresa</button></div>
            </form>
            <table><thead><tr><th>Nome</th><th>E-mail</th><th>Perfil</th><th>Status</th><th></th></tr></thead>
            <tbody>${(res.users||[]).map(u => `<tr>
                <td>${esc(u.name)}</td><td>${esc(u.email)}</td><td>${esc(u.role)}</td>
                <td><span class="badge badge-${u.active?'success':'danger'}">${u.active?'Ativo':'Inativo'}</span></td>
                <td>${u.id>1 ? `<button class="btn btn-sm btn-secondary" onclick="API.post('saas_admin/user_toggle/${u.id}',{}).then(()=>SaasAdminModule.openUsersModal(${tenantId}, '${esc(companyName)}'))">Ativar/Desativar</button>` : ''}</td>
            </tr>`).join('')}</tbody></table>
        `);
        document.getElementById('tuser-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            try {
                const r = await API.post(`saas_admin/tenant_user/${tenantId}`, Object.fromEntries(new FormData(e.target)));
                showToast(r.message);
                this.openUsersModal(tenantId, companyName);
                this.load();
            } catch (err) { showToast(err.message, 'error'); }
        });
    },

    openInvoiceModal() {
        const tenants = (this.data?.tenants || []).filter(t => t.id > 1);
        if (!tenants.length) return showToast('Cadastre um cliente SaaS primeiro.', 'error');
        openModal('Nova Cobrança SaaS', `
            <form id="inv-saas-form">
                <div class="form-grid">
                    <div class="form-group"><label>Empresa Cliente *</label><select name="tenant_id">${tenants.map(t=>`<option value="${t.id}">${esc(t.company_name)} (${fmtBRL(t.monthly_price)})</option>`).join('')}</select></div>
                    <div class="form-group"><label>Valor (R$) *</label><input type="number" step="0.01" min="0.01" name="amount" required value="${tenants[0].monthly_price}"></div>
                    <div class="form-group"><label>Vencimento *</label><input type="date" name="due_date" required value="${new Date().toISOString().slice(0,10)}"></div>
                    <div class="form-group"><label>Forma</label><select name="payment_method"><option>PIX</option><option>Boleto</option><option>Cartão de crédito</option></select></div>
                </div>
                <div class="form-group"><label>Descrição</label><input name="description" value="Mensalidade Varzhun 3D ERP"></div>
                <button type="submit" class="btn btn-primary btn-block">Gerar Cobrança</button>
            </form>
        `);
        document.getElementById('inv-saas-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const res = await API.post('saas_admin/invoice', Object.fromEntries(new FormData(e.target)));
            closeModal();
            showToast(res.message);
            this.activeTab = 'invoices';
            this.load();
        });
    },

    async generateBatch() {
        const res = await API.post('saas_admin/invoice', { batch_all: true, reference_month: new Date().toISOString().slice(0, 7) });
        showToast(res.message);
        this.activeTab = 'invoices';
        this.load();
    },

    async payInvoice(id) {
        const res = await API.post(`saas_admin/invoice_pay/${id}`, {});
        showToast(res.message);
        this.load();
    },

    async removeTenant(id) {
        if (!confirm('Excluir esta empresa cliente, seus usuários e cobranças?')) return;
        await API.del(`saas_admin/tenant/${id}`);
        showToast('Empresa cliente removida.');
        this.load();
    },

    async removeInvoice(id) {
        if (!confirm('Excluir esta cobrança?')) return;
        await API.del(`saas_admin/invoice/${id}`);
        showToast('Cobrança excluída.');
        this.load();
    },

    buildUsersTable(users) {
        if (!users.length) return '<p>Nenhum usuário encontrado.</p>';
        return `<table>
            <thead><tr><th>Usuário</th><th>Empresa</th><th>Perfil</th><th>Status</th><th>Criado em</th><th>Ações</th></tr></thead>
            <tbody>${users.map(u => `<tr>
                <td><strong>${esc(u.name)}</strong><br><small style="color:var(--text-muted)">${esc(u.email)}</small></td>
                <td>${esc(u.company_name || 'Varzhun Matriz')} ${u.tenant_status && u.tenant_status!=='ativo' ? `<span class="badge badge-warning">${esc(u.tenant_status)}</span>`:''}</td>
                <td><span class="badge badge-info">${esc(u.role)}</span></td>
                <td><span class="badge badge-${u.active?'success':'danger'}">${u.active?'Ativo':'Não Ativo'}</span></td>
                <td>${fmtDate(u.created_at)}</td>
                <td class="actions-bar">
                    <button class="btn btn-sm btn-secondary" onclick='SaasAdminModule.openPasswordModal(${JSON.stringify(u).replace(/'/g,"&#39;")})' title="Alterar senha"><i data-lucide="key-round"></i> Senha</button>
                    <button class="btn btn-sm btn-secondary" onclick='SaasAdminModule.openGlobalUserModal(${JSON.stringify(u).replace(/'/g,"&#39;")})' title="Editar usuário"><i data-lucide="edit"></i></button>
                    ${u.id > 1 ? `<button class="btn btn-sm btn-${u.active?'warning':'success'}" onclick="SaasAdminModule.toggleUser(${u.id})">${u.active?'Desativar':'Ativar'}</button>` : ''}
                    ${u.id > 1 ? `<button class="btn btn-sm btn-danger" onclick="SaasAdminModule.removeUser(${u.id})"><i data-lucide="trash-2"></i></button>` : ''}
                </td>
            </tr>`).join('')}</tbody>
        </table>`;
    },

    filterUsers() {
        const q = (document.getElementById('saas-user-filter')?.value || '').toLowerCase();
        const tid = document.getElementById('saas-user-tenant')?.value || '';
        const filtered = (this.data?.users || []).filter(u => {
            const matchText = !q || u.name.toLowerCase().includes(q) || u.email.toLowerCase().includes(q);
            const matchTenant = !tid || String(u.tenant_id) === tid;
            return matchText && matchTenant;
        });
        document.getElementById('saas-users-table').innerHTML = this.buildUsersTable(filtered);
        if (window.lucide) lucide.createIcons();
    },

    openGlobalUserModal(u = null) {
        const tenants = this.data?.tenants || [];
        openModal(u ? `Editar Usuário — ${u.name}` : 'Novo Usuário no Varzhun', `
            <form id="global-user-form">
                <div class="form-grid">
                    <div class="form-group"><label>Empresa Vinculada *</label>
                        <select name="tenant_id">${tenants.map(t => `<option value="${t.id}" ${u?.tenant_id==t.id?'selected':''}>${esc(t.company_name)}</option>`).join('')}</select>
                    </div>
                    <div class="form-group"><label>Nome *</label><input name="name" required value="${esc(u?.name)}"></div>
                    <div class="form-group"><label>E-mail *</label><input type="email" name="email" required value="${esc(u?.email)}"></div>
                    <div class="form-group"><label>Perfil / Nível *</label>
                        <select name="role">
                            <option value="admin" ${u?.role==='admin'?'selected':''}>Administrador</option>
                            <option value="gerente" ${u?.role==='gerente'?'selected':''}>Gerente</option>
                            <option value="financeiro" ${u?.role==='financeiro'?'selected':''}>Financeiro</option>
                            <option value="producao" ${u?.role==='producao'?'selected':''}>Produção</option>
                            <option value="vendedor" ${(!u || u?.role==='vendedor')?'selected':''}>Vendedor</option>
                        </select>
                    </div>
                    <div class="form-group"><label>${u ? 'Nova Senha (deixe vazio para manter)' : 'Senha *'}</label><input type="password" name="password" ${u?'':'required'} placeholder="••••••••"></div>
                    ${u ? `<div class="form-group"><label>Status</label><select name="active"><option value="1" ${u.active?'selected':''}>Ativo</option><option value="0" ${!u.active?'selected':''}>Bloqueado</option></select></div>` : ''}
                </div>
                <button type="submit" class="btn btn-primary btn-block">Salvar Usuário</button>
            </form>
        `);
        document.getElementById('global-user-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const data = Object.fromEntries(new FormData(e.target));
            try {
                const res = u
                    ? await API.put(`saas_admin/user/${u.id}`, data)
                    : await API.post(`saas_admin/tenant_user/${data.tenant_id}`, data);
                closeModal();
                showToast(res.message);
                this.activeTab = 'users';
                this.load();
            } catch (err) { showToast(err.message, 'error'); }
        });
    },

    async toggleUser(id) {
        const res = await API.post(`saas_admin/user_toggle/${id}`, {});
        showToast(res.message);
        this.load();
    },

    async setInvoiceStatus(id, status) {
        const res = await API.post(`saas_admin/invoice_status/${id}`, { status });
        showToast(res.message);
        this.load();
    },

    openPasswordModal(u) {
        openModal(`Alterar Senha — ${u.name}`, `
            <form id="pwd-reset-form">
                <p style="margin-bottom:10px"><small>Usuário: <strong>${esc(u.email)}</strong></small></p>
                <div class="form-group"><label>Nova Senha *</label><input type="password" name="password" required minlength="4" placeholder="Digite a nova senha"></div>
                <button type="submit" class="btn btn-primary btn-block">Salvar Nova Senha</button>
            </form>
        `);
        document.getElementById('pwd-reset-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const password = new FormData(e.target).get('password');
            try {
                const res = await API.put(`saas_admin/user/${u.id}`, {
                    tenant_id: u.tenant_id,
                    name: u.name,
                    email: u.email,
                    role: u.role,
                    active: u.active,
                    password
                });
                closeModal();
                showToast('Senha atualizada com sucesso!');
                this.load();
            } catch (err) { showToast(err.message, 'error'); }
        });
    },

    async removeUser(id) {
        if (!confirm('Excluir este usuário permanentemente?')) return;
        try {
            const res = await API.del(`saas_admin/user/${id}`);
            showToast(res.message);
            this.load();
        } catch (err) { showToast(err.message, 'error'); }
    }
};

/**
 * Módulo Dedicado (Master Admin): Usuários Varzhun
 * Cadastrar, editar, alterar senha e bloquear/ativar acesso imediato ao sistema.
 */
const UsersVarzhunModule = {
    data: null,

    async render() {
        const main = document.getElementById('main-content');
        main.innerHTML = `<div class="page-header">
            <div><h2>Usuários Varzhun</h2><p>Cadastre, edite, altere senhas e bloqueie o acesso de usuários ao sistema</p></div>
            <div class="actions-bar">
                <button class="btn btn-primary" onclick="UsersVarzhunModule.openModal()"><i data-lucide="user-plus"></i> Novo Usuário</button>
            </div>
        </div>
        <div id="uv-summary" class="kpi-grid" style="grid-template-columns:repeat(auto-fit,minmax(180px,1fr))"></div>
        <div class="card">
            <div style="display:flex; flex-wrap:wrap; gap:8px; margin-bottom:12px">
                <input type="text" id="uv-search" placeholder="Buscar nome ou e-mail..." oninput="UsersVarzhunModule.renderTable()">
                <select id="uv-tenant" onchange="UsersVarzhunModule.renderTable()"><option value="">Todas as empresas</option></select>
                <select id="uv-status" onchange="UsersVarzhunModule.renderTable()">
                    <option value="">Todos os status</option>
                    <option value="1">Ativos</option>
                    <option value="0">Bloqueados (Não Ativos)</option>
                </select>
            </div>
            <div class="table-wrap" id="uv-table">Carregando usuários...</div>
        </div>`;
        if (window.lucide) lucide.createIcons();
        this.load();
    },

    async load() {
        try {
            this.data = await API.get('saas_admin');
            const users = this.data.users || [];
            const tenants = this.data.tenants || [];

            const ativos = users.filter(u => Number(u.active) === 1).length;
            const bloqueados = users.length - ativos;

            document.getElementById('uv-summary').innerHTML = `
                <div class="kpi-card"><div class="kpi-label">Total de Usuários</div><div class="kpi-value">${users.length}</div></div>
                <div class="kpi-card"><div class="kpi-label">Ativos (Com Acesso)</div><div class="kpi-value" style="color:var(--success)">${ativos}</div></div>
                <div class="kpi-card"><div class="kpi-label">Bloqueados (Sem Acesso)</div><div class="kpi-value" style="color:var(--danger)">${bloqueados}</div></div>
            `;

            const sel = document.getElementById('uv-tenant');
            if (sel) {
                sel.innerHTML = `<option value="">Todas as empresas</option>` + tenants.map(t => `<option value="${t.id}">${esc(t.company_name)}</option>`).join('');
            }
            this.renderTable();
        } catch (err) {
            document.getElementById('uv-table').innerHTML = `<p style="color:var(--danger)">${esc(err.message)}</p>`;
        }
    },

    renderTable() {
        const users = this.data?.users || [];
        const q = (document.getElementById('uv-search')?.value || '').toLowerCase();
        const tid = document.getElementById('uv-tenant')?.value || '';
        const st = document.getElementById('uv-status')?.value || '';

        const filtered = users.filter(u => {
            const mQ = !q || u.name.toLowerCase().includes(q) || u.email.toLowerCase().includes(q);
            const mT = !tid || String(u.tenant_id) === tid;
            const mS = st === '' || String(u.active) === st;
            return mQ && mT && mS;
        });

        document.getElementById('uv-table').innerHTML = filtered.length ? `<table>
            <thead><tr><th>Usuário</th><th>Empresa</th><th>Perfil</th><th>Status de Acesso</th><th>Criado em</th><th>Ações</th></tr></thead>
            <tbody>${filtered.map(u => `<tr>
                <td><strong>${esc(u.name)}</strong><br><small style="color:var(--text-muted)">${esc(u.email)}</small></td>
                <td>${esc(u.company_name || 'Varzhun Matriz')}</td>
                <td><span class="badge badge-info">${esc(u.role)}</span></td>
                <td><span class="badge badge-${Number(u.active)?'success':'danger'}">${Number(u.active)?'Ativo':'Bloqueado'}</span></td>
                <td>${fmtDate(u.created_at)}</td>
                <td class="actions-bar">
                    <button class="btn btn-sm btn-secondary" onclick='UsersVarzhunModule.openPasswordModal(${JSON.stringify(u).replace(/'/g,"&#39;")})' title="Alterar senha"><i data-lucide="key-round"></i> Senha</button>
                    <button class="btn btn-sm btn-secondary" onclick='UsersVarzhunModule.openModal(${JSON.stringify(u).replace(/'/g,"&#39;")})' title="Editar"><i data-lucide="edit"></i> Editar</button>
                    ${u.id > 1 ? `<button class="btn btn-sm btn-${Number(u.active)?'danger':'success'}" onclick="UsersVarzhunModule.toggle(${u.id})"><i data-lucide="${Number(u.active)?'lock':'unlock'}"></i> ${Number(u.active)?'Bloquear':'Desbloquear'}</button>` : ''}
                    ${u.id > 1 ? `<button class="btn btn-sm btn-danger" onclick="UsersVarzhunModule.remove(${u.id})" title="Excluir"><i data-lucide="trash-2"></i></button>` : ''}
                </td>
            </tr>`).join('')}</tbody>
        </table>` : '<p>Nenhum usuário encontrado.</p>';
        if (window.lucide) lucide.createIcons();
    },

    openModal(u = null) {
        const tenants = this.data?.tenants || [];
        openModal(u ? `Editar Usuário — ${u.name}` : 'Cadastrar Novo Usuário', `
            <form id="uv-form">
                <div class="form-grid">
                    <div class="form-group"><label>Empresa Vinculada *</label>
                        <select name="tenant_id">${tenants.map(t => `<option value="${t.id}" ${u?.tenant_id==t.id?'selected':''}>${esc(t.company_name)}</option>`).join('')}</select>
                    </div>
                    ${!u ? `<div class="form-group"><label>Ou Criar Nova Empresa (opcional)</label><input name="company_name" placeholder="Ex: MixForge 3D (separa dados)"></div>` : ''}
                    <div class="form-group"><label>Nome *</label><input name="name" required value="${esc(u?.name)}"></div>
                    <div class="form-group"><label>E-mail *</label><input type="email" name="email" required value="${esc(u?.email)}"></div>
                    <div class="form-group"><label>Perfil *</label>
                        <select name="role">
                            <option value="admin" ${u?.role==='admin'?'selected':''}>Administrador</option>
                            <option value="gerente" ${u?.role==='gerente'?'selected':''}>Gerente</option>
                            <option value="financeiro" ${u?.role==='financeiro'?'selected':''}>Financeiro</option>
                            <option value="producao" ${u?.role==='producao'?'selected':''}>Produção</option>
                            <option value="vendedor" ${(!u || u?.role==='vendedor')?'selected':''}>Vendedor</option>
                        </select>
                    </div>
                    <div class="form-group"><label>${u ? 'Nova Senha (vazio = manter)' : 'Senha *'}</label><input type="password" name="password" ${u?'':'required'} placeholder="••••••••"></div>
                    ${u ? `<div class="form-group"><label>Acesso ao Sistema</label><select name="active"><option value="1" ${Number(u.active)?'selected':''}>Ativo (Liberado)</option><option value="0" ${!Number(u.active)?'selected':''}>Bloqueado (Sem Acesso)</option></select></div>` : ''}
                </div>
                <button type="submit" class="btn btn-primary btn-block">Salvar Usuário</button>
            </form>
        `);
        document.getElementById('uv-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const data = Object.fromEntries(new FormData(e.target));
            try {
                const res = u
                    ? await API.put(`saas_admin/user/${u.id}`, data)
                    : await API.post(`saas_admin/tenant_user/${data.tenant_id}`, data);
                closeModal();
                showToast(res.message);
                this.load();
            } catch (err) { showToast(err.message, 'error'); }
        });
    },

    openPasswordModal(u) {
        openModal(`Alterar Senha — ${u.name}`, `
            <form id="uv-pwd-form">
                <p style="margin-bottom:10px"><small>Usuário: <strong>${esc(u.email)}</strong></small></p>
                <div class="form-group"><label>Nova Senha *</label><input type="password" name="password" required minlength="4" placeholder="Digite a nova senha"></div>
                <button type="submit" class="btn btn-primary btn-block">Atualizar Senha</button>
            </form>
        `);
        document.getElementById('uv-pwd-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const password = new FormData(e.target).get('password');
            await API.put(`saas_admin/user/${u.id}`, {
                tenant_id: u.tenant_id, name: u.name, email: u.email, role: u.role, active: u.active, password
            });
            closeModal();
            showToast('Senha atualizada!');
            this.load();
        });
    },

    async toggle(id) {
        const res = await API.post(`saas_admin/user_toggle/${id}`, {});
        showToast(res.message);
        this.load();
    },

    async remove(id) {
        if (!confirm('Excluir este usuário permanentemente?')) return;
        try {
            const res = await API.del(`saas_admin/user/${id}`);
            showToast(res.message);
            this.load();
        } catch (err) { showToast(err.message, 'error'); }
    }
};

/**
 * Módulo 6: Gerenciamento de Impressoras 3D
 */
const PrintersModule = {
    async render() {
        const main = document.getElementById('main-content');
        main.innerHTML = `<div class="page-header">
            <div><h2>Impressoras 3D</h2><p>Status operacional, horas trabalhadas, sucessos, falhas e manutenções</p></div>
            <button class="btn btn-primary" onclick="PrintersModule.openForm()"><i data-lucide="plus"></i> Nova Impressora</button>
        </div>
        <div id="printers-grid" class="kpi-grid" style="grid-template-columns:repeat(auto-fill,minmax(300px,1fr))">Carregando...</div>`;
        if (window.lucide) lucide.createIcons();
        this.load();
    },

    async load() {
        const res = await API.get('printers');
        const statusMap = {
            disponivel: ['Disponível', 'success'],
            imprimindo: ['Imprimindo', 'info'],
            manutencao: ['Em Manutenção', 'warning'],
            parada: ['Parada', 'danger'],
            reservada: ['Reservada', 'neutral']
        };
        document.getElementById('printers-grid').innerHTML = (res.printers || []).map(p => {
            const [lbl, clr] = statusMap[p.status] || [p.status, 'neutral'];
            return `<div class="card">
                <div class="card-header">
                    <div><strong>${esc(p.name)}</strong><br><small style="color:var(--text-muted)">${esc(p.brand)} ${esc(p.model)} • ${esc(p.identifier_code)}</small></div>
                    <span class="badge badge-${clr}">${lbl}</span>
                </div>
                <p style="font-size:12px; color:var(--text-secondary); margin-bottom:10px">
                    Tecnologia: <strong>${esc(p.technology)}</strong> | Volume: ${esc(p.build_volume||'—')}<br>
                    Custo/h: ${fmtBRL(p.cost_per_hour)} | Consumo: ${p.energy_kwh} kWh<br>
                    Horas totais: <strong>${p.total_hours}h</strong> | Impressões: ${p.total_prints} (${p.total_success} ok / ${p.total_fails} falhas)
                </p>
                <div class="actions-bar">
                    <button class="btn btn-sm btn-secondary" onclick="PrintersModule.openLogModal(${p.id}, '${esc(p.name)}')"><i data-lucide="clipboard-list"></i> Histórico / Manut.</button>
                    <button class="btn btn-sm btn-secondary" onclick='PrintersModule.openForm(${JSON.stringify(p).replace(/'/g,"&#39;")})'><i data-lucide="edit"></i></button>
                    <button class="btn btn-sm btn-danger" onclick="PrintersModule.remove(${p.id})"><i data-lucide="trash-2"></i></button>
                </div>
            </div>`;
        }).join('');
        if (window.lucide) lucide.createIcons();
    },

    openForm(p = null) {
        openModal(p ? 'Editar Impressora' : 'Nova Impressora 3D', `
            <form id="printer-form">
                <div class="form-grid">
                    <div class="form-group"><label>Nome *</label><input name="name" required value="${esc(p?.name)}"></div>
                    <div class="form-group"><label>Marca</label><input name="brand" value="${esc(p?.brand)}"></div>
                    <div class="form-group"><label>Modelo</label><input name="model" value="${esc(p?.model)}"></div>
                    <div class="form-group"><label>Código ID</label><input name="identifier_code" value="${esc(p?.identifier_code)}"></div>
                    <div class="form-group"><label>Volume (mm)</label><input name="build_volume" placeholder="256x256x256mm" value="${esc(p?.build_volume)}"></div>
                    <div class="form-group"><label>Tecnologia</label><select name="technology"><option ${p?.technology==='FDM'?'selected':''}>FDM</option><option ${p?.technology==='SLA (Resina)'?'selected':''}>SLA (Resina)</option><option ${p?.technology==='SLS'?'selected':''}>SLS</option></select></div>
                    <div class="form-group"><label>Status</label><select name="status">
                        <option value="disponivel" ${p?.status==='disponivel'?'selected':''}>Disponível</option>
                        <option value="imprimindo" ${p?.status==='imprimindo'?'selected':''}>Imprimindo</option>
                        <option value="manutencao" ${p?.status==='manutencao'?'selected':''}>Em manutenção</option>
                        <option value="parada" ${p?.status==='parada'?'selected':''}>Parada</option>
                        <option value="reservada" ${p?.status==='reservada'?'selected':''}>Reservada</option>
                    </select></div>
                    <div class="form-group"><label>Custo/Hora (R$)</label><input type="number" step="0.01" name="cost_per_hour" value="${p?.cost_per_hour ?? 2.50}"></div>
                    <div class="form-group"><label>Consumo (kWh)</label><input type="number" step="0.001" name="energy_kwh" value="${p?.energy_kwh ?? 0.15}"></div>
                    <div class="form-group"><label>Data Aquisição</label><input type="date" name="acquisition_date" value="${esc(p?.acquisition_date)}"></div>
                    <div class="form-group"><label>Valor Aquisição (R$)</label><input type="number" step="0.01" name="acquisition_value" value="${p?.acquisition_value ?? 0}"></div>
                    <div class="form-group"><label>Horas Totais</label><input type="number" step="0.1" name="total_hours" value="${p?.total_hours ?? 0}"></div>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Salvar Impressora</button>
            </form>
        `);
        document.getElementById('printer-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const data = Object.fromEntries(new FormData(e.target));
            const res = p ? await API.put(`printers/${p.id}`, data) : await API.post('printers', data);
            closeModal();
            showToast(res.message);
            this.load();
        });
    },

    async openLogModal(id, name) {
        const res = await API.get(`printers/${id}`);
        openModal(`Histórico — ${name}`, `
            <form id="plog-form" class="form-grid" style="margin-bottom:14px">
                <div class="form-group"><label>Tipo</label><select name="log_type"><option value="impressao">Impressão</option><option value="manutencao">Manutenção</option></select></div>
                <div class="form-group"><label>Horas</label><input type="number" step="0.1" name="hours_worked" value="0"></div>
                <div class="form-group"><label>Sucessos</label><input type="number" name="success_count" value="0"></div>
                <div class="form-group"><label>Falhas</label><input type="number" name="fail_count" value="0"></div>
                <div class="form-group"><label>Custo Manut. (R$)</label><input type="number" step="0.01" name="maintenance_cost" value="0"></div>
                <div class="form-group"><label>Data</label><input type="date" name="log_date" value="${new Date().toISOString().slice(0,10)}"></div>
                <div class="form-group" style="grid-column:1/-1"><label>Descrição</label><input name="description" required placeholder="Detalhes da impressão ou manutenção"></div>
                <button type="submit" class="btn btn-primary btn-sm">Registrar</button>
            </form>
            <table><thead><tr><th>Data</th><th>Tipo</th><th>Horas</th><th>Custo</th><th>Descrição</th></tr></thead>
            <tbody>${(res.logs||[]).map(l => `<tr><td>${fmtDate(l.log_date)}</td><td>${esc(l.log_type)}</td><td>${l.hours_worked}h</td><td>${fmtBRL(l.maintenance_cost)}</td><td>${esc(l.description)}</td></tr>`).join('')}</tbody></table>
        `);
        document.getElementById('plog-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const data = Object.fromEntries(new FormData(e.target));
            data.prints_count = Number(data.success_count || 0) + Number(data.fail_count || 0);
            await API.post(`printers/log/${id}`, data);
            showToast('Registro adicionado!');
            this.openLogModal(id, name);
            this.load();
        });
    },

    async remove(id) {
        if (!confirm('Excluir impressora?')) return;
        await API.del(`printers/${id}`);
        showToast('Impressora removida.');
        this.load();
    }
};

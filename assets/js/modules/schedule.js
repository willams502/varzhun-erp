/**
 * Módulo 8: Agenda e Planejamento de Produção (Diário, Semanal, Mensal + Conflitos)
 */
const ScheduleModule = {
    view: 'semana',

    async render() {
        const main = document.getElementById('main-content');
        main.innerHTML = `<div class="page-header">
            <div><h2>Agenda & Planejamento</h2><p>Alocação de impressoras, manutenções e previsão de capacidade</p></div>
            <div class="actions-bar">
                <select onchange="ScheduleModule.view=this.value; ScheduleModule.load()">
                    <option value="dia" ${this.view==='dia'?'selected':''}>Diário</option>
                    <option value="semana" ${this.view==='semana'?'selected':''}>Semanal</option>
                    <option value="mes" ${this.view==='mes'?'selected':''}>Mensal</option>
                </select>
                <button class="btn btn-primary" onclick="ScheduleModule.openForm()"><i data-lucide="calendar-plus"></i> Novo Agendamento</button>
            </div>
        </div>
        <div id="schedule-capacity" class="kpi-grid" style="grid-template-columns:repeat(auto-fit,minmax(200px,1fr))"></div>
        <div class="card"><div class="table-wrap" id="schedule-list">Carregando...</div></div>`;
        if (window.lucide) lucide.createIcons();
        this.load();
    },

    async load() {
        const today = new Date();
        const from = today.toISOString().slice(0, 10);
        const days = this.view === 'dia' ? 0 : (this.view === 'semana' ? 7 : 30);
        const to = new Date(today.getTime() + days * 86400000).toISOString().slice(0, 10);

        const res = await API.get(`schedule&from=${from}&to=${to}`);
        const cap = res.capacity || {};
        document.getElementById('schedule-capacity').innerHTML = `
            <div class="kpi-card"><div class="kpi-label">Impressoras Ativas</div><div class="kpi-value">${cap.active_printers || 0}</div></div>
            <div class="kpi-card"><div class="kpi-label">Capacidade Semanal</div><div class="kpi-value">${cap.weekly_capacity_hours || 0}h</div></div>
            <div class="kpi-card"><div class="kpi-label">Horas em Fila</div><div class="kpi-value" style="color:var(--primary)">${cap.pending_production_hours || 0}h</div></div>
            <div class="kpi-card"><div class="kpi-label">Ocupação Prevista</div><div class="kpi-value">${cap.utilization_forecast_percent || 0}%</div></div>
        `;

        const rows = res.schedules || [];
        document.getElementById('schedule-list').innerHTML = rows.length ? `<table>
            <thead><tr><th>Título</th><th>Tipo</th><th>Impressora</th><th>Início</th><th>Término</th><th>Status</th><th></th></tr></thead>
            <tbody>${rows.map(s => `<tr>
                <td><strong>${esc(s.title)}</strong><br><small>${esc(s.order_number || '')}</small></td>
                <td><span class="badge badge-${s.schedule_type==='manutencao'?'warning':'info'}">${esc(s.schedule_type)}</span></td>
                <td>${esc(s.printer_name || '—')}</td>
                <td>${fmtDateTime(s.start_datetime)}</td>
                <td>${fmtDateTime(s.end_datetime)}</td>
                <td>${esc(s.status)}</td>
                <td><button class="btn btn-sm btn-danger" onclick="API.del('schedule/${s.id}').then(()=>ScheduleModule.load())"><i data-lucide="trash-2"></i></button></td>
            </tr>`).join('')}</tbody>
        </table>` : '<p>Nenhum agendamento neste período.</p>';
        if (window.lucide) lucide.createIcons();
    },

    async openForm() {
        const [prnRes, opRes] = await Promise.all([API.get('printers'), API.get('production')]);
        const today = new Date().toISOString().slice(0, 10);
        openModal('Novo Agendamento', `
            <form id="sch-form">
                <div class="form-grid">
                    <div class="form-group"><label>Título *</label><input name="title" required></div>
                    <div class="form-group"><label>Tipo</label><select name="schedule_type"><option value="impressao">Impressão</option><option value="reserva">Reserva</option><option value="manutencao">Manutenção</option></select></div>
                    <div class="form-group"><label>Impressora *</label><select name="printer_id" required>${(prnRes.printers||[]).map(p=>`<option value="${p.id}">${esc(p.name)}</option>`).join('')}</select></div>
                    <div class="form-group"><label>Ordem de Produção</label><select name="production_order_id"><option value="">-- Nenhuma --</option>${(opRes.orders||[]).map(o=>`<option value="${o.id}">${esc(o.order_number)} - ${esc(o.title)}</option>`).join('')}</select></div>
                    <div class="form-group"><label>Início *</label><input type="datetime-local" name="start_datetime" required value="${today}T09:00"></div>
                    <div class="form-group"><label>Término *</label><input type="datetime-local" name="end_datetime" required value="${today}T14:00"></div>
                </div>
                <div class="form-group"><label>Observações</label><textarea name="notes" rows="2"></textarea></div>
                <button type="submit" class="btn btn-primary btn-block">Agendar</button>
            </form>
        `);
        document.getElementById('sch-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const data = Object.fromEntries(new FormData(e.target));
            data.start_datetime = data.start_datetime.replace('T', ' ') + ':00';
            data.end_datetime = data.end_datetime.replace('T', ' ') + ':00';
            try {
                const res = await API.post('schedule', data);
                closeModal();
                showToast(res.message);
                this.load();
            } catch (err) { showToast(err.message, 'error'); }
        });
    }
};

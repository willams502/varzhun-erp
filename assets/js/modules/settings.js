/**
 * Módulo 13: Configurações do Sistema e Backup
 */
const SettingsModule = {
    async render() {
        const main = document.getElementById('main-content');
        main.innerHTML = `<div class="page-header">
            <div><h2>Configurações do Sistema</h2><p>Dados da empresa, parâmetros de custo e backup</p></div>
            <div class="actions-bar">
                <button class="btn btn-secondary" onclick="SettingsModule.downloadBackup()"><i data-lucide="download"></i> Exportar Backup JSON</button>
                <button class="btn btn-danger" onclick="SettingsModule.clearDemo()"><i data-lucide="eraser"></i> Limpar Dados [DEMO]</button>
            </div>
        </div>
        <div id="set-body">Carregando...</div>`;
        if (window.lucide) lucide.createIcons();
        this.load();
    },

    async load() {
        try {
            const res = await API.get('settings');
            const s = res.settings || {};
            document.getElementById('set-body').innerHTML = `
                <div class="card">
                        <div class="card-header"><span class="card-title">Empresa & Parâmetros de Cálculo</span></div>
                        <form id="set-form">
                            <div class="form-grid">
                                <div class="form-group"><label>Nome da Empresa</label><input name="company_name" value="${esc(s.company_name)}"></div>
                                <div class="form-group"><label>CNPJ</label><input name="company_cnpj" value="${esc(s.company_cnpj)}"></div>
                                <div class="form-group"><label>Telefone</label><input name="company_phone" value="${esc(s.company_phone)}"></div>
                                <div class="form-group"><label>E-mail</label><input name="company_email" value="${esc(s.company_email)}"></div>
                                <div class="form-group"><label>Custo Padrão kWh (R$)</label><input type="number" step="0.01" name="default_kwh_cost" value="${esc(s.default_kwh_cost)}"></div>
                                <div class="form-group"><label>Margem Padrão (%)</label><input type="number" step="0.5" name="default_profit_margin" value="${esc(s.default_profit_margin)}"></div>
                                <div class="form-group"><label>Desperdício Padrão (%)</label><input type="number" step="0.5" name="default_waste_percent" value="${esc(s.default_waste_percent)}"></div>
                                <div class="form-group"><label>Baixa Automática Estoque</label><select name="auto_deduct_stock"><option value="1" ${s.auto_deduct_stock==='1'?'selected':''}>Ativada</option><option value="0" ${s.auto_deduct_stock==='0'?'selected':''}>Manual</option></select></div>
                            </div>
                            <div class="form-group"><label>Endereço</label><input name="company_address" value="${esc(s.company_address)}"></div>
                            <button type="submit" class="btn btn-primary">Salvar Configurações</button>
                        </form>
                </div>
            `;
            document.getElementById('set-form').addEventListener('submit', async (e) => {
                e.preventDefault();
                const res = await API.post('settings', Object.fromEntries(new FormData(e.target)));
                showToast(res.message);
            });
        } catch (err) {
            document.getElementById('set-body').innerHTML = `<div class="card"><p style="color:var(--danger)">${esc(err.message)}</p></div>`;
        }
    },

    async downloadBackup() {
        const dump = await API.get('settings/backup');
        const blob = new Blob([JSON.stringify(dump, null, 2)], { type: 'application/json' });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = `varzhun-erp-backup-${new Date().toISOString().slice(0,10)}.json`;
        a.click();
        showToast('Backup JSON baixado!');
    },

    async clearDemo() {
        if (!confirm('Remover todos os registros marcados como [DEMO] para iniciar operação real?')) return;
        const res = await API.post('settings/clear_demo', {});
        showToast(res.message);
        App.navigate('dashboard');
    }
};

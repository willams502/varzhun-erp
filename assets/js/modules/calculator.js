/**
 * Módulo 5: Calculadora Automática de Custos de Impressão 3D
 */
const CalculatorModule = {
    async render() {
        const res = await API.get('calculator');
        const printers = res.printers || [];
        const def = res.defaults || {};
        const main = document.getElementById('main-content');

        main.innerHTML = `<div class="page-header">
            <div><h2>Calculadora de Custos 3D</h2><p>Cálculo completo de material, energia, máquina, acabamentos, taxas e margem</p></div>
        </div>
        <div class="grid-2">
            <div class="card">
                <form id="calc-form">
                    <div class="form-group"><label>Nome do Projeto / Peça *</label><input name="title" id="calc-title" required value="Peça Exemplo PLA 150g"></div>
                    <div class="form-grid">
                        <div class="form-group"><label>Impressora (preenche kWh/custo)</label>
                            <select name="printer_id" onchange="CalculatorModule.onSelectPrinter(this)">
                                <option value="">Personalizado</option>
                                ${printers.map(p => `<option value="${p.id}" data-cost="${p.cost_per_hour}" data-kwh="${p.energy_kwh}">${esc(p.name)}</option>`).join('')}
                            </select>
                        </div>
                        <div class="form-group"><label>Tipo de Material</label>
                            <select name="material_type"><option>PLA</option><option>PETG</option><option>ABS</option><option>TPU</option><option>Resina</option><option>Outros</option></select>
                        </div>
                        <div class="form-group"><label>Preço do Kg (R$)</label><input type="number" step="0.01" name="material_price_kg" value="90.00"></div>
                        <div class="form-group"><label>Peso Utilizado (g)</label><input type="number" step="0.1" name="weight_g" value="150"></div>
                        <div class="form-group"><label>Desperdício (%)</label><input type="number" step="0.1" name="waste_percent" value="${def.waste_percent || 5}"></div>
                        <div class="form-group"><label>Horas de Impressão</label><input type="number" step="0.1" name="print_hours" value="5"></div>
                        <div class="form-group"><label>Consumo (kWh/h)</label><input type="number" step="0.01" name="energy_kwh" id="calc-kwh" value="0.18"></div>
                        <div class="form-group"><label>Custo Energia (R$/kWh)</label><input type="number" step="0.01" name="energy_cost_kwh" value="${def.energy_cost_kwh || 0.88}"></div>
                        <div class="form-group"><label>Depreciação (R$/h)</label><input type="number" step="0.01" name="depreciation_per_hour" value="1.20"></div>
                        <div class="form-group"><label>Manutenção (R$/h)</label><input type="number" step="0.01" name="maintenance_per_hour" value="0.60"></div>
                        <div class="form-group"><label>Custo Op. Máquina (R$/h)</label><input type="number" step="0.01" name="machine_op_cost_hour" id="calc-op" value="1.00"></div>
                        <div class="form-group"><label>Mão de Obra (R$)</label><input type="number" step="0.01" name="labor_cost" value="5.00"></div>
                        <div class="form-group"><label>Acabamento (R$)</label><input type="number" step="0.01" name="finishing_cost" value="0"></div>
                        <div class="form-group"><label>Pintura (R$)</label><input type="number" step="0.01" name="painting_cost" value="0"></div>
                        <div class="form-group"><label>Embalagem (R$)</label><input type="number" step="0.01" name="packaging_cost" value="3.00"></div>
                        <div class="form-group"><label>Etiquetas (R$)</label><input type="number" step="0.01" name="labels_cost" value="0"></div>
                        <div class="form-group"><label>Taxa Plataforma (%)</label><input type="number" step="0.1" name="platform_fee_percent" value="0"></div>
                        <div class="form-group"><label>Taxa Pagamento (%)</label><input type="number" step="0.1" name="payment_fee_percent" value="3.5"></div>
                        <div class="form-group"><label>Outros Custos (R$)</label><input type="number" step="0.01" name="extra_costs" value="0"></div>
                        <div class="form-group"><label>Margem Desejada (%)</label><input type="number" step="0.5" name="desired_margin_percent" value="${def.desired_margin_percent || 55}"></div>
                    </div>
                    <div class="actions-bar">
                        <button type="submit" class="btn btn-primary"><i data-lucide="save"></i> Salvar Cálculo</button>
                        <button type="button" class="btn btn-success" onclick="CalculatorModule.applyToProduct()"><i data-lucide="package-plus"></i> Criar Produto no Catálogo</button>
                    </div>
                </form>
            </div>
            <div>
                <div class="card" style="margin-bottom:16px">
                    <div class="card-header"><span class="card-title">Resultado Automático</span></div>
                    <div id="calc-results">Calculando...</div>
                </div>
                <div class="card">
                    <div class="card-header"><span class="card-title">Cálculos Salvos</span></div>
                    <div class="table-wrap" id="calc-history"></div>
                </div>
            </div>
        </div>`;
        if (window.lucide) lucide.createIcons();

        const form = document.getElementById('calc-form');
        form.addEventListener('input', () => this.preview());
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            try {
                const res = await API.post('calculator', Object.fromEntries(new FormData(form)));
                showToast(res.message);
                this.render();
            } catch (err) { showToast(err.message, 'error'); }
        });

        this.renderHistory(res.calculations || []);
        this.preview();
    },

    onSelectPrinter(sel) {
        const opt = sel.selectedOptions[0];
        if (opt && opt.value) {
            document.getElementById('calc-kwh').value = opt.dataset.kwh || 0.15;
            document.getElementById('calc-op').value = opt.dataset.cost || 1.00;
            this.preview();
        }
    },

    async preview() {
        const data = Object.fromEntries(new FormData(document.getElementById('calc-form')));
        try {
            const { result: r } = await API.post('calculator/preview', data);
            document.getElementById('calc-results').innerHTML = `
                <div class="kpi-grid" style="grid-template-columns:1fr 1fr; margin-bottom:12px">
                    <div class="kpi-card"><div class="kpi-label">Custo Total Fabricação</div><div class="kpi-value" style="color:var(--danger)">${fmtBRL(r.calc_total_cost)}</div></div>
                    <div class="kpi-card"><div class="kpi-label">Preço Sugerido Venda</div><div class="kpi-value" style="color:var(--primary)">${fmtBRL(r.calc_suggested_price)}</div></div>
                    <div class="kpi-card"><div class="kpi-label">Lucro Líquido Estimado</div><div class="kpi-value" style="color:var(--success)">${fmtBRL(r.calc_estimated_profit)}</div></div>
                    <div class="kpi-card"><div class="kpi-label">Margem Real</div><div class="kpi-value">${r.calc_margin_percent}%</div></div>
                </div>
                <table>
                    <tbody>
                        <tr><td>Material (com desperdício)</td><td class="mono"><strong>${fmtBRL(r.calc_material_cost)}</strong></td></tr>
                        <tr><td>Energia Elétrica</td><td class="mono"><strong>${fmtBRL(r.calc_energy_cost)}</strong></td></tr>
                        <tr><td>Utilização de Máquina</td><td class="mono"><strong>${fmtBRL(r.calc_machine_cost)}</strong></td></tr>
                        <tr><td>Mão de Obra</td><td class="mono"><strong>${fmtBRL(r.calc_labor_cost)}</strong></td></tr>
                        <tr><td>Acabamento / Embalagem / Extras</td><td class="mono"><strong>${fmtBRL(r.calc_finishing_cost)}</strong></td></tr>
                        <tr><td>Taxas (Plataforma + Pagamento)</td><td class="mono"><strong>${fmtBRL(r.calc_fees_amount)}</strong></td></tr>
                    </tbody>
                </table>
            `;
        } catch {}
    },

    async applyToProduct() {
        const data = Object.fromEntries(new FormData(document.getElementById('calc-form')));
        try {
            const res = await API.post('calculator/apply_product', data);
            showToast(res.message);
        } catch (err) { showToast(err.message, 'error'); }
    },

    renderHistory(calcs) {
        document.getElementById('calc-history').innerHTML = calcs.length ? `<table>
            <thead><tr><th>Projeto</th><th>Custo</th><th>Preço Sug.</th><th>Margem</th><th></th></tr></thead>
            <tbody>${calcs.map(c => `<tr>
                <td><strong>${esc(c.title)}</strong><br><small>${esc(c.material_type)} • ${c.weight_g}g • ${c.print_hours}h</small></td>
                <td>${fmtBRL(c.calc_total_cost)}</td>
                <td><strong>${fmtBRL(c.calc_suggested_price)}</strong></td>
                <td>${c.calc_margin_percent}%</td>
                <td><button class="btn btn-sm btn-danger" onclick="API.del('calculator/${c.id}').then(()=>CalculatorModule.render())"><i data-lucide="trash-2"></i></button></td>
            </tr>`).join('')}</tbody>
        </table>` : '<p>Nenhum cálculo salvo ainda.</p>';
        if (window.lucide) lucide.createIcons();
    }
};

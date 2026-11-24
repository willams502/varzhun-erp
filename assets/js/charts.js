/**
 * Gerenciador de gráficos Chart.js com destruição segura ao re-renderizar.
 */
const ChartManager = {
    instances: {},

    render(canvasId, config) {
        const el = document.getElementById(canvasId);
        if (!el) return;
        if (this.instances[canvasId]) {
            this.instances[canvasId].destroy();
        }
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        const textColor = isDark ? '#cbd5e1' : '#475569';
        const gridColor = isDark ? 'rgba(255,255,255,0.08)' : 'rgba(15,23,42,0.06)';

        config.options = config.options || {};
        config.options.responsive = true;
        config.options.maintainAspectRatio = false;
        config.options.plugins = config.options.plugins || {};
        config.options.plugins.legend = config.options.plugins.legend || { labels: { color: textColor } };

        if (config.type !== 'doughnut' && config.type !== 'pie') {
            config.options.scales = config.options.scales || {
                x: { ticks: { color: textColor }, grid: { color: gridColor }, beginAtZero: true },
                y: { ticks: { color: textColor }, grid: { color: gridColor }, beginAtZero: true, suggestedMax: 5 }
            };
        } else {
            // Garante que gráficos doughnut/pie com todos os valores zerados ainda exibam um anel visual
            const ds = config.data?.datasets?.[0];
            if (ds && Array.isArray(ds.data) && ds.data.every(v => Number(v || 0) === 0)) {
                ds.data = [1];
                ds.backgroundColor = [isDark ? 'rgba(148,163,184,0.2)' : 'rgba(148,163,184,0.3)'];
                config.data.labels = ['Sem valores (R$ 0,00)'];
            }
        }

        this.instances[canvasId] = new Chart(el.getContext('2d'), config);
    }
};

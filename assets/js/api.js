/**
 * Cliente HTTP, formatação PT-BR e utilitários de UI.
 */
const API = {
    async request(route, options = {}) {
        const method = options.method || 'GET';
        const headers = options.headers || {};
        let body = options.body;

        if (body && !(body instanceof FormData) && typeof body === 'object') {
            headers['Content-Type'] = 'application/json';
            body = JSON.stringify(body);
        }

        const [pathPart, ...queryParts] = String(route).split('&');
        const qs = queryParts.length ? '&' + queryParts.join('&') : '';
        const res = await fetch(`api/index.php?route=${encodeURIComponent(pathPart)}${qs}`, {
            method,
            headers,
            body,
        });

        const data = await res.json().catch(() => ({}));
        if (!res.ok) {
            throw new Error(data.error || `Erro HTTP ${res.status}`);
        }
        return data;
    },
    get(route) { return this.request(route, { method: 'GET' }); },
    post(route, body) { return this.request(route, { method: 'POST', body }); },
    put(route, body) { return this.request(route, { method: 'PUT', body }); },
    del(route) { return this.request(route, { method: 'DELETE' }); },
};

function fmtBRL(value) {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(Number(value || 0));
}

function fmtDate(dateStr) {
    if (!dateStr) return '—';
    const d = String(dateStr).slice(0, 10).split('-');
    if (d.length !== 3) return dateStr;
    return `${d[2]}/${d[1]}/${d[0]}`;
}

function fmtDateTime(dtStr) {
    if (!dtStr) return '—';
    const [datePart, timePart] = String(dtStr).split(' ');
    return `${fmtDate(datePart)} ${timePart ? timePart.slice(0, 5) : ''}`.trim();
}

function showToast(message, type = 'success') {
    const container = document.getElementById('toast-container');
    const el = document.createElement('div');
    el.className = `toast toast-${type}`;
    el.textContent = message;
    container.appendChild(el);
    setTimeout(() => el.remove(), 3800);
}

function openModal(title, html) {
    document.getElementById('modal-title').textContent = title;
    document.getElementById('modal-body').innerHTML = html;
    document.getElementById('modal-backdrop').classList.remove('hidden');
    if (window.lucide) lucide.createIcons();
}

function closeModal() {
    document.getElementById('modal-backdrop').classList.add('hidden');
    document.getElementById('modal-body').innerHTML = '';
}

function esc(str) {
    return String(str ?? '').replace(/[&<>"']/g, m => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[m]));
}

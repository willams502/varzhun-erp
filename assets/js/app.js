/**
 * Aplicação Principal: Roteamento, Permissões, Tema, Busca Global e Notificações.
 */
const App = {
    user: null,
    currentRoute: 'dashboard',

    navItems: [
        { section: 'Visão Geral' },
        { id: 'dashboard', label: 'Dashboard', icon: 'layout-dashboard', module: 'dashboard' },
        { section: 'Comercial' },
        { id: 'sales', label: 'Vendas & Orçamentos', icon: 'shopping-cart', module: 'sales' },
        { id: 'customers', label: 'Clientes (CRM)', icon: 'users', module: 'customers' },
        { id: 'products', label: 'Catálogo de Produtos', icon: 'package', module: 'products' },
        { id: 'calculator', label: 'Calculadora 3D', icon: 'calculator', module: 'calculator' },
        { section: 'Operação & Fábrica' },
        { id: 'production', label: 'Produção (Kanban)', icon: 'kanban', module: 'production' },
        { id: 'schedule', label: 'Agenda & Planejamento', icon: 'calendar', module: 'schedule' },
        { id: 'printers', label: 'Impressoras 3D', icon: 'printer', module: 'printers' },
        { id: 'inventory', label: 'Estoque', icon: 'boxes', module: 'inventory' },
        { id: 'suppliers', label: 'Fornecedores', icon: 'truck', module: 'suppliers' },
        { section: 'Gestão' },
        { id: 'finance', label: 'Financeiro', icon: 'wallet', module: 'finance' },
        { id: 'reports', label: 'Relatórios', icon: 'bar-chart-3', module: 'reports' },
        { id: 'users_varzhun', label: 'Usuários Varzhun', icon: 'user-cog', module: 'saas_admin' },
        { id: 'saas_admin', label: 'Clientes & Cobranças', icon: 'shield-check', module: 'saas_admin' },
        { id: 'settings', label: 'Configurações', icon: 'settings', module: 'settings' },
    ],

    async init() {
        this.initTheme();
        this.bindEvents();
        try {
            const res = await API.get('auth/me');
            if (res.authenticated && res.user) {
                this.onLogin(res.user);
            } else {
                this.showLogin();
            }
        } catch (e) {
            this.showLogin();
        }
        if (window.lucide) lucide.createIcons();
    },

    initTheme() {
        const saved = localStorage.getItem('varzhun_theme') || 'light';
        document.documentElement.setAttribute('data-theme', saved);
    },

    bindEvents() {
        document.getElementById('login-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            try {
                const email = document.getElementById('login-email').value;
                const password = document.getElementById('login-password').value;
                const res = await API.post('auth/login', { email, password });
                this.onLogin(res.user);
                showToast(`Bem-vindo(a), ${res.user.name}!`);
            } catch (err) {
                showToast(err.message, 'error');
            }
        });

        document.getElementById('logout-btn').addEventListener('click', async () => {
            await API.post('auth/logout', {});
            this.user = null;
            this.showLogin();
        });

        document.getElementById('sidebar-toggle').addEventListener('click', () => {
            document.getElementById('sidebar').classList.toggle('collapsed');
        });

        document.getElementById('mobile-menu-btn').addEventListener('click', () => {
            document.getElementById('sidebar').classList.toggle('mobile-open');
        });

        document.getElementById('theme-toggle').addEventListener('click', () => {
            const cur = document.documentElement.getAttribute('data-theme');
            const next = cur === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', next);
            localStorage.setItem('varzhun_theme', next);
            if (this.currentRoute === 'dashboard') DashboardModule.render();
        });

        document.getElementById('modal-close').addEventListener('click', closeModal);
        document.getElementById('modal-backdrop').addEventListener('click', (e) => {
            if (e.target.id === 'modal-backdrop') closeModal();
        });

        // Busca global
        let searchTimer;
        const searchInput = document.getElementById('global-search-input');
        const searchBox = document.getElementById('global-search-results');
        searchInput.addEventListener('input', () => {
            clearTimeout(searchTimer);
            const q = searchInput.value.trim();
            if (q.length < 2) { searchBox.classList.add('hidden'); return; }
            searchTimer = setTimeout(async () => {
                try {
                    const res = await API.get(`search&q=${encodeURIComponent(q)}`);
                    if (!res.results.length) {
                        searchBox.innerHTML = '<div class="search-item"><small>Nenhum resultado encontrado.</small></div>';
                    } else {
                        searchBox.innerHTML = res.results.map(r => `
                            <div class="search-item" onclick="App.navigate('${r.module}'); document.getElementById('global-search-results').classList.add('hidden');">
                                <strong>${esc(r.title)}</strong>
                                <small style="color:var(--text-muted)">${esc(r.module_label)} • ${esc(r.subtitle)}</small>
                            </div>
                        `).join('');
                    }
                    searchBox.classList.remove('hidden');
                } catch {}
            }, 250);
        });

        document.addEventListener('click', (e) => {
            if (!e.target.closest('.global-search')) searchBox.classList.add('hidden');
            if (!e.target.closest('.notif-wrapper')) document.getElementById('notif-dropdown').classList.add('hidden');
        });

        document.getElementById('notif-btn').addEventListener('click', () => {
            document.getElementById('notif-dropdown').classList.toggle('hidden');
        });
    },

    showLogin() {
        document.getElementById('app-shell').classList.add('hidden');
        document.getElementById('login-screen').classList.remove('hidden');
        ConstellationBG.start();
    },

    onLogin(user) {
        ConstellationBG.stop();
        this.user = user;
        document.getElementById('login-screen').classList.add('hidden');
        document.getElementById('app-shell').classList.remove('hidden');
        document.getElementById('user-name').textContent = user.name;
        document.getElementById('user-role').textContent = user.role;
        document.getElementById('user-avatar').textContent = user.name.charAt(0).toUpperCase();
        this.renderSidebar();
        this.loadNotifications();
        this.navigate('dashboard');
    },

    hasPerm(module) {
        if (!this.user) return false;
        if (module === 'saas_admin') return Number(this.user.id) === 1;
        const p = this.user.permissions || [];
        return p.includes('*') || p.includes(module) || p.includes(`${module}_read`);
    },

    renderSidebar() {
        const nav = document.getElementById('sidebar-nav');
        nav.innerHTML = this.navItems.map(item => {
            if (item.section) return `<div class="nav-section-title">${item.section}</div>`;
            if (!this.hasPerm(item.module)) return '';
            return `<button class="nav-item ${this.currentRoute === item.id ? 'active' : ''}" data-route="${item.id}" onclick="App.navigate('${item.id}')">
                <i data-lucide="${item.icon}"></i>
                <span class="nav-label">${item.label}</span>
            </button>`;
        }).join('');
        if (window.lucide) lucide.createIcons();
    },

    async loadNotifications() {
        try {
            const res = await API.get('notifications');
            const badge = document.getElementById('notif-count');
            const list = document.getElementById('notif-list');
            if (res.count > 0) {
                badge.textContent = res.count;
                badge.classList.remove('hidden');
                list.innerHTML = res.notifications.map(n => `
                    <div class="notif-item" onclick="App.navigate('${n.module}'); document.getElementById('notif-dropdown').classList.add('hidden');">
                        <strong style="color:var(--${n.type})">${esc(n.title)}</strong>
                        <small>${esc(n.detail)}</small>
                    </div>
                `).join('');
            } else {
                badge.classList.add('hidden');
                list.innerHTML = '<div class="notif-item"><small>Sem alertas pendentes.</small></div>';
            }
        } catch {}
    },

    navigate(route) {
        if (route === 'saas_admin' || route === 'users_varzhun') {
            if (!this.hasPerm('saas_admin')) {
                showToast('Acesso exclusivo do administrador Varzhun.', 'error');
                route = 'dashboard';
            }
        }
        this.currentRoute = route;
        document.getElementById('sidebar').classList.remove('mobile-open');
        document.querySelectorAll('.nav-item').forEach(el => {
            el.classList.toggle('active', el.dataset.route === route);
        });

        const map = {
            dashboard: () => DashboardModule.render(),
            sales: () => SalesModule.render(),
            customers: () => CustomersModule.render(),
            products: () => ProductsModule.render(),
            calculator: () => CalculatorModule.render(),
            printers: () => PrintersModule.render(),
            production: () => ProductionModule.render(),
            schedule: () => ScheduleModule.render(),
            inventory: () => InventoryModule.render(),
            finance: () => FinanceModule.render(),
            reports: () => ReportsModule.render(),
            suppliers: () => SuppliersModule.render(),
            users_varzhun: () => UsersVarzhunModule.render(),
            saas_admin: () => SaasAdminModule.render(),
            settings: () => SettingsModule.render(),
        };
        (map[route] || map.dashboard)();
    }
};

function fillDemoLogin(email, pass) {
    document.getElementById('login-email').value = email;
    document.getElementById('login-password').value = pass;
}

const ConstellationBG = {
    raf: null,
    nodes: [],
    mouse: { x: null, y: null },

    start() {
        const canvas = document.getElementById('login-constellation');
        if (!canvas || this.raf) return;
        const ctx = canvas.getContext('2d');
        const resize = () => {
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
        };
        resize();
        window.addEventListener('resize', resize);
        window.addEventListener('mousemove', (e) => {
            this.mouse.x = e.clientX;
            this.mouse.y = e.clientY;
        });

        const count = Math.min(85, Math.max(40, Math.floor((window.innerWidth * window.innerHeight) / 18000)));
        this.nodes = Array.from({ length: count }, () => ({
            x: Math.random() * canvas.width,
            y: Math.random() * canvas.height,
            vx: (Math.random() - 0.5) * 0.55,
            vy: (Math.random() - 0.5) * 0.55,
            r: Math.random() * 2 + 1.1,
        }));

        const tick = () => {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            const maxDist = 140;

            for (let i = 0; i < this.nodes.length; i++) {
                const a = this.nodes[i];
                a.x += a.vx;
                a.y += a.vy;
                if (a.x < 0 || a.x > canvas.width) a.vx *= -1;
                if (a.y < 0 || a.y > canvas.height) a.vy *= -1;

                // Desenha ponto brilhante
                ctx.beginPath();
                ctx.arc(a.x, a.y, a.r, 0, Math.PI * 2);
                ctx.fillStyle = 'rgba(56, 189, 248, 0.85)';
                ctx.fill();

                // Conecta com vizinhos próximos
                for (let j = i + 1; j < this.nodes.length; j++) {
                    const b = this.nodes[j];
                    const dx = a.x - b.x;
                    const dy = a.y - b.y;
                    const d = Math.hypot(dx, dy);
                    if (d < maxDist) {
                        ctx.beginPath();
                        ctx.moveTo(a.x, a.y);
                        ctx.lineTo(b.x, b.y);
                        ctx.strokeStyle = `rgba(129, 140, 248, ${(1 - d / maxDist) * 0.45})`;
                        ctx.lineWidth = 1;
                        ctx.stroke();
                    }
                }

                // Conecta suavemente ao cursor do mouse
                if (this.mouse.x !== null) {
                    const md = Math.hypot(a.x - this.mouse.x, a.y - this.mouse.y);
                    if (md < 160) {
                        ctx.beginPath();
                        ctx.moveTo(a.x, a.y);
                        ctx.lineTo(this.mouse.x, this.mouse.y);
                        ctx.strokeStyle = `rgba(34, 211, 238, ${(1 - md / 160) * 0.55})`;
                        ctx.lineWidth = 1.1;
                        ctx.stroke();
                    }
                }
            }
            this.raf = requestAnimationFrame(tick);
        };
        this.raf = requestAnimationFrame(tick);
    },

    stop() {
        if (this.raf) {
            cancelAnimationFrame(this.raf);
            this.raf = null;
        }
    }
};

document.addEventListener('DOMContentLoaded', () => App.init());

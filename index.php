<?php
require_once __DIR__ . '/config/config.php';
function asset_v(string $path): string {
    $full = __DIR__ . '/' . $path;
    return $path . '?v=' . (file_exists($full) ? filemtime($full) : time());
}
?>
<!DOCTYPE html>
<html lang="pt-BR" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vharzun 3D ERP — Gestão Profissional para Impressão 3D</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset_v('assets/css/app.css') ?>">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jspdf-autotable@3.8.2/dist/jspdf.plugin.autotable.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
</head>
<body>
    <!-- Tela de Login -->
    <div id="login-screen" class="login-screen hidden">
        <canvas id="login-constellation" class="login-constellation"></canvas>
        <div class="login-card">
            <div class="login-brand">
                <div class="brand-icon"><i data-lucide="box"></i></div>
                <div>
                    <h1>Vharzun 3D ERP</h1>
                    <p>Gestão Completa para Impressão 3D</p>
                </div>
            </div>
            <form id="login-form">
                <div class="form-group">
                    <label for="login-email">E-mail</label>
                    <input type="email" id="login-email" required placeholder="seu@email.com.br">
                </div>
                <div class="form-group">
                    <label for="login-password">Senha</label>
                    <input type="password" id="login-password" required placeholder="••••••••">
                </div>
                <button type="submit" class="btn btn-primary btn-block">Entrar no Sistema</button>
            </form>
            <?php if (defined('DEMO_MODE') && DEMO_MODE): ?>
            <div class="login-demo-box">
                <small><strong>Contas de acesso rápido (Ambiente Local / Demonstração):</strong></small>
                <div class="demo-pills">
                    <button type="button" class="chip" onclick="fillDemoLogin('gerente@vharzun3d.com.br','gerente123')">Gerente</button>
                    <button type="button" class="chip" onclick="fillDemoLogin('financeiro@vharzun3d.com.br','financeiro123')">Financeiro</button>
                    <button type="button" class="chip" onclick="fillDemoLogin('producao@vharzun3d.com.br','producao123')">Produção</button>
                    <button type="button" class="chip" onclick="fillDemoLogin('vendas@vharzun3d.com.br','vendas123')">Vendedor</button>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Shell Principal do ERP -->
    <div id="app-shell" class="app-shell hidden">
        <aside id="sidebar" class="sidebar">
            <div class="sidebar-header">
                <div class="brand">
                    <div class="brand-icon"><i data-lucide="box"></i></div>
                    <span class="brand-text">Vharzun <strong>3D</strong></span>
                </div>
                <button id="sidebar-toggle" class="icon-btn" title="Recolher menu"><i data-lucide="panel-left-close"></i></button>
            </div>
            <nav id="sidebar-nav" class="sidebar-nav"></nav>
            <div class="sidebar-footer">
                <div class="user-card">
                    <div class="user-avatar" id="user-avatar">A</div>
                    <div class="user-info">
                        <div id="user-name" class="user-name">Usuário</div>
                        <div id="user-role" class="user-role">Perfil</div>
                    </div>
                    <button id="logout-btn" class="icon-btn" title="Sair"><i data-lucide="log-out"></i></button>
                </div>
            </div>
        </aside>

        <div class="main-wrapper">
            <header class="topbar">
                <div class="topbar-left">
                    <button id="mobile-menu-btn" class="icon-btn mobile-only"><i data-lucide="menu"></i></button>
                    <div class="global-search">
                        <i data-lucide="search"></i>
                        <input type="text" id="global-search-input" placeholder="Buscar clientes, produtos, pedidos, estoque...">
                        <div id="global-search-results" class="search-dropdown hidden"></div>
                    </div>
                </div>
                <div class="topbar-right">
                    <div id="demo-badge" class="demo-badge hidden" title="Existem dados demonstrativos carregados">
                        <i data-lucide="sparkles"></i> <span>Modo Demonstração</span>
                    </div>
                    <button id="theme-toggle" class="icon-btn" title="Alternar tema claro/escuro"><i data-lucide="moon"></i></button>
                    <div class="notif-wrapper">
                        <button id="notif-btn" class="icon-btn" title="Notificações">
                            <i data-lucide="bell"></i>
                            <span id="notif-count" class="badge-dot hidden">0</span>
                        </button>
                        <div id="notif-dropdown" class="notif-dropdown hidden">
                            <div class="notif-header"><strong>Alertas do Sistema</strong></div>
                            <div id="notif-list" class="notif-list"></div>
                        </div>
                    </div>
                </div>
            </header>

            <main id="main-content" class="main-content"></main>
        </div>
    </div>

    <!-- Modal Global -->
    <div id="modal-backdrop" class="modal-backdrop hidden">
        <div class="modal-dialog" id="modal-dialog">
            <div class="modal-header">
                <h3 id="modal-title">Título</h3>
                <button id="modal-close" class="icon-btn"><i data-lucide="x"></i></button>
            </div>
            <div id="modal-body" class="modal-body"></div>
        </div>
    </div>

    <!-- Toasts -->
    <div id="toast-container" class="toast-container"></div>

    <script src="<?= asset_v('assets/js/api.js') ?>"></script>
    <script src="<?= asset_v('assets/js/charts.js') ?>"></script>
    <script src="<?= asset_v('assets/js/export.js') ?>"></script>
    <script src="<?= asset_v('assets/js/modules/dashboard.js') ?>"></script>
    <script src="<?= asset_v('assets/js/modules/sales.js') ?>"></script>
    <script src="<?= asset_v('assets/js/modules/customers.js') ?>"></script>
    <script src="<?= asset_v('assets/js/modules/products.js') ?>"></script>
    <script src="<?= asset_v('assets/js/modules/calculator.js') ?>"></script>
    <script src="<?= asset_v('assets/js/modules/printers.js') ?>"></script>
    <script src="<?= asset_v('assets/js/modules/production.js') ?>"></script>
    <script src="<?= asset_v('assets/js/modules/schedule.js') ?>"></script>
    <script src="<?= asset_v('assets/js/modules/inventory.js') ?>"></script>
    <script src="<?= asset_v('assets/js/modules/finance.js') ?>"></script>
    <script src="<?= asset_v('assets/js/modules/reports.js') ?>"></script>
    <script src="<?= asset_v('assets/js/modules/suppliers.js') ?>"></script>
    <script src="<?= asset_v('assets/js/modules/settings.js') ?>"></script>
    <script src="<?= asset_v('assets/js/modules/saas_admin.js') ?>"></script>
    <script src="<?= asset_v('assets/js/modules/users_varzhun.js') ?>"></script>
    <script src="<?= asset_v('assets/js/app.js') ?>"></script>
</body>
</html>

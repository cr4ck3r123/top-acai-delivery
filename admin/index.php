<?php
declare(strict_types=1);

require __DIR__ . '/../api/bootstrap.php';
require __DIR__ . '/../api/admin-session.php';
startAdminSession();
header_remove('Content-Type');
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
$csrfToken = htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0b0710">
    <link rel="icon" type="image/svg+xml" href="../top-acai-logo.svg?v=20261020">
    <meta name="csrf-token" content="<?= $csrfToken ?>">
    <title>Painel administrativo - Top Açaí Delivery</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="admin.css?v=20261020-addon-prices">
</head>
<body>
    <main id="admin-app" data-csrf="<?= $csrfToken ?>">
        <section class="login-screen" id="login-screen">
            <div class="login-card">
                <a class="admin-brand" href="../"><img class="admin-logo" src="../top-acai-logo.svg" alt=""><span class="brand-wordmark"><strong>TOP AÇAÍ</strong><small>DELIVERY</small></span></a>
                <span class="eyebrow">ÁREA RESTRITA</span>
                <h1>Bem-vindo<br>de volta.</h1>
                <p>Entre para gerenciar seus produtos, pedidos e sua loja.</p>
                <form id="login-form">
                    <label>Usuário<input name="username" required maxlength="50" autocomplete="username" autofocus></label>
                    <label>Senha<input name="password" required type="password" maxlength="200" autocomplete="current-password"></label>
                    <p class="form-message" id="login-error" role="alert" hidden></p>
                    <button class="button button-primary" type="submit">Entrar no painel <span>→</span></button>
                </form>
                <a class="back-to-store" href="../">← Voltar para o cardápio</a>
            </div>
            <div class="login-visual"><span class="visual-tag">SABOR DE VERDADE. GESTÃO SEM COMPLICAÇÃO.</span></div>
        </section>

        <div class="dashboard" id="dashboard" hidden>
            <aside class="sidebar">
                <a class="admin-brand" href="../"><img class="admin-logo" src="../top-acai-logo.svg" alt=""><span class="brand-wordmark"><strong>TOP AÇAÍ</strong><small>DELIVERY</small></span></a>
                <span class="sidebar-caption">ADMINISTRAÇÃO</span>
                <nav class="admin-nav" aria-label="Menu administrativo">
                    <button class="nav-item active" type="button" data-view="overview" aria-label="Visão geral" title="Visão geral"><span>▦</span> Visão geral</button>
                    <button class="nav-item" type="button" data-view="products" aria-label="Produtos" title="Produtos"><span>▤</span> Produtos <span class="nav-count" id="nav-products-count">0</span></button>
                    <button class="nav-item" type="button" data-view="addons" aria-label="Complementos" title="Complementos"><span>✳</span> Complementos</button>
                    <button class="nav-item" type="button" data-view="orders" aria-label="Pedidos" title="Pedidos"><span>♧</span> Pedidos <span class="nav-count" id="nav-orders-count">0</span></button>
                    <button class="nav-item" type="button" data-view="store" aria-label="Minha loja" title="Minha loja"><span>⚙</span> Minha loja</button>
                    <button class="nav-item" type="button" data-view="admins" aria-label="Acessos administrativos" title="Acessos"><span>♙</span> Acessos</button>
                </nav>
                <div class="sidebar-bottom">
                    <a class="store-link" href="../" target="_blank" rel="noopener">↗ <span>Abrir cardápio</span></a>
                    <div class="signed-in"><span class="admin-avatar" id="avatar-initial">A</span><div><strong id="signed-in-name">Admin</strong><small>Administrador</small></div></div>
                    <button class="logout-button" id="logout-button" type="button">↪ Sair da conta</button>
                </div>
            </aside>

            <div class="dashboard-main">
                <header class="topbar">
                    <div><span class="topbar-label">TOP AÇAÍ DELIVERY&nbsp; / &nbsp;PAINEL</span><h1 id="page-title">Visão geral</h1></div>
                    <div class="topbar-actions"><span class="live-status" id="store-live-status"><i></i> Loja aberta</span><button class="mobile-logout" id="mobile-logout-button" type="button">↪ Sair</button></div>
                </header>
                <div class="content-area">
                    <section class="view-section" id="overview-view">
                        <div class="welcome-banner">
                            <div><span class="eyebrow">TUDO NO SEU TEMPO</span><h2>Olha só como estão<br>as coisas por aí.</h2><p>As informações da sua loja, sempre à mão.</p></div>
                            <span class="welcome-flower" aria-hidden="true">✳</span>
                        </div>
                        <div class="metric-grid">
                            <article class="metric-card"><span>Pedidos de hoje</span><strong id="metric-today">0</strong><small>Acompanhe o movimento da loja</small></article>
                            <article class="metric-card"><span>Vendas de hoje</span><strong id="metric-sales">R$ 0,00</strong><small>Pedidos confirmados e concluídos</small></article>
                            <article class="metric-card"><span>Produtos ativos</span><strong id="metric-products">0</strong><small>Itens disponíveis no cardápio</small></article>
                        </div>
                        <div class="panel-card overview-orders">
                            <div class="panel-heading"><div><span class="eyebrow">ACOMPANHAMENTO</span><h2>Pedidos recentes</h2></div><button class="button button-quiet" type="button" data-open-view="orders">Ver todos →</button></div>
                            <div class="table-wrap"><table><thead><tr><th>PEDIDO</th><th>CLIENTE</th><th>DATA</th><th>VALOR</th><th>STATUS</th></tr></thead><tbody id="recent-orders-body"></tbody></table></div>
                        </div>
                    </section>

                    <section class="view-section" id="products-view" hidden>
                        <div class="section-toolbar"><div><span class="eyebrow">O QUE TEM DE BOM?</span><p id="products-summary">Gerencie os itens do seu cardápio.</p></div><button class="button button-primary" id="new-product-button" type="button">＋ Novo produto</button></div>
                        <div class="panel-card"><div class="table-wrap"><table><thead><tr><th>PRODUTO</th><th>CATEGORIA</th><th>PREÇO</th><th>STATUS</th><th>AÇÕES</th></tr></thead><tbody id="products-body"></tbody></table></div><div class="empty-table" id="products-empty" hidden>Nenhum produto cadastrado. Adicione seu primeiro produto.</div></div>
                    </section>

                    <section class="view-section" id="addons-view" hidden>
                        <div class="section-toolbar"><div><span class="eyebrow">PERSONALIZE O AÇAÍ</span><p>Defina o preço cobrado por cada complemento. O cliente verá o valor atualizado no cardápio.</p></div></div>
                        <form id="addons-form" class="panel-card">
                            <div class="table-wrap"><table><thead><tr><th>COMPLEMENTO</th><th>PREÇO ADICIONAL (R$)</th><th>STATUS</th></tr></thead><tbody id="addons-body"></tbody></table></div>
                            <div class="addons-save"><p class="form-message" id="addons-message" role="status" hidden></p><button class="button button-primary" type="submit">Salvar valores <span>→</span></button></div>
                        </form>
                    </section>
                    <section class="view-section" id="orders-view" hidden>
                        <div class="section-toolbar"><div><span class="eyebrow">CADA PEDIDO É UM NOVO COMEÇO</span><p>Os pedidos atualizam automaticamente a cada 15 segundos.</p></div><label class="filter-control">Status <select id="order-filter"><option value="all">Todos</option><option value="pending">Aguardando</option><option value="confirmed">Confirmado</option><option value="preparing">Preparando</option><option value="ready">Em andamento (saiu para entrega/pronto para retirada)</option><option value="delivered">Concluído</option><option value="cancelled">Cancelado</option></select></label></div>
                        <div class="orders-grid" id="orders-grid"></div><div class="empty-table" id="orders-empty" hidden>Nenhum pedido recebido ainda.</div>
                    </section>

                    <section class="view-section" id="store-view" hidden>
                        <div class="settings-grid">
                            <section class="panel-card settings-card"><div class="panel-heading"><div><span class="eyebrow">SOBRE O SEU NEGÓCIO</span><h2>Informações da loja</h2><p>Esses dados aparecem no cardápio e nos pedidos.</p></div></div>
                                <form id="store-form" class="admin-form">
                                    <label>Nome da loja<input name="name" required maxlength="100"></label>
                                    <label>WhatsApp para receber pedidos<input name="whatsapp_phone" required inputmode="tel" maxlength="20" placeholder="5511988887777"><small>Inclua código do país e DDD.</small></label>
                                    <div class="form-two-columns"><label>Taxa de entrega (R$)<input name="delivery_fee" type="number" required min="0" max="99999999.99" step="0.01"></label><label>Pedido mínimo para entrega grátis (R$)<input name="free_delivery_minimum" type="number" min="0" max="99999999.99" step="0.01" placeholder="Não oferecer"></label></div>
                                    <label>Tempo estimado de entrega (minutos)<input name="estimated_delivery_minutes" type="number" required min="1" max="240"></label>
                                    <label class="toggle-setting"><input type="checkbox" name="is_open"><span><strong>Aceitando pedidos</strong><small>Quando desligado, o cardápio avisa que a loja está fechada.</small></span></label>
                                    <p class="form-message" id="store-message" role="status" hidden></p>
                                    <button class="button button-primary" type="submit">Salvar configurações <span>→</span></button>
                                </form>
                            </section>
                            <section class="panel-card settings-card password-card"><div class="panel-heading"><div><span class="eyebrow">SEGURANÇA</span><h2>Alterar minha senha</h2><p>Use pelo menos 12 caracteres para manter sua conta protegida.</p></div></div>
                                <form id="password-form" class="admin-form">
                                    <label>Senha atual<input name="current_password" required type="password" autocomplete="current-password" maxlength="200"></label>
                                    <label>Nova senha<input name="new_password" required type="password" minlength="12" autocomplete="new-password" maxlength="200"></label>
                                    <p class="form-message" id="password-message" role="status" hidden></p>
                                    <button class="button button-secondary" type="submit">Atualizar senha</button>
                                </form>
                            </section>
                        </div>
                    </section>

                    <section class="view-section" id="admins-view" hidden>
                        <div class="section-toolbar"><div><span class="eyebrow">ACESSO DA EQUIPE</span><p>Crie ou desative contas administrativas.</p></div><button class="button button-primary" id="new-admin-button" type="button">ï¼‹ Adicionar acesso</button></div>
                        <div class="panel-card"><div class="table-wrap"><table><thead><tr><th>USUÁRIO</th><th>CRIADO EM</th><th>STATUS</th><th>AÇÕES</th></tr></thead><tbody id="admins-body"></tbody></table></div></div>
                    </section>
                </div>
            </div>
        </div>

        <div class="modal-backdrop" id="product-modal" hidden><section class="modal-card" role="dialog" aria-modal="true" aria-labelledby="product-modal-title">
            <button class="modal-close" type="button" data-close-modal aria-label="Fechar">×</button><span class="eyebrow">CARDÁPIO DA CASA</span><h2 id="product-modal-title">Novo produto</h2><p class="modal-description">Conte para os clientes o que faz esse prato tão especial.</p>
            <form id="product-form" class="admin-form">
                <input type="hidden" name="id">
                <label>Nome do produto<input name="name" required maxlength="120" placeholder="Ex.: Burger da Casa"></label>
                <label>Descrição<textarea name="description" rows="3" maxlength="300" placeholder="Ingredientes, sabores, o que faz esse prato especial..."></textarea></label>
                <div class="form-two-columns"><label>Categoria<input name="category" required list="category-options" maxlength="60" placeholder="Ex.: Açaí tradicional"><datalist id="category-options"></datalist></label><label>Preço (R$)<input name="price" required type="number" min="0" max="99999999.99" step="0.01" placeholder="0,00"></label></div>
                <label>Imagem do produto<input name="image_file" type="file" accept="image/jpeg,image/png,image/webp"><small>Envie JPG, PNG ou WebP (até 5 MB), ou informe abaixo uma URL pública HTTPS.</small></label><label>URL da imagem<input name="image_url" type="text" pattern="(?:https://.+|/uploads/products/[a-f0-9]{32}\.(?:jpg|png|webp))" maxlength="500" placeholder="https://..."></label>
                <div class="form-two-columns"><label>Ordem no cardápio<input name="sort_order" type="number" min="0" max="65535" value="0"></label><span class="checkbox-options"><label><input name="featured" type="checkbox"><span>✦ Destaque da casa</span></label><label><input name="available" type="checkbox" checked><span>Disponível no cardápio</span></label></span></div>
                <p class="form-message" id="product-message" role="alert" hidden></p>
                <div class="modal-actions"><button class="button button-secondary" type="button" data-close-modal>Cancelar</button><button class="button button-primary" type="submit">Salvar produto <span>→</span></button></div>
            </form>
        </section></div>
        <div class="modal-backdrop" id="admin-modal" hidden><section class="modal-card modal-card-small" role="dialog" aria-modal="true" aria-labelledby="admin-modal-title"><button class="modal-close" type="button" data-close-modal aria-label="Fechar">×</button><span class="eyebrow">ACESSO DA EQUIPE</span><h2 id="admin-modal-title">Adicionar acesso</h2><p class="modal-description">A pessoa poderá gerenciar pedidos, produtos e configurações desta loja.</p><form id="admin-form" class="admin-form">                <label>Nome de usuário<input name="username" required minlength="3" maxlength="50" autocomplete="off"><small>Use letras, números, ponto, hífen ou sublinhado.</small></label><label>Senha inicial<input name="password" type="password" required minlength="12" maxlength="200" autocomplete="new-password"><small>Compartilhe essa senha com segurança. Ela poderá ser alterada após o acesso.</small></label><p class="form-message" id="admin-message" role="alert" hidden></p><div class="modal-actions"><button class="button button-secondary" type="button" data-close-modal>Cancelar</button><button class="button button-primary" type="submit">Criar acesso <span>→</span></button></div></form></section></div>
        <div class="toast" id="toast" role="status" aria-live="polite"></div>
    </main>
    <script src="admin.js?v=20261020-addon-prices" defer></script>
</body>
</html>

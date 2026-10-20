<?php
declare(strict_types=1);

header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0b0710">
    <link rel="icon" type="image/svg+xml" href="top-acai-logo.svg?v=20261020">
    <meta name="referrer" content="no-referrer">
    <title>Acompanhar pedido</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css?v=20261020-acai3">
</head>
<body>
    <header class="site-header">
        <a class="brand" href="./" aria-label="Top Açaí Delivery"><img class="brand-logo" src="top-acai-logo.svg" alt=""><span class="brand-wordmark"><strong>TOP AÇAÍ</strong><small>DELIVERY</small></span></a>
        <a class="tracking-back" href="./">Voltar ao cardápio</a>
    </header>
    <main class="tracking-shell">
        <section class="tracking-card" aria-labelledby="tracking-title">
            <span class="section-kicker">ACOMPANHAMENTO DO PEDIDO</span>
            <h1 id="tracking-title">Acompanhe seu pedido</h1>
            <p class="tracking-order-number" id="tracking-order-number" aria-live="polite"></p>
            <div id="tracking-content" aria-live="polite">
                <p class="tracking-message">Consultando o pedido...</p>
            </div>
            <p class="tracking-privacy">Este link permite consultar apenas o andamento deste pedido. Guarde-o e evite compartilhá-lo.</p>
            <a class="tracking-menu-link" href="./">Voltar ao cardápio</a>
        </section>
    </main>
    <script src="qrcode-generator.min.js" defer></script>
    <script src="pix-qr.js" defer></script>
    <script src="track-order.js" defer></script>
</body>
</html>

(() => {
    "use strict";

    const isStandalone = window.matchMedia("(display-mode: standalone)").matches || navigator.standalone === true;
    if ("serviceWorker" in navigator && location.protocol === "https:") {
        window.addEventListener("load", () => navigator.serviceWorker.register("service-worker.js").catch(() => {}), { once: true });
    }
    if (isStandalone || sessionStorage.getItem("top-acai-install-dismissed")) return;

    let installPrompt = null;
    const isIOS = /iphone|ipad|ipod/i.test(navigator.userAgent)
        || (navigator.platform === "MacIntel" && navigator.maxTouchPoints > 1);

    function showPrompt(iosHelp = false) {
        if (document.querySelector(".pwa-install-card") || sessionStorage.getItem("top-acai-install-dismissed")) return;
        const card = document.createElement("aside");
        card.className = "pwa-install-card";
        card.setAttribute("role", "dialog");
        card.setAttribute("aria-label", "Instalar aplicativo Top Açaí");
        const copy = document.createElement("div");
        copy.className = "pwa-install-copy";
        const title = document.createElement("strong");
        title.textContent = iosHelp ? "Instale a Top Açaí no seu iPhone" : "Quer instalar o aplicativo Top Açaí?";
        const description = document.createElement("span");
        description.textContent = iosHelp
            ? "Toque em Compartilhar e depois em Adicionar à Tela de Início."
            : "Acesse o cardápio mais rápido direto pela tela inicial.";
        copy.append(title, description);
        const actions = document.createElement("div");
        actions.className = "pwa-install-actions";
        const install = document.createElement("button");
        install.type = "button";
        install.className = "pwa-install-confirm";
        install.textContent = iosHelp ? "Entendi" : "Instalar";
        const close = document.createElement("button");
        close.type = "button";
        close.className = "pwa-install-close";
        close.setAttribute("aria-label", "Agora não");
        close.textContent = "×";
        actions.append(install, close);
        card.append(copy, actions);
        document.body.append(card);
        requestAnimationFrame(() => card.classList.add("is-visible"));

        install.addEventListener("click", async () => {
            if (iosHelp) {
                card.remove();
                return;
            }
            if (!installPrompt) return;
            installPrompt.prompt();
            const choice = await installPrompt.userChoice;
            installPrompt = null;
            card.remove();
            if (choice.outcome === "accepted") sessionStorage.setItem("top-acai-install-dismissed", "1");
        });
        close.addEventListener("click", () => {
            sessionStorage.setItem("top-acai-install-dismissed", "1");
            card.remove();
        });
    }

    window.addEventListener("beforeinstallprompt", (event) => {
        event.preventDefault();
        installPrompt = event;
        window.setTimeout(() => showPrompt(false), 1200);
    });
    window.addEventListener("appinstalled", () => {
        installPrompt = null;
        sessionStorage.setItem("top-acai-install-dismissed", "1");
        document.querySelector(".pwa-install-card")?.remove();
    });

    if (isIOS) window.setTimeout(() => showPrompt(true), 1400);
})();

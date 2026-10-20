(() => {
    "use strict";

    const apiUrl = "../api/admin.php";
    const uploadUrl = "../api/upload-product-image.php";
    const currency = new Intl.NumberFormat("pt-BR", { style: "currency", currency: "BRL" });
    const statusLabels = {
        pending: "Aguardando", confirmed: "Confirmado", preparing: "Preparando",
        ready: "Em andamento", delivered: "ConcluÃ­do", cancelled: "Cancelado"
    };
    const paymentLabels = {
        pix: "Pix", credit_card: "CartÃ£o de crÃ©dito",
        debit_card: "CartÃ£o de dÃ©bito", cash: "Dinheiro",
        pix_online: "Pix online", credit_card_online: "CartÃ£o de crÃ©dito online"
    };
    const paymentStatusLabels = {
        not_required: "Pagamento na entrega",
        pending: "Aguardando pagamento",
        approved: "Pagamento aprovado",
        rejected: "Pagamento recusado",
        cancelled: "Pagamento cancelado",
        refunded: "Pagamento estornado"
    };
    const transitions = {
        pending: [["confirmed", "Confirmar pedido"], ["cancelled", "Cancelar pedido"]],
        confirmed: [["preparing", "Iniciar preparo"], ["cancelled", "Cancelar pedido"]],
        preparing: [["ready"], ["cancelled", "Cancelar pedido"]],
        ready: [["delivered"]],
        delivered: [],
        cancelled: []
    };
    const elements = {
        app: document.querySelector("#admin-app"),
        login: document.querySelector("#login-screen"),
        dashboard: document.querySelector("#dashboard"),
        toast: document.querySelector("#toast"),
        loginForm: document.querySelector("#login-form"),
        loginError: document.querySelector("#login-error"),
        productModal: document.querySelector("#product-modal"),
        productForm: document.querySelector("#product-form"),
        productMessage: document.querySelector("#product-message"),
        adminModal: document.querySelector("#admin-modal"),
        adminForm: document.querySelector("#admin-form"),
        adminMessage: document.querySelector("#admin-message"),
        storeForm: document.querySelector("#store-form"),
        storeMessage: document.querySelector("#store-message"),
        addonsForm: document.querySelector("#addons-form"),
        addonsMessage: document.querySelector("#addons-message"),
        passwordForm: document.querySelector("#password-form"),
        passwordMessage: document.querySelector("#password-message")
    };
    const state = { csrf: elements.app.dataset.csrf, admin: null, products: [], addons: [], settings: {}, orders: [], admins: [], view: "overview" };
    let toastTimer;
    let orderPollInProgress = false;

    const escapeHTML = (value) => String(value ?? "").replace(/[&<>"']/g, (character) => ({
        "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;"
    })[character]);

    function productImageUrl(path) {
        const source = String(path || "").trim();
        const projectBase = new URL("../", document.baseURI);
        if (/^https?:\/\//i.test(source)) return source;
        return new URL(source.replace(/^\/+/, ""), projectBase).href;
    }

    function showToast(message) {
        elements.toast.textContent = message;
        elements.toast.classList.add("visible");
        window.clearTimeout(toastTimer);
        toastTimer = window.setTimeout(() => elements.toast.classList.remove("visible"), 2800);
    }

    function showMessage(element, message, success = false) {
        element.textContent = message;
        element.hidden = false;
        element.classList.toggle("success", success);
    }

    function clearMessage(element) {
        element.textContent = "";
        element.hidden = true;
        element.classList.remove("success");
    }

    async function request(action, data = null) {
        const options = { headers: { Accept: "application/json" } };
        if (data !== null) {
            options.method = "POST";
            options.headers["Content-Type"] = "application/json";
            options.headers["X-CSRF-Token"] = state.csrf;
            options.body = JSON.stringify({ action, ...data });
        }
        const response = await fetch(apiUrl, options);
        const result = await response.json();
        if (!response.ok) {
            if (response.status === 401 && action !== "login") showLogin();
            throw new Error(result.error || "NÃ£o foi possÃ­vel concluir essa solicitaÃ§Ã£o.");
        }
        if (result.csrf_token) state.csrf = result.csrf_token;
        return result;
    }

    function showLogin() {
        elements.dashboard.hidden = true;
        elements.login.hidden = false;
        document.querySelector("#page-title").textContent = "Entrar";
    }

    function showDashboard() {
        elements.login.hidden = true;
        elements.dashboard.hidden = false;
    }

    function setView(view) {
        const validViews = ["overview", "products", "addons", "orders", "store", "admins"];
        if (!validViews.includes(view)) return;
        state.view = view;
        document.querySelectorAll(".view-section").forEach((section) => {
            section.hidden = section.id !== `${view}-view`;
        });
        document.querySelectorAll(".nav-item").forEach((button) => {
            const active = button.dataset.view === view;
            button.classList.toggle("active", active);
            button.setAttribute("aria-current", active ? "page" : "false");
        });
        document.querySelector("#page-title").textContent = ({
            overview: "VisÃ£o geral", products: "Produtos", addons: "Complementos", orders: "Pedidos",
            store: "Minha loja", admins: "Acessos administrativos"
        })[view];
    }

    function formatDate(value, includeTime = false) {
        if (!value) return "â€”";
        const date = new Date(String(value).replace(" ", "T"));
        if (Number.isNaN(date.getTime())) return "â€”";
        return new Intl.DateTimeFormat("pt-BR", includeTime
            ? { dateStyle: "short", timeStyle: "short" }
            : { dateStyle: "short" }).format(date);
    }

    function formatPhone(phone) {
        return String(phone || "").replace(/\D/g, "");
    }

    function statusBadge(status, fulfillment = null) {
        const active = status === "active" || status === "available";
        const orderStatusLabel = status === "ready"
            ? fulfillment === "delivery" ? "Saiu para entrega" : fulfillment === "pickup" ? "Pronto para retirada" : "Em andamento"
            : status === "delivered" && fulfillment === "pickup" ? "Retirado pelo cliente"
                : statusLabels[status];
        const label = status === "available" ? "DisponÃ­vel" : status === "unavailable"
            ? "Oculto" : status === "active" ? "Ativo"
                : status === "inactive" ? "Desativado" : (orderStatusLabel || status);
        return `<span class="status-badge ${active ? "active" : escapeHTML(status)}">${escapeHTML(label)}</span>`;
    }

    function renderProducts() {
        const body = document.querySelector("#products-body");
        const empty = document.querySelector("#products-empty");
        document.querySelector("#nav-products-count").textContent = String(state.products.length);
        document.querySelector("#metric-products").textContent = String(state.products.filter((product) => product.available).length);
        document.querySelector("#products-summary").textContent = `${state.products.length} ${state.products.length === 1 ? "produto cadastrado" : "produtos cadastrados"} no seu cardÃ¡pio.`;
        empty.hidden = state.products.length !== 0;
        body.innerHTML = state.products.map((product) => `
            <tr>
                <td><div class="table-product"><img src="${escapeHTML(productImageUrl(product.image_url))}" alt="" loading="lazy"><div><strong>${escapeHTML(product.name)}${product.featured ? " âœ¦" : ""}</strong><small>${escapeHTML(product.description || "Sem descriÃ§Ã£o")}</small></div></div></td>
                <td>${escapeHTML(product.category)}</td>
                <td><strong>${currency.format(product.price)}</strong></td>
                <td>${statusBadge(product.available ? "available" : "unavailable")}</td>
                <td><div class="table-actions">
                    <button class="table-action" type="button" data-edit-product="${product.id}">Editar</button>
                    <button class="table-action" type="button" data-toggle-product="${product.id}" data-available="${!product.available}">${product.available ? "Ocultar" : "Reativar"}</button>
                    <button class="table-action danger" type="button" data-delete-product="${product.id}">Excluir</button>
                </div></td>
            </tr>`).join("");

        const categories = Array.from(new Set(state.products.map((product) => product.category))).sort((a, b) => a.localeCompare(b, "pt-BR"));
        document.querySelector("#category-options").innerHTML = categories.map((category) =>
            `<option value="${escapeHTML(category)}"></option>`).join("");
    }

    function renderOrderTable(order) {
        return `<tr>
            <td><strong>#${String(order.id).padStart(5, "0")}</strong></td>
            <td>${escapeHTML(order.customer_name)}</td><td>${formatDate(order.created_at, true)}</td>
            <td><strong>${currency.format(order.total)}</strong></td><td>${statusBadge(order.status, order.fulfillment)}</td>
        </tr>`;
    }

    function renderOrderCard(order) {
        const nextStatuses = (transitions[order.status] || []).filter(([status]) =>
            status !== "confirmed" || !order.payment_method.endsWith("_online") || order.payment_status === "approved"
        ).map(([status, label]) => [status, label || (
            status === "ready"
                ? order.fulfillment === "delivery" ? "Confirmar saÃ­da para entrega" : "Marcar pronto para retirada"
                : order.fulfillment === "delivery" ? "Confirmar entrega (motoboy retornou)" : "Confirmar retirada pelo cliente"
        )]);
        const phone = formatPhone(order.customer_phone);
        const fulfillment = order.fulfillment === "pickup"
            ? "Retirada no local"
            : [order.address, order.complement, order.neighborhood].filter(Boolean).join(", ");
        const change = order.cash_change === null ? "" : `<p class="order-address">Troco para R$ ${currency.format(Number(order.cash_change)).replace("R$\u00a0", "")}</p>`;
        return `<article class="order-card">
            <div class="order-card-head">
                <div><h3>Pedido #${String(order.id).padStart(5, "0")}</h3><p>${formatDate(order.created_at, true)}</p></div>
                ${statusBadge(order.status, order.fulfillment)}
            </div>
            <div class="order-body">
                <div class="order-customer"><strong>${escapeHTML(order.customer_name)}</strong><a href="https://wa.me/${phone}" target="_blank" rel="noopener noreferrer">WhatsApp â†—</a><span>${escapeHTML(fulfillment)}</span></div>
                <div class="order-items">${order.items.map((item) => `<div>${item.quantity}Ã— ${escapeHTML(item.name)} â€” ${currency.format(item.unit_price * item.quantity)}${item.notes ? `<br><small>Obs.: ${escapeHTML(item.notes)}</small>` : ""}</div>`).join("") || "<div>Itens indisponÃ­veis</div>"}</div>
                <div class="order-totals"><div class="order-total-line"><span>Subtotal</span><span>${currency.format(order.subtotal)}</span></div><div class="order-total-line"><span>Entrega</span><span>${order.fulfillment === "pickup" ? "Retirada" : currency.format(order.delivery_fee)}</span></div><div class="order-total-line"><span>Pagamento</span><span>${escapeHTML(paymentLabels[order.payment_method] || order.payment_method)}</span></div>${order.payment_method.endsWith("_online") ? `<div class="order-total-line"><span>Status do pagamento</span><span>${escapeHTML(paymentStatusLabels[order.payment_status] || order.payment_status)}</span></div>` : ""}<div class="order-total-line final"><span>Total</span><span>${currency.format(order.total)}</span></div></div>
                ${order.fulfillment === "delivery" ? `<p class="order-address">EndereÃ§o: ${escapeHTML(fulfillment)}</p>` : ""}
                ${change}
                ${order.notes ? `<p class="order-notes"><strong>ObservaÃ§Ãµes:</strong> ${escapeHTML(order.notes)}</p>` : ""}
            </div>
            <div class="order-actions">
                <button class="table-action print-order-button" type="button" data-print-order="${order.id}">â–£ Imprimir comanda</button>
                ${nextStatuses.length ? `<select data-order-status="${order.id}" aria-label="Atualizar status do pedido ${order.id}"><option value="">Atualizar status...</option>${nextStatuses.map(([value, label]) => `<option value="${value}">${label}</option>`).join("")}</select>` : ""}
            </div>
        </article>`;
    }

    function printOrder(order) {
        const orderNumber = String(order.id).padStart(5, "0");
        const fulfillment = order.fulfillment === "pickup" ? "RETIRADA NO LOCAL" : "ENTREGA";
        const address = order.fulfillment === "pickup"
            ? "Retirada no balcÃ£o"
            : [order.address, order.complement, order.neighborhood].filter(Boolean).join(", ");
        const items = order.items.map((item) => `
            <div class="item">
                <div>${item.quantity} x ${escapeHTML(item.name)}</div>
                ${item.notes ? `<div class="item-note">Obs.: ${escapeHTML(item.notes)}</div>` : ""}
                <div class="item-price"><span>${currency.format(item.unit_price)} un.</span><strong>${currency.format(item.unit_price * item.quantity)}</strong></div>
            </div>`).join("");
        const cashChange = order.cash_change === null
            ? ""
            : `<div class="receipt-line"><span>Troco para</span><strong>${currency.format(Number(order.cash_change))}</strong></div>`;
        const notes = order.notes
            ? `<section class="notes"><strong>OBSERVAÃ‡Ã•ES</strong><p>${escapeHTML(order.notes)}</p></section>`
            : "";
        const receipt = document.createElement("main");
        receipt.id = "thermal-print-root";
        receipt.setAttribute("aria-label", `Comanda para impressÃ£o do pedido ${orderNumber}`);
        receipt.innerHTML = `
            <header class="receipt-header"><h1>${escapeHTML(state.settings.name)}</h1><p>COMANDA #${orderNumber}</p><p class="receipt-fulfillment">${fulfillment}</p><p>${formatDate(order.created_at, true)}</p></header>
            <section class="receipt-section"><p class="receipt-label">${order.fulfillment === "pickup" ? "CLIENTE" : "ENTREGAR PARA"}</p><p class="receipt-customer">${escapeHTML(order.customer_name)}</p><p>WhatsApp: ${escapeHTML(order.customer_phone)}</p>${order.fulfillment === "delivery" ? `<p class="receipt-address">${escapeHTML(address)}</p>` : `<p>${escapeHTML(address)}</p>`}</section>
            <section class="receipt-section"><p class="receipt-label">ITENS DO PEDIDO</p>${items || "<p>Itens indisponÃ­veis</p>"}</section>
            <section class="receipt-section"><div class="receipt-line"><span>Subtotal</span><strong>${currency.format(order.subtotal)}</strong></div><div class="receipt-line"><span>Entrega</span><strong>${order.fulfillment === "pickup" ? "Retirada" : currency.format(order.delivery_fee)}</strong></div><div class="receipt-line"><span>Pagamento</span><strong>${escapeHTML(paymentLabels[order.payment_method] || order.payment_method)}</strong></div>${order.payment_method.endsWith("_online") ? `<div class="receipt-line"><span>Status do pagamento</span><strong>${escapeHTML(paymentStatusLabels[order.payment_status] || order.payment_status)}</strong></div>` : ""}${cashChange}<div class="receipt-line receipt-total"><span>TOTAL</span><strong>${currency.format(order.total)}</strong></div></section>
            ${notes}
            <footer class="receipt-footer">Pedido recebido pelo cardÃ¡pio online</footer>`;

        document.querySelector("#thermal-print-root")?.remove();
        document.body.append(receipt);
        const afterPrint = () => {
            receipt.remove();
            window.removeEventListener("afterprint", afterPrint);
        };
        window.addEventListener("afterprint", afterPrint);
        try {
            window.print();
        } catch (error) {
            afterPrint();
            showToast("NÃ£o foi possÃ­vel abrir a janela de impressÃ£o neste navegador.");
            return;
        }
        showToast("Comanda enviada para impressÃ£o. Selecione sua impressora tÃ©rmica.");
    }

    function renderOrders() {
        document.querySelector("#nav-orders-count").textContent = String(state.orders.filter((order) => order.status === "pending").length);
        renderFilteredOrders();
        updateOverview();
    }

    function renderFilteredOrders() {
        const filter = document.querySelector("#order-filter").value;
        const orders = filter === "all" ? state.orders : state.orders.filter((order) => order.status === filter);
        document.querySelector("#orders-grid").innerHTML = orders.map(renderOrderCard).join("");
        document.querySelector("#orders-empty").hidden = orders.length > 0;
    }

    function updateOverview() {
        const today = new Date().toLocaleDateString("pt-BR");
        const todayOrders = state.orders.filter((order) => new Date(String(order.created_at).replace(" ", "T")).toLocaleDateString("pt-BR") === today);
        const sales = todayOrders.filter((order) => ["confirmed", "preparing", "ready", "delivered"].includes(order.status));
        document.querySelector("#metric-today").textContent = String(todayOrders.length);
        document.querySelector("#metric-sales").textContent = currency.format(sales.reduce((total, order) => total + order.total, 0));
        document.querySelector("#recent-orders-body").innerHTML = state.orders.slice(0, 6).map(renderOrderTable).join("");
    }

    async function refreshOrders() {
        if (orderPollInProgress || document.hidden || elements.dashboard.hidden || !state.admin) return;

        orderPollInProgress = true;
        try {
            const previousOrderIds = new Set(state.orders.map((order) => String(order.id)));
            const data = await request();
            const newOrders = data.orders.filter((order) => !previousOrderIds.has(String(order.id)));
            state.orders = data.orders;
            renderOrders();

            if (newOrders.length === 1) {
                showToast(`Novo pedido #${String(newOrders[0].id).padStart(5, "0")} recebido.`);
            } else if (newOrders.length > 1) {
                showToast(`${newOrders.length} novos pedidos recebidos.`);
            }
        } catch (error) {
            console.error("Falha ao atualizar pedidos automaticamente.", error);
        } finally {
            orderPollInProgress = false;
        }
    }

    function renderAdmins() {
        document.querySelector("#admins-body").innerHTML = state.admins.map((account) => `
            <tr><td><div class="table-product"><span class="admin-avatar">${escapeHTML(account.username.slice(0, 1).toUpperCase())}</span><div><strong>${escapeHTML(account.username)}${account.id === state.admin.id ? " (vocÃª)" : ""}</strong><small>Acesso administrativo</small></div></div></td>
                <td>${formatDate(account.created_at)}</td><td>${statusBadge(account.active ? "active" : "inactive")}</td>
                <td>${account.id === state.admin.id ? '<span class="table-actions">Sua conta</span>' : `<button class="table-action${account.active ? " danger" : ""}" type="button" data-toggle-admin="${account.id}" data-active="${!account.active}">${account.active ? "Desativar" : "Reativar"}</button>`}</td>
            </tr>`).join("");
    }

    function renderAddons() {
        document.querySelector("#addons-body").innerHTML = state.addons.map((addon) => `
            <tr><td><strong>${escapeHTML(addon.name)}</strong></td>
                <td><input class="addon-price-input" name="addon_price_${addon.id}" type="number" min="0" max="999999.99" step="0.01" value="${Number(addon.price).toFixed(2)}" aria-label="Preço adicional para ${escapeHTML(addon.name)}"></td>
                <td>${statusBadge(addon.available ? "active" : "inactive")}</td>
            </tr>`).join("");
    }
    function fillSettings() {
        const form = elements.storeForm.elements;
        form.name.value = state.settings.name;
        form.whatsapp_phone.value = state.settings.whatsapp_phone;
        form.delivery_fee.value = state.settings.delivery_fee;
        form.free_delivery_minimum.value = state.settings.free_delivery_minimum;
        form.estimated_delivery_minutes.value = state.settings.estimated_delivery_minutes;
        form.is_open.checked = state.settings.is_open;
        const badge = document.querySelector("#store-live-status");
        badge.classList.toggle("closed", !state.settings.is_open);
        badge.innerHTML = `<i></i> Loja ${state.settings.is_open ? "aberta" : "fechada"}`;
    }

    async function loadDashboard() {
        const data = await request();
        state.admin = data.admin;
        state.products = data.products;
        state.addons = data.addons || [];
        state.settings = data.settings;
        state.orders = data.orders;
        state.admins = data.admins;
        document.querySelector("#signed-in-name").textContent = state.admin.username;
        document.querySelector("#avatar-initial").textContent = state.admin.username.slice(0, 1).toUpperCase();
        renderProducts();
        renderAddons();
        renderOrders();
        renderAdmins();
        fillSettings();
        showDashboard();
        setView(state.view);
    }

    function openModal(modal) {
        modal.hidden = false;
        document.body.style.overflow = "hidden";
        const firstInput = modal.querySelector("input:not([type='hidden'])");
        if (firstInput) firstInput.focus();
    }

    function closeModal(modal) {
        modal.hidden = true;
        document.body.style.overflow = "";
    }

    function openProduct(product = null) {
        elements.productForm.reset();
        clearMessage(elements.productMessage);
        const form = elements.productForm.elements;
        if (product) {
            document.querySelector("#product-modal-title").textContent = "Editar produto";
            form.id.value = product.id;
            form.name.value = product.name;
            form.description.value = product.description;
            form.category.value = product.category;
            form.price.value = Number(product.price).toFixed(2);
            form.image_url.value = product.image_url;
            form.sort_order.value = product.sort_order;
            form.featured.checked = product.featured;
            form.available.checked = product.available;
        } else {
            document.querySelector("#product-modal-title").textContent = "Novo produto";
            form.available.checked = true;
            form.sort_order.value = "0";
        }
        openModal(elements.productModal);
    }

    function formProductData() {
        const form = elements.productForm.elements;
        const price = Number(form.price.value);
        return {
            id: form.id.value ? Number(form.id.value) : null,
            name: form.name.value.trim(),
            description: form.description.value.trim(),
            category: form.category.value.trim(),
            price: price.toFixed(2),
            image_url: form.image_url.value.trim(),
            featured: form.featured.checked,
            available: form.available.checked,
            sort_order: Number(form.sort_order.value || 0)
        };
    }

    document.querySelectorAll(".nav-item").forEach((button) => button.addEventListener("click", () => setView(button.dataset.view)));
    document.querySelectorAll("[data-open-view]").forEach((button) => button.addEventListener("click", () => setView(button.dataset.openView)));

    elements.loginForm.addEventListener("submit", async (event) => {
        event.preventDefault();
        clearMessage(elements.loginError);
        const submit = elements.loginForm.querySelector('[type="submit"]');
        submit.disabled = true;
        try {
            const result = await request("login", {
                username: elements.loginForm.elements.username.value.trim(),
                password: elements.loginForm.elements.password.value
            });
            state.csrf = result.csrf_token;
            state.admin = { username: result.username };
            elements.loginForm.reset();
            await loadDashboard();
        } catch (error) {
            showMessage(elements.loginError, error.message);
        } finally {
            submit.disabled = false;
        }
    });

    async function logout() {
        try {
            const result = await request("logout", {});
            state.csrf = result.csrf_token;
            state.admin = null;
            state.products = [];
            state.orders = [];
            state.admins = [];
            showLogin();
        } catch (error) {
            showToast(error.message);
        }
    }
    document.querySelector("#logout-button").addEventListener("click", logout);
    document.querySelector("#mobile-logout-button").addEventListener("click", logout);

    document.querySelector("#new-product-button").addEventListener("click", () => openProduct());
    document.querySelector("#products-body").addEventListener("click", async (event) => {
        const edit = event.target.closest("[data-edit-product]");
        const toggle = event.target.closest("[data-toggle-product]");
        const remove = event.target.closest("[data-delete-product]");
        if (edit) {
            const product = state.products.find((entry) => entry.id === Number(edit.dataset.editProduct));
            if (product) openProduct(product);
        }
        if (toggle) {
            try {
                await request("toggle_product", { id: Number(toggle.dataset.toggleProduct), available: toggle.dataset.available === "true" });
                await loadDashboard();
                setView("products");
                showToast(toggle.dataset.available === "true" ? "Produto reativado no cardÃ¡pio." : "Produto ocultado do cardÃ¡pio.");
            } catch (error) {
                showToast(error.message);
            }
        }
        if (remove) {
            const product = state.products.find((entry) => entry.id === Number(remove.dataset.deleteProduct));
            if (!product || !window.confirm(`Excluir "${product.name}"? Os pedidos antigos serÃ£o preservados, mas essa aÃ§Ã£o nÃ£o pode ser desfeita.`)) return;
            try {
                const result = await request("delete_product", { id: product.id });
                await loadDashboard();
                setView("products");
                showToast(result.message);
            } catch (error) {
                showToast(error.message);
            }
        }
    });

    elements.productForm.addEventListener("submit", async (event) => {
        event.preventDefault();
        clearMessage(elements.productMessage);
        const submit = elements.productForm.querySelector('[type="submit"]');
        submit.disabled = true;
        try {
            const file = elements.productForm.elements.image_file.files[0];
            if (file) {
                const body = new FormData();
                body.append("image", file);
                const upload = await fetch(uploadUrl, {
                    method: "POST",
                    headers: { "X-CSRF-Token": state.csrf },
                    credentials: "same-origin",
                    body
                });
                const uploaded = await upload.json();
                if (!upload.ok) throw new Error(uploaded.error || "NÃ£o foi possÃ­vel enviar a imagem.");
                elements.productForm.elements.image_url.value = uploaded.image_url;
            }
            if (!elements.productForm.elements.image_url.value.trim()) {
                throw new Error("Envie uma imagem ou informe uma URL HTTPS.");
            }
            const result = await request("save_product", formProductData());
            closeModal(elements.productModal);
            await loadDashboard();
            setView("products");
            showToast(result.message);
        } catch (error) {
            showMessage(elements.productMessage, error.message);
        } finally {
            submit.disabled = false;
        }
    });

    document.querySelector("#order-filter").addEventListener("change", (event) => {
        renderFilteredOrders();
    });

    document.querySelector("#orders-grid").addEventListener("change", async (event) => {
        const select = event.target.closest("[data-order-status]");
        if (!select || !select.value) return;
        const order = state.orders.find((item) => item.id === Number(select.dataset.orderStatus));
        const nextStatus = select.value;
        if (nextStatus === "cancelled" && !window.confirm("Deseja cancelar este pedido? Essa aÃ§Ã£o nÃ£o pode ser desfeita.")) {
            select.value = "";
            return;
        }
        try {
            const result = await request("update_order_status", { id: Number(select.dataset.orderStatus), status: nextStatus });
            await loadDashboard();
            setView("orders");
            document.querySelector("#order-filter").value = order?.status === nextStatus ? nextStatus : "all";
            document.querySelector("#order-filter").dispatchEvent(new Event("change"));
            showToast(result.message);
        } catch (error) {
            showToast(error.message);
            await loadDashboard();
            setView("orders");
        }
    });

    document.querySelector("#orders-grid").addEventListener("click", (event) => {
        const button = event.target.closest("[data-print-order]");
        if (!button) return;
        const order = state.orders.find((item) => item.id === Number(button.dataset.printOrder));
        if (order) printOrder(order);
    });

    elements.storeForm.addEventListener("submit", async (event) => {
        event.preventDefault();
        clearMessage(elements.storeMessage);
        const form = elements.storeForm.elements;
        const submit = elements.storeForm.querySelector('[type="submit"]');
        submit.disabled = true;
        try {
            const result = await request("save_settings", {
                name: form.name.value.trim(),
                whatsapp_phone: form.whatsapp_phone.value.trim(),
                delivery_fee: form.delivery_fee.value,
                free_delivery_minimum: form.free_delivery_minimum.value,
                estimated_delivery_minutes: Number(form.estimated_delivery_minutes.value),
                is_open: form.is_open.checked
            });
            await loadDashboard();
            setView("store");
            showMessage(elements.storeMessage, result.message, true);
        } catch (error) {
            showMessage(elements.storeMessage, error.message);
        } finally {
            submit.disabled = false;
        }
    });

    elements.addonsForm.addEventListener("submit", async (event) => {
        event.preventDefault();
        clearMessage(elements.addonsMessage);
        const submit = elements.addonsForm.querySelector('[type="submit"]');
        submit.disabled = true;
        try {
            const addons = state.addons.map((addon) => ({
                id: addon.id,
                price: elements.addonsForm.elements[`addon_price_${addon.id}`].value
            }));
            const result = await request("save_addon_prices", { addons });
            await loadDashboard();
            setView("addons");
            showMessage(elements.addonsMessage, result.message, true);
        } catch (error) {
            showMessage(elements.addonsMessage, error.message);
        } finally {
            submit.disabled = false;
        }
    });
    elements.passwordForm.addEventListener("submit", async (event) => {
        event.preventDefault();
        clearMessage(elements.passwordMessage);
        const form = elements.passwordForm.elements;
        const submit = elements.passwordForm.querySelector('[type="submit"]');
        submit.disabled = true;
        try {
            const result = await request("change_password", {
                current_password: form.current_password.value,
                new_password: form.new_password.value
            });
            elements.passwordForm.reset();
            showMessage(elements.passwordMessage, result.message, true);
        } catch (error) {
            showMessage(elements.passwordMessage, error.message);
        } finally {
            submit.disabled = false;
        }
    });

    document.querySelector("#new-admin-button").addEventListener("click", () => {
        elements.adminForm.reset();
        clearMessage(elements.adminMessage);
        openModal(elements.adminModal);
    });

    elements.adminForm.addEventListener("submit", async (event) => {
        event.preventDefault();
        clearMessage(elements.adminMessage);
        const submit = elements.adminForm.querySelector('[type="submit"]');
        submit.disabled = true;
        try {
            const result = await request("create_admin", {
                username: elements.adminForm.elements.username.value.trim(),
                password: elements.adminForm.elements.password.value
            });
            closeModal(elements.adminModal);
            await loadDashboard();
            setView("admins");
            showToast(result.message);
        } catch (error) {
            showMessage(elements.adminMessage, error.message);
        } finally {
            submit.disabled = false;
        }
    });

    document.querySelector("#admins-body").addEventListener("click", async (event) => {
        const button = event.target.closest("[data-toggle-admin]");
        if (!button) return;
        const active = button.dataset.active === "true";
        const account = state.admins.find((item) => item.id === Number(button.dataset.toggleAdmin));
        if (!active && account && !window.confirm(`Desativar o acesso de "${account.username}"?`)) return;
        try {
            const result = await request("toggle_admin", { id: Number(button.dataset.toggleAdmin), active });
            await loadDashboard();
            setView("admins");
            showToast(result.message);
        } catch (error) {
            showToast(error.message);
        }
    });

    document.querySelectorAll("[data-close-modal]").forEach((button) => {
        button.addEventListener("click", () => {
            closeModal(button.closest(".modal-backdrop"));
        });
    });
    document.querySelectorAll(".modal-backdrop").forEach((modal) => {
        modal.addEventListener("click", (event) => {
            if (event.target === modal) closeModal(modal);
        });
    });
    document.addEventListener("keydown", (event) => {
        if (event.key === "Escape") document.querySelectorAll(".modal-backdrop:not([hidden])").forEach(closeModal);
    });

    (async () => {
        try {
            await loadDashboard();
        } catch (error) {
            if (!elements.login.hidden) {
                showMessage(elements.loginError, error.message);
            }
        }
    })();

    window.setInterval(refreshOrders, 15000);
    document.addEventListener("visibilitychange", () => {
        if (!document.hidden) refreshOrders();
    });
})();


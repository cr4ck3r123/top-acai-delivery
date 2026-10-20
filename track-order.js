(() => {
    "use strict";

    const token = new URLSearchParams(window.location.search).get("token");
    const content = document.querySelector("#tracking-content");
    const orderNumber = document.querySelector("#tracking-order-number");
    let requestInProgress = false;

    function showError(message) {
        content.innerHTML = `<p class="tracking-error">${message}</p><button class="tracking-refresh" type="button">Tentar novamente</button>`;
        content.querySelector("button").addEventListener("click", loadOrder);
    }

    function renderOrder(order) {
        const paymentLabels = {
            pending: "Aguardando confirmação do pagamento",
            approved: "Pagamento aprovado",
            rejected: "Pagamento recusado",
            cancelled: "Pagamento cancelado",
            refunded: "Pagamento estornado"
        };
        const paymentNotice = order.payment_method.endsWith("_online")
            ? `<p class="tracking-status tracking-payment">${paymentLabels[order.payment_status] || "Pagamento em processamento"}</p>`
            : "";
        const pixPanel = typeof order.pix_code === "string" && order.pix_code !== ""
            ? `<section class="pix-payment-panel">
                <h2>Pix copia e cola</h2>
                <p>Escaneie o QR Code com o aplicativo do seu banco ou use Pix Copia e Cola para concluir o pagamento.</p>
                <div class="pix-qr-code" id="tracking-pix-qr" role="img" aria-label="QR Code Pix para pagamento"></div>
                <textarea id="tracking-pix-code" readonly aria-label="Código Pix"></textarea>
                <button class="tracking-refresh" id="copy-tracking-pix" type="button">Copiar código Pix</button>
            </section>`
            : "";
        const finalStep = order.fulfillment === "pickup" ? "Pronto para retirada" : "Saiu para entrega";
        const steps = [
            ["pending", "Pedido recebido"],
            ["confirmed", "Pedido confirmado"],
            ["preparing", "Em preparo"],
            ["ready", finalStep],
            ["delivered", "Concluído"]
        ];
        const currentIndex = steps.findIndex(([status]) => status === order.status);
        if (currentIndex < 0 && order.status !== "cancelled") {
            showError("O status deste pedido não pôde ser exibido.");
            return;
        }

        orderNumber.textContent = `Pedido #${order.order_number}`;
        if (order.status === "cancelled") {
            content.innerHTML = `${paymentNotice}<p class="tracking-status tracking-status-cancelled">Este pedido foi cancelado. Entre em contato com a loja pelo WhatsApp se precisar de ajuda.</p>`;
            return;
        }

        const labels = {
            pending: "Aguardando confirmação da loja",
            confirmed: "Pedido confirmado pela loja",
            preparing: "A loja está preparando seu pedido",
            ready: order.fulfillment === "pickup" ? "Pedido pronto para retirada" : "Pedido saiu para entrega",
            delivered: "Pedido concluído"
        };
        content.innerHTML = `
            ${paymentNotice}
            ${pixPanel}
            <p class="tracking-status">${labels[order.status]}</p>
            <ol class="tracking-steps">
                ${steps.map(([status, label], index) => {
                    const state = order.status === "delivered" || index < currentIndex
                        ? "complete"
                        : index === currentIndex ? "current" : "";
                    return `<li class="tracking-step ${state}"><span class="tracking-step-marker" aria-hidden="true"></span><span>${label}</span></li>`;
                }).join("")}
            </ol>
            <p class="tracking-updated">Esta página atualiza automaticamente enquanto estiver aberta.</p>`;
        if (pixPanel) {
            const pixCodeField = content.querySelector("#tracking-pix-code");
            pixCodeField.value = order.pix_code;
            window.renderPixQr(content.querySelector("#tracking-pix-qr"), order.pix_code);
            content.querySelector("#copy-tracking-pix").addEventListener("click", async () => {
                    try {
                        await navigator.clipboard.writeText(order.pix_code);
                        content.querySelector("#copy-tracking-pix").textContent = "Código copiado";
                    } catch {
                        pixCodeField.focus();
                        pixCodeField.select();
                    }
            });
        }
    }

    async function loadOrder() {
        if (!token || !/^[a-f0-9]{64}$/.test(token)) {
            showError("O link de acompanhamento está inválido ou incompleto.");
            return;
        }
        if (requestInProgress) return;
        requestInProgress = true;
        try {
            const response = await fetch("api/track-order.php", {
                method: "POST",
                headers: { "Content-Type": "application/json", Accept: "application/json" },
                body: JSON.stringify({ token })
            });
            const result = await response.json();
            if (!response.ok) throw new Error(result.error || "Não foi possível consultar o pedido.");
            renderOrder(result);
        } catch (error) {
            showError(error.message || "Não foi possível consultar o pedido. Tente novamente.");
        } finally {
            requestInProgress = false;
        }
    }

    loadOrder();
    window.setInterval(() => {
        if (!document.hidden) loadOrder();
    }, 20000);
})();

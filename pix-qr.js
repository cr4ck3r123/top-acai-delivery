(() => {
    "use strict";

    window.renderPixQr = (container, payload) => {
        if (!(container instanceof HTMLElement) || typeof payload !== "string" || payload === "") {
            return false;
        }

        if (typeof qrcode !== "function") {
            container.textContent = "QR Code indisponível. Use Pix Copia e Cola.";
            console.error("A biblioteca local de QR Code não foi carregada.");
            return false;
        }

        try {
            const qr = qrcode(0, "M");
            qr.addData(payload, "Byte");
            qr.make();
            container.innerHTML = qr.createSvgTag({
                cellSize: 6,
                margin: 4,
                scalable: true,
                alt: "QR Code Pix",
                title: "QR Code Pix para pagamento"
            });
            return true;
        } catch (error) {
            container.textContent = "Não foi possível gerar o QR Code. Use Pix Copia e Cola.";
            console.error("Falha ao gerar o QR Code Pix.", error);
            return false;
        }
    };
})();

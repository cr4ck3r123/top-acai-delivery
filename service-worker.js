const CACHE_NAME = "top-acai-shell-v6";
const BASE_URL = new URL("./", self.registration.scope);
const SHELL = ["./", "./styles.css?v=20261020-acai3", "./app.js?v=20261020-addons2", "./pwa-install.js?v=20261020-subpath", "./top-acai-logo.svg?v=20261020", "./top-acai-hero.svg", "./manifest.webmanifest"].map((path) => new URL(path, BASE_URL).href);

self.addEventListener("install", (event) => {
    event.waitUntil(caches.open(CACHE_NAME).then((cache) => cache.addAll(SHELL)).then(() => self.skipWaiting()));
});

self.addEventListener("activate", (event) => {
    event.waitUntil(caches.keys().then((keys) => Promise.all(keys.filter((key) => key.startsWith("top-acai-shell-") && key !== CACHE_NAME).map((key) => caches.delete(key)))).then(() => self.clients.claim()));
});

self.addEventListener("fetch", (event) => {
    const request = event.request;
    const url = new URL(request.url);
    const basePath = new URL("./", self.registration.scope).pathname;
    if (request.method !== "GET" || url.origin !== self.location.origin || url.pathname.startsWith(`${basePath}api/`)) return;

    if (url.pathname.startsWith(`${basePath}admin/`)) return;

    if (request.mode === "navigate") {
        event.respondWith(fetch(request).then((response) => {
            if (response.ok) {
                const copy = response.clone();
                caches.open(CACHE_NAME).then((cache) => cache.put(request, copy));
            }
            return response;
        }).catch(async () => (await caches.match(request)) || (await caches.match(BASE_URL.href)) || Response.error()));
        return;
    }

    event.respondWith(caches.match(request).then((cached) => cached || fetch(request).then((response) => {
        if (response.ok && url.origin === self.location.origin) {
            const copy = response.clone();
            caches.open(CACHE_NAME).then((cache) => cache.put(request, copy));
        }
        return response;
    })));
});

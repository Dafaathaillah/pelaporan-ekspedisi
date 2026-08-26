const APP_NAME = "Pelaporan Ekspedisi PPA BIB";
const DEFAULT_ICON = "/images/push-icon-192.png";
const DEFAULT_BADGE = "/images/push-badge-96.png";

self.addEventListener("install", () => self.skipWaiting());
self.addEventListener("activate", (event) =>
    event.waitUntil(self.clients.claim()),
);

self.addEventListener("push", (event) => {
    if (!event.data) return;

    event.waitUntil(
        (async () => {
            let payload = {};
            try {
                payload = event.data.json();
            } catch {
                payload = { body: event.data.text() };
            }

            const windows = await self.clients.matchAll({
                type: "window",
                includeUncontrolled: true,
            });
            const hasVisibleWindow = windows.some(
                (client) => client.visibilityState === "visible",
            );

            if (hasVisibleWindow) {
                windows.forEach((client) =>
                    client.postMessage({ type: "PPA_PUSH_RECEIVED", payload }),
                );
                return;
            }

            const title = payload.title || APP_NAME;
            const options = {
                ...payload,
                title: undefined,
                icon: payload.icon || DEFAULT_ICON,
                badge: payload.badge || DEFAULT_BADGE,
                data: {
                    ...(payload.data || {}),
                    url: payload.data?.url || "/dashboard",
                },
            };

            await self.registration.showNotification(title, options);
        })(),
    );
});

self.addEventListener("notificationclick", (event) => {
    event.notification.close();
    const target = new URL(
        event.notification.data?.url || "/dashboard",
        self.location.origin,
    ).href;

    event.waitUntil(
        (async () => {
            const windows = await self.clients.matchAll({
                type: "window",
                includeUncontrolled: true,
            });
            const existing = windows.find(
                (client) => new URL(client.url).origin === self.location.origin,
            );
            if (existing) {
                await existing.focus();
                existing.navigate(target);
                return;
            }
            await self.clients.openWindow(target);
        })(),
    );
});

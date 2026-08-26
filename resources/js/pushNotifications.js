const urlBase64ToUint8Array = (value) => {
    const padding = "=".repeat((4 - (value.length % 4)) % 4);
    const base64 = (value + padding).replace(/-/g, "+").replace(/_/g, "/");
    const raw = window.atob(base64);
    return Uint8Array.from(
        [...raw].map((character) => character.charCodeAt(0)),
    );
};

export const supportsPush = () =>
    window.isSecureContext &&
    "serviceWorker" in navigator &&
    "PushManager" in window &&
    "Notification" in window;

export const getPushState = async () => {
    if (!supportsPush())
        return {
            supported: false,
            permission: "unsupported",
            subscribed: false,
        };
    const registration = await navigator.serviceWorker.register(
        "/service-worker.js",
        { scope: "/" },
    );
    const subscription = await registration.pushManager.getSubscription();
    return {
        supported: true,
        permission: Notification.permission,
        subscribed: Boolean(subscription),
    };
};

export const subscribeToPush = async () => {
    if (!supportsPush())
        throw new Error("Browser atau koneksi ini belum mendukung Web Push.");

    const permission = await Notification.requestPermission();
    if (permission !== "granted")
        throw new Error(
            "Izin notifikasi belum diberikan. Aktifkan melalui pengaturan Chrome.",
        );

    const registration = await navigator.serviceWorker.register(
        "/service-worker.js",
        { scope: "/" },
    );
    await navigator.serviceWorker.ready;
    const { data: config } = await window.axios.get("/push/config");
    if (!config.publicKey)
        throw new Error("VAPID public key belum dikonfigurasi pada server.");

    let subscription = await registration.pushManager.getSubscription();
    if (!subscription) {
        subscription = await registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8Array(config.publicKey),
        });
    }

    const json = subscription.toJSON();
    await window.axios.post("/push/subscriptions", {
        endpoint: subscription.endpoint,
        keys: json.keys,
        contentEncoding: (window.PushManager.supportedContentEncodings || [
            "aes128gcm",
        ])[0],
    });
    await window.axios.post("/push/test");
    return getPushState();
};

export const unsubscribeFromPush = async () => {
    const registration = await navigator.serviceWorker.ready;
    const subscription = await registration.pushManager.getSubscription();
    if (subscription) {
        await window.axios.delete("/push/subscriptions", {
            data: { endpoint: subscription.endpoint },
        });
        await subscription.unsubscribe();
    }
    return getPushState();
};

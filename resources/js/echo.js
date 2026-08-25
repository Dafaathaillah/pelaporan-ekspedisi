import Echo from "laravel-echo";
import Pusher from "pusher-js";
import { router } from "@inertiajs/vue3";
window.Pusher = Pusher;
let echo;
const notify = (detail) =>
    window.dispatchEvent(new CustomEvent("ppa:realtime", { detail }));
const reload = (only) =>
    router.reload({
        only,
        preserveState: true,
        preserveScroll: true,
        showProgress: false,
    });
const coordinatorRefresh = (payload) => {
    if (location.pathname === `/coordinator/submissions/${payload.type}`)
        reload(["items", "stats", "notificationCounts"]);
    else reload(["notificationCounts"]);
};
export function connect(user) {
    if (!user || !import.meta.env.VITE_REVERB_APP_KEY || echo) return echo;
    echo = new Echo({
        broadcaster: "reverb",
        key: import.meta.env.VITE_REVERB_APP_KEY,
        wsHost: import.meta.env.VITE_REVERB_HOST,
        wsPort: Number(import.meta.env.VITE_REVERB_PORT || 80),
        wssPort: Number(import.meta.env.VITE_REVERB_PORT || 443),
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME || "https") === "https",
        enabledTransports: ["ws", "wss"],
    });
    const connection = echo.connector.pusher.connection;
    [
        "connected",
        "connecting",
        "disconnected",
        "unavailable",
        "failed",
    ].forEach((state) =>
        connection.bind(state, () => {
            window.__ppaRealtimeState = state;
            window.dispatchEvent(
                new CustomEvent("ppa:reverb-status", { detail: { state } }),
            );
        }),
    );
    if (user.role === "coordinator" || user.role === "developer")
        echo.private(`coordinator.${user.site}`)
            .listen(".ExpeditionReportCreated", (p) => {
                notify({
                    title: "Kedatangan ekspedisi baru",
                    message: `${p.company} · ${p.driver}`,
                    href: "/coordinator",
                });
                if (location.pathname === "/coordinator")
                    reload(["reports", "summary", "hourly", "options"]);
            })
            .listen(".ExpeditionEscortUpdated", (p) => {
                const closed = p.status === "CLOSED";
                notify({
                    title: closed
                        ? "Escort ekspedisi selesai"
                        : "Escort ekspedisi dimulai",
                    message: `${p.company} · ${p.plate} · ${p.driver || "Driver"}`,
                    href: "/coordinator",
                });
                if (location.pathname === "/coordinator") reload(["reports"]);
            })
            .listen(".VendorSubmissionCreated", (p) => {
                notify({
                    title: `Pengajuan ${p.label} baru`,
                    message: `${p.vendor} · ${p.applicant}`,
                    href: `/coordinator/submissions/${p.type}`,
                });
                coordinatorRefresh(p);
            })
            .listen(".VendorSubmissionUpdated", (p) => {
                notify({
                    title: `Pengajuan ${p.label} diperbarui`,
                    message: `${p.vendor} · ${p.applicant}`,
                    href: `/coordinator/submissions/${p.type}`,
                });
                coordinatorRefresh(p);
            })
            .listen(".VendorSubmissionStatusChanged", (p) => {
                notify({
                    title: `Status ${p.label} diperbarui`,
                    message: `${p.number} · ${p.status_label}`,
                    href: `/coordinator/submissions/${p.type}`,
                });
                coordinatorRefresh(p);
            })
            .listen(".VendorResubmissionRequested", (p) => {
                notify({
                    title: "Permintaan pengajuan ulang dikirim",
                    message: `${p.number} · menunggu Surat Sehat vendor`,
                    href: `/coordinator/submissions/${p.type}`,
                });
                coordinatorRefresh(p);
            })
            .listen(".ExpeditionRequestUpdated", (p) => {
                notify({
                    title: "PR/PO request diperbarui",
                    message: `${p.number} · PR ${p.pr_number || "-"} · PO ${p.po_number || "-"}`,
                    href: "/coordinator/expedition-requests",
                });
                if (location.pathname === "/coordinator/expedition-requests")
                    reload(["items"]);
                if (
                    location.pathname.includes(
                        "/coordinator/submissions/IJIN_LINTAS",
                    )
                )
                    reload(["items"]);
            })
            .listen(".PlanningUpdated", (p) => {
                notify({
                    title: "Planning Izin Lintas diperbarui",
                    message: `${p.vendor} · ${p.planned_date || "Belum dijadwalkan"}`,
                    href: "/coordinator/planning",
                });
                if (location.pathname === "/coordinator/planning")
                    reload(["items", "dates", "range"]);
            });
    if (user.role === "vendor")
        echo.private(`vendor.${user.id}`)
            .listen(".ExpeditionRequestCreated", (p) => {
                notify({
                    title: "Request ekspedisi baru",
                    message: `${p.number} · ${p.route}`,
                    href: "/vendor/expedition-requests",
                });
                if (location.pathname === "/vendor")
                    reload(["expeditionRequests"]);
                if (location.pathname === "/vendor/expedition-requests")
                    reload(["items"]);
            })
            .listen(".VendorSubmissionStatusChanged", (p) => {
                notify({
                    title: "Status pengajuan diperbarui",
                    message: `${p.number} · ${p.status_label}`,
                    href: `/vendor/submissions/${p.id}`,
                });
                location.pathname === "/vendor"
                    ? reload(["submissions"])
                    : reload(["submission"]);
            })
            .listen(".VendorResubmissionRequested", (p) => {
                notify({
                    title: "Perbarui Surat Sehat",
                    message: `Koordinator meminta pembaruan untuk ${p.number}`,
                    href: "/vendor",
                });
                reload(["submissions"]);
            })
            .listen(".ExpeditionRequestUpdated", (p) => {
                notify({
                    title: "Nomor PR/PO diperbarui",
                    message: `${p.number} · PR ${p.pr_number || "-"} · PO ${p.po_number || "-"}`,
                    href: "/vendor/expedition-requests",
                });
                if (location.pathname === "/vendor/expedition-requests")
                    reload(["items"]);
                if (location.pathname === "/vendor")
                    reload(["expeditionRequests"]);
            })
            .listen(".PlanningUpdated", (p) => {
                notify({
                    title: "Tanggal planning diperbarui",
                    message: `${p.number} · ${p.planned_date || "Belum dijadwalkan"}`,
                    href: "/vendor/expedition-requests",
                });
                if (location.pathname === "/vendor/expedition-requests")
                    reload(["items"]);
            });
    if (user.role === "expedition")
        echo.private(`expedition.${user.site}`).listen(
            ".PlanningUpdated",
            (p) => {
                notify({
                    title: "Planning tersinkron",
                    message: `${p.vendor} · ${p.planned_date || "Belum dijadwalkan"}`,
                    href: "/expedition/planning",
                });
                if (location.pathname === "/expedition/planning")
                    reload(["items"]);
            },
        );
    if (user.role === "driver")
        echo.private(`driver.${user.site}`)
            .listen(".ExpeditionReportCreated", (p) => {
                notify({
                    title: "Unit ekspedisi baru tersedia",
                    message: `${p.company} · ${p.driver}`,
                    href: "/driver/escorts",
                });
                if (location.pathname === "/driver/escorts") reload(["items"]);
            })
            .listen(".ExpeditionEscortUpdated", (p) => {
                if (location.pathname === "/driver/escorts") reload(["items"]);
                if (p.driver && p.driver !== user.name)
                    notify({
                        title: "Daftar escort diperbarui",
                        message: `${p.company} · ${p.plate}`,
                        href: "/driver/escorts",
                    });
            });
    return echo;
}

import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { router } from '@inertiajs/vue3';

window.Pusher = Pusher;
let echo;

const notify = detail => window.dispatchEvent(new CustomEvent('ppa:realtime', { detail }));
const silentReload = only => router.reload({ only, preserveState: true, preserveScroll: true, showProgress: false });
const coordinatorRefresh = (kind, payload) => {
    const path = window.location.pathname;
    if (kind === 'expedition' && path === '/coordinator') silentReload(['reports', 'summary', 'hourly', 'options']);
    if (kind === 'submission' && path === `/coordinator/submissions/${payload.type}`) silentReload(['items', 'stats']);
};

export function connect(user) {
    if (!user || !import.meta.env.VITE_REVERB_APP_KEY || echo) return echo;
    echo = new Echo({ broadcaster: 'reverb', key: import.meta.env.VITE_REVERB_APP_KEY, wsHost: import.meta.env.VITE_REVERB_HOST, wsPort: Number(import.meta.env.VITE_REVERB_PORT || 80), wssPort: Number(import.meta.env.VITE_REVERB_PORT || 443), forceTLS: (import.meta.env.VITE_REVERB_SCHEME || 'https') === 'https', enabledTransports: ['ws', 'wss'] });
    const connection = echo.connector.pusher.connection;
    ['connected','connecting','disconnected','unavailable','failed'].forEach(state => connection.bind(state, () => { window.__ppaRealtimeState=state; window.dispatchEvent(new CustomEvent('ppa:reverb-status', { detail:{ state } })); }));
    if (user.role === 'coordinator') echo.private(`coordinator.${user.site}`)
        .listen('.ExpeditionReportCreated', payload => { notify({ title:'Kedatangan ekspedisi baru',message:`${payload.company} · ${payload.driver}`,href:'/coordinator' }); coordinatorRefresh('expedition',payload); })
        .listen('.VendorSubmissionCreated', payload => { notify({ title:`Pengajuan ${payload.label} baru`,message:`${payload.vendor} · ${payload.applicant}`,href:`/coordinator/submissions/${payload.type}` }); coordinatorRefresh('submission',payload); })
        .listen('.VendorSubmissionUpdated', payload => { notify({ title:`Pengajuan ${payload.label} diperbarui`,message:`${payload.vendor} · ${payload.applicant}`,href:`/coordinator/submissions/${payload.type}` }); coordinatorRefresh('submission',payload); })
        .listen('.VendorSubmissionStatusChanged', payload => { notify({ title:`Status ${payload.label} diperbarui`,message:`${payload.number} · ${payload.status_label}`,href:`/coordinator/submissions/${payload.type}` }); coordinatorRefresh('submission',payload); });
    if (user.role === 'vendor') echo.private(`vendor.${user.id}`).listen('.VendorSubmissionStatusChanged', payload => { notify({ title:'Status pengajuan diperbarui',message:`${payload.number} · ${payload.status_label}`,href:`/vendor/submissions/${payload.id}` }); window.location.pathname==='/vendor'?silentReload(['submissions']):silentReload(['submission']); });
    return echo;
}

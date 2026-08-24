import Echo from 'laravel-echo';import Pusher from 'pusher-js';import{router}from'@inertiajs/vue3';window.Pusher=Pusher;let echo;
const notify=detail=>window.dispatchEvent(new CustomEvent('ppa:realtime',{detail}));const reload=only=>router.reload({only,preserveState:true,preserveScroll:true,showProgress:false});
const coordinatorRefresh=payload=>{if(location.pathname===`/coordinator/submissions/${payload.type}`)reload(['items','stats','notificationCounts']);else reload(['notificationCounts'])};
export function connect(user){
 if(!user||!import.meta.env.VITE_REVERB_APP_KEY||echo)return echo;
 echo=new Echo({broadcaster:'reverb',key:import.meta.env.VITE_REVERB_APP_KEY,wsHost:import.meta.env.VITE_REVERB_HOST,wsPort:Number(import.meta.env.VITE_REVERB_PORT||80),wssPort:Number(import.meta.env.VITE_REVERB_PORT||443),forceTLS:(import.meta.env.VITE_REVERB_SCHEME||'https')==='https',enabledTransports:['ws','wss']});
 const connection=echo.connector.pusher.connection;['connected','connecting','disconnected','unavailable','failed'].forEach(state=>connection.bind(state,()=>{window.__ppaRealtimeState=state;window.dispatchEvent(new CustomEvent('ppa:reverb-status',{detail:{state}}))}));
 if(user.role==='coordinator')echo.private(`coordinator.${user.site}`)
  .listen('.ExpeditionReportCreated',p=>{notify({title:'Kedatangan ekspedisi baru',message:`${p.company} · ${p.driver}`,href:'/coordinator'});if(location.pathname==='/coordinator')reload(['reports','summary','hourly','options'])})
  .listen('.VendorSubmissionCreated',p=>{notify({title:`Pengajuan ${p.label} baru`,message:`${p.vendor} · ${p.applicant}`,href:`/coordinator/submissions/${p.type}`});coordinatorRefresh(p)})
  .listen('.VendorSubmissionUpdated',p=>{notify({title:`Pengajuan ${p.label} diperbarui`,message:`${p.vendor} · ${p.applicant}`,href:`/coordinator/submissions/${p.type}`});coordinatorRefresh(p)})
  .listen('.VendorSubmissionStatusChanged',p=>{notify({title:`Status ${p.label} diperbarui`,message:`${p.number} · ${p.status_label}`,href:`/coordinator/submissions/${p.type}`});coordinatorRefresh(p)})
  .listen('.VendorResubmissionRequested',p=>{notify({title:'Permintaan pengajuan ulang dikirim',message:`${p.number} · menunggu Surat Sehat vendor`,href:`/coordinator/submissions/${p.type}`});coordinatorRefresh(p)});
 if(user.role==='vendor')echo.private(`vendor.${user.id}`)
  .listen('.VendorSubmissionStatusChanged',p=>{notify({title:'Status pengajuan diperbarui',message:`${p.number} · ${p.status_label}`,href:`/vendor/submissions/${p.id}`});location.pathname==='/vendor'?reload(['submissions']):reload(['submission'])})
  .listen('.VendorResubmissionRequested',p=>{notify({title:'Perbarui Surat Sehat',message:`Koordinator meminta pembaruan untuk ${p.number}`,href:'/vendor'});reload(['submissions'])});
 return echo;
}

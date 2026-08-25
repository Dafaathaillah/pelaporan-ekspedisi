import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import { connect } from './echo';
import AppLoading from './Components/AppLoading.vue';
import AppFeedback from './Components/AppFeedback.vue';
import RealtimeNotifications from './Components/RealtimeNotifications.vue';

const appName = import.meta.env.VITE_APP_NAME || 'Pelaporan Ekspedisi PPA BIB';

createInertiaApp({
    title: (title) => title ? `${title} | ${appName}` : appName,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        connect(props.initialPage.props.auth?.user);
        return createApp({ render: () => h('div', [h(App, props), h(AppLoading), h(AppFeedback), h(RealtimeNotifications)]) })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});

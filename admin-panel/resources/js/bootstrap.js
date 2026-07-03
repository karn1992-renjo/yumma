import axios from 'axios';
window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

const runtimeBroadcastConfig = window.AppBroadcastConfig ?? {};
const pusherScheme = runtimeBroadcastConfig.scheme ?? import.meta.env.VITE_PUSHER_SCHEME ?? import.meta.env.VITE_REVERB_SCHEME ?? 'https';
const pusherPort = runtimeBroadcastConfig.port ?? import.meta.env.VITE_PUSHER_PORT ?? import.meta.env.VITE_REVERB_PORT ?? (pusherScheme === 'https' ? 443 : 80);
const pusherCluster = runtimeBroadcastConfig.cluster ?? import.meta.env.VITE_PUSHER_APP_CLUSTER ?? import.meta.env.VITE_REVERB_APP_CLUSTER;
const pusherHost = runtimeBroadcastConfig.host ?? import.meta.env.VITE_PUSHER_HOST ?? import.meta.env.VITE_REVERB_HOST ?? (pusherCluster ? `ws-${pusherCluster}.pusher.com` : undefined);
const pusherKey = runtimeBroadcastConfig.key ?? import.meta.env.VITE_PUSHER_APP_KEY ?? import.meta.env.VITE_REVERB_APP_KEY;

if (pusherKey) {
    window.Echo = new Echo({
        broadcaster: 'pusher',
        key: pusherKey,
        cluster: pusherCluster,
        wsHost: pusherHost,
        wssHost: pusherHost,
        wsPort: pusherPort,
        wssPort: pusherPort,
        forceTLS: pusherScheme === 'https',
        enabledTransports: pusherScheme === 'https' ? ['wss'] : ['ws'],
    });
} else {
    console.warn('Pusher app key is missing. Realtime broadcasting was not initialized.');
}

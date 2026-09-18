import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

const apiUrl = import.meta.env.VITE_API_URL || 'http://localhost:8000/api';
const apiOrigin = new URL(apiUrl).origin;
const echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY || 'local-app-key',
    wsHost: import.meta.env.VITE_REVERB_HOST || new URL(apiOrigin).hostname,
    wsPort: Number(import.meta.env.VITE_REVERB_PORT || 8080),
    wssPort: Number(import.meta.env.VITE_REVERB_PORT || 8080),
    forceTLS: false,
    enabledTransports: ['ws', 'wss'],
    authEndpoint: `${apiOrigin}/broadcasting/auth`,
    auth: {
        headers: {
            Authorization: `Bearer ${localStorage.getItem('token') || ''}`,
            Accept: 'application/json',
        },
    },
});

export default echo;
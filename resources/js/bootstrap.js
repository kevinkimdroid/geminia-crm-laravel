import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

const token = document.head.querySelector('meta[name="csrf-token"]');
if (token) {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token.getAttribute('content');
}

window.axios.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response?.status === 419) {
            const msg = error.response?.data?.message || 'Session expired. Refreshing…';
            const refreshUrl = error.response?.data?.refresh_url || window.location.href;
            if (typeof window.toastr !== 'undefined') {
                window.toastr.warning(msg);
            } else {
                alert(msg);
            }
            window.location.href = refreshUrl;
        }
        return Promise.reject(error);
    }
);

/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allow your team to quickly build robust real-time web applications.
 */

import './echo';

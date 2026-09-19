import axios from 'axios';
import toast from '../utils/toast';

const api = axios.create({
    baseURL: import.meta.env.VITE_API_URL || 'http://localhost:8000/api',
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
    },
    timeout: 30000,
});

let _navigate = null;
export const setNavigate = (fn) => { _navigate = fn; };

let _isRedirectingToLogin = false;
let _isRefreshing = false;
let _refreshSubscribers = [];

function onRefreshed(token) {
    _refreshSubscribers.forEach(({ resolve }) => resolve(token));
    _refreshSubscribers = [];
}

function onRefreshFailed(error) {
    _refreshSubscribers.forEach(({ reject }) => reject(error));
    _refreshSubscribers = [];
}

function addRefreshSubscriber(resolve, reject) {
    _refreshSubscribers.push({ resolve, reject });
}

api.interceptors.request.use((config) => {
    const token = localStorage.getItem('token');
    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
});

api.interceptors.response.use(
    (response) => response,
    async (error) => {
        if (axios.isCancel(error) || error.code === 'ERR_CANCELED') {
            return Promise.reject(error);
        }

        const originalRequest = error.config;

        if (error.response?.status === 401 && !originalRequest._retry) {
            const url = originalRequest.url || '';
            const isRefreshRequest = url.includes('/auth/refresh');
            const isLoginRequest = url.includes('/auth/login');
            const isLogoutRequest = url.includes('/auth/logout');

            if (isRefreshRequest) {
                return handleLogout();
            }

            if (isLoginRequest || isLogoutRequest) {
                return Promise.reject(error);
            }

            if (_isRefreshing) {
                return new Promise((resolve, reject) => {
                    addRefreshSubscriber((token) => {
                        originalRequest.headers.Authorization = `Bearer ${token}`;
                        resolve(api(originalRequest));
                    }, reject);
                });
            }

            originalRequest._retry = true;
            _isRefreshing = true;

            try {
                const refreshRes = await api.post('/auth/refresh');
                const newToken = refreshRes.data.token;

                localStorage.setItem('token', newToken);

                onRefreshed(newToken);

                originalRequest.headers.Authorization = `Bearer ${newToken}`;
                _isRefreshing = false;
                return api(originalRequest);
            } catch (refreshError) {
                _isRefreshing = false;
                onRefreshFailed(refreshError);
                return handleLogout();
            }
        }

        if (error.response) {
            const status = error.response.status;
            if (status === 403) {
                toast.error("Accès refusé");
            }
        } else if (!navigator.onLine) {
            toast.error("Vous êtes hors ligne");
        }
        return Promise.reject(error);
    }
);

function handleLogout() {
    if (_isRedirectingToLogin) return Promise.reject({ loggedOut: true });

    toast.error("Votre session a expiré. Veuillez vous reconnecter.");

    localStorage.removeItem('token');
    localStorage.removeItem('user');
    localStorage.removeItem('temp_2fa_token');
    localStorage.removeItem('temp_2fa_email');
    localStorage.removeItem('temp_force_pw_token');
    localStorage.removeItem('temp_force_pw_user');

    import('../store/authStore').then(({ default: useAuthStore }) => {
        useAuthStore.setState({ user: null, token: null, isAuthenticated: false });
    }).catch(() => {});

    if (!_isRedirectingToLogin) {
        _isRedirectingToLogin = true;
        if (_navigate) {
            setTimeout(() => { _navigate('/login'); }, 1500);
        } else if (window.location.pathname !== '/login') {
            setTimeout(() => { window.location.href = '/login'; }, 1500);
        }
        setTimeout(() => { _isRedirectingToLogin = false; }, 5000);
    }

    return Promise.reject({ loggedOut: true });
}

export default api;

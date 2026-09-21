import { create } from 'zustand';
import api from '../services/api';

function parseTokenPayload(token) {
    try {
        const base64 = token.split('.')[1];
        if (!base64) return null;
        const payload = JSON.parse(atob(base64));
        return payload;
    } catch {
        return null;
    }
}

function isTokenValid(token) {
    if (!token) return false;
    const payload = parseTokenPayload(token);
    if (!payload || !payload.exp) return false;
    return payload.exp * 1000 > Date.now();
}

function loadStoredAuth() {
    try {
        const token = localStorage.getItem('token');
        const userStr = localStorage.getItem('user');

        // Si on a un token et un user, on les restaure (même si token semble expiré, l'API le validera)
        if (token && userStr) {
            try {
                const user = JSON.parse(userStr);
                return { user, token, isAuthenticated: true };
            } catch {
                localStorage.removeItem('token');
                localStorage.removeItem('user');
                return { user: null, token: null, isAuthenticated: false };
            }
        }

        return { user: null, token: null, isAuthenticated: false };
    } catch {
        localStorage.removeItem('token');
        localStorage.removeItem('user');
        return { user: null, token: null, isAuthenticated: false };
    }
}

const initialAuth = loadStoredAuth();

const useAuthStore = create((set) => ({
    user: initialAuth.user,
    token: initialAuth.token,
    isAuthenticated: initialAuth.isAuthenticated,

    login: async (email, password) => {
        try {
            const response = await api.post('/auth/login', { email, password });
            const { user, token, requires_2fa, email_verified, must_change_password } = response.data;

            if (requires_2fa) {
                localStorage.setItem('temp_2fa_token', token);
                localStorage.setItem('temp_2fa_email', email);
                return { success: true, requires_2fa: true };
            }

            if (must_change_password) {
                localStorage.setItem('temp_force_pw_token', token);
                localStorage.setItem('temp_force_pw_user', JSON.stringify(user));
                return { success: true, must_change_password: true };
            }

            // IMPORTANT: Sauvegarder AVANT d'update le store
            localStorage.setItem('token', token);
            localStorage.setItem('user', JSON.stringify(user));

            // Ensuite update le store (utilise aussi localStorage.getItem pour être sûr)
            set({ user, token, isAuthenticated: true });
            return { success: true, email_verified };
        } catch (error) {
            // En cas d'erreur, nettoyer la session
            localStorage.removeItem('token');
            localStorage.removeItem('user');
            set({ user: null, token: null, isAuthenticated: false });
            return {
                success: false,
                message: error.response?.data?.message || 'Identifiants invalides'
            };
        }
    },

    verify2FA: async (code) => {
        try {
            const tempToken = localStorage.getItem('temp_2fa_token');
            const tempEmail = localStorage.getItem('temp_2fa_email');

            if (!tempToken || !tempEmail) {
                return { success: false, message: 'Session 2FA expirée. Reconnectez-vous.' };
            }

            const response = await api.post('/auth/2fa/verify-login', {
                token: tempToken,
                email: tempEmail,
                code,
            });

            const { user, token } = response.data;

            localStorage.removeItem('temp_2fa_token');
            localStorage.removeItem('temp_2fa_email');

            if (user.must_change_password) {
                localStorage.setItem('temp_force_pw_token', token);
                localStorage.setItem('temp_force_pw_user', JSON.stringify(user));
                return { success: true, must_change_password: true };
            }

            localStorage.setItem('token', token);
            localStorage.setItem('user', JSON.stringify(user));

            set({ user, token, isAuthenticated: true });
            return { success: true };
        } catch (error) {
            return {
                success: false,
                message: error.response?.data?.message || 'Code 2FA invalide'
            };
        }
    },

    completeForcePasswordChange: async (newPassword, newPasswordConfirmation) => {
        try {
            const tempToken = localStorage.getItem('temp_force_pw_token');
            if (!tempToken) {
                return { success: false, message: 'Session expirée. Reconnectez-vous.' };
            }

            const response = await api.post('/auth/force-change-password', {
                new_password: newPassword,
                new_password_confirmation: newPasswordConfirmation,
            }, {
                headers: { Authorization: `Bearer ${tempToken}` }
            });

            const { user, token } = response.data;

            localStorage.removeItem('temp_force_pw_token');
            localStorage.removeItem('temp_force_pw_user');

            localStorage.setItem('token', token);
            localStorage.setItem('user', JSON.stringify(user));

            set({ user, token, isAuthenticated: true });
            return { success: true };
        } catch (error) {
            return {
                success: false,
                message: error.response?.data?.message || 'Erreur lors du changement de mot de passe'
            };
        }
    },

    logout: async () => {
        try {
            await api.post('/auth/logout');
        } catch {
            // ignore server error, still clear local state
        } finally {
            localStorage.removeItem('token');
            localStorage.removeItem('user');
            localStorage.removeItem('temp_2fa_token');
            localStorage.removeItem('temp_2fa_email');
            localStorage.removeItem('temp_force_pw_token');
            localStorage.removeItem('temp_force_pw_user');
            set({ user: null, token: null, isAuthenticated: false });
        }
    },

    updateProfile: async (data) => {
        try {
            const response = await api.put('/auth/profile', data);
            const { user } = response.data;
            localStorage.setItem('user', JSON.stringify(user));
            set({ user });
            return { success: true, user };
        } catch (error) {
            return {
                success: false,
                message: error.response?.data?.message || 'Erreur de mise à jour'
            };
        }
    },

    setUser: (user) => {
        set({ user, token: localStorage.getItem('token'), isAuthenticated: !!user });
    }
}));

export default useAuthStore;

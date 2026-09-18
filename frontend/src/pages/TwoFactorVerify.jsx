import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import useAuthStore from '../store/authStore';
import toast from '../utils/toast';
import { Shield, ArrowRight, Loader2, ArrowLeft, KeyRound } from 'lucide-react';
import api from '../services/api';

const TwoFactorVerify = () => {
    const [code, setCode] = useState('');
    const [mode, setMode] = useState('totp');
    const [loading, setLoading] = useState(false);
    const navigate = useNavigate();
    const verify2FA = useAuthStore((state) => state.verify2FA);

    const handleSubmit = async (e) => {
        e.preventDefault();
        const expectedLength = mode === 'totp' ? 6 : 8;
        if (code.length !== expectedLength) {
            toast.error(mode === 'totp' ? 'Le code doit contenir 6 chiffres' : 'Le code de récupération fait 8 caractères');
            return;
        }
        setLoading(true);
        try {
            if (mode === 'totp') {
                const result = await verify2FA(code);
                if (result.success) {
                    toast.success('Authentification réussie');
                    navigate('/dashboard');
                } else {
                    toast.error(result.message || 'Code invalide');
                }
            } else {
                const tempToken = localStorage.getItem('temp_2fa_token');
                const tempEmail = localStorage.getItem('temp_2fa_email');
                if (!tempToken || !tempEmail) {
                    toast.error('Session expirée. Reconnectez-vous.');
                    navigate('/login');
                    return;
                }
                const response = await api.post('/auth/2fa/verify-recovery', {
                    token: tempToken,
                    email: tempEmail,
                    code: code.toUpperCase(),
                });
                const { user, token, remaining_recovery_codes } = response.data;
                localStorage.removeItem('temp_2fa_token');
                localStorage.removeItem('temp_2fa_email');

                if (user.must_change_password) {
                    localStorage.setItem('temp_force_pw_token', token);
                    localStorage.setItem('temp_force_pw_user', JSON.stringify(user));
                    toast.info('Vous devez changer votre mot de passe.');
                    navigate('/force-password-change');
                    return;
                }

                localStorage.setItem('token', token);
                localStorage.setItem('user', JSON.stringify(user));
                useAuthStore.setState({ user, token, isAuthenticated: true });
                toast.success(`Connexion réussie. ${remaining_recovery_codes} code(s) de récupération restant(s).`);
                navigate('/dashboard');
            }
        } catch (err) {
            toast.error(err.response?.data?.message || 'Erreur de vérification');
        } finally {
            setLoading(false);
        }
    };

    const toggleMode = () => {
        setMode(mode === 'totp' ? 'recovery' : 'totp');
        setCode('');
    };

    return (
        <div className="min-h-screen flex items-center justify-center bg-slate-950 relative overflow-hidden font-['Outfit'] p-4">
            <div className="absolute inset-x-0 top-0 h-80 bg-gradient-to-b from-sky-500/20 to-transparent pointer-events-none" />
            <div className="absolute inset-0 bg-[radial-gradient(circle_at_top_left,_rgba(59,130,246,0.14),_transparent_18%)] pointer-events-none" />

            <div className="w-full max-w-md relative z-10 animate-fade-in">
                <div className="glass p-8 rounded-[2.5rem] shadow-[0_32px_80px_-32px_rgba(15,23,42,0.45)] border border-white/10 backdrop-blur-xl">
                    <div className="text-center mb-8">
                        <div className="w-16 h-16 bg-gradient-to-br from-emerald-500 to-teal-600 rounded-3xl mx-auto flex items-center justify-center shadow-2xl shadow-emerald-500/25 mb-5">
                            {mode === 'totp' ? (
                                <Shield className="text-white" size={28} />
                            ) : (
                                <KeyRound className="text-white" size={28} />
                            )}
                        </div>
                        <h1 className="text-2xl font-black text-white tracking-tighter">
                            {mode === 'totp' ? 'Code de vérification' : 'Code de récupération'}
                        </h1>
                        <p className="text-gray-400 text-sm mt-2">
                            {mode === 'totp'
                                ? "Entrez le code à 6 chiffres de votre application d'authentification."
                                : 'Entrez un de vos codes de récupération à 8 caractères.'}
                        </p>
                    </div>

                    <form onSubmit={handleSubmit} className="space-y-5">
                        <div className="space-y-2">
                            <label className="text-[10px] font-black text-gray-500 uppercase tracking-widest ml-1">
                                {mode === 'totp' ? 'Code 2FA' : 'Code de récupération'}
                            </label>
                            <input
                                type="text"
                                value={code}
                                onChange={(e) => {
                                    if (mode === 'totp') {
                                        setCode(e.target.value.replace(/\D/g, '').slice(0, 6));
                                    } else {
                                        setCode(e.target.value.replace(/[^a-zA-Z0-9]/g, '').slice(0, 8));
                                    }
                                }}
                                placeholder={mode === 'totp' ? '000000' : 'ABCD1234'}
                                className="w-full text-center text-2xl font-mono tracking-[0.5em] py-3.5 bg-white/5 border border-white/10 rounded-2xl text-white placeholder:text-gray-600 outline-none focus:border-emerald-500 focus:bg-white/[0.08] transition-all text-sm font-medium"
                                maxLength={mode === 'totp' ? 6 : 8}
                                required
                                autoFocus
                            />
                        </div>

                        <button
                            type="submit"
                            disabled={loading || code.length !== (mode === 'totp' ? 6 : 8)}
                            className="w-full group bg-emerald-600 hover:bg-white text-white hover:text-gray-900 py-3.5 rounded-2xl font-black text-xs uppercase tracking-[0.2em] transition-all duration-300 shadow-xl shadow-emerald-500/10 flex items-center justify-center active:scale-95 disabled:opacity-50"
                        >
                            {loading ? (
                                <Loader2 className="w-5 h-5 animate-spin" />
                            ) : (
                                <span className="flex items-center">
                                    Vérifier
                                    <ArrowRight size={16} className="ml-2 group-hover:translate-x-1 transition-transform" />
                                </span>
                            )}
                        </button>
                    </form>

                    <div className="mt-5 text-center space-y-3">
                        <button
                            onClick={toggleMode}
                            className="inline-flex items-center gap-2 text-emerald-400 hover:text-white text-sm transition-colors"
                        >
                            <KeyRound size={14} />
                            {mode === 'totp' ? 'Utiliser un code de récupération' : 'Utiliser le code 2FA'}
                        </button>
                        <div>
                            <button
                                onClick={() => {
                                    localStorage.removeItem('temp_2fa_token');
                                    localStorage.removeItem('temp_2fa_email');
                                    navigate('/login');
                                }}
                                className="inline-flex items-center gap-2 text-gray-400 hover:text-white text-sm transition-colors"
                            >
                                <ArrowLeft size={14} />
                                Retour à la connexion
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default TwoFactorVerify;

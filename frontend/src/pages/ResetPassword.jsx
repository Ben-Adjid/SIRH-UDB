import { useState, useEffect } from 'react';
import { useSearchParams, useNavigate, Link } from 'react-router-dom';
import api from '../services/api';
import toast from '../utils/toast';
import { Shield, Lock, Eye, EyeOff, ArrowRight, ArrowLeft, Loader2, CheckCircle } from 'lucide-react';

const ResetPassword = () => {
    const [searchParams] = useSearchParams();
    const navigate = useNavigate();
    const [password, setPassword] = useState('');
    const [passwordConfirmation, setPasswordConfirmation] = useState('');
    const [showPassword, setShowPassword] = useState(false);
    const [loading, setLoading] = useState(false);
    const [success, setSuccess] = useState(false);
    const [error, setError] = useState('');

    const token = searchParams.get('token');
    const email = searchParams.get('email');

    useEffect(() => {
        if (!token || !email) {
            setError('Lien de réinitialisation invalide. Demandez un nouveau lien.');
        }
    }, [token, email]);

    const passwordRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&#])[A-Za-z\d@$!%*?&#]{12,}$/;

    const handleSubmit = async (e) => {
        e.preventDefault();
        if (password !== passwordConfirmation) {
            toast.error('Les mots de passe ne correspondent pas');
            return;
        }
        if (password.length < 12) {
            toast.error('Le mot de passe doit contenir au moins 12 caractères');
            return;
        }
        if (!passwordRegex.test(password)) {
            toast.error('Le mot de passe doit contenir une majuscule, une minuscule, un chiffre et un symbole (@$!%*?&#)');
            return;
        }
        setLoading(true);
        try {
            const res = await api.post('/auth/reset-password', {
                token,
                email,
                password,
                password_confirmation: passwordConfirmation,
            });
            toast.success(res.data.message);
            setSuccess(true);
        } catch (err) {
            toast.error(err.response?.data?.message || 'Erreur lors de la réinitialisation');
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="min-h-screen flex items-center justify-center bg-slate-950 relative overflow-hidden font-['Outfit'] p-4">
            <div className="absolute inset-x-0 top-0 h-80 bg-gradient-to-b from-sky-500/20 to-transparent pointer-events-none" />
            <div className="absolute inset-0 bg-[radial-gradient(circle_at_top_left,_rgba(59,130,246,0.14),_transparent_18%)] pointer-events-none" />

            <div className="w-full max-w-md relative z-10 animate-fade-in">
                <div className="glass p-8 rounded-[2.5rem] shadow-[0_32px_80px_-32px_rgba(15,23,42,0.45)] border border-white/10 backdrop-blur-xl">
                    <div className="text-center mb-8">
                        <div className="w-16 h-16 bg-gradient-to-br from-emerald-500 to-teal-600 rounded-3xl mx-auto flex items-center justify-center shadow-2xl shadow-emerald-500/25 mb-5">
                            <Lock className="text-white" size={28} />
                        </div>
                        <h1 className="text-2xl font-black text-white tracking-tighter">Nouveau mot de passe</h1>
                        <p className="text-gray-400 text-sm mt-2">Choisissez un mot de passe fort et sécurisé.</p>
                    </div>

                    {success ? (
                        <div className="text-center space-y-4">
                            <div className="bg-green-500/10 border border-green-500/20 rounded-2xl p-6">
                                <CheckCircle className="mx-auto text-green-400 mb-2" size={32} />
                                <p className="text-green-400 font-semibold">Mot de passe réinitialisé !</p>
                                <p className="text-gray-400 text-sm mt-2">
                                    Vous pouvez maintenant vous connecter avec votre nouveau mot de passe.
                                </p>
                            </div>
                            <button
                                onClick={() => navigate('/login')}
                                className="w-full group bg-emerald-600 hover:bg-white text-white hover:text-gray-900 py-3.5 rounded-2xl font-black text-xs uppercase tracking-[0.2em] transition-all duration-300 flex items-center justify-center"
                            >
                                <span className="flex items-center">
                                    Se connecter
                                    <ArrowRight size={16} className="ml-2 group-hover:translate-x-1 transition-transform" />
                                </span>
                            </button>
                        </div>
                    ) : error ? (
                        <div className="text-center space-y-4">
                            <div className="bg-red-500/10 border border-red-500/20 rounded-2xl p-6">
                                <p className="text-red-400 font-semibold">{error}</p>
                            </div>
                            <Link
                                to="/forgot-password"
                                className="inline-flex items-center gap-2 text-amber-400 hover:text-white text-sm font-semibold transition-colors"
                            >
                                Demander un nouveau lien
                            </Link>
                        </div>
                    ) : (
                        <form onSubmit={handleSubmit} className="space-y-5">
                            <div className="space-y-2">
                                <label className="text-[10px] font-black text-gray-500 uppercase tracking-widest ml-1">Nouveau mot de passe</label>
                                <div className="relative group">
                                    <Lock className="absolute left-4 top-1/2 -translate-y-1/2 text-gray-500 group-focus-within:text-emerald-500 transition-colors" size={18} />
                                    <input
                                        type={showPassword ? 'text' : 'password'}
                                        value={password}
                                        onChange={(e) => setPassword(e.target.value)}
                                        placeholder="Min. 12 caractères"
                                        className="w-full pl-12 pr-12 py-3.5 bg-white/5 border border-white/10 rounded-2xl text-white placeholder:text-gray-600 outline-none focus:border-emerald-500 focus:bg-white/[0.08] transition-all text-sm font-medium"
                                        required
                                        minLength={12}
                                    />
                                    <button
                                        type="button"
                                        onClick={() => setShowPassword(!showPassword)}
                                        className="absolute right-4 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-300 transition-colors"
                                    >
                                        {showPassword ? <EyeOff size={18} /> : <Eye size={18} />}
                                    </button>
                                </div>
                                <p className="text-[10px] text-gray-600 ml-1">Min. 12 caractères, majuscule, minuscule, chiffre, caractère spécial</p>
                            </div>

                            <div className="space-y-2">
                                <label className="text-[10px] font-black text-gray-500 uppercase tracking-widest ml-1">Confirmer le mot de passe</label>
                                <div className="relative group">
                                    <Lock className="absolute left-4 top-1/2 -translate-y-1/2 text-gray-500 group-focus-within:text-emerald-500 transition-colors" size={18} />
                                    <input
                                        type={showPassword ? 'text' : 'password'}
                                        value={passwordConfirmation}
                                        onChange={(e) => setPasswordConfirmation(e.target.value)}
                                        placeholder="Retapez le mot de passe"
                                        className="w-full pl-12 pr-4 py-3.5 bg-white/5 border border-white/10 rounded-2xl text-white placeholder:text-gray-600 outline-none focus:border-emerald-500 focus:bg-white/[0.08] transition-all text-sm font-medium"
                                        required
                                        minLength={12}
                                    />
                                </div>
                            </div>

                            <div className="text-xs text-gray-500 space-y-1 px-1">
                                <p className={password.length >= 12 ? 'text-emerald-400' : ''}>• 12 caractères minimum</p>
                                <p className={/[A-Z]/.test(password) ? 'text-emerald-400' : ''}>• Au moins une majuscule</p>
                                <p className={/[a-z]/.test(password) ? 'text-emerald-400' : ''}>• Au moins une minuscule</p>
                                <p className={/\d/.test(password) ? 'text-emerald-400' : ''}>• Au moins un chiffre</p>
                                <p className={/[@$!%*?&#]/.test(password) ? 'text-emerald-400' : ''}>• Au moins un symbole (@$!%*?&#)</p>
                                <p className={password === passwordConfirmation && passwordConfirmation ? 'text-emerald-400' : ''}>• Les mots de passe correspondent</p>
                            </div>

                            <button
                                type="submit"
                                disabled={loading}
                                className="w-full group bg-emerald-600 hover:bg-white text-white hover:text-gray-900 py-3.5 rounded-2xl font-black text-xs uppercase tracking-[0.2em] transition-all duration-300 shadow-xl shadow-emerald-500/10 flex items-center justify-center active:scale-95 disabled:opacity-50"
                            >
                                {loading ? (
                                    <Loader2 className="w-5 h-5 animate-spin" />
                                ) : (
                                    <span className="flex items-center">
                                        Réinitialiser
                                        <ArrowRight size={16} className="ml-2 group-hover:translate-x-1 transition-transform" />
                                    </span>
                                )}
                            </button>
                        </form>
                    )}
                </div>
            </div>
        </div>
    );
};

export default ResetPassword;

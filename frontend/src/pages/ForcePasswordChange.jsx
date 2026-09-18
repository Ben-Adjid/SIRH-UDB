import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import useAuthStore from '../store/authStore';
import toast from '../utils/toast';
import { Shield, ArrowRight, Loader2, KeyRound, LogOut } from 'lucide-react';

const ForcePasswordChange = () => {
    const [newPassword, setNewPassword] = useState('');
    const [confirmPassword, setConfirmPassword] = useState('');
    const [loading, setLoading] = useState(false);
    const navigate = useNavigate();
    const completeForcePasswordChange = useAuthStore((state) => state.completeForcePasswordChange);
    const logout = useAuthStore((state) => state.logout);

    const handleLogout = async () => {
        localStorage.removeItem('temp_force_pw_token');
        localStorage.removeItem('temp_force_pw_user');
        await logout();
        navigate('/login');
    };

    const passwordRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&#])[A-Za-z\d@$!%*?&#]{12,}$/;
    const isValid = passwordRegex.test(newPassword) && newPassword === confirmPassword;

    const handleSubmit = async (e) => {
        e.preventDefault();
        if (newPassword !== confirmPassword) {
            toast.error('Les mots de passe ne correspondent pas');
            return;
        }
        if (!passwordRegex.test(newPassword)) {
            toast.error('Le mot de passe doit contenir 12+ caractères, une majuscule, un chiffre et un symbole (@$!%*?&#)');
            return;
        }
        setLoading(true);
        try {
            const result = await completeForcePasswordChange(newPassword, confirmPassword);
            if (result.success) {
                toast.success('Mot de passe changé avec succès. Bienvenue !');
                navigate('/dashboard');
            } else {
                toast.error(result.message || 'Erreur lors du changement');
            }
        } catch {
            toast.error('Erreur de connexion au serveur');
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="min-h-screen flex items-center justify-center bg-slate-950 relative overflow-hidden font-['Outfit'] p-4">
            <div className="absolute inset-x-0 top-0 h-80 bg-gradient-to-b from-amber-500/20 to-transparent pointer-events-none" />
            <div className="absolute inset-0 bg-[radial-gradient(circle_at_top_left,_rgba(245,158,11,0.14),_transparent_18%)] pointer-events-none" />

            <div className="w-full max-w-md relative z-10 animate-fade-in">
                <div className="glass p-8 rounded-[2.5rem] shadow-[0_32px_80px_-32px_rgba(15,23,42,0.45)] border border-white/10 backdrop-blur-xl">
                    <div className="text-center mb-8">
                        <div className="w-16 h-16 bg-gradient-to-br from-amber-500 to-orange-600 rounded-3xl mx-auto flex items-center justify-center shadow-2xl shadow-amber-500/25 mb-5">
                            <KeyRound className="text-white" size={28} />
                        </div>
                        <h1 className="text-2xl font-black text-white tracking-tighter">Changer votre mot de passe</h1>
                        <p className="text-gray-400 text-sm mt-2">
                            Pour des raisons de sécurité, vous devez définir un nouveau mot de passe lors de votre première connexion.
                        </p>
                    </div>

                    <form onSubmit={handleSubmit} className="space-y-5">
                        <div className="space-y-2">
                            <label className="text-[10px] font-black text-gray-500 uppercase tracking-widest ml-1">Nouveau mot de passe</label>
                            <input
                                type="password"
                                value={newPassword}
                                onChange={(e) => setNewPassword(e.target.value)}
                                placeholder="12+ caractères, majuscule, chiffre, symbole"
                                className="w-full px-4 py-3.5 bg-white/5 border border-white/10 rounded-2xl text-white placeholder:text-gray-600 outline-none focus:border-amber-500 focus:bg-white/[0.08] transition-all text-sm font-medium"
                                required
                                minLength={12}
                                autoFocus
                            />
                        </div>

                        <div className="space-y-2">
                            <label className="text-[10px] font-black text-gray-500 uppercase tracking-widest ml-1">Confirmer le mot de passe</label>
                            <input
                                type="password"
                                value={confirmPassword}
                                onChange={(e) => setConfirmPassword(e.target.value)}
                                placeholder="Retaper le mot de passe"
                                className="w-full px-4 py-3.5 bg-white/5 border border-white/10 rounded-2xl text-white placeholder:text-gray-600 outline-none focus:border-amber-500 focus:bg-white/[0.08] transition-all text-sm font-medium"
                                required
                                minLength={12}
                            />
                        </div>

                        <div className="text-xs text-gray-500 space-y-1 px-1">
                            <p className={newPassword.length >= 12 ? 'text-emerald-400' : ''}>• 12 caractères minimum</p>
                            <p className={/[A-Z]/.test(newPassword) ? 'text-emerald-400' : ''}>• Au moins une majuscule</p>
                            <p className={/[a-z]/.test(newPassword) ? 'text-emerald-400' : ''}>• Au moins une minuscule</p>
                            <p className={/\d/.test(newPassword) ? 'text-emerald-400' : ''}>• Au moins un chiffre</p>
                            <p className={/[@$!%*?&#]/.test(newPassword) ? 'text-emerald-400' : ''}>• Au moins un symbole (@$!%*?&#)</p>
                            <p className={newPassword === confirmPassword && confirmPassword ? 'text-emerald-400' : ''}>• Les mots de passe correspondent</p>
                        </div>

                        <button
                            type="submit"
                            disabled={loading || !isValid}
                            className="w-full group bg-amber-600 hover:bg-white text-white hover:text-gray-900 py-3.5 rounded-2xl font-black text-xs uppercase tracking-[0.2em] transition-all duration-300 shadow-xl shadow-amber-500/10 flex items-center justify-center active:scale-95 disabled:opacity-50"
                        >
                            {loading ? (
                                <Loader2 className="w-5 h-5 animate-spin" />
                            ) : (
                                <span className="flex items-center">
                                    Définir mon mot de passe
                                    <ArrowRight size={16} className="ml-2 group-hover:translate-x-1 transition-transform" />
                                </span>
                            )}
                        </button>

                        <button
                            type="button"
                            onClick={handleLogout}
                            className="w-full flex items-center justify-center gap-2 py-3 text-gray-500 hover:text-red-500 text-xs font-bold uppercase tracking-widest transition-colors"
                        >
                            <LogOut size={14} />
                            Se déconnecter
                        </button>
                    </form>
                </div>
            </div>
        </div>
    );
};

export default ForcePasswordChange;

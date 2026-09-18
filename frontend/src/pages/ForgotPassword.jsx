import { useState } from 'react';
import { Link } from 'react-router-dom';
import api from '../services/api';
import toast from '../utils/toast';
import { Shield, Mail, ArrowRight, ArrowLeft, Loader2 } from 'lucide-react';

const ForgotPassword = () => {
    const [email, setEmail] = useState('');
    const [loading, setLoading] = useState(false);
    const [sent, setSent] = useState(false);

    const handleSubmit = async (e) => {
        e.preventDefault();
        setLoading(true);
        try {
            const res = await api.post('/auth/forgot-password', { email });
            toast.success(res.data.message);
            setSent(true);
        } catch (err) {
            toast.error(err.response?.data?.message || 'Erreur lors de l\'envoi');
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
                        <div className="w-16 h-16 bg-gradient-to-br from-amber-500 to-orange-600 rounded-3xl mx-auto flex items-center justify-center shadow-2xl shadow-amber-500/25 mb-5">
                            <Mail className="text-white" size={28} />
                        </div>
                        <h1 className="text-2xl font-black text-white tracking-tighter">Mot de passe oublié</h1>
                        <p className="text-gray-400 text-sm mt-2">Entrez votre adresse email pour recevoir un lien de réinitialisation.</p>
                    </div>

                    {sent ? (
                        <div className="text-center space-y-4">
                            <div className="bg-green-500/10 border border-green-500/20 rounded-2xl p-6">
                                <p className="text-green-400 font-semibold">Email envoyé !</p>
                                <p className="text-gray-400 text-sm mt-2">
                                    Vérifiez votre boîte de réception et cliquez sur le lien pour réinitialiser votre mot de passe.
                                </p>
                            </div>
                            <Link
                                to="/login"
                                className="inline-flex items-center gap-2 text-blue-400 hover:text-white text-sm font-semibold transition-colors"
                            >
                                <ArrowLeft size={16} />
                                Retour à la connexion
                            </Link>
                        </div>
                    ) : (
                        <form onSubmit={handleSubmit} className="space-y-5">
                            <div className="space-y-2">
                                <label className="text-[10px] font-black text-gray-500 uppercase tracking-widest ml-1">Email</label>
                                <div className="relative group">
                                    <Mail className="absolute left-4 top-1/2 -translate-y-1/2 text-gray-500 group-focus-within:text-amber-500 transition-colors" size={18} />
                                    <input
                                        type="email"
                                        value={email}
                                        onChange={(e) => setEmail(e.target.value)}
                                        placeholder="votre@email.com"
                                        className="w-full pl-12 pr-4 py-3.5 bg-white/5 border border-white/10 rounded-2xl text-white placeholder:text-gray-600 outline-none focus:border-amber-500 focus:bg-white/[0.08] transition-all text-sm font-medium"
                                        required
                                    />
                                </div>
                            </div>

                            <button
                                type="submit"
                                disabled={loading}
                                className="w-full group bg-amber-600 hover:bg-white text-white hover:text-gray-900 py-3.5 rounded-2xl font-black text-xs uppercase tracking-[0.2em] transition-all duration-300 shadow-xl shadow-amber-500/10 flex items-center justify-center active:scale-95 disabled:opacity-50"
                            >
                                {loading ? (
                                    <Loader2 className="w-5 h-5 animate-spin" />
                                ) : (
                                    <span className="flex items-center">
                                        Envoyer le lien
                                        <ArrowRight size={16} className="ml-2 group-hover:translate-x-1 transition-transform" />
                                    </span>
                                )}
                            </button>

                            <div className="text-center">
                                <Link
                                    to="/login"
                                    className="inline-flex items-center gap-2 text-gray-400 hover:text-white text-sm transition-colors"
                                >
                                    <ArrowLeft size={14} />
                                    Retour à la connexion
                                </Link>
                            </div>
                        </form>
                    )}
                </div>
            </div>
        </div>
    );
};

export default ForgotPassword;

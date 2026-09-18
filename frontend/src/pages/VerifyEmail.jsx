import { useState, useEffect } from 'react';
import { useSearchParams, useNavigate, Link } from 'react-router-dom';
import api from '../services/api';
import { Shield, CheckCircle, XCircle, Loader2, ArrowRight } from 'lucide-react';

const VerifyEmail = () => {
    const [searchParams] = useSearchParams();
    const navigate = useNavigate();
    const [status, setStatus] = useState('loading');
    const [message, setMessage] = useState('');

    const token = searchParams.get('token');
    const email = searchParams.get('email');

    useEffect(() => {
        const verify = async () => {
            if (!token || !email) {
                setStatus('error');
                setMessage('Lien de vérification invalide. Paramètres manquants.');
                return;
            }

            try {
                const res = await api.post('/auth/verify-email', { token, email });
                setStatus('success');
                setMessage(res.data.message);
            } catch (err) {
                setStatus('error');
                setMessage(err.response?.data?.message || 'Erreur lors de la vérification.');
            }
        };

        verify();
    }, [token, email]);

    return (
        <div className="min-h-screen flex items-center justify-center bg-slate-950 relative overflow-hidden font-['Outfit'] p-4">
            <div className="absolute inset-x-0 top-0 h-80 bg-gradient-to-b from-sky-500/20 to-transparent pointer-events-none" />
            <div className="absolute inset-0 bg-[radial-gradient(circle_at_top_left,_rgba(59,130,246,0.14),_transparent_18%)] pointer-events-none" />

            <div className="w-full max-w-md relative z-10 animate-fade-in">
                <div className="glass p-8 rounded-[2.5rem] shadow-[0_32px_80px_-32px_rgba(15,23,42,0.45)] border border-white/10 backdrop-blur-xl">
                    <div className="text-center mb-8">
                        <div className="w-16 h-16 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-3xl mx-auto flex items-center justify-center shadow-2xl shadow-blue-500/25 mb-5">
                            <Shield className="text-white" size={28} />
                        </div>
                        <h1 className="text-2xl font-black text-white tracking-tighter">Vérification d'email</h1>
                    </div>

                    {status === 'loading' && (
                        <div className="text-center space-y-4">
                            <Loader2 className="mx-auto text-blue-400 animate-spin" size={40} />
                            <p className="text-gray-400">Vérification de votre adresse email en cours...</p>
                        </div>
                    )}

                    {status === 'success' && (
                        <div className="text-center space-y-4">
                            <CheckCircle className="mx-auto text-emerald-400" size={48} />
                            <p className="text-emerald-400 font-semibold">{message}</p>
                            <p className="text-gray-400 text-sm">
                                Votre adresse email est maintenant vérifiée. Vous pouvez accéder à toutes les fonctionnalités.
                            </p>
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
                    )}

                    {status === 'error' && (
                        <div className="text-center space-y-4">
                            <XCircle className="mx-auto text-red-400" size={48} />
                            <p className="text-red-400 font-semibold">{message}</p>
                            <Link
                                to="/login"
                                className="inline-flex items-center gap-2 text-blue-400 hover:text-white text-sm font-semibold transition-colors"
                            >
                                Retour à la connexion
                            </Link>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
};

export default VerifyEmail;

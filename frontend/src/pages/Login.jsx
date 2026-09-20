import { useState } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import useAuthStore from '../store/authStore';
import toast from '../utils/toast';
import { Shield, Lock, Mail, ArrowRight, Zap, Eye, EyeOff } from 'lucide-react';

const IS_DEMO_MODE = import.meta.env.VITE_DEMO_MODE === 'true';

const QUICK_ACCOUNTS = IS_DEMO_MODE ? [
    { label: 'Admin', email: 'admin@sirh.local', color: 'from-red-500 to-rose-600' },
    { label: 'Responsable RH', email: 'hr@sirh.local', color: 'from-blue-500 to-indigo-600' },
    { label: 'Comptable', email: 'accountant@sirh.local', color: 'from-amber-500 to-orange-600' },
    { label: 'Chef d\'UFR', email: 'academic@sirh.local', color: 'from-violet-500 to-purple-600' },
    { label: 'Enseignant', email: 'abdoulaye@sirh.local', color: 'from-emerald-500 to-teal-600' },
] : [];

const Login = () => {
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [showPassword, setShowPassword] = useState(false);
    const [loading, setLoading] = useState(false);
    const login = useAuthStore((state) => state.login);
    const navigate = useNavigate();

    const handleSubmit = async (e) => {
        e.preventDefault();
        setLoading(true);
        try {
            const result = await login(email, password);
            if (result?.success) {
                if (result.requires_2fa) {
                    navigate('/2fa-verify');
                } else if (result.must_change_password) {
                    navigate('/force-password-change');
                } else {
                    toast.success('Bienvenue dans votre espace');
                    navigate('/dashboard');
                }
            } else {
                const msg = result?.message || 'Identifiants incorrects';
                if (msg.toLowerCase().includes('désactiv') || msg.toLowerCase().includes('suspend')) {
                    toast.warning('Ce compte est désactivé. Contactez l\'administrateur.');
                } else if (msg.toLowerCase().includes('trop de tentatives')) {
                    toast.warning(msg);
                } else {
                    toast.error(msg);
                }
            }
        } catch {
            toast.error('Erreur de connexion au serveur');
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="min-h-screen flex items-center justify-center bg-slate-950 relative overflow-hidden font-['Outfit'] px-4 py-8 sm:px-6 md:px-8">
            <div className="absolute inset-x-0 top-0 h-64 sm:h-80 bg-gradient-to-b from-sky-500/20 to-transparent pointer-events-none" />
            <div className="absolute inset-y-0 right-0 w-64 sm:w-80 md:w-96 bg-gradient-to-l from-indigo-600/20 to-transparent pointer-events-none" />
            <div className="absolute inset-0 bg-[radial-gradient(circle_at_top_left,_rgba(59,130,246,0.14),_transparent_18%),_radial-gradient(circle_at_bottom_right,_rgba(99,102,241,0.12),_transparent_20%)] pointer-events-none" />

            <div className="w-full max-w-xs sm:max-w-sm md:max-w-lg relative z-10 animate-fade-in">
                <div className="glass p-6 sm:p-8 md:p-10 rounded-2xl sm:rounded-3xl md:rounded-[2.5rem] shadow-[0_32px_80px_-32px_rgba(15,23,42,0.45)] border border-white/10 backdrop-blur-xl">
                    <div className="text-center mb-6 sm:mb-8">
                        <div className="w-16 sm:w-20 h-16 sm:h-20 rounded-2xl sm:rounded-3xl mx-auto overflow-hidden shadow-2xl shadow-blue-500/25 mb-4 sm:mb-5 transform transition-transform duration-500 hover:-translate-y-1">
                            <img src="/logo.jpg" alt="UDB Logo" className="w-full h-full object-cover" />
                        </div>
                        <h1 className="text-xl sm:text-2xl md:text-3xl font-black text-white tracking-tighter uppercase italic">SIRH</h1>
                        <p className="text-gray-400 text-[9px] sm:text-[10px] font-bold mt-2 uppercase tracking-[0.2em] sm:tracking-[0.3em]">Système d'Information des Ressources Humaines</p>
                    </div>

                    <form onSubmit={handleSubmit} className="space-y-4 sm:space-y-5">
                        <div className="space-y-2">
                            <label className="text-[9px] sm:text-[10px] font-black text-gray-500 uppercase tracking-widest ml-1">Email</label>
                            <div className="relative group">
                                <Mail className="absolute left-3 sm:left-4 top-1/2 -translate-y-1/2 text-gray-500 group-focus-within:text-blue-500 transition-colors" size={16} />
                                <input
                                    type="email"
                                    value={email}
                                    onChange={(e) => setEmail(e.target.value)}
                                    placeholder="admin@sirh.local"
                                    className="w-full pl-10 sm:pl-12 pr-4 py-3 sm:py-3.5 bg-white/5 border border-white/10 rounded-lg sm:rounded-2xl text-white text-sm sm:text-base placeholder:text-gray-600 outline-none focus:border-blue-500 focus:bg-white/[0.08] transition-all font-medium"
                                    required
                                />
                            </div>
                        </div>

                        <div className="space-y-2">
                            <label className="text-[9px] sm:text-[10px] font-black text-gray-500 uppercase tracking-widest ml-1">Mot de passe</label>
                            <div className="relative group">
                                <Lock className="absolute left-3 sm:left-4 top-1/2 -translate-y-1/2 text-gray-500 group-focus-within:text-blue-500 transition-colors" size={16} />
                                <input
                                    type={showPassword ? 'text' : 'password'}
                                    value={password}
                                    onChange={(e) => setPassword(e.target.value)}
                                    placeholder="••••••••"
                                    className="w-full pl-10 sm:pl-12 pr-10 sm:pr-12 py-3 sm:py-3.5 bg-white/5 border border-white/10 rounded-lg sm:rounded-2xl text-white text-sm sm:text-base placeholder:text-gray-600 outline-none focus:border-blue-500 focus:bg-white/[0.08] transition-all font-medium"
                                    required
                                />
                                <button
                                    type="button"
                                    onClick={() => setShowPassword(!showPassword)}
                                    className="absolute right-4 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-300 transition-colors"
                                >
                                    {showPassword ? <EyeOff size={18} /> : <Eye size={18} />}
                                </button>
                            </div>
                        </div>

                        <button
                            type="submit"
                            disabled={loading}
                            className="w-full group bg-blue-600 hover:bg-white text-white hover:text-gray-900 py-3 sm:py-3.5 rounded-lg sm:rounded-2xl font-black text-xs sm:text-sm uppercase tracking-[0.15em] sm:tracking-[0.2em] transition-all duration-300 shadow-xl shadow-blue-500/10 flex items-center justify-center active:scale-95 disabled:opacity-50 h-12 sm:h-auto"
                        >
                            {loading ? (
                                <div className="w-5 h-5 border-2 border-current border-t-transparent rounded-full animate-spin"></div>
                            ) : (
                                <span className="flex items-center">
                                    Accéder au portail
                                    <ArrowRight size={14} className="ml-2 group-hover:translate-x-1 transition-transform" />
                                </span>
                            )}
                        </button>
                    </form>

                    <div className="mt-4 sm:mt-5 text-center">
                        <Link to="/forgot-password" className="text-amber-400 hover:text-white font-bold transition-colors text-xs sm:text-sm">
                            Mot de passe oublié ?
                        </Link>
                    </div>

                    {IS_DEMO_MODE && QUICK_ACCOUNTS.length > 0 && (
                    <div className="mt-5 sm:mt-6 pt-5 sm:pt-6 border-t border-white/10">
                        <div className="flex items-center gap-2 justify-center mb-3 sm:mb-4">
                            <Zap size={12} className="text-yellow-400" />
                            <p className="text-[9px] sm:text-[10px] font-black text-gray-500 uppercase tracking-widest">Accès rapide (Mode Démo)</p>
                        </div>
                        <div className="grid grid-cols-3 sm:grid-cols-5 gap-2">
                            {QUICK_ACCOUNTS.map((account) => (
                                <button
                                    key={account.email}
                                    type="button"
                                    onClick={() => { setEmail(account.email); setPassword(''); toast.info('Saisissez le mot de passe du compte ' + account.label); }}
                                    className={`group flex flex-col items-center gap-1.5 p-2 rounded-xl bg-gradient-to-br ${account.color} text-white text-[9px] font-bold uppercase tracking-wider hover:scale-105 hover:shadow-lg transition-all duration-200 active:scale-95 cursor-pointer`}
                                >
                                    <span className="w-6 h-6 rounded-lg bg-white/20 flex items-center justify-center text-[10px] font-black">
                                        {account.label.charAt(0)}
                                    </span>
                                    <span>{account.label}</span>
                                </button>
                            ))}
                        </div>
                    </div>
                    )}

                    <div className="mt-5 text-center">
                        <p className="text-[10px] font-black text-gray-500 uppercase tracking-widest">
                            &copy; {new Date().getFullYear()} Université Dakar Bourguiba
                        </p>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default Login;

import { useState, useEffect } from 'react';
import { useSearchParams, useNavigate } from 'react-router-dom';
import api from '../services/api';
import toast from '../utils/toast';
import { Eye, EyeOff, CheckCircle, X } from 'lucide-react';

export default function ActivationPage() {
    const [searchParams] = useSearchParams();
    const navigate = useNavigate();
    const token = searchParams.get('token');
    const email = searchParams.get('email');

    const [password, setPassword] = useState('');
    const [passwordConfirm, setPasswordConfirm] = useState('');
    const [showPassword, setShowPassword] = useState(false);
    const [showPasswordConfirm, setShowPasswordConfirm] = useState(false);
    const [loading, setLoading] = useState(false);

    const passwordRules = {
        length: password.length >= 12,
        uppercase: /[A-Z]/.test(password),
        lowercase: /[a-z]/.test(password),
        number: /\d/.test(password),
        special: /[@$!%*?&#]/.test(password),
    };

    const allRulesMet = Object.values(passwordRules).every(rule => rule);

    const handleActivation = async (e) => {
        e.preventDefault();

        if (!token || !email) {
            toast.error('Lien d\'activation invalide');
            return;
        }

        if (password !== passwordConfirm) {
            toast.error('Les mots de passe ne correspondent pas');
            return;
        }

        if (!allRulesMet) {
            toast.error('Votre mot de passe ne respecte pas les critères');
            return;
        }

        setLoading(true);
        try {
            const response = await api.post('/auth/activation', {
                token,
                email,
                password,
                password_confirmation: passwordConfirm,
            });

            toast.success(response.data.message);
            setTimeout(() => navigate('/login?activated=true'), 2000);
        } catch (error) {
            toast.error(error.response?.data?.message || 'Erreur lors de l\'activation');
        } finally {
            setLoading(false);
        }
    };

    if (!token || !email) {
        return (
            <div className="min-h-screen bg-gradient-to-br from-slate-900 to-slate-800 flex items-center justify-center p-4">
                <div className="max-w-md w-full bg-white rounded-2xl shadow-xl p-8">
                    <h1 className="text-2xl font-bold text-red-600 mb-4">Lien invalide</h1>
                    <p className="text-slate-600 mb-6">Le lien d'activation n'est pas valide.</p>
                    <a href="/login" className="inline-block px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
                        Retour à la connexion
                    </a>
                </div>
            </div>
        );
    }

    return (
        <div className="min-h-screen bg-gradient-to-br from-slate-900 to-slate-800 flex items-center justify-center p-4">
            <div className="max-w-md w-full bg-white rounded-2xl shadow-xl p-8">
                <h1 className="text-3xl font-bold text-slate-900 mb-2">Activer mon compte</h1>
                <p className="text-slate-500 mb-6">Définissez un mot de passe sécurisé pour accéder au SIRH UDB</p>

                <form onSubmit={handleActivation} className="space-y-6">
                    <div>
                        <label className="block text-sm font-semibold text-slate-700 mb-2">
                            Nouveau mot de passe
                        </label>
                        <div className="relative">
                            <input
                                type={showPassword ? 'text' : 'password'}
                                value={password}
                                onChange={(e) => setPassword(e.target.value)}
                                placeholder="••••••••••••"
                                className="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                disabled={loading}
                            />
                            <button
                                type="button"
                                onClick={() => setShowPassword(!showPassword)}
                                className="absolute right-3 top-2.5 text-slate-400 hover:text-slate-600"
                            >
                                {showPassword ? <EyeOff size={20} /> : <Eye size={20} />}
                            </button>
                        </div>
                    </div>

                    <div>
                        <label className="block text-sm font-semibold text-slate-700 mb-2">
                            Confirmer le mot de passe
                        </label>
                        <div className="relative">
                            <input
                                type={showPasswordConfirm ? 'text' : 'password'}
                                value={passwordConfirm}
                                onChange={(e) => setPasswordConfirm(e.target.value)}
                                placeholder="••••••••••••"
                                className="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                disabled={loading}
                            />
                            <button
                                type="button"
                                onClick={() => setShowPasswordConfirm(!showPasswordConfirm)}
                                className="absolute right-3 top-2.5 text-slate-400 hover:text-slate-600"
                            >
                                {showPasswordConfirm ? <EyeOff size={20} /> : <Eye size={20} />}
                            </button>
                        </div>
                    </div>

                    <div className="space-y-2">
                        <p className="text-xs font-semibold text-slate-600 uppercase tracking-wider">
                            Critères de sécurité
                        </p>
                        <div className="space-y-1 text-sm">
                            <Rule met={passwordRules.length} text="Au minimum 12 caractères" />
                            <Rule met={passwordRules.uppercase} text="Au moins une majuscule (A-Z)" />
                            <Rule met={passwordRules.lowercase} text="Au moins une minuscule (a-z)" />
                            <Rule met={passwordRules.number} text="Au moins un chiffre (0-9)" />
                            <Rule met={passwordRules.special} text="Au moins un symbole (@$!%*?&#)" />
                        </div>
                    </div>

                    <button
                        type="submit"
                        disabled={loading || !allRulesMet || !passwordConfirm}
                        className="w-full py-2 px-4 bg-indigo-600 text-white font-semibold rounded-lg hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed transition-all"
                    >
                        {loading ? 'Activation en cours...' : 'Activer mon compte'}
                    </button>
                </form>

                <p className="text-xs text-slate-500 text-center mt-6">
                    Ce lien d'activation expire dans 48 heures et ne peut être utilisé qu'une seule fois.
                </p>
            </div>
        </div>
    );
}

function Rule({ met, text }) {
    return (
        <div className={`flex items-center gap-2 px-3 py-1 rounded ${met ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-50 text-slate-400'}`}>
            {met ? <CheckCircle size={16} /> : <X size={16} />}
            <span>{text}</span>
        </div>
    );
}

import { useState } from 'react';
import { Link } from 'react-router-dom';
import api from '../services/api';
import toast from '../utils/toast';
import { Shield, Smartphone, ArrowRight, ArrowLeft, Loader2, CheckCircle, Copy, AlertTriangle } from 'lucide-react';

const TwoFactorSetup = () => {
    const [step, setStep] = useState('intro');
    const [secret, setSecret] = useState('');
    const [qrCodeUrl, setQrCodeUrl] = useState('');
    const [code, setCode] = useState('');
    const [recoveryCodes, setRecoveryCodes] = useState([]);
    const [loading, setLoading] = useState(false);
    const [copied, setCopied] = useState(false);

    const generateSecret = async () => {
        setLoading(true);
        try {
            const res = await api.get('/auth/2fa/secret');
            setSecret(res.data.secret);
            setQrCodeUrl(res.data.qr_code_url);
            setStep('scan');
        } catch (err) {
            toast.error(err.response?.data?.message || 'Erreur lors de la génération du secret');
        } finally {
            setLoading(false);
        }
    };

    const enable2FA = async (e) => {
        e.preventDefault();
        if (code.length !== 6) {
            toast.error('Le code doit contenir 6 chiffres');
            return;
        }
        setLoading(true);
        try {
            const res = await api.post('/auth/2fa/enable', { code });
            setRecoveryCodes(res.data.recovery_codes);
            setStep('done');
            toast.success('Double authentification activée !');
        } catch (err) {
            toast.error(err.response?.data?.message || 'Code invalide');
        } finally {
            setLoading(false);
        }
    };

    const copyRecoveryCodes = () => {
        navigator.clipboard.writeText(recoveryCodes.join('\n'));
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    return (
        <div className="max-w-2xl mx-auto space-y-6">
            <div className="flex items-center gap-3 mb-6">
                <div className="w-12 h-12 bg-gradient-to-br from-emerald-500 to-teal-600 rounded-2xl flex items-center justify-center">
                    <Shield className="text-white" size={24} />
                </div>
                <div>
                    <h1 className="text-2xl font-black text-gray-900">Double Authentification (2FA)</h1>
                    <p className="text-gray-500 text-sm">Sécurisez votre compte avec un code de votre application d'authentification.</p>
                </div>
            </div>

            {step === 'intro' && (
                <div className="bg-white rounded-2xl border border-gray-100 p-8 text-center space-y-4">
                    <Shield className="mx-auto text-emerald-500" size={48} />
                    <h2 className="text-xl font-bold text-gray-900">Activer la double authentification</h2>
                    <p className="text-gray-500 max-w-md mx-auto">
                        La 2FA ajoute une couche de sécurité supplémentaire. Lors de la connexion, vous devrez entrer un code généré par votre application d'authentification.
                    </p>
                    <p className="text-gray-400 text-sm">
                        Applications recommandées : Google Authenticator, Authy, Microsoft Authenticator
                    </p>
                    <button
                        onClick={generateSecret}
                        disabled={loading}
                        className="px-8 py-3 bg-emerald-600 text-white rounded-xl font-bold hover:bg-emerald-700 transition-colors disabled:opacity-50"
                    >
                        {loading ? <Loader2 className="w-5 h-5 animate-spin mx-auto" /> : 'Commencer l\'activation'}
                    </button>
                </div>
            )}

            {step === 'scan' && (
                <div className="bg-white rounded-2xl border border-gray-100 p-8 space-y-6">
                    <div>
                        <h3 className="font-bold text-gray-900 mb-2">1. Scannez le QR Code</h3>
                        <p className="text-gray-500 text-sm">
                            Ouvrez votre application d'authentification et scannez ce QR code.
                        </p>
                    </div>

                    <div className="flex justify-center">
                        <div className="bg-white p-4 rounded-2xl border-2 border-gray-100">
                            <img
                                src={`https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=${encodeURIComponent(qrCodeUrl)}`}
                                alt="QR Code 2FA"
                                className="w-48 h-48"
                            />
                        </div>
                    </div>

                    <div>
                        <p className="text-gray-400 text-xs mb-2">Ou entrez manuellement ce secret :</p>
                        <div className="flex items-center gap-2 bg-gray-50 rounded-xl p-3">
                            <code className="flex-1 text-sm font-mono text-gray-700 break-all">{secret}</code>
                            <button
                                onClick={() => { navigator.clipboard.writeText(secret); toast.success('Secret copié !'); }}
                                className="text-gray-400 hover:text-gray-600"
                            >
                                <Copy size={16} />
                            </button>
                        </div>
                    </div>

                    <div>
                        <h3 className="font-bold text-gray-900 mb-2">2. Entrez le code de vérification</h3>
                        <p className="text-gray-500 text-sm mb-4">
                            Entrez le code à 6 chiffres affiché dans votre application.
                        </p>
                        <form onSubmit={enable2FA} className="flex gap-3">
                            <input
                                type="text"
                                value={code}
                                onChange={(e) => setCode(e.target.value.replace(/\D/g, '').slice(0, 6))}
                                placeholder="000000"
                                className="flex-1 text-center text-2xl font-mono tracking-[0.5em] py-3 px-4 bg-gray-50 border border-gray-200 rounded-xl focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 outline-none"
                                maxLength={6}
                                required
                            />
                            <button
                                type="submit"
                                disabled={loading || code.length !== 6}
                                className="px-6 py-3 bg-emerald-600 text-white rounded-xl font-bold hover:bg-emerald-700 transition-colors disabled:opacity-50"
                            >
                                {loading ? <Loader2 className="w-5 h-5 animate-spin" /> : 'Vérifier'}
                            </button>
                        </form>
                    </div>
                </div>
            )}

            {step === 'done' && (
                <div className="bg-white rounded-2xl border border-gray-100 p-8 space-y-6">
                    <div className="text-center">
                        <CheckCircle className="mx-auto text-emerald-500 mb-3" size={48} />
                        <h3 className="text-xl font-bold text-gray-900">2FA Activée !</h3>
                    </div>

                    <div className="bg-amber-50 border border-amber-200 rounded-xl p-4">
                        <div className="flex items-start gap-3">
                            <AlertTriangle className="text-amber-500 mt-0.5" size={20} />
                            <div>
                                <p className="font-semibold text-amber-800">Codes de récupération</p>
                                <p className="text-amber-700 text-sm mt-1">
                                    Sauvegardez ces codes en lieu sûr. Ils vous permettront d'accéder à votre compte si vous perdez votre application d'authentification.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div className="bg-gray-50 rounded-xl p-4">
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-xs font-bold text-gray-500 uppercase tracking-wider">Vos codes</span>
                            <button
                                onClick={copyRecoveryCodes}
                                className="text-xs text-emerald-600 hover:text-emerald-700 font-semibold"
                            >
                                {copied ? 'Copié !' : 'Tout copier'}
                            </button>
                        </div>
                        <div className="grid grid-cols-2 gap-2">
                            {recoveryCodes.map((rc, i) => (
                                <code key={i} className="text-sm font-mono text-gray-700 bg-white px-3 py-1.5 rounded-lg border">{rc}</code>
                            ))}
                        </div>
                    </div>

                    <Link
                        to="/profile"
                        className="block w-full text-center py-3 bg-gray-900 text-white rounded-xl font-bold hover:bg-gray-800 transition-colors"
                    >
                        Retour au profil
                    </Link>
                </div>
            )}
        </div>
    );
};

export default TwoFactorSetup;

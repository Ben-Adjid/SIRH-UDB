import { useState, useEffect, useCallback } from 'react';
import { Link } from 'react-router-dom';
import api from '../services/api';
import toast from '../utils/toast';
import { Smartphone, ArrowRight, ArrowLeft, Loader2, CheckCircle, Send } from 'lucide-react';

const PhoneVerification = () => {
    const [phone, setPhone] = useState('');
    const [code, setCode] = useState('');
    const [step, setStep] = useState('phone');
    const [loading, setLoading] = useState(false);
    const [maskedPhone, setMaskedPhone] = useState('');
    const [cooldown, setCooldown] = useState(0);

    useEffect(() => {
        if (cooldown <= 0) return;
        const timer = setTimeout(() => setCooldown(prev => prev - 1), 1000);
        return () => clearTimeout(timer);
    }, [cooldown]);

    const sendCode = useCallback(async (e) => {
        if (e) e.preventDefault();
        if (!phone || phone.length < 9) {
            toast.error('Entrez un numéro de téléphone valide');
            return;
        }
        if (cooldown > 0) return;
        setLoading(true);
        try {
            const res = await api.post('/auth/phone/send-verification', { phone });
            setMaskedPhone(res.data.phone_masked);
            setStep('code');
            setCooldown(60);
            toast.success('Code de vérification envoyé par SMS');
        } catch (err) {
            toast.error(err.response?.data?.message || 'Erreur lors de l\'envoi du SMS');
        } finally {
            setLoading(false);
        }
    }, [phone, cooldown]);

    const verifyCode = async (e) => {
        e.preventDefault();
        if (code.length !== 6) {
            toast.error('Le code doit contenir 6 chiffres');
            return;
        }
        setLoading(true);
        try {
            await api.post('/auth/phone/verify', { code });
            toast.success('Numéro de téléphone vérifié !');
            setStep('done');
        } catch (err) {
            toast.error(err.response?.data?.message || 'Code invalide');
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="max-w-md mx-auto space-y-6">
            <div className="flex items-center gap-3 mb-6">
                <div className="w-12 h-12 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-2xl flex items-center justify-center">
                    <Smartphone className="text-white" size={24} />
                </div>
                <div>
                    <h1 className="text-2xl font-black text-gray-900">Vérification du téléphone</h1>
                    <p className="text-gray-500 text-sm">Vérifiez votre numéro pour la sécurité de votre compte.</p>
                </div>
            </div>

            {step === 'phone' && (
                <div className="bg-white rounded-2xl border border-gray-100 p-8 space-y-6">
                    <div>
                        <h3 className="font-bold text-gray-900 mb-2">Votre numéro de téléphone</h3>
                        <p className="text-gray-500 text-sm">
                            Nous vous enverrons un code de vérification par SMS. Ce numéro sera utilisé pour la récupération de compte.
                        </p>
                    </div>

                    <form onSubmit={sendCode} className="space-y-4">
                        <div>
                            <label className="text-xs font-bold text-gray-500 uppercase tracking-wider">Numéro de téléphone</label>
                            <div className="flex items-center gap-2 mt-1">
                                <span className="text-sm font-semibold text-gray-500 bg-gray-50 px-3 py-3 rounded-xl border">+221</span>
                                <input
                                    type="tel"
                                    value={phone}
                                    onChange={(e) => setPhone(e.target.value.replace(/\D/g, '').slice(0, 9))}
                                    placeholder="77 123 45 67"
                                    className="flex-1 px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none text-sm"
                                    required
                                    pattern="[0-9]{9}"
                                />
                            </div>
                            <p className="text-xs text-gray-400 mt-1">9 chiffres sans l'indicatif pays</p>
                        </div>

                        <button
                            type="submit"
                            disabled={loading}
                            className="w-full py-3 bg-blue-600 text-white rounded-xl font-bold hover:bg-blue-700 transition-colors disabled:opacity-50 flex items-center justify-center gap-2"
                        >
                            {loading ? (
                                <Loader2 className="w-5 h-5 animate-spin" />
                            ) : (
                                <>
                                    <Send size={18} />
                                    Envoyer le code SMS
                                </>
                            )}
                        </button>
                    </form>
                </div>
            )}

            {step === 'code' && (
                <div className="bg-white rounded-2xl border border-gray-100 p-8 space-y-6">
                    <div className="text-center">
                        <Smartphone className="mx-auto text-blue-500 mb-3" size={40} />
                        <h3 className="font-bold text-gray-900">Code envoyé !</h3>
                        <p className="text-gray-500 text-sm mt-1">
                            Un code à 6 chiffres a été envoyé au <strong>{maskedPhone}</strong>
                        </p>
                    </div>

                    <form onSubmit={verifyCode} className="space-y-4">
                        <div>
                            <input
                                type="text"
                                value={code}
                                onChange={(e) => setCode(e.target.value.replace(/\D/g, '').slice(0, 6))}
                                placeholder="000000"
                                className="w-full text-center text-2xl font-mono tracking-[0.5em] py-3 px-4 bg-gray-50 border border-gray-200 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none"
                                maxLength={6}
                                required
                                autoFocus
                            />
                        </div>

                        <button
                            type="submit"
                            disabled={loading || code.length !== 6}
                            className="w-full py-3 bg-blue-600 text-white rounded-xl font-bold hover:bg-blue-700 transition-colors disabled:opacity-50 flex items-center justify-center gap-2"
                        >
                            {loading ? (
                                <Loader2 className="w-5 h-5 animate-spin" />
                            ) : (
                                <>
                                    Vérifier le code
                                    <ArrowRight size={18} />
                                </>
                            )}
                        </button>

                        <button
                            type="button"
                            onClick={() => { setStep('phone'); setCode(''); }}
                            className="w-full text-center text-sm text-gray-500 hover:text-gray-700"
                        >
                            Changer de numéro
                        </button>

                        <button
                            type="button"
                            onClick={sendCode}
                            disabled={cooldown > 0 || loading}
                            className="w-full text-center text-sm text-blue-600 hover:text-blue-800 font-semibold disabled:text-gray-400 disabled:cursor-not-allowed"
                        >
                            {cooldown > 0 ? `Renvoyer le code dans ${cooldown}s` : 'Renvoyer le code'}
                        </button>
                    </form>
                </div>
            )}

            {step === 'done' && (
                <div className="bg-white rounded-2xl border border-gray-100 p-8 text-center space-y-4">
                    <CheckCircle className="mx-auto text-emerald-500" size={48} />
                    <h3 className="text-xl font-bold text-gray-900">Téléphone vérifié !</h3>
                    <p className="text-gray-500">
                        Votre numéro de téléphone a été vérifié avec succès.
                    </p>
                    <Link
                        to="/profile"
                        className="block w-full py-3 bg-gray-900 text-white rounded-xl font-bold hover:bg-gray-800 transition-colors"
                    >
                        Retour au profil
                    </Link>
                </div>
            )}

            <Link
                to="/profile"
                className="flex items-center justify-center gap-2 text-sm text-gray-500 hover:text-gray-700"
            >
                <ArrowLeft size={14} />
                Retour au profil
            </Link>
        </div>
    );
};

export default PhoneVerification;

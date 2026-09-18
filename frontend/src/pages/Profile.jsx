import { useState } from 'react';
import useAuthStore from '../store/authStore';
import { User, Mail, Phone, Save, ArrowLeft, Lock, Shield, Smartphone, CheckCircle, Send, Loader } from 'lucide-react';
import toast from '../utils/toast';
import { useNavigate, Link } from 'react-router-dom';
import api from '../services/api';
import FormModal from '../components/FormModal';
import { getUserRoles, getRoleLabel } from '../utils/roles';

const Profile = () => {
    const user = useAuthStore((state) => state.user);
    const updateProfile = useAuthStore((state) => state.updateProfile);
    const navigate = useNavigate();

    const [formData, setFormData] = useState({
        name: user?.name ?? '',
        email: user?.email ?? '',
        phone: user?.phone ?? '',
        attendance_pin: '',
    });

    const [lastUser, setLastUser] = useState(user);
    const [currentAttendancePin, setCurrentAttendancePin] = useState(user?.attendance_pin ?? '');
    if (user !== lastUser) {
        setLastUser(user);
        setCurrentAttendancePin(user?.attendance_pin ?? '');
        setFormData({
            name: user?.name ?? '',
            email: user?.email ?? '',
            phone: user?.phone ?? '',
            attendance_pin: '',
        });
    }

    const [showPasswordModal, setShowPasswordModal] = useState(false);
    const [passwordData, setPasswordData] = useState({
        current_password: '',
        new_password: '',
        new_password_confirmation: '',
    });
    const [loading, setLoading] = useState(false);

    const handleSubmit = async (e) => {
        e.preventDefault();
        setLoading(true);
        try {
            const payload = { ...formData };
            if (!payload.attendance_pin) delete payload.attendance_pin;
            const result = await updateProfile(payload);
            if (result.success) {
                const updatedUser = result.user;
                setFormData({
                    name: updatedUser?.name || formData.name,
                    email: updatedUser?.email || formData.email,
                    phone: updatedUser?.phone ?? formData.phone,
                    attendance_pin: '',
                });
                setCurrentAttendancePin(updatedUser?.attendance_pin ?? currentAttendancePin);
                toast.success('Profil mis à jour');
            } else {
                toast.error(result.message);
            }
        } catch {
            toast.error('Erreur lors de la mise à jour du profil');
        } finally {
            setLoading(false);
        }
    };

    const handlePasswordChange = async (e) => {
        e.preventDefault();
        if (passwordData.new_password !== passwordData.new_password_confirmation) {
            toast.error('Les mots de passe ne correspondent pas');
            return;
        }
        const passwordRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&#])[A-Za-z\d@$!%*?&#]{12,}$/;
        if (!passwordRegex.test(passwordData.new_password)) {
            toast.error('Le mot de passe doit contenir 12+ caractères, une majuscule, une minuscule, un chiffre et un symbole (@$!%*?&#)');
            return;
        }
        try {
            await api.post('/auth/change-password', passwordData);
            toast.success('Mot de passe changé');
            setShowPasswordModal(false);
            setPasswordData({ current_password: '', new_password: '', new_password_confirmation: '' });
        } catch (error) {
            toast.error(error.response?.data?.message || 'Erreur');
        }
    };

    const handleResendVerification = async () => {
        try {
            await api.post('/auth/send-verification-email');
            toast.success('Email de vérification envoyé. Vérifiez votre boîte de réception.');
        } catch (error) {
            toast.error(error.response?.data?.message || 'Erreur lors de l\'envoi');
        }
    };

    const userRoles = getUserRoles(user);
    const roleLabel = getRoleLabel(userRoles[0]);

    if (!user) return (
        <div className="flex flex-col items-center justify-center min-h-[400px] space-y-4">
            <div className="w-12 h-12 rounded-2xl bg-gray-100 flex items-center justify-center">
                <Loader size={24} className="text-gray-400 animate-spin" />
            </div>
            <p className="text-xs font-black text-gray-400 uppercase tracking-widest">Chargement du profil...</p>
        </div>
    );

    return (
        <div className="max-w-2xl mx-auto space-y-8 animate-fade-in">
            <div className="flex items-center space-x-4">
                <button type="button" onClick={() => navigate(-1)} className="p-3 rounded-xl hover:bg-gray-100 transition-colors">
                    <ArrowLeft size={20} />
                </button>
                <div>
                    <h1 className="text-3xl font-black text-gray-900 tracking-tighter">Mon Profil</h1>
                    <p className="text-gray-500 font-medium">Informations personnelles et sécurité</p>
                </div>
            </div>

            <div className="glass p-8 rounded-[2rem] border border-white/60 shadow-2xl shadow-slate-200/30">
                <div className="flex items-center space-x-6 mb-10 pb-8 border-b border-white/20">
                    <div className="w-20 h-20 rounded-3xl bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center font-black text-white text-3xl shadow-xl">
                        {user?.name?.charAt(0) || 'U'}
                    </div>
                    <div>
                        <h2 className="text-2xl font-black text-gray-900 tracking-tighter">{user?.name}</h2>
                        <p className="text-sm font-bold text-blue-600 uppercase tracking-widest mt-1">{roleLabel}</p>
                        <p className="text-xs text-gray-400 mt-1">{user?.email}</p>
                    </div>
                </div>

                <form onSubmit={handleSubmit} className="space-y-6">
                    <div className="form-group">
                        <label className="form-label">Nom complet</label>
                        <div className="relative">
                            <User className="absolute left-4 top-1/2 -translate-y-1/2 text-gray-300" size={16} />
                            <input required type="text" className="input-field icon"
                                value={formData.name}
                                onChange={e => setFormData({...formData, name: e.target.value})} />
                        </div>
                    </div>

                    <div className="form-group">
                        <label className="form-label">Email</label>
                        <div className="relative">
                            <Mail className="absolute left-4 top-1/2 -translate-y-1/2 text-gray-300" size={16} />
                            <input required type="email" className="input-field icon"
                                value={formData.email}
                                onChange={e => setFormData({...formData, email: e.target.value})} />
                        </div>
                    </div>

                    <div className="form-group">
                        <label className="form-label">Téléphone</label>
                        <div className="relative">
                            <Phone className="absolute left-4 top-1/2 -translate-y-1/2 text-gray-300" size={16} />
                            <input type="text" className="input-field icon" placeholder="+221 XX XXX XX XX"
                                value={formData.phone}
                                onChange={e => setFormData({...formData, phone: e.target.value})} />
                        </div>
                    </div>

                    <div className="form-group">
                        <label className="form-label">PIN de pointage administratif</label>
                        <input type="password" inputMode="numeric" pattern="[0-9]*" maxLength={6} minLength={4} className="input-field" placeholder="4 à 6 chiffres" value={formData.attendance_pin} onChange={e => setFormData({...formData, attendance_pin: e.target.value.replace(/[^0-9]/g, '')})} />
                        <p className="text-xs text-gray-400 mt-1">Laissez vide pour conserver votre PIN actuel.</p>
                    </div>

                    <div className="flex items-center space-x-4 pt-4">
                        <button type="submit" disabled={loading} className="bg-gray-900 text-white px-8 py-4 rounded-2xl flex items-center space-x-3 hover:bg-blue-600 transition-all active:scale-95 disabled:opacity-50">
                            <Save size={18} />
                            <span className="font-black text-xs uppercase tracking-widest">{loading ? 'Enregistrement...' : 'Enregistrer'}</span>
                        </button>
                        <button type="button" onClick={() => setShowPasswordModal(true)}
                            className="bg-amber-50 text-amber-600 px-8 py-4 rounded-2xl flex items-center space-x-3 hover:bg-amber-600 hover:text-white transition-all active:scale-95">
                            <Lock size={18} />
                            <span className="font-black text-xs uppercase tracking-widest">Changer mot de passe</span>
                        </button>
                    </div>
                </form>
            </div>

            <FormModal
                isOpen={showPasswordModal}
                onClose={() => setShowPasswordModal(false)}
                onSubmit={handlePasswordChange}
                title="Changer le mot de passe"
                subtitle="Sécurité du compte"
                submitText="Confirmer"
            >
                <div className="space-y-5">
                    <div className="form-group">
                        <label className="form-label">Mot de passe actuel</label>
                        <input required type="password" className="input-field"
                            value={passwordData.current_password}
                            onChange={e => setPasswordData({...passwordData, current_password: e.target.value})} />
                    </div>
                    <div className="form-group">
                        <label className="form-label">Nouveau mot de passe</label>
                        <input required type="password" className="input-field" minLength={12}
                            value={passwordData.new_password}
                            onChange={e => setPasswordData({...passwordData, new_password: e.target.value})} />
                    </div>
                    <div className="form-group">
                        <label className="form-label">Confirmer le mot de passe</label>
                        <input required type="password" className="input-field" minLength={12}
                            value={passwordData.new_password_confirmation}
                            onChange={e => setPasswordData({...passwordData, new_password_confirmation: e.target.value})} />
                    </div>
                </div>
            </FormModal>

            {/* Section Sécurité */}
            <div className="glass p-8 rounded-[2rem] border border-white/60 shadow-2xl shadow-slate-200/30">
                <div className="flex items-center space-x-3 mb-6">
                    <Shield className="text-emerald-500" size={24} />
                    <h2 className="text-xl font-black text-gray-900 tracking-tighter">Sécurité du compte</h2>
                </div>

                <div className="space-y-4">
                    {/* Code PIN de pointage (enseignants uniquement) */}
                    {user?.teacher?.pin_code && (
                        <div className="flex items-center justify-between p-4 bg-indigo-50 rounded-xl border border-indigo-100">
                            <div className="flex items-center gap-3">
                                <div className="w-10 h-10 rounded-xl bg-indigo-100 flex items-center justify-center">
                                    <Shield size={20} className="text-indigo-600" />
                                </div>
                                <div>
                                    <p className="font-bold text-gray-900 text-sm">Code PIN de pointage</p>
                                    <p className="text-xs text-gray-500">Saisissez ce code pour pointer votre arrivée</p>
                                </div>
                            </div>
                            <span className="text-2xl font-black font-mono tracking-widest text-indigo-700">
                                {user.teacher.pin_code}
                            </span>
                        </div>
                    )}

                    {!user?.teacher && currentAttendancePin && (
                        <div className="flex items-center justify-between p-4 bg-indigo-50 rounded-xl border border-indigo-100">
                            <div className="flex items-center gap-3">
                                <div className="w-10 h-10 rounded-xl bg-indigo-100 flex items-center justify-center"><Shield size={20} className="text-indigo-600" /></div>
                                <div><p className="font-bold text-gray-900 text-sm">Code PIN de pointage</p><p className="text-xs text-gray-500">Utilisez ce code pour pointer votre arrivée</p></div>
                            </div>
                            <span className="text-2xl font-black font-mono tracking-widest text-indigo-700">{currentAttendancePin}</span>
                        </div>
                    )}

                    {/* 2FA */}
                    <div className="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
                        <div className="flex items-center gap-3">
                            <div className={`w-10 h-10 rounded-xl flex items-center justify-center ${user?.two_factor_enabled_at ? 'bg-emerald-100' : 'bg-gray-200'}`}>
                                <Shield size={20} className={user?.two_factor_enabled_at ? 'text-emerald-600' : 'text-gray-400'} />
                            </div>
                            <div>
                                <p className="font-bold text-gray-900 text-sm">Double Authentification (2FA)</p>
                                <p className="text-xs text-gray-500">
                                    {user?.two_factor_enabled_at ? 'Activée' : 'Non activée'}
                                </p>
                            </div>
                        </div>
                        <div className="flex items-center gap-2">
                            {user?.two_factor_enabled_at ? (
                                <span className="flex items-center gap-1 text-emerald-600 text-xs font-bold">
                                    <CheckCircle size={14} /> Activée
                                </span>
                            ) : (
                                <Link
                                    to="/2fa-setup"
                                    className="px-4 py-2 bg-emerald-600 text-white text-xs font-bold rounded-lg hover:bg-emerald-700 transition-colors"
                                >
                                    Activer
                                </Link>
                            )}
                        </div>
                    </div>

                    {/* Email vérifié */}
                    <div className="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
                        <div className="flex items-center gap-3">
                            <div className={`w-10 h-10 rounded-xl flex items-center justify-center ${user?.email_verified_at ? 'bg-blue-100' : 'bg-amber-100'}`}>
                                <Mail size={20} className={user?.email_verified_at ? 'text-blue-600' : 'text-amber-600'} />
                            </div>
                            <div>
                                <p className="font-bold text-gray-900 text-sm">Email vérifié</p>
                                <p className="text-xs text-gray-500">{user?.email}</p>
                            </div>
                        </div>
                        {user?.email_verified_at ? (
                            <span className="flex items-center gap-1 text-blue-600 text-xs font-bold">
                                <CheckCircle size={14} /> Vérifié
                            </span>
                        ) : (
                            <button
                                onClick={handleResendVerification}
                                className="flex items-center gap-1 px-3 py-2 bg-amber-500 text-white text-xs font-bold rounded-lg hover:bg-amber-600 transition-colors"
                            >
                                <Send size={12} />
                                Renvoyer
                            </button>
                        )}
                    </div>

                    {/* Téléphone vérifié */}
                    <div className="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
                        <div className="flex items-center gap-3">
                            <div className={`w-10 h-10 rounded-xl flex items-center justify-center ${user?.phone_verified_at ? 'bg-indigo-100' : 'bg-gray-200'}`}>
                                <Smartphone size={20} className={user?.phone_verified_at ? 'text-indigo-600' : 'text-gray-400'} />
                            </div>
                            <div>
                                <p className="font-bold text-gray-900 text-sm">Téléphone vérifié</p>
                                <p className="text-xs text-gray-500">
                                    {user?.phone || 'Non renseigné'}
                                </p>
                            </div>
                        </div>
                        {user?.phone_verified_at ? (
                            <span className="flex items-center gap-1 text-indigo-600 text-xs font-bold">
                                <CheckCircle size={14} /> Vérifié
                            </span>
                        ) : (
                            <Link
                                to="/phone-verify"
                                className="px-4 py-2 bg-indigo-600 text-white text-xs font-bold rounded-lg hover:bg-indigo-700 transition-colors"
                            >
                                Vérifier
                            </Link>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
};

export default Profile;

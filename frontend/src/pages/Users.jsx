import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import api from '../services/api';
import { User, Shield, ShieldCheck, UserCheck, UserX, Search, Plus, Trash2, Mail, Phone } from 'lucide-react';
import toast from '../utils/toast';
import { getRoleLabel, hasAnyRole } from '../utils/roles';
import ConfirmModal from '../components/ConfirmModal';
import FormModal from '../components/FormModal';
import Pagination from '../components/Pagination';
import useAuthStore from '../store/authStore';

const ROLES = [
    { value: 'teacher', label: 'Enseignant', desc: 'Emploi du temps, cours, devoirs, pointage' },
    { value: 'hr_manager', label: 'Responsable Administratif', desc: 'Enseignants, contrats, pointage, absences, rapports' },
    { value: 'secretary', label: 'Secretaire Administratif', desc: 'Memes acces que le Responsable Administratif' },
    { value: 'academic_manager', label: "Chef d'UFR", desc: 'Emploi du temps, salles, matieres, devoirs' },
    { value: 'accountant', label: 'Comptable', desc: 'Paie, bulletins, validation, exports' },
    { value: 'admin', label: 'Administrateur', desc: 'Acces complet au systeme' },
];

const UsersPage = () => {
    const navigate = useNavigate();
    const userAuth = useAuthStore((state) => state.user);
    const isAdmin = hasAnyRole(userAuth, ['admin', 'hr_manager', 'secretary']);
    const isAdminUser = hasAnyRole(userAuth, ['admin']);
    const [users, setUsers] = useState([]);
    const [loading, setLoading] = useState(true);
    const [searchTerm, setSearchTerm] = useState('');
    const [page, setPage] = useState(1);
    const perPage = 12;
    const [pagination, setPagination] = useState({ current_page: 1, last_page: 1, total: 0 });
    const [confirmConfig, setConfirmConfig] = useState({
        isOpen: false, type: 'danger', title: '', message: '', onConfirm: () => {}
    });
    const [showCreateModal, setShowCreateModal] = useState(false);
    const [createLoading, setCreateLoading] = useState(false);
    const [createForm, setCreateForm] = useState({ name: '', email: '', phone: '', roles: [], ufr_id: '' });
    const [ufrs, setUfrs] = useState([]);

    useEffect(() => {
        if (userAuth === null) return;
        if (!isAdmin) {
            navigate('/dashboard', { replace: true });
            return;
        }
    }, [isAdmin, navigate, userAuth]);

    const fetchUsers = async (pageNum = 1) => {
        if (!isAdmin) return;
        setLoading(true);
        try {
            const params = { page: pageNum, per_page: perPage };
            if (searchTerm) params.search = searchTerm;
            const response = await api.get('/auth/users', { params });
            const data = response.data;
            setUsers(data.data || []);
            setPagination({ current_page: data.current_page, last_page: data.last_page, total: data.total });
        } catch {
            toast.error('Erreur de chargement');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        if (!isAdmin) return;
        const controller = new AbortController();
        api.get('/ufrs', { signal: controller.signal }).then(res => {
            setUfrs(res.data?.data ?? []);
        }).catch(() => {});
        return () => controller.abort();
    }, [isAdmin]);

    useEffect(() => {
        fetchUsers(page);
    // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [page, searchTerm]);

    useEffect(() => { setPage(1); }, [searchTerm]);

    const toggleStatus = async (id) => {
        try {
            await api.patch(`/auth/users/${id}/toggle-status`);
            toast.success('Statut mis a jour');
            fetchUsers(page);
        } catch {
            toast.error('Action impossible');
        }
    };

    const handleDeleteUser = async (id) => {
        setConfirmConfig({
            isOpen: true,
            type: 'danger',
            title: 'Supprimer definitivement ?',
            message: "Cette action est irreversible. L'utilisateur et toutes ses donnees seront supprimes.",
            confirmText: 'Supprimer',
            onConfirm: async () => {
                try {
                    await api.delete(`/auth/users/${id}`);
                    toast.success('Utilisateur supprime');
                    setConfirmConfig({ ...confirmConfig, isOpen: false });
                    fetchUsers(page);
                } catch {
                    toast.error('Erreur de suppression');
                    setConfirmConfig({ ...confirmConfig, isOpen: false });
                }
            }
        });
    };

    const handleCreate = async (e) => {
        e.preventDefault();
        if (createForm.roles.length === 0) {
            toast.error('Selectionnez au moins un role');
            return;
        }
        if (createForm.roles.includes('teacher') && !createForm.ufr_id) {
            toast.error("Selectionnez le UFR de l'enseignant");
            return;
        }
        setCreateLoading(true);
        try {
            const payload = { ...createForm };
            if (!payload.roles.includes('teacher')) {
                delete payload.ufr_id;
            }
            const res = await api.post('/auth/users', payload);
            toast.success(res.data?.message || 'Compte cree. Un SMS avec le mot de passe temporaire a ete envoye.');
            await fetchUsers(1);
            setShowCreateModal(false);
            setCreateForm({ name: '', email: '', phone: '', roles: [], ufr_id: '' });
            setSearchTerm('');
            setPage(1);
        } catch (error) {
            const errors = error.response?.data?.errors;
            if (errors) {
                toast.error(Object.values(errors)[0]?.[0] || 'Erreur lors de la creation');
            } else {
                toast.error(error.response?.data?.message || 'Erreur lors de la creation');
            }
        } finally {
            setCreateLoading(false);
        }
    };

    const toggleRole = (role) => {
        setCreateForm(prev => {
            const has = prev.roles.includes(role);
            const roles = has ? prev.roles.filter(r => r !== role) : [...prev.roles, role];
            const ufr_id = roles.includes('teacher') ? prev.ufr_id : '';
            return { ...prev, roles, ufr_id };
        });
    };

    const AVATAR_GRADIENTS = [
        'from-gray-800 to-gray-900', 'from-blue-500 to-indigo-600', 'from-purple-500 to-pink-600',
        'from-emerald-500 to-teal-600', 'from-amber-500 to-orange-600'
    ];

    return (
        <div className="min-h-screen">
            <div className="max-w-[1400px] mx-auto space-y-6 px-2 sm:px-4">
                <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 pt-2">
                    <div>
                        <div className="flex items-center gap-3 mb-1">
                            <div className="w-10 h-10 rounded-xl bg-gradient-to-br from-slate-700 to-slate-900 flex items-center justify-center shadow-lg shadow-slate-500/25">
                                <Shield size={20} className="text-white" />
                            </div>
                            <h1 className="text-2xl font-bold text-slate-900 tracking-tight">Gestion des Comptes</h1>
                        </div>
                        <p className="text-slate-400 text-sm ml-[52px]">Pilotez les acces et les profils utilisateurs</p>
                    </div>
                    <button
                        onClick={() => setShowCreateModal(true)}
                        className="flex items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-slate-700 to-slate-900 text-white rounded-xl font-semibold text-sm shadow-lg shadow-slate-500/25 hover:shadow-slate-500/40 transition-all duration-300 active:scale-[0.97]"
                    >
                        <Plus size={18} strokeWidth={2.5} />
                        <span>Nouveau Compte</span>
                    </button>
                </div>

                <div className="bg-white rounded-2xl border border-slate-100 p-3">
                    <div className="relative flex-1">
                        <Search className="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-300" size={16} />
                        <input
                            type="text"
                            placeholder="Rechercher un utilisateur..."
                            className="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-100 rounded-xl text-sm text-slate-700 placeholder:text-slate-300 focus:outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-500/10 transition-all"
                            value={searchTerm}
                            onChange={(e) => setSearchTerm(e.target.value)}
                        />
                    </div>
                </div>

                {loading ? (
                    <div className="flex justify-center py-20">
                        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600" />
                    </div>
                ) : users.length === 0 ? (
                    <div className="flex flex-col items-center justify-center py-24">
                        <div className="w-16 h-16 bg-slate-100 rounded-2xl flex items-center justify-center mb-4">
                            <Shield size={28} className="text-slate-300" />
                        </div>
                        <p className="text-base font-bold text-slate-400 mb-1">Aucun utilisateur</p>
                        <p className="text-xs text-slate-300">
                            {searchTerm ? "Essayez de modifier votre recherche" : "Commencez par creer un compte"}
                        </p>
                    </div>
                ) : (
                    <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 mb-6">
                        {users.map((u) => {
                            const isActive = u.status === 'active';
                            const avatarIdx = u.id % AVATAR_GRADIENTS.length;
                            const userRoles = Array.isArray(u.roles) ? u.roles.map(r => typeof r === 'string' ? r : r.name) : [];
                            const role = userRoles[0] || 'teacher';
                            const roleLabel = getRoleLabel(role);
                            const roleBadgeBg = {
                                admin: 'bg-gray-100 text-gray-700 border-gray-200',
                                hr_manager: 'bg-blue-50 text-blue-700 border-blue-200',
                                secretary: 'bg-blue-50 text-blue-700 border-blue-200',
                                academic_manager: 'bg-purple-50 text-purple-700 border-purple-200',
                                accountant: 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                teacher: 'bg-amber-50 text-amber-700 border-amber-200'
                            }[role] || 'bg-gray-50 text-gray-600 border-gray-200';
                            const subText = userRoles.length > 1 ? userRoles.slice(1).map(getRoleLabel).join(' / ') : 'Acces standard';

                            return (
                                <div key={u.id} onClick={null}
                                    className="group relative bg-white rounded-2xl p-5 border border-slate-100 shadow-sm hover:shadow-lg hover:border-indigo-200 transition-all duration-300">
                                    <div className="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-indigo-400 to-purple-500 opacity-0 group-hover:opacity-100 transition-opacity rounded-t-2xl" />

                                    <div className="flex items-start justify-between mb-4">
                                        <div className="flex items-center gap-3">
                                            <div className={`flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br ${AVATAR_GRADIENTS[avatarIdx]} text-white font-bold text-sm shadow-md`}>
                                                {u.name?.charAt(0)?.toUpperCase() || '?'}
                                            </div>
                                            <div className="min-w-0">
                                                <h3 className="font-bold text-slate-900 text-sm truncate group-hover:text-indigo-600 transition-colors">{u.name}</h3>
                                                <p className="text-[10px] text-slate-400 truncate">{u.email}</p>
                                            </div>
                                        </div>
                                    </div>

                                    <div className="flex flex-wrap gap-1.5 mb-3">
                                        <span className={`inline-flex items-center px-2.5 py-1 rounded-lg text-[10px] font-black uppercase tracking-wider border ${roleBadgeBg}`}>
                                            <span className={`w-1.5 h-1.5 rounded-full mr-1.5 ${
                                                role === 'admin' ? 'bg-gray-500' :
                                                role === 'hr_manager' || role === 'secretary' ? 'bg-blue-500' :
                                                role === 'academic_manager' ? 'bg-purple-500' :
                                                role === 'accountant' ? 'bg-emerald-500' : 'bg-amber-500'
                                            }`}></span>
                                            {roleLabel}
                                        </span>
                                        <span className={`inline-flex items-center px-2.5 py-1 rounded-lg text-[10px] font-black uppercase tracking-wider border ${isActive ? 'bg-emerald-50 text-emerald-600 border-emerald-100' : 'bg-red-50 text-red-500 border-red-100'}`}>
                                            <span className={`w-1.5 h-1.5 rounded-full mr-1.5 ${isActive ? 'bg-emerald-500 animate-pulse' : 'bg-red-500'}`} />
                                            {isActive ? 'Actif' : 'Inactif'}
                                        </span>
                                    </div>

                                    <p className="text-[10px] text-slate-400 mb-2">{subText}</p>
                                    <p className="text-[10px] text-slate-400">
                                        Cree le {u.created_at ? new Date(u.created_at).toLocaleDateString('fr-FR') : '—'}
                                    </p>

                                    <div className="flex items-center gap-1.5 pt-3 border-t border-slate-100 mt-3">
                                        {isAdminUser && (
                                        <>
                                        <button
                                            onClick={(e) => { e.stopPropagation(); toggleStatus(u.id); }}
                                            className={`flex-1 flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl text-[11px] font-bold transition-all duration-200 ${
                                                isActive
                                                    ? 'bg-amber-50 text-amber-600 hover:bg-amber-500 hover:text-white'
                                                    : 'bg-emerald-50 text-emerald-600 hover:bg-emerald-500 hover:text-white'
                                            }`}
                                        >
                                            {isActive ? 'Suspendre' : 'Activer'}
                                        </button>
                                        <button
                                            onClick={(e) => { e.stopPropagation(); handleDeleteUser(u.id); }}
                                            className="px-3 py-2 bg-red-50 text-red-400 rounded-xl hover:bg-red-500 hover:text-white transition-all duration-200"
                                        >
                                            <Trash2 size={12} />
                                        </button>
                                        </>
                                        )}
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                )}

                <Pagination
                    page={pagination.current_page}
                    totalPages={pagination.last_page}
                    onPageChange={setPage}
                    totalItems={pagination.total}
                    perPage={perPage}
                />
            </div>

            <ConfirmModal
                isOpen={confirmConfig.isOpen}
                onClose={() => setConfirmConfig(prev => ({ ...prev, isOpen: false }))}
                onConfirm={confirmConfig.onConfirm}
                title={confirmConfig.title}
                message={confirmConfig.message}
                confirmText={confirmConfig.confirmText || 'Supprimer'}
                type={confirmConfig.type || 'danger'}
            />

            <FormModal
                isOpen={showCreateModal}
                onClose={() => setShowCreateModal(false)}
                onSubmit={handleCreate}
                title="Nouveau Compte"
                subtitle="Creer un compte avec un mot de passe temporaire"
                size="md"
                submitText={createLoading ? 'Creation...' : 'Creer le compte'}
                disableSubmit={createLoading}
            >
                <div className="space-y-5">
                    <div className="form-group">
                        <label className="form-label">Nom complet</label>
                        <div className="relative">
                            <User className="absolute left-4 top-1/2 -translate-y-1/2 text-gray-300" size={16} />
                            <input type="text" required value={createForm.name}
                                onChange={e => setCreateForm({ ...createForm, name: e.target.value })}
                                placeholder="Jean Dupont" className="input-field icon" />
                        </div>
                    </div>
                    <div className="form-group">
                        <label className="form-label">Adresse Email</label>
                        <div className="relative">
                            <Mail className="absolute left-4 top-1/2 -translate-y-1/2 text-gray-300" size={16} />
                            <input type="email" required value={createForm.email}
                                onChange={e => setCreateForm({ ...createForm, email: e.target.value })}
                                placeholder="prenom.nom@udb.edu.sn" className="input-field icon" />
                        </div>
                    </div>
                    <div className="form-group">
                        <label className="form-label">Telephone</label>
                        <div className="relative">
                            <Phone className="absolute left-4 top-1/2 -translate-y-1/2 text-gray-300" size={16} />
                            <input type="tel" value={createForm.phone}
                                onChange={e => setCreateForm({ ...createForm, phone: e.target.value.replace(/\D/g, '').slice(0, 9) })}
                                placeholder="77 123 45 67" className="input-field icon" pattern="[0-9]{9}" />
                        </div>
                        <p className="text-[10px] text-gray-400 mt-1 ml-1">Le mot de passe temporaire sera envoye par SMS a ce numero</p>
                    </div>
                    <div className="form-group">
                        <label className="form-label">Acces / Roles</label>
                        <div className="grid grid-cols-1 gap-2">
                            {ROLES.filter(r => isAdminUser || r.value !== 'admin').map(r => {
                                const checked = createForm.roles.includes(r.value);
                                return (
                                    <label key={r.value}
                                        className={`flex items-start gap-3 rounded-2xl border p-3 cursor-pointer transition-all select-none ${checked ? 'border-indigo-300 bg-indigo-50' : 'border-gray-200 bg-white hover:border-gray-300'}`}>
                                        <input type="checkbox" checked={checked} onChange={() => toggleRole(r.value)}
                                            className="mt-0.5 w-4 h-4 rounded accent-indigo-600" />
                                        <div className="flex-1">
                                            <p className="text-sm font-bold text-slate-800">{r.label}</p>
                                            <p className="text-xs text-slate-400 mt-0.5">{r.desc}</p>
                                        </div>
                                    </label>
                                );
                            })}
                        </div>
                        <p className="text-[10px] text-gray-400 mt-1.5 ml-1">Cochez une ou plusieurs capacites pour ce compte.</p>
                    </div>
                    {createForm.roles.includes('teacher') && (
                        <div className="form-group">
                            <label className="form-label">UFR de l'enseignant</label>
                            <div className="relative">
                                <User className="absolute left-4 top-1/2 -translate-y-1/2 text-gray-300" size={16} />
                                <select required value={createForm.ufr_id}
                                    onChange={e => setCreateForm({ ...createForm, ufr_id: e.target.value })}
                                    className="input-field icon">
                                    <option value="">Selectionner un UFR...</option>
                                    {ufrs.map(d => <option key={d.id} value={d.id}>{d.name}</option>)}
                                </select>
                            </div>
                        </div>
                    )}
                    <div className="p-4 bg-blue-50 border border-blue-200 rounded-xl">
                        <p className="text-blue-700 text-xs font-medium">
                            Un mot de passe temporaire sera genere automatiquement et envoye par SMS et par email. L'utilisateur devra le changer lors de sa premiere connexion.
                        </p>
                    </div>
                </div>
            </FormModal>
        </div>
    );
};

export default UsersPage;

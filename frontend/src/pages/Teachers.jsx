import { useEffect, useState } from 'react';
import api from '../services/api';
import {
    Plus, Search, Edit, Trash2, UserCheck, UserX, Users,
    Mail, Phone, Eye, EyeOff,
    Calendar, Banknote, Clock, X, GraduationCap
} from 'lucide-react';
import toast from '../utils/toast';
import ConfirmModal from '../components/ConfirmModal';
import FormModal from '../components/FormModal';
import Pagination from '../components/Pagination';
import cleanPayload from '../utils/cleanPayload';
import useAuthStore from '../store/authStore';
import { hasAnyRole } from '../utils/roles';
import { SkeletonGrid } from '../components/Skeleton';

const COLORS = [
    { bg: 'from-blue-500 to-blue-600', light: 'bg-blue-50', text: 'text-blue-600', ring: 'ring-blue-100' },
    { bg: 'from-emerald-500 to-emerald-600', light: 'bg-emerald-50', text: 'text-emerald-600', ring: 'ring-emerald-100' },
    { bg: 'from-violet-500 to-violet-600', light: 'bg-violet-50', text: 'text-violet-600', ring: 'ring-violet-100' },
    { bg: 'from-amber-500 to-amber-600', light: 'bg-amber-50', text: 'text-amber-600', ring: 'ring-amber-100' },
    { bg: 'from-rose-500 to-rose-600', light: 'bg-rose-50', text: 'text-rose-600', ring: 'ring-rose-100' },
    { bg: 'from-cyan-500 to-cyan-600', light: 'bg-cyan-50', text: 'text-cyan-600', ring: 'ring-cyan-100' },
    { bg: 'from-fuchsia-500 to-fuchsia-600', light: 'bg-fuchsia-50', text: 'text-fuchsia-600', ring: 'ring-fuchsia-100' },
    { bg: 'from-teal-500 to-teal-600', light: 'bg-teal-50', text: 'text-teal-600', ring: 'ring-teal-100' },
];

const statusStyles = {
    active: { bg: 'bg-emerald-50', text: 'text-emerald-700', label: 'En poste', dot: 'bg-emerald-500' },
    inactive: { bg: 'bg-red-50', text: 'text-red-600', label: 'Inactif', dot: 'bg-red-400' },
};

const emptyForm = {
    name: '', email: '', ufr_id: '', employee_id: '',
    phone: '', mobile: '', address: '',
    hire_date: new Date().toISOString().split('T')[0],
    volume_hours: '', hourly_rate: ''
};

const Teachers = () => {
    const user = useAuthStore((s) => s.user);
    const canManage = hasAnyRole(user, ['admin', 'hr_manager', 'secretary']);
    const [teachers, setTeachers] = useState([]);
    const [ufrs, setUfrs] = useState([]);
    const [loading, setLoading] = useState(true);
    const [showModal, setShowModal] = useState(false);
    const [isEditing, setIsEditing] = useState(false);
    const [searchTerm, setSearchTerm] = useState('');
    const [ufrFilter, setUfrFilter] = useState('');
    const [statusFilter, setStatusFilter] = useState('');
    const [selectedTeacher, setSelectedTeacher] = useState(null);
    const [expandedCard, setExpandedCard] = useState(null);
    const [page, setPage] = useState(1);
    const perPage = 12;
    const [pagination, setPagination] = useState({ current_page: 1, last_page: 1, total: 0 });
    const [submitting, setSubmitting] = useState(false);
    const [fieldErrors, setFieldErrors] = useState({});
    const [confirmConfig, setConfirmConfig] = useState({
        isOpen: false, type: 'danger', title: '', message: '', onConfirm: () => {}
    });

    const [formData, setFormData] = useState(emptyForm);

    const fetchTeachers = async (pageNum = 1) => {
        setLoading(true);
        try {
            const params = { page: pageNum, per_page: perPage };
            if (searchTerm) params.search = searchTerm;
            if (ufrFilter) params.ufr_id = ufrFilter;
            if (statusFilter) params.status = statusFilter;
            const response = await api.get('/teachers', { params });
            const data = response.data;
            setTeachers(data.data || []);
            setPagination({ current_page: data.current_page, last_page: data.last_page, total: data.total });
        } catch {
            toast.error('Erreur chargement enseignants');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        const controller = new AbortController();
        let cancelled = false;
        api.get('/ufrs', { signal: controller.signal }).then(res => {
            if (!cancelled) {
                const d = res.data;
                setUfrs(Array.isArray(d) ? d : (d?.data || []));
            }
        }).catch(() => {});
        return () => { cancelled = true; controller.abort(); };
    }, []);

    useEffect(() => {
        fetchTeachers(page);
    // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [page, searchTerm, ufrFilter, statusFilter]);

    useEffect(() => { setPage(1); }, [searchTerm, ufrFilter, statusFilter]);

    const handleOpenAddModal = () => {
        setIsEditing(false);
        setSelectedTeacher(null);
        setFieldErrors({});
        setFormData({ ...emptyForm });
        setShowModal(true);
    };

    const handleOpenEditModal = (teacher) => {
        setIsEditing(true);
        setSelectedTeacher(teacher);
        setFieldErrors({});
        setFormData({
            name: teacher.user?.name ?? '',
            email: teacher.user?.email ?? '',
            ufr_id: teacher.ufr_id ?? '',
            employee_id: teacher.employee_id ?? '',
            phone: teacher.phone ?? '',
            mobile: teacher.mobile ?? '',
            address: teacher.address ?? '',
            hire_date: teacher.hire_date ? teacher.hire_date.split('T')[0] : '',
            volume_hours: teacher.volume_hours ?? '',
            hourly_rate: teacher.hourly_rate ?? '',
            status: teacher.status || 'active',
        });
        setShowModal(true);
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        setSubmitting(true);
        setFieldErrors({});
        try {
            const payload = { ...formData };
            delete payload.password;
            delete payload.password_confirmation;
            if (isEditing) {
                delete payload.email;
                await api.put(`/teachers/${selectedTeacher.id}`, cleanPayload(payload, { stripEmpty: false }));
                toast.success('Enseignant mis a jour avec succes');
            } else {
                await api.post('/teachers', cleanPayload(payload, { stripEmpty: false }));
                toast.success('Enseignant ajoute avec succes');
            }
            await fetchTeachers(1);
            setShowModal(false);
            setSelectedTeacher(null);
            setFormData({ ...emptyForm });
            setFieldErrors({});
            setSearchTerm('');
            setUfrFilter('');
            setStatusFilter('');
            setPage(1);
        } catch (error) {
            if (error.response?.status === 422 && (error.response?.data?.errors || error.response?.data?.messages)) {
                setFieldErrors(error.response.data.errors || error.response.data.messages);
                toast.error('Veuillez corriger les erreurs du formulaire');
            } else {
                const msg = error.response?.data?.message || error.response?.data?.error || 'Une erreur est survenue';
                toast.error(msg);
            }
        } finally {
            setSubmitting(false);
        }
    };

    const handleArchiveConfirm = (teacher) => {
        const isActivating = teacher.status !== 'active';
        setConfirmConfig({
            isOpen: true,
            type: isActivating ? 'info' : 'warning',
            title: isActivating ? 'Reactiver le compte ?' : 'Desactiver le compte ?',
            message: `Voulez-vous vraiment ${isActivating ? 'reactiver' : 'desactiver'} l'acces de ${teacher.user?.name} ?`,
            confirmText: isActivating ? 'Reactiver' : 'Desactiver',
            onConfirm: async () => {
                try {
                    await api.patch(`/teachers/${teacher.id}/archive`);
                    toast.success(isActivating ? 'Compte reactive' : 'Compte desactive');
                    setConfirmConfig(prev => ({ ...prev, isOpen: false }));
                    fetchTeachers(page);
                } catch {
                    toast.error('Erreur lors de la mise a jour du statut');
                    setConfirmConfig(prev => ({ ...prev, isOpen: false }));
                }
            }
        });
    };

    const handleDeleteConfirm = (teacher) => {
        setConfirmConfig({
            isOpen: true, type: 'danger',
            title: 'Supprimer definitivement ?',
            message: `Supprimer ${teacher.user?.name} ainsi que son compte et toutes ses donnees (cours, pointages, honoraires...) ?`,
            confirmText: 'Tout supprimer',
            onConfirm: async () => {
                try {
                    const res = await api.delete(`/teachers/${teacher.id}`);
                    toast.success(res.data?.message || 'Enseignant et donnees liees supprimes');
                    setConfirmConfig(prev => ({ ...prev, isOpen: false }));
                    fetchTeachers(page);
                } catch (err) {
                    toast.error(err.response?.data?.message || 'Erreur lors de la suppression');
                    setConfirmConfig(prev => ({ ...prev, isOpen: false }));
                }
            }
        });
    };

    const getColor = (id) => COLORS[(id || 0) % COLORS.length];

    const getInitials = (name) => {
        if (!name) return '?';
        const parts = name.trim().split(' ');
        return parts.length >= 2
            ? (parts[0][0] + parts[parts.length - 1][0]).toUpperCase()
            : name.substring(0, 2).toUpperCase();
    };

    return (
        <div className="min-h-screen">
            <div className="max-w-[1400px] mx-auto space-y-6 px-2 sm:px-4">

                <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 pt-2">
                    <div>
                        <div className="flex items-center gap-3 mb-1">
                            <div className="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center shadow-lg shadow-blue-500/25">
                                <Users size={20} className="text-white" />
                            </div>
                            <h1 className="text-2xl font-bold text-slate-900 tracking-tight">Enseignants</h1>
                        </div>
                        <p className="text-slate-400 text-sm ml-[52px]">Gestion du corps professoral</p>
                    </div>
                    {canManage && (
                    <button
                        onClick={handleOpenAddModal}
                        className="flex items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-blue-600 to-indigo-600 text-white rounded-xl font-semibold text-sm shadow-lg shadow-blue-500/25 hover:shadow-blue-500/40 hover:from-blue-700 hover:to-indigo-700 transition-all duration-300 active:scale-[0.97]"
                    >
                        <Plus size={18} strokeWidth={2.5} />
                        <span>Nouvel Enseignant</span>
                    </button>
                    )}
                </div>

                <div className="bg-white rounded-2xl border border-slate-100 p-3 flex flex-col sm:flex-row gap-2">
                    <div className="relative flex-1">
                        <Search className="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-300" size={16} />
                        <input
                            type="text"
                            placeholder="Rechercher par nom, matricule, email..."
                            className="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-100 rounded-xl text-sm text-slate-700 placeholder:text-slate-300 focus:outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-500/10 transition-all"
                            value={searchTerm}
                            onChange={(e) => setSearchTerm(e.target.value)}
                        />
                        {searchTerm && (
                            <button onClick={() => setSearchTerm('')} className="absolute right-3 top-1/2 -translate-y-1/2 text-slate-300 hover:text-slate-500">
                                <X size={14} />
                            </button>
                        )}
                    </div>

                    <div className="flex gap-2">
                        <select
                            className="bg-slate-50 border border-slate-100 rounded-xl px-3 py-2.5 pr-8 text-xs font-semibold text-slate-600 cursor-pointer focus:outline-none focus:border-blue-400 transition-all"
                            value={ufrFilter}
                            onChange={e => setUfrFilter(e.target.value)}
                        >
                            <option value="">Tous les dept.</option>
                            {ufrs.map(d => <option key={d.id} value={d.id}>{d.name}</option>)}
                        </select>

                        <select
                            className="bg-slate-50 border border-slate-100 rounded-xl px-3 py-2.5 pr-8 text-xs font-semibold text-slate-600 cursor-pointer focus:outline-none focus:border-blue-400 transition-all"
                            value={statusFilter}
                            onChange={e => setStatusFilter(e.target.value)}
                        >
                            <option value="">Tous les statuts</option>
                            <option value="active">En poste</option>
                            <option value="inactive">Inactif</option>
                            <option value="on_leave">En absence</option>
                        </select>
                    </div>
                </div>

                {(searchTerm || ufrFilter || statusFilter) && (
                    <div className="flex items-center gap-2 flex-wrap">
                        <span className="text-xs font-semibold text-slate-400">Filtres :</span>
                        {searchTerm && (
                            <span className="inline-flex items-center gap-1 px-2.5 py-1 bg-blue-50 text-blue-600 rounded-lg text-xs font-semibold">
                                &quot;{searchTerm}&quot;
                                <button onClick={() => setSearchTerm('')}><X size={11} /></button>
                            </span>
                        )}
                        {ufrFilter && (
                            <span className="inline-flex items-center gap-1 px-2.5 py-1 bg-cyan-50 text-cyan-600 rounded-lg text-xs font-semibold">
                                {ufrs.find(u => String(u.id) === ufrFilter)?.name}
                                <button onClick={() => setUfrFilter('')}><X size={11} /></button>
                            </span>
                        )}
                        {statusFilter && (
                            <span className="inline-flex items-center gap-1 px-2.5 py-1 bg-emerald-50 text-emerald-600 rounded-lg text-xs font-semibold">
                                {statusStyles[statusFilter]?.label}
                                <button onClick={() => setStatusFilter('')}><X size={11} /></button>
                            </span>
                        )}
                        <button
                            onClick={() => { setSearchTerm(''); setUfrFilter(''); setStatusFilter(''); }}
                            className="text-xs font-semibold text-slate-400 hover:text-red-500 transition-colors"
                        >
                            Tout effacer
                        </button>
                    </div>
                )}

                {loading ? (
                    <SkeletonGrid cards={6} className="mt-4" />
                ) : teachers.length === 0 ? (
                    <div className="flex flex-col items-center justify-center py-24">
                        <div className="w-16 h-16 bg-slate-100 rounded-2xl flex items-center justify-center mb-4">
                            <Users size={28} className="text-slate-300" />
                        </div>
                        <p className="text-base font-bold text-slate-400 mb-1">Aucun enseignant trouve</p>
                        <p className="text-xs text-slate-300">
                            {(searchTerm || ufrFilter || statusFilter)
                                ? "Essayez de modifier vos filtres"
                                : "Commencez par ajouter un enseignant"}
                        </p>
                        {!searchTerm && !ufrFilter && !statusFilter && canManage && (
                            <button
                                onClick={handleOpenAddModal}
                                className="mt-4 px-4 py-2 bg-blue-50 text-blue-600 rounded-xl text-sm font-semibold hover:bg-blue-100 transition-colors"
                            >
                                <Plus size={14} className="inline mr-1" />
                                Ajouter un enseignant
                            </button>
                        )}
                    </div>
                ) : (
                    <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 mb-6">
                        {teachers.map((teacher) => {
                            const color = getColor(teacher.id);
                            const isActive = teacher.status === 'active';
                            const isExpanded = expandedCard === teacher.id;

                            return (
                                <div
                                    key={teacher.id}
                                    className={`group relative bg-white rounded-2xl p-5 border border-slate-100 shadow-sm hover:shadow-lg hover:border-indigo-200 transition-all duration-300 overflow-hidden ${!isActive ? 'opacity-70' : ''}`}
                                >
                                    <div className="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-indigo-400 to-purple-500 opacity-0 group-hover:opacity-100 transition-opacity rounded-t-2xl" />

                                    <div className="flex items-start justify-between mb-4">
                                        <div className="flex items-center gap-3">
                                            <div className={`w-11 h-11 rounded-xl bg-gradient-to-br ${color.bg} flex items-center justify-center text-white font-bold text-sm shadow-md`}>
                                                {getInitials(teacher.user?.name)}
                                            </div>
                                            <div className="min-w-0">
                                                <h3 className="font-bold text-slate-900 text-sm truncate group-hover:text-indigo-600 transition-colors">
                                                    {teacher.user?.name || 'Inconnu'}
                                                </h3>
                                                <p className="text-[10px] font-mono font-semibold text-slate-400 tracking-wider">
                                                    {teacher.employee_id}
                                                </p>
                                            </div>
                                        </div>

                                        <div className="flex items-center gap-1">
                                            <button
                                                onClick={() => setExpandedCard(isExpanded ? null : teacher.id)}
                                                className="p-1.5 rounded-lg text-slate-300 hover:text-indigo-500 hover:bg-indigo-50 transition-all"
                                                title="Details"
                                            >
                                                {isExpanded ? <EyeOff size={14} /> : <Eye size={14} />}
                                            </button>
                                            {canManage && (
                                            <button
                                                onClick={() => handleOpenEditModal(teacher)}
                                                className="p-1.5 rounded-lg text-slate-300 hover:text-amber-500 hover:bg-amber-50 transition-all"
                                                title="Modifier"
                                            >
                                                <Edit size={14} />
                                            </button>
                                            )}
                                        </div>
                                    </div>

                                    <div className="flex flex-wrap gap-1.5 mb-4">
                                        <span className={`inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider ${color.light} ${color.text}`}>
                                            {teacher.ufr?.name || 'N/A'}
                                        </span>
                                        <span className={`inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider ${statusStyles[teacher.status]?.bg || 'bg-slate-50'} ${statusStyles[teacher.status]?.text || 'text-slate-500'}`}>
                                            <span className={`w-1.5 h-1.5 rounded-full ${statusStyles[teacher.status]?.dot || 'bg-slate-400'} mr-1.5 ${isActive ? 'animate-pulse' : ''}`}></span>
                                            {statusStyles[teacher.status]?.label || teacher.status}
                                        </span>
                                    </div>

                                    <div className="bg-slate-50 rounded-xl border border-slate-100 overflow-hidden transition-all duration-300" style={{ maxHeight: isExpanded ? '300px' : '0px', opacity: isExpanded ? 1 : 0 }}>
                                        <div className="p-3.5 space-y-2.5">
                                            <div className="flex items-center text-xs text-slate-500">
                                                <Mail size={12} className="mr-2 text-blue-400 shrink-0" />
                                                <span className="truncate">{teacher.user?.email || '-'}</span>
                                            </div>
                                            <div className="flex items-center text-xs text-slate-500">
                                                <Phone size={12} className="mr-2 text-emerald-400 shrink-0" />
                                                <span>{teacher.phone || '-'}</span>
                                            </div>
                                            <div className="flex items-center text-xs text-slate-500">
                                                <Calendar size={12} className="mr-2 text-amber-400 shrink-0" />
                                                <span>Embauche : {teacher.hire_date ? new Date(teacher.hire_date).toLocaleDateString('fr-FR') : '-'}</span>
                                            </div>
                                            {teacher.volume_hours && (
                                                <div className="flex items-center text-xs text-slate-500">
                                                    <Clock size={12} className="mr-2 text-cyan-400 shrink-0" />
                                                    <span>Volume horaire : {teacher.volume_hours}h</span>
                                                </div>
                                            )}
                                            {teacher.hourly_rate && (
                                                <div className="flex items-center text-xs text-slate-500">
                                                    <Banknote size={12} className="mr-2 text-violet-400 shrink-0" />
                                                    <span>{teacher.volume_hours || 0}h x {new Intl.NumberFormat('fr-FR').format(teacher.hourly_rate)} FCFA/h</span>
                                                </div>
                                            )}
                                        </div>
                                    </div>

                                    {canManage && (
                                    <div className="flex items-center gap-1.5 pt-3 border-t border-slate-100 mt-3">
                                        <button
                                            onClick={() => handleArchiveConfirm(teacher)}
                                            className={`flex-1 flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl text-[11px] font-bold transition-all duration-200 ${
                                                isActive
                                                    ? 'bg-amber-50 text-amber-600 hover:bg-amber-500 hover:text-white'
                                                    : 'bg-emerald-50 text-emerald-600 hover:bg-emerald-500 hover:text-white'
                                            }`}
                                        >
                                            {isActive ? <UserX size={12} /> : <UserCheck size={12} />}
                                            {isActive ? 'Desactiver' : 'Reactiver'}
                                        </button>
                                        <button
                                            onClick={() => handleDeleteConfirm(teacher)}
                                            className="px-3 py-2 bg-red-50 text-red-400 rounded-xl hover:bg-red-500 hover:text-white transition-all duration-200"
                                        >
                                            <Trash2 size={12} />
                                        </button>
                                    </div>
                                    )}
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

            <FormModal
                isOpen={showModal}
                onClose={() => { setShowModal(false); setFieldErrors({}); }}
                onSubmit={handleSubmit}
                title={isEditing ? 'Modifier Enseignant' : 'Nouvel Enseignant'}
                subtitle={isEditing ? `Edition de ${formData.name ?? ''}` : "Creation d'un nouveau profil enseignant"}
                size="xl"
                disableSubmit={submitting}
                submitText={submitting ? 'Envoi...' : isEditing ? 'Enregistrer les modifications' : 'Creer le profil enseignant'}>
                <div className="max-w-4xl mx-auto space-y-5">
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className="form-label">Nom complet</label>
                            <input required type="text" className="input-field" placeholder="Dr. Moussa Kasse"
                                value={formData.name} onChange={e => setFormData({...formData, name: e.target.value})} />
                            {fieldErrors.name && <p className="text-xs text-red-500 mt-1">{fieldErrors.name[0]}</p>}
                        </div>
                        {!isEditing && (
                            <div>
                                <label className="form-label">Email</label>
                                <input required type="email" className="input-field" placeholder="email@sirh.local"
                                    value={formData.email} onChange={e => setFormData({...formData, email: e.target.value})} />
                                {fieldErrors.email && <p className="text-xs text-red-500 mt-1">{fieldErrors.email[0]}</p>}
                            </div>
                        )}
                        {isEditing && <div/>}
                    </div>

                    <div className="grid grid-cols-3 gap-4">
                        <div>
                            <label className="form-label">Matricule</label>
                            <input required type="text" className="input-field" placeholder="PROF-XXXX"
                                disabled={isEditing} value={formData.employee_id}
                                onChange={e => setFormData({...formData, employee_id: e.target.value})} />
                            {fieldErrors.employee_id && <p className="text-xs text-red-500 mt-1">{fieldErrors.employee_id[0]}</p>}
                        </div>
                        <div>
                            <label className="form-label">Departement</label>
                            <select required className="select-field"
                                value={formData.ufr_id} onChange={e => setFormData({...formData, ufr_id: e.target.value})}>
                                <option value="">Selectionner...</option>
                                {ufrs.map(d => <option key={d.id} value={d.id}>{d.name}</option>)}
                            </select>
                            {fieldErrors.ufr_id && <p className="text-xs text-red-500 mt-1">{fieldErrors.ufr_id[0]}</p>}
                        </div>
                        <div>
                            <label className="form-label">Date embauche</label>
                            <input required type="date" className="input-field"
                                value={formData.hire_date} onChange={e => setFormData({...formData, hire_date: e.target.value})} />
                        </div>
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className="form-label">Volume horaire/an</label>
                            <input required type="number" min="0" className="input-field" placeholder="120"
                                value={formData.volume_hours} onChange={e => setFormData({...formData, volume_hours: e.target.value})} />
                            {fieldErrors.volume_hours && <p className="text-xs text-red-500 mt-1">{fieldErrors.volume_hours[0]}</p>}
                        </div>
                        <div>
                            <label className="form-label">Taux horaire FCFA</label>
                            <input required type="number" min="0" className="input-field" placeholder="15000"
                                value={formData.hourly_rate} onChange={e => setFormData({...formData, hourly_rate: e.target.value})} />
                            {fieldErrors.hourly_rate && <p className="text-xs text-red-500 mt-1">{fieldErrors.hourly_rate[0]}</p>}
                        </div>
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className="form-label">Telephone</label>
                            <input type="text" className="input-field" placeholder="+221 XX XXX XX XX"
                                value={formData.phone} onChange={e => setFormData({...formData, phone: e.target.value})} />
                            {fieldErrors.phone && <p className="text-xs text-red-500 mt-1">{fieldErrors.phone[0]}</p>}
                        </div>
                        <div>
                            <label className="form-label">Mobile</label>
                            <input type="text" className="input-field" placeholder="+221 XX XXX XX XX"
                                value={formData.mobile} onChange={e => setFormData({...formData, mobile: e.target.value})} />
                            {fieldErrors.mobile && <p className="text-xs text-red-500 mt-1">{fieldErrors.mobile[0]}</p>}
                        </div>
                    </div>

                    <div>
                        <label className="form-label">Adresse</label>
                        <input type="text" className="input-field" placeholder="Adresse complete"
                            value={formData.address} onChange={e => setFormData({...formData, address: e.target.value})} />
                        {fieldErrors.address && <p className="text-xs text-red-500 mt-1">{fieldErrors.address[0]}</p>}
                    </div>

                    {!isEditing && (
                        <div className="border-t border-gray-200 pt-5">
                            <p className="text-xs font-black uppercase tracking-widest text-gray-400 mb-4">Authentification</p>
                            <div className="p-4 bg-blue-50 border border-blue-200 rounded-xl">
                                <p className="text-blue-700 text-xs font-medium">
                                    Un mot de passe temporaire sera genere automatiquement et envoye par SMS au numero de telephone fourni. L'utilisateur devra le changer lors de sa premiere connexion.
                                </p>
                            </div>
                        </div>
                    )}
                </div>
            </FormModal>

            <ConfirmModal
                isOpen={confirmConfig.isOpen}
                onClose={() => setConfirmConfig({...confirmConfig, isOpen: false})}
                onConfirm={confirmConfig.onConfirm}
                title={confirmConfig.title}
                message={confirmConfig.message}
                confirmText={confirmConfig.confirmText}
                type={confirmConfig.type}
            />
        </div>
    );
};

export default Teachers;

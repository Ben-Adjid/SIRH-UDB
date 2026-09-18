import { useEffect, useState } from 'react';
import api from '../services/api';
import { Search, Edit, Trash2, Plus, GraduationCap } from 'lucide-react';
import toast from '../utils/toast';
import ConfirmModal from '../components/ConfirmModal';
import FormModal from '../components/FormModal';
import Pagination from '../components/Pagination';
import useAuthStore from '../store/authStore';
import { hasAnyRole } from '../utils/roles';

const COLORS = [
    { bg: 'from-blue-500 to-blue-600', light: 'bg-blue-50', text: 'text-blue-600' },
    { bg: 'from-emerald-500 to-emerald-600', light: 'bg-emerald-50', text: 'text-emerald-600' },
    { bg: 'from-violet-500 to-violet-600', light: 'bg-violet-50', text: 'text-violet-600' },
    { bg: 'from-amber-500 to-amber-600', light: 'bg-amber-50', text: 'text-amber-600' },
    { bg: 'from-rose-500 to-rose-600', light: 'bg-rose-50', text: 'text-rose-600' },
    { bg: 'from-cyan-500 to-cyan-600', light: 'bg-cyan-50', text: 'text-cyan-600' },
];

const Ufrs = () => {
    const userAuth = useAuthStore((s) => s.user);
    const canManage = hasAnyRole(userAuth, ['admin', 'academic_manager']);

    const [ufrs, setUfrs] = useState([]);
    const [users, setUsers] = useState([]);
    const [loading, setLoading] = useState(true);
    const [showModal, setShowModal] = useState(false);
    const [isEditing, setIsEditing] = useState(false);
    const [selectedUfr, setSelectedUfr] = useState(null);
    const [searchTerm, setSearchTerm] = useState('');
    const [page, setPage] = useState(1);
    const perPage = 12;
    const [pagination, setPagination] = useState({ current_page: 1, last_page: 1, total: 0 });

    const [isConfirmOpen, setIsConfirmOpen] = useState(false);
    const [ufrToDelete, setUfrToDelete] = useState(null);

    const [formData, setFormData] = useState({
        name: '', code: '', description: '', head_id: ''
    });

    const fetchUfrs = async (pageNum = 1) => {
        setLoading(true);
        try {
            const params = { page: pageNum, per_page: perPage };
            if (searchTerm) params.search = searchTerm;
            const response = await api.get('/ufrs', { params });
            const data = response.data;
            setUfrs(data.data || []);
            setPagination({ current_page: data.current_page, last_page: data.last_page, total: data.total });
        } catch {
            toast.error('Erreur de chargement');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchUfrs(page);
    // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [page, searchTerm]);

    useEffect(() => {
        const controller = new AbortController();
        const endpoint = hasAnyRole(userAuth, ['admin', 'hr_manager', 'secretary']) ? '/auth/users' : '/teachers';
        api.get(endpoint, { params: { per_page: 200 }, signal: controller.signal }).then(res => {
            const d = res.data;
            const list = Array.isArray(d) ? d : (d?.data || []);
            const eligible = endpoint === '/auth/users'
                ? list.filter(u => u.roles?.some?.(role => ['teacher', 'academic_manager'].includes(role.name || role)))
                : list.map(teacher => ({ ...teacher, user: teacher.user }));
            if (hasAnyRole(userAuth, ['academic_manager']) && !eligible.some(u => (u.id || u.user?.id) === userAuth.id)) {
                eligible.push({ id: userAuth.id, user: userAuth });
            }
            setUsers(eligible);
        }).catch(() => {});
        return () => controller.abort();
    }, [userAuth]);

    useEffect(() => { setPage(1); }, [searchTerm]);

    const openCreate = () => {
        setFormData({ name: '', code: '', description: '', head_id: '' });
        setIsEditing(false);
        setShowModal(true);
    };

    const openEdit = (ufr) => {
        setSelectedUfr(ufr);
        setFormData({
            name: ufr.name || '',
            code: ufr.code || '',
            description: ufr.description || '',
            head_id: ufr.head_id || '',
        });
        setIsEditing(true);
        setShowModal(true);
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        try {
            if (isEditing) {
                await api.put(`/ufrs/${selectedUfr.id}`, formData);
                toast.success('UFR mise a jour');
            } else {
                await api.post('/ufrs', formData);
                toast.success('UFR creee');
            }
            setShowModal(false);
            setSelectedUfr(null);
            setSearchTerm('');
            setPage(1);
            fetchUfrs(1);
        } catch (err) {
            const msg = err.response?.data?.message || "Erreur lors de l'enregistrement";
            toast.error(msg);
        }
    };

    const openDelete = (ufr) => {
        setUfrToDelete(ufr);
        setIsConfirmOpen(true);
    };

    const handleDelete = async () => {
        if (!ufrToDelete) return;
        try {
            await api.delete(`/ufrs/${ufrToDelete.id}`);
            toast.success('UFR supprimee');
            fetchUfrs(page);
        } catch (err) {
            const msg = err.response?.data?.message || 'Erreur lors de la suppression';
            toast.error(msg);
        } finally {
            setIsConfirmOpen(false);
            setUfrToDelete(null);
        }
    };

    const getColor = (id) => COLORS[(id || 0) % COLORS.length];

    const formFields = (
        <>
            <label className="form-label">Nom</label>
            <input required className="input-field" value={formData.name} onChange={e => setFormData({ ...formData, name: e.target.value })} />
            <label className="form-label">Code</label>
            <input required className="input-field" value={formData.code} onChange={e => setFormData({ ...formData, code: e.target.value })} />
            <label className="form-label">Description</label>
            <textarea className="input-field" rows="2" value={formData.description} onChange={e => setFormData({ ...formData, description: e.target.value })} />
            <label className="form-label">Chef d'UFR</label>
            <select className="input-field" value={formData.head_id} onChange={e => setFormData({ ...formData, head_id: e.target.value })}>
                <option value="">Aucun</option>
                {users.map(u => (
                    <option key={u.id} value={u.user?.id || u.id}>
                        {u.user?.name || u.name || u.user?.email || u.email}
                    </option>
                ))}
            </select>
        </>
    );

    return (
        <div className="min-h-screen">
            <div className="max-w-[1400px] mx-auto space-y-6 px-2 sm:px-4">
                <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 pt-2">
                    <div>
                        <div className="flex items-center gap-3 mb-1">
                            <div className="w-10 h-10 rounded-xl bg-gradient-to-br from-violet-500 to-purple-600 flex items-center justify-center shadow-lg shadow-violet-500/25">
                                <GraduationCap size={20} className="text-white" />
                            </div>
                            <h1 className="text-2xl font-bold text-slate-900 tracking-tight">UFR</h1>
                        </div>
                        <p className="text-slate-400 text-sm ml-[52px]">Unites de Formation et de Recherche</p>
                    </div>
                    {canManage && (
                        <button onClick={openCreate}
                            className="flex items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-violet-600 to-purple-700 text-white rounded-xl font-semibold text-sm shadow-lg shadow-violet-500/25 hover:shadow-violet-500/40 transition-all duration-300 active:scale-[0.97]">
                            <Plus size={18} strokeWidth={2.5} />
                            <span>Ajouter une UFR</span>
                        </button>
                    )}
                </div>

                <div className="bg-white rounded-2xl border border-slate-100 p-3">
                    <div className="relative flex-1">
                        <Search className="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-300" size={16} />
                        <input
                            className="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-100 rounded-xl text-sm text-slate-700 placeholder:text-slate-300 focus:outline-none focus:border-violet-400 focus:ring-2 focus:ring-violet-500/10 transition-all"
                            placeholder="Rechercher une UFR..."
                            value={searchTerm}
                            onChange={e => setSearchTerm(e.target.value)}
                        />
                    </div>
                </div>

                {loading ? (
                    <div className="flex justify-center py-20">
                        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-violet-600" />
                    </div>
                ) : ufrs.length === 0 ? (
                    <div className="flex flex-col items-center justify-center py-24">
                        <div className="w-16 h-16 bg-slate-100 rounded-2xl flex items-center justify-center mb-4">
                            <GraduationCap size={28} className="text-slate-300" />
                        </div>
                        <p className="text-base font-bold text-slate-400 mb-1">Aucune UFR trouvee</p>
                        <p className="text-xs text-slate-300">{searchTerm ? 'Essayez de modifier votre recherche' : 'Commencez par ajouter une UFR'}</p>
                    </div>
                ) : (
                    <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 mb-6">
                        {ufrs.map(ufr => {
                            const color = getColor(ufr.id);
                            return (
                                <div key={ufr.id}
                                    className="group relative bg-white rounded-2xl p-5 border border-slate-100 shadow-sm hover:shadow-lg hover:border-violet-200 transition-all duration-300">
                                    <div className="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-violet-400 to-purple-500 opacity-0 group-hover:opacity-100 transition-opacity rounded-t-2xl" />

                                    <div className="flex items-start justify-between mb-3">
                                        <div className="flex items-center gap-3">
                                            <div className={`w-11 h-11 rounded-xl bg-gradient-to-br ${color.bg} flex items-center justify-center text-white font-bold text-sm shadow-md`}>
                                                {ufr.code?.substring(0, 2)?.toUpperCase() || 'UF'}
                                            </div>
                                            <div className="min-w-0">
                                                <h3 className="font-bold text-slate-900 text-sm truncate group-hover:text-violet-600 transition-colors">{ufr.name}</h3>
                                                <p className="text-[10px] font-mono font-semibold text-slate-400">{ufr.code}</p>
                                            </div>
                                        </div>
                                        {canManage && (
                                            <div className="flex items-center gap-1">
                                                <button onClick={() => openEdit(ufr)} className="p-1.5 rounded-lg text-slate-300 hover:text-violet-500 hover:bg-violet-50 transition-all">
                                                    <Edit size={14} />
                                                </button>
                                                <button onClick={() => openDelete(ufr)} className="p-1.5 rounded-lg text-slate-300 hover:text-red-500 hover:bg-red-50 transition-all">
                                                    <Trash2 size={14} />
                                                </button>
                                            </div>
                                        )}
                                    </div>

                                    {ufr.description && (
                                        <p className="text-xs text-slate-500 mb-3 line-clamp-2">{ufr.description}</p>
                                    )}

                                    <div className="flex items-center gap-2 pt-3 border-t border-slate-100">
                                        <span className="inline-flex items-center px-2.5 py-1 rounded-lg text-[10px] font-bold uppercase bg-violet-50 text-violet-600">
                                            <GraduationCap size={10} className="mr-1" />
                                            {ufr.teachers_count ?? 0} enseignant(s)
                                        </span>
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

            <FormModal
                isOpen={showModal}
                onClose={() => { setShowModal(false); setSelectedUfr(null); }}
                title={isEditing ? "Modifier l'UFR" : 'Nouvelle UFR'}
                onSubmit={handleSubmit}
                submitText={isEditing ? 'Enregistrer' : 'Creer'}
            >
                {formFields}
            </FormModal>

            <ConfirmModal
                isOpen={isConfirmOpen}
                onClose={() => { setIsConfirmOpen(false); setUfrToDelete(null); }}
                onConfirm={handleDelete}
                title="Supprimer cette UFR ?"
                message={`L'UFR "${ufrToDelete?.name}" sera definitivement supprimee.`}
                confirmText="Supprimer"
                type="danger"
            />
        </div>
    );
};

export default Ufrs;

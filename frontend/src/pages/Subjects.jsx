import { useEffect, useState } from 'react';
import api from '../services/api';
import { Plus, Search, Edit, Trash2, BookOpen, GraduationCap } from 'lucide-react';
import toast from '../utils/toast';
import ConfirmModal from '../components/ConfirmModal';
import FormModal from '../components/FormModal';
import Pagination from '../components/Pagination';
import useAuthStore from '../store/authStore';
import { hasAnyRole } from '../utils/roles';

const LEVEL_COLORS = {
    L1: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    L2: 'bg-blue-50 text-blue-700 border-blue-200',
    L3: 'bg-purple-50 text-purple-700 border-purple-200',
    M1: 'bg-amber-50 text-amber-700 border-amber-200',
    M2: 'bg-rose-50 text-rose-700 border-rose-200',
};

const Subjects = () => {
    const userAuth = useAuthStore((s) => s.user);
    const canManage = hasAnyRole(userAuth, ['admin', 'academic_manager']);
    const [subjects, setSubjects] = useState([]);
    const [loading, setLoading] = useState(true);
    const [showModal, setShowModal] = useState(false);
    const [isEditing, setIsEditing] = useState(false);
    const [selectedSubject, setSelectedSubject] = useState(null);
    const [searchTerm, setSearchTerm] = useState('');
    const [page, setPage] = useState(1);
    const perPage = 12;
    const [pagination, setPagination] = useState({ current_page: 1, last_page: 1, total: 0 });

    const [isConfirmOpen, setIsConfirmOpen] = useState(false);
    const [subjectToDelete, setSubjectToDelete] = useState(null);

    const [formData, setFormData] = useState({
        name: '', code: '', level: '', credit_hours: '', description: ''
    });

    const fetchSubjects = async (pageNum = 1) => {
        setLoading(true);
        try {
            const params = { page: pageNum, per_page: perPage };
            if (searchTerm) params.search = searchTerm;
            const response = await api.get('/subjects', { params });
            const data = response.data;
            setSubjects(data.data || []);
            setPagination({ current_page: data.current_page, last_page: data.last_page, total: data.total });
        } catch {
            toast.error('Erreur chargement matieres');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchSubjects(page);
    // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [page, searchTerm]);

    useEffect(() => { setPage(1); }, [searchTerm]);

    const handleOpenAdd = () => {
        setIsEditing(false);
        setSelectedSubject(null);
        setFormData({ name: '', code: '', level: '', credit_hours: '', description: '' });
        setShowModal(true);
    };

    const handleOpenEdit = (subject) => {
        setIsEditing(true);
        setSelectedSubject(subject);
        setFormData({
            name: subject.name,
            code: subject.code,
            level: subject.level ?? '',
            credit_hours: subject.credit_hours ?? '',
            description: subject.description ?? ''
        });
        setShowModal(true);
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        try {
            if (isEditing) {
                await api.put(`/subjects/${selectedSubject?.id}`, formData);
                toast.success('Matiere mise a jour');
            } else {
                await api.post('/subjects', formData);
                toast.success('Matiere creee');
            }
            setShowModal(false);
            setSelectedSubject(null);
            setSearchTerm('');
            setPage(1);
            fetchSubjects(1);
        } catch (error) {
            const errors = error.response?.data?.errors;
            const msg = errors
                ? Object.values(errors).flat().join(' ')
                : error.response?.data?.message || "Erreur lors de l'enregistrement";
            toast.error(msg);
        }
    };

    const handleOpenDeleteConfirm = (subject) => {
        setSubjectToDelete(subject);
        setIsConfirmOpen(true);
    };

    const handleDelete = async () => {
        try {
            const res = await api.delete(`/subjects/${subjectToDelete?.id}`);
            toast.success(res.data?.message || 'Matiere supprimee');
            fetchSubjects(page);
        } catch (err) {
            toast.error(err.response?.data?.message || 'Erreur de suppression');
        } finally {
            setIsConfirmOpen(false);
            setSubjectToDelete(null);
        }
    };

    return (
        <div className="min-h-screen">
            <div className="max-w-[1400px] mx-auto space-y-6 px-2 sm:px-4">
                <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 pt-2">
                    <div>
                        <div className="flex items-center gap-3 mb-1">
                            <div className="w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center shadow-lg shadow-indigo-500/25">
                                <BookOpen size={20} className="text-white" />
                            </div>
                            <h1 className="text-2xl font-bold text-slate-900 tracking-tight">Matieres</h1>
                        </div>
                        <p className="text-slate-400 text-sm ml-[52px]">Catalogue des enseignements et modules</p>
                    </div>
                    {canManage && (
                        <button onClick={handleOpenAdd}
                            className="flex items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-indigo-600 to-purple-700 text-white rounded-xl font-semibold text-sm shadow-lg shadow-indigo-500/25 hover:shadow-indigo-500/40 transition-all duration-300 active:scale-[0.97]">
                            <Plus size={18} strokeWidth={2.5} />
                            <span>Ajouter Module</span>
                        </button>
                    )}
                </div>

                <div className="bg-white rounded-2xl border border-slate-100 p-3">
                    <div className="relative flex-1">
                        <Search className="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-300" size={16} />
                        <input type="text" placeholder="Rechercher une unite d'enseignement..."
                            className="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-100 rounded-xl text-sm text-slate-700 placeholder:text-slate-300 focus:outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-500/10 transition-all"
                            value={searchTerm} onChange={(e) => setSearchTerm(e.target.value)} />
                    </div>
                </div>

                {loading ? (
                    <div className="flex justify-center py-20">
                        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600" />
                    </div>
                ) : subjects.length === 0 ? (
                    <div className="flex flex-col items-center justify-center py-24">
                        <div className="w-16 h-16 bg-slate-100 rounded-2xl flex items-center justify-center mb-4">
                            <BookOpen size={28} className="text-slate-300" />
                        </div>
                        <p className="text-base font-bold text-slate-400 mb-1">Aucune matiere</p>
                        <p className="text-xs text-slate-300">{searchTerm ? 'Essayez de modifier votre recherche' : 'Commencez par ajouter un module'}</p>
                    </div>
                ) : (
                    <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 mb-6">
                        {subjects.map((s) => {
                            const levelKey = (s.level ?? '').toUpperCase();
                            const levelClass = LEVEL_COLORS[levelKey] || 'bg-gray-50 text-gray-600 border-gray-200';
                            return (
                                <div key={s.id}
                                    className="group relative bg-white rounded-2xl p-5 border border-slate-100 shadow-sm hover:shadow-lg hover:border-indigo-200 transition-all duration-300">
                                    <div className="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-indigo-400 to-purple-500 opacity-0 group-hover:opacity-100 transition-opacity rounded-t-2xl" />

                                    <div className="flex items-start justify-between mb-4">
                                        <div className="w-11 h-11 rounded-xl bg-gradient-to-br from-indigo-50 to-purple-50 text-indigo-600 flex items-center justify-center border border-indigo-100 shadow-sm">
                                            <GraduationCap size={20} />
                                        </div>
                                        <div className="flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                            {canManage && (
                                            <>
                                            <button onClick={() => handleOpenEdit(s)}
                                                className="p-1.5 rounded-lg text-slate-300 hover:text-indigo-500 hover:bg-indigo-50 transition-all">
                                                <Edit size={14} />
                                            </button>
                                            <button onClick={() => handleOpenDeleteConfirm(s)}
                                                className="p-1.5 rounded-lg text-slate-300 hover:text-red-500 hover:bg-red-50 transition-all">
                                                <Trash2 size={14} />
                                            </button>
                                            </>
                                            )}
                                        </div>
                                    </div>

                                    <h3 className="font-bold text-slate-900 text-sm group-hover:text-indigo-600 transition-colors mb-3 line-clamp-2">{s.name}</h3>

                                    <div className="flex flex-wrap gap-1.5 mb-3">
                                        <span className="inline-flex items-center px-2.5 py-1 rounded-lg text-[10px] font-black uppercase tracking-wider border bg-gray-50 text-gray-600 border-gray-200">
                                            {s.code}
                                        </span>
                                        <span className={`inline-flex items-center px-2.5 py-1 rounded-lg text-[10px] font-black uppercase tracking-wider border ${levelClass}`}>
                                            {s.level || 'Niveau'}
                                        </span>
                                        {s.credit_hours != null && s.credit_hours !== '' && (
                                            <span className="inline-flex items-center px-2.5 py-1 rounded-lg text-[10px] font-black uppercase tracking-wider border bg-amber-50 text-amber-700 border-amber-200">
                                                {s.credit_hours} Credits
                                            </span>
                                        )}
                                    </div>

                                    {s.description && (
                                        <p className="text-xs text-slate-500 leading-relaxed line-clamp-2 border-t border-slate-100 pt-3">{s.description}</p>
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
                onClose={() => setShowModal(false)}
                onSubmit={handleSubmit}
                title={isEditing ? "Editer l'UE" : 'Nouvelle Matiere'}
                subtitle="Conception du programme academique"
                submitText="Valider & Publier"
            >
                <div className="space-y-6">
                    <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-5">
                        <div className="col-span-2 form-group">
                            <label className="form-label">Libelle complet</label>
                            <input required type="text" className="input-field"
                                value={formData.name} onChange={e => setFormData({...formData, name: e.target.value})} />
                        </div>
                        <div className="form-group">
                            <label className="form-label">Code UE</label>
                            <input required type="text" className="input-field" placeholder="EX: MTH101"
                                value={formData.code} onChange={e => setFormData({...formData, code: e.target.value})} />
                        </div>
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <div className="form-group">
                            <label className="form-label">Niveau</label>
                            <select required className="select-field"
                                value={formData.level} onChange={e => setFormData({...formData, level: e.target.value})}>
                                <option value="">Selectionner un niveau</option>
                                <option value="L1">L1 — Licence 1</option>
                                <option value="L2">L2 — Licence 2</option>
                                <option value="L3">L3 — Licence 3</option>
                                <option value="M1">M1 — Master 1</option>
                                <option value="M2">M2 — Master 2</option>
                            </select>
                        </div>
                        <div className="form-group">
                            <label className="form-label">Credits</label>
                            <input type="number" min="0" className="input-field" placeholder="Ex: 3"
                                value={formData.credit_hours} onChange={e => setFormData({...formData, credit_hours: e.target.value === '' ? '' : Number(e.target.value)})} />
                        </div>
                    </div>
                    <div className="form-group">
                        <label className="form-label">Description</label>
                        <textarea className="textarea-field" rows="3"
                            value={formData.description} onChange={e => setFormData({...formData, description: e.target.value})}></textarea>
                    </div>
                </div>
            </FormModal>

            <ConfirmModal
                isOpen={isConfirmOpen}
                onClose={() => setIsConfirmOpen(false)}
                onConfirm={handleDelete}
                title="Suppression Definitive"
                message={`Voulez-vous supprimer la matiere academique "${subjectToDelete?.name}" ?`}
                type="danger"
            />
        </div>
    );
};

export default Subjects;

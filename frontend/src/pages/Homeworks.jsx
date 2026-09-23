import { useCallback, useEffect, useState } from 'react';
import api from '../services/api';
import { dataRefresh } from '../services/dataRefresh';
import useAuthStore from '../store/authStore';
import { getUserRoles } from '../utils/roles';
import { Plus, Search, HelpCircle, FileText, Calendar, User, Edit, Trash2, Lock, CheckCircle, ClipboardList, Clock, X, CalendarClock } from 'lucide-react';
import toast from '../utils/toast';
import ConfirmModal from '../components/ConfirmModal';
import FormModal from '../components/FormModal';
import Pagination from '../components/Pagination';

const Homeworks = () => {
    const user = useAuthStore((state) => state.user);
    const [homeworks, setHomeworks] = useState([]);
    const [subjects, setSubjects] = useState([]);
    const [teacherSubjectIds, setTeacherSubjectIds] = useState([]);
    const [teachers, setTeachers] = useState([]);
    const [classrooms, setClassrooms] = useState([]);
    const [loading, setLoading] = useState(true);
    const [showModal, setShowModal] = useState(false);
    const [isEditing, setIsEditing] = useState(false);
    const [selectedHomework, setSelectedHomework] = useState(null);
    const [searchTerm, setSearchTerm] = useState('');
    const [page, setPage] = useState(1);
    const perPage = 12;
    const [pagination, setPagination] = useState({ current_page: 1, last_page: 1, total: 0 });
    const [confirmDelete, setConfirmDelete] = useState({ isOpen: false, id: null });
    const [actionConfirm, setActionConfirm] = useState({ isOpen: false, title: '', message: '', onConfirm: () => {} });
    const [showReschedule, setShowReschedule] = useState(false);
    const [rescheduleHomework, setRescheduleHomework] = useState(null);
    const [rescheduleData, setRescheduleData] = useState({ scheduled_date: '', start_time: '08:00', end_time: '09:00' });
    const [showGrades, setShowGrades] = useState(false);
    const [gradingHomework, setGradingHomework] = useState(null);
    const [gradeRows, setGradeRows] = useState([]);
    const [gradesLoading, setGradesLoading] = useState(false);

    const userRoles = getUserRoles(user);
    const isAdmin = userRoles.includes('admin');
    const isAcademic = userRoles.includes('academic_manager') || isAdmin || userRoles.includes('hr_manager') || userRoles.includes('secretary');
    const isTeacher = userRoles.includes('teacher') && !isAcademic;
    const canCreateHomework = isTeacher || isAcademic;
    const currentUserId = user?.id;
    const currentTeacherId = user?.teacher?.id;

    const [formData, setFormData] = useState({
        title: '', description: '', teacher_id: '', subject_id: '', classroom_id: '',
        scheduled_date: '', start_time: '08:00', end_time: '09:00', max_score: 20
    });

    useEffect(() => {
        const controller = new AbortController();
        let cancelled = false;
        const load = async () => {
            try {
                const requests = [
                    api.get('/subjects', { signal: controller.signal }),
                    api.get('/classrooms', { signal: controller.signal }),
                ];
                if (isAcademic) requests.push(api.get('/teachers', { signal: controller.signal }));
                if (isTeacher && currentTeacherId) requests.push(api.get('/schedules', { params: { per_page: 500 }, signal: controller.signal }));
                const results = await Promise.all(requests);
                if (!cancelled) {
                    const s = results[0].data;
                    setSubjects(Array.isArray(s) ? s : (s?.data || []));
                    const c = results[1].data;
                    setClassrooms(Array.isArray(c) ? c : (c?.data || []));
                    if (results[2]) { const t = results[2].data; setTeachers(Array.isArray(t) ? t : (t?.data || [])); }
                    const scheduleResult = isAcademic ? results[3] : results[2];
                    if (isTeacher && scheduleResult) {
                        const schedules = scheduleResult.data?.data || scheduleResult.data || [];
                        setTeacherSubjectIds([...new Set(schedules.map(schedule => schedule.subject_id).filter(Boolean))]);
                    }
                }
            } catch { /* reference data is optional while the list remains usable */ }
        };
        load();
        return () => { cancelled = true; controller.abort(); };
    }, [isAcademic, isTeacher, currentTeacherId]);

    const fetchHomeworks = useCallback(async (pageNum = 1) => {
        setLoading(true);
        try {
            const params = { page: pageNum, per_page: perPage };
            if (searchTerm) params.search = searchTerm;
            if (isTeacher && currentTeacherId) params.teacher_id = currentTeacherId;
            const response = await api.get('/homeworks', { params });
            const data = response.data;
            setHomeworks(data.data || []);
            setPagination({ current_page: data.current_page, last_page: data.last_page, total: data.total });
        } catch {
            toast.error('Erreur chargement devoirs');
        } finally {
            setLoading(false);
        }
    }, [isTeacher, currentTeacherId, searchTerm]);

    useEffect(() => {
        fetchHomeworks(page);
    }, [page, fetchHomeworks]);

    useEffect(() => { setPage(1); }, [searchTerm]);

    useEffect(() => {
        const unsubscribe = dataRefresh.subscribe('homeworks', () => fetchHomeworks(page));
        return unsubscribe;
    }, [page, fetchHomeworks]);

    const canEditHomework = (hw) => {
        if (isAcademic) return !['completed', 'cancelled'].includes(hw.status);
        if (isTeacher && hw.status === 'planned' && (hw.teacher?.user?.id === currentUserId || hw.teacher_id === currentTeacherId)) return true;
        return false;
    };

    // La saisie des notes sera activée avec le module étudiants.
    const canManageGrades = () => false;

    const isHomeworkDateReached = (homework) => {
        if (!homework.scheduled_date) return false;
        const scheduledDate = String(homework.scheduled_date).split('T')[0].split(' ')[0];
        const today = new Date().toISOString().split('T')[0];
        return scheduledDate <= today;
    };

    const openGrades = async (hw) => {
        setGradingHomework(hw);
        setShowGrades(true);
        setGradesLoading(true);
        try {
            const response = await api.get(`/homeworks/${hw.id}/grades`);
            const existing = Array.isArray(response.data) ? response.data : [];
            setGradeRows(existing.length > 0
                ? existing.map(g => ({ student_id: g.student_id ?? '', student_name: g.student_name ?? '', score: g.score ?? '' }))
                : [{ student_id: '', student_name: '', score: '' }]);
        } catch {
            toast.error('Erreur chargement des notes');
            setGradeRows([{ student_id: '', student_name: '', score: '' }]);
        } finally {
            setGradesLoading(false);
        }
    };

    const updateGradeRow = (index, field, value) => {
        setGradeRows(rows => rows.map((r, i) => (i === index ? { ...r, [field]: value } : r)));
    };

    const addGradeRow = () => {
        setGradeRows(rows => [...rows, { student_id: '', student_name: '', score: '' }]);
    };

    const removeGradeRow = (index) => {
        setGradeRows(rows => rows.filter((_, i) => i !== index));
    };

    const handleSaveGrades = async (e) => {
        e.preventDefault();
        if (!gradingHomework) return;
        const validRows = gradeRows.filter(r => (r.student_id ?? '').trim() !== '' && (r.student_name ?? '').trim() !== '' && (r.score ?? '').trim() !== '');
        if (validRows.length === 0) {
            toast.error('Ajoutez au moins un etudiant avec son matricule, son nom et sa note');
            return;
        }
        const scores = validRows.map(r => Number(r.score));
        if (scores.some(s => Number.isNaN(s) || s < 0)) {
            toast.error('Les notes doivent etre des nombres positifs');
            return;
        }
        try {
            await api.post(`/homeworks/${gradingHomework.id}/grades`, { grades: validRows });
            toast.success('Notes enregistrees');
            setShowGrades(false);
            setGradingHomework(null);
            fetchHomeworks(page);
        } catch {
            toast.error("Erreur lors de l'enregistrement des notes");
        }
    };

    const handleOpenAdd = () => {
        setIsEditing(false);
        setFormData({
            title: '', description: '', teacher_id: isTeacher ? currentTeacherId : '',
            subject_id: '', classroom_id: '', scheduled_date: '',
            start_time: '08:00', end_time: '09:00', max_score: 20
        });
        setShowModal(true);
    };

    const handleOpenEdit = (hw) => {
        if (!hw) return;
        setIsEditing(true);
        setSelectedHomework(hw);
        let formattedDate = '';
        if (hw.scheduled_date && typeof hw.scheduled_date === 'string') {
            formattedDate = hw.scheduled_date.split(' ')[0];
        } else if (hw.scheduled_date) {
            try { formattedDate = new Date(hw.scheduled_date).toISOString().split('T')[0]; } catch { formattedDate = ''; }
        }
        setFormData({
            title: hw.title ?? '', description: hw.description ?? '',
            teacher_id: hw.teacher_id ?? '', subject_id: hw.subject_id ?? '',
            classroom_id: hw.classroom_id ?? '', scheduled_date: formattedDate,
            start_time: hw.start_time || '08:00', end_time: hw.end_time || '09:00',
            max_score: hw.max_score ?? 20
        });
        setShowModal(true);
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        try {
            if (formData.end_time <= formData.start_time) {
                toast.error("L'heure de fin doit etre posterieure a l'heure de debut");
                return;
            }
            if (isEditing) {
                await api.put(`/homeworks/${selectedHomework.id}`, formData);
                toast.success('Devoir mis a jour');
            } else {
                await api.post('/homeworks', formData);
                toast.success(isTeacher ? 'Devoir soumis à validation' : 'Devoir créé');
            }
            await fetchHomeworks(1);
            setShowModal(false);
            setSelectedHomework(null);
            setFormData({
                title: '', description: '', teacher_id: isTeacher ? currentTeacherId : '',
                subject_id: '', classroom_id: '', scheduled_date: '',
                start_time: '08:00', end_time: '09:00', max_score: 20
            });
            setSearchTerm('');
            setPage(1);
        } catch {
            toast.error('Erreur lors de la publication');
        }
    };

    const handleApprove = async (id) => {
        try {
            await api.patch(`/homeworks/${id}/approve`);
            toast.success('Devoir approuve');
            fetchHomeworks(page);
        } catch {
            toast.error("Erreur d'approbation");
        }
    };

    const handleComplete = async (id) => {
        try {
            await api.patch(`/homeworks/${id}/complete`);
            toast.success('Devoir marqué comme effectué');
            fetchHomeworks(page);
        } catch (error) {
            toast.error(error.response?.data?.message || 'Impossible de clôturer le devoir');
        }
    };

    const handleCancel = async (id) => {
        const reason = window.prompt('Motif de l’annulation (optionnel) :');
        if (reason === null) return;
        try {
            await api.patch(`/homeworks/${id}/cancel`, { reason: reason.trim() || null });
            toast.success('Devoir annulé');
            fetchHomeworks(page);
        } catch (error) {
            toast.error(error.response?.data?.message || 'Impossible d’annuler le devoir');
        }
    };

    const openReschedule = (homework) => {
        setRescheduleHomework(homework);
        setRescheduleData({
            scheduled_date: homework.scheduled_date ? String(homework.scheduled_date).split('T')[0].split(' ')[0] : '',
            start_time: homework.start_time?.substring(11, 16) || '08:00',
            end_time: homework.end_time?.substring(11, 16) || '09:00',
        });
        setShowReschedule(true);
    };

    const handleReschedule = async (event) => {
        event.preventDefault();
        try {
            await api.patch(`/homeworks/${rescheduleHomework.id}/reschedule`, rescheduleData);
            toast.success('Devoir reporté');
            setShowReschedule(false);
            setRescheduleHomework(null);
            fetchHomeworks(page);
        } catch (error) {
            toast.error(error.response?.data?.message || 'Impossible de reporter le devoir');
        }
    };

    const handleDelete = async (id) => {
        try {
            await api.delete(`/homeworks/${id}`);
            toast.success('Devoir supprime');
            fetchHomeworks(page);
        } catch {
            toast.error('Erreur de suppression');
        }
    };

    const statusConfig = {
        approved: { bg: 'bg-emerald-50', text: 'text-emerald-700', border: 'border-emerald-200', label: 'Approuve', dot: 'bg-emerald-500' },
        completed: { bg: 'bg-blue-50', text: 'text-blue-700', border: 'border-blue-200', label: 'Effectue', dot: 'bg-blue-500' },
        cancelled: { bg: 'bg-red-50', text: 'text-red-700', border: 'border-red-200', label: 'Annule', dot: 'bg-red-500' },
        planned: { bg: 'bg-amber-50', text: 'text-amber-700', border: 'border-amber-200', label: 'Planifie', dot: 'bg-amber-500' },
    };

    return (
        <div className="min-h-screen">
            <div className="max-w-[1400px] mx-auto space-y-6 px-2 sm:px-4">
                <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 pt-2">
                    <div>
                        <div className="flex items-center gap-3 mb-1">
                            <div className="w-10 h-10 rounded-xl bg-gradient-to-br from-purple-500 to-pink-600 flex items-center justify-center shadow-lg shadow-purple-500/25">
                                <FileText size={20} className="text-white" />
                            </div>
                            <h1 className="text-2xl font-bold text-slate-900 tracking-tight">
                                {isTeacher ? 'Mes Cours & Devoirs' : 'Gestion des Devoirs'}
                            </h1>
                        </div>
                        <p className="text-slate-400 text-sm ml-[52px]">
                            {isTeacher ? 'Vos ressources pedagogiques et publications' : 'Plateforme de diffusion et validation des supports pedagogiques'}
                        </p>
                    </div>
                    {canCreateHomework && (
                        <button onClick={handleOpenAdd}
                            className="flex items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-purple-600 to-pink-700 text-white rounded-xl font-semibold text-sm shadow-lg shadow-purple-500/25 hover:shadow-purple-500/40 transition-all duration-300 active:scale-[0.97]">
                            <Plus size={18} strokeWidth={2.5} />
                            <span>{isTeacher ? 'Creer un Devoir' : 'Gerer les Devoirs'}</span>
                        </button>
                    )}
                </div>

                <div className="bg-white rounded-2xl border border-slate-100 p-3">
                    <div className="relative flex-1">
                        <Search className="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-300" size={16} />
                        <input type="text" placeholder="Rechercher un devoir, un cours..."
                            className="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-100 rounded-xl text-sm text-slate-700 placeholder:text-slate-300 focus:outline-none focus:border-purple-400 focus:ring-2 focus:ring-purple-500/10 transition-all"
                            value={searchTerm} onChange={(e) => setSearchTerm(e.target.value)} />
                    </div>
                </div>

                {loading ? (
                    <div className="flex justify-center py-20">
                        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-purple-600" />
                    </div>
                ) : homeworks.length === 0 ? (
                    <div className="flex flex-col items-center justify-center py-24">
                        <div className="w-16 h-16 bg-slate-100 rounded-2xl flex items-center justify-center mb-4">
                            <FileText size={28} className="text-slate-300" />
                        </div>
                        <p className="text-base font-bold text-slate-400 mb-1">
                            {searchTerm ? 'Aucun resultat pour cette recherche' : (isTeacher ? "Vous n'avez pas encore cree de devoirs" : 'Aucun devoir publie')}
                        </p>
                    </div>
                ) : (
                    <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 mb-6">
                        {homeworks.map((hw) => {
                            const sc = statusConfig[hw.status] || statusConfig.planned;
                            return (
                                <div key={hw.id}
                                    className="group relative bg-white rounded-2xl p-5 border border-slate-100 shadow-sm hover:shadow-lg hover:border-purple-200 transition-all duration-300 overflow-hidden">
                                    <div className={`absolute top-0 left-0 right-0 h-1 bg-gradient-to-r ${hw.status === 'approved' ? 'from-emerald-400 to-teal-500' : 'from-amber-400 to-orange-500'} opacity-100 rounded-t-2xl`} />

                                    <div className="flex items-start justify-between mb-4">
                                        <div className="w-11 h-11 rounded-xl bg-gradient-to-br from-purple-50 to-pink-50 text-purple-600 flex items-center justify-center border border-purple-100 shadow-sm">
                                            <FileText size={20} />
                                        </div>
                                        <div className="flex gap-1">
                                             {isAcademic && hw.status === 'planned' && (
                                                 <button onClick={() => handleApprove(hw.id)}
                                                     className="p-1.5 rounded-lg text-slate-300 hover:text-emerald-500 hover:bg-emerald-50 transition-all" title="Approuver">
                                                     <CheckCircle size={14}/>
                                                 </button>
                                             )}
                                             {isAcademic && hw.status === 'approved' && isHomeworkDateReached(hw) && (
                                                 <button onClick={() => setActionConfirm({ isOpen: true, title: 'Confirmer la fin du devoir', message: 'Le devoir est-il bien terminé ?', onConfirm: () => handleComplete(hw.id) })}
                                                     className="p-1.5 rounded-lg text-slate-300 hover:text-blue-500 hover:bg-blue-50 transition-all" title="Marquer comme effectué">
                                                     <CheckCircle size={14}/>
                                                 </button>
                                             )}
                                             {isAcademic && ['planned', 'approved'].includes(hw.status) && (
                                                 <>
                                                     <button onClick={() => openReschedule(hw)} className="p-1.5 rounded-lg text-slate-300 hover:text-amber-500 hover:bg-amber-50 transition-all" title="Reporter">
                                                         <CalendarClock size={14}/>
                                                     </button>
                                                     <button onClick={() => setActionConfirm({ isOpen: true, title: 'Annuler le devoir', message: 'Voulez-vous vraiment annuler ce devoir ?', onConfirm: () => handleCancel(hw.id) })}
                                                         className="p-1.5 rounded-lg text-slate-300 hover:text-red-500 hover:bg-red-50 transition-all" title="Annuler">
                                                         <X size={14}/>
                                                     </button>
                                                 </>
                                             )}
                                             {canManageGrades(hw) && (
                                             <button onClick={() => openGrades(hw)} className="p-1.5 rounded-lg text-slate-300 hover:text-blue-500 hover:bg-blue-50 transition-all" title="Gerer les notes"><ClipboardList size={14}/></button>
                                             )}
                                             {canEditHomework(hw) && (
                                             <>
                                             <button onClick={() => handleOpenEdit(hw)} className="p-1.5 rounded-lg text-slate-300 hover:text-amber-500 hover:bg-amber-50 transition-all"><Edit size={14}/></button>
                                             <button onClick={() => setConfirmDelete({ isOpen: true, id: hw.id })} className="p-1.5 rounded-lg text-slate-300 hover:text-red-500 hover:bg-red-50 transition-all"><Trash2 size={14}/></button>
                                             </>
                                             )}
                                             {!canManageGrades(hw) && !canEditHomework(hw) && !isAcademic && (
                                                <div className="p-1.5 text-slate-300 flex items-center bg-slate-50 rounded-lg">
                                                    <Lock size={14} />
                                                </div>
                                            )}
                                        </div>
                                    </div>

                                    <h3 className="font-bold text-slate-900 text-sm group-hover:text-purple-600 transition-colors mb-2 uppercase line-clamp-1">{hw.title}</h3>

                                    <div className="flex flex-wrap gap-1.5 mb-3">
                                        <span className="inline-flex items-center px-2.5 py-1 rounded-lg text-[10px] font-black uppercase tracking-wider border bg-blue-50 text-blue-600 border-blue-200">
                                            {hw.subject?.name || 'Matiere'}
                                        </span>
                                        {hw.status && (
                                            <span className={`inline-flex items-center px-2.5 py-1 rounded-lg text-[10px] font-black uppercase tracking-wider border ${sc.bg} ${sc.text} ${sc.border}`}>
                                                <span className={`w-1.5 h-1.5 rounded-full ${sc.dot} mr-1.5`}></span>
                                                {sc.label}
                                            </span>
                                        )}
                                    </div>

                                    <p className="text-xs text-slate-400 line-clamp-2 mb-4">{hw.description || "Consignes et ressources pour le cours."}</p>

                                    <div className="pt-3 border-t border-slate-100 space-y-2">
                                        <div className="flex items-center justify-between text-[10px] font-bold text-slate-400">
                                            <div className="flex items-center gap-1">
                                                <Calendar size={12} className="text-purple-400" />
                                                <span>{hw.scheduled_date ? new Date(hw.scheduled_date).toLocaleDateString() : 'Non definie'}</span>
                                            </div>
                                            <span className="text-purple-600 font-black">/ {hw.max_score} PTS</span>
                                        </div>
                                        <div className="flex items-center text-[10px] text-slate-400 font-bold bg-slate-50 p-2 rounded-xl">
                                            <User size={12} className="mr-2 text-blue-500" />
                                            <span className="truncate">{hw.teacher?.user?.name || 'Enseignant'}</span>
                                        </div>
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

                <FormModal
                    isOpen={showModal}
                    onClose={() => setShowModal(false)}
                    onSubmit={handleSubmit}
                    title={isEditing ? 'Editer' : 'Publication'}
                    subtitle="Creation et gestion des devoirs"
                    size="lg"
                    submitText="Confirmer"
                >
                    <div className="space-y-5">
                        <div className="form-group">
                            <label className="form-label">Titre de l'activite</label>
                            <input required type="text" className="input-field"
                                value={formData.title} onChange={e => setFormData({...formData, title: e.target.value})} />
                        </div>
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <div className="form-group">
                                <label className="form-label">Matiere</label>
                                <select required className="select-field"
                                    value={formData.subject_id} onChange={e => setFormData({...formData, subject_id: e.target.value})}>
                                    <option value="">Choisir...</option>
                                    {subjects.filter(s => !isTeacher || teacherSubjectIds.includes(s.id)).map(s => <option key={s.id} value={s.id}>{s.name}</option>)}
                                </select>
                            </div>
                            <div className="form-group">
                                <label className="form-label">Classe</label>
                                <select required className="select-field"
                                    value={formData.classroom_id} onChange={e => setFormData({...formData, classroom_id: e.target.value})}>
                                    <option value="">Selectionner...</option>
                                    {classrooms.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
                                </select>
                            </div>
                        </div>
                        {isAcademic && (
                            <div className="form-group">
                                <label className="form-label">Enseignant</label>
                                <select required className="select-field"
                                    value={formData.teacher_id} onChange={e => setFormData({...formData, teacher_id: e.target.value})}>
                                    <option value="">Selectionner...</option>
                                    {teachers.map(t => <option key={t.id} value={t.id}>{t.user?.name}</option>)}
                                </select>
                            </div>
                        )}
                        <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-5">
                            <div className="form-group">
                                <label className="form-label">Date</label>
                                <input required type="date" className="input-field"
                                    value={formData.scheduled_date} onChange={e => setFormData({...formData, scheduled_date: e.target.value})} />
                            </div>
                            <div className="form-group">
                                <label className="form-label">Debut</label>
                                <input required type="time" className="input-field"
                                    value={formData.start_time} onChange={e => setFormData({...formData, start_time: e.target.value})} />
                            </div>
                            <div className="form-group">
                                <label className="form-label">Fin</label>
                                <input required type="time" className="input-field"
                                    value={formData.end_time} onChange={e => setFormData({...formData, end_time: e.target.value})} />
                            </div>
                        </div>
                        <div className="form-group">
                            <label className="form-label">Total points</label>
                            <input required type="number" min="1" className="input-field"
                                value={formData.max_score} onChange={e => setFormData({...formData, max_score: e.target.value === '' ? '' : Number(e.target.value)})} />
                        </div>
                        <div className="form-group">
                            <label className="form-label">Consignes & Details</label>
                            <textarea required className="textarea-field" rows="4"
                                value={formData.description} onChange={e => setFormData({...formData, description: e.target.value})}></textarea>
                        </div>
                    </div>
                </FormModal>

                <FormModal
                    isOpen={showReschedule}
                    onClose={() => setShowReschedule(false)}
                    onSubmit={handleReschedule}
                    title="Reporter le devoir"
                    subtitle={rescheduleHomework ? rescheduleHomework.title : ''}
                    submitText="Confirmer le report"
                >
                    <div className="space-y-5">
                        <div className="form-group">
                            <label className="form-label">Nouvelle date</label>
                            <input required type="date" className="input-field" value={rescheduleData.scheduled_date} onChange={e => setRescheduleData({ ...rescheduleData, scheduled_date: e.target.value })} />
                        </div>
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <div className="form-group">
                                <label className="form-label">Début</label>
                                <input required type="time" className="input-field" value={rescheduleData.start_time} onChange={e => setRescheduleData({ ...rescheduleData, start_time: e.target.value })} />
                            </div>
                            <div className="form-group">
                                <label className="form-label">Fin</label>
                                <input required type="time" className="input-field" value={rescheduleData.end_time} onChange={e => setRescheduleData({ ...rescheduleData, end_time: e.target.value })} />
                            </div>
                        </div>
                    </div>
                </FormModal>

                <FormModal
                    isOpen={showGrades}
                    onClose={() => setShowGrades(false)}
                    onSubmit={handleSaveGrades}
                    title="Saisie des notes"
                    subtitle={gradingHomework ? `Devoir : ${gradingHomework.title} - bareme / ${gradingHomework.max_score ?? 20} points` : ''}
                    size="full"
                    submitText="Enregistrer les notes"
                >
                    {gradesLoading ? (
                        <div className="py-10 text-center text-gray-400 text-sm font-bold uppercase tracking-widest">
                            Chargement des notes...
                        </div>
                    ) : (
                        <div className="space-y-4">
                            <div className="flex items-center justify-between">
                                <p className="text-sm font-bold text-gray-600 uppercase tracking-widest">
                                    Etudiants ({gradeRows.length})
                                </p>
                                <button type="button" onClick={addGradeRow} className="px-4 py-2 border border-gray-200 rounded-xl text-xs font-bold text-gray-600 hover:bg-gray-50 transition-colors">
                                    <Plus size={14} className="inline mr-1" /> Ajouter un etudiant
                                </button>
                            </div>
                            <div className="space-y-3">
                                {gradeRows.map((row, i) => (
                                    <div key={i} className="grid grid-cols-12 gap-3 items-center">
                                        <input type="text" placeholder="Matricule / ID"
                                            className="input-field col-span-3" value={row.student_id}
                                            onChange={e => updateGradeRow(i, 'student_id', e.target.value)} />
                                        <input type="text" placeholder="Nom complet de l'etudiant"
                                            className="input-field col-span-5" value={row.student_name}
                                            onChange={e => updateGradeRow(i, 'student_name', e.target.value)} />
                                        <input type="number" min="0" max={gradingHomework?.max_score ?? 20} step="0.25"
                                            placeholder={`Note / ${gradingHomework?.max_score ?? 20}`}
                                            className="input-field col-span-3" value={row.score}
                                            onChange={e => updateGradeRow(i, 'score', e.target.value)} />
                                        <button type="button" onClick={() => removeGradeRow(i)} className="p-1.5 rounded-lg text-gray-300 hover:text-red-500 hover:bg-red-50 transition-all justify-self-end" title="Retirer la ligne">
                                            <Trash2 size={16}/>
                                        </button>
                                    </div>
                                ))}
                                {gradeRows.length === 0 && (
                                    <p className="text-center text-gray-400 text-sm py-6">Aucune note saisie. Ajoutez un etudiant.</p>
                                )}
                            </div>
                        </div>
                    )}
                </FormModal>

                <ConfirmModal
                    isOpen={confirmDelete.isOpen}
                    onClose={() => setConfirmDelete({ isOpen: false, id: null })}
                    onConfirm={() => { if (confirmDelete.id) handleDelete(confirmDelete.id); setConfirmDelete({ isOpen: false, id: null }); }}
                    title="Supprimer le devoir"
                    message="Voulez-vous vraiment supprimer ce devoir ? Cette action est irreversible."
                    confirmText="Supprimer"
                    type="danger"
                />
                <ConfirmModal
                    isOpen={actionConfirm.isOpen}
                    onClose={() => setActionConfirm({ isOpen: false, title: '', message: '', onConfirm: () => {} })}
                    onConfirm={() => { actionConfirm.onConfirm(); setActionConfirm({ isOpen: false, title: '', message: '', onConfirm: () => {} }); }}
                    title={actionConfirm.title}
                    message={actionConfirm.message}
                    confirmText="Confirmer"
                    type="warning"
                />
            </div>
        </div>
    );
};

export default Homeworks;

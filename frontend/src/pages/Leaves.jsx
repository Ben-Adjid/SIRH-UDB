import { useEffect, useState } from 'react';
import { Check, X, Search, Calendar, User, Plus } from 'lucide-react';
import api from '../services/api';
import toast from '../utils/toast';
import FormModal from '../components/FormModal';
import useAuthStore from '../store/authStore';
import { hasAnyRole } from '../utils/roles';

const STATUS_CONFIG = {
    approved: { label: 'Approuve', color: 'text-emerald-600 bg-emerald-50 border-emerald-100' },
    rejected: { label: 'Rejete', color: 'text-red-600 bg-red-50 border-red-100' },
    pending: { label: 'En attente', color: 'text-amber-600 bg-amber-50 border-amber-100' },
};

const emptyForm = { teacher_id: '', leave_type_id: '', start_date: '', end_date: '', reason: '' };

const Leaves = () => {
    const user = useAuthStore((state) => state.user);
    const canManage = hasAnyRole(user, ['admin', 'hr_manager', 'secretary']);
    const [leaves, setLeaves] = useState([]);
    const [types, setTypes] = useState([]);
    const [teachers, setTeachers] = useState([]);
    const [search, setSearch] = useState('');
    const [loading, setLoading] = useState(true);
    const [showForm, setShowForm] = useState(false);
    const [form, setForm] = useState(emptyForm);
    const [rejecting, setRejecting] = useState(null);
    const [rejectionReason, setRejectionReason] = useState('');
    const [currentTeacherId, setCurrentTeacherId] = useState(null);

    const loadLeaves = async () => {
        setLoading(true);
        try {
            const response = await api.get('/leaves', { params: { per_page: 100, search: search || undefined } });
            const data = response.data;
            setLeaves(Array.isArray(data) ? data : (data.data || []));
        } catch (error) {
            toast.error(error.response?.data?.message || 'Erreur lors du chargement des conges');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        api.get('/leave-types').then((response) => setTypes(response.data || [])).catch(() => toast.error('Types de conges indisponibles'));
    }, []);

    useEffect(() => {
        loadLeaves();
        const loadData = async () => {
            try {
                // Load leave types
                const typesRes = await api.get('/leave-types');
                setTypes(Array.isArray(typesRes.data) ? typesRes.data : (typesRes.data?.data || []));

                // Load teachers (for RH/admin to select)
                const teachersRes = await api.get('/teachers', { params: { per_page: 200 } });
                const teacherList = Array.isArray(teachersRes.data) ? teachersRes.data : (teachersRes.data?.data || []);
                setTeachers(teacherList);

                // If current user is a teacher, find their teacher_id
                if (!canManage && user?.id) {
                    const currentTeacher = teacherList.find(t => t.user_id === user.id);
                    if (currentTeacher) {
                        setCurrentTeacherId(currentTeacher.id);
                        setForm(prev => ({ ...prev, teacher_id: currentTeacher.id }));
                    }
                }
            } catch (error) {
                console.error('Error loading data:', error);
            }
        };
        loadData();
    }, [search]);

    const submitLeave = async (event) => {
        event.preventDefault();
        try {
            await api.post('/leaves', form);
            toast.success('Demande de conge envoyee');
            await loadLeaves();
            setShowForm(false);
            setForm(emptyForm);
        } catch (error) {
            toast.error(error.response?.data?.message || 'Impossible d envoyer la demande');
        }
    };

    const updateStatus = async (leave, status) => {
        if (status === 'rejected' && !rejectionReason.trim()) {
            toast.error('Indiquez le motif du rejet');
            return;
        }
        try {
            await api.patch(`/leaves/${leave.id}/status`, { status, rejection_reason: rejectionReason.trim() || null });
            toast.success(status === 'approved' ? 'Demande approuvee' : 'Demande rejetee');
            loadLeaves();
        } catch (error) {
            toast.error(error.response?.data?.message || 'Impossible de mettre a jour la demande');
        } finally {
            setRejecting(null);
            setRejectionReason('');
        }
    };

    return (
        <div className="min-h-screen">
            <div className="max-w-[1400px] mx-auto space-y-6 px-2 sm:px-4">
                <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 pt-2">
                    <div>
                        <div className="flex items-center gap-3 mb-1">
                            <div className="w-10 h-10 rounded-xl bg-gradient-to-br from-purple-500 to-violet-600 flex items-center justify-center shadow-lg shadow-purple-500/25"><Calendar size={20} className="text-white" /></div>
                            <h1 className="text-2xl font-bold text-slate-900 tracking-tight">Conges & Absences</h1>
                        </div>
                        <p className="text-slate-400 text-sm ml-[52px]">Suivi des demandes et de leur validation</p>
                    </div>
                    <button onClick={() => setShowForm(true)} className="flex items-center gap-2 px-5 py-2.5 bg-purple-600 text-white rounded-xl font-semibold text-sm shadow-lg shadow-purple-500/25"><Plus size={18} /> Demander un conge</button>
                </div>

                <div className="bg-white rounded-2xl border border-slate-100 p-3 relative">
                    <Search className="absolute left-6 top-1/2 -translate-y-1/2 text-slate-300" size={16} />
                    <input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Rechercher une demande..." className="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-100 rounded-xl text-sm focus:outline-none" />
                </div>

                {loading ? <div className="flex justify-center py-20"><div className="animate-spin rounded-full h-8 w-8 border-b-2 border-purple-600" /></div> : leaves.length === 0 ? <div className="text-center py-20 text-slate-400">Aucune demande de conge</div> : (
                    <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                        {leaves.map((leave) => {
                            const status = STATUS_CONFIG[leave.status] || STATUS_CONFIG.pending;
                            return <div key={leave.id} className="bg-white rounded-2xl p-5 border border-slate-100 shadow-sm">
                                <div className="flex items-start justify-between gap-3 mb-4">
                                    <div><h3 className="font-bold text-slate-900 text-sm">{leave.user?.name || leave.user_name || 'Demandeur'}</h3><p className="text-xs text-slate-400 flex items-center gap-1 mt-1"><User size={11} /> {leave.approved_by ? `Traitee par ${leave.approved_by.name}` : 'En attente de validation RH / administration'}</p></div>
                                    <span className={`px-2.5 py-1 rounded-lg text-[10px] font-black uppercase border ${status.color}`}>{status.label}</span>
                                </div>
                                <p className="font-semibold text-sm text-slate-700">{leave.leaveType?.name || 'Conge'}</p>
                                <p className="text-xs text-slate-500 flex items-center gap-2 mt-2"><Calendar size={12} /> {leave.start_date} au {leave.end_date} ({leave.duration_days} jour(s))</p>
                                {leave.reason && <p className="text-xs text-slate-400 mt-3 italic">{leave.reason}</p>}
                                {leave.status === 'pending' && canManage && <div className="flex gap-2 mt-4 pt-3 border-t border-slate-100"><button onClick={() => updateStatus(leave, 'approved')} className="flex-1 flex items-center justify-center gap-1 rounded-xl py-2 text-xs font-bold text-emerald-600 bg-emerald-50"><Check size={13} /> Approuver</button><button onClick={() => { setRejecting(leave); setRejectionReason(''); }} className="flex-1 flex items-center justify-center gap-1 rounded-xl py-2 text-xs font-bold text-red-600 bg-red-50"><X size={13} /> Rejeter</button></div>}
                                {leave.status === 'rejected' && leave.rejection_reason && <p className="text-xs text-red-500 mt-3">Motif: {leave.rejection_reason}</p>}
                            </div>;
                        })}
                    </div>
                )}
            </div>

            <FormModal isOpen={showForm} onClose={() => setShowForm(false)} onSubmit={submitLeave} title="Demander un conge" submitText="Envoyer la demande">
                <div className="space-y-4">
                    {canManage && <div><label className="form-label">Enseignant</label><select required className="input-field" value={form.teacher_id} onChange={(event) => setForm({ ...form, teacher_id: event.target.value })}><option value="">Selectionner</option>{teachers.map((teacher) => <option key={teacher.id} value={teacher.id}>{teacher.user?.name || 'Inconnu'}</option>)}</select></div>}
                    <div><label className="form-label">Type de conge</label><select required className="input-field" value={form.leave_type_id} onChange={(event) => setForm({ ...form, leave_type_id: event.target.value })}><option value="">Selectionner</option>{types.map((type) => <option key={type.id} value={type.id}>{type.name}</option>)}</select></div>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4"><div><label className="form-label">Date de debut</label><input required type="date" className="input-field" value={form.start_date} onChange={(event) => setForm({ ...form, start_date: event.target.value })} /></div><div><label className="form-label">Date de fin</label><input required type="date" className="input-field" value={form.end_date} onChange={(event) => setForm({ ...form, end_date: event.target.value })} /></div></div>
                    <div><label className="form-label">Motif</label><textarea required className="input-field" rows="3" value={form.reason} onChange={(event) => setForm({ ...form, reason: event.target.value })} /></div>
                </div>
            </FormModal>

            <FormModal isOpen={Boolean(rejecting)} onClose={() => setRejecting(null)} onSubmit={(event) => { event.preventDefault(); updateStatus(rejecting, 'rejected'); }} title="Rejeter la demande" submitText="Confirmer le rejet"><label className="form-label">Motif du rejet</label><textarea required className="input-field" rows="3" value={rejectionReason} onChange={(event) => setRejectionReason(event.target.value)} /></FormModal>
        </div>
    );
};

export default Leaves;
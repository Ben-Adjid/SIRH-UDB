import { useEffect, useState, useCallback, useMemo } from 'react';
import api from '../services/api';
import { dataRefresh } from '../services/dataRefresh';
import useAuthStore from '../store/authStore';
import { hasAnyRole } from '../utils/roles';
import { Plus, Edit, Trash2, RefreshCw, BookOpen, GraduationCap, ChevronLeft, ChevronRight, MapPin } from 'lucide-react';
import toast from '../utils/toast';
import FormModal from '../components/FormModal';
import ConfirmModal from '../components/ConfirmModal';
import { SkeletonCard } from '../components/Skeleton';

const DAYS = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
const LEVELS = ['L1', 'L2', 'L3', 'M1', 'M2'];
const TIME_SLOTS = ['08:00-10:00', '10:00-12:00', '12:00-14:00', '14:00-16:00', '16:00-18:00', '18:00-20:00'];

const subjectColors = [
    { bg: 'bg-indigo-50', border: 'border-indigo-200', text: 'text-indigo-800', badge: 'bg-indigo-100 text-indigo-700' },
    { bg: 'bg-emerald-50', border: 'border-emerald-200', text: 'text-emerald-800', badge: 'bg-emerald-100 text-emerald-700' },
    { bg: 'bg-amber-50', border: 'border-amber-200', text: 'text-amber-800', badge: 'bg-amber-100 text-amber-700' },
    { bg: 'bg-rose-50', border: 'border-rose-200', text: 'text-rose-800', badge: 'bg-rose-100 text-rose-700' },
    { bg: 'bg-cyan-50', border: 'border-cyan-200', text: 'text-cyan-800', badge: 'bg-cyan-100 text-cyan-700' },
    { bg: 'bg-violet-50', border: 'border-violet-200', text: 'text-violet-800', badge: 'bg-violet-100 text-violet-700' },
    { bg: 'bg-orange-50', border: 'border-orange-200', text: 'text-orange-800', badge: 'bg-orange-100 text-orange-700' },
    { bg: 'bg-teal-50', border: 'border-teal-200', text: 'text-teal-800', badge: 'bg-teal-100 text-teal-700' },
];

const emptyForm = {
    teacher_id: '', subject_id: '', teaching_type_id: '',
    classroom_id: '', level: 'L1', semester: 'S1',
    day_of_week: 'Lundi', start_time: '08:00', end_time: '10:00',
    start_date: '', end_date: '', notes: ''
};

const Schedules = () => {
    const user = useAuthStore(s => s.user);
    const canManage = hasAnyRole(user, ['admin', 'academic_manager', 'hr_manager']);
    const isTeacher = hasAnyRole(user, ['teacher']) && !hasAnyRole(user, ['admin', 'hr_manager', 'academic_manager', 'accountant']);

    const [schedules, setSchedules] = useState([]);
    const [exams, setExams] = useState([]);
    const [teachers, setTeachers] = useState([]);
    const [subjects, setSubjects] = useState([]);
    const [classrooms, setClassrooms] = useState([]);
    const [teachingTypes, setTeachingTypes] = useState([]);
    const [ufrs, setUfrs] = useState([]);
    const [loading, setLoading] = useState(true);

    const isUfrManager = hasAnyRole(user, ['academic_manager']) && !hasAnyRole(user, ['admin']);
    const visibleUfrs = isUfrManager
        ? ufrs.filter(ufr => Number(ufr.head_id) === Number(user?.id) || Number(ufr.id) === Number(user?.teacher?.ufr_id))
        : ufrs;

    const [selectedUfrIdx, setSelectedUfrIdx] = useState(0);
    const selectedUfr = visibleUfrs[selectedUfrIdx] || null;
    const goToUfr = (idx) => { if (idx >= 0 && idx < visibleUfrs.length) setSelectedUfrIdx(idx); };

    const [selectedSchedule, setSelectedSchedule] = useState(null);
    const [isEditing, setIsEditing] = useState(false);
    const [showModal, setShowModal] = useState(false);
    const [formData, setFormData] = useState(emptyForm);
    const [fieldErrors, setFieldErrors] = useState({});
    const [submitting, setSubmitting] = useState(false);
    const [showExamModal, setShowExamModal] = useState(false);
    const [editingExam, setEditingExam] = useState(null);
    const [examForm, setExamForm] = useState({ ...emptyForm, type: 'exam', exam_type: 'DS' });
    const [confirmConfig, setConfirmConfig] = useState({ isOpen: false, title: '', message: '', onConfirm: () => { } });

    const fetchData = useCallback(async () => {
        setLoading(true);
        try { const u = (await api.get('/ufrs', { params: { per_page: 200 } })).data; setUfrs(Array.isArray(u) ? u : (u?.data || [])); } catch { /* keep the current reference data */ }
        try {
            const [schedRes, teacherRes, subjectRes, classRes, typeRes] = await Promise.all([
                api.get('/schedules', { params: { per_page: 500, include_archived: false } }),
                api.get('/teachers', { params: { per_page: 200 } }),
                api.get('/subjects', { params: { per_page: 200 } }),
                api.get('/classrooms', { params: { per_page: 200 } }),
                api.get('/teaching-types'),
            ]);
            const s = schedRes.data;
            const schedulesData = Array.isArray(s) ? s : (s?.data || []);
            setSchedules(schedulesData);
            const examRes = await api.get('/exams', { params: { per_page: 500 } });
            const examData = examRes.data; setExams(Array.isArray(examData) ? examData : (examData?.data || []));
            const t = teacherRes.data; setTeachers(Array.isArray(t) ? t : (t?.data || []));
            const sub = subjectRes.data; setSubjects(Array.isArray(sub) ? sub : (sub?.data || []));
            const c = classRes.data; setClassrooms(Array.isArray(c) ? c : (c?.data || []));
            setTeachingTypes(typeRes.data?.data || typeRes.data || []);
        } catch { /* the page renders the available data and can be refreshed */ }
        finally { setLoading(false); }
    }, []);

    useEffect(() => { fetchData(); }, [fetchData]);

    useEffect(() => {
        const unsubscribe = dataRefresh.subscribe('schedules', () => fetchData());
        return unsubscribe;
    }, [fetchData]);

    const getTeacherName = (tId) => teachers.find(t => t.id === tId)?.user?.name || '—';
    const getSubjectName = (sId) => subjects.find(s => s.id === sId)?.name || '—';
    const getClassroomName = (cId) => classrooms.find(c => c.id === cId)?.name || '—';
    const getTeachingTypeLabel = (ttId) => teachingTypes.find(t => t.id === ttId)?.code || '';

    const subjectColorMap = useMemo(() => { const map = {}; subjects.forEach((s, i) => { map[s.id] = i; }); return map; }, [subjects]);

    const ufrLevelSchedules = useMemo(() => {
        const result = {};
        visibleUfrs.forEach(ufr => { result[ufr.id] = {}; });
        schedules.filter(s => s.status !== 'cancelled').forEach(s => {
            const uid = s.teacher?.ufr_id;
            if (uid && result[uid] && s.level) { if (!result[uid][s.level]) result[uid][s.level] = []; result[uid][s.level].push(s); }
        });
        return result;
    }, [schedules, visibleUfrs]);

    const getTimeSlots = (courses) => {
        const map = new Map();
        TIME_SLOTS.forEach(ts => { const [s] = ts.split('-'); map.set(s, ts); });
        (courses || []).forEach(s => {
            const st = s.start_time ? (s.start_time.length > 8 ? s.start_time.substring(11, 16) : s.start_time.substring(0, 5)) : null;
            const et = s.end_time ? (s.end_time.length > 8 ? s.end_time.substring(11, 16) : s.end_time.substring(0, 5)) : null;
            if (st && et) map.set(st, `${st}-${et}`);
        });
        return Array.from(map.entries()).sort((a, b) => a[0].localeCompare(b[0])).map(([, v]) => v);
    };

    const buildGrid = (courses) => {
        const slots = getTimeSlots(courses);
        const grid = {};
        DAYS.forEach((_, di) => { grid[di] = {}; slots.forEach(ts => { grid[di][ts] = []; }); });
        courses.forEach(s => {
            const di = DAYS.indexOf(s.day_of_week);
            if (di === -1) return;
            const st = s.start_time ? (s.start_time.length > 8 ? s.start_time.substring(11, 16) : s.start_time.substring(0, 5)) : null;
            if (!st) return;
            const et = s.end_time ? (s.end_time.length > 8 ? s.end_time.substring(11, 16) : s.end_time.substring(0, 5)) : null;
            const key = et ? `${st}-${et}` : st;
            if (!grid[di][key]) grid[di][key] = [];
            grid[di][key].push(s);
        });
        return grid;
    };

    const handleOpenAdd = () => { setIsEditing(false); setSelectedSchedule(null); setFieldErrors({}); setFormData({ ...emptyForm }); setShowModal(true); };
    const handleOpenEdit = (schedule) => {
        setIsEditing(true); setSelectedSchedule(schedule); setFieldErrors({});
        setFormData({
            teacher_id: schedule.teacher_id || '', subject_id: schedule.subject_id || '',
            teaching_type_id: schedule.teaching_type_id || '', classroom_id: schedule.classroom_id || '',
            level: schedule.level || 'L1', semester: schedule.semester || 'S1',
            day_of_week: schedule.day_of_week || 'Lundi',
            start_time: schedule.start_time ? (schedule.start_time.length > 8 ? schedule.start_time.substring(11, 16) : schedule.start_time.substring(0, 5)) : '08:00',
            end_time: schedule.end_time ? (schedule.end_time.length > 8 ? schedule.end_time.substring(11, 16) : schedule.end_time.substring(0, 5)) : '10:00',
            start_date: schedule.start_date?.split('T')[0] || '', end_date: schedule.end_date?.split('T')[0] || '',
            notes: schedule.notes || '',
        });
        setShowModal(true);
    };
    const handleChange = (field, value) => { setFormData(prev => ({ ...prev, [field]: value })); if (fieldErrors[field]) setFieldErrors(prev => { const n = { ...prev }; delete n[field]; return n; }); };
    const handleSubmit = async (e) => {
        e.preventDefault(); setFieldErrors({}); setSubmitting(true);
        try {
            if (isEditing) { await api.put(`/schedules/${selectedSchedule.id}`, formData); toast.success('Cours mis à jour'); }
            else { await api.post('/schedules', formData); toast.success('Cours ajouté'); }
            await fetchData();
            setShowModal(false);
            setSelectedSchedule(null);
            setFormData({ ...emptyForm });
            setFieldErrors({});
            setSelectedUfrIdx(0);
        } catch (err) {
            if (err.response?.data?.errors) setFieldErrors(err.response.data.errors);
            else toast.error(err.response?.data?.message || 'Erreur');
        } finally { setSubmitting(false); }
    };
    const handleExamSubmit = async (e) => {
        e.preventDefault(); setSubmitting(true);
        try {
            if (editingExam) {
                await api.put(`/exams/${editingExam.id}`, examForm); toast.success('Examen modifié');
            } else {
                await api.post('/exams', examForm); toast.success('Examen créé');
            }
            await fetchData();
            setShowExamModal(false);
            setEditingExam(null);
            setExamForm({ ...emptyForm, type: 'exam', exam_type: 'DS' });
            setFieldErrors({});
            setSelectedUfrIdx(0);
        } catch (err) {
            if (err.response?.data?.errors) setFieldErrors(err.response.data.errors);
            else toast.error(err.response?.data?.message || 'Erreur');
        } finally { setSubmitting(false); }
    };

    const handleEditExam = (exam) => {
        setEditingExam(exam);
        setExamForm({
            ...emptyForm,
            type: 'exam',
            teacher_id: exam.teacher_id || '', subject_id: exam.subject_id || '', classroom_id: exam.classroom_id || '',
            level: exam.level || 'L1', semester: exam.semester || 'S1', day_of_week: exam.day_of_week || 'Lundi',
            start_time: exam.start_time ? (exam.start_time.length > 8 ? exam.start_time.substring(11, 16) : exam.start_time.substring(0, 5)) : '08:00', end_time: exam.end_time ? (exam.end_time.length > 8 ? exam.end_time.substring(11, 16) : exam.end_time.substring(0, 5)) : '10:00',
            start_date: exam.start_date?.split('T')[0] || '', end_date: exam.end_date?.split('T')[0] || '',
            exam_type: exam.exam_type || 'DS',
        });
        setShowExamModal(true);
    };

    const handleDeleteExam = (exam) => {
        setConfirmConfig({
            isOpen: true,
            title: 'Supprimer cet examen ?',
            message: `L examen de ${exam.subject?.name || 'cette matière'} sera supprimé définitivement.`,
            onConfirm: async () => {
                try {
                    await api.delete(`/exams/${exam.id}`);
                    toast.success('Examen supprimé');
                    setConfirmConfig(prev => ({ ...prev, isOpen: false }));
                    await fetchData();
                } catch (err) {
                    toast.error(err.response?.data?.message || 'Erreur de suppression');
                    setConfirmConfig(prev => ({ ...prev, isOpen: false }));
                }
            },
        });
    };

    const renderLevelGrid = (level, courses) => {
        if (!courses || courses.length === 0) return null;
        const slots = getTimeSlots(courses);
        const grid = buildGrid(courses);
        return (
            <div key={level} className="mb-6">
                <div className="flex items-center gap-3 mb-3 px-1">
                    <span className="text-sm font-black text-slate-700 bg-indigo-50 text-indigo-700 px-3 py-1 rounded-xl">{level}</span>
                    <span className="text-[11px] font-semibold text-slate-400">{courses.length} cours</span>
                </div>
                <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full border-collapse" style={{ minWidth: '800px' }}>
                            <thead>
                                <tr className="border-b-2 border-slate-200">
                                    <th className="p-3 text-center text-[10px] font-bold text-slate-400 uppercase tracking-wider bg-slate-50 w-[100px]">Heures</th>
                                    {DAYS.map(day => <th key={day} className="p-3 text-center text-[11px] font-bold text-slate-700 uppercase tracking-wider border-l border-slate-100">{day}</th>)}
                                </tr>
                            </thead>
                            <tbody>
                                {slots.map(ts => {
                                    const [sTime, eTime] = ts.split('-');
                                    return (
                                        <tr key={ts} className="border-b border-slate-100 hover:bg-slate-50/30 transition-colors">
                                            <td className="p-2 text-center border-r border-slate-100 bg-slate-50/50">
                                                <div className="text-[11px] font-bold text-slate-600">{sTime}</div>
                                                <div className="text-[10px] text-slate-400">{eTime}</div>
                                            </td>
                                            {DAYS.map((day, di) => {
                                                const cells = grid[di]?.[ts] || [];
                                                return (
                                                    <td key={`${day}-${ts}`} className="p-1.5 border-l border-slate-100 align-top" style={{ minWidth: '150px', height: '80px' }}>
                                                        {cells.map((s, si) => {
                                                            const colorIdx = subjectColorMap[s.subject_id] ?? si;
                                                            const c = subjectColors[colorIdx % subjectColors.length];
                                                            return (
                                                                    <div key={s.id} className={`mb-1 p-2 rounded-lg border ${c.bg} ${c.border} cursor-pointer hover:shadow-md transition-all`}
                                                                        onClick={() => canManage && handleOpenEdit(s)}>
                                                                        <div className={`text-xs font-bold ${c.text}`}>{getSubjectName(s.subject_id)}</div>
                                                                        <div className={`text-[11px] mt-0.5 ${c.text} opacity-80`}>{getTeacherName(s.teacher_id)}</div>
                                                                        <div className="flex items-center gap-1.5 mt-1">
                                                                            {getTeachingTypeLabel(s.teaching_type_id) && <span className={`text-[10px] px-1.5 py-0.5 rounded font-bold ${c.badge}`}>{getTeachingTypeLabel(s.teaching_type_id)}</span>}
                                                                            <span className="text-[10px] text-slate-400 font-medium">{sTime}-{eTime}</span>
                                                                        </div>
                                                                        <div className="flex items-center gap-1 mt-1 text-[10px] text-slate-500">
                                                                            <MapPin size={9} /> <span>{getClassroomName(s.classroom_id)}</span>
                                                                        </div>
                                                                    </div>
                                                            );
                                                        })}
                                                        {cells.length === 0 && <div className="text-[10px] text-slate-200 text-center py-4">—</div>}
                                                    </td>
                                                );
                                            })}
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        );
    };

    if (loading) return <div className="space-y-4"><SkeletonCard /><SkeletonCard /></div>;

    const teacherSchedules = schedules.filter(s => s.status !== 'cancelled');
    const teacherGrid = buildGrid(teacherSchedules);
    const teacherSlots = getTimeSlots(teacherSchedules);

    return (
        <div className="space-y-6">
            {isTeacher ? (
                <>
                    <div className="flex items-center justify-between">
                        <h3 className="text-base font-black text-slate-800 flex items-center gap-2">
                            <GraduationCap size={20} className="text-indigo-500" /> Mon Emploi du temps
                        </h3>
                        <button onClick={fetchData} className="p-2.5 rounded-xl bg-white border border-slate-200 text-slate-500 hover:text-indigo-600 transition-all">
                            <RefreshCw size={16} />
                        </button>
                    </div>
                    {teacherSchedules.length === 0 ? (
                        <div className="bg-white rounded-2xl border border-slate-200 shadow-sm p-12 text-center">
                            <GraduationCap size={48} className="text-slate-200 mx-auto mb-3" />
                            <p className="text-slate-400 text-sm font-semibold">Aucun cours programmé pour vous</p>
                        </div>
                    ) : (
                        <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                            <div className="overflow-x-auto">
                                <table className="w-full border-collapse" style={{ minWidth: '800px' }}>
                                    <thead>
                                        <tr className="border-b-2 border-slate-200">
                                            <th className="p-3 text-center text-[10px] font-bold text-slate-400 uppercase tracking-wider bg-slate-50 w-[100px]">Heures</th>
                                            {DAYS.map(day => <th key={day} className="p-3 text-center text-[11px] font-bold text-slate-700 uppercase tracking-wider border-l border-slate-100">{day}</th>)}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {teacherSlots.map(ts => {
                                            const [sTime, eTime] = ts.split('-');
                                            return (
                                                <tr key={ts} className="border-b border-slate-100 hover:bg-slate-50/30 transition-colors">
                                                    <td className="p-2 text-center border-r border-slate-100 bg-slate-50/50">
                                                        <div className="text-[11px] font-bold text-slate-600">{sTime}</div>
                                                        <div className="text-[10px] text-slate-400">{eTime}</div>
                                                    </td>
                                                    {DAYS.map((day, di) => {
                                                        const cells = teacherGrid[di]?.[ts] || [];
                                                        return (
                                                            <td key={`t-${day}-${ts}`} className="p-1.5 border-l border-slate-100 align-top" style={{ minWidth: '150px', height: '80px' }}>
                                                                {cells.map((s, si) => {
                                                                    const colorIdx = subjectColorMap[s.subject_id] ?? si;
                                                                    const c = subjectColors[colorIdx % subjectColors.length];
                                                                    const ufrName = ufrs.find(u => u.id === s.teacher?.ufr_id)?.name || '';
                                                                    return (
                                                                        <div key={s.id} className={`mb-1 p-2 rounded-lg border ${c.bg} ${c.border}`}>
                                                                            <div className={`text-xs font-bold ${c.text}`}>{getSubjectName(s.subject_id)}</div>
                                                                            <div className="flex items-center gap-1.5 mt-1">
                                                                                {getTeachingTypeLabel(s.teaching_type_id) && <span className={`text-[10px] px-1.5 py-0.5 rounded font-bold ${c.badge}`}>{getTeachingTypeLabel(s.teaching_type_id)}</span>}
                                                                                <span className="text-[10px] text-slate-400 font-medium">{sTime}-{eTime}</span>
                                                                            </div>
                                                                            <div className="flex items-center gap-1 mt-1 text-[10px] text-slate-500">
                                                                                <MapPin size={9} /> <span>{getClassroomName(s.classroom_id)}</span>
                                                                            </div>
                                                                            {ufrName && <div className="text-[10px] text-slate-400 mt-0.5">{ufrName} — {s.level || ''}</div>}
                                                                        </div>
                                                                    );
                                                                })}
                                                                {cells.length === 0 && <div className="text-[10px] text-slate-200 text-center py-4">—</div>}
                                                            </td>
                                                        );
                                                    })}
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    )}
                </>
            ) : (
                <>
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <h3 className="text-base font-black text-slate-800 flex items-center gap-2">
                            <GraduationCap size={20} className="text-indigo-500" /> Emploi du temps
                            {selectedUfr && <span className="text-sm font-semibold text-slate-400 ml-1">— {selectedUfr.name}</span>}
                        </h3>
                        <div className="flex items-center gap-2">
                            <button onClick={fetchData} className="p-2.5 rounded-xl bg-white border border-slate-200 text-slate-500 hover:text-indigo-600 transition-all"><RefreshCw size={16} /></button>
                            {canManage && <>
                                <button onClick={handleOpenAdd} className="px-4 py-2.5 rounded-xl bg-indigo-600 text-white text-xs font-bold hover:bg-indigo-700 flex items-center gap-2 shadow-sm"><Plus size={14} /> Cours</button>
                                <button onClick={() => setShowExamModal(true)} className="px-4 py-2.5 rounded-xl bg-amber-500 text-white text-xs font-bold hover:bg-amber-600 flex items-center gap-2 shadow-sm"><BookOpen size={14} /> Examen</button>
                            </>}
                        </div>
                    </div>
                    <div className="bg-white rounded-2xl border border-slate-200 shadow-sm p-3">
                        <div className="flex items-center gap-2 overflow-x-auto pb-1">
                            <button onClick={() => goToUfr(selectedUfrIdx - 1)} disabled={selectedUfrIdx === 0} className="p-2 rounded-xl border border-slate-200 text-slate-400 hover:text-indigo-600 disabled:opacity-30 flex-shrink-0"><ChevronLeft size={14} /></button>
                             {visibleUfrs.map((ufr, idx) => {
                                const total = Object.values(ufrLevelSchedules[ufr.id] || {}).flat().length;
                                const isActive = idx === selectedUfrIdx;
                                return (
                                    <button key={ufr.id} onClick={() => setSelectedUfrIdx(idx)}
                                        className={`flex-shrink-0 px-4 py-2 rounded-xl text-xs font-bold whitespace-nowrap ${isActive ? 'bg-indigo-600 text-white shadow-md' : total > 0 ? 'bg-slate-50 text-slate-600 border hover:border-indigo-200' : 'text-slate-300 border border-slate-100'}`}>
                                        {ufr.name} <span className={`ml-1.5 text-[10px] ${isActive ? 'text-indigo-200' : 'text-slate-400'}`}>({total})</span>
                                    </button>
                                );
                            })}
                             <button onClick={() => goToUfr(selectedUfrIdx + 1)} disabled={selectedUfrIdx >= visibleUfrs.length - 1} className="p-2 rounded-xl border border-slate-200 text-slate-400 hover:text-indigo-600 disabled:opacity-30 flex-shrink-0"><ChevronRight size={14} /></button>
                        </div>
                    </div>
                    {exams.length > 0 && <div className="bg-white rounded-2xl border border-amber-200 shadow-sm p-4"><h3 className="text-sm font-black text-slate-800 mb-3 flex items-center gap-2"><BookOpen size={16} className="text-amber-500" /> Examens programmes</h3><div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">{exams.map(exam => <div key={exam.id} className="rounded-xl border border-amber-100 bg-amber-50/50 p-3"><div className="flex items-start justify-between gap-2"><p className="text-sm font-bold text-slate-800">{exam.subject?.name || 'Matiere'}</p>{canManage && <div className="flex gap-1"><button onClick={() => handleEditExam(exam)} className="p-1 text-slate-400 hover:text-indigo-600" title="Modifier"><Edit size={14} /></button><button onClick={() => handleDeleteExam(exam)} className="p-1 text-slate-400 hover:text-red-600" title="Supprimer"><Trash2 size={14} /></button></div>}</div><p className="text-xs text-slate-500 mt-1">{exam.exam_type || 'Examen'} · {exam.level} · {exam.day_of_week}</p><p className="text-xs text-slate-500">{exam.start_date} · {exam.start_time?.substring(11, 16)} - {exam.end_time?.substring(11, 16)} · {exam.classroom?.name || 'Salle non renseignee'}</p></div>)}</div></div>}
                    {selectedUfr ? (
                        <div className="space-y-1">
                            {(() => {
                                const ld = ufrLevelSchedules[selectedUfr.id] || {};
                                const total = Object.values(ld).flat().length;
                                return total === 0 ? (
                                    <div className="bg-white rounded-2xl border border-slate-200 p-12 text-center">
                                        <GraduationCap size={48} className="text-slate-200 mx-auto mb-3" />
                                        <p className="text-slate-400 text-sm font-semibold">Aucun cours pour {selectedUfr.name}</p>
                                    </div>
                                ) : LEVELS.map(l => renderLevelGrid(l, ld[l] || []));
                            })()}
                        </div>
                    ) : (
                        <div className="bg-white rounded-2xl border border-slate-200 p-16 text-center">
                            <GraduationCap size={48} className="text-slate-200 mx-auto mb-4" />
                            <p className="text-slate-400 text-sm font-semibold">Aucun UFR disponible</p>
                        </div>
                    )}
                </>
            )}

            {/* COUrs MODAL */}
            {showModal && (
                <FormModal isOpen={showModal} onClose={() => setShowModal(false)} onSubmit={handleSubmit} size="xl" title={isEditing ? 'Modifier le cours' : 'Nouveau cours'} submitText={submitting ? 'Enregistrement...' : 'Enregistrer'} disableSubmit={submitting}>
                    <div className="grid grid-cols-2 gap-4">
                        <div className="form-group"><label className="form-label">Enseignant</label><select value={formData.teacher_id} onChange={e => handleChange('teacher_id', e.target.value)} className="input-field" required><option value="">Sélectionner...</option>{teachers.map(t => <option key={t.id} value={t.id}>{t.user?.name || t.name || `#${t.id}`}</option>)}</select></div>
                        <div className="form-group"><label className="form-label">Matière</label><select value={formData.subject_id} onChange={e => handleChange('subject_id', e.target.value)} className="input-field" required><option value="">Sélectionner...</option>{subjects.map(s => <option key={s.id} value={s.id}>{s.name}</option>)}</select></div>
                        <div className="form-group"><label className="form-label">Type (CM/TD/TP)</label><select value={formData.teaching_type_id} onChange={e => handleChange('teaching_type_id', e.target.value)} className="input-field"><option value="">Aucun</option>{teachingTypes.map(t => <option key={t.id} value={t.id}>{t.name}</option>)}</select></div>
                        <div className="form-group"><label className="form-label">Salle</label><select value={formData.classroom_id} onChange={e => handleChange('classroom_id', e.target.value)} className="input-field"><option value="">Aucune</option>{classrooms.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}</select></div>
                        <div className="form-group"><label className="form-label">Niveau</label><select value={formData.level} onChange={e => handleChange('level', e.target.value)} className="input-field" required>{LEVELS.map(l => <option key={l} value={l}>{l}</option>)}</select></div>
                        <div className="form-group"><label className="form-label">Semestre</label><select value={formData.semester} onChange={e => handleChange('semester', e.target.value)} className="input-field"><option value="S1">S1</option><option value="S2">S2</option></select></div>
                        <div className="form-group"><label className="form-label">Jour</label><select value={formData.day_of_week} onChange={e => handleChange('day_of_week', e.target.value)} className="input-field" required>{DAYS.map(d => <option key={d} value={d}>{d}</option>)}</select></div>
                        <div className="form-group"><label className="form-label">Heure début</label><input type="time" value={formData.start_time} onChange={e => handleChange('start_time', e.target.value)} className="input-field" required /></div>
                        <div className="form-group"><label className="form-label">Heure fin</label><input type="time" value={formData.end_time} onChange={e => handleChange('end_time', e.target.value)} className="input-field" required /></div>
                        <div className="form-group col-span-2"><label className="form-label">Notes</label><textarea value={formData.notes} onChange={e => handleChange('notes', e.target.value)} className="input-field" rows={2} placeholder="Optionnel..." /></div>
                    </div>
                </FormModal>
            )}

            {/* EXAMEN MODAL */}
            {showExamModal && (
                <FormModal isOpen={showExamModal} onClose={() => { setShowExamModal(false); setEditingExam(null); }} onSubmit={handleExamSubmit} size="lg" title={editingExam ? 'Modifier un examen' : 'Créer un examen'} submitText={submitting ? 'Enregistrement...' : (editingExam ? 'Enregistrer' : "Créer l'examen")} disableSubmit={submitting}>
                    <div className="grid grid-cols-2 gap-4">
                        <div className="form-group"><label className="form-label">Enseignant</label><select value={examForm.teacher_id} onChange={e => setExamForm({ ...examForm, teacher_id: e.target.value })} className="input-field" required><option value="">Sélectionner...</option>{teachers.map(t => <option key={t.id} value={t.id}>{t.user?.name || t.name || `#${t.id}`}</option>)}</select></div>
                        <div className="form-group"><label className="form-label">Matière</label><select value={examForm.subject_id} onChange={e => setExamForm({ ...examForm, subject_id: e.target.value })} className="input-field" required><option value="">Sélectionner...</option>{subjects.map(s => <option key={s.id} value={s.id}>{s.name}</option>)}</select></div>
                        <div className="form-group"><label className="form-label">Type</label><select value={examForm.exam_type} onChange={e => setExamForm({ ...examForm, exam_type: e.target.value })} className="input-field"><option value="DS">DS</option><option value="Examen">Examen</option><option value="Rattrapage">Rattrapage</option></select></div>
                        <div className="form-group"><label className="form-label">Salle</label><select required value={examForm.classroom_id} onChange={e => setExamForm({ ...examForm, classroom_id: e.target.value })} className="input-field"><option value="">Sélectionner...</option>{classrooms.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}</select></div>
                        <div className="form-group"><label className="form-label">Niveau</label><select value={examForm.level} onChange={e => setExamForm({ ...examForm, level: e.target.value })} className="input-field" required>{LEVELS.map(l => <option key={l} value={l}>{l}</option>)}</select></div>
                        <div className="form-group"><label className="form-label">Jour</label><select value={examForm.day_of_week} onChange={e => setExamForm({ ...examForm, day_of_week: e.target.value })} className="input-field" required>{DAYS.map(d => <option key={d} value={d}>{d}</option>)}</select></div>
                        <div className="form-group"><label className="form-label">Début</label><input type="time" value={examForm.start_time} onChange={e => setExamForm({ ...examForm, start_time: e.target.value })} className="input-field" required /></div>
                        <div className="form-group"><label className="form-label">Fin</label><input type="time" value={examForm.end_time} onChange={e => setExamForm({ ...examForm, end_time: e.target.value })} className="input-field" required /></div>
                        <div className="form-group col-span-2"><label className="form-label">Date</label><input type="date" value={examForm.start_date} onChange={e => setExamForm({ ...examForm, start_date: e.target.value, end_date: e.target.value })} className="input-field" required /></div>
                    </div>
                </FormModal>
            )}

            <ConfirmModal isOpen={confirmConfig.isOpen} onClose={() => setConfirmConfig(prev => ({ ...prev, isOpen: false }))} onConfirm={confirmConfig.onConfirm} title={confirmConfig.title} message={confirmConfig.message} type="danger" confirmText="Supprimer" />
        </div>
    );
};

export default Schedules;

import { useState, useEffect, useCallback, memo } from 'react';
import api from '../services/api';
import useAuthStore from '../store/authStore';
import {
    Clock, Calendar, CheckCircle, AlertCircle, ArrowRight,
    Users, UserCheck, UserX, Timer, Download, RefreshCw, ChevronLeft,
    ChevronRight, Search, Sun, Moon, Fingerprint, CalendarCheck,
    MapPin, BookOpen
} from 'lucide-react';
import toast from '../utils/toast';
import FormModal from '../components/FormModal';
import Pagination from '../components/Pagination';
import { getUserRoles } from '../utils/roles';
import { SkeletonCard } from '../components/Skeleton';

const STATUS_MAP = {
    present: { label: 'Present', dot: 'bg-emerald-500', badge: 'bg-emerald-50 text-emerald-600 border-emerald-100', cardBg: 'bg-gradient-to-br from-emerald-50 to-emerald-100' },
    late: { label: 'Retard', dot: 'bg-amber-500', badge: 'bg-amber-50 text-amber-600 border-amber-100', cardBg: 'bg-gradient-to-br from-amber-50 to-amber-100' },
    absent: { label: 'Absent', dot: 'bg-red-500', badge: 'bg-red-50 text-red-600 border-red-100', cardBg: 'bg-gradient-to-br from-red-50 to-red-100' },
    half_day: { label: 'Demi-journee', dot: 'bg-blue-500', badge: 'bg-blue-50 text-blue-600 border-blue-100', cardBg: 'bg-gradient-to-br from-blue-50 to-blue-100' },
    none: { label: 'Aucun pointage', dot: 'bg-gray-400', badge: 'bg-gray-50 text-gray-600 border-gray-100', cardBg: 'bg-white' },
};

const StatusBadge = memo(({ status }) => {
    const s = STATUS_MAP[status] || STATUS_MAP.none;
    return (
        <span className={`inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-[10px] font-black uppercase tracking-widest border ${s.badge}`}>
            <span className={`w-2 h-2 rounded-full ${s.dot}`}></span>
            {s.label}
        </span>
    );
});

const LiveClock = memo(() => {
    const [time, setTime] = useState(new Date());
    useEffect(() => {
        const t = setInterval(() => setTime(new Date()), 1000);
        return () => clearInterval(t);
    }, []);
    const h = time.getHours();
    const Icon = h < 6 ? Moon : h < 18 ? Sun : Moon;
    return (
        <div className="flex items-center gap-4">
            <div className="text-5xl font-black text-gray-900 font-mono tracking-tighter tabular-nums">
                {time.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit', second: '2-digit' })}
            </div>
            <div className="text-gray-300"><Icon size={20} /></div>
        </div>
    );
});

const WorkTimer = memo(({ checkInTime }) => {
    const [elapsed, setElapsed] = useState('0h 00min');
    useEffect(() => {
        if (!checkInTime) return;
        const update = () => {
            const [h, m, s] = checkInTime.split(':').map(Number);
            const now = new Date();
            const start = new Date(now.getFullYear(), now.getMonth(), now.getDate(), h, m, s);
            if (start > now) start.setDate(start.getDate() - 1);
            const diff = Math.max(0, now - start.getTime());
            const hours = Math.floor(diff / 3600000);
            const mins = Math.floor((diff % 3600000) / 60000);
            setElapsed(`${hours}h ${String(mins).padStart(2, '0')}min`);
        };
        update();
        const t = setInterval(update, 60000);
        return () => clearInterval(t);
    }, [checkInTime]);
    return <span className="text-lg font-black text-emerald-600">{elapsed}</span>;
});


const CalendarHistory = memo(({ weekData, onPrevWeek, onNextWeek, weeksBack }) => {
    const dayStatusColor = (status) => {
        switch (status) {
            case 'present': return 'bg-emerald-500 text-white';
            case 'late': return 'bg-amber-400 text-white';
            case 'absent': return 'bg-red-400 text-white';
            case 'half_day': return 'bg-blue-400 text-white';
            default: return 'bg-gray-100 text-gray-400';
        }
    };
    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between">
                <button onClick={onPrevWeek} className="p-2 hover:bg-gray-100 rounded-xl transition-all"><ChevronLeft size={16} className="text-gray-500" /></button>
                <p className="text-xs font-black text-gray-500 uppercase tracking-widest">{weeksBack === 0 ? 'Cette semaine' : `${weeksBack} semaine${weeksBack > 1 ? 's' : ''} avant`}</p>
                <button onClick={onNextWeek} disabled={weeksBack === 0} className="p-2 hover:bg-gray-100 rounded-xl transition-all disabled:opacity-30"><ChevronRight size={16} className="text-gray-500" /></button>
            </div>
            <div className="grid grid-cols-4 sm:grid-cols-7 gap-2">
                {weekData?.days?.map((day, i) => (
                    <div key={i} className={`flex flex-col items-center gap-1.5 ${i >= 4 ? 'hidden sm:flex' : ''}`}>
                        <p className="text-[9px] font-black text-gray-400 uppercase">{day.day_short}</p>
                        <div className={`w-10 h-10 rounded-2xl flex items-center justify-center text-xs font-black transition-all ${dayStatusColor(day.status)} ${day.is_today ? 'ring-2 ring-indigo-500 ring-offset-2' : ''}`}>{day.day_number}</div>
                        {day.check_in && <p className="text-[8px] font-mono text-gray-400">{day.check_in}</p>}
                    </div>
                ))}
            </div>
        </div>
    );
});

const Attendance = () => {
    const user = useAuthStore((state) => state.user);
    const [history, setHistory] = useState([]);
    const [loading, setLoading] = useState(true);
    const [code, setCode] = useState('');

    const [allAttendance, setAllAttendance] = useState([]);
    const [page, setPage] = useState(1);
    const perPage = 12;
    const [attMeta, setAttMeta] = useState({ total: 0, last_page: 1 });
    const [filterDate, setFilterDate] = useState(new Date().toISOString().split('T')[0]);
    const [filterStatus, setFilterStatus] = useState('');
    const [filterDepartment, setFilterDepartment] = useState('');
    const [searchName, setSearchName] = useState('');
    const [debouncedSearch, setDebouncedSearch] = useState('');
    const [ufrs, setUfrs] = useState([]);
    const [autoRefresh, setAutoRefresh] = useState(true);
    const [exporting, setExporting] = useState(false);
    const [weekData, setWeekData] = useState(null);
    const [weeksBack, setWeeksBack] = useState(0);
    const [todaySchedules, setTodaySchedules] = useState([]);
    const [selfAttendance, setSelfAttendance] = useState(null);
    const [staffPin, setStaffPin] = useState('');
    const [configuredPin, setConfiguredPin] = useState('');

    const [showAbsenceModal, setShowAbsenceModal] = useState(false);
    const [showRecordAbsenceModal, setShowRecordAbsenceModal] = useState(false);
    const [showValidateAbsenceModal, setShowValidateAbsenceModal] = useState(false);
    const [showAvailabilityModal, setShowAvailabilityModal] = useState(false);
    const [selectedAttendance, setSelectedAttendance] = useState(null);
    const [activeSchedule, setActiveSchedule] = useState(null);
    const [currentSchedule, setCurrentSchedule] = useState(null);
    const [attendanceMode, setAttendanceMode] = useState(null); // 'physical' ou 'online'
    const [teachers, setTeachers] = useState([]);
    const [onlineCourseRequest, setOnlineCourseRequest] = useState(null);
    const [showOnlineRequestModal, setShowOnlineRequestModal] = useState(false);
    const [onlineRequestForm, setOnlineRequestForm] = useState({ schedule_id: '', course_date: '' });
    const [submittingOnlineRequest, setSubmittingOnlineRequest] = useState(false);
    const [schedules, setSchedules] = useState([]);
    const [userPinCode, setUserPinCode] = useState('••••••');

    const [absenceForm, setAbsenceForm] = useState({ attendance_date: new Date().toISOString().split('T')[0], status: 'absent', reason: '' });
    const [recordAbsenceForm, setRecordAbsenceForm] = useState({ teacher_id: '', attendance_date: new Date().toISOString().split('T')[0], status: 'absent', reason: '' });
    const [validateForm, setValidateForm] = useState({ justified: true, notes: '' });
    const [availabilityForm, setAvailabilityForm] = useState({ date: new Date().toISOString().split('T')[0], available: true, time_slots: '' });

    const userRoles = getUserRoles(user);
    const isAdminOrHR = userRoles.includes('admin') || userRoles.includes('hr_manager') || userRoles.includes('secretary');
    const isAccountant = userRoles.includes('accountant');
    const isAcademicManager = userRoles.includes('academic_manager');
    const isTeacher = userRoles.includes('teacher');
    const isManagement = isAdminOrHR || isAccountant || isAcademicManager;
    const hasDualRole = isTeacher && isManagement;
    const [viewMode, setViewMode] = useState(isTeacher ? 'teacher' : 'management');

    const todayStr = new Date().toISOString().split('T')[0];
    const todayAttendance = todaySchedules.find(s => s.check_in_time) ||
        history.find(h => {
            const d = typeof h.attendance_date === 'string' ? h.attendance_date.substring(0, 10) : '';
            return d === todayStr && h.check_in_time;
        }) || selfAttendance;

    const loadManagementData = useCallback(async (signal, pageNum = 1) => {
        const params = { date: filterDate, page: pageNum, per_page: perPage };
        if (filterDepartment) params.ufr_id = filterDepartment;
        if (filterStatus) params.status = filterStatus;
        if (debouncedSearch) params.search = debouncedSearch;

        await Promise.all([
            api.get('/attendance', { params, signal }).then(r => {
                const raw = r.data;
                const list = Array.isArray(raw?.data) ? raw.data : Array.isArray(raw) ? raw : [];
                setAllAttendance(list);
                setAttMeta({ total: raw.total ?? list.length, last_page: raw.last_page ?? 1 });
            }),
            api.get('/attendance/stats', { params: { date: filterDate, period: 'day' }, signal }).catch(() => {}),
            api.get('/ufrs', { signal }).then(r => { const d = r.data; setUfrs(Array.isArray(d) ? d : (d?.data || [])); }).catch(() => {}),
        ]);
    }, [filterDate, filterDepartment, filterStatus, debouncedSearch]);

    useEffect(() => { setPage(1); }, [filterDate, filterDepartment, filterStatus, debouncedSearch]);

    const loadTeacherData = useCallback(async (signal) => {
        await Promise.all([
            api.get('/attendance', { signal }).then(res => {
                const histData = res.data.data || res.data;
                setHistory(res.data.history || (Array.isArray(histData) ? histData : []));
                setTodaySchedules(res.data.today_schedules || []);
            }),
            api.get('/online-course-requests/today', { signal }).then(res => {
                setOnlineCourseRequest(res.data.request || null);
            }).catch(() => setOnlineCourseRequest(null)),
        ]);
    }, []);

    const loadWeekData = useCallback(async (signal) => {
        const res = await api.get('/attendance/weekly', { params: { weeks: weeksBack }, signal });
        setWeekData(res.data);
    }, [weeksBack]);

    const loadSelfAttendance = useCallback(async () => {
        try {
            const res = await api.get('/attendance', { params: { self: 1 } });
            setSelfAttendance(res.data.today || null);
        } catch (err) {
            console.error('Error loading self attendance:', err);
        }
    }, []);

    useEffect(() => {
        if (!user) return;
        const controller = new AbortController();
        let cancelled = false;
        const load = async () => {
            setLoading(true);
            try {
                const pinResponse = await api.get('/attendance/my-pin', { signal: controller.signal });
                if (!cancelled) {
                    const pin = pinResponse.data?.pin || pinResponse.data || '';
                    console.log('PIN loaded:', { pin, full: pinResponse.data }); // Debug
                    setConfiguredPin(typeof pin === 'string' ? pin : pin.pin || '');
                }
                if (isTeacher) {
                    await loadTeacherData(controller.signal);
                    await loadWeekData(controller.signal);
                }
                if (isManagement) {
                    await loadManagementData(controller.signal, page);
                    const self = await api.get('/attendance', { params: { self: 1 }, signal: controller.signal });
                    if (!cancelled) setSelfAttendance(self.data.today || null);
                    if (isAdminOrHR) {
                        const r = await api.get('/teachers', { signal: controller.signal });
                        if (!cancelled) { const d = r.data; setTeachers(Array.isArray(d) ? d : (d?.data || [])); }
                    }
                }
            } catch (err) {
                if (err.name !== 'CanceledError' && !cancelled) toast.error('Erreur chargement des donnees');
            } finally {
                if (!cancelled) setLoading(false);
            }
        };
        load();
        return () => { cancelled = true; controller.abort(); };
    }, [user, isTeacher, isManagement, isAdminOrHR, loadTeacherData, loadWeekData, loadManagementData, page]);

    useEffect(() => {
        if (!autoRefresh || !isManagement) return;
        const t = setInterval(() => { const c = new AbortController(); loadManagementData(c.signal, page); }, 30000);
        return () => clearInterval(t);
    }, [autoRefresh, isManagement, loadManagementData, page]);

    useEffect(() => { const t = setTimeout(() => setDebouncedSearch(searchName), 400); return () => clearTimeout(t); }, [searchName]);

    const fetchMyAttendance = useCallback(async () => {
        try {
            await Promise.all([
                api.get('/attendance').then(res => {
                    setTodaySchedules(res.data.today_schedules || []);
                    const histData = res.data.data || res.data;
                    setHistory(res.data.history || (Array.isArray(histData) ? histData : []));
                }),
                api.get('/schedules').then(res => {
                    const data = res.data?.data || res.data || [];
                    setSchedules(Array.isArray(data) ? data : []);
                }).catch(err => {
                    console.error('Erreur chargement schedules:', err.message);
                    setSchedules([]);
                }),
                api.get('/online-course-requests/today').then(res => {
                    setOnlineCourseRequest(res.data.request || null);
                }).catch(err => {
                    console.error('Erreur chargement online-course-requests:', err.message);
                    setOnlineCourseRequest(null);
                }),
            ]);
        } catch (err) {
            console.error('Erreur fetchMyAttendance:', err);
            toast.error('Erreur chargement');
        }
    }, []);

    useEffect(() => {
        if (todaySchedules && todaySchedules.length > 0) {
            setActiveSchedule(todaySchedules.find(s => s.check_in_time && !s.check_out_time) || null);
            setCurrentSchedule(todaySchedules.find(s => !s.check_in_time) || null);
        } else {
            setActiveSchedule(null);
            setCurrentSchedule(null);
        }
    }, [todaySchedules]);

    const handleSubmitOnlineRequest = useCallback(async (e) => {
        e.preventDefault();
        if (!onlineRequestForm.schedule_id || !onlineRequestForm.course_date) {
            toast.error('Veuillez remplir tous les champs');
            return;
        }
        setSubmittingOnlineRequest(true);
        try {
            const payload = {
                schedule_id: parseInt(onlineRequestForm.schedule_id),
                course_date: onlineRequestForm.course_date
            };
            await api.post('/online-course-requests', payload);
            toast.success('✅ Demande soumise! En attente de validation par l\'administration');
            setShowOnlineRequestModal(false);
            setOnlineRequestForm({ schedule_id: '', course_date: '' });
            await fetchMyAttendance();
        } catch (err) {
            console.error('Erreur submission:', err);
            toast.error(err.response?.data?.message || 'Erreur de soumission');
        } finally {
            setSubmittingOnlineRequest(false);
        }
    }, [onlineRequestForm, fetchMyAttendance]);

    const handleCheckIn = useCallback(async (e) => {
        e.preventDefault();
        if (!code || code.length < 4) { toast.error('Veuillez saisir votre code PIN (4 à 6 chiffres)'); return; }
        try { const res = await api.post('/attendance/check-in', { pin: code }); toast.success(res.data.message); await fetchMyAttendance(); setCode(''); }
        catch (error) { toast.error(error.response?.data?.message || 'Erreur de pointage'); }
    }, [code, fetchMyAttendance]);

    const handleCheckOut = useCallback(async () => {
        try {
            const res = await api.post('/attendance/check-out');
            toast.success(res.data.message);
            if (isManagement) {
                await Promise.all([
                    loadSelfAttendance(),
                    loadManagementData(new AbortController().signal, page)
                ]);
            } else {
                await fetchMyAttendance();
            }
        }
        catch (error) { toast.error(error.response?.data?.message || 'Erreur'); }
    }, [fetchMyAttendance, isManagement, loadManagementData, loadSelfAttendance, page]);

    const handleCheckInOnline = useCallback(async () => {
        if (!activeSchedule) { toast.error('Aucun cours sélectionné'); return; }
        try {
            const res = await api.post('/attendance/check-in-online', { schedule_id: activeSchedule.id });
            toast.success(res.data.message);
            await fetchMyAttendance();
        } catch (error) { toast.error(error?.response?.data?.message || 'Erreur du pointage en ligne'); }
    }, [activeSchedule, fetchMyAttendance]);

    const handleCheckOutOnline = useCallback(async () => {
        if (!activeSchedule) { toast.error('Aucun cours sélectionné'); return; }
        try {
            const res = await api.post('/attendance/check-out-online', { schedule_id: activeSchedule.id });
            toast.success(res.data.message);
            await fetchMyAttendance();
        } catch (error) { toast.error(error?.response?.data?.message || 'Erreur lors de la sortie en ligne'); }
    }, [activeSchedule, fetchMyAttendance]);

    const handleStaffCheckIn = useCallback(async () => {
        if (staffPin.length < 4) { toast.error('Saisissez votre PIN administratif'); return; }
        try {
            const res = await api.post('/attendance/check-in', { pin: staffPin });
            toast.success(res.data.message);
            setStaffPin('');
            await Promise.all([
                loadSelfAttendance(),
                loadManagementData(new AbortController().signal, page)
            ]);
        } catch (error) { toast.error(error.response?.data?.message || 'Erreur de pointage'); }
    }, [staffPin, loadManagementData, loadSelfAttendance, page]);

    const handleReportAbsence = useCallback(async (e) => {
        e.preventDefault();
        try {
            await api.post('/attendance/report-absence', { attendance_date: absenceForm.attendance_date, status: absenceForm.status, reason: absenceForm.reason || null });
            toast.success('Signalement enregistre');
            setAbsenceForm({ attendance_date: new Date().toISOString().split('T')[0], status: 'absent', reason: '' });
            await fetchMyAttendance();
            if (isManagement) {
                const response = await api.get('/attendance', { params: { self: 1 } });
                setSelfAttendance(response.data.today || null);
            }
            setShowAbsenceModal(false);
        } catch (error) {
            const data = error.response?.data;
            const msg = data?.messages ? Object.values(data.messages).flat().join(' | ') : data?.message || 'Erreur';
            toast.error(msg);
        }
    }, [absenceForm, fetchMyAttendance, isManagement]);

    const handleDeclareAvailability = useCallback(async (e) => {
        e.preventDefault();
        try {
            const slots = availabilityForm.time_slots ? availabilityForm.time_slots.split(',').map(s => s.trim()).filter(Boolean) : [];
            await api.post('/attendance/declare-availability', { date: availabilityForm.date, available: availabilityForm.available, time_slots: slots });
            toast.success('Disponibilite enregistree');
            setAvailabilityForm({ date: new Date().toISOString().split('T')[0], available: true, time_slots: '' });
            await fetchMyAttendance();
            setShowAvailabilityModal(false);
        } catch (error) {
            const data = error.response?.data;
            const msg = data?.messages ? Object.values(data.messages).flat().join(' | ') : data?.message || 'Erreur';
            toast.error(msg);
        }
    }, [availabilityForm, fetchMyAttendance]);

    const handleRecordAbsence = useCallback(async (e) => {
        e.preventDefault();
        try {
            await api.post('/attendance/record-absence', { teacher_id: recordAbsenceForm.teacher_id, attendance_date: recordAbsenceForm.attendance_date, status: recordAbsenceForm.status, reason: recordAbsenceForm.reason || null });
            toast.success('Absence enregistree');
            setRecordAbsenceForm({ teacher_id: '', attendance_date: new Date().toISOString().split('T')[0], status: 'absent', reason: '' });
            const c = new AbortController();
            await loadManagementData(c.signal, page);
            setShowRecordAbsenceModal(false);
        } catch (error) {
            const data = error.response?.data;
            const msg = data?.messages ? Object.values(data.messages).flat().join(' | ') : data?.message || 'Erreur';
            toast.error(msg);
        }
    }, [recordAbsenceForm, loadManagementData, page]);

    const handleValidateAbsence = useCallback(async (e) => {
        e.preventDefault();
        try {
            await api.patch(`/attendance/${selectedAttendance.id}/validate-absence`, { justified: validateForm.justified, notes: validateForm.notes || null });
            toast.success('Absence ' + (validateForm.justified ? 'justifiee' : 'non justifiee'));
            setSelectedAttendance(null);
            setValidateForm({ justified: true, notes: '' });
            const c = new AbortController();
            await loadManagementData(c.signal, page);
            setShowValidateAbsenceModal(false);
        } catch (error) {
            const data = error.response?.data;
            const msg = data?.messages ? Object.values(data.messages).flat().join(' | ') : data?.message || 'Erreur';
            toast.error(msg);
        }
    }, [selectedAttendance, validateForm, loadManagementData, page]);

    const handleExport = useCallback(async () => {
        if (exporting) return;
        setExporting(true);
        try {
            const params = new URLSearchParams();
            if (filterDate) params.set('date', filterDate);
            if (filterDepartment) params.set('ufr_id', filterDepartment);
            if (filterStatus) params.set('status', filterStatus);
            const res = await api.get(`/attendance/export?${params.toString()}`, { responseType: 'blob' });
            const url = window.URL.createObjectURL(new Blob([res.data]));
            const link = document.createElement('a');
            link.href = url;
            link.setAttribute('download', `pointage_export_${new Date().toISOString().slice(0,10)}.csv`);
            document.body.appendChild(link);
            link.click();
            link.remove();
            window.URL.revokeObjectURL(url);
        } catch (error) {
            toast.error(error.response?.data?.message || "Erreur lors de l'export");
        } finally {
            setExporting(false);
        }
    }, [filterDate, filterDepartment, filterStatus, exporting]);

    if (loading) return (
        <div className="min-h-screen">
            <div className="max-w-[1400px] mx-auto space-y-6 px-2 sm:px-4 pt-2">
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    {[...Array(4)].map((_, i) => <SkeletonCard key={i} />)}
                </div>
                <div className="flex justify-center py-20">
                    <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600" />
                </div>
            </div>
        </div>
    );

    if (viewMode === 'teacher' || (!isManagement && isTeacher)) {
        const hasAnyCourse = todaySchedules.length > 0;

        return (
            <div className="max-w-4xl mx-auto space-y-6 sm:space-y-8 animate-fade-in pb-20 px-2 sm:px-0">
                {hasDualRole && (
                    <div className="flex items-center gap-2 p-1.5 bg-slate-100 rounded-2xl w-fit">
                        <button
                            type="button"
                            onClick={() => setViewMode('teacher')}
                            className="px-5 py-2.5 rounded-xl font-bold text-xs uppercase tracking-wider bg-white text-indigo-700 shadow-md shadow-slate-200"
                        >
                            👨‍🏫 Mon Pointage Enseignant
                        </button>
                        <button
                            type="button"
                            onClick={() => setViewMode('management')}
                            className="px-5 py-2.5 rounded-xl font-bold text-xs uppercase tracking-wider text-slate-500 hover:text-slate-900"
                        >
                            📋 Registre des Présences (UFR)
                        </button>
                    </div>
                )}

                <header className="text-center space-y-3">
                    <h1 className="text-2xl sm:text-4xl font-black text-gray-900 tracking-tighter italic">Mon Pointage</h1>
                    <p className="text-gray-500 font-medium text-sm sm:text-base">{new Date().toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })}</p>
                    <div className="inline-block bg-amber-100 border-2 border-amber-300 rounded-xl px-6 py-3">
                        <p className="text-xs text-amber-700 uppercase font-bold mb-1">🔑 Votre PIN Permanent</p>
                        <p className="text-2xl font-black text-amber-600 font-mono tracking-wider">{configuredPin || '••••••'}</p>
                    </div>
                </header>

                {onlineCourseRequest && onlineCourseRequest.status === 'approved' && (
                    <div className="bg-gradient-to-r from-emerald-50 to-teal-50 rounded-[2rem] border-2 border-emerald-200 p-6 sm:p-8 shadow-lg shadow-emerald-100/50">
                        <div className="flex items-start gap-4">
                            <div className="w-12 h-12 bg-emerald-500 rounded-full flex items-center justify-center flex-shrink-0">
                                <CheckCircle size={24} className="text-white" />
                            </div>
                            <div className="flex-1 min-w-0">
                                <h3 className="text-lg font-black text-emerald-900 mb-2">✅ Cours en ligne approuvé</h3>
                                <p className="text-sm text-emerald-800 mb-3">L'administration a validé votre demande de faire cours en ligne aujourd'hui.</p>
                                <div className="bg-white rounded-xl p-4 border border-emerald-200">
                                    <p className="text-xs text-gray-600 uppercase tracking-widest font-bold mb-1">Code d'accès temporaire</p>
                                    <p className="text-2xl font-black text-emerald-600 font-mono tracking-wider">{onlineCourseRequest.temporary_code}</p>
                                    <p className="text-xs text-gray-500 mt-2">Utilisez ce code pour pointer votre présence en ligne</p>
                                </div>
                            </div>
                        </div>
                    </div>
                )}

                {!hasAnyCourse && (
                    <div className="bg-indigo-50/70 border border-indigo-200 rounded-2xl p-5 flex items-center gap-4">
                        <div className="w-10 h-10 bg-indigo-100 rounded-xl flex items-center justify-center flex-shrink-0 text-indigo-600">
                            <BookOpen size={20} />
                        </div>
                        <div>
                            <p className="text-sm font-bold text-indigo-900">Aucun cours planifié aujourd'hui</p>
                            <p className="text-xs text-indigo-600">Vous pouvez enregistrer votre présence générale sur le campus avec le formulaire ci-dessous.</p>
                        </div>
                    </div>
                )}

                <div className="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    {hasAnyCourse && (
                    <div className="bg-gradient-to-br from-blue-50 to-cyan-50 rounded-[2.5rem] shadow-xl border-2 border-blue-200 p-6">
                        <h3 className="text-lg font-black text-blue-900 mb-4">📱 POINTAGE EN LIGNE</h3>
                        {onlineCourseRequest?.status === 'approved' ? (
                            <div className="space-y-3">
                                <div className="bg-white rounded-xl p-4 border-2 border-emerald-200">
                                    <p className="text-xs text-emerald-700 uppercase font-bold mb-2">Code temporaire</p>
                                    <p className="text-3xl font-black text-emerald-600 font-mono tracking-wider">{onlineCourseRequest.temporary_code}</p>
                                </div>
                                <div className="grid grid-cols-2 gap-3">
                                    <button onClick={handleCheckInOnline} className="px-4 py-3 bg-blue-600 text-white text-sm font-bold rounded-lg hover:bg-blue-700">▶️ Démarrer</button>
                                    <button onClick={handleCheckOutOnline} className="px-4 py-3 bg-red-600 text-white text-sm font-bold rounded-lg hover:bg-red-700">⏹️ Terminer</button>
                                </div>
                            </div>
                        ) : (
                            <button onClick={() => setShowOnlineRequestModal(true)} className="w-full px-4 py-3 bg-blue-600 text-white text-sm font-bold rounded-lg hover:bg-blue-700">
                                📤 Demander Cours en Ligne
                            </button>
                        )}
                    </div>
                    )}

                    <div className={`bg-gradient-to-br from-orange-50 to-red-50 rounded-[2.5rem] shadow-xl border-2 border-orange-200 p-6 ${!hasAnyCourse ? 'sm:col-span-2' : ''}`}>
                        <h3 className="text-lg font-black text-orange-900 mb-4">🏫 POINTAGE EN PRÉSENTIEL</h3>
                        <div className="space-y-3">
                            {todayAttendance?.check_in_time && !todayAttendance?.check_out_time ? (
                                <div className="space-y-3">
                                    <div className="p-4 bg-emerald-50 rounded-xl border border-emerald-200">
                                        <div className="flex items-center gap-2 text-emerald-800 font-bold text-sm">
                                            <CheckCircle size={18} className="text-emerald-600" />
                                            Arrivée enregistrée à {todayAttendance.check_in_time.substring(11, 16)}
                                        </div>
                                        <p className="text-xs text-emerald-600 mt-1">N'oubliez pas d'enregistrer votre départ à la fin de vos cours.</p>
                                    </div>
                                    <button
                                        type="button"
                                        onClick={handleCheckOut}
                                        className="w-full px-4 py-3 bg-red-600 text-white text-sm font-bold rounded-lg hover:bg-red-700 transition-all flex items-center justify-center gap-2 shadow-md active:scale-95"
                                    >
                                        ⏹️ Pointer mon départ
                                    </button>
                                </div>
                            ) : todayAttendance?.check_in_time && todayAttendance?.check_out_time ? (
                                <div className="p-4 bg-emerald-50 rounded-xl border border-emerald-200 text-center space-y-1">
                                    <p className="text-sm font-bold text-emerald-800">✅ Pointage terminé pour aujourd'hui</p>
                                    <p className="text-xs text-emerald-600 font-mono">
                                        Entrée : {todayAttendance.check_in_time.substring(11, 16)} — Sortie : {todayAttendance.check_out_time.substring(11, 16)}
                                    </p>
                                </div>
                            ) : (
                                <form onSubmit={handleCheckIn} className="space-y-4">
                                    <div className="bg-white rounded-xl p-4 border-2 border-amber-200 text-center">
                                        <p className="text-xs text-amber-700 uppercase font-bold mb-2">👇 Votre PIN Permanent</p>
                                        <p className="text-3xl font-black text-amber-600 font-mono tracking-wider mb-3">{configuredPin || '••••••'}</p>
                                    </div>
                                    <input
                                        type="password"
                                        placeholder="Entrez le code ci-dessus"
                                        value={code}
                                        onChange={(e) => setCode(e.target.value)}
                                        className="w-full px-4 py-3 rounded-lg border border-orange-300 focus:outline-none focus:ring-2 focus:ring-orange-500 text-lg font-mono tracking-widest text-center"
                                        autoFocus
                                    />
                                    <button type="submit" className="w-full px-4 py-3 bg-orange-600 text-white text-sm font-bold rounded-lg hover:bg-orange-700 shadow-md transition-all active:scale-95">
                                        🏢 Pointer Présence (Arrivée)
                                    </button>
                                </form>
                            )}
                        </div>
                    </div>
                </div>

                {hasAnyCourse && (
                    <div className="bg-white rounded-[2.5rem] shadow-2xl shadow-gray-200/60 border border-gray-50 p-6 sm:p-8">
                        <div className="flex items-center justify-between mb-6">
                            <h2 className="text-xl font-black text-gray-900 tracking-tighter flex items-center gap-2"><Calendar size={20} className="text-indigo-500" /> Mes cours du jour</h2>
                            <LiveClock />
                        </div>
                        <div className="space-y-3">
                            {todaySchedules.map((s) => {
                                const isActive = activeSchedule?.id === s.id;
                                const isDone = s.check_out_time;
                                const isCurrent = currentSchedule?.id === s.id;
                                return (
                                    <div key={s.id} className={`p-4 rounded-2xl border transition-all ${isCurrent ? 'border-indigo-300 bg-indigo-50/50 ring-2 ring-indigo-200' : isActive ? 'border-emerald-200 bg-emerald-50/40' : isDone ? 'border-gray-100 bg-gray-50/50' : 'border-gray-100 bg-white'}`}>
                                        <div className="flex items-start justify-between gap-3">
                                            <div className="flex-1 min-w-0">
                                                <div className="flex items-center gap-2 flex-wrap">
                                                    <BookOpen size={15} className="text-indigo-500" />
                                                    <p className="font-bold text-slate-900 text-sm">{s.subject_name || 'Matière'}</p>
                                                    {s.teaching_type && <span className="text-[10px] font-black px-2 py-0.5 rounded bg-indigo-100 text-indigo-600 uppercase">{s.teaching_type}</span>}
                                                </div>
                                                <div className="flex items-center gap-3 mt-1.5 text-xs text-slate-500 flex-wrap">
                                                    <span className="flex items-center gap-1"><MapPin size={11} /> {s.classroom_name || '—'}</span>
                                                    {s.level && <span className="font-bold text-indigo-600">Niveau {s.level}</span>}
                                                    <span className="font-mono font-bold text-slate-700">{s.start_time?.substring(11, 16)} - {s.end_time?.substring(11, 16)}</span>
                                                </div>
                                            </div>
                                            <div className="flex flex-col items-end gap-1.5 shrink-0">
                                                {isDone ? (
                                                    <span className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[10px] font-black uppercase bg-emerald-100 text-emerald-700"><CheckCircle size={12} /> Terminé</span>
                                                ) : isActive ? (
                                                    <span className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[10px] font-black uppercase bg-blue-100 text-blue-700"><span className="w-1.5 h-1.5 rounded-full bg-blue-500 animate-pulse" /> En cours</span>
                                                ) : s.status === 'late' ? (
                                                    <span className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[10px] font-black uppercase bg-amber-100 text-amber-700"><AlertCircle size={12} /> Retard</span>
                                                ) : isCurrent ? (
                                                    <span className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[10px] font-black uppercase bg-indigo-100 text-indigo-700">À pointer</span>
                                                ) : (
                                                    <span className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[10px] font-black uppercase bg-gray-100 text-gray-500">En attente</span>
                                                )}
                                                {s.check_in_time && !s.check_out_time && <span className="font-mono text-[11px] text-slate-500">Arrivée {s.check_in_time.substring(11, 16)}</span>}
                                            </div>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                )}

                <div className="bg-white rounded-[2.5rem] shadow-xl shadow-gray-200/40 border border-gray-50 p-8">
                    <h3 className="text-xl font-black text-gray-900 tracking-tighter mb-6">Historique - 4 semaines</h3>
                    <CalendarHistory weekData={weekData} weeksBack={weeksBack} onPrevWeek={() => setWeeksBack(w => Math.min(w + 1, 3))} onNextWeek={() => setWeeksBack(w => Math.max(w - 1, 0))} />
                </div>
                {history.length > 0 && (
                    <div className="bg-white rounded-[2.5rem] shadow-xl shadow-gray-200/40 border border-gray-50 p-8">
                        <h3 className="text-xl font-black text-gray-900 tracking-tighter mb-6">Details recents</h3>
                        <div className="space-y-3">
                             {history.slice(0, 7).map((record, i) => (
                                 <div key={i} className="p-4 bg-gray-50/50 rounded-2xl border border-transparent hover:shadow-lg transition-all space-y-2">
                                     <div className="flex items-center justify-between gap-3">
                                         <div className="flex items-center gap-3"><Calendar size={16} className="text-indigo-600" /><div><p className="text-[10px] font-black text-gray-400 uppercase tracking-widest">{new Date(record.attendance_date).toLocaleDateString('fr-FR', { weekday: 'short' })}</p><p className="text-xs font-black text-gray-800">{new Date(record.attendance_date).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' })}</p></div></div>
                                         <div className="flex items-center gap-3"><StatusBadge status={record.status} />{record.check_in_time && <span className="font-mono text-xs text-gray-500">{record.check_in_time.substring(11, 16)}</span>}</div>
                                     </div>
                                     {record.notes && <p className="text-xs text-slate-500"><span className="font-bold text-slate-600">Motif :</span> {record.notes}</p>}
                                     {record.justification_status && <p className={`text-xs font-semibold ${record.justification_status === 'justified' ? 'text-emerald-600' : 'text-red-600'}`}>
                                         {record.justification_status === 'justified' ? 'Justification validée' : 'Justification refusée'}{record.validator?.name ? ` par ${record.validator.name}` : ''}
                                     </p>}
                                 </div>
                             ))}
                        </div>
                    </div>
                )}
                <FormModal isOpen={showAbsenceModal} onClose={() => setShowAbsenceModal(false)} onSubmit={handleReportAbsence} title="Signaler Absence ou Retard" submitText="Signaler">
                    <div className="space-y-5">
                        <div className="form-group"><label className="form-label">Date</label><input required type="date" className="input-field" max={new Date().toISOString().split('T')[0]} value={absenceForm.attendance_date} onChange={e => setAbsenceForm({...absenceForm, attendance_date: e.target.value})} /></div>
                        <div className="form-group"><label className="form-label">Type</label><select required className="select-field" value={absenceForm.status} onChange={e => setAbsenceForm({...absenceForm, status: e.target.value})}><option value="absent">Absent</option><option value="late">Retard</option></select></div>
                        <div className="form-group"><label className="form-label">Motif</label><textarea className="textarea-field" rows="3" placeholder="Motif optionnel..." value={absenceForm.reason} onChange={e => setAbsenceForm({...absenceForm, reason: e.target.value})}></textarea></div>
                    </div>
                </FormModal>
                <FormModal isOpen={showAvailabilityModal} onClose={() => setShowAvailabilityModal(false)} onSubmit={handleDeclareAvailability} title="Declarer ma Disponibilite" subtitle="Indiquez votre presence ou absence pour la journee" submitText="Enregistrer" submitClassName="btn-success">
                    <div className="space-y-5">
                        <div className="form-group"><label className="form-label">Date</label><input required type="date" className="input-field" max={new Date().toISOString().split('T')[0]} value={availabilityForm.date} onChange={e => setAvailabilityForm({...availabilityForm, date: e.target.value})} /></div>
                        <div className="form-group"><label className="form-label">Disponibilite</label><div className="grid grid-cols-2 gap-3">
                            <label className={`flex items-center justify-center gap-2 p-4 rounded-2xl border-2 cursor-pointer transition-all font-black text-xs uppercase tracking-widest ${availabilityForm.available ? 'border-emerald-500 bg-emerald-50 text-emerald-700' : 'border-gray-200 bg-white text-gray-400'}`}><input type="radio" className="hidden" checked={availabilityForm.available === true} onChange={() => setAvailabilityForm({...availabilityForm, available: true})} /><UserCheck size={16} /> Present</label>
                            <label className={`flex items-center justify-center gap-2 p-4 rounded-2xl border-2 cursor-pointer transition-all font-black text-xs uppercase tracking-widest ${!availabilityForm.available ? 'border-red-500 bg-red-50 text-red-700' : 'border-gray-200 bg-white text-gray-400'}`}><input type="radio" className="hidden" checked={availabilityForm.available === false} onChange={() => setAvailabilityForm({...availabilityForm, available: false})} /><UserX size={16} /> Absent</label>
                        </div></div>
                        <div className="form-group"><label className="form-label">Creneaux (optionnel)</label><input type="text" className="input-field" placeholder="Ex: 08:00-10:00, 14:00-16:00" value={availabilityForm.time_slots} onChange={e => setAvailabilityForm({...availabilityForm, time_slots: e.target.value})} /><p className="text-[11px] text-gray-400 mt-2">Separez les creneaux par des virgules.</p></div>
                    </div>
                </FormModal>
            </div>
        );
    }

    if (isManagement) {
        return (
            <div className="min-h-screen">
                <div className="max-w-[1400px] mx-auto space-y-6 px-2 sm:px-4 pt-2">
                    {hasDualRole && (
                        <div className="flex items-center gap-2 mb-2 p-1.5 bg-slate-100 rounded-2xl w-fit">
                            <button
                                type="button"
                                onClick={() => setViewMode('teacher')}
                                className="px-5 py-2.5 rounded-xl font-bold text-xs uppercase tracking-wider text-slate-500 hover:text-slate-900"
                            >
                                👨‍🏫 Mon Pointage Enseignant
                            </button>
                            <button
                                type="button"
                                onClick={() => setViewMode('management')}
                                className="px-5 py-2.5 rounded-xl font-bold text-xs uppercase tracking-wider bg-white text-indigo-700 shadow-md shadow-slate-200"
                            >
                                📋 Registre des Présences (UFR)
                            </button>
                        </div>
                    )}
                    <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                        <div>
                            <div className="flex items-center gap-3 mb-1">
                                <div className="w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-500 to-blue-600 flex items-center justify-center shadow-lg shadow-indigo-500/25">
                                    <Users size={20} className="text-white" />
                                </div>
                                <h1 className="text-2xl font-bold text-slate-900 tracking-tight">Registre des Presences</h1>
                            </div>
                            <p className="text-slate-400 text-sm ml-[52px]">{isAcademicManager ? 'Suivi de mon departement' : 'Suivi et validation des absences'}</p>
                        </div>
                        <div className="flex items-center gap-3 flex-wrap">
                            <div className="bg-white rounded-2xl border border-slate-100 p-3 flex items-center gap-2">
                                <Fingerprint size={16} className="text-indigo-400" />
                                <span className="text-[10px] font-bold text-slate-400 uppercase">PIN Individuel</span>
                            </div>
                            <div className="bg-white rounded-xl border border-slate-100 p-2.5 flex items-center gap-2">
                                <Calendar size={16} className="text-indigo-600" />
                                <input type="date" className="border-none focus:ring-0 text-sm font-bold text-slate-700 bg-transparent p-0 w-32" value={filterDate} onChange={(e) => setFilterDate(e.target.value)} />
                            </div>
                             <button onClick={handleExport} disabled={exporting} className="flex items-center gap-2 px-4 py-2.5 bg-emerald-600 text-white rounded-xl font-semibold text-sm shadow-lg shadow-emerald-500/25 hover:bg-emerald-700 transition-all active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed">
                                 <Download size={16} /><span className="font-bold text-xs uppercase">{exporting ? 'Export...' : 'CSV'}</span>
                            </button>
                            <button onClick={() => setAutoRefresh(!autoRefresh)} className={`p-2.5 rounded-xl transition-all ${autoRefresh ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-500'}`}>
                                <RefreshCw size={14} className={autoRefresh ? 'animate-spin' : ''} style={{ animationDuration: '3s' }} />
                            </button>
                        </div>
                    </div>

                    <div className="bg-white rounded-2xl border border-indigo-100 p-4 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                        <div>
                            <p className="text-xs font-black text-slate-400 uppercase tracking-wider">Mon pointage du jour</p>
                            {configuredPin && (
                                <p className="text-3xl font-black font-mono text-indigo-700 mt-2 tracking-widest">{configuredPin}</p>
                            )}
                            <p className="text-sm font-semibold text-slate-700 mt-3">{selfAttendance?.check_in_time ? `Entree a ${selfAttendance.check_in_time.substring(11, 16)}` : 'Aucun pointage personnel'}</p>
                            {selfAttendance?.check_out_time && <p className="text-xs text-slate-400 mt-1">Sortie a {selfAttendance.check_out_time.substring(11, 16)}</p>}
                        </div>
                        <div className="flex flex-wrap gap-2">
                            {!selfAttendance?.check_in_time && <><input type="password" inputMode="numeric" maxLength={6} placeholder="PIN" value={staffPin} onChange={e => setStaffPin(e.target.value.replace(/[^0-9]/g, ''))} className="w-24 px-3 py-2.5 rounded-xl border border-slate-200 text-center text-sm" /><button onClick={handleStaffCheckIn} className="px-4 py-2.5 rounded-xl bg-emerald-600 text-white text-xs font-bold">Pointer mon arrivée</button></>}
                            {selfAttendance?.check_in_time && !selfAttendance?.check_out_time && <button onClick={handleCheckOut} className="px-4 py-2.5 rounded-xl bg-red-600 text-white text-xs font-bold">Pointer mon départ</button>}
                            <button onClick={() => setShowAbsenceModal(true)} className="px-4 py-2.5 rounded-xl bg-amber-100 text-amber-700 text-xs font-bold">Signaler absence / retard</button>
                        </div>
                    </div>

                    <div className="bg-white rounded-2xl border border-slate-100 p-3">
                        <div className="flex flex-wrap gap-3 items-center">
                            <div className="relative flex-1 min-w-[200px]">
                                <Search className="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-300" size={16} />
                                <input type="text" placeholder="Rechercher un enseignant..." className="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-100 rounded-xl text-sm text-slate-700 placeholder:text-slate-300 focus:outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-500/10 transition-all" value={searchName} onChange={e => setSearchName(e.target.value)} />
                            </div>
                            <select className="px-4 py-2.5 bg-slate-50 border border-slate-100 rounded-xl text-sm font-medium text-slate-600 cursor-pointer focus:outline-none" value={filterDepartment} onChange={e => setFilterDepartment(e.target.value)}>
                                <option value="">Tous les departements</option>
                                {ufrs.map(d => <option key={d.id} value={d.id}>{d.name}</option>)}
                            </select>
                            <select className="px-4 py-2.5 bg-slate-50 border border-slate-100 rounded-xl text-sm font-medium text-slate-600 cursor-pointer focus:outline-none" value={filterStatus} onChange={e => setFilterStatus(e.target.value)}>
                                <option value="">Tous les statuts</option>
                                <option value="present">Present</option>
                                <option value="late">Retard</option>
                                <option value="absent">Absent</option>
                            </select>
                            {isAdminOrHR && (
                                <button onClick={() => setShowRecordAbsenceModal(true)} className="flex items-center gap-2 px-4 py-2.5 bg-amber-600 text-white rounded-xl font-semibold text-sm hover:bg-amber-700 transition-all active:scale-95">
                                    <AlertCircle size={15} /><span className="font-bold text-xs uppercase">Enregistrer Absence</span>
                                </button>
                            )}
                        </div>
                    </div>

                    {allAttendance.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-24">
                            <div className="w-16 h-16 bg-slate-100 rounded-2xl flex items-center justify-center mb-4"><Clock size={28} className="text-slate-300" /></div>
                            <p className="text-base font-bold text-slate-400 mb-1">Aucun pointage</p>
                            <p className="text-xs text-slate-300">Les donnees de presence apparaitront ici</p>
                        </div>
                    ) : (
                        <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 mb-6">
                            {allAttendance.map((att) => {
                                const sm = STATUS_MAP[att.status] || STATUS_MAP.none;
                                return (
                                    <div key={att.id}
                                        className={`group relative bg-white rounded-2xl p-5 border border-slate-100 shadow-sm hover:shadow-lg hover:border-indigo-200 transition-all duration-300`}>
                                        <div className={`absolute top-0 left-0 right-0 h-1 bg-gradient-to-r ${att.status === 'present' ? 'from-emerald-400 to-teal-500' : att.status === 'late' ? 'from-amber-400 to-orange-500' : att.status === 'absent' ? 'from-red-400 to-rose-500' : 'from-indigo-400 to-purple-500'} opacity-100 rounded-t-2xl`} />

                                        <div className="flex items-start justify-between mb-4">
                                            <div className="flex items-center gap-3">
                                                <div className="w-11 h-11 rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center text-white font-bold text-sm shadow-md">
                                                    {(att.teacher?.user?.name || att.user?.name || '?').charAt(0).toUpperCase()}
                                                </div>
                                                <div className="min-w-0">
                                                    <h3 className="font-bold text-slate-900 text-sm truncate">{att.teacher?.user?.name || att.user?.name || 'Inconnu'}</h3>
                                                    <p className="text-[10px] font-mono font-semibold text-slate-400">{att.teacher?.employee_id || '-'}</p>
                                                </div>
                                            </div>
                                            <span className={`inline-flex items-center px-2.5 py-1 rounded-lg text-[10px] font-black uppercase tracking-wider border ${sm.badge}`}>
                                                <span className={`w-1.5 h-1.5 rounded-full ${sm.dot} mr-1.5`}></span>
                                                {sm.label}
                                            </span>
                                        </div>

                                        <div className="flex flex-wrap gap-1.5 mb-4">
                                            <span className="inline-flex items-center px-2.5 py-1 rounded-lg text-[10px] font-bold uppercase border bg-gray-50 text-gray-600 border-gray-200">
                                                {att.teacher?.ufr?.name || '-'}
                                            </span>
                                             {att.justification_status === 'justified' && (
                                                 <span className="inline-flex items-center px-2.5 py-1 rounded-lg text-[10px] font-black uppercase border bg-emerald-50 text-emerald-600 border-emerald-100">
                                                     <CheckCircle size={10} className="mr-1" /> Justifie
                                                 </span>
                                             )}
                                             {att.justification_status === 'unjustified' && (
                                                 <span className="inline-flex items-center px-2.5 py-1 rounded-lg text-[10px] font-black uppercase border bg-red-50 text-red-600 border-red-100">
                                                     <AlertCircle size={10} className="mr-1" /> Non justifie
                                                 </span>
                                             )}
                                             {!att.justification_status && (att.status === 'absent' || att.status === 'late' || att.status === 'half_day') && (
                                                 <span className="inline-flex items-center px-2.5 py-1 rounded-lg text-[10px] font-black uppercase border bg-amber-50 text-amber-600 border-amber-100">
                                                     A traiter
                                                 </span>
                                             )}
                                         </div>

                                         {att.notes && (
                                             <div className="mb-3 rounded-xl bg-slate-50 border border-slate-100 p-3 text-xs text-slate-600">
                                                 <p className="font-black uppercase tracking-wider text-[9px] text-slate-400 mb-1">Motif du professeur</p>
                                                 <p>{att.notes}</p>
                                             </div>
                                         )}
                                         {att.justification_notes && (
                                             <div className="mb-3 rounded-xl bg-blue-50 border border-blue-100 p-3 text-xs text-blue-700">
                                                 <p className="font-black uppercase tracking-wider text-[9px] text-blue-400 mb-1">Note de validation</p>
                                                 <p>{att.justification_notes}</p>
                                             </div>
                                         )}
                                         {att.justification_status && (
                                             <p className="text-[10px] text-slate-400 mb-3">
                                                 Traite par <span className="font-bold text-slate-600">{att.validator?.name || 'un responsable'}</span>
                                                 {att.justified_at ? ` le ${new Date(att.justified_at).toLocaleString('fr-FR')}` : ''}
                                             </p>
                                         )}

                                        <div className="grid grid-cols-2 gap-2 mb-4">
                                            <div className="bg-slate-50 rounded-xl p-2.5 text-center">
                                                <p className="text-[10px] font-bold text-slate-400 uppercase mb-0.5">Entree</p>
                                                <span className="font-mono font-black text-sm text-emerald-600">{att.check_in_time?.substring(11, 16) || '--:--'}</span>
                                            </div>
                                            <div className="bg-slate-50 rounded-xl p-2.5 text-center">
                                                <p className="text-[10px] font-bold text-slate-400 uppercase mb-0.5">Sortie</p>
                                                <span className="font-mono font-black text-sm text-amber-600">{att.check_out_time?.substring(11, 16) || '--:--'}</span>
                                            </div>
                                        </div>

                                         {(att.status === 'absent' || att.status === 'late' || att.status === 'half_day') && !att.justification_status && (isAdminOrHR || isAcademicManager) && (
                                             <div className="pt-3 border-t border-slate-100">
                                                 <button onClick={() => { setSelectedAttendance(att); setValidateForm({ justified: true, notes: '' }); setShowValidateAbsenceModal(true); }}
                                                     className="w-full flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl text-[11px] font-bold bg-blue-50 text-blue-600 hover:bg-blue-600 hover:text-white transition-all duration-200">
                                                     <CheckCircle size={12} /> Traiter le signalement
                                                 </button>
                                            </div>
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                    )}

                    <Pagination page={page} totalPages={attMeta.last_page} onPageChange={setPage} totalItems={attMeta.total} perPage={perPage} />

                    <FormModal isOpen={showRecordAbsenceModal} onClose={() => setShowRecordAbsenceModal(false)} onSubmit={handleRecordAbsence} title="Enregistrer une Absence" submitText="Enregistrer">
                        <div className="space-y-5">
                            <div className="form-group"><label className="form-label">Enseignant</label><select required className="select-field" value={recordAbsenceForm.teacher_id} onChange={e => setRecordAbsenceForm({...recordAbsenceForm, teacher_id: e.target.value})}><option value="">Selectionner...</option>{teachers.map(t => <option key={t.id} value={t.id}>{t.user?.name}</option>)}</select></div>
                            <div className="form-group"><label className="form-label">Date</label><input required type="date" className="input-field" max={new Date().toISOString().split('T')[0]} value={recordAbsenceForm.attendance_date} onChange={e => setRecordAbsenceForm({...recordAbsenceForm, attendance_date: e.target.value})} /></div>
                            <div className="form-group"><label className="form-label">Statut</label><select required className="select-field" value={recordAbsenceForm.status} onChange={e => setRecordAbsenceForm({...recordAbsenceForm, status: e.target.value})}><option value="absent">Absent</option><option value="late">Retard</option><option value="half_day">Demi-journee</option></select></div>
                            <div className="form-group"><label className="form-label">Motif</label><textarea className="textarea-field" rows="3" placeholder="Motif optionnel..." value={recordAbsenceForm.reason} onChange={e => setRecordAbsenceForm({...recordAbsenceForm, reason: e.target.value})}></textarea></div>
                        </div>
                    </FormModal>

                     <FormModal isOpen={showValidateAbsenceModal} onClose={() => setShowValidateAbsenceModal(false)} onSubmit={handleValidateAbsence} title={selectedAttendance?.status === 'late' ? 'Traiter un retard' : 'Traiter une absence'} submitText="Confirmer">
                         <div className="space-y-5">
                             {selectedAttendance?.notes && <div className="rounded-xl bg-slate-50 border border-slate-100 p-3 text-sm text-slate-600"><p className="text-[10px] font-black uppercase tracking-wider text-slate-400 mb-1">Motif du professeur</p>{selectedAttendance.notes}</div>}
                             <div className="form-group"><label className="form-label">Signalement justifie ?</label><select required className="select-field" value={String(validateForm.justified)} onChange={e => setValidateForm({...validateForm, justified: e.target.value === 'true'})}><option value="true">Oui - Justifie</option><option value="false">Non - Non justifie</option></select></div>
                             <div className="form-group"><label className="form-label">Commentaire de validation</label><textarea className="textarea-field" rows="3" maxLength={500} placeholder="Commentaire optionnel..." value={validateForm.notes} onChange={e => setValidateForm({...validateForm, notes: e.target.value})}></textarea></div>
                         </div>
                    </FormModal>

                    <FormModal isOpen={showAbsenceModal} onClose={() => setShowAbsenceModal(false)} onSubmit={handleReportAbsence} title="Signaler une absence ou un retard" submitText="Signaler">
                        <div className="space-y-5"><div className="form-group"><label className="form-label">Date</label><input required type="date" className="input-field" max={new Date().toISOString().split('T')[0]} value={absenceForm.attendance_date} onChange={e => setAbsenceForm({...absenceForm, attendance_date: e.target.value})} /></div><div className="form-group"><label className="form-label">Type</label><select required className="select-field" value={absenceForm.status} onChange={e => setAbsenceForm({...absenceForm, status: e.target.value})}><option value="absent">Absent</option><option value="late">Retard</option></select></div><div className="form-group"><label className="form-label">Motif</label><textarea className="textarea-field" rows="3" value={absenceForm.reason} onChange={e => setAbsenceForm({...absenceForm, reason: e.target.value})} /></div></div>
                    </FormModal>

                    <FormModal isOpen={showOnlineRequestModal} onClose={() => setShowOnlineRequestModal(false)} onSubmit={handleSubmitOnlineRequest} title="Demander un Cours en Ligne" submitText={submittingOnlineRequest ? "..." : "Soumettre"} disableSubmit={!onlineRequestForm.schedule_id || !onlineRequestForm.course_date}>
                        <div className="space-y-5">
                            {schedules.length === 0 ? (
                                <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                                    <p className="text-sm text-yellow-800">⚠️ Aucun cours trouvé. Veuillez vous assurer que vous avez des cours assignés.</p>
                                </div>
                            ) : (
                                <>
                                    <div className="form-group"><label className="form-label">Cours</label><select required className="select-field" value={onlineRequestForm.schedule_id} onChange={e => setOnlineRequestForm({...onlineRequestForm, schedule_id: e.target.value})}><option value="">Sélectionner un cours...</option>{schedules.map(s => <option key={s.id} value={s.id}>{s.subject?.name} - {s.start_time?.substring(11, 16)}</option>)}</select></div>
                                    <div className="form-group"><label className="form-label">Date du cours (minimum 2 jours avant)</label><input required type="date" className="input-field" min={new Date(Date.now() + 2*24*60*60*1000).toISOString().split('T')[0]} value={onlineRequestForm.course_date} onChange={e => setOnlineRequestForm({...onlineRequestForm, course_date: e.target.value})} /></div>
                                </>
                            )}
                            <p className="text-xs text-blue-600 bg-blue-50 p-3 rounded-lg">ℹ️ L'administration validera votre demande. Un code temporaire vous sera généré automatiquement.</p>
                        </div>
                    </FormModal>
                </div>
            </div>
        );
    }
};

export default Attendance;

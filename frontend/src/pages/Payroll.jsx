import { useEffect, useState, useCallback } from 'react';
import api from '../services/api';
import { dataRefresh } from '../services/dataRefresh';
import useAuthStore from '../store/authStore';
import { CreditCard, Download, DollarSign, FileText, CheckCircle, AlertCircle, Zap, TrendingDown, Calendar, Play, ChevronRight } from 'lucide-react';
import toast from '../utils/toast';
import ConfirmModal from '../components/ConfirmModal';
import Pagination from '../components/Pagination';
import { getUserRoles } from '../utils/roles';
import { MONTH_NAMES } from '../utils/constants';

const Payroll = () => {
    const user = useAuthStore((state) => state.user);
    const [payrolls, setPayrolls] = useState([]);
    const [loading, setLoading] = useState(true);
    const [page, setPage] = useState(1);
    const perPage = 6;
    const [pagination, setPagination] = useState({ current_page: 1, last_page: 1, total: 0 });
    const [previousPage, setPreviousPage] = useState(1);
    const [previousPagination, setPreviousPagination] = useState({ current_page: 1, last_page: 1, total: 0 });
    const [selectedMonth, setSelectedMonth] = useState(() => {
        const now = new Date();
        return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
    });
    const [previousPayrolls, setPreviousPayrolls] = useState([]);
    const [loadingPrevious, setLoadingPrevious] = useState(true);
    const [isCalculating, setIsCalculating] = useState(false);
    const EMPTY_STATS = { total: 0, calculated: 0, validated: 0, paid: 0, totalNet: 0 };
    const [stats, setStats] = useState(EMPTY_STATS);
    const [previousStats, setPreviousStats] = useState(EMPTY_STATS);
    const [showConfirm, setShowConfirm] = useState({ isOpen: false, action: null, payroll: null });
    const [showCalculateModal, setShowCalculateModal] = useState(false);
    const [calculationProgress, setCalculationProgress] = useState('');
    const [showIndividualCalculateModal, setShowIndividualCalculateModal] = useState(false);
    const [teachers, setTeachers] = useState([]);
    const formForMonth = (ym) => ({ teacher_id: '', month: String(parseInt(ym.split('-')[1], 10)), year: ym.split('-')[0] });
    const [individualForm, setIndividualForm] = useState(() => formForMonth(selectedMonth));
    const [attendanceData, setAttendanceData] = useState(null);
    const [calculationPreview, setCalculationPreview] = useState(null);
    const [loadingAttendance, setLoadingAttendance] = useState(false);

    const userRoles = getUserRoles(user);
    const isAdmin = userRoles.includes('admin');
    const isAccountant = userRoles.includes('accountant') || isAdmin;
    const isTeacher = userRoles.includes('teacher') && !isAccountant;
    const currentTeacherId = user?.teacher?.id;

    const loadMonth = useCallback(async (monthNum, yearNum, pageNum) => {
        const params = { page: pageNum, per_page: perPage, month: monthNum, year: yearNum };
        if (isTeacher && currentTeacherId) params.teacher_id = currentTeacherId;
        const { data } = await api.get('/payroll', { params });
        const summary = data.summary || {};
        return {
            items: data.data || [],
            pagination: { current_page: data.current_page || 1, last_page: data.last_page || 1, total: data.total || 0 },
            stats: {
                total: data.total || 0,
                calculated: (summary.calculated || 0) + (summary.draft || 0),
                validated: summary.validated || 0,
                paid: summary.paid || 0,
                totalNet: Number(summary.total_net || 0),
            },
        };
    }, [isTeacher, currentTeacherId]);

    const [selectedYear, selectedMonthNum] = selectedMonth.split('-').map(Number);
    const previousMonth = selectedMonthNum === 1
        ? { year: selectedYear - 1, month: 12 }
        : { year: selectedYear, month: selectedMonthNum - 1 };

    const fetchPayrolls = useCallback(async (pageNum = 1) => {
        setLoading(true);
        try {
            const result = await loadMonth(selectedMonthNum, selectedYear, pageNum);
            setPayrolls(result.items);
            setPagination(result.pagination);
            setStats(result.stats);
        } catch {
            toast.error('Erreur chargement paies');
        } finally {
            setLoading(false);
        }
    }, [loadMonth, selectedMonthNum, selectedYear]);

    const fetchPreviousPayrolls = useCallback(async (pageNum = 1) => {
        setLoadingPrevious(true);
        try {
            const result = await loadMonth(previousMonth.month, previousMonth.year, pageNum);
            setPreviousPayrolls(result.items);
            setPreviousPagination(result.pagination);
            setPreviousStats(result.stats);
        } catch {
            setPreviousPayrolls([]);
        } finally {
            setLoadingPrevious(false);
        }
    }, [loadMonth, previousMonth.month, previousMonth.year]);

    const refreshAll = useCallback(async () => {
        await Promise.all([fetchPayrolls(page), fetchPreviousPayrolls(previousPage)]);
    }, [page, previousPage, fetchPayrolls, fetchPreviousPayrolls]);

    useEffect(() => {
        fetchPayrolls(page);
    }, [page, fetchPayrolls]);

    useEffect(() => {
        fetchPreviousPayrolls(previousPage);
    }, [previousPage, fetchPreviousPayrolls]);

    useEffect(() => {
        if (isAccountant && showIndividualCalculateModal) {
            const fetchTeachers = async () => {
                try {
                    const response = await api.get('/teachers', { params: { per_page: 100 } });
                    setTeachers(response.data.data || []);
                } catch {
                    toast.error('Erreur chargement enseignants');
                }
            };
            fetchTeachers();
        }
    }, [isAccountant, showIndividualCalculateModal]);

    useEffect(() => {
        const unsubscribe = dataRefresh.subscribe('payroll', refreshAll);
        return unsubscribe;
    }, [refreshAll]);

    const calculatePreview = useCallback((attData) => {
        const hoursWorked = Math.max(0, (attData?.hours || 0) - (attData?.leave_hours || 0));
        const hourlyRate = parseFloat(attData?.hourly_rate) || 0;
        const baseAmount = Math.round(hoursWorked * hourlyRate * 100) / 100;
        const brsAmount = baseAmount >= 25000 ? Math.round(baseAmount * 0.05 * 100) / 100 : 0;
        const netSalary = baseAmount - brsAmount;
        const paymentMethod = netSalary >= 100000 ? 'Chèque' : 'Espèces';

        setCalculationPreview({
            hours: hoursWorked,
            hourlyRate,
            baseAmount: Math.round(baseAmount * 100) / 100,
            brsAmount: Math.round(brsAmount * 100) / 100,
            netSalary: Math.max(0, Math.round(netSalary * 100) / 100),
            paymentMethod
        });
    }, []);

    const loadAttendanceData = useCallback(async (teacherId, month, year) => {
        if (!teacherId) return;
        setLoadingAttendance(true);
        try {
            const monthNum = parseInt(month, 10);
            const yearNum = parseInt(year, 10);

            if (isNaN(monthNum) || monthNum < 1 || monthNum > 12 || isNaN(yearNum)) {
                toast.error('Période invalide');
                setLoadingAttendance(false);
                return;
            }

            const response = await api.get('/payroll/attendance-hours', { params: { teacher_id: teacherId, month: monthNum, year: yearNum } });
            const summary = response.data?.summary || {};
            setAttendanceData(summary);
            calculatePreview(summary);
        } catch (error) {
            setAttendanceData(null);
            setCalculationPreview(null);
            if (error.response?.status === 401) {
                toast.error('Session expirée, veuillez vous reconnecter');
            } else {
                toast.error('Erreur lors du chargement des données');
            }
        } finally {
            setLoadingAttendance(false);
        }
    }, [calculatePreview]);

    const handleCalculateIndividual = useCallback(async (e) => {
        e.preventDefault();
        if (!individualForm.teacher_id) {
            toast.error('Sélectionnez un enseignant');
            return;
        }
        setIsCalculating(true);
        setCalculationProgress('Enregistrement de la paie...');
        try {
            const payload = {
                teacher_id: parseInt(individualForm.teacher_id),
                month: parseInt(individualForm.month),
                year: parseInt(individualForm.year),
            };

            await api.post('/payroll/calculate', payload);
            setCalculationProgress(`✅ Paie calculée avec succès`);
            setTimeout(() => {
                setShowIndividualCalculateModal(false);
                setCalculationProgress('');
                setIndividualForm(formForMonth(selectedMonth));
                setAttendanceData(null);
                setCalculationPreview(null);
                toast.success('Paie calculée');
                refreshAll();
            }, 1500);
        } catch (error) {
            setCalculationProgress('❌ Erreur lors du calcul');
            toast.error(error.response?.data?.message || 'Erreur lors du calcul');
        } finally {
            setIsCalculating(false);
        }
    }, [individualForm, selectedMonth, refreshAll]);

    const handleCalculateMonth = useCallback(async () => {
        if (!selectedMonth) return;
        setIsCalculating(true);
        setCalculationProgress('Préparation du calcul...');
        try {
            const [year, month] = selectedMonth.split('-').map(Number);
            setCalculationProgress('Calcul en cours...');
            const response = await api.post('/payroll/calculate-month', { month, year });
            setCalculationProgress(`✅ ${response.data.calculated} paies calculées avec succès`);
            setTimeout(() => {
                setShowCalculateModal(false);
                setCalculationProgress('');
                toast.success(`${response.data.calculated} paies calculées automatiquement`);
                refreshAll();
            }, 1500);
        } catch (error) {
            setCalculationProgress('❌ Erreur lors du calcul');
            toast.error(error.response?.data?.message || 'Erreur lors du calcul automatique');
        } finally {
            setIsCalculating(false);
        }
    }, [selectedMonth, refreshAll]);

    const handleValidate = useCallback(async (id) => {
        try {
            await api.post(`/payroll/${id}/validate`);
            toast.success('Paie validée');
            await refreshAll();
        } catch (error) {
            toast.error(error?.response?.data?.message || 'Erreur lors de la validation');
        }
    }, [refreshAll]);

    const handleMarkAsPaid = useCallback(async (id) => {
        try {
            await api.post(`/payroll/${id}/mark-as-paid`);
            toast.success('Paie marquée comme payée');
            await refreshAll();
        } catch (error) {
            toast.error(error?.response?.data?.message || 'Erreur lors du marquage comme payée');
        }
    }, [refreshAll]);

    const handleDownloadPDF = useCallback(async (payroll) => {
        try {
            const response = await api.get(`/payroll/${payroll.id}/pdf`, { responseType: 'blob' });
            const url = window.URL.createObjectURL(new Blob([response.data]));
            const link = document.createElement('a');
            link.href = url;
            link.download = `bulletin_paie_${payroll.month}_${payroll.year}.pdf`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            window.URL.revokeObjectURL(url);
            toast.success('Bulletin PDF téléchargé');
        } catch (error) {
            console.error('PDF download error:', error);
            toast.error(error?.response?.data?.message || 'Erreur lors du téléchargement du PDF');
        }
    }, []);

    const handleExport = useCallback(async () => {
        try {
            const [year, month] = selectedMonth.split('-').map(Number);
            const response = await api.get('/payroll/export', { params: { month, year }, responseType: 'blob' });
            const url = window.URL.createObjectURL(new Blob([response.data]));
            const link = document.createElement('a');
            link.href = url;
            link.download = `paies_${month}-${year}.csv`;
            link.click();
            window.URL.revokeObjectURL(url);
            toast.success('Export réussi');
        } catch (error) {
            toast.error(error?.response?.data?.message || 'Erreur lors de l\'export');
        }
    }, [selectedMonth]);

    const AVATAR_GRADIENTS = ['from-indigo-500 to-violet-600', 'from-emerald-500 to-teal-600', 'from-blue-500 to-indigo-600', 'from-amber-500 to-orange-600'];

    const formatFcfa = (value) => `${new Intl.NumberFormat('fr-FR').format(Number(value || 0))} FCFA`;

    const STATUS_STYLES = {
        paid: { label: 'Payée', badge: 'bg-emerald-50 text-emerald-700 ring-emerald-200', dot: 'bg-emerald-500', bar: 'bg-emerald-500' },
        validated: { label: 'Validée', badge: 'bg-blue-50 text-blue-700 ring-blue-200', dot: 'bg-blue-500', bar: 'bg-blue-500' },
        calculated: { label: 'Calculée', badge: 'bg-amber-50 text-amber-700 ring-amber-200', dot: 'bg-amber-500', bar: 'bg-amber-400' },
        draft: { label: 'Brouillon', badge: 'bg-slate-100 text-slate-600 ring-slate-200', dot: 'bg-slate-400', bar: 'bg-slate-300' },
    };

    const renderPayrollCard = (p) => {
        const avatarIdx = p.id % AVATAR_GRADIENTS.length;
        const status = STATUS_STYLES[p.status] || STATUS_STYLES.draft;
        const details = p.calculation_details || {};
        const hours = Number(details.hours_worked || 0);
        const rate = Number(details.hourly_rate || 0);

        return (
            <div key={p.id} className="relative bg-white rounded-2xl border border-slate-100 shadow-sm hover:shadow-md transition-shadow overflow-hidden">
                <div className={`absolute inset-y-0 left-0 w-1 ${status.bar}`} />
                <div className="p-4 pl-5">
                    <div className="flex items-center justify-between gap-3">
                        <div className="flex items-center gap-3 min-w-0">
                            <div className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br ${AVATAR_GRADIENTS[avatarIdx]} text-white font-bold text-sm`}>
                                {p.teacher?.user?.name?.charAt(0)?.toUpperCase()}
                            </div>
                            <div className="min-w-0">
                                <h3 className="font-bold text-slate-900 text-sm truncate">{p.teacher?.user?.name}</h3>
                                <p className="text-[11px] font-mono text-slate-400">{p.teacher?.employee_id || 'Enseignant'}</p>
                            </div>
                        </div>
                        <span className={`inline-flex shrink-0 items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold ring-1 ${status.badge}`}>
                            <span className={`w-1.5 h-1.5 rounded-full ${status.dot}`} />
                            {status.label}
                        </span>
                    </div>

                    <dl className="mt-4 space-y-1.5 text-sm">
                        <div className="flex justify-between">
                            <dt className="text-slate-500">{hours > 0 ? `${hours} h × ${new Intl.NumberFormat('fr-FR').format(rate)} FCFA` : 'Brut'}</dt>
                            <dd className="font-semibold text-slate-900 tabular-nums">{formatFcfa(p.gross_salary || p.honorarium_amount)}</dd>
                        </div>
                        {Number(p.brs_amount || 0) > 0 && (
                            <div className="flex justify-between">
                                <dt className="text-slate-500 flex items-center gap-1"><TrendingDown size={13} className="text-red-400" /> BRS 5 %</dt>
                                <dd className="font-semibold text-red-500 tabular-nums">− {formatFcfa(p.brs_amount)}</dd>
                            </div>
                        )}
                    </dl>

                    <div className="mt-3 pt-3 border-t border-slate-100 flex items-end justify-between">
                        <div>
                            <p className="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Net à payer</p>
                            <p className="text-lg font-black text-slate-900 tabular-nums">{formatFcfa(p.net_salary)}</p>
                        </div>
                        <span className="text-[11px] font-semibold text-slate-500 bg-slate-50 px-2 py-1 rounded-md">
                            {p.payment_method === 'check' ? 'Chèque' : 'Espèces'}
                        </span>
                    </div>

                    {(isAccountant || isTeacher) && (
                        <div className="mt-3 flex items-center gap-2">
                            {isAccountant && p.status === 'calculated' && (
                                <button onClick={() => setShowConfirm({ isOpen: true, action: 'validate', payroll: p })}
                                    className="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg text-xs font-bold bg-blue-50 text-blue-700 hover:bg-blue-600 hover:text-white transition-colors">
                                    <CheckCircle size={14} /> Valider
                                </button>
                            )}
                            {isAccountant && p.status === 'validated' && (
                                <button onClick={() => setShowConfirm({ isOpen: true, action: 'pay', payroll: p })}
                                    className="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg text-xs font-bold bg-emerald-50 text-emerald-700 hover:bg-emerald-600 hover:text-white transition-colors">
                                    <DollarSign size={14} /> Marquer payée
                                </button>
                            )}
                            <button onClick={() => handleDownloadPDF(p)}
                                className={`${p.status === 'paid' ? 'flex-1' : ''} inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg text-xs font-bold text-slate-600 bg-slate-50 hover:bg-slate-200 transition-colors`}>
                                <FileText size={14} /> Bulletin PDF
                            </button>
                        </div>
                    )}
                </div>
            </div>
        );
    };

    const renderMonthHeader = ({ tone, caption, name, yearValue, s }) => {
        const toneClasses = tone === 'current'
            ? { dot: 'bg-emerald-500', text: 'text-emerald-700', ring: 'border-emerald-200' }
            : { dot: 'bg-indigo-500', text: 'text-indigo-700', ring: 'border-indigo-200' };
        return (
            <div className={`bg-white rounded-2xl border ${toneClasses.ring} p-4 shadow-sm`}>
                <div className="flex items-center justify-between gap-3">
                    <div>
                        <p className="flex items-center gap-2 text-[11px] font-bold uppercase tracking-wider text-slate-400">
                            <span className={`w-2 h-2 rounded-full ${toneClasses.dot}`} /> {caption}
                        </p>
                        <p className={`text-xl font-black ${toneClasses.text}`}>{name} {yearValue}</p>
                    </div>
                    <div className="text-right">
                        <p className="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Total net</p>
                        <p className="text-lg font-black text-slate-900 tabular-nums">{formatFcfa(s.totalNet)}</p>
                    </div>
                </div>
                <div className="mt-3 grid grid-cols-4 gap-2 text-center">
                    {[
                        { label: 'Paies', value: s.total, cls: 'bg-slate-50 text-slate-700' },
                        { label: 'Calculées', value: s.calculated, cls: 'bg-amber-50 text-amber-700' },
                        { label: 'Validées', value: s.validated, cls: 'bg-blue-50 text-blue-700' },
                        { label: 'Payées', value: s.paid, cls: 'bg-emerald-50 text-emerald-700' },
                    ].map(item => (
                        <div key={item.label} className={`rounded-xl py-2 ${item.cls}`}>
                            <p className="text-lg font-black leading-none tabular-nums">{item.value}</p>
                            <p className="mt-1 text-[10px] font-bold uppercase tracking-wide opacity-80">{item.label}</p>
                        </div>
                    ))}
                </div>
            </div>
        );
    };

    const today = new Date();
    const actualMonth = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}`;
    const isActualMonth = selectedMonth === actualMonth;
    const year = selectedYear;
    const monthLabel = MONTH_NAMES[selectedMonthNum] || '';
    const previousMonthLabel = MONTH_NAMES[previousMonth.month] || '';
    const changePeriod = (value) => {
        setSelectedMonth(value);
        setPage(1);
        setPreviousPage(1);
    };

    return (
        <div className="min-h-screen">
            <div className="max-w-[1400px] mx-auto space-y-6 px-2 sm:px-4">
                <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 pt-2">
                    <div>
                        <div className="flex items-center gap-3 mb-1">
                            <div className="w-10 h-10 rounded-xl bg-gradient-to-br from-emerald-500 to-teal-600 flex items-center justify-center shadow-lg shadow-emerald-500/25">
                                <DollarSign size={20} className="text-white" />
                            </div>
                            <h1 className="text-2xl font-bold text-slate-900">Gestion des Honoraires</h1>
                        </div>
                        <p className="text-slate-400 text-sm ml-[52px]">Calcul automatique, validation et paiement des honoraires</p>
                    </div>
                    <div className="flex gap-2 flex-wrap">
                        {isAccountant && (
                            <button onClick={() => { setIndividualForm(formForMonth(selectedMonth)); setAttendanceData(null); setCalculationPreview(null); setShowIndividualCalculateModal(true); }} disabled={isCalculating}
                                className="flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-emerald-600 to-teal-700 text-white rounded-xl font-semibold text-sm shadow-lg shadow-emerald-500/25 hover:shadow-emerald-500/40 transition-all duration-300 active:scale-[0.97] disabled:opacity-50 hover:scale-105">
                                <Zap size={20} />
                                <div className="flex flex-col items-start">
                                    <span className="font-bold">Calculer paie</span>
                                    <span className="text-xs opacity-90">{monthLabel} {year}</span>
                                </div>
                            </button>
                        )}
                        {isAccountant && (
                            <button onClick={() => { setCalculationProgress(''); setShowCalculateModal(true); }} disabled={isCalculating}
                                className="flex items-center gap-2 px-5 py-2.5 bg-white border border-emerald-200 text-emerald-700 rounded-xl font-semibold text-sm shadow-sm hover:bg-emerald-50 transition-all duration-300 active:scale-[0.97] disabled:opacity-50">
                                <Play size={18} />
                                <span>Tout le mois</span>
                            </button>
                        )}
                        {isAccountant && (
                            <button onClick={handleExport}
                                className="flex items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-indigo-600 to-blue-700 text-white rounded-xl font-semibold text-sm shadow-lg shadow-indigo-500/25 hover:shadow-indigo-500/40 transition-all duration-300 active:scale-[0.97]">
                                <Download size={18} />
                                <span>Exporter</span>
                            </button>
                        )}
                    </div>
                </div>

                {/* Period bar */}
                <div className="bg-white rounded-2xl border border-slate-100 p-4 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div className="flex flex-wrap items-center gap-3">
                        <Calendar size={20} className="text-slate-400" />
                        <div className="flex items-center gap-2 px-3 py-1.5 rounded-xl bg-emerald-50">
                            <span className="text-xs font-semibold text-emerald-700">{isActualMonth ? 'Mois actuel' : 'Mois affiché'}</span>
                            <span className="text-sm font-black text-emerald-700">{monthLabel} {year}</span>
                        </div>
                        <ChevronRight size={16} className="text-slate-300 rotate-180" />
                        <div className="flex items-center gap-2 px-3 py-1.5 rounded-xl bg-indigo-50">
                            <span className="text-xs font-semibold text-indigo-700">Mois précédent</span>
                            <span className="text-sm font-black text-indigo-700">{previousMonthLabel} {previousMonth.year}</span>
                        </div>
                    </div>
                    {isAccountant && (
                        <div className="flex items-center gap-2">
                            {!isActualMonth && (
                                <button onClick={() => changePeriod(actualMonth)}
                                    className="px-3 py-2 rounded-xl text-xs font-bold text-emerald-700 bg-emerald-50 hover:bg-emerald-100 transition-colors">
                                    Revenir au mois actuel
                                </button>
                            )}
                            <label htmlFor="payroll-period" className="text-xs font-semibold text-slate-500">Période</label>
                            <input id="payroll-period" type="month" value={selectedMonth} onChange={(e) => { if (e.target.value) changePeriod(e.target.value); }}
                                className="px-3 py-2 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500" />
                        </div>
                    )}
                </div>

                {/* Current month (left) and previous month (right) */}
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">
                    <section className="space-y-4">
                        {renderMonthHeader({ tone: 'current', caption: isActualMonth ? 'Mois en cours' : 'Mois affiché', name: monthLabel, yearValue: year, s: stats })}

                        {loading ? (
                            <div className="flex justify-center py-12">
                                <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-emerald-600" />
                            </div>
                        ) : payrolls.length === 0 ? (
                            <div className="flex flex-col items-center justify-center py-12 px-6 text-center bg-white/70 rounded-2xl border border-dashed border-slate-200">
                                <CreditCard size={36} className="text-slate-300 mb-3" />
                                <p className="text-sm font-bold text-slate-600 mb-1">Aucune paie pour {monthLabel} {year}</p>
                                <p className="text-xs text-slate-400 mb-4">{isAccountant ? 'Les enseignants qui ont pointé ce mois peuvent être calculés.' : 'Pas de paie disponible.'}</p>
                                {isAccountant && (
                                    <button onClick={() => { setIndividualForm(formForMonth(selectedMonth)); setAttendanceData(null); setCalculationPreview(null); setShowIndividualCalculateModal(true); }}
                                        className="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 transition-colors">
                                        <Zap size={14} /> Calculer une paie
                                    </button>
                                )}
                            </div>
                        ) : (
                            <div className="grid grid-cols-1 2xl:grid-cols-2 gap-4">
                                {payrolls.map(renderPayrollCard)}
                            </div>
                        )}

                        <Pagination page={pagination.current_page} totalPages={pagination.last_page} onPageChange={setPage} totalItems={pagination.total} perPage={perPage} />
                    </section>

                    <section className="space-y-4">
                        {renderMonthHeader({ tone: 'previous', caption: 'Mois précédent', name: previousMonthLabel, yearValue: previousMonth.year, s: previousStats })}

                        {loadingPrevious ? (
                            <div className="flex justify-center py-12">
                                <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600" />
                            </div>
                        ) : previousPayrolls.length === 0 ? (
                            <div className="flex flex-col items-center justify-center py-12 px-6 text-center bg-white/70 rounded-2xl border border-dashed border-slate-200">
                                <CreditCard size={36} className="text-slate-300 mb-3" />
                                <p className="text-sm font-bold text-slate-600">Aucune paie pour {previousMonthLabel} {previousMonth.year}</p>
                            </div>
                        ) : (
                            <div className="grid grid-cols-1 2xl:grid-cols-2 gap-4">
                                {previousPayrolls.map(renderPayrollCard)}
                            </div>
                        )}

                        <Pagination page={previousPagination.current_page} totalPages={previousPagination.last_page} onPageChange={setPreviousPage} totalItems={previousPagination.total} perPage={perPage} />
                    </section>
                </div>

                {/* Calculate Month Modal */}
                {showCalculateModal && (
                    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
                        <div className="bg-white rounded-2xl shadow-2xl max-w-md w-full p-6 border border-slate-200">
                            <div className="flex items-center gap-3 mb-4">
                                <div className="w-12 h-12 rounded-xl bg-emerald-100 flex items-center justify-center">
                                    <Play className="text-emerald-600" size={24} />
                                </div>
                                <div>
                                    <h2 className="text-xl font-bold text-slate-900">Calculer les paies</h2>
                                    <p className="text-sm text-slate-500">{monthLabel} {year}</p>
                                </div>
                            </div>

                            <div className="bg-emerald-50 border border-emerald-200 rounded-xl p-4 mb-6 space-y-3">
                                <div className="flex items-start gap-3">
                                    <AlertCircle className="text-emerald-600 flex-shrink-0 mt-1" size={20} />
                                    <div>
                                        <p className="font-semibold text-emerald-900">Calcul automatique</p>
                                        <p className="text-sm text-emerald-700 mt-1">Le système va automatiquement:</p>
                                        <ul className="text-sm text-emerald-700 mt-2 space-y-1 ml-4">
                                            <li>✓ Récupérer les heures pointées</li>
                                            <li>✓ Déduire les congés approuvés</li>
                                            <li>✓ Calculer le brut (heures × taux)</li>
                                            <li>✓ Appliquer la BRS si nécessaire</li>
                                            <li>✓ Déterminer le mode de paiement</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>

                            {!isCalculating && (
                                <div className="bg-slate-50 rounded-xl p-4 mb-6">
                                    <p className="text-sm font-semibold text-slate-700 mb-2">Paies déjà présentes pour {monthLabel} {year} :</p>
                                    <div className="space-y-2 text-sm">
                                        <div className="flex justify-between">
                                            <span className="text-slate-600">Calculées (seront recalculées)</span>
                                            <span className="font-bold text-amber-600">{stats.calculated}</span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span className="text-slate-600">Validées ou payées (inchangées)</span>
                                            <span className="font-bold text-emerald-600">{stats.validated + stats.paid}</span>
                                        </div>
                                    </div>
                                    <p className="mt-3 text-xs text-slate-500">Seuls les enseignants actifs ayant pointé ce mois et ayant un taux horaire reçoivent une paie.</p>
                                </div>
                            )}

                            {isCalculating && (
                                <div className="bg-blue-50 rounded-xl p-4 mb-6 text-center">
                                    <div className="inline-block mb-3">
                                        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                                    </div>
                                    <p className="text-sm font-semibold text-blue-900">{calculationProgress || 'Calcul en cours...'}</p>
                                </div>
                            )}

                            <div className="flex gap-3">
                                <button onClick={() => { setShowCalculateModal(false); setCalculationProgress(''); }} disabled={isCalculating}
                                    className="flex-1 px-4 py-2.5 border border-slate-300 text-slate-700 rounded-xl font-semibold hover:bg-slate-50 transition-all disabled:opacity-50">
                                    Annuler
                                </button>
                                <button onClick={handleCalculateMonth} disabled={isCalculating}
                                    className="flex-1 flex items-center justify-center gap-2 px-4 py-2.5 bg-gradient-to-r from-emerald-600 to-teal-700 text-white rounded-xl font-semibold hover:shadow-lg transition-all disabled:opacity-50">
                                    <Zap size={18} />
                                    <span>{isCalculating ? 'Calcul...' : 'Calculer'}</span>
                                </button>
                            </div>
                        </div>
                    </div>
                )}

                {/* Individual Calculate Modal - Automated */}
                {showIndividualCalculateModal && (
                    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
                        <div className="bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto p-6 border border-slate-200">
                            <div className="flex items-center gap-3 mb-6">
                                <div className="w-12 h-12 rounded-xl bg-emerald-100 flex items-center justify-center">
                                    <Zap className="text-emerald-600" size={24} />
                                </div>
                                <div>
                                    <h2 className="text-xl font-bold text-slate-900">Calculer une paie</h2>
                                    <p className="text-sm text-slate-500">Sélection → Calcul automatique → Validation</p>
                                </div>
                            </div>

                            {!isCalculating && (
                                <form onSubmit={handleCalculateIndividual} className="space-y-5">
                                    {/* Enseignant & Période */}
                                    <div className="grid grid-cols-3 gap-4">
                                        <div className="col-span-1">
                                            <label className="form-label">Enseignant*</label>
                                            <select required className="input-field" value={individualForm.teacher_id}
                                                onChange={(e) => {
                                                    setIndividualForm({...individualForm, teacher_id: e.target.value});
                                                    if (e.target.value) {
                                                        loadAttendanceData(e.target.value, individualForm.month, individualForm.year);
                                                    } else {
                                                        setAttendanceData(null);
                                                        setCalculationPreview(null);
                                                    }
                                                }}>
                                                <option value="">-- Sélectionner --</option>
                                                {teachers.map(t => (
                                                    <option key={t.id} value={t.id}>{t.user?.name}</option>
                                                ))}
                                            </select>
                                        </div>
                                        <div>
                                            <label className="form-label">Mois*</label>
                                            <select required className="input-field text-sm" value={individualForm.month}
                                                onChange={(e) => {
                                                    setIndividualForm({...individualForm, month: e.target.value});
                                                    if (individualForm.teacher_id) loadAttendanceData(individualForm.teacher_id, e.target.value, individualForm.year);
                                                }}>
                                                {[1,2,3,4,5,6,7,8,9,10,11,12].map(m => (
                                                    <option key={m} value={m}>{MONTH_NAMES[m]}</option>
                                                ))}
                                            </select>
                                        </div>
                                        <div>
                                            <label className="form-label">Année*</label>
                                            <select required className="input-field text-sm" value={individualForm.year}
                                                onChange={(e) => {
                                                    setIndividualForm({...individualForm, year: e.target.value});
                                                    if (individualForm.teacher_id) loadAttendanceData(individualForm.teacher_id, individualForm.month, e.target.value);
                                                }}>
                                                {[2024, 2025, 2026, 2027].map(y => (
                                                    <option key={y} value={y}>{y}</option>
                                                ))}
                                            </select>
                                        </div>
                                    </div>

                                    {/* Données chargées */}
                                    {loadingAttendance && (
                                        <div className="p-4 bg-blue-50 rounded-xl text-center">
                                            <div className="inline-block mb-2">
                                                <div className="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-600"></div>
                                            </div>
                                            <p className="text-sm text-blue-900">Chargement données...</p>
                                        </div>
                                    )}

                                    {attendanceData && !loadingAttendance && (
                                        <div className="space-y-4">
                                            {/* Heures pointées */}
                                            <div className="p-4 bg-slate-50 rounded-xl border border-slate-200">
                                                <div className="grid grid-cols-3 gap-4">
                                                    <div>
                                                        <p className="text-xs text-slate-500 font-semibold">Heures pointées</p>
                                                        <p className="text-2xl font-bold text-slate-900">{attendanceData.hours || 0} h</p>
                                                    </div>
                                                    <div>
                                                        <p className="text-xs text-slate-500 font-semibold">Pointages incomplets</p>
                                                        <p className="text-lg font-bold text-slate-600">{attendanceData.incomplete || 0}</p>
                                                    </div>
                                                    <div>
                                                        <p className="text-xs text-slate-500 font-semibold">Congés approuvés</p>
                                                        <p className="text-lg font-bold text-amber-600">{attendanceData.leave_days || 0} jour(s)</p>
                                                    </div>
                                                </div>
                                            </div>

                                            {/* Prévisualisation du calcul */}
                                            {calculationPreview && (
                                                <div className="p-4 bg-emerald-50 rounded-xl border border-emerald-200">
                                                    <p className="text-xs font-bold text-emerald-900 mb-3">📊 PRÉVISUALISATION DU CALCUL</p>
                                                    <div className="space-y-2 text-sm">
                                                        <div className="flex justify-between">
                                                            <span className="text-slate-600">{calculationPreview.hours || 0} h × {new Intl.NumberFormat('fr-FR').format(calculationPreview.hourlyRate || 0)} FCFA</span>
                                                            <span className="font-bold text-slate-900">{new Intl.NumberFormat('fr-FR').format(calculationPreview.baseAmount || 0)} FCFA</span>
                                                        </div>
                                                        {(calculationPreview.brsAmount || 0) > 0 && (
                                                            <div className="flex justify-between text-red-600">
                                                                <span>BRS (5%)</span>
                                                                <span className="font-bold">- {new Intl.NumberFormat('fr-FR').format(calculationPreview.brsAmount || 0)} FCFA</span>
                                                            </div>
                                                        )}
                                                        <div className="border-t border-emerald-300 pt-2 flex justify-between font-bold text-lg">
                                                            <span className="text-emerald-900">NET À PAYER</span>
                                                            <span className="text-emerald-600">{new Intl.NumberFormat('fr-FR').format(calculationPreview.netSalary || 0)} FCFA</span>
                                                        </div>
                                                        <div className="flex justify-between text-xs text-slate-500 mt-2">
                                                            <span>Mode de paiement</span>
                                                            <span className="font-bold text-slate-700">{calculationPreview.paymentMethod}</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            )}
                                        </div>
                                    )}

                                    <div className="flex gap-3 pt-4">
                                        <button type="button" onClick={() => {
                                            setShowIndividualCalculateModal(false);
                                            setAttendanceData(null);
                                            setCalculationPreview(null);
                                        }} disabled={isCalculating}
                                            className="flex-1 px-4 py-2.5 border border-slate-300 text-slate-700 rounded-xl font-semibold hover:bg-slate-50 transition-all disabled:opacity-50">
                                            Annuler
                                        </button>
                                        <button type="submit" disabled={isCalculating || !attendanceData}
                                            className="flex-1 flex items-center justify-center gap-2 px-4 py-2.5 bg-gradient-to-r from-emerald-600 to-teal-700 text-white rounded-xl font-semibold hover:shadow-lg transition-all disabled:opacity-50">
                                            <Zap size={18} />
                                            <span>{isCalculating ? 'Calcul...' : 'Calculer & Enregistrer'}</span>
                                        </button>
                                    </div>
                                </form>
                            )}

                            {isCalculating && (
                                <div className="bg-emerald-50 rounded-xl p-6 text-center">
                                    <div className="inline-block mb-4">
                                        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-emerald-600"></div>
                                    </div>
                                    <p className="text-sm font-semibold text-emerald-900">{calculationProgress || 'Calcul en cours...'}</p>
                                </div>
                            )}
                        </div>
                    </div>
                )}


                {/* Confirm Modal */}
                <ConfirmModal isOpen={showConfirm.isOpen} onClose={() => setShowConfirm({ ...showConfirm, isOpen: false })}
                    onConfirm={() => {
                        if (showConfirm.action === 'validate') handleValidate(showConfirm.payroll.id);
                        else if (showConfirm.action === 'pay') handleMarkAsPaid(showConfirm.payroll.id);
                        setShowConfirm({ ...showConfirm, isOpen: false });
                    }}
                    title={showConfirm.action === 'validate' ? "Valider la paie" : "Marquer comme payée"}
                    message={showConfirm.action === 'validate' ?
                        `Valider l'honoraire de ${showConfirm.payroll?.teacher?.user?.name} (${showConfirm.payroll?.month}/${showConfirm.payroll?.year})?` :
                        `Confirmer le paiement de ${showConfirm.payroll?.teacher?.user?.name} (${showConfirm.payroll?.net_salary} FCFA)?`
                    }
                    confirmText="Confirmer"
                    type="warning"
                />
            </div>
        </div>
    );
};

export default Payroll;

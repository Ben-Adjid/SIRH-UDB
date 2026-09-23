import { useEffect, useState, useCallback } from 'react';
import api from '../services/api';
import { dataRefresh } from '../services/dataRefresh';
import useAuthStore from '../store/authStore';
import { CreditCard, Download, DollarSign, FileText, CheckCircle, AlertCircle, Zap, TrendingUp, TrendingDown, Users, Calendar, Eye, Play, ChevronRight } from 'lucide-react';
import toast from '../utils/toast';
import ConfirmModal from '../components/ConfirmModal';
import Pagination from '../components/Pagination';
import { getUserRoles } from '../utils/roles';
import { escapeHtml } from '../utils/escape';
import { MONTH_NAMES } from '../utils/constants';

const Payroll = () => {
    const user = useAuthStore((state) => state.user);
    const [payrolls, setPayrolls] = useState([]);
    const [loading, setLoading] = useState(true);
    const [page, setPage] = useState(1);
    const perPage = 15;
    const [pagination, setPagination] = useState({ current_page: 1, last_page: 1, total: 0 });
    const [selectedPayroll, setSelectedPayroll] = useState(null);
    const [selectedMonth, setSelectedMonth] = useState(new Date().toISOString().substring(0, 7));
    const [isCalculating, setIsCalculating] = useState(false);
    const [stats, setStats] = useState({ total: 0, calculated: 0, validated: 0, paid: 0, totalAmount: 0 });
    const [showConfirm, setShowConfirm] = useState({ isOpen: false, action: null, payroll: null });
    const [showCalculateModal, setShowCalculateModal] = useState(false);
    const [calculationProgress, setCalculationProgress] = useState('');
    const [showIndividualCalculateModal, setShowIndividualCalculateModal] = useState(false);
    const [teachers, setTeachers] = useState([]);
    const [individualForm, setIndividualForm] = useState({ teacher_id: '', month: selectedMonth.split('-')[1], year: selectedMonth.split('-')[0] });
    const [attendanceData, setAttendanceData] = useState(null);
    const [leaveData, setLeaveData] = useState(null);
    const [calculationPreview, setCalculationPreview] = useState(null);
    const [loadingAttendance, setLoadingAttendance] = useState(false);

    const userRoles = getUserRoles(user);
    const isAdmin = userRoles.includes('admin');
    const isAccountant = userRoles.includes('accountant') || isAdmin;
    const isTeacher = userRoles.includes('teacher') && !isAccountant;
    const currentTeacherId = user?.teacher?.id;

    const fetchPayrolls = useCallback(async (pageNum = 1) => {
        setLoading(true);
        try {
            let endpoint = '/payroll';
            const params = { page: pageNum, per_page: perPage, month: selectedMonth.split('-')[1], year: selectedMonth.split('-')[0] };
            if (isTeacher && currentTeacherId) params.teacher_id = currentTeacherId;
            const response = await api.get(endpoint, { params });
            const data = response.data;
            setPayrolls(data.data || []);
            setPagination({ current_page: data.current_page, last_page: data.last_page, total: data.total });

            const calculated = data.data?.filter(p => p.status === 'calculated').length || 0;
            const validated = data.data?.filter(p => p.status === 'validated').length || 0;
            const paid = data.data?.filter(p => p.status === 'paid').length || 0;
            const totalAmount = data.data?.reduce((sum, p) => sum + (p.net_salary || 0), 0) || 0;
            setStats({ total: data.total || 0, calculated, validated, paid, totalAmount });
        } catch {
            toast.error('Erreur chargement paies');
        } finally {
            setLoading(false);
        }
    }, [isTeacher, currentTeacherId, selectedMonth]);

    useEffect(() => {
        fetchPayrolls(page);
    }, [page, selectedMonth, fetchPayrolls]);

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
        const unsubscribe = dataRefresh.subscribe('payroll', () => fetchPayrolls(page));
        return unsubscribe;
    }, [page, fetchPayrolls]);

    const calculatePreview = useCallback((teacherId, month, year, attData) => {
        const selectedTeacher = teachers.find(t => t.id == teacherId);
        if (!selectedTeacher) return;

        const hoursWorked = attData?.hours || 0;
        const baseAmount = hoursWorked * (selectedTeacher.hourly_rate || 0);
        const brsAmount = baseAmount >= 25000 ? baseAmount * 0.05 : 0;
        const netSalary = baseAmount - brsAmount;
        const paymentMethod = netSalary >= 100000 ? 'Chèque' : 'Espèces';

        setCalculationPreview({
            hours: hoursWorked,
            baseAmount,
            brsAmount,
            netSalary: Math.max(0, netSalary),
            paymentMethod
        });
    }, [teachers]);

    const loadAttendanceData = useCallback(async (teacherId, month, year) => {
        if (!teacherId) return;
        setLoadingAttendance(true);
        try {
            const [attendResp, leaveResp] = await Promise.all([
                api.get('/payroll/attendance-hours', { params: { teacher_id: teacherId, month: parseInt(month), year: parseInt(year) } }),
                api.get('/leaves', { params: { teacher_id: teacherId, month: parseInt(month), year: parseInt(year), status: 'approved' } })
            ]);
            setAttendanceData(attendResp.data);
            setLeaveData(leaveResp.data?.data || []);
            calculatePreview(teacherId, parseInt(month), parseInt(year), attendResp.data);
        } catch (error) {
            if (error.response?.status === 401) {
                toast.error('Session expirée, veuillez vous reconnecter');
            } else if (error.response?.status === 404) {
                toast.error('Données non trouvées pour ce mois');
                setAttendanceData({ hours: 0 });
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

            const response = await api.post('/payroll/calculate', payload);
            setCalculationProgress(`✅ Paie calculée avec succès`);
            setTimeout(() => {
                setShowIndividualCalculateModal(false);
                setCalculationProgress('');
                setIndividualForm({ teacher_id: '', month: selectedMonth.split('-')[1], year: selectedMonth.split('-')[0] });
                setAttendanceData(null);
                setLeaveData(null);
                setCalculationPreview(null);
                toast.success('Paie calculée');
                fetchPayrolls(1);
            }, 1500);
        } catch (error) {
            setCalculationProgress('❌ Erreur lors du calcul');
            toast.error(error.response?.data?.message || 'Erreur lors du calcul');
        } finally {
            setIsCalculating(false);
        }
    }, [individualForm, selectedMonth, fetchPayrolls]);

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
                fetchPayrolls(1);
            }, 1500);
        } catch (error) {
            setCalculationProgress('❌ Erreur lors du calcul');
            toast.error(error.response?.data?.message || 'Erreur lors du calcul automatique');
        } finally {
            setIsCalculating(false);
        }
    }, [selectedMonth, fetchPayrolls]);

    const handleValidate = useCallback(async (id) => {
        try {
            await api.post(`/payroll/${id}/validate`);
            toast.success('Paie validée');
            await fetchPayrolls(page);
        } catch (error) {
            toast.error(error?.response?.data?.message || 'Erreur lors de la validation');
        }
    }, [page, fetchPayrolls]);

    const handleMarkAsPaid = useCallback(async (id) => {
        try {
            await api.post(`/payroll/${id}/mark-as-paid`);
            toast.success('Paie marquée comme payée');
            await fetchPayrolls(page);
        } catch (error) {
            toast.error(error?.response?.data?.message || 'Erreur lors du marquage comme payée');
        }
    }, [page, fetchPayrolls]);

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

    const [year, month] = selectedMonth.split('-').map(Number);
    const monthLabel = MONTH_NAMES[month - 1] || '';

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
                            <button onClick={() => setShowIndividualCalculateModal(true)} disabled={isCalculating}
                                className="flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-emerald-600 to-teal-700 text-white rounded-xl font-semibold text-sm shadow-lg shadow-emerald-500/25 hover:shadow-emerald-500/40 transition-all duration-300 active:scale-[0.97] disabled:opacity-50 hover:scale-105">
                                <Zap size={20} />
                                <div className="flex flex-col items-start">
                                    <span className="font-bold">Calculer paie</span>
                                    <span className="text-xs opacity-90">{monthLabel} {year}</span>
                                </div>
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

                {/* Stats Dashboard */}
                <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div className="bg-white rounded-2xl border border-slate-100 p-4 shadow-sm">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-xs text-slate-500 font-semibold">Total</p>
                                <p className="text-2xl font-black text-slate-900">{stats.total}</p>
                            </div>
                            <Users className="text-slate-300" size={28} />
                        </div>
                    </div>
                    <div className="bg-amber-50 rounded-2xl border border-amber-200 p-4 shadow-sm">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-xs text-amber-600 font-semibold">Calculées</p>
                                <p className="text-2xl font-black text-amber-600">{stats.calculated}</p>
                            </div>
                            <AlertCircle className="text-amber-300" size={28} />
                        </div>
                    </div>
                    <div className="bg-blue-50 rounded-2xl border border-blue-200 p-4 shadow-sm">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-xs text-blue-600 font-semibold">Validées</p>
                                <p className="text-2xl font-black text-blue-600">{stats.validated}</p>
                            </div>
                            <CheckCircle className="text-blue-300" size={28} />
                        </div>
                    </div>
                    <div className="bg-emerald-50 rounded-2xl border border-emerald-200 p-4 shadow-sm">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-xs text-emerald-600 font-semibold">Payées</p>
                                <p className="text-2xl font-black text-emerald-600">{stats.paid}</p>
                            </div>
                            <TrendingUp className="text-emerald-300" size={28} />
                        </div>
                    </div>
                </div>

                {/* Month Selector */}
                {isAccountant && (
                    <div className="bg-white rounded-2xl border border-slate-100 p-4 shadow-sm flex items-center justify-between">
                        <div className="flex items-center gap-3">
                            <Calendar size={20} className="text-slate-400" />
                            <label className="font-semibold text-slate-700">Mois actuel:</label>
                            <span className="text-lg font-bold text-emerald-600">{monthLabel} {year}</span>
                        </div>
                        <input type="month" value={selectedMonth} onChange={(e) => { setSelectedMonth(e.target.value); setPage(1); }}
                            className="px-4 py-2.5 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500" />
                    </div>
                )}

                {/* Payrolls List */}
                {loading ? (
                    <div className="flex justify-center py-20">
                        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-emerald-600" />
                    </div>
                ) : payrolls.length === 0 ? (
                    <div className="flex flex-col items-center justify-center py-24 text-center">
                        <CreditCard size={48} className="text-slate-200 mb-4" />
                        <p className="text-lg font-bold text-slate-400 mb-2">Aucune paie</p>
                        <p className="text-sm text-slate-300">{isAccountant ? 'Cliquez sur "Calculer paies" pour générer les paies du mois' : 'Pas de paie disponible'}</p>
                    </div>
                ) : (
                    <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                        {payrolls.map((p) => {
                            const avatarIdx = p.id % AVATAR_GRADIENTS.length;
                            const isPaid = p.status === 'paid';
                            const isValidated = p.status === 'validated';

                            return (
                                <div key={p.id} className="group relative bg-white rounded-2xl p-5 border border-slate-100 shadow-sm hover:shadow-lg hover:border-emerald-200 transition-all duration-300">
                                    <div className="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-emerald-400 to-teal-500 opacity-0 group-hover:opacity-100 transition-opacity rounded-t-2xl" />
                                    <div className="flex items-start justify-between mb-4">
                                        <div className="flex items-center gap-3">
                                            <div className={`flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br ${AVATAR_GRADIENTS[avatarIdx]} text-white font-bold text-sm shadow-md`}>
                                                {p.teacher?.user?.name?.charAt(0)?.toUpperCase()}
                                            </div>
                                            <div className="min-w-0">
                                                <h3 className="font-bold text-slate-900 text-sm truncate">{p.teacher?.user?.name}</h3>
                                                <p className="text-[10px] font-mono font-semibold text-slate-400">{p.teacher?.employee_id || 'ENSEIGNANT'}</p>
                                            </div>
                                        </div>
                                        <span className={`inline-flex items-center px-2.5 py-1 rounded-lg text-[10px] font-black uppercase tracking-wider border ${
                                            isPaid ? 'bg-emerald-50 text-emerald-600 border-emerald-100' :
                                            isValidated ? 'bg-blue-50 text-blue-600 border-blue-100' :
                                            'bg-amber-50 text-amber-600 border-amber-100'
                                        }`}>
                                            <span className={`w-1.5 h-1.5 rounded-full mr-1.5 ${isPaid ? 'bg-emerald-500' : isValidated ? 'bg-blue-500' : 'bg-amber-500 animate-pulse'}`}></span>
                                            {isPaid ? 'Payée' : isValidated ? 'Validée' : 'Calculée'}
                                        </span>
                                    </div>

                                    <div className="space-y-2 mb-4">
                                        <div className="flex items-center justify-between">
                                            <span className="text-xs text-slate-500">Brut</span>
                                            <span className="text-sm font-bold text-slate-900">{new Intl.NumberFormat('fr-FR').format(p.gross_salary || p.honorarium_amount || 0)} FCFA</span>
                                        </div>
                                        {(p.brs_amount || 0) > 0 && (
                                            <div className="flex items-center justify-between">
                                                <span className="text-xs text-red-400 flex items-center gap-1"><TrendingDown size={12} /> BRS (5%)</span>
                                                <span className="text-sm font-bold text-red-500">-{new Intl.NumberFormat('fr-FR').format(p.brs_amount || 0)}</span>
                                            </div>
                                        )}
                                        <div className="flex items-center justify-between pt-2 border-t border-slate-100">
                                            <span className="text-xs font-bold text-slate-600">Net</span>
                                            <span className="text-lg font-black text-emerald-600">{new Intl.NumberFormat('fr-FR').format(p.net_salary || 0)} FCFA</span>
                                        </div>
                                    </div>

                                    {(isAccountant || isTeacher) && (
                                        <div className="flex items-center gap-1.5 pt-3 border-t border-slate-100">
                                            {isAccountant && p.status === 'calculated' && (
                                                <>
                                                    <button onClick={() => setShowConfirm({ isOpen: true, action: 'validate', payroll: p })}
                                                        className="px-3 py-2 bg-blue-50 text-blue-600 rounded-xl hover:bg-blue-500 hover:text-white transition-all duration-200" title="Valider">
                                                        <CheckCircle size={12} />
                                                    </button>
                                                </>
                                            )}
                                            {isAccountant && p.status === 'validated' && (
                                                <button onClick={() => setShowConfirm({ isOpen: true, action: 'pay', payroll: p })}
                                                    className="flex-1 flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl text-[11px] font-bold bg-emerald-50 text-emerald-600 hover:bg-emerald-500 hover:text-white transition-all duration-200">
                                                    <DollarSign size={12} /> Marquer payée
                                                </button>
                                            )}
                                            <button onClick={() => handleDownloadPDF(p)}
                                                className="px-3 py-2 text-slate-300 hover:text-blue-500 hover:bg-blue-50 rounded-xl transition-all duration-200" title="PDF">
                                                <Download size={14} />
                                            </button>
                                        </div>
                                    )}
                                </div>
                            );
                        })}
                    </div>
                )}

                <Pagination page={pagination.current_page} totalPages={pagination.last_page} onPageChange={setPage} totalItems={pagination.total} perPage={perPage} />

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
                                    <p className="text-sm font-semibold text-slate-700 mb-2">Résumé:</p>
                                    <div className="space-y-2 text-sm">
                                        <div className="flex justify-between">
                                            <span className="text-slate-600">Enseignants actifs:</span>
                                            <span className="font-bold text-slate-900">{stats.total}</span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span className="text-slate-600">Déjà calculées:</span>
                                            <span className="font-bold text-amber-600">{stats.calculated}</span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span className="text-slate-600">À calculer:</span>
                                            <span className="font-bold text-emerald-600">{stats.total - stats.calculated}</span>
                                        </div>
                                    </div>
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
                                                    <option key={m} value={m}>{MONTH_NAMES[m-1]}</option>
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
                                                        <p className="text-2xl font-bold text-slate-900">{attendanceData.hours}h</p>
                                                    </div>
                                                    {leaveData.length > 0 && (
                                                        <div>
                                                            <p className="text-xs text-slate-500 font-semibold">Absences approuvées</p>
                                                            <p className="text-lg font-bold text-amber-600">{leaveData.length} jour(s)</p>
                                                        </div>
                                                    )}
                                                </div>
                                            </div>

                                            {/* Prévisualisation du calcul */}
                                            {calculationPreview && (
                                                <div className="p-4 bg-emerald-50 rounded-xl border border-emerald-200">
                                                    <p className="text-xs font-bold text-emerald-900 mb-3">📊 PRÉVISUALISATION DU CALCUL</p>
                                                    <div className="space-y-2 text-sm">
                                                        <div className="flex justify-between">
                                                            <span className="text-slate-600">{calculationPreview.hours || 0}h × taux horaire</span>
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

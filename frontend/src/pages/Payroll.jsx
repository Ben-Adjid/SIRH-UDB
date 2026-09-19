import { useEffect, useState, useCallback } from 'react';
import api from '../services/api';
import useAuthStore from '../store/authStore';
import { CreditCard, Download, DollarSign, Percent, FileText, CheckCircle, AlertCircle, Zap, Plus, TrendingUp, TrendingDown, Users, Calendar, Eye, Play, ChevronRight } from 'lucide-react';
import toast from '../utils/toast';
import ConfirmModal from '../components/ConfirmModal';
import FormModal from '../components/FormModal';
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
    const [showBonusModal, setShowBonusModal] = useState(false);
    const [showDeductionModal, setShowDeductionModal] = useState(false);
    const [bonusForm, setBonusForm] = useState({ description: '', amount: 0 });
    const [deductionForm, setDeductionForm] = useState({ description: '', amount: 0 });
    const [selectedMonth, setSelectedMonth] = useState(new Date().toISOString().substring(0, 7));
    const [isCalculating, setIsCalculating] = useState(false);
    const [stats, setStats] = useState({ total: 0, calculated: 0, validated: 0, paid: 0, totalAmount: 0 });
    const [showConfirm, setShowConfirm] = useState({ isOpen: false, action: null, payroll: null });
    const [showCalculateModal, setShowCalculateModal] = useState(false);
    const [calculationProgress, setCalculationProgress] = useState('');
    const [showIndividualCalculateModal, setShowIndividualCalculateModal] = useState(false);
    const [teachers, setTeachers] = useState([]);
    const [individualForm, setIndividualForm] = useState({ teacher_id: '', month: selectedMonth.split('-')[1], year: selectedMonth.split('-')[0], hours_worked: '', bonus: '', deduction: '' });

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

    const handleCalculateIndividual = useCallback(async (e) => {
        e.preventDefault();
        if (!individualForm.teacher_id) {
            toast.error('Sélectionnez un enseignant');
            return;
        }
        setIsCalculating(true);
        setCalculationProgress('Calcul en cours...');
        try {
            const payload = {
                teacher_id: parseInt(individualForm.teacher_id),
                month: parseInt(individualForm.month),
                year: parseInt(individualForm.year),
            };
            if (individualForm.hours_worked) payload.hours_worked = parseFloat(individualForm.hours_worked);
            if (individualForm.bonus) payload.bonus = parseFloat(individualForm.bonus);
            if (individualForm.deduction) payload.deduction = parseFloat(individualForm.deduction);

            const response = await api.post('/payroll/calculate', payload);
            setCalculationProgress(`✅ Paie calculée avec succès`);
            setTimeout(() => {
                setShowIndividualCalculateModal(false);
                setCalculationProgress('');
                setIndividualForm({ teacher_id: '', month: selectedMonth.split('-')[1], year: selectedMonth.split('-')[0], hours_worked: '', bonus: '', deduction: '' });
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

    const handleAddBonus = useCallback(async (e) => {
        e.preventDefault();
        if (!bonusForm.description.trim()) { toast.error('Description requise'); return; }
        if (!bonusForm.amount || bonusForm.amount <= 0) { toast.error('Montant doit être > 0'); return; }
        try {
            await api.post(`/payroll/${selectedPayroll.id}/line-item`, {
                description: bonusForm.description.trim(), amount: bonusForm.amount, type: 'earning'
            });
            toast.success('Prime ajoutée');
            setBonusForm({ description: '', amount: 0 });
            setShowBonusModal(false);
            fetchPayrolls(page);
        } catch (error) {
            toast.error(error.response?.data?.message || 'Erreur');
        }
    }, [bonusForm, selectedPayroll, page, fetchPayrolls]);

    const handleAddDeduction = useCallback(async (e) => {
        e.preventDefault();
        if (!deductionForm.description.trim()) { toast.error('Description requise'); return; }
        if (!deductionForm.amount || deductionForm.amount <= 0) { toast.error('Montant doit être > 0'); return; }
        try {
            await api.post(`/payroll/${selectedPayroll.id}/line-item`, {
                description: deductionForm.description.trim(), amount: deductionForm.amount, type: 'deduction'
            });
            toast.success('Retenue ajoutée');
            setDeductionForm({ description: '', amount: 0 });
            setShowDeductionModal(false);
            fetchPayrolls(page);
        } catch (error) {
            toast.error(error.response?.data?.message || 'Erreur');
        }
    }, [deductionForm, selectedPayroll, page, fetchPayrolls]);

    const handleValidate = useCallback(async (id) => {
        try {
            await api.post(`/payroll/${id}/validate`);
            toast.success('Paie validée');
            fetchPayrolls(page);
        } catch (error) {
            toast.error(error.response?.data?.message || 'Erreur validation');
        }
    }, [page, fetchPayrolls]);

    const handleMarkAsPaid = useCallback(async (id) => {
        try {
            await api.post(`/payroll/${id}/mark-as-paid`);
            toast.success('Paie marquée comme payée');
            fetchPayrolls(page);
        } catch (error) {
            toast.error(error.response?.data?.message || 'Erreur');
        }
    }, [page, fetchPayrolls]);

    const handleDownloadPDF = useCallback(async (payroll) => {
        try {
            const response = await api.get(`/payroll/${payroll.id}`);
            const data = response.data;
            const teacherName = escapeHtml(data.teacher?.user?.name || 'N/A');
            const period = `${data.month}/${data.year}`;
            const gross = new Intl.NumberFormat('fr-FR').format(data.gross_salary || data.honorarium_amount || 0);
            const brs = data.brs_amount ?? 0;
            const net = new Intl.NumberFormat('fr-FR').format(data.net_salary ?? 0);
            const paymentMethod = (data.payment_method || 'cash') === 'check' ? 'Chèque' : 'Espèces';
            const lineItems = data.details || [];
            const totalEarnings = lineItems.filter(li => li.type === 'earning').reduce((sum, li) => sum + Number(li.amount || 0), 0);
            const totalDeductions = lineItems.filter(li => li.type === 'deduction').reduce((sum, li) => sum + Number(li.amount || 0), 0);

            let lineItemsHtml = '';
            lineItems.forEach(li => {
                const isEarning = li.type === 'earning';
                lineItemsHtml += `<tr><td style="padding:6px 12px;font-size:14px;border-bottom:1px solid #e5e7eb;">${escapeHtml(li.description || '')}</td><td style="padding:6px 12px;font-size:14px;text-align:right;border-bottom:1px solid #e5e7eb;color:${isEarning ? '#059669' : '#dc2626'};">${isEarning ? '+' : '-'}${new Intl.NumberFormat('fr-FR').format(li.amount || 0)} FCFA</td></tr>`;
            });

            const html = `<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>Etat d'honoraire</title><style>body{font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;margin:40px;color:#1e293b;}.header{text-align:center;margin-bottom:30px;border-bottom:3px solid #059669;padding-bottom:20px;}.header h1{color:#059669;margin:0;font-size:24px;}.header p{color:#64748b;margin:5px 0 0;font-size:14px;}.info-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:24px;}.info-card{background:#f8fafc;border-radius:8px;padding:12px 16px;}.info-card .label{font-size:11px;text-transform:uppercase;color:#94a3b8;font-weight:700;}.info-card .value{font-size:16px;font-weight:700;color:#1e293b;margin-top:4px;}.summary{background:#059669;color:white;border-radius:12px;padding:20px;margin-top:20px;}.summary-row{display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;font-size:15px;}.summary-row.total{border-top:2px solid rgba(255,255,255,.3);padding-top:12px;margin-top:12px;font-size:20px;font-weight:800;}.section-title{font-size:14px;font-weight:700;color:#059669;margin:16px 0 8px;}table{width:100%;border-collapse:collapse;margin:20px 0;}th{text-align:left;padding:8px 12px;font-size:11px;text-transform:uppercase;color:#94a3b8;border-bottom:2px solid #e2e8f0;}.footer{margin-top:40px;text-align:center;font-size:11px;color:#94a3b8;border-top:1px solid #e5e7eb;padding-top:12px;}</style></head><body><div class="header"><h1>ETAT D'HONORAIRE</h1><p>Période: ${period}</p></div><div class="info-grid"><div class="info-card"><div class="label">Enseignant</div><div class="value">${teacherName}</div></div><div class="info-card"><div class="label">Mode de paiement</div><div class="value">${paymentMethod}</div></div></div>${lineItems.length > 0 ? `<div class="section-title">Détails</div><table><thead><tr><th>Description</th><th style="text-align:right;">Montant</th></tr></thead><tbody>${lineItemsHtml}</tbody></table>` : ''}<div class="summary"><div class="summary-row"><span>Honoraire brut</span><span>${gross} FCFA</span></div>${brs > 0 ? `<div class="summary-row"><span>BRS (5%)</span><span>-${new Intl.NumberFormat('fr-FR').format(brs)} FCFA</span></div>` : ''}<div class="summary-row total"><span>NET À PAYER</span><span>${net} FCFA</span></div></div><div class="footer">Généré le ${new Date().toLocaleDateString('fr-FR')}</div></body></html>`;
            const printWindow = window.open('', '_blank');
            if (printWindow) { printWindow.document.write(html); printWindow.document.close(); }
        } catch {
            toast.error('Erreur téléchargement');
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
        } catch {
            toast.error('Erreur export');
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
                            <>
                                <button onClick={() => setShowIndividualCalculateModal(true)} disabled={isCalculating}
                                    className="flex items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-blue-600 to-indigo-700 text-white rounded-xl font-semibold text-sm shadow-lg shadow-blue-500/25 hover:shadow-blue-500/40 transition-all duration-300 active:scale-[0.97] disabled:opacity-50">
                                    <Plus size={18} />
                                    <span>Calculer paie</span>
                                </button>
                                <button onClick={() => setShowCalculateModal(true)} disabled={isCalculating}
                                    className="flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-emerald-600 to-teal-700 text-white rounded-xl font-semibold text-sm shadow-lg shadow-emerald-500/25 hover:shadow-emerald-500/40 transition-all duration-300 active:scale-[0.97] disabled:opacity-50 hover:scale-105">
                                    <Zap size={20} />
                                    <div className="flex flex-col items-start">
                                        <span className="font-bold">Calculer paies</span>
                                        <span className="text-xs opacity-90">{monthLabel} {year}</span>
                                    </div>
                                </button>
                            </>
                        )}
                        <button onClick={handleExport}
                            className="flex items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-indigo-600 to-blue-700 text-white rounded-xl font-semibold text-sm shadow-lg shadow-indigo-500/25 hover:shadow-indigo-500/40 transition-all duration-300 active:scale-[0.97]">
                            <Download size={18} />
                            <span>Exporter</span>
                        </button>
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
                                        {p.brs_amount > 0 && (
                                            <div className="flex items-center justify-between">
                                                <span className="text-xs text-red-400 flex items-center gap-1"><TrendingDown size={12} /> BRS (5%)</span>
                                                <span className="text-sm font-bold text-red-500">-{new Intl.NumberFormat('fr-FR').format(p.brs_amount)}</span>
                                            </div>
                                        )}
                                        <div className="flex items-center justify-between pt-2 border-t border-slate-100">
                                            <span className="text-xs font-bold text-slate-600">Net</span>
                                            <span className="text-lg font-black text-emerald-600">{new Intl.NumberFormat('fr-FR').format(p.net_salary)} FCFA</span>
                                        </div>
                                    </div>

                                    {(isAccountant || isTeacher) && (
                                        <div className="flex items-center gap-1.5 pt-3 border-t border-slate-100">
                                            {isAccountant && p.status === 'calculated' && (
                                                <>
                                                    <button onClick={() => { setSelectedPayroll(p); setBonusForm({ description: '', amount: 0 }); setShowBonusModal(true); }}
                                                        className="px-3 py-2 bg-emerald-50 text-emerald-600 rounded-xl hover:bg-emerald-500 hover:text-white transition-all duration-200" title="Ajouter prime">
                                                        <Plus size={12} />
                                                    </button>
                                                    <button onClick={() => { setSelectedPayroll(p); setDeductionForm({ description: '', amount: 0 }); setShowDeductionModal(true); }}
                                                        className="px-3 py-2 bg-red-50 text-red-500 rounded-xl hover:bg-red-500 hover:text-white transition-all duration-200" title="Ajouter retenue">
                                                        <Percent size={12} />
                                                    </button>
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

                {/* Individual Calculate Modal */}
                {showIndividualCalculateModal && (
                    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
                        <div className="bg-white rounded-2xl shadow-2xl max-w-md w-full p-6 border border-slate-200">
                            <div className="flex items-center gap-3 mb-4">
                                <div className="w-12 h-12 rounded-xl bg-blue-100 flex items-center justify-center">
                                    <Plus className="text-blue-600" size={24} />
                                </div>
                                <div>
                                    <h2 className="text-xl font-bold text-slate-900">Calculer une paie</h2>
                                    <p className="text-sm text-slate-500">Calcul individuel</p>
                                </div>
                            </div>

                            {!isCalculating && (
                                <form onSubmit={handleCalculateIndividual} className="space-y-4">
                                    <div className="form-group">
                                        <label className="form-label">Enseignant</label>
                                        <select required className="input-field" value={individualForm.teacher_id} onChange={(e) => setIndividualForm({...individualForm, teacher_id: e.target.value})}>
                                            <option value="">-- Sélectionnez un enseignant --</option>
                                            {teachers.map(t => (
                                                <option key={t.id} value={t.id}>{t.user?.name} ({t.employee_id})</option>
                                            ))}
                                        </select>
                                    </div>

                                    <div className="grid grid-cols-2 gap-3">
                                        <div className="form-group">
                                            <label className="form-label">Mois</label>
                                            <select required className="input-field text-sm" value={individualForm.month} onChange={(e) => setIndividualForm({...individualForm, month: e.target.value})}>
                                                {[1,2,3,4,5,6,7,8,9,10,11,12].map(m => (
                                                    <option key={m} value={m}>{MONTH_NAMES[m-1]}</option>
                                                ))}
                                            </select>
                                        </div>
                                        <div className="form-group">
                                            <label className="form-label">Année</label>
                                            <select required className="input-field text-sm" value={individualForm.year} onChange={(e) => setIndividualForm({...individualForm, year: e.target.value})}>
                                                {[2024, 2025, 2026, 2027].map(y => (
                                                    <option key={y} value={y}>{y}</option>
                                                ))}
                                            </select>
                                        </div>
                                    </div>

                                    <div className="form-group">
                                        <label className="form-label">Heures travaillées (optionnel)</label>
                                        <input type="number" className="input-field text-sm" placeholder="Laisser vide pour utiliser le pointage"
                                            value={individualForm.hours_worked} onChange={(e) => setIndividualForm({...individualForm, hours_worked: e.target.value})} />
                                    </div>

                                    <div className="grid grid-cols-2 gap-3">
                                        <div className="form-group">
                                            <label className="form-label">Prime (optionnel)</label>
                                            <input type="number" className="input-field text-sm" placeholder="0" min="0" step="0.01"
                                                value={individualForm.bonus} onChange={(e) => setIndividualForm({...individualForm, bonus: e.target.value})} />
                                        </div>
                                        <div className="form-group">
                                            <label className="form-label">Retenue (optionnel)</label>
                                            <input type="number" className="input-field text-sm" placeholder="0" min="0" step="0.01"
                                                value={individualForm.deduction} onChange={(e) => setIndividualForm({...individualForm, deduction: e.target.value})} />
                                        </div>
                                    </div>

                                    <div className="flex gap-3 pt-4">
                                        <button type="button" onClick={() => setShowIndividualCalculateModal(false)} disabled={isCalculating}
                                            className="flex-1 px-4 py-2.5 border border-slate-300 text-slate-700 rounded-xl font-semibold hover:bg-slate-50 transition-all disabled:opacity-50">
                                            Annuler
                                        </button>
                                        <button type="submit" disabled={isCalculating}
                                            className="flex-1 flex items-center justify-center gap-2 px-4 py-2.5 bg-gradient-to-r from-blue-600 to-indigo-700 text-white rounded-xl font-semibold hover:shadow-lg transition-all disabled:opacity-50">
                                            <Plus size={18} />
                                            <span>{isCalculating ? 'Calcul...' : 'Calculer'}</span>
                                        </button>
                                    </div>
                                </form>
                            )}

                            {isCalculating && (
                                <div className="bg-blue-50 rounded-xl p-4 text-center">
                                    <div className="inline-block mb-3">
                                        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                                    </div>
                                    <p className="text-sm font-semibold text-blue-900">{calculationProgress || 'Calcul en cours...'}</p>
                                </div>
                            )}
                        </div>
                    </div>
                )}

                {/* Bonus Modal */}
                <FormModal isOpen={showBonusModal} onClose={() => setShowBonusModal(false)} onSubmit={handleAddBonus} title="Ajouter une Prime" submitText="Ajouter" submitClassName="btn-success">
                    <div className="space-y-5">
                        <div className="form-group">
                            <label className="form-label">Description</label>
                            <input required type="text" className="input-field" placeholder="Ex: Prime de performance"
                                value={bonusForm.description} onChange={(e) => setBonusForm({...bonusForm, description: e.target.value})} />
                        </div>
                        <div className="form-group">
                            <label className="form-label">Montant (FCFA)</label>
                            <input required type="number" className="input-field" min="0.01" step="0.01"
                                value={bonusForm.amount} onChange={(e) => setBonusForm({...bonusForm, amount: Number(e.target.value) || 0})} />
                        </div>
                    </div>
                </FormModal>

                {/* Deduction Modal */}
                <FormModal isOpen={showDeductionModal} onClose={() => setShowDeductionModal(false)} onSubmit={handleAddDeduction} title="Ajouter une Retenue" submitText="Ajouter" submitClassName="btn-danger">
                    <div className="space-y-5">
                        <div className="form-group">
                            <label className="form-label">Description</label>
                            <input required type="text" className="input-field" placeholder="Ex: Avance sur honoraire"
                                value={deductionForm.description} onChange={(e) => setDeductionForm({...deductionForm, description: e.target.value})} />
                        </div>
                        <div className="form-group">
                            <label className="form-label">Montant (FCFA)</label>
                            <input required type="number" className="input-field" min="0.01" step="0.01"
                                value={deductionForm.amount} onChange={(e) => setDeductionForm({...deductionForm, amount: Number(e.target.value) || 0})} />
                        </div>
                    </div>
                </FormModal>

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

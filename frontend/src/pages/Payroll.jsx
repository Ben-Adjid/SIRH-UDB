import { useEffect, useState, useCallback } from 'react';
import api from '../services/api';
import useAuthStore from '../store/authStore';
import { CreditCard, Plus, CheckCircle, Download, DollarSign, Percent, FileText, TrendingUp, TrendingDown, Users, Wallet, Calendar } from 'lucide-react';
import toast from '../utils/toast';
import FormModal from '../components/FormModal';
import ConfirmModal from '../components/ConfirmModal';
import Pagination from '../components/Pagination';
import { getUserRoles } from '../utils/roles';
import { escapeHtml } from '../utils/escape';
import { MONTH_NAMES } from '../utils/constants';

const Payroll = () => {
    const user = useAuthStore((state) => state.user);
    const [payrolls, setPayrolls] = useState([]);
    const [teachers, setTeachers] = useState([]);
    const [loading, setLoading] = useState(true);
    const [page, setPage] = useState(1);
    const perPage = 12;
    const [pagination, setPagination] = useState({ current_page: 1, last_page: 1, total: 0 });
    const [showBonusModal, setShowBonusModal] = useState(false);
    const [showDeductionModal, setShowDeductionModal] = useState(false);
    const [selectedPayroll, setSelectedPayroll] = useState(null);
    const [showCalcModal, setShowCalcModal] = useState(false);
    const [calcTeacherInfo, setCalcTeacherInfo] = useState(null);
    const [attendanceSummary, setAttendanceSummary] = useState({ hours: 0, incomplete: 0 });

    const userRoles = getUserRoles(user);
    const isAdmin = userRoles.includes('admin');
    const isAccountant = userRoles.includes('accountant') || isAdmin;
    const isTeacher = userRoles.includes('teacher') && !isAccountant;
    const currentTeacherId = user?.teacher?.id;

    const [salaryForm, setSalaryForm] = useState({
        teacher_id: '', month: new Date().toISOString().substring(0, 7), bonus: 0, deduction: 0, hours_worked: 0,
    });
    const [bonusForm, setBonusForm] = useState({ description: '', amount: 0 });
    const [deductionForm, setDeductionForm] = useState({ description: '', amount: 0 });
    const [confirmConfig, setConfirmConfig] = useState({
        isOpen: false, type: 'warning', title: '', message: '', onConfirm: () => {}
    });

    useEffect(() => {
        const controller = new AbortController();
        if (isAccountant) {
            api.get('/teachers', { params: { per_page: 200 }, signal: controller.signal }).then(res => {
                const d = res.data;
                setTeachers(Array.isArray(d) ? d : (d?.data || []));
            }).catch(() => {});
        }
        return () => controller.abort();
    }, [isAccountant]);

    const fetchPayrolls = useCallback(async (pageNum = 1) => {
        setLoading(true);
        try {
            let endpoint = '/payroll';
            const params = { page: pageNum, per_page: perPage };
            if (isTeacher && currentTeacherId) params.teacher_id = currentTeacherId;
            const response = await api.get(endpoint, { params });
            const data = response.data;
            setPayrolls(data.data || []);
            setPagination({ current_page: data.current_page, last_page: data.last_page, total: data.total });
        } catch {
            toast.error('Erreur chargement honoraires');
        } finally {
            setLoading(false);
        }
    }, [isTeacher, currentTeacherId]);

    useEffect(() => {
        fetchPayrolls(page);
    }, [page, fetchPayrolls]);

    const handleCalcTeacherSelect = useCallback(async (teacherId) => {
        setSalaryForm(prev => ({...prev, teacher_id: teacherId}));
        if (!teacherId) { setCalcTeacherInfo(null); return; }
        try {
            const response = await api.get(`/teachers/${teacherId}`);
            setCalcTeacherInfo(response.data);
        } catch {
            setCalcTeacherInfo(null);
        }
    }, []);

    useEffect(() => {
        if (!salaryForm.teacher_id || !salaryForm.month || !isAccountant) {
            setAttendanceSummary({ hours: 0, incomplete: 0 });
            return;
        }
        const [year, month] = salaryForm.month.split('-').map(Number);
        const controller = new AbortController();
        api.get('/payroll/attendance-hours', { params: { teacher_id: salaryForm.teacher_id, year, month }, signal: controller.signal })
            .then(response => setAttendanceSummary({ hours: Number(response.data?.hours || 0), incomplete: Number(response.data?.incomplete || 0) }))
            .catch(error => { if (error.name !== 'CanceledError') toast.error('Impossible de charger les heures pointées'); });
        return () => controller.abort();
    }, [salaryForm.teacher_id, salaryForm.month, isAccountant]);

    const handleCalculate = useCallback(async (e) => {
        e.preventDefault();
        try {
            const [year, month] = salaryForm.month.split('-').map(Number);
            const hoursToUse = salaryForm.hours_worked > 0 ? salaryForm.hours_worked : attendanceSummary.hours;
            const response = await api.post('/payroll/calculate', {
                teacher_id: salaryForm.teacher_id, month, year,
                bonus: salaryForm.bonus ?? 0, deduction: salaryForm.deduction ?? 0,
                hours_worked: hoursToUse,
            });
            toast.success('Honoraire calcule avec succes');
            if (response.data?.summary?.incomplete_attendances > 0) {
                toast.error(`${response.data.summary.incomplete_attendances} pointage(s) sans sortie restent à clôturer.`);
            }
            if (response.data?.summary?.approved_leaves_deducted) {
                const hours = response.data.summary.approved_leaves_hours || 0;
                toast.warning(`Congés approuvés détectés: ${hours}h déduites du salaire.`);
            }
            setShowCalcModal(false);
            setCalcTeacherInfo(null);
            fetchPayrolls(page);
        } catch (error) {
            toast.error(error.response?.data?.message || 'Erreur de calcul');
        }
    }, [salaryForm, attendanceSummary.hours, fetchPayrolls, page]);

    const handleGeneratePayslip = useCallback(async (payroll) => {
        try {
            await api.post(`/payroll/${payroll.id}/validate`);
            toast.success("Etat d'honoraire genere");
            fetchPayrolls(page);
        } catch {
            toast.error('Erreur de generation');
        }
    }, [fetchPayrolls, page]);

    const handleAddBonus = useCallback(async (e) => {
        e.preventDefault();
        if (!bonusForm.description.trim()) { toast.error('La description est requise'); return; }
        if (!bonusForm.amount || bonusForm.amount <= 0) { toast.error('Le montant doit etre superieur a 0'); return; }
        try {
            await api.post(`/payroll/${selectedPayroll.id}/line-item`, {
                description: bonusForm.description.trim(), amount: bonusForm.amount, type: 'earning'
            });
            toast.success('Prime ajoutee');
            setBonusForm({ description: '', amount: 0 });
            setShowBonusModal(false);
            fetchPayrolls(page);
        } catch (error) {
            toast.error(error.response?.data?.message || 'Erreur ajout prime');
        }
    }, [bonusForm, selectedPayroll, fetchPayrolls, page]);

    const handleAddDeduction = useCallback(async (e) => {
        e.preventDefault();
        if (!deductionForm.description.trim()) { toast.error('La description est requise'); return; }
        if (!deductionForm.amount || deductionForm.amount <= 0) { toast.error('Le montant doit etre superieur a 0'); return; }
        try {
            await api.post(`/payroll/${selectedPayroll.id}/line-item`, {
                description: deductionForm.description.trim(), amount: deductionForm.amount, type: 'deduction'
            });
            toast.success('Retenue ajoutee');
            setDeductionForm({ description: '', amount: 0 });
            setShowDeductionModal(false);
            fetchPayrolls(page);
        } catch (error) {
            toast.error(error.response?.data?.message || 'Erreur ajout retenue');
        }
    }, [deductionForm, selectedPayroll, fetchPayrolls, page]);

    const handleMarkAsPaid = useCallback(async (id) => {
        try {
            await api.post(`/payroll/${id}/mark-as-paid`);
            toast.success('Honoraire marque comme paye');
            fetchPayrolls(page);
        } catch (error) {
            toast.error(error.response?.data?.message || 'Erreur');
        }
    }, [fetchPayrolls, page]);

    const handleValidate = useCallback(async (id) => {
        try {
            await api.post(`/payroll/${id}/validate`);
            toast.success('Honoraire valide');
            fetchPayrolls(page);
        } catch (error) {
            toast.error(error.response?.data?.message || 'Erreur de validation');
        }
    }, [fetchPayrolls, page]);

    const handleExport = useCallback(async () => {
        try {
            const response = await api.get('/payroll/export', { responseType: 'blob' });
            const url = window.URL.createObjectURL(new Blob([response.data]));
            const a = document.createElement('a');
            a.href = url;
            a.download = `export_honoraires_${new Date().toISOString().split('T')[0]}.csv`;
            a.click();
            window.URL.revokeObjectURL(url);
            toast.success('Exporte avec succes');
        } catch {
            toast.error("Erreur d'export");
        }
    }, []);

    const openConfirm = useCallback((title, message, action, type = 'warning') => {
        setConfirmConfig({ isOpen: true, title, message, onConfirm: action, type });
    }, []);

    const handleDownload = useCallback(async (payroll) => {
        try {
            const response = await api.get(`/payroll/${payroll.id}`);
            const data = response.data;
            const teacherName = escapeHtml(data.teacher?.user?.name || payroll.teacher?.user?.name || 'N/A');
            const period = `${data.month || payroll.month}/${data.year || payroll.year}`;
            const gross = new Intl.NumberFormat('fr-FR').format(data.gross_salary || data.honorarium_amount || payroll.gross_salary || payroll.honorarium_amount || 0);
            const brs = data.brs_amount ?? payroll.brs_amount ?? 0;
            const net = new Intl.NumberFormat('fr-FR').format(data.net_salary ?? payroll.net_salary ?? 0);
            const paymentMethod = (data.payment_method || payroll.payment_method) === 'check' ? 'Cheque' : (data.payment_method || payroll.payment_method) === 'cash' ? 'Espece' : '-';
             const lineItems = data.details || data.line_items || payroll.details || payroll.line_items || [];
            const totalEarnings = lineItems.filter(li => li.type === 'earning').reduce((sum, li) => sum + Number(li.amount || 0), 0);
            const totalDeductions = lineItems.filter(li => li.type === 'deduction').reduce((sum, li) => sum + Number(li.amount || 0), 0);

            let lineItemsHtml = '';
            lineItems.forEach(li => {
                const isEarning = li.type === 'earning';
                lineItemsHtml += `
                    <tr>
                        <td style="padding:6px 12px;font-size:14px;border-bottom:1px solid #e5e7eb;">${escapeHtml(li.description || '')}</td>
                        <td style="padding:6px 12px;font-size:14px;text-align:right;border-bottom:1px solid #e5e7eb;color:${isEarning ? '#059669' : '#dc2626'};">${isEarning ? '+' : '-'}${new Intl.NumberFormat('fr-FR').format(li.amount || 0)} FCFA</td>
                    </tr>`;
            });

            const html = `<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><title>Etat d'honoraire</title>
<style>
    body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 40px; color: #1e293b; }
    .header { text-align: center; margin-bottom: 30px; border-bottom: 3px solid #059669; padding-bottom: 20px; }
    .header h1 { color: #059669; margin: 0; font-size: 24px; }
    .header p { color: #64748b; margin: 5px 0 0; font-size: 14px; }
    .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 24px; }
    .info-card { background: #f8fafc; border-radius: 8px; padding: 12px 16px; }
    .info-card .label { font-size: 11px; text-transform: uppercase; color: #94a3b8; font-weight: 700; letter-spacing: 1px; }
    .info-card .value { font-size: 16px; font-weight: 700; color: #1e293b; margin-top: 4px; }
    .summary { background: #059669; color: white; border-radius: 12px; padding: 20px; margin-top: 20px; }
    .summary-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; font-size: 15px; }
    .summary-row.total { border-top: 2px solid rgba(255,255,255,0.3); padding-top: 12px; margin-top: 12px; font-size: 20px; font-weight: 800; }
    table { width: 100%; border-collapse: collapse; margin: 20px 0; }
    th { text-align: left; padding: 8px 12px; font-size: 11px; text-transform: uppercase; color: #94a3b8; border-bottom: 2px solid #e2e8f0; }
    .section-title { font-size: 14px; font-weight: 700; color: #059669; margin: 16px 0 8px; }
    .footer { margin-top: 40px; text-align: center; font-size: 11px; color: #94a3b8; border-top: 1px solid #e5e7eb; padding-top: 12px; }
</style></head>
<body>
    <div class="header">
        <h1>ETAT D'HONORAIRE</h1>
        <p>Period: ${period}</p>
    </div>
    <div class="info-grid">
        <div class="info-card"><div class="label">Enseignant</div><div class="value">${teacherName}</div></div>
        <div class="info-card"><div class="label">Mode de paiement</div><div class="value">${paymentMethod}</div></div>
    </div>
    ${lineItems.length > 0 ? `<div class="section-title">Details des lignes</div><table><thead><tr><th>Description</th><th style="text-align:right;">Montant</th></tr></thead><tbody>${lineItemsHtml}</tbody></table>` : ''}
    <div class="summary">
        <div class="summary-row"><span>Honoraire brut</span><span>${gross} FCFA</span></div>
        ${brs > 0 ? `<div class="summary-row"><span>BRS (5%)</span><span>-${new Intl.NumberFormat('fr-FR').format(brs)} FCFA</span></div>` : ''}
        ${totalEarnings > 0 ? `<div class="summary-row"><span>Primes</span><span>+${new Intl.NumberFormat('fr-FR').format(totalEarnings)} FCFA</span></div>` : ''}
        ${totalDeductions > 0 ? `<div class="summary-row"><span>Retenues</span><span>-${new Intl.NumberFormat('fr-FR').format(totalDeductions)} FCFA</span></div>` : ''}
        <div class="summary-row total"><span>NET A PAYER</span><span>${net} FCFA</span></div>
    </div>
    <div class="footer">Document genere le ${new Date().toLocaleDateString('fr-FR')}</div>
</body></html>`;
            const printWindow = window.open('', '_blank');
            if (printWindow) {
                printWindow.document.write(html);
                printWindow.document.close();
                toast.success("Honoraire ouvert dans un nouvel onglet");
            } else {
                toast.success('Honoraire telecharge');
            }
        } catch {
            toast.error('Erreur de telechargement');
        }
    }, []);

    const AVATAR_GRADIENTS = [
        'from-indigo-500 to-violet-600', 'from-emerald-500 to-teal-600',
        'from-blue-500 to-indigo-600', 'from-amber-500 to-orange-600'
    ];

    return (
        <div className="min-h-screen">
            <div className="max-w-[1400px] mx-auto space-y-6 px-2 sm:px-4">
                <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 pt-2">
                    <div>
                        <div className="flex items-center gap-3 mb-1">
                            <div className="w-10 h-10 rounded-xl bg-gradient-to-br from-emerald-500 to-teal-600 flex items-center justify-center shadow-lg shadow-emerald-500/25">
                                <DollarSign size={20} className="text-white" />
                            </div>
                            <h1 className="text-2xl font-bold text-slate-900 tracking-tight">Gestion des Honoraires</h1>
                        </div>
                        <p className="text-slate-400 text-sm ml-[52px]">Heures de cours pointées, calcul, validation et export des honoraires</p>
                    </div>
                    <div className="flex gap-2">
                        {isAccountant && (
                            <button onClick={() => { setSalaryForm({ teacher_id: '', month: new Date().toISOString().substring(0, 7), bonus: 0, deduction: 0, hours_worked: 0 }); setCalcTeacherInfo(null); setShowCalcModal(true); }}
                                className="flex items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-emerald-600 to-teal-700 text-white rounded-xl font-semibold text-sm shadow-lg shadow-emerald-500/25 hover:shadow-emerald-500/40 transition-all duration-300 active:scale-[0.97]">
                                <Plus size={18} strokeWidth={2.5} />
                                <span>Calculer Honoraire</span>
                            </button>
                        )}
                        <button onClick={handleExport}
                            className="flex items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-indigo-600 to-blue-700 text-white rounded-xl font-semibold text-sm shadow-lg shadow-indigo-500/25 hover:shadow-indigo-500/40 transition-all duration-300 active:scale-[0.97]">
                            <Download size={18} />
                            <span>Exporter</span>
                        </button>
                    </div>
                </div>

                {loading ? (
                    <div className="flex justify-center py-20">
                        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-emerald-600" />
                    </div>
                ) : payrolls.length === 0 ? (
                    <div className="flex flex-col items-center justify-center py-24">
                        <div className="w-16 h-16 bg-slate-100 rounded-2xl flex items-center justify-center mb-4">
                            <CreditCard size={28} className="text-slate-300" />
                        </div>
                        <p className="text-base font-bold text-slate-400 mb-1">Aucun etat d'honoraire</p>
                        <p className="text-xs text-slate-300">Les etats d'honoraire apparaitront ici une fois calcules</p>
                    </div>
                ) : (
                    <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 mb-6">
                        {payrolls.map((p) => {
                            const avatarIdx = p.id % AVATAR_GRADIENTS.length;
                            const isPaid = p.status === 'paid';
                            const isValidated = p.status === 'validated';

                            return (
                                <div key={p.id}
                                    className="group relative bg-white rounded-2xl p-5 border border-slate-100 shadow-sm hover:shadow-lg hover:border-emerald-200 transition-all duration-300">
                                    <div className="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-emerald-400 to-teal-500 opacity-0 group-hover:opacity-100 transition-opacity rounded-t-2xl" />

                                    <div className="flex items-start justify-between mb-4">
                                        <div className="flex items-center gap-3">
                                            <div className={`flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br ${AVATAR_GRADIENTS[avatarIdx]} text-white font-bold text-sm shadow-md`}>
                                                {p.teacher?.user?.name?.charAt(0)?.toUpperCase()}
                                            </div>
                                            <div className="min-w-0">
                                                <h3 className="font-bold text-slate-900 text-sm truncate group-hover:text-emerald-600 transition-colors">{p.teacher?.user?.name}</h3>
                                                <p className="text-[10px] font-mono font-semibold text-slate-400">{p.teacher?.employee_id || 'ENSEIGNANT'}</p>
                                            </div>
                                        </div>
                                        <span className={`inline-flex items-center px-2.5 py-1 rounded-lg text-[10px] font-black uppercase tracking-wider border ${
                                            isPaid ? 'bg-emerald-50 text-emerald-600 border-emerald-100' :
                                            isValidated ? 'bg-blue-50 text-blue-600 border-blue-100' :
                                            'bg-amber-50 text-amber-600 border-amber-100'
                                        }`}>
                                            <span className={`w-1.5 h-1.5 rounded-full mr-1.5 ${isPaid ? 'bg-emerald-500' : isValidated ? 'bg-blue-500' : 'bg-amber-500 animate-pulse'}`}></span>
                                            {isPaid ? 'Paye' : isValidated ? 'Valide' : 'Calcule'}
                                        </span>
                                    </div>

                                    <div className="grid grid-cols-2 gap-3 mb-4">
                                        <div className="bg-slate-50 rounded-xl p-3 text-center">
                                            <p className="text-[10px] font-bold text-slate-400 uppercase mb-1">Periode</p>
                                            <div className="flex items-center justify-center gap-1">
                                                <Calendar size={12} className="text-emerald-500" />
                                                <p className="text-sm font-bold text-slate-900">{p.month}/{p.year}</p>
                                            </div>
                                        </div>
                                        <div className="bg-slate-50 rounded-xl p-3 text-center">
                                            <p className="text-[10px] font-bold text-slate-400 uppercase mb-1">Paiement</p>
                                            <span className={`inline-flex items-center gap-1 text-[10px] font-black uppercase px-2 py-1 rounded-full border ${
                                                p.payment_method === 'check' ? 'bg-blue-50 text-blue-600 border-blue-100' : 'bg-amber-50 text-amber-600 border-amber-100'
                                            }`}>
                                                {p.payment_method === 'check' ? <FileText size={10} /> : <DollarSign size={10} />}
                                                {p.payment_method === 'check' ? 'Cheque' : p.payment_method === 'cash' ? 'Espece' : '-'}
                                            </span>
                                        </div>
                                    </div>

                                    <div className="space-y-2 mb-4">
                                        <div className="flex items-center justify-between">
                                            <span className="text-xs text-slate-500">Brut</span>
                                            <span className="text-sm font-bold text-slate-900">{new Intl.NumberFormat('fr-FR').format(p.gross_salary || p.honorarium_amount || 0)} FCFA</span>
                                        </div>
                                        {p.brs_amount > 0 && (
                                            <div className="flex items-center justify-between">
                                                <span className="text-xs text-red-400 flex items-center gap-1">
                                                    <TrendingDown size={12} /> BRS (5%)
                                                </span>
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
                                                <button onClick={() => handleGeneratePayslip(p)}
                                                    className="flex-1 flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl text-[11px] font-bold bg-purple-50 text-purple-600 hover:bg-purple-500 hover:text-white transition-all duration-200">
                                                    <FileText size={12} /> Generer
                                                </button>
                                                <button onClick={() => { setSelectedPayroll(p); setBonusForm({ description: '', amount: 0 }); setShowBonusModal(true); }}
                                                    className="px-3 py-2 bg-emerald-50 text-emerald-600 rounded-xl hover:bg-emerald-500 hover:text-white transition-all duration-200">
                                                    <Plus size={12} />
                                                </button>
                                                <button onClick={() => { setSelectedPayroll(p); setDeductionForm({ description: '', amount: 0 }); setShowDeductionModal(true); }}
                                                    className="px-3 py-2 bg-red-50 text-red-500 rounded-xl hover:bg-red-500 hover:text-white transition-all duration-200">
                                                    <Percent size={12} />
                                                </button>
                                                <button onClick={() => openConfirm(
                                                    "Valider l'honoraire",
                                                    `Etes-vous sur de vouloir valider l'honoraire de ${p.teacher?.user?.name} (${p.month}/${p.year}) ?`,
                                                    () => handleValidate(p.id), 'warning'
                                                )}
                                                    className="px-3 py-2 bg-blue-50 text-blue-600 rounded-xl hover:bg-blue-500 hover:text-white transition-all duration-200">
                                                    <CheckCircle size={12} />
                                                </button>
                                            </>
                                            )}
                                            {isAccountant && p.status === 'validated' && (
                                                <button onClick={() => openConfirm(
                                                    'Marquer comme paye',
                                                    `Confirmer le paiement de l'honoraire de ${p.teacher?.user?.name} (${p.month}/${p.year}) ?`,
                                                    () => handleMarkAsPaid(p.id), 'danger'
                                                )}
                                                    className="flex-1 flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl text-[11px] font-bold bg-emerald-50 text-emerald-600 hover:bg-emerald-500 hover:text-white transition-all duration-200">
                                                    <DollarSign size={12} /> Marquer comme paye
                                                </button>
                                            )}
                                            <button onClick={() => handleDownload(p)}
                                                className="px-3 py-2 text-slate-300 hover:text-blue-500 hover:bg-blue-50 rounded-xl transition-all duration-200">
                                                <Download size={14} />
                                                <span className="sr-only">Ouvrir le récapitulatif</span>
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

                <FormModal
                    isOpen={showCalcModal}
                    onClose={() => { setShowCalcModal(false); setCalcTeacherInfo(null); }}
                    onSubmit={handleCalculate}
                    title="Calculer l'Honoraire"
                    subtitle="Les heures sont calculées automatiquement depuis les cours pointés"
                    submitText="Calculer"
                    submitClassName="btn-success"
                >
                    <div className="space-y-5">
                        <div className="form-group">
                            <label className="form-label">Enseignant</label>
                            <select required className="select-field"
                                value={salaryForm.teacher_id} onChange={e => handleCalcTeacherSelect(e.target.value)}>
                                <option value="">Selectionner un enseignant...</option>
                                {teachers.map(t => (
                                    <option key={t.id} value={t.id}>{t.user?.name} ({t.employee_id})</option>
                                ))}
                            </select>
                        </div>
                        {calcTeacherInfo && (
                            <div className="bg-gradient-to-r from-emerald-50 to-blue-50 border border-emerald-200 rounded-xl p-4 space-y-2">
                                <p className="text-xs font-black uppercase tracking-widest text-emerald-600 mb-2">Informations enseignant</p>
                                <div className="grid grid-cols-2 gap-2 text-xs">
                                    <div className="flex justify-between"><span className="text-gray-500">Matricule</span><span className="font-bold text-gray-700">{calcTeacherInfo.employee_id || 'N/A'}</span></div>
                                    {calcTeacherInfo.volume_hours != null && (
                                        <div className="flex justify-between"><span className="text-gray-500">Volume horaire</span><span className="font-bold text-gray-700">{calcTeacherInfo.volume_hours}h</span></div>
                                    )}
                                    {calcTeacherInfo.hourly_rate != null && (
                                        <div className="flex justify-between"><span className="text-gray-500">Taux horaire</span><span className="font-bold text-gray-700">{new Intl.NumberFormat('fr-FR').format(calcTeacherInfo.hourly_rate)} F</span></div>
                                    )}
                                    <div className="flex justify-between col-span-2 border-t border-emerald-200 pt-2 mt-1">
                                        <span className="text-emerald-700 font-bold">Honoraires estimes</span>
                                        <span className="font-black text-emerald-700">{new Intl.NumberFormat('fr-FR').format(Number(calcTeacherInfo.volume_hours || 0) * Number(calcTeacherInfo.hourly_rate || 0))} F/mois</span>
                                    </div>
                                </div>
                            </div>
                        )}
                        <div className="form-group">
                            <label className="form-label">Periode</label>
                            <input required type="month" className="input-field"
                                value={salaryForm.month} onChange={e => setSalaryForm({...salaryForm, month: e.target.value})} />
                        </div>
                        <div className="rounded-xl border border-indigo-200 bg-indigo-50 p-4">
                            <div className="flex items-center justify-between gap-3 mb-3">
                                <div><p className="text-xs font-black uppercase tracking-wider text-indigo-700">Heures de cours pointées</p><p className="text-xs text-indigo-500 mt-1">Calcul automatique selon les pointages du mois</p></div>
                                <span className="text-2xl font-black text-indigo-700">{attendanceSummary.hours} h</span>
                            </div>
                            {attendanceSummary.incomplete > 0 && <p className="text-xs font-bold text-amber-700 mb-3">{attendanceSummary.incomplete} pointage(s) sans sortie seront signalés.</p>}
                            <div className="border-t border-indigo-200 pt-3">
                                <label className="form-label text-indigo-700 mb-2">Heures manuelles (optionnel)</label>
                                <input type="number" className="input-field" min="0" step="0.5" placeholder="Saisir si pas de pointages"
                                    value={salaryForm.hours_worked} onChange={e => setSalaryForm({...salaryForm, hours_worked: Number(e.target.value) || 0})} />
                                <p className="text-xs text-indigo-600 mt-1">Laissez à 0 pour utiliser les heures pointées automatiquement</p>
                            </div>
                        </div>
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <div className="form-group">
                                <label className="form-label text-emerald-600">Primes (+)</label>
                                <input type="number" className="input-field" min="0"
                                    value={salaryForm.bonus} onChange={e => setSalaryForm({...salaryForm, bonus: Number(e.target.value) || 0})} />
                            </div>
                            <div className="form-group">
                                <label className="form-label text-red-600">Retenues (-)</label>
                                <input type="number" className="input-field" min="0"
                                    value={salaryForm.deduction} onChange={e => setSalaryForm({...salaryForm, deduction: Number(e.target.value) || 0})} />
                            </div>
                        </div>
                    </div>
                </FormModal>

                <FormModal
                    isOpen={showBonusModal}
                    onClose={() => setShowBonusModal(false)}
                    onSubmit={handleAddBonus}
                    title="Ajouter une Prime"
                    subtitle={selectedPayroll ? `Honoraire ${selectedPayroll.month}/${selectedPayroll.year}` : ''}
                    submitText="Ajouter"
                    submitClassName="btn-success"
                >
                    <div className="space-y-5">
                        <div className="form-group">
                            <label className="form-label">Description</label>
                            <input required type="text" className="input-field" placeholder="Ex: Prime de performance"
                                value={bonusForm.description} onChange={e => setBonusForm({...bonusForm, description: e.target.value})} />
                        </div>
                        <div className="form-group">
                            <label className="form-label">Montant (FCFA)</label>
                            <input required type="number" className="input-field" min="0.01" step="0.01"
                                value={bonusForm.amount} onChange={e => setBonusForm({...bonusForm, amount: Number(e.target.value) || 0})} />
                        </div>
                    </div>
                </FormModal>

                <FormModal
                    isOpen={showDeductionModal}
                    onClose={() => setShowDeductionModal(false)}
                    onSubmit={handleAddDeduction}
                    title="Ajouter une Retenue"
                    subtitle={selectedPayroll ? `Honoraire ${selectedPayroll.month}/${selectedPayroll.year}` : ''}
                    submitText="Ajouter"
                    submitClassName="btn-danger"
                >
                    <div className="space-y-5">
                        <div className="form-group">
                            <label className="form-label">Description</label>
                            <input required type="text" className="input-field" placeholder="Ex: Avance sur honoraire"
                                value={deductionForm.description} onChange={e => setDeductionForm({...deductionForm, description: e.target.value})} />
                        </div>
                        <div className="form-group">
                            <label className="form-label">Montant (FCFA)</label>
                            <input required type="number" className="input-field" min="0.01" step="0.01"
                                value={deductionForm.amount} onChange={e => setDeductionForm({...deductionForm, amount: Number(e.target.value) || 0})} />
                        </div>
                    </div>
                </FormModal>

                <ConfirmModal
                    isOpen={confirmConfig.isOpen}
                    onClose={() => setConfirmConfig({ ...confirmConfig, isOpen: false })}
                    onConfirm={confirmConfig.onConfirm}
                    title={confirmConfig.title}
                    message={confirmConfig.message}
                    confirmText="Confirmer"
                    type={confirmConfig.type}
                />
            </div>
        </div>
    );
};

export default Payroll;

import { createElement, useEffect, useState, useRef, memo, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import api from '../services/api';
import { dataRefresh } from '../services/dataRefresh';
import useAuthStore from '../store/authStore';
import {
    Users, Calendar, Clock, FileText,
    TrendingUp, AlertCircle, CheckCircle,
    Wallet, BookOpen, GraduationCap, MapPin,
    ChevronRight, ArrowUpRight, Activity,
    Bell, Zap, Target, Shield, UserCheck,
    Sun, Moon, RefreshCw
} from 'lucide-react';
import toast from '../utils/toast';
import SimpleChart from '../components/SimpleChart';
import { getPrimaryRole, ROLE_LABELS } from '../utils/roles';
import { MONTH_NAMES } from '../utils/constants';
import { escapeHtml } from '../utils/escape';
import useAnimatedNumber from '../hooks/useAnimatedNumber';
import { SkeletonCard, SkeletonChart } from '../components/Skeleton';

const quickActions = {
    admin: [
        { label: 'Nouveau compte', icon: Users, path: '/users', color: 'from-indigo-500 to-purple-600' },
        { label: 'Gérer enseignants', icon: Users, path: '/teachers', color: 'from-emerald-500 to-teal-600' },
        { label: 'Voir pointages', icon: Calendar, path: '/attendance', color: 'from-amber-500 to-orange-600' },
    ],
    hr_manager: [
        { label: 'Nouveau compte', icon: Users, path: '/users', color: 'from-indigo-500 to-purple-600' },
        { label: 'Gérer enseignants', icon: Users, path: '/teachers', color: 'from-emerald-500 to-teal-600' },
        { label: 'Gérer absences', icon: Calendar, path: '/leaves', color: 'from-blue-500 to-indigo-600' },
    ],
     [
        { label: 'Nouveau compte', icon: Users, path: '/users', color: 'from-indigo-500 to-purple-600' },
        { label: 'Gérer enseignants', icon: Users, path: '/teachers', color: 'from-emerald-500 to-teal-600' },
        { label: 'Gérer absences', icon: Calendar, path: '/leaves', color: 'from-blue-500 to-indigo-600' },
    ],
    accountant: [
        { label: 'Nouvel honoraire', icon: Wallet, path: '/payroll', color: 'from-emerald-500 to-teal-600' },
        { label: 'Export données', icon: FileText, path: '/payroll', color: 'from-blue-500 to-indigo-600' },
        { label: 'Voir paiements', icon: TrendingUp, path: '/payroll', color: 'from-amber-500 to-orange-600' },
    ],
    academic_manager: [
        { label: 'Planifier cours', icon: Calendar, path: '/schedules', color: 'from-purple-500 to-pink-600' },
        { label: 'Gérer salles', icon: MapPin, path: '/classrooms', color: 'from-blue-500 to-cyan-600' },
        { label: 'Nouveau devoir', icon: BookOpen, path: '/homeworks', color: 'from-amber-500 to-orange-600' },
    ],
    teacher: [
        { label: 'Mon pointage', icon: Clock, path: '/attendance', color: 'from-emerald-500 to-teal-600' },
        { label: 'Emploi du temps', icon: Calendar, path: '/schedules', color: 'from-blue-500 to-indigo-600' },
        { label: 'Mes honoraires', icon: Wallet, path: '/payroll', color: 'from-amber-500 to-orange-600' },
        { label: 'Mes documents', icon: FileText, path: '/documents', color: 'from-purple-500 to-pink-600' },
    ],
};

const QuickActions = memo(({ role }) => {
    const navigate = useNavigate();
    const actions = quickActions[role] || quickActions.teacher;
    return (
        <div className="flex flex-wrap gap-3">
            {actions.map((a, i) => (
                <button key={i} onClick={() => navigate(a.path)}
                    className="group relative overflow-hidden px-5 py-3 rounded-2xl bg-white border border-slate-100 shadow-sm hover:shadow-md transition-all hover:-translate-y-0.5 flex items-center gap-3"
                >
                    <div className={`w-8 h-8 rounded-xl bg-gradient-to-br ${a.color} flex items-center justify-center shadow-md`}>
                        <a.icon size={14} className="text-white" />
                    </div>
                    <span className="text-xs font-black text-slate-700 whitespace-nowrap">{a.label}</span>
                </button>
            ))}
        </div>
    );
});

const useTilt = (maxTilt = 4) => {
    const ref = useRef(null);
    useEffect(() => {
        const el = ref.current;
        if (!el) return;
        const onMove = (e) => {
            const rect = el.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            const cx = rect.width / 2;
            const cy = rect.height / 2;
            el.style.transform = `perspective(800px) rotateX(${-((y - cy) / cy) * maxTilt}deg) rotateY(${((x - cx) / cx) * maxTilt}deg) scale3d(1.02,1.02,1.02)`;
            el.style.setProperty('--tilt-x', `${((x / rect.width) * 100)}%`);
            el.style.setProperty('--tilt-y', `${((y / rect.height) * 100)}%`);
        };
        const onLeave = () => {
            el.style.transform = 'perspective(800px) rotateX(0deg) rotateY(0deg) scale3d(1,1,1)';
        };
        el.addEventListener('mousemove', onMove);
        el.addEventListener('mouseleave', onLeave);
        return () => { el.removeEventListener('mousemove', onMove); el.removeEventListener('mouseleave', onLeave); };
    }, [maxTilt]);
    return ref;
};

const LiveTimestamp = () => {
    const [now, setNow] = useState(() => Date.now());
    const [fetchTime] = useState(() => Date.now());
    useEffect(() => {
        const id = setInterval(() => setNow(Date.now()), 1000);
        return () => clearInterval(id);
    }, []);
    const diff = Math.floor((now - fetchTime) / 1000);
    const label = diff < 10 ? "à l'instant" : diff < 60 ? `il y a ${diff}s` : `il y a ${Math.floor(diff / 60)}min`;
    return (
        <div className="flex items-center gap-2 text-[10px] font-black uppercase tracking-widest">
            <span className="relative flex h-2 w-2">
                <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                <span className="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
            </span>
            <span className="text-slate-400">Mis à jour {label}</span>
        </div>
    );
};

const WelcomeHero = memo(({ user, userRole, onRefresh, loading }) => {
    const hour = new Date().getHours();
    const greeting = hour < 12 ? 'Bonjour' : hour < 17 ? 'Bon après-midi' : 'Bonsoir';
    const today = new Date().toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
    return (
        <section className="relative overflow-hidden bg-white rounded-3xl p-8 md:p-10 border border-slate-100 shadow-sm">
            <div className="absolute top-0 right-0 -mr-24 -mt-24 w-64 h-64 bg-indigo-100 rounded-full blur-[80px] opacity-60"></div>
            <div className="absolute bottom-0 left-1/3 w-48 h-48 bg-blue-100 rounded-full blur-[60px] opacity-40"></div>
            <div className="relative z-10 flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
                <div className="flex items-center gap-5">
                    <div className="relative">
                        <div className="w-16 h-16 rounded-2xl bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white text-2xl font-black shadow-lg shadow-indigo-200">
                            {user?.name?.charAt(0)?.toUpperCase() || 'U'}
                        </div>
                        <div className="absolute -bottom-1 -right-1 w-6 h-6 bg-emerald-500 rounded-full border-2 border-white flex items-center justify-center">
                            <CheckCircle size={12} className="text-white" />
                        </div>
                    </div>
                    <div>
                        <div className="flex items-center gap-3 mb-1">
                            <h1 className="text-2xl md:text-3xl font-black text-slate-800 tracking-tighter">
                                {greeting}, {user?.name?.split(' ')[0] || 'Utilisateur'}
                            </h1>
                        </div>
                        <div className="flex items-center gap-3">
                            <span className="text-xs font-bold text-indigo-600 bg-indigo-50 px-3 py-1 rounded-full border border-indigo-100">
                                {ROLE_LABELS[userRole] || userRole}
                            </span>
                            <span className="text-xs text-slate-400 capitalize">{today}</span>
                            <LiveTimestamp />
                        </div>
                    </div>
                </div>
                <button onClick={onRefresh} disabled={loading} className="p-3 bg-slate-100 hover:bg-slate-200 rounded-xl text-slate-500 hover:text-slate-700 transition-all disabled:opacity-40">
                    <RefreshCw size={16} className={loading ? 'animate-spin' : ''} />
                </button>
            </div>
        </section>
    );
});

const AnimatedStatValue = ({ value, duration = 800 }) => {
    const numValue = typeof value === 'number' ? value : (typeof value === 'string' ? parseInt(value, 10) || 0 : 0);
    const animatedValue = useAnimatedNumber(numValue, duration);
    return animatedValue.toLocaleString('fr-FR');
};

const ACCENT_COLORS = {
    indigo: 'from-indigo-500 to-purple-600',
    emerald: 'from-emerald-500 to-teal-600',
    blue: 'from-blue-500 to-cyan-600',
    amber: 'from-amber-500 to-orange-500',
    rose: 'from-rose-500 to-pink-600',
    purple: 'from-purple-500 to-pink-600',
};

const ICON_BG = {
    indigo: 'bg-indigo-50',
    emerald: 'bg-emerald-50',
    blue: 'bg-blue-50',
    amber: 'bg-amber-50',
    rose: 'bg-rose-50',
    purple: 'bg-purple-50',
};

const ICON_TEXT = {
    indigo: 'text-indigo-600',
    emerald: 'text-emerald-600',
    blue: 'text-blue-600',
    amber: 'text-amber-500',
    rose: 'text-rose-600',
    purple: 'text-purple-600',
};

const StatCard = memo(({ title, value, icon, accent = 'indigo', subValue }) => {
    const tiltRef = useTilt(3);
    return (
        <div ref={tiltRef}
            className="relative overflow-hidden bg-white rounded-2xl p-5 border border-slate-100 shadow-sm hover:shadow-md transition-all duration-300 group"
        >
            <div className={`absolute left-0 top-0 bottom-0 w-1 bg-gradient-to-b ${ACCENT_COLORS[accent]} rounded-full`} />
            <div className="flex items-start justify-between">
                <div className="space-y-1.5">
                    <p className="text-xs font-semibold text-slate-400 uppercase tracking-wider">{title}</p>
                    <p className="text-3xl font-black text-slate-800">
                        <AnimatedStatValue value={value} />
                    </p>
                    {subValue && (
                        <p className="text-xs font-semibold text-slate-400">{subValue}</p>
                    )}
                </div>
                <div className={`w-12 h-12 rounded-2xl ${ICON_BG[accent]} flex items-center justify-center`}>
                     {createElement(icon, { size: 22, className: ICON_TEXT[accent] })}
                </div>
            </div>
        </div>
    );
});

const SectionCard = memo(({ title, subtitle, children }) => (
    <div className="bg-white rounded-2xl p-6 md:p-8 border border-slate-100 shadow-sm">
        <div className="flex justify-between items-start mb-6">
            <div>
                <h2 className="text-lg font-bold text-slate-800 tracking-tight">{title}</h2>
                {subtitle && <p className="text-xs font-semibold text-slate-400 uppercase tracking-wider mt-1">{subtitle}</p>}
            </div>
        </div>
        {children}
    </div>
));

const MiniStat = memo(({ label, value, color = 'text-slate-800' }) => (
    <div className="flex items-center justify-between py-3 border-b border-slate-100 last:border-0">
        <span className="text-xs font-semibold text-slate-400">{label}</span>
        <span className={`text-sm font-black ${color}`}>{value}</span>
    </div>
));

const Dashboard = () => {
    const user = useAuthStore((state) => state.user);
    const [stats, setStats] = useState(null);
    const [loading, setLoading] = useState(true);
    const abortControllerRef = useRef(null);

    const userRole = user ? getPrimaryRole(user) : null;

    const formatCurrency = (value) => `${Number(value ?? 0).toLocaleString('fr-FR')} XOF`;

    const getRolePath = (role) => {
        const roleMap = {
            admin: 'admin',
            hr_manager: 'hr',
             'hr',
            accountant: 'accountant',
            academic_manager: 'academic',
            teacher: 'teacher'
        };
        return roleMap[role] || 'teacher';
    };

    // Fonction pour charger les données avec gestion robuste
    const loadDashboardData = useCallback(async () => {
        // Vérifier que l'utilisateur est prêt
        if (!user?.id) {
            setLoading(false);
            return;
        }

        if (!userRole) {
            setLoading(false);
            return;
        }

        // Annuler les anciennes requêtes
        if (abortControllerRef.current) {
            abortControllerRef.current.abort();
        }

        abortControllerRef.current = new AbortController();
        const endpoint = `/dashboards/${getRolePath(userRole)}`;

        setLoading(true);
        let retries = 0;
        const maxRetries = 2;

        const attempt = async () => {
            try {
                const response = await api.get(endpoint, {
                    signal: abortControllerRef.current.signal
                });

                if (response.data && Object.keys(response.data).length > 0) {
                    // Force immediate render
                    setLoading(false);
                    setStats(response.data);
                } else {
                    throw new Error('Empty response data');
                }
            } catch (error) {
                // Si la requête a été annulée, ne pas réessayer
                if (error.name === 'AbortError') {
                    return;
                }

                if (retries < maxRetries) {
                    retries++;
                    await new Promise(r => setTimeout(r, 500));
                    await attempt();
                } else {
                    setStats(null);
                    setLoading(false);
                    toast.error('Impossible de charger les données. Veuillez réessayer.');
                }
            }
        };

        await attempt();
    }, [user?.id, userRole]);

    // Charger les données au montage et quand l'utilisateur change
    useEffect(() => {
        loadDashboardData();

        return () => {
            if (abortControllerRef.current) {
                abortControllerRef.current.abort();
            }
        };
    }, [user?.id, userRole]); // N'inclure que les vraies dépendances

    // Subscribe to auto-refresh after mutations
    useEffect(() => {
        const unsubscribe = dataRefresh.subscribe('dashboard', () => loadDashboardData());
        return unsubscribe;
    }, [loadDashboardData]);

    const handleRefresh = () => {
        loadDashboardData();
    };

    const renderBar = (val, max, color) => (
        <div className="w-full bg-slate-100 h-2 rounded-full overflow-hidden">
            <div className={`h-full rounded-full ${color} transition-all duration-700`} style={{ width: max > 0 ? `${Math.min((val / max) * 100, 100)}%` : '0%' }}></div>
        </div>
    );

    const handleDownloadPayslip = async () => {
        try {
            if (!stats?.last_payslip?.id) return;
            const response = await api.get(`/payroll/${stats.last_payslip.id}`);
            const data = response.data;
            const monthNames = MONTH_NAMES;
            const esc = escapeHtml;
            const html = `<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Honoraire ${esc(monthNames[data.month])}/${esc(String(data.year))}</title>
            <style>@media print{body{margin:0;}@page{size:A4;margin:15mm;}}body{font-family:system-ui,sans-serif;padding:40px;max-width:700px;margin:0 auto;}
            .h{border-bottom:3px solid #4f46e5;padding-bottom:16px;margin-bottom:20px;}.h h1{font-size:20px;color:#4f46e5;}
            table{width:100%;border-collapse:collapse;margin:12px 0;}th{background:#f1f5f9;padding:8px;text-align:left;font-size:12px;}
            td{padding:8px;border-bottom:1px solid #e2e8f0;font-size:13px;}.r{text-align:right;}.net{font-size:18px;font-weight:800;color:#4f46e5;text-align:right;margin-top:16px;}
            .btn{margin-top:24px;text-align:center;}button{padding:10px 24px;border:none;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;}
            @media print{.btn{display:none;}}</style></head><body>
            <div class="h"><h1>Université Dakar Bourguiba</h1><p style="color:#64748b;font-size:12px;">État d'Honoraire - ${esc(monthNames[data.month])}/${esc(String(data.year))}</p></div>
            <p><strong>Enseignant:</strong> ${esc(data.teacher?.user?.name || 'N/A')} | <strong>Matricule:</strong> ${esc(data.teacher?.employee_id || 'N/A')}</p>
            <table><thead><tr><th>Description</th><th class="r">Montant</th></tr></thead><tbody>
            ${(data.details||[]).map(d=>'<tr><td>'+esc(d.description)+'</td><td class="r" style="color:'+(d.type==='earning'?'#16a34a':'#dc2626')+'">'+(d.type==='deduction'?'-':'')+esc(Number(d.amount).toLocaleString('fr-FR',{minimumFractionDigits:2}))+' F</td></tr>').join('')}
            </tbody></table>
            <div style="display:flex;justify-content:space-between;font-size:13px;margin-top:12px;"><span>Brut: ${esc(Number(data.gross_salary || 0).toLocaleString('fr-FR',{minimumFractionDigits:2}))} F</span><span>Retenues: ${esc(Number(data.deductions || 0).toLocaleString('fr-FR',{minimumFractionDigits:2}))} F</span></div>
            <div class="net">Net à payer: ${esc(Number(data.net_salary || 0).toLocaleString('fr-FR',{minimumFractionDigits:2}))} F</div>
            <div class="btn"><button onclick="window.print()">Imprimer / PDF</button> <button onclick="window.close()">Fermer</button></div>
            </body></html>`;
            const w = window.open('', '_blank');
            if (w) { w.document.write(html); w.document.close(); toast.success('Honoraire ouvert'); }
        } catch { toast.error("Erreur de téléchargement de l'honoraire"); }
    };

    // Afficher le chargement
    if (loading) return (
        <div className="space-y-6 animate-fadeIn">
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                {[...Array(4)].map((_, i) => <SkeletonCard key={i} />)}
            </div>
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <SkeletonChart /><SkeletonChart />
            </div>
        </div>
    );

    // Afficher erreur si pas de données
    if (!stats) {
        return (
            <div className="bg-white rounded-2xl p-8 border border-rose-100 shadow-sm text-center space-y-4">
                <AlertCircle size={32} className="text-rose-500 mx-auto" />
                <h2 className="font-bold text-slate-800 text-lg">Impossible de charger les données</h2>
                <p className="text-sm text-slate-500">Une erreur s'est produite lors du chargement de vos statistiques.</p>
                <button
                    onClick={handleRefresh}
                    className="px-6 py-2 bg-indigo-500 text-white rounded-lg hover:bg-indigo-600 transition-all font-semibold"
                >
                    Réessayer
                </button>
            </div>
        );
    }

    // ADMIN
    if (userRole === 'admin') {
        return (
            <div className="space-y-6 animate-fadeIn">
                <WelcomeHero user={user} userRole={userRole} onRefresh={handleRefresh} loading={loading} />
                <QuickActions role={userRole} />
                <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 md:gap-5">
                    <StatCard title="Utilisateurs" value={stats.total_users ?? 0} icon={Users} accent="indigo" subValue={`${stats.active_users ?? 0} actifs`} />
                    <StatCard title="Enseignants" value={stats.total_teachers ?? 0} icon={GraduationCap} accent="emerald" subValue={`${stats.active_teachers ?? 0} actifs`} />
                    <StatCard title="Présents" value={stats.today_present ?? 0} icon={UserCheck} accent="blue" subValue={`${stats.today_total ?? 0} pointages`} />
                    <StatCard title="Absences" value={stats.month_absences ?? 0} icon={FileText} accent="amber" subValue={`${stats.month_absences ?? 0} ce mois`} />
                </div>
                <SectionCard title="Vue d'Ensemble" subtitle="Indicateurs clés du système">
                    <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                        {[
                            { label: 'Utilisateurs', val: stats.total_users ?? 0, sub: `${stats.active_users ?? 0} actifs · ${stats.inactive_users ?? 0} inactifs`, barVal: stats.active_users ?? 0, barMax: stats.total_users || 1, barColor: 'bg-emerald-500' },
                            { label: 'Enseignants', val: stats.total_teachers ?? 0, sub: `${stats.active_teachers ?? 0} actifs · ${stats.teachers_on_leave ?? 0} congé`, barVal: stats.active_teachers ?? 0, barMax: stats.total_teachers || 1, barColor: 'bg-emerald-500' },
                            { label: 'Présents ajd', val: stats.today_present ?? 0, sub: `${stats.today_late ?? 0} retards · ${stats.today_absent ?? 0} absents`, barVal: stats.today_present ?? 0, barMax: stats.today_total || 1, barColor: 'bg-emerald-500' },
                            { label: 'Absences mois', val: stats.month_absences ?? 0, sub: `${stats.approved_leaves ?? 0} approuvés · ${stats.rejected_leaves ?? 0} refusés`, barVal: stats.month_attendance_rate ?? 0, barMax: 100, barColor: 'bg-amber-500' },
                        ].map((d, i) => (
                            <div key={i} className="bg-slate-50 rounded-2xl p-5 text-center border border-slate-100">
                                <p className="text-2xl font-black text-slate-800">{d.val}</p>
                                <p className="text-xs font-bold text-slate-400 uppercase tracking-wider mt-1">{d.label}</p>
                                <p className="text-xs text-slate-400 mt-1">{d.sub}</p>
                                <div className="mt-2">{renderBar(d.barVal, d.barMax, d.barColor)}</div>
                            </div>
                        ))}
                    </div>
                    {stats.charts?.attendance && (
                        <SimpleChart title="Tendance Présence (7 jours)" data={(stats.charts.attendance || []).map(d => ({ label: d.day, value: d.present ?? d.count ?? 0 }))} color="bg-indigo-500" />
                    )}
                    {stats.recent_teachers?.length > 0 && (
                        <div className="mt-6">
                            <h3 className="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4">Derniers Enseignants</h3>
                            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                                {stats.recent_teachers.slice(0, 6).map((t) => (
                                    <div key={`teacher-${t.id}`} className="flex items-center gap-3 bg-slate-50 hover:bg-slate-100 rounded-xl p-3 transition-all border border-transparent hover:border-slate-200">
                                        <div className="w-10 h-10 rounded-xl bg-indigo-100 text-indigo-600 flex items-center justify-center font-black text-xs border border-indigo-200">
                                            {t.user?.name?.charAt(0) || '?'}
                                        </div>
                                        <div className="flex-1 min-w-0">
                                            <p className="text-sm font-bold text-slate-700 truncate">{t.user?.name || 'N/A'}</p>
                                            <p className="text-xs text-slate-400 font-semibold uppercase">Mat. {t.employee_id || 'N/A'}</p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}
                </SectionCard>
            </div>
        );
    }

    // RH / SECRETAIRE
    if (userRole === 'hr_manager' || userRole === ) {
        return (
            <div className="space-y-6 animate-fadeIn">
                <WelcomeHero user={user} userRole={userRole} onRefresh={handleRefresh} loading={loading} />
                <QuickActions role={userRole} />
                <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 md:gap-5">
                    <StatCard title="Enseignants" value={stats.total_teachers ?? 0} icon={Users} accent="emerald" subValue={`${stats.active_teachers ?? 0} actifs`} />
                    <StatCard title="En congé" value={stats.teachers_on_leave ?? 0} icon={Calendar} accent="blue" subValue="Absents" />
                    <StatCard title="Présents" value={stats.today_present ?? 0} icon={CheckCircle} accent="indigo" subValue={`${stats.today_total ?? 0} pointages`} />
                    <StatCard title="Taux présence" value={`${stats.month_attendance_rate ?? 0}%`} icon={Target} accent="purple" subValue="Ce mois" />
                </div>
                <SectionCard title="Suivi des Présences" subtitle="Absentéisme et données du jour">
                    <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        {stats.charts?.absences && (
                            <div className="lg:col-span-2">
                                <SimpleChart title="Absentéisme (7 jours)" data={(stats.charts.absences || []).map(d => ({ label: d.label, value: d.total ?? d.value ?? 0 }))} color="bg-rose-500" />
                            </div>
                        )}
                        <div className="space-y-4">
                            <div className="bg-slate-50 rounded-2xl p-5 border border-slate-100">
                                <h3 className="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4">Aujourd'hui</h3>
                                <div className="space-y-3">
                                    <div className="flex justify-between items-center p-3 bg-white rounded-xl border border-slate-100">
                                        <span className="text-xs font-bold text-emerald-600">Présents</span>
                                        <span className="text-lg font-black text-emerald-600">{stats.today_present ?? 0}</span>
                                    </div>
                                    <div className="flex justify-between items-center p-3 bg-white rounded-xl border border-slate-100">
                                        <span className="text-xs font-bold text-amber-500">Retards</span>
                                        <span className="text-lg font-black text-amber-500">{stats.today_late ?? 0}</span>
                                    </div>
                                    <div className="flex justify-between items-center p-3 bg-white rounded-xl border border-slate-100">
                                        <span className="text-xs font-bold text-rose-500">Absents</span>
                                        <span className="text-lg font-black text-rose-500">{stats.today_absent ?? 0}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </SectionCard>
                <SectionCard title="Enseignants qui ont pointé" subtitle="Liste du jour">
                    {stats.recent_attendances && stats.recent_attendances.length > 0 ? (
                        <div className="space-y-2">
                            {stats.recent_attendances.map((att) => (
                                <div key={att.id} className="flex items-center justify-between p-3 bg-slate-50 rounded-lg border border-slate-100 hover:border-slate-200 transition-all">
                                    <div className="flex items-center gap-3 flex-1">
                                        <div className={`w-2 h-2 rounded-full ${att.status === 'present' ? 'bg-emerald-500' : att.status === 'late' ? 'bg-amber-500' : 'bg-rose-500'}`} />
                                        <div className="flex-1">
                                            <p className="text-sm font-semibold text-slate-700">{att.teacher?.user?.name || 'N/A'}</p>
                                            <p className="text-xs text-slate-400">{att.check_in_time && att.check_in_time.slice(0, 5)} - {att.check_out_time && att.check_out_time.slice(0, 5)}</p>
                                        </div>
                                    </div>
                                    <span className={`text-xs font-bold px-2 py-1 rounded-full ${att.status === 'present' ? 'bg-emerald-100 text-emerald-700' : att.status === 'late' ? 'bg-amber-100 text-amber-700' : 'bg-rose-100 text-rose-700'}`}>
                                        {att.status === 'present' ? 'Présent' : att.status === 'late' ? 'Retard' : 'Absent'}
                                    </span>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <div className="text-center py-8 text-slate-400">
                            <p className="text-sm">Aucun pointage enregistré aujourd'hui</p>
                        </div>
                    )}
                </SectionCard>
            </div>
        );
    }

    // COMPTABLE
    if (userRole === 'accountant') {
        return (
            <div className="space-y-6 animate-fadeIn">
                <WelcomeHero user={user} userRole={userRole} onRefresh={handleRefresh} loading={loading} />
                <QuickActions role={userRole} />
                <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 md:gap-5">
                    <StatCard title="Enseignants" value={stats.total_teachers ?? 0} icon={Users} accent="purple" subValue={`${stats.active_teachers ?? 0} actifs`} />
                    <StatCard title="Masse salariale" value={formatCurrency(stats.total_gross_salary ?? 0)} icon={Wallet} accent="emerald" />
                    <StatCard title="Honoraires" value={stats.payrolls_total ?? 0} icon={FileText} accent="blue" subValue="Ce mois" />
                    <StatCard title="Payés" value={stats.payrolls_paid ?? 0} icon={Shield} accent="amber" subValue="Effectués" />
                </div>
                <SectionCard title="Résumé Financier" subtitle="Masse salariale et paiements">
                    {stats.charts?.payroll_gross && (
                        <SimpleChart title="Masse Salariale (6 derniers mois)" data={(stats.charts.payroll_gross || []).map(d => ({ label: d.label, value: d.value ?? 0 }))} color="bg-indigo-600" />
                    )}
                </SectionCard>
            </div>
        );
    }

    // CHEF UFR
    if (userRole === 'academic_manager') {
        return (
            <div className="space-y-6 animate-fadeIn">
                <WelcomeHero user={user} userRole={userRole} onRefresh={handleRefresh} loading={loading} />
                <QuickActions role={userRole} />
                <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 md:gap-5">
                    <StatCard title="Matières" value={stats.total_subjects ?? 0} icon={GraduationCap} accent="purple" />
                    <StatCard title="Salles" value={stats.total_classrooms ?? 0} icon={MapPin} accent="blue" />
                    <StatCard title="Cours cette semaine" value={stats.schedules_this_week ?? 0} icon={Calendar} accent="indigo" />
                    <StatCard title="Devoirs" value={stats.active_homeworks ?? 0} icon={BookOpen} accent="amber" />
                </div>
                <SectionCard title="Planning Académique" subtitle="Cours et ressources">
                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div className="bg-slate-50 rounded-2xl p-5 border border-slate-100">
                            <h3 className="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4">Enseignants de l'UFR</h3>
                            <p className="text-3xl font-black text-slate-800">{stats.active_teachers ?? 0}</p>
                            <p className="text-xs text-slate-400 mt-2">{stats.total_teachers ?? 0} total dans la faculté</p>
                        </div>
                        <div className="bg-slate-50 rounded-2xl p-5 border border-slate-100">
                            <h3 className="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4">Présence cette semaine</h3>
                            <p className="text-3xl font-black text-emerald-600">{stats.attendance_rate ?? 0}%</p>
                            <p className="text-xs text-slate-400 mt-2">Taux de présence moyen</p>
                        </div>
                    </div>
                </SectionCard>
            </div>
        );
    }

    // ENSEIGNANT (Prof)
    const attMonth = stats.my_attendance_month || {};
    const attTotal = (attMonth.present ?? 0) + (attMonth.absent ?? 0) + (attMonth.late ?? 0);
    const attRate = attTotal > 0 ? Math.round(((attMonth.present ?? 0) / attTotal) * 100) : 0;
    const contractProgress = stats.contract_progress || { worked_hours: 0, total_hours: 0, percentage: 0 };
    const nextCourse = stats.next_course;

    return (
        <div className="space-y-6 animate-fadeIn">
            <WelcomeHero user={user} userRole={userRole} onRefresh={handleRefresh} loading={loading} />
            <QuickActions role={userRole} />

            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-5">
                <div className="relative overflow-hidden bg-white rounded-2xl p-5 border border-slate-100 shadow-sm hover:shadow-md transition-all group">
                    <div className="absolute left-0 top-0 bottom-0 w-1 bg-gradient-to-b from-indigo-500 to-purple-600 rounded-full" />
                    <div className="flex items-start justify-between mb-3">
                        <p className="text-xs font-semibold text-slate-400 uppercase tracking-wider">Progression Contrat</p>
                        <div className="w-10 h-10 rounded-xl bg-indigo-50 flex items-center justify-center">
                            <Target size={20} className="text-indigo-600" />
                        </div>
                    </div>
                    <p className="text-3xl font-black text-slate-800">{contractProgress.percentage}%</p>
                    <p className="text-xs text-slate-400 mt-1">{contractProgress.worked_hours}h / {contractProgress.total_hours}h</p>
                    <div className="mt-3 w-full bg-slate-100 h-2.5 rounded-full overflow-hidden">
                        <div className="h-full bg-gradient-to-r from-indigo-500 to-purple-600 rounded-full transition-all duration-1000"
                            style={{ width: `${contractProgress.percentage}%` }}></div>
                    </div>
                </div>

                <div className="relative overflow-hidden bg-white rounded-2xl p-5 border border-slate-100 shadow-sm hover:shadow-md transition-all group">
                    <div className="absolute left-0 top-0 bottom-0 w-1 bg-gradient-to-b from-emerald-500 to-teal-600 rounded-full" />
                    <div className="flex items-start justify-between mb-3">
                        <p className="text-xs font-semibold text-slate-400 uppercase tracking-wider">Honoraires Estimés</p>
                        <div className="w-10 h-10 rounded-xl bg-emerald-50 flex items-center justify-center">
                            <Wallet size={20} className="text-emerald-600" />
                        </div>
                    </div>
                    <p className="text-2xl font-black text-emerald-600">{formatCurrency(stats.estimated_honoraria ?? 0)}</p>
                    <p className="text-xs text-slate-400 mt-1">{contractProgress.worked_hours}h travaillées</p>
                </div>

                <div className="relative overflow-hidden bg-white rounded-2xl p-5 border border-slate-100 shadow-sm hover:shadow-md transition-all group">
                    <div className="absolute left-0 top-0 bottom-0 w-1 bg-gradient-to-b from-blue-500 to-cyan-600 rounded-full" />
                    <div className="flex items-start justify-between mb-3">
                        <p className="text-xs font-semibold text-slate-400 uppercase tracking-wider">Prochain Cours</p>
                        <div className="w-10 h-10 rounded-xl bg-blue-50 flex items-center justify-center">
                            <Clock size={20} className="text-blue-600" />
                        </div>
                    </div>
                    {nextCourse ? (
                        <>
                            <p className="text-lg font-black text-slate-800 truncate">{nextCourse.subject_name}</p>
                            <p className="text-xs text-slate-500 mt-2">Salle {nextCourse.classroom_name}</p>
                            <p className="text-[10px] font-bold text-slate-400 uppercase mt-2">{nextCourse.start_time?.substring(11, 16)}</p>
                        </>
                    ) : (
                        <p className="text-lg font-black text-slate-300">Aucun cours</p>
                    )}
                </div>

                <StatCard title="Présences (Mois)" value={attMonth.present ?? 0} icon={CheckCircle} accent="purple" subValue={`${attRate}% taux`} />
            </div>

            <SectionCard title="Mes Cours & Honoraires" subtitle="Planning et rémunération">
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    {stats.my_schedules?.length > 0 ? (
                        <div className="bg-slate-50 rounded-2xl p-5 border border-slate-100">
                            <h3 className="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4">Mes Cours</h3>
                            <div className="space-y-3">
                                {stats.my_schedules.slice(0, 5).map((s) => (
                                    <div key={`schedule-${s.id}`} className="flex items-center gap-4 bg-white rounded-xl p-3 border border-slate-100">
                                        <div className="text-sm font-black text-slate-600">{s.start_time?.substring(0, 5)}</div>
                                        <div className="flex-1 min-w-0">
                                            <p className="text-sm font-bold text-slate-700 truncate">{s.subject?.name || 'Matière'}</p>
                                            <p className="text-xs text-slate-400">Salle {s.classroom?.name || '—'}</p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    ) : (
                        <div className="bg-slate-50 rounded-2xl p-5 border border-slate-100 flex items-center justify-center">
                            <p className="text-slate-400 text-sm">Aucun cours programmé</p>
                        </div>
                    )}

                    <div className="bg-slate-50 rounded-2xl p-5 border border-slate-100">
                        <h3 className="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4">Présence ce Mois</h3>
                        <div className="grid grid-cols-3 gap-3">
                            <div className="text-center p-3 bg-emerald-50 rounded-xl border border-emerald-100">
                                <p className="text-xl font-black text-emerald-600">{attMonth.present ?? 0}</p>
                                <p className="text-[10px] font-bold text-emerald-500 uppercase">Présent</p>
                            </div>
                            <div className="text-center p-3 bg-amber-50 rounded-xl border border-amber-100">
                                <p className="text-xl font-black text-amber-500">{attMonth.late ?? 0}</p>
                                <p className="text-[10px] font-bold text-amber-400 uppercase">Retard</p>
                            </div>
                            <div className="text-center p-3 bg-rose-50 rounded-xl border border-rose-100">
                                <p className="text-xl font-black text-rose-500">{attMonth.absent ?? 0}</p>
                                <p className="text-[10px] font-bold text-rose-400 uppercase">Absent</p>
                            </div>
                        </div>
                        <div className="mt-4">
                            <div className="flex-1 bg-slate-200 h-2 rounded-full overflow-hidden">
                                <div className="bg-emerald-500 h-full rounded-full" style={{ width: `${attRate}%` }}></div>
                            </div>
                            <p className="text-xs font-black text-emerald-600 mt-2 text-center">{attRate}% de présence</p>
                        </div>
                    </div>
                </div>
            </SectionCard>
        </div>
    );
};

export default Dashboard;

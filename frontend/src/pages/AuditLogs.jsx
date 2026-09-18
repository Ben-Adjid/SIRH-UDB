import { useEffect, useState } from 'react';
import api from '../services/api';
import { Shield, Clock, User, Activity, Search, Trash2, ShieldAlert } from 'lucide-react';
import toast from '../utils/toast';
import ConfirmModal from '../components/ConfirmModal';
import Pagination from '../components/Pagination';

const getActionColor = (action) => {
    if (!action) return 'bg-gray-50 text-gray-600 border-gray-100';
    const a = action.toUpperCase();
    if (a.includes('CREATED') || a.includes('ADDED')) return 'bg-emerald-50 text-emerald-600 border-emerald-100';
    if (a.includes('DELETED') || a.includes('REMOVED')) return 'bg-red-50 text-red-500 border-red-100';
    if (a.includes('UPDATED') || a.includes('MODIFIED')) return 'bg-blue-50 text-blue-600 border-blue-100';
    if (a.includes('LOGIN') || a.includes('AUTH')) return 'bg-purple-50 text-purple-600 border-purple-100';
    return 'bg-gray-50 text-gray-600 border-gray-100';
};

const getActionIcon = (action) => {
    if (!action) return Activity;
    const a = action.toUpperCase();
    if (a.includes('CREATED') || a.includes('ADDED')) return 'bg-emerald-500';
    if (a.includes('DELETED') || a.includes('REMOVED')) return 'bg-red-500';
    if (a.includes('UPDATED') || a.includes('MODIFIED')) return 'bg-blue-500';
    return 'bg-slate-500';
};

const AVATAR_GRADIENTS = [
    'from-gray-700 to-gray-900', 'from-blue-500 to-indigo-600', 'from-purple-500 to-pink-600',
    'from-emerald-500 to-teal-600', 'from-amber-500 to-orange-600'
];

const AuditLogs = () => {
    const [logs, setLogs] = useState([]);
    const [loading, setLoading] = useState(true);
    const [searchTerm, setSearchTerm] = useState('');
    const [confirmClear, setConfirmClear] = useState(false);
    const [page, setPage] = useState(1);
    const perPage = 12;
    const [pagination, setPagination] = useState({ current_page: 1, last_page: 1, total: 0 });

    const fetchLogs = async (pageNum = 1) => {
        setLoading(true);
        try {
            const params = { page: pageNum, per_page: perPage };
            if (searchTerm) params.search = searchTerm;
            const response = await api.get('/auth/audit-logs', { params });
            const data = response.data;
            setLogs(data.data || []);
            setPagination({ current_page: data.current_page, last_page: data.last_page, total: data.total });
        } catch {
            toast.error('Erreur de lecture du journal');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchLogs(page);
    // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [page, searchTerm]);

    useEffect(() => { setPage(1); }, [searchTerm]);

    const clearLogs = async () => {
        try {
            await api.delete('/auth/audit-logs');
            toast.success('Journal purge');
            fetchLogs(page);
        } catch {
            toast.error('Action refusee');
        }
    };

    return (
        <div className="min-h-screen">
            <div className="max-w-[1400px] mx-auto space-y-6 px-2 sm:px-4">
                <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 pt-2">
                    <div>
                        <div className="flex items-center gap-3 mb-1">
                            <div className="w-10 h-10 rounded-xl bg-gradient-to-br from-slate-700 to-slate-900 flex items-center justify-center shadow-lg shadow-slate-500/25">
                                <Shield size={20} className="text-white" />
                            </div>
                            <h1 className="text-2xl font-bold text-slate-900 tracking-tight">Journal de Securite</h1>
                        </div>
                        <p className="text-slate-400 text-sm ml-[52px]">Tracabilite & Integrite des donnees</p>
                    </div>
                    <button onClick={() => setConfirmClear(true)}
                        className="flex items-center gap-2 px-5 py-2.5 bg-red-50 text-red-600 rounded-xl font-semibold text-sm hover:bg-red-600 hover:text-white transition-all duration-300 active:scale-[0.97] shadow-sm border border-red-100">
                        <Trash2 size={18} />
                        <span>Purger le Journal</span>
                    </button>
                </div>

                <div className="bg-white rounded-2xl border border-slate-100 p-3">
                    <div className="relative flex-1">
                        <Search className="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-300" size={16} />
                        <input type="text" placeholder="Analyser une action, un utilisateur, une entite..."
                            className="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-100 rounded-xl text-sm text-slate-700 placeholder:text-slate-300 focus:outline-none focus:border-slate-400 focus:ring-2 focus:ring-slate-500/10 transition-all"
                            value={searchTerm} onChange={(e) => setSearchTerm(e.target.value)} />
                    </div>
                </div>

                {loading ? (
                    <div className="flex justify-center py-20">
                        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-slate-600" />
                    </div>
                ) : logs.length === 0 ? (
                    <div className="flex flex-col items-center justify-center py-24">
                        <div className="w-16 h-16 bg-slate-100 rounded-2xl flex items-center justify-center mb-4">
                            <ShieldAlert size={28} className="text-slate-300" />
                        </div>
                        <p className="text-base font-bold text-slate-400 mb-1">Journal vierge</p>
                        <p className="text-xs text-slate-300">{searchTerm ? 'Aucun resultat' : 'Aucun evenement enregistre'}</p>
                    </div>
                ) : (
                    <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 mb-6">
                        {logs.map((l) => {
                            const actionColor = getActionColor(l.action);
                            const dotColor = getActionIcon(l.action);
                            const avatarIdx = l.id % AVATAR_GRADIENTS.length;

                            return (
                                <div key={l.id}
                                    className="group relative bg-white rounded-2xl p-5 border border-slate-100 shadow-sm hover:shadow-lg hover:border-slate-200 transition-all duration-300">
                                    <div className="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-slate-400 to-slate-600 opacity-0 group-hover:opacity-100 transition-opacity rounded-t-2xl" />

                                    <div className="flex items-start justify-between mb-3">
                                        <div className="flex items-center gap-3">
                                            <div className={`flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br ${AVATAR_GRADIENTS[avatarIdx]} text-white font-bold text-sm shadow-md`}>
                                                {l.user?.name?.charAt(0) || 'S'}
                                            </div>
                                            <div className="min-w-0">
                                                <h3 className="font-bold text-slate-900 text-sm truncate">{l.user?.name || 'Systeme'}</h3>
                                                <p className="text-[10px] text-slate-400 flex items-center gap-1">
                                                    <Clock size={10} />
                                                    {l.created_at ? new Date(l.created_at).toLocaleString() : 'Inconnu'}
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    <div className="flex flex-wrap gap-1.5 mb-3">
                                        <span className={`inline-flex items-center px-2.5 py-1 rounded-lg text-[10px] font-black uppercase tracking-wider border ${actionColor}`}>
                                            <span className={`w-2 h-2 rounded-full ${dotColor} mr-1.5`}></span>
                                            {l.action || 'ACTION'}
                                        </span>
                                        <span className="inline-flex items-center px-2.5 py-1 rounded-lg text-[10px] font-black uppercase tracking-wider border bg-slate-50 text-slate-600 border-slate-200">
                                            <Activity size={10} className="mr-1" />
                                             {l.model?.split('\\').pop() || 'N/A'}
                                        </span>
                                    </div>

                                    <div className="bg-slate-50 rounded-xl p-3 border border-slate-100">
                                        <p className="text-[10px] font-mono text-slate-400 truncate">ID: {l.model_id || 'N/A'}</p>
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

                <div className="p-6 bg-slate-900 rounded-2xl text-center">
                    <p className="text-[10px] font-bold text-white/40 uppercase tracking-[0.4em]">
                        UDB Security Protocol • Hash Verification Active
                    </p>
                </div>
            </div>

            <ConfirmModal
                isOpen={confirmClear}
                onClose={() => setConfirmClear(false)}
                onConfirm={() => { setConfirmClear(false); clearLogs(); }}
                title="Purger le journal d'audit"
                message="Action critique : Voulez-vous vraiment effacer tout le journal d'audit ? Cette action est irreversible."
                confirmText="Tout purger"
                type="danger"
            />
        </div>
    );
};

export default AuditLogs;

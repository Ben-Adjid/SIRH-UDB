import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import api from '../services/api';
import { Bell, Check, CheckCheck, Trash2, Filter, Clock, AlertCircle, Info, XCircle, ChevronLeft, ChevronRight } from 'lucide-react';
import toast from '../utils/toast';
import ConfirmModal from '../components/ConfirmModal';
import echo from '../services/echo';
import useAuthStore from '../store/authStore';

const TYPE_CONFIG = {
    info: { bg: 'bg-blue-50', text: 'text-blue-600', border: 'border-blue-200', icon: Info, label: 'Info' },
    success: { bg: 'bg-emerald-50', text: 'text-emerald-600', border: 'border-emerald-200', icon: CheckCheck, label: 'Succès' },
    warning: { bg: 'bg-amber-50', text: 'text-amber-600', border: 'border-amber-200', icon: AlertCircle, label: 'Attention' },
    danger: { bg: 'bg-red-50', text: 'text-red-600', border: 'border-red-200', icon: XCircle, label: 'Alerte' },
};

const Notifications = () => {
    const navigate = useNavigate();
    const user = useAuthStore((state) => state.user);
    const [notifications, setNotifications] = useState([]);
    const [loading, setLoading] = useState(true);
    const [unreadCount, setUnreadCount] = useState(0);
    const [filter, setFilter] = useState('all');
    const [currentPage, setCurrentPage] = useState(1);
    const [confirmDelete, setConfirmDelete] = useState({ isOpen: false, id: null });
    const perPage = 20;

    const fetchNotifications = async (showLoading = false) => {
        if (showLoading) setLoading(true);
        try {
            const response = await api.get('/notifications', {
                params: { unread_only: filter === 'unread' ? 1 : 0, limit: 200 }
            });
            setNotifications(response.data.notifications || []);
            setUnreadCount(response.data.unread_count || 0);
        } catch {
            toast.error('Erreur de chargement');
        } finally {
            if (showLoading) setLoading(false);
        }
    };

    useEffect(() => {
        fetchNotifications(true);
        if (!user?.id) return undefined;
        const channel = echo.private(`App.Models.User.${user.id}`);
        channel.listen('.notification.created', (event) => {
            const notification = event.notification;
            setNotifications((previous) => [notification, ...previous.filter((item) => item.id !== notification.id)]);
            setUnreadCount((count) => count + (notification.read_at ? 0 : 1));
        });
        return () => echo.leave(`private-App.Models.User.${user.id}`);
    // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [filter, user?.id]);

    const markAsRead = async (id) => {
        try {
            await api.patch(`/notifications/${id}/read`);
            setNotifications(prev => prev.map(n => n.id === id ? { ...n, read_at: new Date().toISOString() } : n));
            setUnreadCount(prev => Math.max(0, prev - 1));
        } catch {
            toast.error('Erreur');
        }
    };

    const markAllRead = async () => {
        try {
            await api.post('/notifications/mark-all-read');
            setNotifications(prev => prev.map(n => ({ ...n, read_at: n.read_at || new Date().toISOString() })));
            setUnreadCount(0);
            toast.success('Toutes les notifications marquées comme lues');
        } catch {
            toast.error('Erreur');
        }
    };

    const deleteNotification = async (id) => {
        try {
            await api.delete(`/notifications/${id}`);
            const wasUnread = notifications.find(n => n.id === id && !n.read_at);
            setNotifications(prev => prev.filter(n => n.id !== id));
            if (wasUnread) setUnreadCount(prev => Math.max(0, prev - 1));
            toast.success('Notification supprimée');
        } catch {
            toast.error('Erreur de suppression');
        }
    };

    const handleNotificationClick = (n) => {
        if (!n.read_at) markAsRead(n.id);
        if (n.link) navigate(n.link);
    };

    const filteredNotifications = filter === 'unread'
        ? notifications.filter(n => !n.read_at)
        : notifications;

    const totalPages = Math.ceil(filteredNotifications.length / perPage);
    const paginatedNotifications = filteredNotifications.slice((currentPage - 1) * perPage, currentPage * perPage);

    useEffect(() => {
        setCurrentPage(p => Math.min(p, totalPages || 1));
    }, [totalPages]);

    useEffect(() => {
        setCurrentPage(1);
    }, [filter]);

    const formatDate = (dateStr) => {
        const d = new Date(dateStr);
        const now = new Date();
        const diff = now - d;
        if (diff < 60000) return "à l'instant";
        if (diff < 3600000) return `il y a ${Math.floor(diff / 60000)} min`;
        if (diff < 86400000) return `il y a ${Math.floor(diff / 3600000)}h`;
        if (diff < 604800000) return `il y a ${Math.floor(diff / 86400000)}j`;
        return d.toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' });
    };

    return (
        <div className="space-y-6 animate-fade-in">
            {/* Header */}
            <div className="glass p-8 rounded-[2rem] border border-white/60 shadow-2xl shadow-slate-200/30 text-center">
                <h1 className="text-4xl font-black text-gray-900 tracking-tighter">Notifications</h1>
                <p className="text-sm font-bold text-gray-500 mt-2 uppercase tracking-widest">
                    Centre d'alertes et de messages
                    <span className="ml-2 text-blue-600">| {unreadCount} non lue{unreadCount > 1 ? 's' : ''}</span>
                </p>
            </div>

            {/* Filters & Actions */}
            <div className="glass p-4 rounded-2xl border border-white/60 shadow-xl flex flex-wrap items-center justify-between gap-4">
                <div className="flex items-center gap-2">
                    <Filter size={16} className="text-gray-400" />
                    <button
                        onClick={() => setFilter('all')}
                        className={`px-4 py-2 rounded-xl text-xs font-black uppercase tracking-wider transition-all ${filter === 'all' ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-500 hover:bg-gray-200'}`}
                    >
                        Toutes ({notifications.length})
                    </button>
                    <button
                        onClick={() => setFilter('unread')}
                        className={`px-4 py-2 rounded-xl text-xs font-black uppercase tracking-wider transition-all ${filter === 'unread' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-500 hover:bg-gray-200'}`}
                    >
                        Non lues ({unreadCount})
                    </button>
                </div>
                <div className="flex items-center gap-2">
                    {unreadCount > 0 && (
                        <button
                            onClick={markAllRead}
                            className="flex items-center gap-2 px-4 py-2 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-xl text-xs font-black uppercase hover:bg-emerald-100 transition-colors"
                        >
                            <CheckCheck size={14} />
                            Tout lire
                        </button>
                    )}
                </div>
            </div>

            {/* Content */}
            {loading ? (
                <div className="text-center py-20">
                    <Clock size={48} className="mx-auto mb-4 text-gray-300 animate-spin" />
                    <p className="text-gray-500 font-bold">Chargement...</p>
                </div>
            ) : paginatedNotifications.length === 0 ? (
                <div className="text-center py-20 glass rounded-2xl border border-white/60 shadow-xl">
                    <Bell size={48} className="mx-auto mb-4 text-gray-300" />
                    <p className="text-gray-700 font-black text-lg">
                        {filter === 'unread' ? 'Tout est lu !' : 'Aucune notification'}
                    </p>
                    <p className="text-gray-400 text-sm mt-2">
                        {filter === 'unread' ? 'Vous avez lu toutes vos notifications.' : 'Vous n\'avez reçu aucune notification pour le moment.'}
                    </p>
                </div>
            ) : (
                <>
                    <div className="space-y-2">
                        {paginatedNotifications.map(n => {
                            const tc = TYPE_CONFIG[n.type] || TYPE_CONFIG.info;
                            const Icon = tc.icon;
                            const isUnread = !n.read_at;
                            return (
                                <div
                                    key={n.id}
                                    className={`glass rounded-2xl border transition-all overflow-hidden ${isUnread ? 'border-blue-200 shadow-lg shadow-blue-100/30 hover:shadow-blue-200/40' : 'border-white/60 shadow-xl hover:shadow-2xl'} ${n.link ? 'cursor-pointer' : ''}`}
                                    onClick={() => handleNotificationClick(n)}
                                >
                                    <div className={`flex items-start gap-4 p-5 ${isUnread ? 'bg-blue-50/30' : ''}`}>
                                        <div className={`flex-shrink-0 p-2.5 rounded-xl border ${tc.bg} ${tc.border}`}>
                                            <Icon size={18} className={tc.text} />
                                        </div>
                                        <div className="flex-1 min-w-0">
                                            <div className="flex items-start justify-between gap-3">
                                                <div>
                                                    <p className={`text-sm font-black ${isUnread ? 'text-gray-900' : 'text-gray-600'}`}>
                                                        {isUnread && <span className="inline-block w-2 h-2 rounded-full bg-blue-500 mr-2 animate-pulse" />}
                                                        {n.title}
                                                    </p>
                                                    <p className="text-xs text-gray-500 mt-1 leading-relaxed">{n.message}</p>
                                                </div>
                                                <div className="flex items-center gap-1 flex-shrink-0">
                                                    <span className="text-[10px] text-gray-400 font-mono whitespace-nowrap">{formatDate(n.created_at)}</span>
                                                </div>
                                            </div>
                                            <div className="flex items-center gap-3 mt-3">
                                                <span className={`inline-flex items-center gap-1 text-[10px] font-black px-2.5 py-0.5 rounded-full border uppercase ${tc.bg} ${tc.text} ${tc.border}`}>
                                                    {tc.label}
                                                </span>
                                                {n.link && (
                                                    <span className="text-[10px] font-bold text-blue-500 uppercase">Voir →</span>
                                                )}
                                                <div className="flex-1" />
                                                {!n.read_at && (
                                                    <button
                                                        onClick={(e) => { e.stopPropagation(); markAsRead(n.id); }}
                                                        className="p-1.5 rounded-lg text-gray-300 hover:text-emerald-500 hover:bg-emerald-50 transition-all"
                                                        title="Marquer comme lu"
                                                    >
                                                        <Check size={14} />
                                                    </button>
                                                )}
                                                <button
                                                    onClick={(e) => { e.stopPropagation(); setConfirmDelete({ isOpen: true, id: n.id }); }}
                                                    className="p-1.5 rounded-lg text-gray-300 hover:text-red-500 hover:bg-red-50 transition-all"
                                                    title="Supprimer"
                                                >
                                                    <Trash2 size={14} />
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            );
                        })}
                    </div>

                    {/* Pagination */}
                    {totalPages > 1 && (
                        <div className="flex items-center justify-center gap-3">
                            <button
                                onClick={() => setCurrentPage(p => Math.max(1, p - 1))}
                                disabled={currentPage === 1}
                                className="p-2 rounded-xl bg-white border border-gray-200 text-gray-500 hover:bg-gray-50 disabled:opacity-30 disabled:cursor-not-allowed transition-all"
                            >
                                <ChevronLeft size={18} />
                            </button>
                            <span className="text-xs font-black text-gray-500 uppercase">
                                Page {currentPage} / {totalPages}
                            </span>
                            <button
                                onClick={() => setCurrentPage(p => Math.min(totalPages, p + 1))}
                                disabled={currentPage === totalPages}
                                className="p-2 rounded-xl bg-white border border-gray-200 text-gray-500 hover:bg-gray-50 disabled:opacity-30 disabled:cursor-not-allowed transition-all"
                            >
                                <ChevronRight size={18} />
                            </button>
                        </div>
                    )}
                </>
            )}

            <ConfirmModal
                isOpen={confirmDelete.isOpen}
                onClose={() => setConfirmDelete({ isOpen: false, id: null })}
                onConfirm={() => { deleteNotification(confirmDelete.id); setConfirmDelete({ isOpen: false, id: null }); }}
                title="Supprimer la notification"
                message="Voulez-vous vraiment supprimer cette notification ?"
                confirmText="Supprimer"
                type="danger"
            />
        </div>
    );
};

export default Notifications;

import { useState, useEffect, useRef } from 'react';
import { useNavigate } from 'react-router-dom';
import api from '../services/api';
import { Bell, Check } from 'lucide-react';
import toast from '../utils/toast';

const NotificationBell = () => {
    const [notifications, setNotifications] = useState([]);
    const [unreadCount, setUnreadCount] = useState(0);
    const [isOpen, setIsOpen] = useState(false);
    const dropdownRef = useRef(null);
    const navigate = useNavigate();

    const fetchNotifications = async () => {
        try {
            const response = await api.get('/notifications?unread_only=0&limit=5');
            setNotifications(response.data.notifications || []);
            setUnreadCount(response.data.unread_count || 0);
        } catch (err) {
            if (!err.loggedOut) toast.error("Erreur chargement notifications");
        }
    };

    useEffect(() => {
        let cancelled = false;
        const controller = new AbortController();
        const load = async () => {
            try {
                const response = await api.get('/notifications?unread_only=0&limit=5', { signal: controller.signal });
                if (!cancelled) {
                    setNotifications(response.data.notifications || []);
                    setUnreadCount(response.data.unread_count ?? 0);
                }
            } catch (err) {
                if (!err.loggedOut && err.name !== 'CanceledError') toast.error("Erreur chargement notifications");
            }
        };
        load();
        const interval = setInterval(load, 60000);
        return () => { cancelled = true; controller.abort(); clearInterval(interval); };
    }, []);

    useEffect(() => {
        const handleClickOutside = (event) => {
            if (dropdownRef.current && !dropdownRef.current.contains(event.target)) {
                setIsOpen(false);
            }
        };
        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    const markAsRead = async (id) => {
        try {
            await api.patch(`/notifications/${id}/read`);
            fetchNotifications();
        } catch {
            toast.error('Erreur lors de la lecture');
        }
    };

    const markAllRead = async () => {
        try {
            await api.post('/notifications/mark-all-read');
            fetchNotifications();
        } catch {
            toast.error('Erreur lors de la lecture');
        }
    };

    const getTypeStyles = (type) => {
        switch (type) {
            case 'success': return 'bg-emerald-50 text-emerald-600 border-emerald-100';
            case 'danger': return 'bg-red-50 text-red-600 border-red-100';
            case 'warning': return 'bg-amber-50 text-amber-600 border-amber-100';
            default: return 'bg-blue-50 text-blue-600 border-blue-100';
        }
    };

    const handleViewAll = () => {
        setIsOpen(false);
        navigate('/notifications');
    };

    const handleNotificationClick = (notification) => {
        if (!notification.read_at) markAsRead(notification.id);
        setIsOpen(false);
        navigate(notification.link || '/notifications');
    };

    return (
        <div className="relative" ref={dropdownRef}>
            <button 
                type="button"
                onClick={() => setIsOpen(!isOpen)}
                className="relative p-2 rounded-full text-gray-400 hover:text-blue-600 hover:bg-blue-50 transition-all duration-300 focus:outline-none"
            >
                <Bell size={20} />
                <span className="absolute top-1.5 right-1.5 flex h-4 w-4 items-center justify-center rounded-full bg-red-500 text-[10px] font-bold text-white ring-2 ring-white" style={{ opacity: unreadCount > 0 ? 1 : 0, transform: unreadCount > 0 ? 'scale(1)' : 'scale(0)', transition: 'opacity 0.2s, transform 0.2s' }}>
                    {unreadCount > 9 ? '9+' : unreadCount}
                </span>
            </button>

            <div
                className="absolute right-0 mt-3 w-80 glass rounded-3xl border border-gray-200/60 shadow-2xl z-50 overflow-hidden"
                style={{ opacity: isOpen ? 1 : 0, visibility: isOpen ? 'visible' : 'hidden', transform: isOpen ? 'scale(1)' : 'scale(0.95)', transformOrigin: 'top right', transition: 'opacity 0.15s, transform 0.15s, visibility 0.15s' }}
            >
                    <div className="p-4 border-b border-gray-100 flex justify-between items-center bg-white/80 backdrop-blur-xl">
                        <h3 className="font-black text-gray-900 text-sm uppercase tracking-wider">Centre d'alertes</h3>
                        {unreadCount > 0 && (
                            <button 
                                type="button"
                                onClick={markAllRead}
                                className="text-[10px] font-black text-blue-600 hover:text-blue-700 uppercase"
                            >
                                Tout lire
                            </button>
                        )}
                    </div>

                    <div className="max-h-96 overflow-y-auto">
                        {notifications.length > 0 ? (
                            notifications.map((n) => (
                                 <div 
                                     key={n.id} 
                                     onClick={() => handleNotificationClick(n)}
                                     className={`p-4 border-b border-gray-50 hover:bg-gray-50 transition-colors relative group cursor-pointer ${!n.read_at ? 'bg-blue-50/30' : ''}`}
                                 >
                                    <div className="flex items-start space-x-3">
                                        <div className={`p-2 rounded-lg border ${getTypeStyles(n.type)}`}>
                                            <Bell size={14} />
                                        </div>
                                        <div className="flex-1">
                                            <p className={`text-xs font-black ${!n.read_at ? 'text-gray-900' : 'text-gray-500'}`}>{n.title}</p>
                                            <p className="text-[11px] text-gray-500 mt-1 line-clamp-2">{n.message}</p>
                                            <p className="text-[9px] text-gray-400 mt-2 font-mono">{new Date(n.created_at).toLocaleString()}</p>
                                        </div>
                                        {!n.read_at && (
                                            <button 
                                                type="button"
                                                onClick={() => markAsRead(n.id)}
                                                className="p-1 rounded-full text-gray-300 hover:text-emerald-500 hover:bg-emerald-50 transition-all"
                                                title="Marquer comme lu"
                                            >
                                                <Check size={14} />
                                            </button>
                                        )}
                                    </div>
                                </div>
                            ))
                        ) : (
                            <div className="p-8 text-center">
                                <Bell className="mx-auto mb-2 text-gray-200" size={32} />
                                <p className="text-gray-400 text-xs font-semibold">Aucune notification pour le moment.</p>
                            </div>
                        )}
                    </div>
                    
                    <button type="button" onClick={handleViewAll} className="w-full py-3 bg-gray-50 text-[10px] font-black text-gray-400 uppercase hover:bg-gray-100 hover:text-gray-600 transition-colors">
                        Voir tout l'historique
                    </button>
            </div>
        </div>
    );
};

export default NotificationBell;

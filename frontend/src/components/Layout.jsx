import { useState, useEffect } from 'react';
import { Outlet, useLocation, useNavigate } from 'react-router-dom';
import Sidebar from './Sidebar';
import NotificationBell from './NotificationBell';
import Breadcrumb from './Breadcrumb';
import { Search, Plus, MoreHorizontal, User, Settings, HelpCircle, LogOut, Menu, X } from 'lucide-react';
import useAuthStore from '../store/authStore';
import api from '../services/api';
import toast from '../utils/toast';
import { getUserRoles, getRoleLabel } from '../utils/roles';

const SUPPORT_EMAIL = import.meta.env.VITE_SUPPORT_EMAIL || 'support@sirh.local';

const ROUTE_META = {
    '/dashboard':  { title: 'Tableau de bord',     sub: "Vue d'ensemble système" },
    '/teachers':   { title: 'Gestion du Personnel', sub: 'Corps professoral' },
    '/ufrs':       { title: 'UFR',                   sub: 'Architecture académique' },
    '/subjects':   { title: 'Catalogue des Matières',sub: "Unités d'enseignement" },
    '/classrooms': { title: 'Gestion des Salles',   sub: 'Espaces pédagogiques' },
    '/schedules':  { title: 'Planning UFR',          sub: 'Emplois du temps' },
    '/homeworks':  { title: 'Cours & Devoirs',       sub: 'Ressources pédagogiques' },
    '/attendance': { title: 'Pointage',              sub: 'Suivi des présences' },
    '/leaves':     { title: 'Congés & Absences',     sub: 'Demandes & suivi' },
    '/payroll':    { title: 'Honoraires',            sub: "États d'honoraire" },
    '/users':      { title: 'Comptes & Rôles',       sub: 'Gestion des accès' },
    '/documents':  { title: 'Documents',             sub: 'Pièces & justificatifs' },
    '/notifications': { title: 'Notifications',      sub: "Centre d'alertes" },
    '/profile':       { title: 'Mon Profil',         sub: 'Informations personnelles' },
};

const Layout = () => {
    const location = useLocation();
    const logout = useAuthStore((state) => state.logout);
    const user = useAuthStore((state) => state.user);
    const setUser = useAuthStore((state) => state.setUser);
    const navigate = useNavigate();
    const [showMenu, setShowMenu] = useState(false);
    const [searchQuery, setSearchQuery] = useState('');
    const [sidebarOpen, setSidebarOpen] = useState(false);
    const userRoles = getUserRoles(user);

    // Restaurer la session au démarrage du layout (doublon de sécurité)
    useEffect(() => {
        if (!user) {
            const token = localStorage.getItem('token');
            const storedUser = localStorage.getItem('user');

            if (token && storedUser) {
                try {
                    const parsed = JSON.parse(storedUser);
                    setUser(parsed);
                    // Assurez-vous que le token est aussi dans le store
                } catch (error) {
                    console.error('Session restore error:', error);
                    localStorage.removeItem('token');
                    localStorage.removeItem('user');
                }
            }
        }
    }, [user, setUser]);

    const [lastPath, setLastPath] = useState(location.pathname);

    useEffect(() => {
        if (lastPath !== location.pathname) {
            setLastPath(location.pathname);
            setShowMenu(false);
            setSidebarOpen(false);
            setSearchQuery('');
        }
    }, [location.pathname, lastPath]);

    const meta = (() => {
        const base = ROUTE_META[location.pathname] || { title: 'Portail', sub: 'Espace opérationnel' };
        if (location.pathname === '/leaves') {
            if (userRoles.includes('teacher')) {
                return { title: 'Mes Absences', sub: 'Demandes & suivi des absences' };
            }
        }
        if (location.pathname === '/payroll' && userRoles.includes('teacher')) {
            return { title: "État d'Honoraires", sub: 'Détail des honoraires' };
        }
        return base;
    })();

    const handleLogout = async () => {
        await logout();
        navigate('/login');
    };

    const handleQuickAction = () => {
        if (userRoles.includes('admin')) navigate('/users');
        else if (userRoles.includes('hr_manager') || userRoles.includes('secretary')) navigate('/users');
        else if (userRoles.includes('academic_manager')) navigate('/schedules');
        else if (userRoles.includes('accountant')) navigate('/payroll');
        else navigate('/homeworks');
    };

    return (
        <div className="relative min-h-screen font-['Outfit'] select-none bg-gradient-to-br from-slate-50 via-blue-50/30 to-indigo-50/20">

            <Sidebar sidebarOpen={sidebarOpen} onToggle={setSidebarOpen} />

            <div className="flex-1 flex flex-col min-h-screen ml-0 lg:ml-[280px] relative">

                {/* TOP BAR */}
                <header className="fixed top-0 right-0 left-0 lg:left-[280px] h-[72px] bg-white/80 backdrop-blur-xl border-b border-slate-200/50 z-30 flex items-center justify-between px-4 sm:px-6 md:px-8 shadow-[0_1px_3px_rgba(15,23,42,0.04)]">

                    {/* Mobile */}
                    <div className="flex items-center gap-3 lg:hidden">
                        <button
                            type="button"
                            onClick={() => setSidebarOpen(!sidebarOpen)}
                            className="p-2.5 -ml-1 rounded-xl hover:bg-slate-100 transition-colors"
                        >
                            {sidebarOpen ? <X size={20} className="text-slate-600" /> : <Menu size={20} className="text-slate-600" />}
                        </button>
                        <div className="flex flex-col">
                            <h2 className="text-sm font-black text-slate-800 leading-none tracking-tight">{meta.title}</h2>
                            <p className="text-[10px] font-semibold text-indigo-500 uppercase tracking-[0.15em] mt-0.5">{meta.sub}</p>
                        </div>
                    </div>

                    {/* Desktop title */}
                    <div className="hidden lg:flex flex-col justify-center">
                        <h2 className="text-[15px] font-black text-slate-800 leading-none tracking-tight">{meta.title}</h2>
                        <p className="text-[10px] font-semibold text-indigo-500 uppercase tracking-[0.15em] mt-1">{meta.sub}</p>
                    </div>

                    {/* Search */}
                    <div className="flex-1 max-w-sm mx-6 hidden md:block relative group">
                        <Search className="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 group-focus-within:text-indigo-500 transition-colors" size={15} />
                        <input
                            type="text"
                            placeholder="Recherche rapide..."
                            className="w-full bg-slate-50 border border-slate-200 rounded-xl py-2.5 pl-10 pr-16 text-[13px] font-medium text-slate-700 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-300 focus:bg-white transition-all"
                            value={searchQuery}
                            onChange={(e) => setSearchQuery(e.target.value)}
                            onKeyDown={(e) => { if (e.key === 'Enter' && searchQuery.trim()) {
                                const q = encodeURIComponent(searchQuery.trim());
                                const path = location.pathname;
                                if (path.startsWith('/teachers')) navigate(`/teachers?search=${q}`);
                                else if (path.startsWith('/payroll')) navigate(`/payroll?search=${q}`);
                                else if (path.startsWith('/leaves')) navigate(`/leaves?search=${q}`);
                                else if (path.startsWith('/homeworks')) navigate(`/homeworks?search=${q}`);
                                else if (path.startsWith('/schedules')) navigate(`/schedules?search=${q}`);
                                else navigate(`/teachers?search=${q}`);
                            } }}
                        />
                        <div className="absolute right-3 top-1/2 -translate-y-1/2 flex items-center space-x-1">
                            <kbd className="text-[9px] font-black text-slate-300 bg-white border border-slate-200 px-1.5 py-0.5 rounded-md">⌘</kbd>
                            <kbd className="text-[9px] font-black text-slate-300 bg-white border border-slate-200 px-1.5 py-0.5 rounded-md">K</kbd>
                        </div>
                    </div>

                    {/* Right actions */}
                    <div className="flex items-center space-x-2">
                        <button type="button" onClick={handleQuickAction} className="p-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl transition-all shadow-sm shadow-indigo-200">
                            <Plus size={18} />
                        </button>

                        <NotificationBell />

                        <div className="h-7 w-px bg-slate-200 mx-1"></div>

                        <div className="relative">
                            <button
                                type="button"
                                onClick={() => setShowMenu(!showMenu)}
                                className="flex items-center space-x-2.5 px-3 py-2 rounded-xl hover:bg-slate-100 transition-all group"
                            >
                                <div className="w-8 h-8 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center font-black text-white text-sm shadow-md">
                                    {user?.name?.charAt(0) || 'U'}
                                </div>
                                <div className="hidden sm:block text-left">
                                    <p className="text-xs font-black text-slate-700 leading-none">{user?.name?.split(' ')[0]}</p>
                                    <p className="text-[9px] text-slate-400 uppercase tracking-widest font-semibold mt-0.5">
                                        {getRoleLabel(userRoles[0])}
                                    </p>
                                </div>
                                <MoreHorizontal size={16} className="text-slate-400 group-hover:text-slate-600" />
                            </button>

                            {/* Dropdown overlay */}
                            <div
                                className="fixed inset-0 z-40"
                                style={{ opacity: showMenu ? 1 : 0, visibility: showMenu ? 'visible' : 'hidden' }}
                                onClick={() => setShowMenu(false)}
                            />
                            <div
                                className="absolute right-0 top-full mt-2 w-52 bg-white rounded-2xl shadow-lg border border-slate-100 overflow-hidden z-50"
                                style={{ opacity: showMenu ? 1 : 0, visibility: showMenu ? 'visible' : 'hidden', transform: showMenu ? 'scale(1)' : 'scale(0.95)', transformOrigin: 'top right', transition: 'opacity 0.15s, transform 0.15s, visibility 0.15s' }}
                            >
                                <div className="p-3 border-b border-slate-100">
                                    <p className="text-[11px] font-black text-slate-800">{user?.name}</p>
                                    <p className="text-[10px] text-slate-400 mt-0.5">{user?.email}</p>
                                </div>
                                <div className="p-2">
                                    <button type="button" onClick={() => { navigate('/profile'); setShowMenu(false); }} className="w-full flex items-center space-x-3 px-3 py-2.5 text-xs font-semibold text-slate-600 hover:bg-indigo-50 hover:text-indigo-600 rounded-xl transition-colors">
                                        <User size={14} /> <span>Mon Profil</span>
                                    </button>
                                    <button type="button" onClick={() => { navigate(['admin', 'hr_manager', 'secretary'].some(r => userRoles.includes(r)) ? '/users' : '/profile'); setShowMenu(false); }} className="w-full flex items-center space-x-3 px-3 py-2.5 text-xs font-semibold text-slate-600 hover:bg-indigo-50 hover:text-indigo-600 rounded-xl transition-colors">
                                        <Settings size={14} /> <span>Paramètres</span>
                                    </button>
                                    <button type="button" onClick={() => { toast.success(`Contact support : ${SUPPORT_EMAIL}`); setShowMenu(false); }} className="w-full flex items-center space-x-3 px-3 py-2.5 text-xs font-semibold text-slate-600 hover:bg-indigo-50 hover:text-indigo-600 rounded-xl transition-colors">
                                        <HelpCircle size={14} /> <span>Support</span>
                                    </button>
                                </div>
                                <div className="p-2 border-t border-slate-100">
                                    <button
                                        type="button"
                                        onClick={handleLogout}
                                        className="w-full flex items-center space-x-3 px-3 py-2.5 text-xs font-semibold text-rose-500 hover:bg-rose-50 rounded-xl transition-colors"
                                    >
                                        <LogOut size={14} /> <span>Déconnexion</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </header>

                {/* MAIN CONTENT */}
                <main className="px-4 sm:px-6 md:px-8 pt-[96px] pb-10 min-h-screen">
                    <div className="max-w-7xl mx-auto animate-fadeIn" key={location.pathname}>
                        <Breadcrumb />
                        <Outlet />
                    </div>
                    <footer className="py-8 text-center">
                        <p className="text-xs font-semibold text-slate-300 uppercase tracking-[0.25em]">
                            Université Dakar Bourguiba (UDB) · {new Date().getFullYear()}
                        </p>
                    </footer>
                </main>
            </div>
        </div>
    );
};

export default Layout;

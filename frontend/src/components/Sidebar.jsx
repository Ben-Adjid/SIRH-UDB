import { NavLink, useNavigate } from 'react-router-dom';
import {
    LayoutDashboard, Users, Calendar, Clock, Palmtree,
    CreditCard, LogOut, BookOpen, Menu, X,
    LayoutGrid, MapPin, GraduationCap, Settings,
    ChevronRight, FileText, Shield, Zap
} from 'lucide-react';
import { getRoleLabel } from '../utils/roles';
import useAuthStore from '../store/authStore';
import { getUserRoles } from '../utils/roles';

const Sidebar = ({ sidebarOpen, onToggle }) => {
    const logout = useAuthStore((state) => state.logout);
    const user = useAuthStore((state) => state.user);
    const navigate = useNavigate();

    const handleLogout = async () => {
        await logout();
        navigate('/login');
    };

    const userRoles = getUserRoles(user);
    const isAdmin = userRoles.includes('admin');

    const menuGroups = [
        {
            label: 'Principal',
            items: [
                { icon: <LayoutDashboard size={18} />, label: 'Tableau de bord', path: '/dashboard' },
            ]
        },
        {
            label: 'Pointage & Présences',
            items: [
                { icon: <Clock size={18} />, label: 'Pointage', path: '/attendance', roles: ['admin', 'hr_manager', 'secretary', 'academic_manager', 'accountant', 'teacher'] },
            ]
        },
        {
            label: 'Paie & Honoraires',
            items: [
                { icon: <CreditCard size={18} />, label: 'Paie', path: '/payroll', roles: ['admin', 'accountant', 'hr_manager', 'secretary', 'teacher'] },
            ]
        },
        {
            label: 'Gestion Ressources Humaines',
            roles: ['admin', 'hr_manager', 'secretary'],
            items: [
                { icon: <Users size={18} />, label: 'Enseignants', path: '/teachers', roles: ['admin', 'hr_manager', 'secretary', 'academic_manager', 'accountant'] },
                { icon: <Palmtree size={18} />, label: 'Congés & Absences', path: '/leaves', roles: ['admin', 'hr_manager', 'secretary', 'academic_manager', 'accountant'] },
                { icon: <FileText size={18} />, label: 'Documents', path: '/documents', roles: ['admin', 'hr_manager', 'secretary', 'accountant', 'teacher'] },
            ]
        },
        {
            label: 'Académique',
            roles: ['admin', 'academic_manager'],
            items: [
                { icon: <LayoutGrid size={18} />, label: 'UFR', path: '/ufrs', roles: ['admin', 'academic_manager'] },
                { icon: <GraduationCap size={18} />, label: 'Matières', path: '/subjects', roles: ['admin', 'academic_manager'] },
                { icon: <MapPin size={18} />, label: 'Salles', path: '/classrooms', roles: ['admin', 'academic_manager'] },
                { icon: <Calendar size={18} />, label: 'Emploi du Temps', path: '/schedules', roles: ['admin', 'academic_manager', 'teacher'] },
                { icon: <BookOpen size={18} />, label: 'Cours & Devoirs', path: '/homeworks', roles: ['admin', 'academic_manager', 'teacher'] },
            ]
        },
        {
            label: 'Mon Espace',
            items: [
                { icon: <Settings size={18} />, label: 'Mon Profil', path: '/profile' },
            ]
        },
        {
            label: 'Administration',
            roles: ['admin', 'hr_manager', 'secretary'],
            items: [
                { icon: <Shield size={18} />, label: 'Gestion Comptes', path: '/users', roles: ['admin', 'hr_manager', 'secretary'] },
                { icon: <FileText size={18} />, label: 'Audit & Logs', path: '/audit-logs', roles: ['admin', 'hr_manager'] },
            ]
        },
    ];

    const canSeeItem = (item) => {
        if (!item.roles) return true;
        if (isAdmin) return true;
        return item.roles.some(requiredRole => userRoles.includes(requiredRole));
    };

    const dedupedMenuGroups = () => {
        const seenPaths = new Set();
        return menuGroups
            .map((group) => {
                const items = group.items.filter((item) => {
                    if (!canSeeItem(item)) return false;
                    if (seenPaths.has(item.path)) return false;
                    seenPaths.add(item.path);
                    return true;
                });
                return { ...group, items };
            })
            .filter((group) => group.items.length > 0);
    };

    return (
        <>
            <aside className={`h-screen w-[280px] bg-gradient-to-b from-slate-900 via-slate-900 to-indigo-950 flex flex-col fixed left-0 top-0 z-40 transition-transform duration-500 ease-[cubic-bezier(0.16,1,0.3,1)] ${sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'}`}>

                <div className="px-6 py-7 border-b border-white/10">
                    <div className="flex items-center gap-3">
                        <div className="w-12 h-12 rounded-2xl overflow-hidden shadow-lg shadow-indigo-500/30">
                            <img src="/logo.jpg" alt="UDB Logo" className="w-full h-full object-cover" />
                        </div>
                        <div>
                            <h1 className="text-base font-black text-white uppercase tracking-[0.25em] leading-none">UDB</h1>
                            <p className="text-[10px] text-indigo-300/70 uppercase tracking-[0.2em] mt-1 font-semibold">SIRH</p>
                        </div>
                    </div>
                </div>

                <nav className="flex-1 px-4 py-6 overflow-y-auto custom-scrollbar space-y-6">
                    {dedupedMenuGroups().filter(group => group.items.length > 0).map((group, gi) => (
                        <div key={gi}>
                            <p className="text-[9px] font-black text-indigo-300/50 uppercase tracking-[0.25em] px-3 mb-2">
                                {group.label}
                            </p>
                            <div className="space-y-1">
                                {group.items.map((item, index) => (
                                    <NavLink
                                        key={index}
                                        to={item.path}
                                        onClick={() => onToggle(false)}
                                        className={({ isActive }) =>
                                            `flex items-center gap-3 px-4 py-3 rounded-2xl transition-all duration-200 group relative ${
                                                isActive
                                                    ? 'bg-indigo-600/30 text-white shadow-lg shadow-indigo-500/10 ring-1 ring-indigo-400/20'
                                                    : 'text-slate-300 hover:bg-white/5 hover:text-white hover:shadow-[0_0_20px_-5px_rgba(99,102,241,0.3)]'
                                            }`
                                        }
                                    >
                                        {({ isActive }) => (
                                            <>
                                                <span className={`flex-shrink-0 grid place-items-center w-11 h-11 rounded-2xl transition-all duration-200 ${isActive ? 'bg-white/15 text-white shadow-md shadow-indigo-500/20' : 'bg-white/5 text-slate-300 group-hover:bg-white/10'}`}>
                                                    {item.icon}
                                                </span>
                                                <span className="text-[13px] font-semibold flex-1 leading-none">{item.label}</span>
                                                <span style={{ opacity: isActive ? 1 : 0, transition: 'opacity 0.2s' }}>
                                                    <ChevronRight size={14} className="text-indigo-300/70" />
                                                </span>
                                            </>
                                        )}
                                    </NavLink>
                                ))}
                            </div>
                        </div>
                    ))}
                </nav>

                <div className="p-4 border-t border-white/10 space-y-4">
                    <button type="button" onClick={() => { navigate('/dashboard'); onToggle(false); }} className="w-full flex items-center gap-3 px-4 py-4 rounded-xl bg-white/5 border border-white/10 hover:bg-white/10 transition-all cursor-pointer group text-left">
                        <div className="w-11 h-11 rounded-2xl bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center font-black text-white text-sm shadow-lg shadow-indigo-500/20 group-hover:scale-105 transition-transform flex-shrink-0">
                            {user?.name?.charAt(0) || 'U'}
                        </div>
                        <div className="flex-1 overflow-hidden">
                            <p className="text-sm font-bold text-white truncate leading-none">{user?.name || 'Utilisateur'}</p>
                            <p className="text-[10px] text-indigo-300/60 uppercase font-semibold tracking-[0.12em] mt-1">
                                {getRoleLabel(userRoles[0])}
                            </p>
                        </div>
                    </button>
                    <button
                        type="button"
                        onClick={handleLogout}
                        className="w-full inline-flex items-center justify-center gap-2 py-3 text-white bg-rose-500/15 border border-rose-500/20 rounded-xl transition-all hover:bg-rose-500/25 hover:text-white font-semibold text-[11px] uppercase tracking-[0.2em] active:scale-[0.98]"
                    >
                        <LogOut size={14} />
                        <span>Déconnexion</span>
                    </button>
                </div>
            </aside>

            <div
                onClick={() => onToggle(false)}
                className={`fixed inset-0 bg-black/60 backdrop-blur-sm z-30 lg:hidden ${sidebarOpen ? 'animate-fade-in' : 'pointer-events-none'}`}
                style={{ opacity: sidebarOpen ? 1 : 0, transition: 'opacity 0.2s' }}
            />
        </>
    );
};

export default Sidebar;

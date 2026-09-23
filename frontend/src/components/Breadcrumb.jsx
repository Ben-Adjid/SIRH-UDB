import { Link, useLocation } from 'react-router-dom';
import { ChevronRight, Home } from 'lucide-react';

const BREADCRUMB_MAP = {
    '/dashboard': { label: 'Tableau de bord' },
    '/teachers': { label: 'Enseignants' },
    '/users': { label: 'Utilisateurs' },
    '/attendance': { label: 'Pointage' },
    '/payroll': { label: 'Paies' },
    '/leaves': { label: 'Congés & Absences' },
    '/schedules': { label: 'Emploi du Temps' },
    '/homeworks': { label: 'Cours & Devoirs' },
    '/subjects': { label: 'Matières' },
    '/classrooms': { label: 'Salles' },
    '/ufrs': { label: 'UFR' },
    '/documents': { label: 'Documents' },
    '/notifications': { label: 'Notifications' },
    '/audit-logs': { label: 'Audit & Logs' },
    '/profile': { label: 'Mon Profil' },
};

const Breadcrumb = () => {
    const location = useLocation();
    const pathSegments = location.pathname.split('/').filter(Boolean);

    if (pathSegments.length === 0 || location.pathname === '/dashboard') {
        return null; // Don't show breadcrumb on dashboard
    }

    const breadcrumbs = pathSegments.map((segment, index) => {
        const path = '/' + pathSegments.slice(0, index + 1).join('/');
        const label = BREADCRUMB_MAP[path]?.label || segment.charAt(0).toUpperCase() + segment.slice(1);

        return {
            label,
            path,
            isCurrent: index === pathSegments.length - 1,
        };
    });

    return (
        <nav className="flex items-center gap-2 text-sm px-4 py-2 bg-slate-50 rounded-lg border border-slate-100 mb-4" aria-label="Breadcrumb">
            <Link to="/dashboard" className="text-slate-500 hover:text-slate-700 transition-colors">
                <Home size={16} />
            </Link>

            {breadcrumbs.map((crumb) => (
                <div key={crumb.path} className="flex items-center gap-2">
                    <ChevronRight size={16} className="text-slate-300" />
                    {crumb.isCurrent ? (
                        <span className="text-slate-700 font-semibold">{crumb.label}</span>
                    ) : (
                        <Link to={crumb.path} className="text-slate-600 hover:text-slate-900 transition-colors">
                            {crumb.label}
                        </Link>
                    )}
                </div>
            ))}
        </nav>
    );
};

export default Breadcrumb;

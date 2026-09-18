import { useNavigate, useLocation, Link } from 'react-router-dom';
import { ShieldOff, ArrowLeft } from 'lucide-react';
import { ROLE_LABELS } from '../utils/roles';

export default function AccessDenied() {
    const navigate = useNavigate();
    const location = useLocation();
    const requiredRoles = location.state?.requiredRoles || [];

    const formatRoles = (roles) => {
        if (roles.length === 0) return '';
        if (roles.length === 1) return ROLE_LABELS[roles[0]] || roles[0];
        return roles.map((r) => ROLE_LABELS[r] || r).join(' ou ');
    };

    return (
        <div className="min-h-screen flex flex-col items-center justify-center bg-gray-50 px-4">
            <div className="text-center max-w-md">
                <div className="inline-flex items-center justify-center w-20 h-20 rounded-3xl bg-red-50 mb-6">
                    <ShieldOff className="w-10 h-10 text-red-500" />
                </div>
                <h1 className="text-3xl font-black text-gray-900 mb-2">Accès refusé</h1>
                <p className="text-gray-500 mb-2">
                    Vous n'avez pas les droits nécessaires pour accéder à cette page.
                </p>
                {requiredRoles.length > 0 && (
                    <p className="text-sm text-gray-400 mb-8">
                        Rôle requis : <span className="font-semibold text-gray-600">{formatRoles(requiredRoles)}</span>
                    </p>
                )}
                {requiredRoles.length === 0 && <div className="mb-8" />}
                <div className="flex items-center justify-center gap-3">
                    <button
                        type="button"
                        onClick={() => navigate(-1)}
                        className="inline-flex items-center gap-2 px-5 py-2.5 bg-white border border-gray-200 text-gray-700 rounded-xl font-bold text-xs uppercase tracking-wider hover:bg-gray-50 transition-all"
                    >
                        <ArrowLeft className="w-4 h-4" />
                        Retour
                    </button>
                    <Link
                        to="/dashboard"
                        className="px-5 py-2.5 bg-indigo-600 text-white rounded-xl font-bold text-xs uppercase tracking-wider hover:bg-indigo-700 transition-all"
                    >
                        Tableau de bord
                    </Link>
                </div>
            </div>
        </div>
    );
}



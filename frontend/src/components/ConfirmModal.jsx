import { useState, useEffect, useRef } from 'react';
import { AlertTriangle, Info, X } from 'lucide-react';
import toast from '../utils/toast';

const configs = {
    danger: {
        icon: AlertTriangle,
        btnClass: 'bg-rose-500 hover:bg-rose-600 shadow-rose-200 text-white',
        iconClass: 'text-rose-500 bg-rose-50',
        ringClass: 'ring-rose-100',
        gradient: 'from-rose-500 to-pink-500',
    },
    warning: {
        icon: AlertTriangle,
        btnClass: 'bg-amber-500 hover:bg-amber-600 shadow-amber-200 text-white',
        iconClass: 'text-amber-500 bg-amber-50',
        ringClass: 'ring-amber-100',
        gradient: 'from-amber-500 to-orange-500',
    },
    info: {
        icon: Info,
        btnClass: 'bg-blue-500 hover:bg-blue-600 shadow-blue-200 text-white',
        iconClass: 'text-blue-500 bg-blue-50',
        ringClass: 'ring-blue-100',
        gradient: 'from-blue-500 to-cyan-500',
    },
};

const ConfirmModal = ({ isOpen, onClose, onConfirm, title, message, confirmText = "Supprimer", loadingText = "Chargement...", type = "danger", requireConfirmation = false }) => {
    const [loading, setLoading] = useState(false);
    const [confirmValue, setConfirmValue] = useState('');
    const overlayRef = useRef(null);

    const cfg = configs[type] || configs.danger;
    const Icon = cfg.icon;
    const displayText = loading ? loadingText : confirmText;
    const isConfirmDisabled = requireConfirmation && confirmValue.toUpperCase() !== 'CONFIRMER';

    useEffect(() => {
        if (!isOpen) {
            setLoading(false);
            setConfirmValue('');
        }
    }, [isOpen]);

    useEffect(() => {
        if (!isOpen) {
            document.body.style.overflow = '';
            return;
        }
        document.body.style.overflow = 'hidden';
        const handleEscape = (e) => { if (e.key === 'Escape') onClose(); };
        document.addEventListener('keydown', handleEscape);
        return () => {
            document.body.style.overflow = '';
            document.removeEventListener('keydown', handleEscape);
        };
    }, [isOpen, onClose]);

    if (!isOpen) return null;

    return (
        <div
            className="fixed inset-0 flex items-center justify-center p-4"
            style={{ zIndex: 100 }}
            role="dialog"
            aria-modal="true"
            aria-labelledby="confirm-modal-title"
        >
            <div
                ref={overlayRef}
                className="absolute inset-0 bg-slate-900/40 backdrop-blur-sm animate-fade-in"
                onClick={onClose}
            />
            <div className="relative z-10 w-full max-w-md p-8 bg-white rounded-3xl shadow-xl border border-slate-100 animate-scale-in">
                <div className="absolute top-0 inset-x-0 h-1 bg-gradient-to-r from-indigo-500 via-purple-500 to-pink-500 rounded-t-3xl" />
                <div className="flex flex-col items-center text-center">
                    <div className={`p-4 rounded-2xl mb-5 ${cfg.iconClass} ring-1 ${cfg.ringClass}`}>
                        <Icon size={32} />
                    </div>
                    <h2 id="confirm-modal-title" className="text-xl font-black text-slate-800 mb-2">{title}</h2>
                    <p className="text-slate-500 text-sm leading-relaxed mb-6">
                        {message}
                    </p>
                    {requireConfirmation && (
                        <div className="w-full mb-6 p-4 bg-rose-50 border border-rose-200 rounded-xl">
                            <p className="text-xs font-semibold text-rose-700 mb-2">⚠️ Action permanente</p>
                            <input
                                type="text"
                                placeholder='Tapez "CONFIRMER" pour continuer'
                                value={confirmValue}
                                onChange={(e) => setConfirmValue(e.target.value)}
                                className="w-full px-3 py-2 text-sm border border-rose-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-rose-500/20 bg-white"
                                disabled={loading}
                            />
                        </div>
                    )}
                    <div className="flex w-full gap-3">
                        <button
                            onClick={onClose}
                            disabled={loading}
                            className="flex-1 px-4 py-3 rounded-xl font-semibold text-sm text-slate-600 bg-white border border-slate-200 hover:bg-slate-50 transition-all disabled:opacity-40"
                        >
                            Annuler
                        </button>
                        <button
                            disabled={loading || isConfirmDisabled}
                            onClick={async () => {
                                setLoading(true);
                                try {
                                    await onConfirm();
                                } catch (e) {
                                    toast.error(e?.response?.data?.message || 'Erreur lors de l\'action');
                                } finally {
                                    setLoading(false);
                                    onClose();
                                }
                            }}
                            className={`${cfg.btnClass} flex-1 px-4 py-3 rounded-xl font-semibold text-sm shadow-lg transition-all text-center disabled:opacity-50 disabled:cursor-not-allowed`}
                        >
                            {displayText}
                        </button>
                    </div>
                </div>
                <button onClick={onClose} disabled={loading} className="absolute top-4 right-4 p-2 text-slate-400 hover:text-slate-600 transition-colors rounded-xl hover:bg-slate-100">
                    <X size={18} />
                </button>
            </div>
        </div>
    );
};

export default ConfirmModal;

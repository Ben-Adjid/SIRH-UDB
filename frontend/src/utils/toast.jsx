import { useState, useEffect } from 'react';

let _setToasts = null;
let _idCounter = 0;
const _timers = new Map();

function pushToast(message, type = 'info') {
    if (_setToasts) {
        const id = ++_idCounter;
        _setToasts(prev => [...prev, { id, message, type }]);
        const timer = setTimeout(() => {
            _setToasts(prev => prev.filter(t => t.id !== id));
            _timers.delete(id);
        }, 4000);
        _timers.set(id, timer);
    }
}

function clearAllTimers() {
    _timers.forEach(timer => clearTimeout(timer));
    _timers.clear();
}

const toast = {
    success: (msg) => pushToast(msg, 'success'),
    error:   (msg) => pushToast(msg, 'error'),
    info:    (msg) => pushToast(msg, 'info'),
    warning: (msg) => pushToast(msg, 'warning'),
};

const COLORS = {
    success: { bg: 'bg-emerald-50', border: 'border-emerald-400', text: 'text-emerald-700', icon: '\u2713' },
    error:   { bg: 'bg-red-50',     border: 'border-red-400',     text: 'text-red-700',     icon: '\u2715' },
    info:    { bg: 'bg-blue-50',    border: 'border-blue-400',    text: 'text-blue-700',    icon: '\u2139' },
    warning: { bg: 'bg-amber-50',   border: 'border-amber-400',   text: 'text-amber-700',   icon: '\u26A0' },
};

function ToastContainer() {
    const [toasts, setToasts] = useState([]);

    useEffect(() => {
        _setToasts = setToasts;
        return () => { 
            _setToasts = null;
            clearAllTimers();
        };
    }, []);

    return (
        <div className="fixed top-4 right-4 z-[9999] flex flex-col gap-2 pointer-events-none" style={{ maxWidth: '22rem' }}>
            {toasts.map(t => {
                const c = COLORS[t.type] || COLORS.info;
                return (
                    <div
                        key={t.id}
                        className={`pointer-events-auto ${c.bg} ${c.border} border ${c.text} px-4 py-3 rounded-xl shadow-lg font-semibold text-sm flex items-center gap-2 animate-slide-in`}
                    >
                        <span className="text-base">{c.icon}</span>
                        <span className="flex-1">{t.message}</span>
                    </div>
                );
            })}
        </div>
    );
}

// eslint-disable-next-line react-refresh/only-export-components
export default toast;
export { ToastContainer };

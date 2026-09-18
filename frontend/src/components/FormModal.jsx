import { useEffect, useRef } from 'react';

const sizeMap = {
    sm: 'max-w-md',
    md: 'max-w-lg',
    lg: 'max-w-xl',
    xl: 'max-w-2xl',
    full: 'max-w-4xl',
};

const FormModal = ({
    isOpen,
    onClose,
    onSubmit,
    size = 'md',
    title,
    subtitle,
    children,
    submitText = 'Confirmer',
    cancelText = 'Annuler',
    submitClassName,
    footerExtra = null,
    disableSubmit = false,
}) => {
    const maxW = sizeMap[size] || sizeMap.md;
    const overlayRef = useRef(null);

    useEffect(() => {
        if (isOpen) {
            document.body.style.overflow = 'hidden';
        } else {
            document.body.style.overflow = '';
        }
        return () => { document.body.style.overflow = ''; };
    }, [isOpen]);

    useEffect(() => {
        if (!isOpen) return;
        const handler = (e) => { if (e.key === 'Escape') onClose(); };
        document.addEventListener('keydown', handler);
        return () => document.removeEventListener('keydown', handler);
    }, [isOpen, onClose]);

    if (!isOpen) return null;

    return (
        <div
            className="fixed inset-0 flex items-center justify-center px-4 py-8"
            style={{ zIndex: 50 }}
            role="dialog"
            aria-modal="true"
            aria-labelledby={title ? 'form-modal-title' : undefined}
        >
            <div
                ref={overlayRef}
                className="absolute inset-0 bg-slate-900/40 backdrop-blur-sm animate-fadeIn"
                onClick={onClose}
            />
            <div className={`relative w-full ${maxW} max-h-[calc(100vh-4rem)] flex flex-col overflow-hidden bg-white rounded-3xl shadow-xl border border-slate-100 animate-scale-in`}>
                <div className="absolute top-0 inset-x-0 h-1 bg-gradient-to-r from-indigo-500 via-purple-500 to-pink-500 rounded-t-3xl" />
                <form onSubmit={onSubmit} className="flex flex-col flex-1 overflow-hidden">
                    {title && (
                        <div className="px-6 pt-6 pb-2">
                            <h2 id="form-modal-title" className="text-lg font-bold text-slate-800">{title}</h2>
                            {subtitle && <p className="text-sm text-slate-400 mt-0.5">{subtitle}</p>}
                        </div>
                    )}
                    <div className="px-6 py-4 overflow-y-auto flex-1 space-y-5">
                        {children}
                    </div>
                    <div className="px-6 py-4 border-t border-slate-100 bg-slate-50/80 flex flex-wrap items-center justify-end gap-3 rounded-b-3xl">
                        <button type="button" onClick={onClose} className="px-5 py-2.5 text-sm font-semibold text-slate-600 bg-white border border-slate-200 rounded-xl hover:bg-slate-100 transition-all">
                            {cancelText}
                        </button>
                        {footerExtra}
                        <button type="submit" className={submitClassName || 'px-5 py-2.5 text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl shadow-sm shadow-indigo-200 transition-all'} disabled={disableSubmit}>
                            {submitText}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
};

export default FormModal;

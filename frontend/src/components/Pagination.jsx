import { ChevronLeft, ChevronRight, ChevronsLeft, ChevronsRight } from 'lucide-react';

const Pagination = ({ page, totalPages, onPageChange, totalItems, perPage }) => {
    if (totalItems === 0) return null;

    const from = totalItems === 0 ? 0 : (page - 1) * perPage + 1;
    const to = Math.min(page * perPage, totalItems);
    const showNav = totalPages > 1;

    const pages = [];
    if (totalPages <= 7) {
        for (let i = 1; i <= totalPages; i++) pages.push(i);
    } else {
        pages.push(1);
        if (page > 3) pages.push('...');
        const start = Math.max(2, page - 1);
        const end = Math.min(totalPages - 1, page + 1);
        for (let i = start; i <= end; i++) pages.push(i);
        if (page < totalPages - 2) pages.push('...');
        pages.push(totalPages);
    }

    const btnBase = 'w-8 h-8 flex items-center justify-center rounded-lg text-xs font-bold transition-all duration-200 disabled:opacity-30 disabled:cursor-not-allowed disabled:pointer-events-none';
    const btnInactive = 'bg-white border border-slate-200 text-slate-500 hover:border-indigo-200 hover:text-indigo-600 hover:bg-indigo-50';
    const btnActive = 'bg-gradient-to-br from-indigo-600 to-purple-600 text-white shadow-md shadow-indigo-200 border-transparent';

    return (
        <div className="flex flex-wrap items-center justify-between gap-3 pt-6 mt-4 border-t border-slate-100">
            <div className="flex items-center gap-3">
                <p className="text-xs font-semibold text-slate-400">
                    <span className="font-black text-slate-700">{from}–{to}</span> sur <span className="font-black text-slate-700">{totalItems}</span> élément{totalItems > 1 ? 's' : ''}
                </p>
            </div>

            <div className="flex items-center gap-1">
                {showNav && (
                    <button onClick={() => onPageChange(1)} disabled={page <= 1}
                        className={`${btnBase} ${btnInactive}`} aria-label="Première page">
                        <ChevronsLeft size={14} />
                    </button>
                )}
                <button onClick={() => onPageChange(page - 1)} disabled={page <= 1}
                    className={`${btnBase} ${btnInactive}`} aria-label="Page précédente">
                    <ChevronLeft size={14} />
                </button>

                {showNav && (
                    <div className="flex items-center gap-1">
                        {pages.map((p, i) => (
                            p === '...' ? (
                                <span key={`dots-${i}`} className="w-8 h-8 flex items-center justify-center text-[10px] font-bold text-slate-300">•••</span>
                            ) : (
                                <button key={p} onClick={() => onPageChange(p)}
                                    className={`${btnBase} ${p === page ? btnActive : btnInactive}`}>
                                    {p}
                                </button>
                            )
                        ))}
                    </div>
                )}

                <button onClick={() => onPageChange(page + 1)} disabled={page >= totalPages}
                    className={`${btnBase} ${btnInactive}`} aria-label="Page suivante">
                    <ChevronRight size={14} />
                </button>
                {showNav && (
                    <button onClick={() => onPageChange(totalPages)} disabled={page >= totalPages}
                        className={`${btnBase} ${btnInactive}`} aria-label="Dernière page">
                        <ChevronsRight size={14} />
                    </button>
                )}
            </div>
        </div>
    );
};

export default Pagination;

import { useCallback, useRef } from 'react';

/**
 * Hook pour gérer les rafraîchissements automatiques après des mutations
 * Évite les race conditions et permet un debounce
 */
export const useAutoRefresh = () => {
    const timeoutRef = useRef(null);
    const pendingRefreshesRef = useRef(new Set());

    const scheduleRefresh = useCallback((refreshFn, delayMs = 300) => {
        // Ajouter à la queue
        pendingRefreshesRef.current.add(refreshFn);

        // Attendre un peu pour grouper les refreshes
        if (timeoutRef.current) {
            clearTimeout(timeoutRef.current);
        }

        timeoutRef.current = setTimeout(() => {
            // Exécuter tous les refreshes en parallèle
            Promise.all(Array.from(pendingRefreshesRef.current).map(fn => fn()));
            pendingRefreshesRef.current.clear();
        }, delayMs);
    }, []);

    const refreshNow = useCallback((refreshFn) => {
        return refreshFn();
    }, []);

    return { scheduleRefresh, refreshNow };
};

export default useAutoRefresh;

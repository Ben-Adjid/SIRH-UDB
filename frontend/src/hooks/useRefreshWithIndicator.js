import { useState, useCallback } from 'react';
import toast from '../utils/toast';

/**
 * Hook qui améliore le refresh avec un indicateur visuel
 * Affiche une notification et désactive les boutons pendant le refresh
 */
export const useRefreshWithIndicator = () => {
    const [isRefreshing, setIsRefreshing] = useState(false);

    const refresh = useCallback(async (refreshFn, showToast = true) => {
        setIsRefreshing(true);
        try {
            await refreshFn();
            if (showToast) {
                toast.success('Données mises à jour');
            }
        } catch (error) {
            console.error('[Refresh] Error:', error);
            toast.error('Erreur lors de la mise à jour des données');
        } finally {
            setIsRefreshing(false);
        }
    }, []);

    return { isRefreshing, refresh };
};

export default useRefreshWithIndicator;

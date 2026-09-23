/**
 * Service centralisé pour gérer les refreshes de données après mutations
 * Permet aux composants de s'enregistrer pour être notifiés des changements
 */

class DataRefreshManager {
    constructor() {
        this.subscribers = {
            users: [],
            teachers: [],
            attendance: [],
            schedules: [],
            leaves: [],
            payroll: [],
            homeworks: [],
            dashboard: []
        };
    }

    // Enregistrer un callback pour une clé de données
    subscribe(key, callback) {
        if (!this.subscribers[key]) {
            this.subscribers[key] = [];
        }
        this.subscribers[key].push(callback);

        // Retourner une fonction pour se désinscrire
        return () => {
            this.subscribers[key] = this.subscribers[key].filter(cb => cb !== callback);
        };
    }

    // Notifier tous les subscribers d'une clé
    async notify(key, options = {}) {
        console.log(`[DataRefresh] Notifying ${key} subscribers...`);

        if (!this.subscribers[key]) {
            console.warn(`[DataRefresh] No subscribers for key: ${key}`);
            return;
        }

        try {
            // Exécuter tous les callbacks en parallèle
            await Promise.all(
                this.subscribers[key].map(cb => {
                    try {
                        return Promise.resolve(cb());
                    } catch (e) {
                        console.error(`[DataRefresh] Callback error for ${key}:`, e);
                        return Promise.reject(e);
                    }
                })
            );
            console.log(`[DataRefresh] Successfully notified ${key}`);
        } catch (e) {
            console.error(`[DataRefresh] Error notifying ${key}:`, e);
        }
    }

    // Convenience methods pour chaque type de données
    refreshUsers() { return this.notify('users'); }
    refreshTeachers() { return this.notify('teachers'); }
    refreshAttendance() { return this.notify('attendance'); }
    refreshSchedules() { return this.notify('schedules'); }
    refreshLeaves() { return this.notify('leaves'); }
    refreshPayroll() { return this.notify('payroll'); }
    refreshHomeworks() { return this.notify('homeworks'); }
    refreshDashboard() { return this.notify('dashboard'); }

    // Rafraîchir plusieurs clés à la fois
    async refreshMultiple(...keys) {
        return Promise.all(keys.map(key => this.notify(key)));
    }
}

// Instance unique
export const dataRefresh = new DataRefreshManager();

// Hook pour utiliser le service de refresh
export const useDataRefresh = (key) => {
    if (!key) throw new Error('useDataRefresh requires a key parameter');

    return {
        subscribe: (callback) => dataRefresh.subscribe(key, callback),
        refresh: () => dataRefresh.notify(key)
    };
};

export default dataRefresh;

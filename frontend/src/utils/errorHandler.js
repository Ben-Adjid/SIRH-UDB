import toast from './toast';

/**
 * Affiche un message d'erreur élégant et cohérent
 * Priorité: message du serveur > message personnalisé > message par défaut
 */
export function showError(error, defaultMessage = 'Une erreur s\'est produite') {
    // Si c'est un objet avec response (erreur Axios)
    if (error?.response?.data?.message) {
        toast.error(error.response.data.message);
        return;
    }

    // Si c'est un message d'erreur simple
    if (typeof error === 'string') {
        toast.error(error);
        return;
    }

    // Si c'est un objet d'erreur normal
    if (error?.message) {
        toast.error(error.message);
        return;
    }

    // Message par défaut
    toast.error(defaultMessage);
}

/**
 * Gère les réponses d'erreur API avec gestion intelligente
 */
export function getErrorMessage(error, defaultMessage = 'Une erreur s\'est produite') {
    // Message du serveur
    if (error?.response?.data?.message) {
        return error.response.data.message;
    }

    // Message d'erreur standard
    if (error?.message) {
        return error.message;
    }

    // Message par défaut
    return defaultMessage;
}

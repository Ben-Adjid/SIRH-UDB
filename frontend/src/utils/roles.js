export const ROLE_LABELS = {
    admin: 'Administrateur',
    hr_manager: 'Responsable Administratif',
    secretary: 'Secrétaire Administratif',
    academic_manager: 'Chef d\'UFR',
    accountant: 'Comptable',
    teacher: 'Enseignant',
};

export const getRoleLabel = (role) => ROLE_LABELS[role] || 'Collaborateur';

export const normalizeRoleName = (role) => {
    if (!role) return null;
    if (typeof role === 'string') return role;
    if (typeof role === 'object') {
        return role.name || role.slug || role.role || null;
    }
    return null;
};

export const getUserRoles = (user) => {
    if (!user) return [];

    const rawRoles = user.roles ?? user.role ?? [];

    if (Array.isArray(rawRoles)) {
        return rawRoles
            .map((role) => normalizeRoleName(role))
            .filter(Boolean);
    }

    if (typeof rawRoles === 'string') {
        return [rawRoles];
    }

    if (rawRoles && typeof rawRoles === 'object') {
        if (Array.isArray(rawRoles.data)) {
            return rawRoles.data
                .map((role) => normalizeRoleName(role))
                .filter(Boolean);
        }
        const singleRole = normalizeRoleName(rawRoles);
        return singleRole ? [singleRole] : [];
    }

    return [];
};

export const getPrimaryRole = (user) => {
    const roles = getUserRoles(user);
    return roles[0] || 'teacher';
};

export const hasAnyRole = (user, allowedRoles = []) => {
    const userRoles = getUserRoles(user);
    return allowedRoles.some((role) => userRoles.includes(role));
};

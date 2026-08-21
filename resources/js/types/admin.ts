import type { UserRole } from './auth';

export type AdminUser = {
    id: number;
    name: string;
    email: string;
    role: UserRole;
    email_verified_at: string | null;
    owned_workspaces_count: number;
    created_at: string;
};

export type AdminWorkspace = {
    id: string;
    name: string;
    owner: {
        id: number;
        name: string;
        email: string;
    };
    collections_count: number;
    members_count: number;
    created_at: string;
};

export type AdminStat = {
    total: number;
    delta: number | null;
};

export type AdminStatKey =
    'users' | 'admins' | 'workspaces' | 'collections' | 'requests';

export type AdminStats = Record<AdminStatKey, AdminStat>;

export type ChartPoint = {
    date: string;
    label: string;
    value: number;
};

/**
 * Shape of a Laravel length-aware paginator once serialized to Inertia props.
 */
export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    from: number | null;
    to: number | null;
    total: number;
};

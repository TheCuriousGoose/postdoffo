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
    id: number;
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

export type AdminStats = {
    users: number;
    admins: number;
    workspaces: number;
    collections: number;
    requests: number;
};

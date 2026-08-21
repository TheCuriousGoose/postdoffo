export type TeamRole = 'owner' | 'admin' | 'member';

export type Team = {
    id: string;
    name: string;
    owner_id: number;
    workspaces_count?: number;
    role?: TeamRole | null;
};

/**
 * The roles a team can hand out. `owner` is not among them — it comes from
 * the team's `owner_id`, and there is exactly one of it. Mirrors
 * {@link \App\Enums\TeamRole::assignableValues()}.
 */
export type TeamMemberRole = 'admin' | 'member';

export const teamRoleLabels: Record<TeamRole, string> = {
    owner: 'Owner',
    admin: 'Admin',
    member: 'Member',
};

/** What each assignable role gets you, shown next to it when picking one. */
export const teamRoleDescriptions: Record<TeamMemberRole, string> = {
    admin: 'Can manage members and workspaces, and invite others',
    member: 'Gets editor-level access to every workspace this team owns',
};

export type TeamMember = {
    id: number;
    name: string;
    email: string;
    role: TeamRole;
};

export type TeamInvitation = {
    id: number;
    email: string;
    role: TeamMemberRole;
    created_at: string;
    url: string;
};

export type Appearance = 'light' | 'dark' | 'system';
export type ResolvedAppearance = 'light' | 'dark';

export type AppVariant = 'header' | 'sidebar';

export type FlashToast = {
    type: 'success' | 'info' | 'warning' | 'error';
    message: string;
};

export type AppNotification = {
    id: string;
    type: string;
    data: {
        message: string;
        workspace_id?: string;
        [key: string]: unknown;
    };
    read_at: string | null;
    created_at: string;
};

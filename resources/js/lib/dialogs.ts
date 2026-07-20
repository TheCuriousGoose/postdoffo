import { reactive } from 'vue';

/**
 * Backs the app-wide ConfirmDialog/PromptDialog singletons (mounted once per
 * layout: AppHeaderLayout, WorkspaceLayout). We never use
 * window.confirm/alert/prompt — those block the render thread and can't be
 * styled or tested, so every "are you sure?" or "name this" flow goes
 * through these instead.
 */

type ConfirmOptions = {
    title?: string;
    description?: string;
    confirmText?: string;
    cancelText?: string;
    variant?: 'default' | 'destructive';
};

type PromptOptions = {
    title?: string;
    description?: string;
    label?: string;
    defaultValue?: string;
    placeholder?: string;
    confirmText?: string;
    cancelText?: string;
};

export const confirmState = reactive<
    Required<ConfirmOptions> & {
        open: boolean;
        resolve: (value: boolean) => void;
    }
>({
    open: false,
    title: 'Are you sure?',
    description: '',
    confirmText: 'Confirm',
    cancelText: 'Cancel',
    variant: 'default',
    resolve: () => {},
});

export const promptState = reactive<
    Required<PromptOptions> & {
        open: boolean;
        value: string;
        resolve: (value: string | null) => void;
    }
>({
    open: false,
    title: '',
    description: '',
    label: '',
    defaultValue: '',
    placeholder: '',
    confirmText: 'Save',
    cancelText: 'Cancel',
    value: '',
    resolve: () => {},
});

export function confirmDialog(options: ConfirmOptions = {}): Promise<boolean> {
    return new Promise((resolve) => {
        confirmState.title = options.title ?? 'Are you sure?';
        confirmState.description = options.description ?? '';
        confirmState.confirmText = options.confirmText ?? 'Confirm';
        confirmState.cancelText = options.cancelText ?? 'Cancel';
        confirmState.variant = options.variant ?? 'default';
        confirmState.resolve = resolve;
        confirmState.open = true;
    });
}

export function promptDialog(
    options: PromptOptions = {},
): Promise<string | null> {
    return new Promise((resolve) => {
        promptState.title = options.title ?? '';
        promptState.description = options.description ?? '';
        promptState.label = options.label ?? '';
        promptState.defaultValue = options.defaultValue ?? '';
        promptState.placeholder = options.placeholder ?? '';
        promptState.confirmText = options.confirmText ?? 'Save';
        promptState.cancelText = options.cancelText ?? 'Cancel';
        promptState.value = options.defaultValue ?? '';
        promptState.resolve = resolve;
        promptState.open = true;
    });
}

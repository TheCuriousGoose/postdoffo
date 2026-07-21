import { createInertiaApp } from '@inertiajs/vue3';
import createServer from '@inertiajs/vue3/server';
import { createPinia } from 'pinia';
import { createSSRApp, h } from 'vue';
import { renderToString } from 'vue/server-renderer';
import WorkspaceLayout from '@/layouts/app/WorkspaceLayout.vue';
import AdminLayout from '@/layouts/admin/Layout.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createServer((page) =>
    createInertiaApp({
        page,
        render: renderToString,
        title: (title) => (title ? `${title} - ${appName}` : appName),
        layout: (name) => {
            switch (true) {
                case name === 'Welcome':
                    return null;
                case name.startsWith('legal/'):
                    return null;
                case name.startsWith('auth/'):
                    return AuthLayout;
                case name === 'workspaces/Show':
                    return WorkspaceLayout;
                case name.startsWith('settings/'):
                    return [AppLayout, SettingsLayout];
                case name.startsWith('admin/'):
                    return [AppLayout, AdminLayout];
                default:
                    return AppLayout;
            }
        },
        setup({ App, props, plugin }) {
            return createSSRApp({ render: () => h(App, props) })
                .use(plugin)
                .use(createPinia());
        },
    }),
);

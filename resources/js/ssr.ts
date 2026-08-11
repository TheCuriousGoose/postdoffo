import { createInertiaApp } from '@inertiajs/vue3';
import createServer from '@inertiajs/vue3/server';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createPinia } from 'pinia';
import { createSSRApp, h } from 'vue';
import type { DefineComponent } from 'vue';
import { renderToString } from 'vue/server-renderer';
import AdminLayout from '@/layouts/admin/Layout.vue';
import WorkspaceLayout from '@/layouts/app/WorkspaceLayout.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createServer((page) =>
    createInertiaApp({
        page,
        render: renderToString,
        // Providing resolve explicitly makes the SSR overload of
        // createInertiaApp resolve under vue-tsc (the @inertiajs/vite plugin
        // otherwise injects it at build time, which the type-checker can't
        // see). The plugin skips injection when resolve is already present.
        resolve: (name) =>
            resolvePageComponent(
                `./pages/${name}.vue`,
                import.meta.glob<DefineComponent>('./pages/**/*.vue'),
            ),
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

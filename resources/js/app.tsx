import '../css/app.css';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { startNativeApp } from './lib/native';
import { startTimeTheme } from './lib/theme';

const appName = import.meta.env.VITE_APP_NAME || 'TUVIO';

startTimeTheme();

createInertiaApp({
    title: (title) => (title ? `${title} · ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.tsx`,
            import.meta.glob('./Pages/**/*.tsx'),
        ),
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
        startNativeApp();
    },
    progress: {
        color: '#6418f5',
    },
});

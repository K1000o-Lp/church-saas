import '@fontsource-variable/instrument-sans';
import '@fontsource-variable/fraunces';
import '../css/app.css';

import { createInertiaApp } from '@inertiajs/react';
import type { ComponentType } from 'react';

const appName = import.meta.env.VITE_APP_NAME ?? 'Church SaaS';

const pages = import.meta.glob<{ default: ComponentType }>('./pages/**/*.tsx');

void createInertiaApp({
    title: (title) => (title ? `${title} · ${appName}` : appName),
    resolve: (name) => {
        const page = pages[`./pages/${name}.tsx`];

        if (!page) {
            throw new Error(`No se encontró la página de Inertia: ${name}`);
        }

        return page().then((module) => module.default);
    },
    strictMode: true,
    progress: {
        color: 'var(--accent)',
    },
});

import '../css/app.css';

import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

const pages = import.meta.glob([
    './Pages/**/*.jsx',
    '../../Modules/*/resources/js/Pages/**/*.jsx',
]);

const resolvePage = (name) => {
    // Module pages are namespaced "<Module>/<Path>" and live at
    // Modules/<Module>/resources/js/Pages/<Path>.jsx.
    const [module, ...rest] = name.split('/');
    const page =
        pages[`../../Modules/${module}/resources/js/Pages/${rest.join('/')}.jsx`] ??
        pages[`./Pages/${name}.jsx`];

    if (! page) {
        throw new Error(`Page not found: ${name}`);
    }

    return typeof page === 'function' ? page() : page;
};

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: resolvePage,
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(<App {...props} />);
    },
    progress: {
        color: '#4B5563',
    },
});

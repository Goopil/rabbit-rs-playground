import ReactDOMServer from 'react-dom/server';
import { createInertiaApp } from '@inertiajs/react';
import { route } from 'ziggy-js';

export default function render(page) {
    return createInertiaApp({
        page,
        render: ReactDOMServer.renderToString,
        title: (title) => `${title} - ${import.meta.env.VITE_APP_NAME || 'Laravel'}`,
        resolve: (name) => {
            const pages = import.meta.glob([
                './Pages/**/*.jsx',
                '../../Modules/*/resources/js/Pages/**/*.jsx',
            ], { eager: true });

            // Module pages are namespaced "<Module>/<Path>" and live at
            // Modules/<Module>/resources/js/Pages/<Path>.jsx.
            const [module, ...rest] = name.split('/');

            return (
                pages[`../../Modules/${module}/resources/js/Pages/${rest.join('/')}.jsx`] ??
                pages[`./Pages/${name}.jsx`]
            );
        },
        setup: ({ App, props }) => {
            global.route = (name, params, absolute) =>
                route(name, params, absolute, page.props.ziggy);

            return <App {...props} />;
        },
    });
}

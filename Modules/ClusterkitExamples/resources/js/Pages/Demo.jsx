import { Head } from '@inertiajs/react';

export default function Demo() {
    const renderedBy = typeof window === 'undefined' ? 'Server (ClusterKit SSR)' : 'Browser (client hydration)';

    return (
        <div className="flex min-h-screen items-center justify-center bg-gray-100">
            <Head title="ClusterKit demo" />
            <div className="w-full max-w-lg space-y-3 rounded-lg bg-white p-8 shadow-sm">
                <h1 className="text-xl font-semibold text-gray-900">ClusterKit SSR demo</h1>
                <p className="text-sm text-gray-600">
                    This page is served by the Inertia SSR pool orchestrated by{' '}
                    <code className="rounded bg-gray-100 px-1.5 py-0.5">@goopil/clusterkit</code>. Rendered by:
                </p>
                <p className="text-lg font-semibold text-indigo-600">{renderedBy}</p>
                <p className="text-xs text-gray-400">
                    Fire renders at it: <code>sail artisan examples:clusterkit:render --count=20</code>
                </p>
            </div>
        </div>
    );
}

import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

function Card({ title, subtitle, children }) {
    return (
        <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
            <div className="border-b border-gray-200 p-6">
                <h3 className="text-sm font-semibold text-gray-900">{title}</h3>
                <p className="mt-1 text-xs text-gray-500">{subtitle}</p>
            </div>
            <div className="space-y-4 p-6">{children}</div>
        </div>
    );
}

export default function Examples() {
    const { success, sentinel } = usePage().props;
    const [example, setExample] = useState('dispatch');
    const [count, setCount] = useState(3);
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState('');

    const run = (e) => {
        e.preventDefault();
        setError('');
        setProcessing(true);
        router.post(route('lab.examples.rabbit-rs'), { example, count }, {
            onFinish: () => setProcessing(false),
            onError: () => setError('Dispatch failed.'),
        });
    };

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Usage examples</h2>}
        >
            <Head title="Examples" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                    {success && (
                        <div className="rounded-lg bg-emerald-50 p-4 text-sm text-emerald-700">{success}</div>
                    )}

                    <Card
                        title="rabbit-rs — routing, delays, failures"
                        subtitle="Dispatches the RabbitRsExamples job classes; each prints its contract via the CLI examples too."
                    >
                        <form onSubmit={run} className="flex flex-wrap items-end gap-4">
                            <div>
                                <InputLabel htmlFor="example" value="Example" />
                                <select
                                    id="example"
                                    className="mt-1 block rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                                    value={example}
                                    onChange={(e) => setExample(e.target.value)}
                                >
                                    <option value="dispatch">dispatch — routing_key = {`{queue}`}</option>
                                    <option value="delay">delay — later(30), ttl bucket</option>
                                    <option value="fail">fail — tries → failed-jobs</option>
                                </select>
                            </div>
                            <div>
                                <InputLabel htmlFor="count" value="Count" />
                                <input
                                    id="count"
                                    type="number"
                                    min="1"
                                    max="50"
                                    className="mt-1 block w-24 rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                                    value={count}
                                    onChange={(e) => setCount(e.target.value)}
                                />
                            </div>
                            <PrimaryButton disabled={processing}>Run</PrimaryButton>
                            <InputError message={error} className="ms-2" />
                        </form>
                    </Card>

                    <Card
                        title="redis-sentinel — what this process resolved"
                        subtitle="Snapshot from SentinelWhoami (RabbitRs examples live in /horizon; the full status ships as sail artisan sentinel:status)."
                    >
                        <dl className="grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
                            <div><dt className="text-gray-500">service</dt><dd className="font-semibold">{sentinel?.service ?? '–'}</dd></div>
                            <div><dt className="text-gray-500">master</dt><dd className="font-semibold">{sentinel?.master ?? '–'}</dd></div>
                            <div><dt className="text-gray-500">role</dt><dd className="font-semibold">{sentinel?.role ?? '–'}</dd></div>
                            <div><dt className="text-gray-500">connection</dt><dd className="font-semibold">{sentinel?.connection ?? '–'}</dd></div>
                        </dl>
                        <div className="flex gap-3 text-xs text-gray-500">
                            <span>Failover drill:</span>
                            <code>make sentinel-watch</code>
                            <code>make chaos-kill-master</code>
                            <code>make chaos-heal</code>
                        </div>
                    </Card>

                    <Card
                        title="clusterkit — Inertia SSR pool"
                        subtitle="The demo page renders through the SSR server; fire renders at it from the CLI."
                    >
                        <div className="flex flex-wrap items-center gap-4 text-sm">
                            <a href="/clusterkit-demo" target="_blank" rel="noreferrer"
                                className="font-semibold text-indigo-600 hover:text-indigo-500">
                                Open /clusterkit-demo →
                            </a>
                            <code className="rounded bg-gray-100 px-2 py-1 text-xs">
                                sail artisan examples:clusterkit:render --count=20
                            </code>
                        </div>
                    </Card>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

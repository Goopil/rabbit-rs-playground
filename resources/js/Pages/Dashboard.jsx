import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { Head, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

const JOBS = ['default', 'high-priority'];
const CONNECTIONS = ['redis-sentinel', 'rabbit-rs', 'both'];
const QUEUES = ['default', 'high-priority', 'bulk'];

function StatCard({ label, value, accent = '' }) {
    return (
        <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
            <div className="p-6">
                <p className="text-xs font-medium uppercase tracking-wide text-gray-500">{label}</p>
                <p className={`mt-2 text-3xl font-semibold ${accent || 'text-gray-900'}`}>{value}</p>
            </div>
        </div>
    );
}

export default function Dashboard() {
    const { stats, success } = usePage().props;
    const [form, setForm] = useState({ job: 'default', connection: 'redis-sentinel', queue: 'default', count: 10 });
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState('');

    useEffect(() => {
        const id = setInterval(() => {
            router.reload({ only: ['stats'] });
        }, 3000);
        return () => clearInterval(id);
    }, []);

    const dispatch = (e) => {
        e.preventDefault();
        setError('');
        setProcessing(true);
        router.post(route('lab.dispatch'), form, {
            onFinish: () => setProcessing(false),
            onError: () => setError('Dispatch failed.'),
        });
    };

    const horizon = stats?.horizon ?? { masters: [], recent: 0, pending: 0, failed: 0 };
    const queues = stats?.queues ?? {};

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Queue Lab
                </h2>
            }
        >
            <Head title="Lab" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                    {success && (
                        <div className="rounded-lg bg-emerald-50 p-4 text-sm text-emerald-700">
                            {success}
                        </div>
                    )}

                    <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                        <StatCard label="Pending" value={horizon.pending} accent="text-amber-600" />
                        <StatCard label="Recent" value={horizon.recent} />
                        <StatCard label="Failed" value={horizon.failed} accent="text-rose-600" />
                        <StatCard
                            label="Masters"
                            value={Array.isArray(horizon.masters) ? horizon.masters.length : 0}
                            accent="text-emerald-600"
                        />
                    </div>

                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div className="p-6">
                            <h3 className="text-sm font-semibold text-gray-900">Queues depth (redis)</h3>
                            <div className="mt-3 flex flex-wrap gap-3">
                                {['default', 'high-priority', 'bulk'].map((q) => (
                                    <span
                                        key={q}
                                        className="rounded-lg bg-gray-100 px-3 py-1.5 text-sm text-gray-700"
                                    >
                                        {q}: <strong>{queues[q] ?? 0}</strong>
                                    </span>
                                ))}
                            </div>
                        </div>
                    </div>

                    <form
                        onSubmit={dispatch}
                        className="overflow-hidden bg-white shadow-sm sm:rounded-lg"
                    >
                        <div className="grid grid-cols-1 gap-4 p-6 sm:grid-cols-4">
                            <div>
                                <InputLabel htmlFor="job" value="Job" />
                                <select
                                    id="job"
                                    className="mt-1 block w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                                    value={form.job}
                                    onChange={(e) => setForm({ ...form, job: e.target.value })}
                                >
                                    {JOBS.map((j) => (
                                        <option key={j} value={j}>{j}</option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <InputLabel htmlFor="connection" value="Connection" />
                                <select
                                    id="connection"
                                    className="mt-1 block w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                                    value={form.connection}
                                    onChange={(e) => setForm({ ...form, connection: e.target.value })}
                                >
                                    {CONNECTIONS.map((c) => (
                                        <option key={c} value={c}>{c}</option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <InputLabel htmlFor="queue" value="Queue" />
                                <select
                                    id="queue"
                                    className="mt-1 block w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                                    value={form.queue}
                                    onChange={(e) => setForm({ ...form, queue: e.target.value })}
                                >
                                    {QUEUES.map((q) => (
                                        <option key={q} value={q}>{q}</option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <InputLabel htmlFor="count" value="Count" />
                                <TextInput
                                    id="count"
                                    type="number"
                                    min="1"
                                    max="10000"
                                    className="mt-1 block w-full"
                                    value={form.count}
                                    onChange={(e) => setForm({ ...form, count: e.target.value })}
                                />
                            </div>
                        </div>
                        <div className="flex items-center gap-3 border-t border-gray-200 px-6 py-4">
                            <PrimaryButton disabled={processing}>Dispatch</PrimaryButton>
                            <InputError message={error} className="ms-2" />
                        </div>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

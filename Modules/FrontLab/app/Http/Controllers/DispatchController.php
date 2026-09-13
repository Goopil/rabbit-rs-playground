<?php

namespace Modules\FrontLab\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\RabbitRs\Jobs\ProcessDefaultJob;
use Modules\RabbitRs\Jobs\ProcessHighPriorityJob;

class DispatchController extends Controller
{
    private const JOBS = [
        'default' => ProcessDefaultJob::class,
        'high-priority' => ProcessHighPriorityJob::class,
    ];

    public function store(Request $request)
    {
        $validated = $request->validate([
            'job' => 'required|string|in:'.implode(',', array_keys(self::JOBS)),
            'connection' => 'required|in:redis-sentinel,rabbit-rs,rabbit-rs-work,rabbit-rs-ia,both',
            'queue' => 'nullable|string|in:default,high-priority,bulk,work,ia-summary,ia-embed',
            'count' => 'required|integer|min:1|max:10000',
        ]);

        $connections = $validated['connection'] === 'both'
            ? ['redis-sentinel', 'rabbit-rs']
            : [$validated['connection']];

        $queue = $validated['queue'] ?? 'default';
        $dispatched = 0;
        foreach ($connections as $connection) {
            for ($i = 0; $i < $validated['count']; $i++) {
                self::JOBS[$validated['job']]::dispatch(['id' => $i, 'source' => 'lab'])
                    ->onConnection($connection)
                    ->onQueue($queue);
                $dispatched++;
            }
        }

        return back()->with('success', "Dispatched {$dispatched} jobs.");
    }
}

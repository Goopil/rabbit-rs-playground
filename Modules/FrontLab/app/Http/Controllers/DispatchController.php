<?php

namespace Modules\FrontLab\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\RabbitRs\Jobs\ProcessDefaultJob;
use Modules\RabbitRs\Jobs\ProcessHighPriorityJob;
use Modules\RabbitRs\Jobs\ProcessOrderCreated;
use Modules\RabbitRs\Jobs\SendEmailNotification;

class DispatchController extends Controller
{
    private const JOBS = [
        'default' => ProcessDefaultJob::class,
        'high-priority' => ProcessHighPriorityJob::class,
        'order' => ProcessOrderCreated::class,
        'email' => SendEmailNotification::class,
    ];

    public function store(Request $request)
    {
        $validated = $request->validate([
            'job' => 'required|string|in:'.implode(',', array_keys(self::JOBS)),
            'connection' => 'required|in:redis-sentinel,rabbit-rs,both',
            'queue' => 'nullable|string|max:100',
            'count' => 'required|integer|min:1|max:10000',
        ]);

        $connections = $validated['connection'] === 'both'
            ? ['redis-sentinel', 'rabbit-rs']
            : [$validated['connection']];

        $dispatched = 0;
        foreach ($connections as $connection) {
            $queue = $validated['queue'] ?? ($connection === 'redis-sentinel' ? 'default' : 'simple.default.default');
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

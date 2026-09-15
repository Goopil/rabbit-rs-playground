<?php

namespace Modules\FrontLab\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\RabbitRsExamples\Jobs\DelayedReportJob;
use Modules\RabbitRsExamples\Jobs\FlakyJob;
use Modules\RabbitRsExamples\Jobs\ProcessOrderJob;
use Modules\SentinelExamples\Support\SentinelWhoami;

class ExampleController extends Controller
{
    public function index()
    {
        return inertia('FrontLab/Examples', [
            'sentinel' => SentinelWhoami::captureOrError(),
        ]);
    }

    public function rabbitRs(Request $request)
    {
        $validated = $request->validate([
            'example' => 'required|in:dispatch,delay,fail',
            'count' => 'required|integer|min:1|max:50',
        ]);

        $watch = match ($validated['example']) {
            'dispatch' => $this->dispatchMany($validated['count']),
            'delay' => $this->dispatchDelayed($validated['count']),
            'fail' => $this->dispatchFailing($validated['count']),
        };

        return back()->with('success', $watch);
    }

    public function sentinel()
    {
        // Same contract as DashboardController::rabbitDepth: unreachable
        // infra degrades to an ok=false payload, never a 500.
        return response()->json(SentinelWhoami::captureOrError() + ['ok' => true]);
    }

    private function dispatchMany(int $count): string
    {
        foreach (['default', 'high-priority', 'bulk'] as $queue) {
            for ($i = 0; $i < $count; $i++) {
                ProcessOrderJob::dispatch(['order' => uniqid('order-')])->onQueue($queue);
            }
        }

        return "Dispatched {$count} order(s) per queue — watch /horizon (Recent) or /lab.";
    }

    private function dispatchDelayed(int $count): string
    {
        for ($i = 0; $i < $count; $i++) {
            DelayedReportJob::dispatch(['report' => uniqid('report-')])
                ->delay(30)
                ->onConnection('rabbit-rs')
                ->onQueue('default');
        }

        return "Delayed {$count} report(s) by 30s (ttl bucket) — watch rabbit-rs.delay.* in the RabbitMQ UI.";
    }

    private function dispatchFailing(int $count): string
    {
        for ($i = 0; $i < $count; $i++) {
            FlakyJob::dispatch(uniqid('order-'))->onConnection('rabbit-rs')->onQueue('bulk');
        }

        return "Dispatched {$count} FlakyJob(s) (tries=3) — watch /horizon/failed.";
    }
}

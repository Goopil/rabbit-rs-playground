<?php

namespace Modules\FrontLab\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $stats = [
            'horizon' => [
                'masters' => collect(app(MasterSupervisorRepository::class)->all())->pluck('name'),
                'recent' => app(JobRepository::class)->countRecent(),
                'pending' => app(JobRepository::class)->countPending(),
                'failed' => app(JobRepository::class)->countFailed(),
            ],
            'queues' => collect(['default', 'high-priority', 'bulk'])->mapWithKeys(fn ($q) => [$q => [
                'redis' => Redis::connection('default')->llen("queues:{$q}"),
                'rabbit' => $this->rabbitDepth($q),
            ]]),
        ];

        if ($request->boolean('only-stats')) {
            return response()->json($stats);
        }

        return inertia('FrontLab/Dashboard', [
            'stats' => $stats,
        ]);
    }

    /**
     * AMQP queue depth via the Management API — the ground truth (the driver's
     * size() reads are unreliable inside the publishing process, bug 10 — see
     * docs/PLAYGROUND.md). Null when the broker is unreachable: the dashboard
     * must not 500 because RabbitMQ is down, and the 3s poll cadence makes
     * per-poll exception reports pure log spam.
     */
    private function rabbitDepth(string $queue): ?int
    {
        $connection = config('queue.connections.rabbit-rs', []);

        try {
            $response = Http::withBasicAuth(
                $connection['username'] ?? 'guest',
                $connection['password'] ?? 'guest',
            )
                ->timeout(2)
                ->get(
                    rtrim($connection['management_url'] ?? 'http://rabbitmq-simple:15672', '/')
                    .'/api/queues/%2F/'.rawurlencode($queue),
                );

            if (! $response->successful()) {
                return null;
            }

            $ready = $response->json('messages_ready');

            return is_int($ready) ? $ready : null;
        } catch (\Throwable) {
            return null;
        }
    }
}

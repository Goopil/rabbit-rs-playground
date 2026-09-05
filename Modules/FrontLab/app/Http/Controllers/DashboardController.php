<?php

namespace Modules\FrontLab\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
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
            'queues' => collect(['default', 'high-priority', 'bulk'])
                ->mapWithKeys(fn ($q) => [$q => Redis::connection('default')->llen("queues:{$q}")]),
        ];

        if ($request->boolean('only-stats')) {
            return response()->json($stats);
        }

        return inertia('FrontLab/Dashboard', [
            'stats' => $stats,
        ]);
    }
}

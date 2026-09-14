<?php

namespace Modules\ClusterkitExamples\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ExamplesClusterkitRenderCommand extends Command
{
    protected $signature = 'examples:clusterkit:render
                            {--count=10 : Number of renders}
                            {--url= : SSR base URL (default: http://127.0.0.1:{SSR_PORT})}';

    protected $description = 'Example: fire N renders at the ClusterKit SSR pool and report the accounting';

    public function handle(): int
    {
        $base = rtrim($this->option('url') ?: 'http://127.0.0.1:'.env('SSR_PORT', 13715), '/');
        $count = (int) $this->option('count');
        if ($count < 1) {
            $this->error("Invalid --count: {$count}. Must be >= 1");

            return 1;
        }

        // The Demo page calls no route() and needs no auth props — a minimal
        // payload is enough (the app shell's ziggy requirement applies to
        // pages that call route(), see docs/PLAYGROUND.md "SSR roast").
        $payload = [
            'component' => 'ClusterkitExamples/Demo',
            'props' => ['auth' => ['user' => null]],
            'url' => '/clusterkit-demo',
            'version' => 'legacy-dirty',
        ];

        $rendered = 0;
        for ($i = 0; $i < $count; $i++) {
            try {
                $response = Http::timeout(5)->post("{$base}/render", $payload);
            } catch (\Throwable $e) {
                $this->error("SSR pool unreachable at {$base}: {$e->getMessage()}");
                $this->line('Start it: supervisord program `ssr`, or node node/clusterkit-server.mjs');

                return 1;
            }

            $response->successful() ? $rendered++ : $this->line('  render '.($i + 1).": HTTP {$response->status()}");
        }

        $this->info("Rendered {$rendered}/{$count} through the ClusterKit pool at {$base}.");
        $this->line('The usage contract (copy this):');
        $this->line('  Http::post($ssrBase."/render", ["component" => "Module/Page", "props" => [...], ...]);');
        $this->line('Full roast battery (300 renders, kill -9 drills): node/ck-roast.mjs (node/README.md).');

        return $rendered === $count ? 0 : 1;
    }
}

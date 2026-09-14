<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The render example fires real renders at the ClusterKit SSR server
 * (supervisord program `ssr`). When the pool is down the command must
 * fail gracefully (exit 1), and the in-suite test skips.
 */
class ExamplesClusterkitRenderTest extends TestCase
{
    public function test_render_command_renders_through_the_ssr_pool(): void
    {
        try {
            Http::withHeaders(['X-Probe' => '1'])
                ->post($this->ssrUrl().'/render', [
                    'component' => 'ClusterkitExamples/Demo',
                    'props' => ['auth' => ['user' => null]],
                    'url' => '/clusterkit-demo',
                    'version' => 'legacy-dirty',
                ])
                ->throw();
        } catch (\Throwable $e) {
            $this->markTestSkipped('SSR pool unreachable (ssr program down): '.$e->getMessage());
        }

        $exitCode = Artisan::call('examples:clusterkit:render', ['--count' => 5]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Rendered', $output);
    }

    public function test_demo_page_is_reachable_without_auth(): void
    {
        $this->get('/clusterkit-demo')->assertOk();
    }

    private function ssrUrl(): string
    {
        return 'http://127.0.0.1:'.env('SSR_PORT', 13715);
    }
}

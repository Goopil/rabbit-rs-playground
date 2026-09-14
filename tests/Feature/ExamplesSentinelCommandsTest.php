<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Live tests: the sentinels run in the Sail stack (sentinel-1/2/3), same
 * spirit as the broker-up tests in the upstream suite.
 */
class ExamplesSentinelCommandsTest extends TestCase
{
    public function test_whoami_command_resolves_the_current_topology(): void
    {
        $exitCode = Artisan::call('examples:sentinel:whoami');
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('service "mymaster"', $output);
        $this->assertStringContainsString('role:', $output);
    }

    public function test_cache_command_roundtrips_the_default_store(): void
    {
        $exitCode = Artisan::call('examples:sentinel:cache');
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('round-trip OK', $output);
    }
}

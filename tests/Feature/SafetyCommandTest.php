<?php

namespace Tests\Feature;

use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * The safety command runs as a real child process: 0.1.x pools are shared
 * per-process by config fingerprint, so an in-process run after other tests
 * can inherit a dead pool and read broker received=0 (observed on 0.1.6).
 */
class SafetyCommandTest extends TestCase
{
    public function test_safety_command_reports_accurate_broker_reception(): void
    {
        $output = $this->runSafetyCommand();

        foreach (['blind', 'unsafe', 'safe'] as $mode) {
            $this->assertMatchesRegularExpression(
                "/\|\s*{$mode}\s*\|\s*5\s*\|\s*[\d,]+\s*\|\s*5\s*\|/",
                $output,
                "The {$mode} row must report published=5 and broker received=5",
            );
        }
    }

    public function test_safety_command_fails_loud_on_unroutable_in_safe_mode(): void
    {
        $output = $this->runSafetyCommand();

        $this->assertMatchesRegularExpression(
            '/\|\s*safe\s*\|.*rejected:\s*QueueException/',
            $output,
            'The safe row must show the loud unroutable rejection',
        );
        $this->assertMatchesRegularExpression(
            '/\|\s*(blind|unsafe)\s*\|.*silently dropped/',
            $output,
            'Blind and unsafe must drop the unroutable probe silently',
        );
    }

    public function test_safety_command_rejects_non_rabbit_rs_connections(): void
    {
        $process = new Process(
            ['/var/www/html/artisan', 'queue-lab:safety', '--connection=redis-sentinel'],
            '/var/www/html',
            timeout: 60,
        );
        $process->run();

        $this->assertSame(1, $process->getExitCode());
        $this->assertStringContainsString('is not a rabbit-rs connection', $process->getOutput());
    }

    private function runSafetyCommand(): string
    {
        // Dedicated queue: 'work' picks up async stragglers from other tests
        // publishing through the same queue in the parent phpunit process.
        $process = new Process(
            ['/var/www/html/artisan', 'queue-lab:safety', '--count=5', '--settle=1', '--queue=queue-lab-safety'],
            '/var/www/html',
            timeout: 120,
        );
        $process->mustRun();

        return $process->getOutput();
    }
}

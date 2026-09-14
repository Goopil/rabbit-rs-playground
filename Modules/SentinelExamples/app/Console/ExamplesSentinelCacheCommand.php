<?php

namespace Modules\SentinelExamples\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

class ExamplesSentinelCacheCommand extends Command
{
    protected $signature = 'examples:sentinel:cache';

    protected $description = 'Example: cache round-trip through the sentinel-backed store';

    public function handle(): int
    {
        $store = config('cache.default');
        $key = 'examples:sentinel:'.uniqid();
        $value = 'sentinel-'.bin2hex(random_bytes(4));

        try {
            $startedAt = microtime(true);
            Cache::store()->put($key, $value, 30);
            $read = Cache::store()->get($key);
            Cache::store()->forget($key);
            $ms = (int) ((microtime(true) - $startedAt) * 1000);
        } catch (Throwable $e) {
            $this->error("Cache round-trip FAILED on store [{$store}]: ".$e->getMessage());

            return 1;
        }

        if ($read !== $value) {
            $this->error("Cache round-trip FAILED on store [{$store}]: wrote {$value}, read ".var_export($read, true));

            return 1;
        }

        $this->info("Cache round-trip OK on store [{$store}] in {$ms}ms.");
        $this->line('The usage contract (copy this):');
        $this->line('  Cache::store()->put($key, $value, $ttl);  // nothing sentinel-specific:');
        $this->line('  the driver resolves master/replicas under the hood (read/write split).');

        return 0;
    }
}

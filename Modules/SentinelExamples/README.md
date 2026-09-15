# SentinelExamples — laravel-redis-sentinel usage examples

Didactic examples for
[goopil/laravel-redis-sentinel](https://github.com/Goopil/laravel-redis-sentinel).
The package's own diagnostics ship as `sentinel:status` — these examples show
what an application asks the driver for.

## Commands

| Command | Demonstrates |
|---------|--------------|
| `examples:sentinel:whoami` | `RedisSentinelManager` resolution: connector → sentinel client → master, + role of the resolved connection |
| `examples:sentinel:cache` | Cache round-trip through the sentinel-backed store (read/write split is invisible app-side) |

## Wiring notes (cheatsheet)

- `config/database.php` — `database.redis.default` is sentinel-backed:
  `sentinels` (host/port list), `service` = `mymaster`,
  `read_only_replicas` (read/write splitting).
- `config/phpredis-sentinel.php` — driver knobs: `node_cache.ttl`,
  `retry.*`, `log.*` (failover events land in `storage/logs/sentinel.log`).
- Cache, sessions, Horizon and the `redis-sentinel` queue connection all
  ride the same sentinel set (1 master + 2 replicas + 3 sentinels in
  `compose.yaml`).

## Failover drills

```bash
make sentinel-watch      # terminal 1: +switch-master / +sdown / +odown
make chaos-kill-master   # terminal 2: stop valkey-master
make horizon-probes      # Horizon stays ready/alive through failover
make chaos-heal          # old master rejoins as replica
```

Findings: `docs/upstream-laravel-redis-sentinel.md`.

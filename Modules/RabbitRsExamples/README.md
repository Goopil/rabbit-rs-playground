# RabbitRsExamples — rabbit-rs-laravel usage examples

Didactic examples for [goopil/rabbit-rs-laravel](https://github.com/Goopil/php-rabbit-rs):
each command prints the copyable contract it demonstrates. The probing twin
of this module is [`RabbitRs`](../RabbitRs) (topology setup) and the `*Lab`
modules (findings).

## Commands

| Command | Demonstrates |
|---------|--------------|
| `examples:rabbit-rs:dispatch` | One job class → `default`/`high-priority`/`bulk` via `routing_key = {queue}` |
| `examples:rabbit-rs:delay --seconds=30` | `later(N)` in ttl mode — buckets quantize UP to 1/5/30/120 s |
| `examples:rabbit-rs:fail` | `tries` → retries → failure recording (`/horizon/failed`) |

## Wiring notes (cheatsheet)

- `config/queue.php` — `rabbit-rs` connection: `exchange` = `laravel.jobs`,
  `routing_key` = `{queue}`; subscriptions define the worker side
  (default/high-priority/bulk, weights + prefetch).
- `config/rabbit-rs.php` — cross-cutting: `safety` (blind/unsafe/safe),
  `topology_mode` (external/declare), `delay.mode` (ttl/auto/plugin) +
  `delay.buckets`, `delivery_limit` + `dead_letter`.
- Consumers: Horizon on both `redis-sentinel` and `rabbit-rs`
  (`supervisor-horizon`/`supervisor-bulk`/`supervisor-rabbit` in
  `config/horizon.php`).
- Broker: `make setup` declares the exchange, the 7 quorum queues and the
  `failed-jobs` DLQ pair.

## Where the edge cases live

`docs/PLAYGROUND.md` (known traps) and `docs/upstream-rabbit-rs-laravel.md`
(bug dossier). The test suite pins the contract:
`tests/Feature/UpstreamFindingsTest.php`.

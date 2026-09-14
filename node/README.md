# node/ — manual probe scripts

Lab probes that run OUTSIDE the Laravel suite: raw Node / shell / PHP against
the broker, the SSR server, or a tinker process. Findings from these probes
land in the upstream dossiers (`docs/upstream-*.md`); anything automatable
graduated into the suite (`tests/Feature/`).

## clusterkit (SSR roast) — findings in `docs/upstream-clusterkit.md`

| Script | Purpose |
|--------|---------|
| `clusterkit-server.mjs` | Local ClusterKit orchestrator (also the supervisord `ssr` program) |
| `ck-roast.mjs` | Batch render roast + exact accounting (300 renders) |
| `ck-lag.mjs` | Health/lag verification |
| `ck-edge.mjs` | Edge surface: wedged slot, crash breaker, RSS roast |
| `ck-t3.sh` | Circuit breaker trip: 5 crashes of ONE slot's replacements |
| `ck-deadtest.sh` | Kill 2/3 workers — does the primary survive and re-fork? |

## delay (bug 15 family) — findings in `docs/upstream-rabbit-rs-laravel.md`

| Script | Purpose |
|--------|---------|
| `ck15-decisive.sh` | Clean-state proof: purge → `later(120)` → straight-to-bucket |
| `ck15-clean.sh` | Bug 15.1 re-verification, ttl mode, timestamped trace |
| `ck15-auto.sh` | Auto mode (no plugin): early main-queue window |
| `ck15probe.mjs` | Timestamped depth observer (work + delay buckets) |

## safety (blind / unsafe / safe)

| Script | Purpose |
|--------|---------|
| `safety-matrix.sh` | `{safe, unsafe, blind}` × contract matrix, fresh tinker per cell |
| `safety-unroutable.sh` | Real unroutable test: delete the binding, run the per-mode probe |
| `safe-return-probe.php` | Per-mode unroutable probe (API-called from the shell scripts) |
| `list-work-bindings.php` | Broker bindings dump |

## sentinel (valkey failover)

| Script | Purpose |
|--------|---------|
| `sentinel-roast-failover.sh` | Failover UNDER LOAD: 10 writes/s + 10 reads/s through the sentinel driver, 90 s |

## Protocol

After editing any probe, verify the container sees the same file before
running it (bind-mount lag caused three false negatives in one session):

```bash
md5 -q node/<file>                                      # host
vendor/bin/sail exec laravel.test md5sum node/<file>    # container — must match
```

See `docs/PLAYGROUND.md` ("SSR roast") for the full battery.

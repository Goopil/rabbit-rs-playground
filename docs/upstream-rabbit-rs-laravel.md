# Upstream bugs — goopil/rabbit-rs-laravel

> **Session 2026-09-13 — v0.3.4** (package + ext 0.3.4). The full consolidated inventory —
> fixed-and-verified vs still open across every finding in this dossier — lives in
> **"Current status — post-v0.3.4"** below. Session notes for v0.3.2 and earlier follow.
>
> **Session 2026-09-13 — v0.3.2** (package + ext 0.3.2 lockstep — the package now enforces
> `EXTENSION_CONSTRAINT ^0.3.2` at runtime; the doctor reports the constraint as satisfied).
> The headline is the **auto-scaling / one-shot feature (upstream PR #262)**: `rabbit-rs:work`
> gains `--once` / `--stop-when-empty`, admission-only scaling driven by broker depth
> (management API first, native `Pool::size()` fallback via the new `QueueDepthSampler` —
> persistent lazy pools, not forks), and a doctor capacity line. Verified live here: admission
> grows the fleet to `--max-workers` and never beyond, idle fleets downscale without touching
> the restart bookkeeping, `--stop-when-empty` drains authoritatively. **The roast found one
> HIGH, filed and fixed upstream the same day (#269 → PR #270):** in `--once` mode every child
> exits after one job, so the fleet empties constantly and each empty moment consumed one
> re-arm of an absolute budget of 3 — the supervisor then exited **0** with 192 of 215 jobs
> still pending. **v0.3.2 ships without this fix** (merged post-release, unreleased at the
> time of writing); playground-verified against a path-repo build of the fix: 215 jobs → 202
> consumed in the first pass, 7 → 1 → 0 over three passes. The fix also restores the
> pre-#262 signal order (handlers installed before the initial spawn — no more orphan window
> on SIGTERM during spawn). The small per-pass residual is the quorum-stats convergence race
> (`messages_ready` lags seconds behind reality after a burst) — now documented upstream:
> authoritative drains belong to `--stop-when-empty`, whose children observe emptiness
> directly. Remaining from the same live review, filed upstream: #272 (the native depth
> fallback does a blocking AMQP round-trip inside the 100 ms supervision loop) and #273
> (see bug 11 below). Suite: 71 tests, 65 pass, 6 skip, 0 fail.
>
> **Session 2026-09-11 — v0.2.2** (package + ext 0.2.2, lockstep). **Bug 16 fixed**: the
> ext now enforces the publish buffer's `flush_interval` age deadline with a background
> timer — the changelog cites our exact 0.2.1 symptoms ("a lone FPM publish stayed
> invisible; alternating publishes landed in pairs"). Verified: a lone push reaches the
> broker on its own again (0.0 s in the best case); the bug 9 pop pin and the async-flush
> pin pass again. Caveat kept as a latency note: the timer does not hold the 1 ms
> `flush_interval` contract under all conditions — lone pushes observed landing at
> ~4–4.6 s in some runs (0 ms in others; no pattern found), and the after_commit pin can
> still skip under suite load with a 10 s window. No retention, no loss — but "dispatched
> → on the broker" is now ~0–5 s, not ~1 ms. Suite: 71 tests, 64 pass, 7 skip, 0 fail.
>
> **Session 2026-09-10 — v0.2.1** (package + `rabbit-rs-native` ext 0.2.1 via
> `pie install goopil/rabbit-rs-native`; the package now requires `^0.2.1` — the compiled
> config carries the publish `routes` in the native section, so package and ext must move
> together). Shipped fixes, verified here: **bug 13 fixed** (#204 — compile-time
> rejection of `delivery_limit` on classic queues and of non-durable quorum queues, with
> the exact config path; the two compile pins flipped green), **bug 11 fixed** (#208/#214
> — `topology --fix` runs in ~1 s, the 30 s readiness stall is gone; an unreachable
> broker is "unverifiable" instead of missing), **bug 14 superseded** (#205 — the compiled
> publish route makes driver-declared queues reachable: a deleted `laravel.jobs→work`
> binding does **not** break publishing, messages still land; `--fix` itself still does
> not re-declare that one binding). Bug 8's pop scoping (#207) verified working.
> **New bug 16 (0.2.1): the publish buffer no longer age-flushes** — `flush_interval`
> (default 1 ms) is ignored; a publish leaves only on the NEXT publish or on pool close.
> A lone push sat invisible for 15 s+; alternating pushes land in pairs ~2 s after the
> second. The close-drain (#194) still delivers on process exit, so web requests and
> daemons lose nothing — but the last message of a batch is *retained* while the process
> lives, and the driver's own depth counters become unreliable (`size()` returned 0 for a
> queue the broker saw holding 7, then 2 for a queue holding 1). Tests that read depth
> right after a publish must poll the broker (guard `brokerDepthReaches`) or skip.
> In-suite, bug 16 blocks 3 tests (bug 9 pin, async-flush pin, after_commit pin) and the
> `clear()`-racing behavior (bug 10.3, unchanged) can swallow same-process publishes
> outright. Suite: 71 tests, 64 pass, 7 skip, 0 fail.
>
> **Session 2026-09-10 — v0.2.0** (rabbit-rs-laravel v0.1.6 → v0.2.0 **plus** the matching
> native `rabbit-rs-native` v0.1.3 → **v0.2.0** via `pie install goopil/rabbit-rs-native` —
> the package now requires `ext-rabbit_rs ^0.2`; running the 0.2.0 package on the 0.1.x
> extension fails every resolution with
> `config: invalid structure: unknown field 'flush_interval'`, because the new compiler
> emits the new `publisher.flush_interval` knob the old ext rejects). Breaking config
> surface: subscription keys `priority_class` and `starvation_after` are **gone**
> (accepted keys are now `queue, weight, prefetch, early_ack, no_ack`), the per-connection
> `delay_mode` scalar moved to the package config as a `delay` object
> (`{mode, buckets, max_buckets, queue_expiry_margin}`, buckets default `[1, 5, 30, 120]`
> seconds, `max_buckets` 8) — the CHANGELOG still stops at 0.1.0, so none of this is
> documented upstream. The delay machinery itself is the big change: **bug 12's cosmetic
> in-memory strategies are replaced by real bucket queues**, with a new contract break
> of its own (**bug 15**).
>
> **goopil review round (2026-09-10, after their response to this doc):** bugs 6 and 7
> are **fixed in 0.2.0** (5c295a5, 85d48df) and now verified here — the doctor passes;
> the bug-6/7 guard was updated (the "no supervisors configured" warn is legitimate on
> an environment without supervisors) and passes. Bug 9's `block_for` claim was **wrong**
> — the key is accepted (seconds → pop block window) and delivers; re-qualified as a pin.
> Bug 15.1 (early main-queue visibility) is **refuted for ttl** — the message goes
> straight into its bucket; the observed "early window" was residue DLX releases from
> other tests' buckets landing in `work` during the observation window. In `auto` mode
> without the plugin the early window is **real** (probe `node/ck15-auto.sh`) but cannot
> be asserted in-suite (in-process mode switching reuses the cached compiled pool).
> Bug 15.2 (lazy quorum-TTL release) stands. Guard status in 0.2.0 after the round:
> 10 and 12.1 still live (blind contract / plugin absent); 12.3 pass-or-skip (lazy TTL);
> 6/7/9/15-ttl now pins. Suite: 71 tests, 68 pass, 3 skip, 0 fail.
>
> goopil's fixes landing on branch `fox-0.2.0-feedback` (not yet in a dist): #205 bug 14,
> #207 bug 8, #204 bug 13, #208 bug 11, #209 bug 10.1 (safe-mode barrier), #210/#211
> bug 15 (quantization floor + keep-alive redeclare). This playground runs
> `RABBIT_RS_SAFETY=blind`, so #209's safe-mode barrier does not change its 10.1/12.4
> behavior; blind is fire-and-forget by contract.
>
> **Session 2026-09-09 — v0.1.6** (rabbit-rs-laravel v0.1.3 → v0.1.6 jump, rabbit-rs-native
> 0.1.x, RabbitMQ 4.x single node `rabbitmq-simple` behind the lab stack). The v0.1.4/v0.1.5
> write-ups are below; 0.1.6 status is tracked per bug. Bugs 1–5 stay resolved upstream.
> Every finding below has an executable test case in `tests/Feature/UpstreamFindingsTest.php`:
> green pins guard the verified behaviors, bug guards skip while the bug persists and
> auto-activate on the upstream fix (`sail test --group upstream`).
>
> **0.1.6 re-verification:** the bug guards still skip — bugs 6, 7, 9, 10.1 and 12.4 are
> **still live in 0.1.6**. Three new findings (bugs 13, 14 and the bug 10 update below)
> and one improved behavior: bug 11's `--fix` no longer exits 1 after the failed readiness
> gate — it now prints a soft warning (`consumer readiness not confirmed … within 30s`) and
> exits **0**; the 30 s gate itself still burns the clock.

## Current status — post-v0.3.4 (2026-09-13)

Consolidated inventory across every finding in this dossier, as verified in this playground on the
v0.3.4 dist (package + ext 0.3.4, lockstep enforced at runtime).

### Fixed and verified

| Finding | Fixed in | Verified here |
|---|---|---|
| 1–5 (Horizon readyNow, worker inheritance, publish deadline, failure recording, status counters) | 0.1.1–0.1.4 | table below, guards green |
| 6, 7 (doctor broker probe + Horizon check) | 0.2.0 | doctor green on all connections |
| 8 (cross-queue consumption leak) | #207, 0.2.1 | pop scoping verified 2026-09-10; the one-connection-per-group split stays as defense-in-depth |
| 9 (pop blocking) | retracted | `block_for` documented default; pin green |
| 11 (`--fix` false failure + 30 s stall + optimistic success line) | #208/#214 (0.2.1) + #273/#280 (0.3.4) | `--fix` ~1 s, post-condition verified per object, non-zero exit on gaps |
| 12.1 (plugin absent = silent loss in `plugin` mode) | #279, 0.3.4 | `DelayPluginMissingException` on the first delayed publish; `auto` degrades to ttl at compile |
| 12.2/12.4 (teardown publishes deferred jobs early) | 0.2.2 | safety-matrix probe E: routed to the bucket in all three modes |
| 13 (`x-delivery-limit` on classic queues) | #204, 0.2.1 | compile-time rejection with the config path |
| 14 (unreachable declared queues) | #205, 0.2.1 (publishes) + #280 (detection) | publishes route without the binding; a `--fix` declare gap now fails loudly per object instead of hiding behind the success line |
| 15-auto (early main-queue window without the plugin) | #279, 0.3.4 | live 2026-09-13: `later(10)` went straight into the ttl bucket, main queue empty until the deadline |
| 15.1-ttl (early visibility claim) | retracted | straight-to-bucket pin green |
| 10.1/10.3 (stale `size()` / `clear()` race swallowing publishes) | remediation wave 2 (post-0.2.2), reconciled in #253 | re-checked on 0.3.4 (2026-09-13): safe = full barrier; blind reads lag ≤1 by the documented hand-off contract and settle to full depth; the `clear()` race is closed by quiesce + synchronous `flush_all()` (`SizeFlushBarrierTest`) |
| 16 (age-flush contract break) | 0.2.2 | lone push delivers on its own; latency caveat below |
| Safety-matrix probe F (safe-mode unroutable invisible) | #252 (0.3.3) + #278 (0.3.4) | doctor reads `return_unroutable`; the destructor logs every never-surfaced return at teardown |
| DLX canary proposal | #219/#271 (0.3.3) + #275/#276 (0.3.4) | bulk-scan canary, coverage-aware verdicts; see #288 for the hygiene debt |
| #269 (`--once` exits 0 with the queue pending) | #270, 0.3.3 | 215-job drain validated (see #287 for the 0.3.4 convergence regression) |
| #272 (blocking native fallback in the supervision loop) | #281, 0.3.4 | 2 s TTL memoization; see #287 for the once-mode interaction |
| #273 (`--fix` prints success without verification) | #280, 0.3.4 | post-condition verification |
| #275 (canary false-fails behind a DLQ backlog) | #276, 0.3.4 | 3× `[ok]` behind a 63-message backlog; Horizon connection warns inconclusive |
| Doctor "inheritance trap" lint | 0.3.4 | now `[ok] worker class … resolved through the package defaults` — informational, no longer a false warn |

### Still open

- **15.2 — quorum-TTL release is a floor with no ceiling** (lazy expiry on an idle broker;
  observed minutes past the deadline). Broker semantics — **documented limitation, non-goal**
  per #253 (mitigated by the keep-alive redeclare of live bucket queues, #211); the
  pass-or-skip guards in the delay tests encode it.
- **#282 — consume throughput: the 3× driver gap is supply-side wake-chain latency** (profiling
  findings + optimization leads; perf investigation, not a correctness bug). The flush-timer
  latency re-measure (#253's item 6) is folded into this investigation — #253 itself is closed
  (2026-09-14 reconciliation close-out).
- **Flush-timer latency caveat** (not a bug, flagged since 0.2.2): dispatch→broker is ~0–5 s
  under load, not the 1 ms `flush_interval` contract — re-measure rides with #282.
- **`--max-jobs`/`--max-time` still only recycle the child** without stopping the supervisor —
  by design now (`--stop-when-empty` is the CI mode).

### Fixed by the 2026-09-14 wave (merged to main, pending playground verification at the 0.3.5 release)

- **#287 / #291** — `--once` re-probes the depth uncached before the final drain decision; a
  stale memoized 0 or a memoized failed probe can no longer end the drain with work pending;
  a fully failed fresh probe retries within the bounded re-arm budget.
- **#290 / #294** — `RabbitMqQueue::stats()` surfaces the native counters (`returns_total`,
  `dropped_publications_total`) to userland; `rabbit-rs:status` reports the drop counter and
  warns on non-zero.
- **#288 / #292** — the doctor canary verifies through a doctor-owned `rabbit-rs.canary.<hash>`
  DLQ (bound to the configured DLX, purged + deleted every run) with tiered verdicts: found on
  the configured DLQ → ok; only in the canary DLQ (configured backlog deeper than the scan
  window, foreign count reported) → warn; never reaches it → decisive fail.
- **#285 / #293** — admin/consumer acquisition no longer discards typed coordinator errors: a
  permanently failed pool fails admin calls immediately with its published reason instead of
  raw lapin `invalid connection state: Closed`; the 403→FailedPermanent classification stays by
  design (red-team audit).
- **#253** closed as complete (reconciliation close-out) — its remaining live thread (the
  flush-timer re-measure) continues in #282.

## Resolved upstream — verified in this playground

| # | Finding                                                                                                                      | Fixed in | Verified                                                                 |
|---|------------------------------------------------------------------------------------------------------------------------------|----------|--------------------------------------------------------------------------|
| 1 | `Horizon\RabbitMqQueue` missing `readyNow()` → Horizon supervisor crash-loop                                                 | 0.1.1    | 2026-09-05: ships upstream, local vendor patch removed                   |
| 2 | `worker` key not inherited from cross-cutting defaults                                                                       | 0.1.1    | package-defaults fallback implemented, connection workaround removed     |
| 3 | `publish deadline expired` on first publish after idle (long-lived processes)                                                | 0.1.1    | 10/10 publishes after ~25 min idle, zero exceptions                      |
| 4 | Exhausted jobs recorded "completed" in Horizon, not failed                                                                   | 0.1.1    | 6/6 failures in the `failed_jobs` zset + dashboard                       |
| 5 | `rabbit-rs:status` cross-process counters always 0 (read top-level keys instead of nested `message_stats` / API field names) | 0.1.4    | counters match the management API + new `ready` depth column (see below) |
| 6 | `rabbit-rs:doctor` broker probe always fails (`Undefined variable $nativeConfig`)                                            | 0.2.0    | 2026-09-10: 5c295a5, doctor green on all connections, guard passes       |
| 7 | `rabbit-rs:doctor` Horizon check reads `config('horizon')['supervisors']`, a key that never exists                           | 0.2.0    | 2026-09-10: 85d48df reads `horizon.environments.<env>`, guard passes     |

## Verified this session (v0.1.5)

### 0.1.4 — `rabbit-rs:status` counters (bug 5 regression-closed)

After real traffic the management-API section prints non-zero counters identical to
`/api/queues/%2F/{queue}` `message_stats` (`bulk: delivered 106, acked 100, redelivered 6`)
plus the new `ready` depth column. `--format=json` exposes the per-process pool stats.

### 0.1.5 — auto-subscribe profile synthesis (A/B against previous extension)

With `auto_subscribe=true`, plain-name pops resolve through implicit `__auto__.{queue}`
profiles synthesized by the native core at first pop:

- ext **0.1.5**: `pop('ad-hoc-probe')` → DELIVERED, then `pop('ad-hoc-2')` (second distinct plain name, same process,
  after pool creation) → DELIVERED.
- ext **0.1.3**: both pops silently returned `null` (no exception, no log) — the old config-mutation path could not
  extend a pool that already existed.

### after_commit (dispatch protection) — supported end-to-end

The Laravel 13 protection against dispatching inside an open transaction (`->afterCommit()`, `ShouldQueueAfterCommit`,
or `'after_commit' => true` on the connection)
is honored by the driver:

- `push()`/`later()` go through the framework's `enqueueUsing()`
  (`RabbitMqQueue.php:174/196`); `bulk()` partitions jobs by afterCommit and defers only the flagged subset
  (`RabbitMqQueue.php:217+`); `after_commit` is an accepted connection key (`RabbitMqConnector.php:52`, allow-listed in
  `ConnectionCompiler.php:41`).

Live verification (mysql transaction + rabbit-rs, blind safety):

- dispatch `->afterCommit()` inside a transaction that throws → rollback → **nothing published** (broker depth 0) ✓
- same dispatch with a commit → `JobQueued` event fired, message on the broker (~1 s later) ✓ on both the direct
  `Queue::push($job)` path and the standard `dispatch(...)->afterCommit()` path

Measurement trap worth recording: an immediate `size()` right after the commit returned **0**
while the deferred publish was still in the blind-mode publish buffer (age-flushed ~1 s later) — same root as bug 9
(nothing blocks, nothing force-flushes on read). The job is not lost; only pop-once/observe-once consumers and tests are
fooled.

Residual caveat (inherent to `safety=blind`, not to after_commit): a process crash between the SQL commit and the buffer
flush loses the job — the doctor's own warning covers it ("silent message loss is possible"). Production should run
`safety=safe`.

### Topology modes (`declare` vs `external`) — declare verified, with a readiness-gate bug

- `external` (the playground's default): doctor warns "existence cannot be verified", `rabbit-rs:topology --fix`
  refuses — as documented.
- `declare`: **`rabbit-rs:topology`** becomes the verifier — `[ok] queue '…' exists`
  (passive probes), and `--fix --force` **declares** a queue added to the compiled config (quorum, correct args) ✓ — see
  bug 11 for the false-failure exit. The DOCTOR gains nothing in declare mode: its queue-existence check stays blocked
  behind the broken broker probe (bug 6) and still prints "broker unreachable".
- `declare` does **not** cover ad-hoc `queue:work --queue=<new-name>`: a worker booted on a queue that exists nowhere
  (config, broker) never declares it —
  `auto_subscribe` synthesis is passive. Publishing to it in `declare` mode is still unroutable → silently dropped under
  blind. Only compiled subscriptions get declared.

### Delay strategies (`delay.mode`: auto / plugin / ttl) — two durability classes, teardown ignores the delay

Live-tested with `later(2-10s)` on `rabbit-rs-work` (RabbitMQ 4.x, **no broker plugin installed**), in-process and
across process exit:

| mode     | in-process                         | process exits before the delay — artisan (terminating hook)                                                                                            | exit without the hook (tinker)                     |
|----------|------------------------------------|--------------------------------------------------------------------------------------------------------------------------------------------------------|----------------------------------------------------|
| `auto`   | ✓ delivered at the requested time | ✓ **honored** — broker-side mechanism                                                                                                                 | ✓ honored                                         |
| `ttl`    | ✓ delivered                       | ✗ **delivered immediately** — the terminating close flushes the deferred publish without its delay: jobs run ~9 s early (bug 12.4, timestamped repro) | ✗ **lost** — dropped by shutdown, never published |
| `plugin` | ✓ delivered                       | ✗ same as ttl                                                                                                                                         | ✗ same as ttl                                     |

Neither `ttl` nor `plugin` ever declares the infrastructure their names promise: no bucket queues (`delay.buckets`
defaults `[1,5,30,120]`) and no
`x-delayed-message` exchange ever appear on the broker — both silently degrade to the same in-memory hold, only `auto`
is durable. See bug 12 for the full teardown picture. Earlier "silent loss" readings for ttl/plugin were artisan
one-shots observed through hook-less tinker checks — the real teardown picture is the two-column split above.

Upstream needs: make `ttl`/`plugin` do (or refuse loudly what they don't do), and make the terminating quiesce honor (or
refuse) the remaining delay instead of publishing early.

### Worker isolation split (bug 8 workaround) + safety-mode lab

Because of bug 8 the app now runs **one connection per consumer group**
(`config/queue.php`):

- `rabbit-rs` → Horizon `supervisor-rabbit` (default / high-priority / bulk — legacy)
- `rabbit-rs-work` → single `work` queue, consumed by plain `queue:work rabbit-rs-work`
- `rabbit-rs-ia` → `ia-summary` + `ia-embed`, consumed by `rabbit-rs:work --connection=rabbit-rs-ia`

E2E verified: the plain worker drained 3/3 `work` jobs without touching the ia queues, and `rabbit-rs:work` drained 4/4
ia jobs without touching `work` — zero cross-picks, three distinct job classes observed end-to-end (`StressJob`,
`IaSummaryJob`, `IaEmbedJob`).

`queue-lab:safety --count=300 --settle=5` (new lab command, `Modules/QueueLab`)
compares the three `safety` modes live (config switch + QueueManager reflection between modes):

| mode   | published | ops/s | broker received | unroutable routing                   |
|--------|-----------|-------|-----------------|--------------------------------------|
| blind  | 300       | 869   | 300             | silently dropped                     |
| unsafe | 300       | 2,357 | 300             | silently dropped                     |
| safe   | 300       | 2,476 | 300             | **rejected loud** (`QueueException`) |

All three deliver everything while the process lives — blind differs only on crash (buffered tail lost). safe fails loud
on unroutable routing (mandatory + confirms) ✓. Reads must wait for the async flush (bug 10) — earlier runs read 246/300
at t+3s, 300 at t+5s.

### Publish API surface (live, `rabbit-rs-work`)

- `push()` ✓ — exercised by the safety lab and after_commit tests
- `later()` ✓ — `later(8, $job)` is not on the broker at t+3 s, lands at t+8 s (work depth 2 → 3), no phantom delay
  queue created on the broker
- `bulk()` ✓ — 2-job `bulk()` on `work`, depth 3 → 5 after the flush settle;
  `size()` read right after the dispatch still returns the pre-dispatch depth (bug 10, no force-flush on read)
- safety modes ✓ — see the lab table above

### Command sweep — all rabbit-rs + framework commands

| Command / variant                            | Result                                                                                                                                                 |
|----------------------------------------------|--------------------------------------------------------------------------------------------------------------------------------------------------------|
| `rabbit-rs:work` (no flags)                  | fan-out plan lists every defined queue, children spawn ✓ — but see bug 8: the plan does not scope consumption                                         |
| `rabbit-rs:work --queue=…`                   | resolves by definition; unknown names fail with the typed error listing every defined queue ✓                                                         |
| `rabbit-rs:work --connection=`               | filters the plan ✓                                                                                                                                    |
| `rabbit-rs:work --workers=2`                 | 2 children spawned ✓ (both share the same unscoped profile — bug 8)                                                                                   |
| `rabbit-rs:work --max-jobs/--max-time`       | recycle children but never stop the supervisor (see notes)                                                                                             |
| `rabbit-rs:doctor` (+ `--connection=`)       | runs, filter works — but broker probe broken (bug 6), Horizon check dead (bug 7)                                                                       |
| `rabbit-rs:topology` (+ `--fix --force`)     | preflight green on all queues; `--fix` declares in external mode ("topology declared (worker profile 'rabbit-rs')" — again the single-profile compile) |
| `rabbit-rs:status` (+ `--format=json`)       | counters fixed in 0.1.4 ✓                                                                                                                             |
| `rabbit-rs:setup-topology` (app command)     | exchange, queues, bindings, DLQ declared ✓                                                                                                            |
| `queue:clear rabbit-rs --queue=work --force` | `ClearableQueue` works, purge count correct ✓                                                                                                         |
| `queue:monitor rabbit-rs:work`               | runs, exercises `size()` through the framework ✓                                                                                                      |
| `queue:size`                                 | removed in Laravel 13 — not applicable                                                                                                                 |
| `queue:pause` / `queue:resume`               | not supported by this driver (not tested further)                                                                                                      |
| Horizon path (`Horizon\RabbitMqQueue`)       | exercised live via running supervisors (push/pop/failed/readyNow) ✓                                                                                   |

## Bug dossier (0.1.5-era write-ups; per-bug status notes inline — the consolidated state lives in "Current status" above)

### Bug 6: `rabbit-rs:doctor` broker probe always fails — `Undefined variable $nativeConfig`

> **FIXED in 0.2.0 (5c295a5) — verified 2026-09-10.** The vendor dist captures
> `$nativeConfig` in the closure (`DoctorProbe.php:40`); `rabbit-rs:doctor` is green on
> all three connections (broker, management API, dead-letter wiring). The guard in
> `UpstreamFindingsTest` now asserts the healthy output. Analysis below kept for
> reference.

**v0.1.5 code** (`src/Console/DoctorProbe.php:38-42`):

```php
public function broker(array $nativeConfig): ?string
{
    return $this->probePool($nativeConfig, function (Pool $pool): void {
        $pool->size(self::brokerName($nativeConfig), self::queueName($nativeConfig));
    });
}
```

The closure captures `Pool $pool` but **not `$nativeConfig`** — the sibling methods (`queueSize`, `declareTopology`) do
it correctly (`use ($broker, $queue)`,
`use ($workerProfile)`). The probe therefore always throws
`Undefined variable $nativeConfig` on a perfectly healthy broker, and the doctor prints
`[fail] broker rabbitmq-simple:5672 (vhost /): Undefined variable $nativeConfig`
followed by the misleading `[warn] queue existence not verified: broker unreachable`. The doctor's headline check has
never worked as shipped (introduced with the command in 0.1.3, still broken in 0.1.5).

**Fix:** `function (Pool $pool) use ($nativeConfig): void { ... }` — **verified locally**: with the one-line capture
applied, the broker probe passes on all three connections of this playground (`[ok] broker … reachable` ×3, management
API and dead-letter wiring green too) and reverts cleanly.

### Bug 7: `rabbit-rs:doctor` Horizon alignment check reads a config key that does not exist

> **FIXED in 0.2.0 (85d48df) — verified 2026-09-10.** The doctor now reads
> `horizon.environments.<env>`; the warn is emitted only when the *current* environment
> has no supervisors (legitimate — e.g. the `testing` env here). Guard updated to feed a
> supervisors-bearing environment and asserts the warn disappears. Original analysis:

`RabbitMqDoctorCommand.php:303` reads `config('horizon')['supervisors']` — Horizon has no such namespace; supervisors
live under
`config('horizon.environments.<env>.<supervisor>')`. The check can never find a supervisor and always emits `[warn] no supervisors configured in
config/horizon.php` — even with three supervisors configured (this playground). Dead check, false alarm.

### Bug 8 (design): `rabbit-rs:work --queue=X` does not scope consumption — cross-queue leak

> **FIXED upstream (#207) — pop scoping verified 2026-09-10 (0.2.1).** The one-connection-per-group
> split below stays as defense-in-depth: it also scopes Horizon's compiled profile at boot, which
> the pop-level fix cannot do for long-running workers that keep the profile from boot.

The 0.1.0 fan-out is advertised as "`--queue=x,y` resolves names by definition … a queue defined on two targeted
connections is consumed on both". The plan resolution (`WorkPlanResolver`) is correct, but the plan only decides **which
children spawn** — it never scopes what a child consumes:

1. `ConnectionCompiler` compiles **exactly one worker profile per connection**
   (`'workers' => [$worker]`, `ConnectionCompiler.php:80`), whose subscription list contains **every** queue defined on
   the connection.
2. `RabbitMqQueue::pop($queue)` resolves the queue to that shared profile via
   `WorkerProfileResolver::profileForQueue()` and consumes with
   `$pool->consumer($profile)` — a weighted round-robin over **all** subscriptions. The `--queue` flag of the
   `queue:work` child is passed to `pop()` but the resolution ignores the restriction.

Demonstrated on the live broker (Horizon exonerated — its children held a stale pre-change profile and could not consume
the new queues at all):

- `rabbit-rs:work --connection=rabbit-rs --queue=work --max-jobs=3` — the child processed a **`ia-summary`** delivery
  (logged failure, `worker-0`, persisted to
  `failed_jobs`).
- `rabbit-rs:work --queue=ia-summary --max-jobs=1` — the child announced
  `rabbit-rs[ia-summary]`, then drained a **`work`** job within 12 s, and (after the clean-exit recycle) the parked **
  `ia-embed`** job too.

Net effect: on any connection defining more than one queue, `--queue` gives a **false sense of isolation** — every child
eats from every queue. This also defeats Horizon's queue-based supervisors pointed at the same connection
(`pop('default')` → same shared profile). The only driver-level isolation today is one connection per consumer group.

**Fix sketch:** compile one worker profile per targeted queue (or per `--queue`

partition) instead of a single all-subscriptions profile, so `pop($queue)` can resolve a profile scoped to exactly that
queue; or pass the queue filter down to the consumer acquisition.

### Bug 9 (minor, **retracted 2026-09-10**): `pop()` is non-blocking — first pop after a same-process publish races

> **CORRECTED after goopil's review:** the claim that `block_for` "would be rejected as an
> unknown key" was **wrong** — the connection key is accepted (validated as a non-negative
> integer, `RabbitMqConnector.php:56-72`, seconds → pop block window) and works: with
> `block_for=2`, a pop issued immediately after a same-process publish delivers the job
> (verified live + pin `test_bug9_pop_with_block_for_delivers_after_a_same_process_publish`).
> What remains is a **documented default**, not a bug: `block_for` defaults to `0`
> (non-blocking), so pop-once consumers (tests, tooling) must opt in to a block window.
> Worker daemons loop and are unaffected. Kept here as the corrected contract.

`RabbitMqQueue::$blockForMilliseconds` defaults to `0`; with the default, `pop()` →
`consumer->next(0)` returns immediately, while the publish of a job dispatched **in the same
process** is still buffered (flush happens on the consume path). `consumers.wait_timeout`
(30 s) is a transport acquisition deadline, not a pop wait — the naming invites the wrong
conclusion.

### Bug 10 (regression + data-loss footgun): publish buffer never force-flushes on read; async age-flush up to several seconds; un-flushed tail lost at terminating close in multi-pool flows

> **Status after 0.3.4 — CLOSED:** 10.2 was fixed back in 0.0.6. 10.1/10.3 were closed by the
> remediation wave after 0.2.2 (reconciled in #253) and re-verified live on the 0.3.4 dist
> (2026-09-13): safe-mode `size()` right after a same-process push returns the real broker depth
> through the full barrier; blind-mode reads may lag by exactly the one in-flight publication
> (documented hand-off contract — "hand-off is not delivery") and settle to the full depth; the
> `clear()` race is closed by the quiesce + synchronous `flush_all()`, guarded by
> `SizeFlushBarrierTest`.

**0.1.6 update:** the force-flush calls are back in the code (`RabbitMqQueue`
`size()`/`clear()` call `$this->pool->flush()` — "issue #194" refs) but they are **not synchronous barriers**: `size()`
immediately after a same-process push still returns the stale depth (the 10.1 guard still skips). Re-measured on a clean
broker (management stats ruled out via the authoritative `/get` endpoint):

- **Warm pool** (any broker op — `clear()`/`size()` — before the push): all 5 publishes land in **~0.6 s** ✓ (big
  improvement over 0.1.3's 1–5 s).
- **Cold pool** (push as the very first broker operation of the process):
  delivery is **chunky and slow** — 4/5 after 12 s, the straggler lands at
  ~20–25 s. Artisan one-shots that push cold and exit inside that window lose the tail (this is the reproducible form of
  the historical one-shot losses).
- Lab recipe: prime the pool (`clear()`/`size()`) before publishing in scripts and tests.

Three observations, one root area (`RabbitMqQueue` publish path + pool lifecycle in the 0.1.x pipelining rewrite):

1. **No force-flush on read (regression vs 0.0.9).** The 0.0.9 changelog documented "size ()/clear () flush the publish
   buffer first". In 0.1.5 neither does: `size()` right after a same-process dispatch returns 0 (then the real depth ~
   1.5 s later). Reads and pop-once consumers are fooled.
2. **Async age-flush latency up to ~5 s.** Publishes land on the broker asynchronously (observed 1–5 s) with no
   documented knob and no flush trigger on subsequent queue operations. A 300-publish safe-mode batch read 246/300 at
   t+3s and 300/300 at t+5s.
3. **Terminating close: quiesce works — the loss needs a clear () in flight.**
   `RabbitMqServiceProvider.php:120` registers `$app->terminating(… flush())` →
   `NativePoolFactory::flush()` → `Pool::close()` on every cached pool. An executable child-process repro
   (`queue-lab:dispatch-and-exit`, see
   `tests/Feature/ChildProcessReproTest.php`) delivers **9/9 jobs through three pools** on a clean exit — the
   terminating quiesce is sound on its own. The historical 900-loss (safety lab, git history) involved per-mode
   `clear()`
   calls racing the async flush window: messages landed after a purge was issued and the tail vanished. The suspect is
   the purge/flush interplay, not
   `close()` dropping un-attempted batches — exact drop point still needs upstream confirmation.

Impact: daemons tolerate this (their next op flushes); **artisan one-shots**
(migrations, seeders, scripts dispatching jobs) can exit inside the flush window — combined with (3) the tail is
silently lost, and with `safety=blind`
this is by design per the doctor's warning. Fix sketch: force-flush the buffer in `size()`/`clear()` (restore 0.0.9
semantics), and quiesce-with-deadline all pool buffers in the terminating hook before closing.

### Bug 11 (false failure): `rabbit-rs:topology --fix` declares successfully, then exits "declaration failed"

**0.1.6 update:** the false exit is gone — `--fix` now prints
`topology declared (worker profile '…'); consumer readiness not confirmed:
consumer profile '…' did not become ready within 30s` and exits **0**. The declare itself works. Remaining rough edge:
the 30 s readiness gate still blocks every `--fix` invocation without running workers (the bootstrap scenario), and a
separate verification quirk remains: with `management_url` unset on the connection, the verify phase prints
`[fail] queue '…' is missing` right before the declare succeeds (verify uses the management API, declare uses the
transient consumer — the two stages report against different states).

With `topology_mode=declare`, `rabbit-rs:topology --fix --force` **does** declare the missing queue (verified on the
broker: quorum, correct args) — but the command then waits 30 s for consumer-profile readiness and exits with
`declaration failed — consumer profile 'rabbit-rs-ia' did not become ready
within 30s` (0.1.5 behavior). The lab had no ia worker running; readiness is apparently gated on a running consumer,
making `--fix` fail whenever it is used without workers already up — the exact bootstrap scenario where you need it.

**Fix sketch:** report declaration success on the declare step itself; treat consumer readiness as a separate warning
(or only check it when workers are flagged as running). — *0.1.6 moved most of the way there; the 30 s gate remains.*

> **0.3.2 live finding (filed upstream as #273):** `--fix` prints its success line ("topology
> declared") without confirming the declared objects actually exist — observed live: the
> command exited green while the queue was never created (it had to be declared by API
> afterwards). Suggested upstream: verify the post-condition per object (or re-declare
> passively) before printing success, and exit non-zero when an object is missing.
>
> **FIXED in 0.3.4 (#280) — verified 2026-09-13:** the post-condition verification re-runs the
> probes after the declare pass and prints success only for objects confirmed on the broker;
> gaps exit non-zero per object. Bug 11 is fully closed.

### Bug 12 (false strategies + teardown semantics + hang): `delay.mode=plugin`/`ttl` are in-memory aliases of
`auto`; teardown publishes deferred jobs early or loses them; safe mode hangs instead of failing

See the delay-strategy table above. Four findings in one area:

1. **False strategies.** `ttl` never creates its bucket queues and `plugin`
   never declares an `x-delayed-message` exchange — both silently degrade to the same in-memory hold that `auto` backs
   with a broker-side mechanism. A config rename with no warning.
2. **Teardown publishes early.** In a process whose terminating hook fires (plain artisan command), `Pool::close()`
   flushes the in-memory deferred publishes **without honoring their remaining delay** — a `later(10s)` job lands at
   t+1s and runs ~9 s early (timestamped repro in
   `tests/Feature/ChildProcessReproTest.php`).
3. **No hook, no delivery.** In hook-less processes (tinker loop, custom shutdown paths), the same deferred publishes
   are dropped by shutdown — never published.
4. **safe hangs.** With `safety=safe`, a failed plugin-mode publish does not fail at the 30 s `confirm_timeout` — the
   follow-up operation hangs indefinitely (>200 s observed). Not reproducible in CI; kept manual.

The correct behavior is `auto`'s: durable deferred delivery with the delay honored across process exit, and bounded,
loud failure when a strategy's prerequisites are missing.

> **0.2.0 status:** 12.1 is fixed — `ttl` now creates real bucket queues and `auto`
> degrades to them without the plugin (no hard error). 12.4 (teardown publishes early)
> is still live. The bucket machinery brings its own break: see bug 15 (early
> consumer-visibility + unbounded release lateness).
>
> **Status after 0.3.4:** 12.1 fully dead — #279 makes `plugin` refuse loudly
> (`DelayPluginMissingException`, first delayed publish, when the management API proves the
> plugin absent) and resolves `auto` against the broker at compile time (degrading to ttl
> without the plugin), so the plugin strategy never runs unguarded. 12.2/12.4 dead since the
> 0.2.2 timer (probe E: teardown routes to the bucket in all three modes). **12.3 remains
> open**: hook-less processes (tinker, custom shutdown paths) still drop the in-memory
> deferred hold — the bucket paths cover the configured modes, not this fallback shape.

### Bug 13 (declare breakage, 0.1.6): the compiler emits
`x-delivery-limit` for classic queues — RabbitMQ rejects the declare

`delivery_limit` rides into the queue arguments of **every** compiled queue, regardless of
`queue_type` — in this playground it comes from the connection config
(`config/rabbit-rs.php` sets `delivery_limit => 20` explicitly; goopil's review notes the
package itself does not default it — the "20" here was ours, matching the broker's quorum
default). RabbitMQ 4.x only accepts `x-delivery-limit` on **quorum** queues; classic queues
reject it at declare time:

```
operation queue.declare caused a channel exception precondition_failed:
invalid arg 'x-delivery-limit' for queue 'nondurable-probe' in vhost '/'
of queue type rabbit_classic_queue
```

(broker log, three consecutive attempts — `rabbit-rs:topology --fix` on a
`queue_type=classic` connection, default `delivery_limit` inherited). Net effect: a classic queue with the inherited
default can never be declared by the driver — the declare fails, the queue never appears, and the `--fix` run only
reports the soft readiness warning on top.

Adjacent hole in the same area (no broker log needed): `queue_durable=false` +
`queue_type=quorum` compiles silently although quorum queues are always durable — the declare would fail broker-side.
Compile-level pin in
`tests/Feature/RabbitRsTopologyConfigTest.php`.

**Fix sketch:** emit `x-delivery-limit` only for quorum queues (or only when explicitly configured), and reject
`queue_durable=false` + quorum at compile time with the typed config error.
**FIXED in 0.2.1 (#204) — verified 2026-09-10:** both combinations now throw at compile
time with the exact `queue.connections.<name>.<key>` path; the two compile pins flipped
green (they now assert the rejection).

### Bug 14 (declare gap, 0.1.6):
`rabbit-rs:topology --fix` declares the queue and the DLX chain but NOT the publish-side route binding

Probed with a temp declare-mode connection (quorum queue `dlx-probe-main`,
`dead_letter` = dlx-probe-dlx / dlx-probe-dead, `routing_key = null`): after a successful `--fix --force` the broker
shows the queue, the DLX, the DLQ, and the DLQ→DLX binding — but **no binding from the connection's exchange**
(`laravel.jobs` ← `dlx-probe-main`). A publish to that exchange with the queue's routing key answers
`{"routed": false}` → unroutable → **silently dropped under blind**. The queue exists, the DLX chain is perfect, and the
queue is still unreachable for every publisher.

`reference.md` states "Rabbit RS declares all exchanges, queues, and bindings idempotently" — the route binding is the
one binding that decides whether a declared queue is usable at all. **Untested:** whether a worker boot declares route
bindings (temp connections are invisible to child artisan processes; needs config-file surgery to probe).

**Fix sketch:** declare route bindings (`routes.default.exchange` ←
`routing_key` per compiled queue) in the same pass as the queues — or document that declare-mode users must provision
route bindings externally.

**0.2.1 update (#205) — superseded for normal publishes, partially fixed in `--fix`:**
the compiled config now carries the publish `routes` and messages reach their queues:
deleting the `laravel.jobs→work` binding and pushing (clean process, no `clear()`) still
delivered — the driver's routing no longer depends on that one binding. `--fix --force`
itself still does **not** re-declare the deleted binding (it declares the queues, the
DLX chain and exits green in ~1 s), so external provisioning of that binding remains
required; re-creating it by API took the broker back to normal. Kept open as a
cosmetic/declare-completeness item rather than a data-loss one.

### Bug 15 (delay contract, 0.2.0 — **re-verified after goopil's review**): `auto` exposes an early main-queue window without the plugin; ttl bucket release can be unboundedly late; ttl early-visibility claim **retracted**

0.2.0 replaces the cosmetic in-memory delay hold (bug 12) with **real bucket queues**:
`later(N)` publishes into a quorum bucket queue
(`rabbit-rs.delay.<fingerprint>.<id>.<bucket_ms>`) whose `x-message-ttl` equals the
quantized bucket size, dead-letters back to the main queue via the connection exchange
(`x-dead-letter-exchange: laravel.jobs`, `x-dead-letter-routing-key: <main queue>`), and
self-cleans after 65 s idle (`x-expires: 65000`). Quantization is UP to the configured
bucket families (`[1, 5, 30, 120]` by default — `later(3)` → the 5 s bucket,
`later(10)` → the **30 s** bucket).

**Clean-state re-verification (2026-09-10)** — the first write-up of 15.1 was an
observation artifact. The original probe read `work` depth 7 s after publishing a
`later(30)` and found it elevated; in a suite full of delay tests, **residue DLX releases
from other tests' buckets dead-letter into `work` during any observation window** — the
elevated depth was residue, not the deferred job. After deleting every
`rabbit-rs.delay.*` bucket and purging `work` first (`node/ck15-decisive.sh`):

1. **ttl mode: REFETCHED — the message goes straight into its bucket.** `later(120)`:
   `work` stays 0 for the whole trace while the bucket is declared and holds the message
   (the bucket's `messages_ready` reads 0 for a few seconds — quorum visibility lag —
   then 1). No main-queue hop, no sweep. goopil's analysis was right; the pin
   `test_bug15_ttl_deferred_jobs_go_straight_to_the_bucket` (bucket purge + 3 s window)
   now guards it in-suite.
2. **auto mode (no plugin): the early window is REAL.** `later(30)` publishes without
   exception and the message sits **in `work`** at +2 s, with no bucket created — a later
   sweep re-buckets it (`node/ck15-auto.sh`). Any consumer polling in that window runs
   the job early. This contradicts goopil's description of `auto` (plugin strategy →
   terminal per-message error on a plugin-less broker): that
3. is the behavior of **their
   `fox-0.2.0-feedback` branch (#210/#211)**, not of the 0.2.0 dist this playground runs.
   In-suite assertion is impossible — switching `delay.mode` in-process reuses the cached
   compiled pool, so an "auto" publish rides the ttl path; the finding stays documented
   with its probe.
3. **Release can be unboundedly late on an idle broker (15.2, stands).** Quorum-queue
   `x-message-ttl` expiry is lazy: a 2 s delay arrived at t+11 s in one probe and was
   still missing at t+45 s in another; a `later(30)` bucket held its message minutes past
   the deadline while idle. The delay deadline is a floor with no ceiling — the bucket
   sits idle until the broker's lazy TTL sweep fires. `rabbit-rs.delay.queue_expiry_margin`
   (default 60) and `max_buckets` are configurable; the TTL laziness is broker semantics
   and not configurable at all. The 45 s wait in
   `test_bug12_ttl_delay_mode_delivers_deferred_jobs` is pass-or-skip by design.

Net effect in the 0.2.0 dist: `auto` (plugin-less) = early execution risk; `ttl` = correct
routing but late-release risk; `plugin` = correct when installed, **silent total loss**
when absent (bug 12.1). goopil's #210/#211 (quantization floor + keep-alive redeclare)
address parts of this on their branch.

> **Status after 0.3.4:** 15-auto is **dead** — #279 resolves `auto` against the broker at
> connection compile time and degrades it to the ttl bucket queues when the plugin is absent,
> so the main-queue early-execution window no longer exists (live-verified 2026-09-13:
> `later(10)` on a plugin-less broker went straight into the bucket; main queue empty until the
> deadline). 15.2 stands: quorum-TTL release is still a floor with no ceiling on an idle broker.

### Bug 16 (flush contract break, 0.2.1): the publish buffer no longer age-flushes — a lone publish is retained until the next publish or pool close

> **FIXED in 0.2.2 — verified 2026-09-11.** The ext enforces the age deadline with a
> background timer (lockstep ext 0.2.2, no Laravel-layer change). A lone push reaches the
> broker on its own again (best case ~0 ms); the alternation pattern is gone; the bug 9
> pop pin and the async-flush pin pass again. **Residual latency caveat:** the timer does
> not always hold the 1 ms contract — lone pushes observed at ~3.9–4.6 s in some runs
> (0.0 s in others). No retention, no loss, but the dispatch-to-broker latency is
> ~0–5 s, not ~1 ms. The after_commit pin still skips under suite load (10 s window vs
> the observed lag) — pass-or-skip by design. 0.2.1 analysis below kept for reference.

0.2.1 ships the branch fixes but silently breaks the age-flush path (bug 10's fix family —
#194 added `publisher.flush_interval`, default 1 ms, bounded 0..3,600,000). Observed on
this playground (package 0.2.1 + ext 0.2.1, rabbitmq-simple, single connection
`rabbit-rs-work`, `safety=blind`):

- A **lone push** sat broker-invisible for 15 s+ (polled the management API at 200 ms);
  the same push pattern in 0.2.0 landed within the 1 ms flush window.
- **Alternating pushes land in pairs**: push 1 invisible at +8 s, push 2 visible at
  +2.4 s (both together), push 3 invisible, push 4 visible at +2.0 s — the flush rides
  the *next* publish.
- **Pool close still drains** (#194 holds): a push followed by process exit delivers
  (ready=1 after exit). Web requests and daemons that exit cleanly lose nothing.
- The driver's **depth counters read nonsense while this holds**: `size()` returned 0
  while the broker held 7 messages, then 2 while it held 1 — it appears to reflect the
  in-process buffer, not the queue.
- Popping does **not** release the buffer: `push` → blocking `pop` (2 s) → null while
  the message sat in the publish buffer (the bug 9 pin now skips on this).
- The `clear()`-racing behavior (bug 10.3) still swallows same-process publishes — a
  `clear()` followed by a push in the same process lost the message even across exit.

Net: nothing is lost for clean-exit processes, but "dispatched" no longer means "on the
broker within `flush_interval`" — it means "whenever the next publish happens, or at
close". Timed consumers, one-shot CLI publishers waiting to observe their message, and
any depth assertion inside the publishing process are broken. Guards:
`test_publishes_reach_the_broker_within_the_async_flush_window` and the after_commit pin
skip while a lone publish doesn't reach the broker within 5 s.

**Fix sketch:** restore the age-flush path (timer thread per pool, or flush on read
depth) — 0.2.0 had it working at the 1 ms default.

## Safety-mode contract matrix (0.2.2, 2026-09-11)

`rabbit-rs.safety` compiles to `confirms = safety !== 'blind'` + a publisher actor that
branches on the mode (`ConnectionCompiler.php:457-470`): **safe** = confirms + mandatory
(outcome tracking), **unsafe** = synchronous socket write without outcome tracking,
**blind** = fire-and-forget. The playground ran the same six probes in a fresh tinker
process per (mode × probe) — mode read from env at boot, no in-process switching
(`node/safety-matrix.sh`), connection `rabbit-rs-work`, each probe prefixed by a
bucket delete + queue purge:

| Probe | safe | unsafe | blind |
|---|---|---|---|
| A — lone publish reaches broker | 2.3 s ✓ | 1.6 s ✓ | 2.5 s ✓ |
| B — `pop()` after visible publish | GOT ✓ | GOT ✓ | GOT ✓ |
| C — `size()` once the broker holds 1 | 1 ✓ | 1 ✓ | 1 ✓ |
| D — `clear()` then push, clean exit | delivered ✓ | delivered ✓ | delivered ✓ |
| E — child exit with pending `later(10)` | routed to **bucket** ✓ (no early main-queue publish) | bucket ✓ | bucket ✓ |
| F — publish with the route binding removed (**unroutable**) | **NO exception, message lost** | no exception, lost | no exception, lost |

Read-through:

- The 0.2.2 timer and the pop/size/close-drain contracts hold **identically in all three
  modes** — safety only changes the wire level (confirms/mandatory), not the buffering
  or the queue plumbing. Simple-path behavior: no mode-dependent surprises.
- **The safe-mode `mandatory` contract is not observable at the API level (probe F).**
  With the publish binding removed, safe should fail the publish (the broker returns the
  message — that is what mandatory exists for). Instead all three modes publish
  silently and the message is lost. At best the return is tracked internally (a
  `dropped_publications_total` counter would be the place to look); from the caller's
  side safe and blind are indistinguishable on unroutables, which defeats the point of
  the mode. Candidate finding: `mandatory` returns should surface as an exception (or at
  least a counter the app can read).
  **0.3.3/0.3.4 update (#252, #278):** the outcome is now observable — the doctor reads
  `message_stats.return_unroutable` on the publish exchange (fail under safe, warn under
  unsafe/blind), and `RabbitMqQueue::__destruct()` logs every never-surfaced return at
  teardown (`error` level, `kind`/`message_id`/`message` context). The synchronous typed
  throw stays limited to `bulk()`/explicit `flush()`; the pipelined path keeps its
  replay-on-recovery contract.
- Probe D is the *simple* clear-then-push shape (bug 10.3's race needs a flush actually
  in flight when `clear()` runs — covered by `ChildProcessReproTest`, not by this matrix).
- E across all modes is the 0.2.2 shape of the old bug 12.4: the child-exit teardown now
  routes deferred jobs into their bucket instead of republishing them into the main
  queue early — 12.4's early-publish behavior is gone in all three modes.

### Note: the DLX wiring contract (verified) + the direct-`#` trap + doctor-canary proposal

- **The driver's declared pair is coherent** (probe + canary above): DLX type
  `direct`, `x-dead-letter-routing-key` = the **main** queue's name on the source queue, DLQ bound to the DLX with that
  same key. A terminally rejected delivery lands in the DLQ (`dlx-contract-canary`, `x-death` recorded). The reference
  doc's "the queue name is used as the routing key" means the main queue's name — the correct choice for a per-queue
  DLX.
- **The trap:** a hand-wired `direct` DLX with a `#` binding silently drops everything — on a direct exchange `#`
  matches only the literal key, dead-lettered messages keep their original routing key, and the broker-internal DL
  publish carries no `mandatory` flag, so the AMQP drop is silent **by design**. The lab hit exactly this (our
  `setup-topology` created direct + `#`; the poison test failed loud — the right layer to catch it). Two valid models,
  never mix them: (1) driver-style direct + explicit dlrk/binding pair per queue, (2) lab-style **fanout** catch-all.
- **Proposal:** give the doctor (or `rabbit-rs:topology` verify) a DLX canary — publish a probe delivery, terminally
  reject it, assert the DLQ receives it. It would have caught the lab's dead wiring immediately, and it is the only
  check that exercises the whole chain (queue args → DLX → binding → DLQ)
  instead of inspecting its parts.
  **Implemented:** #219/#271 shipped the canary in 0.3.3; its head-of-line false-fail was fixed in #276 (0.3.4,
  bulk scan + coverage-aware verdicts). Hygiene debt remains: stale canaries accumulate in the DLQ and eventually
  outgrow the scan window (#288).

## Notes (not bugs)

- **0.2.0 delay surface (recorded for the next migration).** The connection-level
  `delay_mode` key is gone — the compiler rejects it with `unknown key`; the knob now
  lives in the package config as `rabbit-rs.delay.{mode, buckets, max_buckets,
  queue_expiry_margin}`. Subscription knobs `priority_class`/`starvation_after` were
  removed (dead knobs); the accepted set is `queue, weight, prefetch, early_ack, no_ack`.
  The publisher block gained `flush_interval` (default **1 ms**), which is also the
  marker of an ext/package version skew: the 0.1.x ext answers the new compiled config
  with `unknown field 'flush_interval', expected one of 'safety', 'confirms',
  'mandatory', 'confirm_timeout'` on every resolution — upgrade the extension with
  `pie install goopil/rabbit-rs-native` together with the package, never one alone.
- **Declare-mode probe race ("invalid channel state: Closing").** After an in-process
  `Artisan::call` that created pools, a following `rabbit-rs:topology` verify probe can
  race the asynchronously-closing pools and fail one queue with
  `invalid channel state: Closing (queue.declare)` — connection-dependent, not
  time-based (a fixed sleep does not help). Tests retry the verify
  (`test_declare_mode_topology_command_verifies_queues`); scripts should too.
- **In-process pool staleness (0.1.x).** Pools are shared per-process by config fingerprint ("two byte-identical
  connections share one native pool"), and the registry survives `QueueManager` connection-forgetting: after long test
  processes (or Octane workers) resolved and *closed* pools, a re-resolved connection can inherit a dead pool —
  publishes buffer into it and never reach the broker (the safety lab read `broker received=0` in-process while the same
  command from a fresh CLI read 5/5/5). Lab workaround, also the recommended shape for one-shot scripts: run
  publish-heavy flows in a fresh child process (`tests/Feature/SafetyCommandTest.php` does exactly that).
- **Management API publish gotcha (lab-side).** RabbitMQ's
  `POST /api/exchanges/%2F/<name>/publish` requires `properties` to be a JSON **object**; PHP's empty array encodes as
  `[]` and the API answers **500** with an empty body. Cast `(object) []` (see `RabbitRsLiveTopologyTest`). Recorded
  here because a 500 on the publish API looks like a broker outage.
- **Management stats can lie after a broker incident.** During the 2026-09-09 storage incident the queue summary
  (`messages_ready`) reported phantom depths (30 on an empty queue) while the authoritative
  `POST /api/queues/%2F/{queue}/get`
  returned nothing. Trust `/get` over the summary when numbers stop moving.
- `rabbit-rs:doctor` warns `worker resolves to Horizon\RabbitMqQueue through the
  package defaults … inheritance trap` — but default inheritance is exactly what the v0.1.1 fix (#2 above) implemented.
  The lint contradicts the shipped, documented behavior; make it informational or drop it.
  **Resolved in 0.3.4:** the check now prints `[ok] worker class: … (resolved through the package defaults)` —
  informational, no longer a false warn.
- `rabbit-rs:work` never self-terminates: `--max-jobs`/`--max-time` recycle the child (clean exit → immediate restart,
  per the 0.0.9 fix) and the supervisor keeps running. Fine for production daemons; surprising in CI. A
  `--stop-when-empty` (or honoring `--max-time` at supervisor level) would help.
  **0.3.2 update:** self-termination now exists — `--stop-when-empty` (drain then exit; children observe emptiness
  directly, the authoritative mode) and `--once` (depth-driven drain; see the 0.3.2 session note for the #269/#270
  re-arm history). `--max-jobs`/`--max-time` still only recycle the child without stopping the supervisor.
- Long-running workers (Horizon) keep the compiled profile from boot: config changes require `horizon:terminate` +
  restart. With the pre-split config, bug 8 meant a restarted `supervisor-rabbit` would have consumed the new `work`/
  `ia-*`
  queues too; the connection split above now keeps Horizon scoped to its three legacy queues even after a restart.
- The failed cross-queue job WAS persisted to `failed_jobs` and recorded as failed — the at-least-once → failure path
  keeps working (delivery-limit 20, one broker redelivery observed in the `redelivered` counter).

## Verdict

**0.3.4:** the agent-PR wave lands in a dist (#276 canary bulk-scan, #278 safe teardown surfacing,
#279 delay-plugin guards, #280 topology post-condition verification, #281 sampler TTL cache) and the
live roast found two things. The canary works as designed behind a backlog (3× `[ok]` after purging)
but **self-sandbags**: every doctor run deposits a canary that never leaves the DLQ, the playground's
accumulated 188 messages outgrew the 100-message scan window, and the check degraded to permanent
`inconclusive` on every connection of that broker (#288 — a probe that dies of its own byproduct; a
dedicated canary DLQ is the deterministic fix). And `--once` needs roughly **twice the passes** to
converge under quorum lag (215 jobs: 204/4/6/1 over 4 passes vs 208/0 over 2 on 0.3.3): #281's 2 s
sampler cache makes a stale-0 or failed probe authoritative, and the final drain check exits on the
cached empty reading instead of re-probing (#287 — silent exit 0 with real work pending, `/get`-verified).
The delay path is clean live: `auto` without the plugin degraded to the ttl bucket queues at compile
(#279), the bucket appeared on demand, and the 3 delayed jobs landed on the main queue at the deadline.

**0.3.3:** #270 ships in a dist and is validated live here — 215 jobs through `--once --max-workers=4`
drained 208 in pass 1 with the known quorum-stats convergence residual (7), and pass 2 finished the queue
(`ready: 0`, twice reproduced). But the release's headline doctor canary (#219, #271) **false-failed out of
the box** behind this playground's 63-message `failed-jobs` backlog: the verification pulled one message at
the DLQ head with `ack_requeue_true`, never advanced past foreign traffic, and reported "not received" —
issue #275, fixed in #276 (not yet in a dist): a bounded bulk-scan window (`CANARY_DLQ_SCAN_WINDOW`, default
100) with coverage-aware verdicts — whole DLQ visible and probe absent after polling = genuine failure,
full window = `CanaryInconclusiveException` warning, competing consumers on the connection (Horizon running)
= inconclusive warning instead of a fail. Live result: 3 connections `[ok] delivered, rejected, and received
on the DLQ` behind the backlog, the Horizon connection `[warn] inconclusive` instead of red. #272 and #273
carry fixes in open PRs (#281 sampler TTL-cache fallback, #280 `--fix` post-condition verification), plus
#278 (safe-mode teardown surfacing) and #279 (delay-plugin guards: `auto` resolves against the broker at
compile time, `plugin` refuses loudly) — #279's first cut resolved the delay mode only inside the connector,
splitting the pool fingerprint from every raw-recompile site (the Octane RoadRunner certification caught it:
`/publish` buffered 5, `/stats` polled an empty pool forever); the resolution now lives in
`ConnectionCompiler::compile()` itself.

**0.3.2:** the auto-scaling / one-shot surface lands (upstream #262) — `--once`,
`--stop-when-empty`, depth-driven admission scaling with the management-API sampler plus a
native fallback, a doctor capacity line, and package↔extension lockstep enforced at runtime
(`EXTENSION_CONSTRAINT`). The feature shipped with one drain-correctness hole (upstream #269,
fixed post-release in #270, not yet in a dist): `--once`'s absolute 3-re-arm budget was
consumed by the constantly-emptying fleet and the command exited 0 with 192/215 jobs pending;
after #270 the budget renews on observed progress (clean child exits, crash-loops excluded)
and the only per-pass residual is the quorum-stats convergence race. Bugs 10.1, 10.3, 12.1,
12.3/15.2 and 15-auto carry over; #272 (blocking native fallback in the 100 ms loop) and #273
(optimistic `--fix` success line) are the new open items.

**0.2.2:** bug 16 dead — the background timer restores lone-publish delivery and the
depth counters behave again. Latency between dispatch and broker is back to sub-second
in the best case, though the timer can still lag to ~4–5 s under load (contract says
1 ms; flagged above). Bugs 10.1 (stale `size()`), 10.3 (clear-race), 12.1 (plugin absent
= silent loss), 12.3/15.2 (lazy quorum TTL) and 15-auto (early window without the
plugin) carry over; 13/11/14/#207 stay fixed as verified in 0.2.1.

**0.2.1:** the branch fixes land as promised — bug 13 dead (#204), bug 11 dead (#208/#214),
publish routing makes bug 14's "unreachable queues" scenario moot for normal publishes
(#205), and #207's pop scoping behaves. But the release **breaks the publish flush
contract** (new bug 16): `flush_interval` is ignored, a lone publish is retained until
the next publish or pool close, and the driver's `size()` reads nonsense meanwhile.
Nothing is lost for processes that exit cleanly (close-drain holds), but latency between
"dispatched" and "on the broker" is now unbounded — for one-shot publishers, timed
consumers and any depth-based assertion this is a hard contract break. Bugs 10.1 (stale
`size()`, now worse), 10.3 (clear-race), 12.1 (plugin absent = silent loss), 12.3/15.2
(lazy quorum TTL) and 15-auto (early window without the plugin) carry over.

**0.2.0 (re-verified after goopil's review round):** the headline change — real bucket
queues for `ttl` delays — makes deferred delivery broker-side and correct in routing
(15.1-ttl retracted: the message goes straight to its bucket; the "early window" was
test-suite contamination). Two real contract items remain: **`auto` without the plugin
publishes the deferred job into the main queue** (early execution until the sweep
re-buckets it — probe `node/ck15-auto.sh`; goopil's terminal-error behavior is their
branch, not this dist), and **quorum-TTL laziness leaves the release deadline with no
ceiling** on an idle broker (15.2). The doctor's two core checks are **fixed and
verified** (6, 7), `block_for` is an accepted key (9 retracted to a documented default —
pop-once consumers must opt in), `size()` still reads stale despite the new 1 ms
`flush_interval` default (10.1; this playground runs `safety=blind`, fire-and-forget by
contract — goopil's #209 barrier concerns safe mode), the child-exit teardown still
publishes deferred jobs early (12.4), classic queues still can't be declared with an
explicit `delivery_limit` (13 — fixed on `fox-0.2.0-feedback` #204), and `--fix` still
leaves route bindings unwired — driver-declared queues remain unreachable for publishers
(14 — #205 on the branch). Bug 8 (one connection per consumer group — #207) and the DLX
contract note (sound chain + canary proposal) carry over unchanged. The 0.2.0 config
migration is undocumented in the changelog: new extension major, new config keys, dead
knobs removed — see the notes above for the exact surface.

**0.1.6:** the false `--fix` failure is fixed (bug 11 closed to a soft warning with a still-annoying 30 s gate) and the
pipelined flush drops primed publishes in ~0.5 s, but the doctor still fails its two core checks (6, 7), pop is still
non-blocking (9), `size()` still doesn't flush synchronously (10.1) and the cold-pool path can lose one-shot batches
outright, the delay strategies are still cosmetic with early-teardown publishes (12), classic queues cannot be declared
at all with the inherited `delivery_limit` (13 — new in 0.1.6), and the declare path leaves route bindings unwired so
driver-declared queues are unreachable for publishers (14 — new in 0.1.6). One connection per consumer group remains the
only working isolation (8). The DLX chain itself is sound (verified contract + canary — see the note).

**0.1.4/0.1.5** shipped exactly what the changelogs claimed: the status-counter fix is regression-closed, auto-subscribe
synthesis works (A/B proven against the previous extension), `after_commit` is honored end-to-end (rollback cancel +
deferred publish + `bulk()` partitioning), and the full console-command surface behaves as documented. But
`rabbit-rs:doctor` — the command shipped to catch misconfigurations — fails its two core checks out of the box (bugs 6,
7), the fan-out's `--queue` filter is not enforced at consumption time (bug 8), which is a data-isolation defect, not a
doc nit, the publish buffer regressed to fire-and-forget-ish behavior with no force-flush on read and an undocumented
multi-second flush latency (bug 10) — acceptable only if upstream restores 0.0.9's flush-on-read semantics, `--fix`
fails after succeeding (bug 11), and the delay strategies are cosmetic: `ttl`/`plugin` silently degrade to an in-memory
hold that loses deferred jobs at process exit, with safe mode hanging instead of failing (bug 12). Only
`delay.mode=auto` is production-safe today; of the topology modes only `declare` verifies (and it is not this
playground's default).

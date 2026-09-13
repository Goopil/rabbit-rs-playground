// clusterkit edge roast v2: covers the remaining feature surface.
//   node node/ck-edge.mjs <port>
//   GET /            -> ok pid=<pid> tag=<CK_TAG from workers.env>
//   GET /?crash=1    -> worker exits before responding (feeds the breaker)
//   GET /?crashboot=1 -> boot-phase exit (feeds bootFailQuarantine)
//   GET /?block=<ms> -> busy-wait block
//   GET /?bloat=<mb> -> allocate and retain <mb>
// Env toggles: RSS_MAX_MB, MAX_AGE_MS, DEGRADED_MS, CRASH_BOOT=1, BOOT_QUARANTINE=N
import { Orchestrator } from '@goopil/clusterkit';

const port = Number(process.argv[2] || 13950);

const orchestrator = new Orchestrator({
    logger: { info: () => {}, warn: (m) => console.log('[warn]', m), error: (m) => console.log('[err]', m), debug: () => {} },
    workers: {
        count: 2,
        maxRssMb: Number(process.env.RSS_MAX_MB || 0),
        maxAgeMs: Number(process.env.MAX_AGE_MS || 0),
        env: { CK_TAG: process.env.CK_TAG || 'unset' },
    },
    health: { heartbeatMs: 300, wedgedTimeoutMs: 1200, degradedAfterMs: Number(process.env.DEGRADED_MS || 0) },
    restart: {
        crashThreshold: Number(process.env.CRASH_THRESHOLD || 5),
        crashWindowMs: 20000,
        backoffMs: 300,
        maxBackoffMs: 5000,
        bootFailQuarantine: Number(process.env.BOOT_QUARANTINE || 0),
    },
});

const ts = () => String(Date.now() % 100000).padStart(5, '0');
for (const ev of [
    'worker:crash', 'worker:draining', 'worker:recycle', 'worker:quarantined',
    'circuit-breaker:tripped', 'fleet:degraded', 'fleet:recovered', 'restart:start',
]) {
    orchestrator.on(ev, (e) => console.log(ts(), `>>> ${ev}`, JSON.stringify(e)));
}
// Quarantine remedy demo: 5 s after the first quarantine, restartWorkers()
// clears the counters and refills the missing slots.
orchestrator.on('worker:quarantined', () => {
    setTimeout(() => {
        console.log(ts(), '>>> calling restartWorkers()');
        orchestrator.restartWorkers();
    }, 5000);
});
orchestrator.on('worker:health', (e) => console.log(ts(), 'health', e.workerId, e.pid, `rss=${Math.round(e.rss / 1048576)}Mb`, 'lag=' + e.eventLoopLagMs));

const bloat = [];
orchestrator.run(async () => {
    // CRASH_BOOT=<prob 0..1>: boot-phase exit (feeds bootFailQuarantine) with
    // that probability — one bad slot while the rest of the fleet serves.
    const bootFail = Number(process.env.CRASH_BOOT || 0);
    if (bootFail > 0 && Math.random() < bootFail) process.exit(1);
    const { createServer } = await import('node:http');
    const server = createServer((req, res) => {
        const p = new URL(req.url, 'http://x').searchParams;
        if (p.has('crashboot')) process.exit(1);
        if (p.has('crash')) process.exit(1);
        const mb = Number(p.get('bloat') || 0);
        if (mb) {
            bloat.push(Buffer.alloc(mb * 1048576, 1));
            res.end(`bloated pid=${process.pid}`);
            return;
        }
        const ms = Number(p.get('block') || 0);
        const end = Date.now() + ms;
        while (Date.now() < end) { /* busy */ }
        res.end(`ok pid=${process.pid} tag=${process.env.CK_TAG}`);
    });
    server.listen(port, '127.0.0.1', () => console.log('listening', process.pid));
    orchestrator.registerOnShutdown(() => server.close());
});

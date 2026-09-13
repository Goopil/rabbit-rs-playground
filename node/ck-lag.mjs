// clusterkit lag/health verification probe (rewritten after goopil's rebuttal).
//
//   node node/ck-lag.mjs <port>                # 2 workers + sizing plugin, manual blocks
//   NO_PLUGIN=1 node node/ck-lag.mjs <port>    # L2 check: core-only, NO sizing plugin
//   SATURATION=1 node node/ck-lag.mjs <port>   # L1 check: 1 worker, hammer externally
//
// Health contract (goopil, verified 2026-09-10): worker:health beats are CORE
// (health-monitor), the reported value is beat drift = now - lastBeat - interval;
// a sync block makes the overdue beat fire on release with drift ≈ block duration.
// The lag policy targets SUSTAINED saturation — a one-off long block yields a single
// high beat and correctly does not recycle (lagRecycleBeats consecutive beats).
import { Orchestrator } from '@goopil/clusterkit';
import { createContainerSizingPlugin } from '@goopil/clusterkit-sizing';

const port = Number(process.argv[2] || 13900);
const noPlugin = process.env.NO_PLUGIN === '1';

const orchestrator = new Orchestrator({
    logger: { info: () => {}, warn: () => {}, error: (m) => console.log('[err]', m), debug: () => {} },
    workers: { count: process.env.SATURATION === '1' ? 1 : 2 },
    health: { heartbeatMs: 300, maxEventLoopLagMs: 50, lagRecycleBeats: 2 },
});

if (!noPlugin) orchestrator.use(createContainerSizingPlugin());
console.log(`probe up: workers=${process.env.SATURATION === '1' ? 1 : 2} sizing=${noPlugin ? 'OFF' : 'ON'}`);

const ts = () => String(Date.now() % 100000).padStart(5, '0');
orchestrator.on('worker:health', (e) => console.log(ts(), 'health', e.workerId, e.pid, 'lag=' + e.eventLoopLagMs));
orchestrator.on('worker:draining', (e) => console.log(ts(), '>>> draining', JSON.stringify(e)));
orchestrator.on('worker:recycle', (e) => console.log(ts(), '>>> recycle', JSON.stringify(e)));

orchestrator.run(async () => {
    const { createServer } = await import('node:http');
    const server = createServer((req, res) => {
        const ms = Number(new URL(req.url, 'http://x').searchParams.get('block') || 0);
        if (ms) console.log(`[${process.pid}] block ${ms}ms start`);
        const end = Date.now() + ms;
        while (Date.now() < end) { /* busy-wait: sync event-loop block */ }
        res.end(`ok pid=${process.pid}`);
    });
    server.listen(port, '127.0.0.1', () => console.log('listening', process.pid));
    orchestrator.registerOnShutdown(() => server.close());
});

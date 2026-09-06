import http from 'node:http';
import { Orchestrator } from '@goopil/clusterkit';
import { createContainerSizingPlugin } from '@goopil/clusterkit-sizing';
import { createPrometheusPlugin } from '@goopil/clusterkit-prometheus';

const SSR_PORT = Number(process.env.SSR_PORT || 13715);
const SSR_HOST = process.env.SSR_HOST || '127.0.0.1';
const SSR_METRICS_PORT = Number(process.env.SSR_METRICS_PORT || 13716);

const orchestrator = new Orchestrator({
    logger: console,
    workers: {
        count: Number(process.env.WEB_CONCURRENCY) || 'auto',
        maxRssMb: Number(process.env.SSR_MAX_RSS_MB) || 0,
    },
    // RSS recycling is fed by worker health heartbeats — disabled without them.
    health: { heartbeatMs: Number(process.env.SSR_HEARTBEAT_MS) || 10000 },
});

// compileCache: injects NODE_COMPILE_CACHE so recycled workers skip recompiling
// the SSR bundle. Cache dir is content-hash keyed, tmpfs-safe.
const sizing = createContainerSizingPlugin({ compileCache: true });
const prometheus = createPrometheusPlugin({ prefix: 'clusterkit_' });

// serve() binds /metrics + /healthz in the primary only (no-op in workers)
// and closes the server on shutdown.
prometheus.serve({ port: SSR_METRICS_PORT, host: SSR_HOST });

orchestrator.use(sizing).use(prometheus).run(async () => {
    const capabilities = await Orchestrator.getCapabilities();
    const { default: render } = await import('../bootstrap/ssr/ssr.js');

    const server = http.createServer(async (req, res) => {
        if (req.method !== 'POST' || req.url !== '/render') {
            res.statusCode = 404;
            return res.end();
        }

        let raw = '';
        for await (const chunk of req) raw += chunk;

        try {
            const result = await render(JSON.parse(raw));
            res.setHeader('Content-Type', 'application/json');
            res.end(JSON.stringify(result));
        } catch (error) {
            res.statusCode = 500;
            res.end(String(error));
        }
    });

    server.listen({
        port: SSR_PORT,
        host: SSR_HOST,
        reusePort: capabilities.reusePort,
        exclusive: capabilities.reusePort,
    });
    orchestrator.registerOnShutdown(() => server.close());
});

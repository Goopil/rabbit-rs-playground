import http from 'node:http';
import { Orchestrator } from '@goopil/clusterkit';

const SSR_PORT = Number(process.env.SSR_PORT || 13715);
const SSR_HOST = process.env.SSR_HOST || '127.0.0.1';

const orchestrator = new Orchestrator({
    logger: console,
    workers: { count: Number(process.env.WEB_CONCURRENCY) || 'auto' },
});

orchestrator.run(async () => {
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

# ClusterkitExamples — @goopil/clusterkit SSR pool example

Didactic example for the Inertia SSR pool orchestrated by
`@goopil/clusterkit`: fire renders at the supervisord `ssr` program and read
the accounting. The roast-battery probes (`ck-roast.mjs` and siblings) live
outside the suite — see `node/README.md`.

## Commands

| Command | Demonstrates |
|---------|--------------|
| `examples:clusterkit:render --count=20` | `Http::post($ssrBase."/render", [...])` — fetch → ClusterKit worker fan-out → rendered/total accounting |
| `examples:clusterkit:render --url=http://127.0.0.1:PORT` | Override the SSR base URL (default `http://127.0.0.1:{SSR_PORT}`, env default `13715`) |

## Wiring notes (cheatsheet)

- The SSR server is the supervisord `ssr` program (`docker/8.5/supervisord.conf`)
  running `node/clusterkit-server.mjs` — a ClusterKit `Orchestrator`
  (`WEB_CONCURRENCY` workers) bound to `http://127.0.0.1:{SSR_PORT}`
  (env default `13715`), accepting POST `/render` with
  `{component, props, ...}`.
- Ports: main pool on `SSR_PORT` (`13715`); Prometheus `/metrics` + `/healthz`
  on `SSR_METRICS_PORT` (`13716`) in the primary only.
- **Restart gotcha:** workers import `bootstrap/ssr/ssr.js` once at startup —
  after `npm run build:ssr`, run `supervisorctl restart ssr` or renders keep
  serving the old bundle.

## Demo page

`/clusterkit-demo` (`Demo.jsx`) deliberately demonstrates the SSR-vs-client
hydration badge — "Rendered by: Server (ClusterKit SSR)" on the initial
response, "Browser (client hydration)" after hydration.

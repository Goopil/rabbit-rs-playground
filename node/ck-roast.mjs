// SSR pool roast probe: fires N x batch renders, prints exact accounting.
// Usage: node node/ck-roast.mjs <base-url> <n> <batch>
const [url = 'http://127.0.0.1:13715', n = '20', batch = '15'] = process.argv.slice(2);
const props = {
    auth: { user: { name: 'roast', email: 'roast@lab' } },
    ziggy: {
        location: 'http://localhost/roast', url: '',
        routes: {
            dashboard: { uri: 'dashboard', methods: ['GET', 'HEAD'] },
            login: { uri: 'login', methods: ['GET', 'HEAD'] },
            register: { uri: 'register', methods: ['GET', 'HEAD'] },
            'profile.edit': { uri: 'profile/edit', methods: ['GET', 'HEAD'] },
            logout: { uri: 'logout', methods: ['POST'] },
        },
    },
};
const payload = JSON.stringify({
    component: 'Welcome',
    props,
    url: '/roast',
    version: 'legacy-dirty',
});
let ok = 0, fail5xx = 0, lost = 0, firstError = '';
async function one() {
    try {
        const r = await fetch(url + '/render', { method: 'POST', body: payload });
        if (r.status === 200) { ok++; } else {
            fail5xx++;
            if (!firstError) firstError = (await r.text()).slice(0, 200);
        }
    } catch { lost++; }
}
const t0 = Date.now();
await Promise.all(Array.from({ length: Number(n) }, () =>
    (async () => { for (let j = 0; j < Number(batch); j++) await one(); })()));
console.log(JSON.stringify({ sent: Number(n) * Number(batch), ok, fail5xx, lost, ms: Date.now() - t0, firstError }));

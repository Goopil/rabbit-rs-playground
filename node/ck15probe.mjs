// Bug 15.1 clean-state observer: timestamped depth trace of work + delay buckets.
const base = 'http://rabbitmq-simple:15672/api';
const auth = 'Basic ' + Buffer.from('guest:guest').toString('base64');
const depth = async (name) => {
    const r = await fetch(`${base}/queues/%2F/${encodeURIComponent(name)}`, { headers: { Authorization: auth } });
    if (r.status !== 200) return -1;
    return (await r.json()).messages_ready ?? 0;
};
const buckets = async () => {
    const r = await fetch(`${base}/queues/%2F`, { headers: { Authorization: auth } });
    return (await r.json()).map((q) => q.name).filter((n) => n.startsWith('rabbit-rs.delay.'));
};
const t0 = Date.now();
const t = () => `+${((Date.now() - t0) / 1000).toFixed(1)}s`;
await new Promise((r) => setTimeout(r, 500));
console.log('start observing');
for (let i = 0; i < 10; i++) {
    const w = await depth('work');
    const bs = await buckets();
    const bdepths = [];
    for (const b of bs) bdepths.push(`${b.split('.').pop()}=${await depth(b)}`);
    console.log(`t${t()} work=${w} buckets[${bdepths.join(' ')}]`);
    await new Promise((r) => setTimeout(r, 500));
}

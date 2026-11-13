import { test } from 'node:test';
import assert from 'node:assert/strict';
import registerAvailability from '../resources/js/seeker/listingAvailability.js';

function setup(t) {
    const originals = new Map();
    const replace = (key, value) => {
        originals.set(key, globalThis[key]);
        globalThis[key] = value;
    };
    const intervals = [];
    const cleared = [];
    const requests = [];
    replace('document', { hidden: false, addEventListener() {} });
    replace('window', { location: { origin: 'https://napc.test' }, addEventListener() {} });
    replace('setInterval', (callback, delay) => { intervals.push({ callback, delay }); return intervals.length; });
    replace('clearInterval', (id) => cleared.push(id));
    replace('setTimeout', () => 1);
    replace('clearTimeout', () => {});
    replace('fetch', async (url) => {
        requests.push(url);
        return { ok: true, json: async () => ({ listings: { 1: { remaining: 1, label: '1 of 2 slots left' } } }) };
    });
    t.after(() => { for (const [key, value] of originals) globalThis[key] = value; });
    let store;
    registerAvailability({
        store(name, value) { if (value) store = value; return store; },
        data() {},
    });
    return { store, intervals, cleared, requests };
}

test('duplicate widgets share one five-second poll and one request per page', async (t) => {
    const { store, intervals, requests, cleared } = setup(t);
    store.register('1', { remaining: 2 }, '/listings/availability');
    store.register('1', { remaining: 2 }, '/listings/availability');
    store.register('2', { remaining: 3 }, '/listings/availability');

    await store.refresh();

    assert.equal(intervals.length, 1);
    assert.equal(intervals[0].delay, 5000);
    assert.equal(requests.length, 1);
    assert.deepEqual(requests[0].searchParams.getAll('ids[]'), ['1', '2']);
    assert.equal(store.items['1'].remaining, 1);
    assert.equal(store.items['2'].unavailable, true);
    store.unregister('1');
    assert.equal(store.references['1'], 1);
    store.unregister('1');
    store.unregister('2');
    assert.equal(cleared.length, 1);
});

test('hidden tabs and in-flight requests do not create extra polls', async (t) => {
    const { store, requests } = setup(t);
    store.register('1', { remaining: 2 }, '/listings/availability');
    document.hidden = true;
    await store.refresh();
    assert.equal(requests.length, 0);
    document.hidden = false;
    store.busy = true;
    await store.refresh();
    assert.equal(requests.length, 0);
    store.busy = false;
    await store.refresh();
    assert.equal(requests.length, 1);
});

test('failed requests keep the last count and recover on the next poll', async (t) => {
    const { store } = setup(t);
    store.register('1', { remaining: 2, label: '2 of 2 slots left' }, '/listings/availability');
    globalThis.fetch = async () => { throw new Error('Offline'); };

    await store.refresh();

    assert.equal(store.stale, true);
    assert.equal(store.busy, false);
    assert.equal(store.items['1'].remaining, 2);
    globalThis.fetch = async () => ({ ok: true, json: async () => ({ listings: { 1: { remaining: 0 } } }) });
    await store.refresh();
    assert.equal(store.stale, false);
    assert.equal(store.items['1'].remaining, 0);
});

test('large pages split into bounded batches instead of failing the endpoint limit', async (t) => {
    const { store, requests } = setup(t);
    for (let id = 1; id <= 51; id++) store.register(String(id), { remaining: 2 }, '/listings/availability');

    await store.refresh();

    assert.equal(requests.length, 2);
    assert.equal(requests[0].searchParams.getAll('ids[]').length, 50);
    assert.equal(requests[1].searchParams.getAll('ids[]').length, 1);
});

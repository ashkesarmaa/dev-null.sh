import assert from 'node:assert/strict';
import * as React from 'react';
import { afterEach, beforeEach, test, vi } from 'vite-plus/test';
import type { Commit } from '../../resources/js/types';

vi.doMock('react', () => ({
    ...React,
    useState: (initial: () => unknown) => [initial(), () => {}],
    useEffect: (effect: () => void) => effect(),
}));

const { useLastVisit } = await import('../../resources/js/hooks/use-last-visit');

const newestAt = '2026-10-20T10:00:00.000000Z';
const commits = [
    { id: 'newest', user_id: 'someone', created_at: newestAt },
    { id: 'older', user_id: 'someone', created_at: '2026-10-19T10:00:00.000000Z' },
] as Commit[];

let storage: Map<string, string>;
let cookies: string[];

beforeEach(() => {
    storage = new Map();
    cookies = [];
    vi.stubGlobal('window', {
        localStorage: {
            getItem: (key: string) => storage.get(key) ?? null,
            setItem: (key: string, value: string) => storage.set(key, value),
            removeItem: (key: string) => storage.delete(key),
        },
    });
    vi.stubGlobal('document', {
        set cookie(value: string) {
            cookies.push(value);
        },
    });
});

afterEach(() => {
    vi.unstubAllGlobals();
});

test('remembers the newest commit seen in Following for the where-you-left-off mark and the catch-up in one write', () => {
    useLastVisit('viewer', 'following', commits);

    assert.equal(storage.get('last-visit:viewer:following'), newestAt);
    assert.equal(cookies.length, 1);
    assert.ok(cookies[0].startsWith(`following_last_visit=viewer|${newestAt};`));
});

test('only sends the last visit to Following to the server', () => {
    useLastVisit('viewer', 'latest', commits);

    assert.equal(storage.get('last-visit:viewer:latest'), newestAt);
    assert.deepEqual(cookies, []);
});

test('remembers nothing before a commit is shown', () => {
    useLastVisit('viewer', 'following', []);

    assert.equal(storage.size, 0);
    assert.deepEqual(cookies, []);
});

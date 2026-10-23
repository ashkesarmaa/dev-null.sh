import { useEffect, useState } from 'react';
import { setCookie } from '@/hooks/use-appearance';
import { readStorage, writeStorage } from '@/lib/storage';
import type { Commit, Log } from '@/types';

const keyFor = (userId: string, log: Log): string =>
    `last-visit:${userId}:${log}`;

/**
 * Remembers when the newest commit the user saw in a log was committed. Following's is also sent to the server, so it can
 * catch them up from the same point as the page loads.
 */
export function useLastVisit(
    userId: string,
    log: Log,
    commits: Commit[],
): string | null {
    const key = keyFor(userId, log);
    const [lastVisitAt] = useState(() => readStorage(key));
    const newestAt = commits[0]?.created_at;

    useEffect(() => {
        if (newestAt === undefined) {
            return;
        }

        writeStorage(key, newestAt);

        if (log === 'following') {
            setCookie('following_last_visit', `${userId}|${newestAt}`);
        }
    }, [key, log, userId, newestAt]);

    if (lastVisitAt === '') {
        return null;
    }

    const firstSeen = commits.findIndex(
        (commit) => Date.parse(commit.created_at) <= Date.parse(lastVisitAt),
    );
    const isNewFromOthers = commits
        .slice(0, Math.max(firstSeen, 0))
        .some((commit) => commit.user_id !== userId);

    return isNewFromOthers ? commits[firstSeen].id : null;
}

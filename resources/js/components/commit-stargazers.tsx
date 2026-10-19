import { Link, router, usePage } from '@inertiajs/react';
import type { CSSProperties } from 'react';
import { useEffect, useEffectEvent, useRef, useState } from 'react';
import { index as stargazersIndex } from '@/actions/App/Http/Controllers/CommitStargazerController';
import { ActionTooltip } from '@/components/action-tooltip';
import { FollowButton } from '@/components/follow-button';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { UserAvatar } from '@/components/user-avatar';
import { formatCount } from '@/lib/numbers';
import { cn, laneColor } from '@/lib/utils';
import { show } from '@/routes/user';
import type { Commit, CursorPaginated, User } from '@/types';

type Stargazer = User & { is_followed: boolean };

type Stargazers = ReturnType<typeof useStargazers>;

/**
 * The people who starred a commit, a page at a time, newest star first. Loading the first page again starts over, so
 * reopening the list shows stars given since.
 */
function useStargazers(commit: Pick<Commit, 'id'>) {
    const [people, setPeople] = useState<Stargazer[]>([]);
    const [cursor, setCursor] = useState<string | null>(null);
    const [isDone, setIsDone] = useState(false);
    const [isLoading, setIsLoading] = useState(false);
    const [failed, setFailed] = useState(false);
    const loading = useRef(false);

    const load = async (next: string | null) => {
        if (loading.current) {
            return;
        }

        loading.current = true;
        setIsLoading(true);
        setFailed(false);

        try {
            const response = await fetch(
                stargazersIndex(commit, { query: { cursor: next } }).url,
                { headers: { Accept: 'application/json' } },
            );

            if (!response.ok) {
                throw new Error('Unable to load stargazers');
            }

            const data = (await response.json()) as CursorPaginated<Stargazer>;

            setPeople((previous) =>
                next ? [...previous, ...data.data] : data.data,
            );
            setCursor(data.next_cursor);
            setIsDone(data.next_cursor === null);
        } catch {
            setFailed(true);
        } finally {
            loading.current = false;
            setIsLoading(false);
        }
    };

    return { people, cursor, isDone, isLoading, failed, load };
}

/**
 * The star count opens the people behind it, like GitHub's stargazers, so the count is a button of its own beside the star.
 */
export function CommitStargazersButton({
    commit,
    count,
    className,
}: {
    commit: Pick<Commit, 'id'>;
    count: number;
    className?: string;
}) {
    const [isOpen, setIsOpen] = useState(false);
    const stargazers = useStargazers(commit);
    const noun = count === 1 ? 'stargazer' : 'stargazers';

    return (
        <>
            <ActionTooltip label="Stargazers">
                <button
                    type="button"
                    onClick={() => {
                        setIsOpen(true);
                        void stargazers.load(null);
                    }}
                    aria-haspopup="dialog"
                    aria-label={`See ${count} ${noun}`}
                    className={cn(
                        'min-w-[1ch] rounded-md px-1 py-1 text-left font-mono text-[13px] font-medium underline-offset-2 transition-colors hover:text-foreground hover:underline',
                        className,
                    )}
                    data-test="star-count"
                >
                    {formatCount(count)}
                </button>
            </ActionTooltip>
            <Dialog open={isOpen} onOpenChange={setIsOpen}>
                <DialogContent
                    className="gap-0 p-0 sm:max-w-md"
                    data-test="stargazers-dialog"
                >
                    <DialogHeader className="border-b px-5 py-4 text-left">
                        <DialogTitle className="text-base">
                            Stargazers
                        </DialogTitle>
                        <DialogDescription className="font-mono text-[13px]">
                            {formatCount(count)} {noun}
                        </DialogDescription>
                    </DialogHeader>
                    <StargazerList
                        stargazers={stargazers}
                        count={count}
                        onNavigate={() => setIsOpen(false)}
                    />
                </DialogContent>
            </Dialog>
        </>
    );
}

/**
 * Loads the next page as the end of the list scrolls into view.
 */
function StargazerList({
    stargazers,
    count,
    onNavigate,
}: {
    stargazers: Stargazers;
    count: number;
    onNavigate: () => void;
}) {
    const { auth } = usePage().props;
    const { people, cursor, isDone, isLoading, failed, load } = stargazers;
    const end = useRef<HTMLDivElement>(null);
    const loadMore = useEffectEvent(() => void load(cursor));

    useEffect(() => {
        const node = end.current;

        if (!node || isDone || failed || people.length === 0) {
            return;
        }

        const observer = new IntersectionObserver(([entry]) => {
            if (entry?.isIntersecting) {
                loadMore();
            }
        });

        observer.observe(node);

        return () => observer.disconnect();
    }, [cursor, isDone, failed, people.length]);

    const placeholders = isLoading
        ? Math.max(1, Math.min(count - people.length, 3))
        : 0;

    return (
        <div className="max-h-[min(28rem,70svh)] overflow-y-auto">
            {isDone && people.length === 0 ? (
                <p className="px-5 py-10 text-center font-mono text-[13px] text-muted-foreground">
                    fatal: no stargazers to show
                </p>
            ) : (
                <ul className="divide-y">
                    {people.map((person) => (
                        <li
                            key={person.id}
                            className="flex items-center gap-3 pr-5 hover:bg-muted/50"
                            data-test="stargazer"
                        >
                            <Link
                                href={show(person)}
                                onClick={onNavigate}
                                className="flex min-w-0 flex-1 items-center gap-3 py-3 pl-5"
                            >
                                <UserAvatar
                                    user={person}
                                    className="size-9 ring-2 ring-(--lane) ring-offset-2 ring-offset-background"
                                    style={
                                        {
                                            '--lane': laneColor(person.lane),
                                        } as CSSProperties
                                    }
                                />
                                <div className="min-w-0">
                                    <p className="truncate font-medium">
                                        {person.name}
                                    </p>
                                    <p className="truncate font-mono text-[13px] text-muted-foreground">
                                        @{person.username}
                                    </p>
                                </div>
                            </Link>
                            {person.id !== auth.user?.id && (
                                <FollowButton
                                    user={person}
                                    isFollowing={person.is_followed}
                                    size="sm"
                                    className="min-w-24 shrink-0"
                                    only={['auth']}
                                    preserveState
                                    preserveScroll
                                    onSuccess={() => router.flushAll()}
                                />
                            )}
                        </li>
                    ))}
                    {Array.from({ length: placeholders }, (_, row) => (
                        <li
                            key={`placeholder-${row}`}
                            aria-hidden
                            className="flex items-center gap-3 px-5 py-3 motion-safe:animate-pulse"
                        >
                            <span className="size-9 rounded-full bg-muted" />
                            <span className="grid flex-1 gap-1.5">
                                <span className="h-3 w-2/5 rounded bg-muted" />
                                <span className="h-3 w-1/4 rounded bg-muted" />
                            </span>
                        </li>
                    ))}
                </ul>
            )}
            {isLoading && (
                <p role="status" className="sr-only">
                    Loading stargazers…
                </p>
            )}
            {failed && (
                <div
                    role="alert"
                    className="flex flex-wrap items-center gap-3 px-5 py-4"
                >
                    <p className="font-mono text-[13px] text-muted-foreground">
                        fatal: could not load stargazers
                    </p>
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => void load(cursor)}
                    >
                        Try again
                    </Button>
                </div>
            )}
            <div ref={end} aria-hidden className="h-px" />
        </div>
    );
}

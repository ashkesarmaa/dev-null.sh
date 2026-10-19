import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import { update } from '@/actions/App/Http/Controllers/UserRemotesController';
import { Switch } from '@/components/ui/switch';

/**
 * Remotes are on by default. Turning them off hides the Remotes page and stops anyone messaging the user.
 */
export function RemotesSetting() {
    const { auth } = usePage().props;
    const enabled = auth.remotes !== null;
    const [processing, setProcessing] = useState(false);

    const toggle = () => {
        if (processing) {
            return;
        }

        router.put(
            update.url(),
            { enabled: !enabled },
            {
                preserveScroll: true,
                only: ['auth'],
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onError: () =>
                    toast.error('Could not save remotes. Please try again.'),
                onSuccess: () => router.flushAll(),
            },
        );
    };

    return (
        <section className="px-4 py-6 sm:px-6">
            <div className="flex items-start justify-between gap-6">
                <div className="min-w-0 space-y-2">
                    <h2 id="remotes-label" className="text-base font-semibold">
                        Remotes
                    </h2>
                    <p
                        id="remotes-description"
                        className="text-sm text-muted-foreground"
                    >
                        One-to-one messages with other developers. People you
                        follow can push to you, and you can push to people who
                        follow you. Whether you share pulls (read receipts) is
                        up to you, from Remotes.
                    </p>
                    <p className="text-sm text-muted-foreground">
                        When off, nobody can message you and Remotes is hidden.
                        Your conversations are kept for when you turn it back
                        on.
                    </p>
                </div>
                <Switch
                    checked={enabled}
                    onCheckedChange={toggle}
                    aria-labelledby="remotes-label"
                    aria-describedby="remotes-description"
                    aria-disabled={processing}
                    data-test="remotes-setting"
                />
            </div>
        </section>
    );
}

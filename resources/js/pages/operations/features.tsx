import { Head, router } from '@inertiajs/react';
import { useId, useState } from 'react';
import { toast } from 'sonner';
import FeatureController from '@/actions/App/Http/Controllers/FeatureController';
import { Section } from '@/components/operations/section';
import { Switch } from '@/components/ui/switch';
import AppLayout from '@/layouts/app-layout';
import OperationsLayout from '@/layouts/operations/layout';
import { formatCount } from '@/lib/numbers';
import type { Feature } from '@/types';

const FEATURES: Record<
    Feature['name'],
    { label: string; description: string }
> = {
    translations: {
        label: 'translations',
        description:
            'The Translate control, cached translations, and background language detection. Off stops new detection jobs from being queued and skips any already waiting.',
    },
};

function FeatureRow({ feature }: { feature: Feature }) {
    const [processing, setProcessing] = useState(false);
    const labelId = useId();
    const descriptionId = useId();
    const { label, description } = FEATURES[feature.name];

    const toggle = () => {
        if (processing) {
            return;
        }

        const enabled = !feature.enabled;

        router.put(
            FeatureController.update.url(feature.name),
            { enabled },
            {
                only: ['features'],
                preserveState: true,
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onSuccess: () =>
                    toast(
                        <span className="font-mono text-[13px]">
                            {enabled
                                ? `Switched ${label} on for everyone`
                                : `Switched ${label} off for everyone`}
                        </span>,
                    ),
                onError: () =>
                    toast.error(
                        <span className="font-mono text-[13px]">
                            fatal: could not switch {label}{' '}
                            {enabled ? 'on' : 'off'}
                        </span>,
                    ),
            },
        );
    };

    return (
        <li
            className="flex items-start justify-between gap-6 px-4 py-3"
            data-test="feature"
        >
            <div className="min-w-0 space-y-1">
                <p id={labelId} className="font-mono text-[13px] font-medium">
                    {label}
                </p>
                <p id={descriptionId} className="text-sm text-muted-foreground">
                    {description}
                </p>
            </div>
            <Switch
                checked={feature.enabled}
                onCheckedChange={toggle}
                aria-labelledby={labelId}
                aria-describedby={descriptionId}
                aria-disabled={processing}
                data-test={`feature-${feature.name}-switch`}
            />
        </li>
    );
}

export default function Index({ features }: { features: Feature[] }) {
    return (
        <>
            <Head title="Feature switches" />

            <Section
                title="Features"
                meta={`${formatCount(features.filter((feature) => !feature.enabled).length)} off`}
            >
                <ul className="divide-y rounded-lg border">
                    {features.map((feature) => (
                        <FeatureRow key={feature.name} feature={feature} />
                    ))}
                </ul>
            </Section>
        </>
    );
}

Index.layout = [AppLayout, OperationsLayout];

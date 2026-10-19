import type { ComponentProps } from 'react';
import { cn } from '@/lib/utils';

/**
 * An on/off toggle. Given a `name`, it also sends its state with the form it is in, as `1` or `0`.
 */
export function Switch({
    checked,
    onCheckedChange,
    name,
    className,
    ...props
}: Omit<ComponentProps<'button'>, 'type' | 'role' | 'onClick'> & {
    checked: boolean;
    onCheckedChange: (checked: boolean) => void;
}) {
    return (
        <>
            {name && (
                <input type="hidden" name={name} value={checked ? '1' : '0'} />
            )}
            <button
                type="button"
                role="switch"
                aria-checked={checked}
                onClick={() => onCheckedChange(!checked)}
                className={cn(
                    'mt-0.5 inline-flex h-6 w-11 shrink-0 items-center rounded-full border border-transparent p-0.5 transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring',
                    checked ? 'bg-primary' : 'bg-muted-foreground/30',
                    className,
                )}
                {...props}
            >
                <span
                    className={cn(
                        'size-4.5 rounded-full bg-background shadow-sm transition-transform motion-reduce:transition-none',
                        checked && 'translate-x-5',
                    )}
                />
            </button>
        </>
    );
}

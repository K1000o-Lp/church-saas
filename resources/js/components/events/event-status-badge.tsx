import { cn } from '@/lib/utils';
import type { EventStatusValue } from '@/types';

const styles: Record<EventStatusValue, string> = {
    draft: 'border-border bg-secondary/60 text-foreground',
    published: 'border-primary/30 bg-primary/10 text-primary',
    cancelled: 'border-destructive/25 bg-destructive/10 text-destructive',
    completed: 'border-accent/40 bg-accent/10 text-accent',
};

export function EventStatusBadge({
    status,
    label,
}: {
    status: EventStatusValue;
    label: string;
}) {
    return (
        <span
            className={cn(
                'inline-flex rounded-full border px-2.5 py-0.5 text-xs font-semibold',
                styles[status] ?? 'border-border bg-secondary/60 text-foreground',
            )}
        >
            {label}
        </span>
    );
}

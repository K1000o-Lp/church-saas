import { cn } from '@/lib/utils';
import type { ChurchStatusValue } from '@/types';

const styles: Record<ChurchStatusValue, string> = {
    active: 'border-primary/25 bg-primary/10 text-primary',
    suspended: 'border-accent/35 bg-accent/10 text-accent',
    inactive: 'border-border bg-secondary text-muted-foreground',
    archived: 'border-border bg-background text-muted-foreground',
};

export function ChurchStatusBadge({
    status,
    label,
    className,
}: {
    status: ChurchStatusValue;
    label: string;
    className?: string;
}) {
    return (
        <span
            className={cn(
                'inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-semibold',
                styles[status],
                className,
            )}
        >
            <span
                aria-hidden="true"
                className={cn(
                    'size-1.5 rounded-full',
                    status === 'active' && 'bg-primary',
                    status === 'suspended' && 'bg-accent',
                    (status === 'inactive' || status === 'archived') && 'bg-muted-foreground',
                )}
            />
            {label}
        </span>
    );
}

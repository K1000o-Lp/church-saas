import { cn } from '@/lib/utils';
import type { RegistrationStatusValue } from '@/types';

const styles: Record<RegistrationStatusValue, string> = {
    registered: 'border-primary/30 bg-primary/10 text-primary',
    confirmed: 'border-primary/30 bg-primary/10 text-primary',
    declined: 'border-destructive/25 bg-destructive/10 text-destructive',
    waitlisted: 'border-accent/40 bg-accent/10 text-accent',
    cancelled: 'border-border bg-secondary/60 text-muted-foreground',
};

export function RegistrationStatusBadge({
    status,
    label,
}: {
    status: RegistrationStatusValue;
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

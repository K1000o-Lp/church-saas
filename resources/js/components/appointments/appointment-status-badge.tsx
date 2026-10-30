import { cn } from '@/lib/utils';
import type { AppointmentStatusValue } from '@/types';

const styles: Record<AppointmentStatusValue, string> = {
    scheduled: 'border-primary/30 bg-primary/10 text-primary',
    rescheduled: 'border-accent/40 bg-accent/10 text-accent',
    completed: 'border-border bg-secondary/60 text-foreground',
    cancelled: 'border-destructive/25 bg-destructive/10 text-destructive',
    no_show: 'border-accent/40 bg-accent/10 text-accent',
};

export function AppointmentStatusBadge({
    status,
    label,
}: {
    status: AppointmentStatusValue;
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

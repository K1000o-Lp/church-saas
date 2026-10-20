import { cn } from '@/lib/utils';
import type { InvoiceStatusValue } from '@/types';

const styles: Record<InvoiceStatusValue, string> = {
    issued: 'border-border bg-secondary text-foreground',
    paid: 'border-primary/25 bg-primary/10 text-primary',
    overdue: 'border-destructive/25 bg-destructive/10 text-destructive',
    void: 'border-border bg-background text-muted-foreground',
};

const dotStyles: Record<InvoiceStatusValue, string> = {
    issued: 'bg-foreground/60',
    paid: 'bg-primary',
    overdue: 'bg-destructive',
    void: 'bg-muted-foreground',
};

export function InvoiceStatusBadge({
    status,
    label,
    className,
}: {
    status: InvoiceStatusValue;
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
            <span aria-hidden="true" className={cn('size-1.5 rounded-full', dotStyles[status])} />
            {label}
        </span>
    );
}

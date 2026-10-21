import type { PlanUsageItem } from '@/types';

export function UsageRow({ item }: { item: PlanUsageItem }) {
    const percent =
        item.limit === null || item.limit === 0
            ? item.used > 0 && item.limit === 0
                ? 100
                : 0
            : Math.min(100, Math.round((item.used / item.limit) * 100));

    return (
        <li className="space-y-2 py-4 first:pt-0 last:pb-0">
            <div className="flex flex-wrap items-baseline justify-between gap-2">
                <div className="flex items-center gap-2">
                    <p className="text-sm font-semibold">{item.label}</p>
                    {item.source === 'override' && (
                        <span className="rounded-full border border-accent/40 bg-accent/10 px-2 py-0.5 text-[0.68rem] font-semibold text-accent">
                            Ajuste de plataforma
                        </span>
                    )}
                </div>
                <p className="text-sm tabular-nums">
                    <span
                        className={
                            item.exceeded ? 'font-semibold text-destructive' : 'font-semibold'
                        }
                    >
                        {item.used}
                    </span>
                    <span className="text-muted-foreground">
                        {' '}
                        / {item.unlimited ? 'Ilimitado' : item.limit} {item.unit}
                    </span>
                </p>
            </div>
            <div
                className="h-2 overflow-hidden rounded-full bg-secondary"
                role="progressbar"
                aria-label={`${item.label}: ${item.used} de ${item.limit ?? 'ilimitado'}`}
                aria-valuenow={item.used}
                aria-valuemin={0}
                aria-valuemax={item.limit ?? undefined}
            >
                <div
                    className={
                        item.exceeded
                            ? 'h-full rounded-full bg-destructive'
                            : 'h-full rounded-full bg-primary'
                    }
                    style={{ width: `${percent}%` }}
                />
            </div>
            <p className="text-xs text-muted-foreground">{item.description}</p>
        </li>
    );
}

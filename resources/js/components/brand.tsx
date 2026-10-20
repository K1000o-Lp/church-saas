import { Link } from '@inertiajs/react';

import { cn } from '@/lib/utils';

export function BrandMark({ className }: { className?: string }) {
    return (
        <span
            className={cn(
                'grid size-9 place-items-center rounded-lg bg-primary text-primary-foreground',
                className,
            )}
            aria-hidden="true"
        >
            <svg viewBox="0 0 24 24" fill="none" className="size-5">
                <path
                    d="M5.5 19V11.2a6.5 6.5 0 0 1 13 0V19"
                    stroke="currentColor"
                    strokeWidth="1.8"
                    strokeLinecap="round"
                />
                <path d="M9 19v-3.6a3 3 0 0 1 6 0V19" stroke="currentColor" strokeWidth="1.8" />
                <circle cx="12" cy="6.5" r="1.4" fill="currentColor" className="text-accent" />
            </svg>
        </span>
    );
}

export function Brand({ compact = false }: { compact?: boolean }) {
    return (
        <Link href="/" className="group flex items-center gap-3 no-underline">
            <BrandMark className="transition-transform group-hover:-translate-y-0.5" />
            {!compact && (
                <span className="flex flex-col leading-none">
                    <span className="font-display text-base font-semibold text-foreground">
                        Church SaaS
                    </span>
                    <span className="mt-1 text-[0.68rem] font-semibold tracking-[0.18em] text-muted-foreground uppercase">
                        Gestión pastoral
                    </span>
                </span>
            )}
        </Link>
    );
}

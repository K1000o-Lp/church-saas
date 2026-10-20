import { ChevronDown } from 'lucide-react';
import type { ComponentProps } from 'react';

import { cn } from '@/lib/utils';

function Select({ className, children, ...props }: ComponentProps<'select'>) {
    return (
        <div className="relative">
            <select
                data-slot="select"
                className={cn(
                    'h-10 w-full appearance-none rounded-lg border border-input bg-card py-2 pr-9 pl-3 text-sm text-foreground shadow-xs outline-none transition-colors',
                    'focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/25',
                    'aria-invalid:border-destructive aria-invalid:ring-destructive/20',
                    'disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50',
                    className,
                )}
                {...props}
            >
                {children}
            </select>
            <ChevronDown
                aria-hidden="true"
                className="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-muted-foreground"
            />
        </div>
    );
}

export { Select };

import type { ComponentProps } from 'react';

import { cn } from '@/lib/utils';

function Input({ className, type, ...props }: ComponentProps<'input'>) {
    return (
        <input
            type={type}
            data-slot="input"
            className={cn(
                'h-10 w-full min-w-0 rounded-lg border border-input bg-card px-3 py-2 text-sm text-foreground shadow-xs outline-none transition-colors',
                'placeholder:text-muted-foreground/70',
                'focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/25',
                'aria-invalid:border-destructive aria-invalid:ring-destructive/20',
                'disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50',
                className,
            )}
            {...props}
        />
    );
}

export { Input };

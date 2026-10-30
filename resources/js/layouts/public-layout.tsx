import { usePage } from '@inertiajs/react';
import { Church } from 'lucide-react';
import type { ReactNode } from 'react';

import { Brand } from '@/components/brand';
import type { PageProps } from '@/types';

type Props = {
    churchName?: string | null;
    logoUrl?: string | null;
    children: ReactNode;
};

export function PublicLayout({ churchName, logoUrl, children }: Props) {
    const { props } = usePage<PageProps>();
    const flash = props.flash;

    return (
        <div className="min-h-screen bg-background">
            <header className="border-b border-border bg-card/70">
                <div className="mx-auto flex max-w-5xl items-center justify-between gap-4 px-4 py-4 sm:px-6">
                    <div className="flex min-w-0 items-center gap-3">
                        <Brand compact />
                        {churchName && (
                            <span className="hidden truncate border-l border-border pl-3 text-sm font-semibold sm:block">
                                {churchName}
                            </span>
                        )}
                    </div>
                    {logoUrl ? (
                        <img
                            src={logoUrl}
                            alt={churchName ?? 'Logo de la iglesia'}
                            className="h-9 w-auto max-w-32 object-contain"
                        />
                    ) : (
                        <Church className="size-5 text-muted-foreground" />
                    )}
                </div>
            </header>

            {flash?.success && (
                <div className="mx-auto mt-4 max-w-5xl px-4 sm:px-6">
                    <div className="rounded-lg border border-primary/25 bg-primary/10 px-4 py-3 text-sm font-medium text-primary">
                        {flash.success}
                    </div>
                </div>
            )}
            {flash?.error && (
                <div className="mx-auto mt-4 max-w-5xl px-4 sm:px-6">
                    <div className="rounded-lg border border-destructive/25 bg-destructive/10 px-4 py-3 text-sm font-medium text-destructive">
                        {flash.error}
                    </div>
                </div>
            )}

            <main className="mx-auto w-full max-w-5xl px-4 py-10 sm:px-6">{children}</main>

            <footer className="border-t border-border py-6 text-center text-xs text-muted-foreground">
                {churchName ? `${churchName} · ` : ''}Gestionado con Church SaaS
            </footer>
        </div>
    );
}

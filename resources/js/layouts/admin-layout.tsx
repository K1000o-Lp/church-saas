import { Link, router, usePage } from '@inertiajs/react';
import {
    Building2,
    CreditCard,
    Layers,
    LogOut,
    ScrollText,
    ShieldCheck,
} from 'lucide-react';
import type { ComponentType, ReactNode } from 'react';

import { BrandMark } from '@/components/brand';
import { ThemeToggle } from '@/components/theme-toggle';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { PageProps } from '@/types';

type AdminNavItem = {
    label: string;
    href: string | null;
    icon: ComponentType<{ className?: string }>;
};

const navItems: AdminNavItem[] = [
    { label: 'Iglesias', href: '/admin/churches', icon: Building2 },
    { label: 'Planes', href: null, icon: Layers },
    { label: 'Facturación', href: null, icon: CreditCard },
    { label: 'Auditoría', href: null, icon: ScrollText },
];

function isActive(pathname: string, href: string): boolean {
    return pathname === href || pathname.startsWith(`${href}/`);
}

function initials(name: string): string {
    return name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join('');
}

function AdminNavLink({ item, pathname }: { item: AdminNavItem; pathname: string }) {
    const Icon = item.icon;

    if (item.href === null) {
        return (
            <span
                className="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-muted-foreground/60"
                title="Disponible en una fase posterior"
                aria-disabled="true"
            >
                <Icon className="size-4" />
                {item.label}
            </span>
        );
    }

    const active = isActive(pathname, item.href);

    return (
        <Link
            href={item.href}
            aria-current={active ? 'page' : undefined}
            className={cn(
                'flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors',
                active
                    ? 'bg-sidebar-primary text-sidebar-primary-foreground'
                    : 'text-sidebar-foreground/80 hover:bg-sidebar-accent hover:text-sidebar-accent-foreground',
            )}
        >
            <Icon className="size-4" />
            {item.label}
        </Link>
    );
}

export function AdminLayout({ children }: { children: ReactNode }) {
    const { props, url } = usePage<PageProps>();
    const admin = props.platformAdmin;
    const pathname = url.split('?')[0] ?? '/';

    return (
        <div className="min-h-screen bg-background">
            <aside className="fixed inset-y-0 left-0 z-40 hidden w-[17rem] flex-col border-r border-sidebar-border bg-sidebar lg:flex">
                <div className="flex items-center gap-3 px-5 py-5">
                    <BrandMark />
                    <span className="flex flex-col leading-none">
                        <span className="font-display text-base font-semibold text-foreground">
                            Church SaaS
                        </span>
                        <span className="mt-1 text-[0.68rem] font-semibold tracking-[0.18em] text-muted-foreground uppercase">
                            Plataforma
                        </span>
                    </span>
                </div>

                <div className="px-3">
                    <div className="rounded-lg border border-sidebar-border bg-sidebar-accent/60 px-3 py-3">
                        <p className="eyebrow">Acceso exclusivo</p>
                        <p className="mt-1 text-sm font-semibold">Backoffice de plataforma</p>
                        <p className="mt-0.5 text-xs text-muted-foreground">
                            Sin acceso a datos pastorales de las iglesias
                        </p>
                    </div>
                </div>

                <nav className="mt-6 flex-1 space-y-1 px-3" aria-label="Navegación de plataforma">
                    <p className="px-3 pb-2 text-[0.68rem] font-semibold tracking-[0.16em] text-muted-foreground uppercase">
                        Administración
                    </p>
                    {navItems.map((item) => (
                        <AdminNavLink key={item.label} item={item} pathname={pathname} />
                    ))}
                </nav>

                <div className="border-t border-sidebar-border p-3">
                    <div className="flex items-center gap-3 rounded-lg px-2 py-2">
                        <span className="grid size-9 shrink-0 place-items-center rounded-full bg-primary/10 text-xs font-bold text-primary">
                            {admin ? initials(admin.name) : '—'}
                        </span>
                        <span className="min-w-0 flex-1">
                            <span className="block truncate text-sm font-semibold">
                                {admin?.name ?? 'Administrador'}
                            </span>
                            <span className="block truncate text-xs text-muted-foreground">
                                {admin?.email}
                            </span>
                        </span>
                    </div>
                    <div className="mt-1 flex items-center justify-between px-1">
                        <ThemeToggle />
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            onClick={() => router.post('/admin/logout')}
                            aria-label="Cerrar sesión de plataforma"
                            title="Cerrar sesión"
                        >
                            <LogOut />
                        </Button>
                    </div>
                </div>
            </aside>

            <div className="lg:pl-[17rem]">
                <header className="sticky top-0 z-30 border-b border-border bg-background/85 backdrop-blur">
                    <div className="flex h-16 items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
                        <div className="flex min-w-0 items-center gap-3">
                            <div className="lg:hidden">
                                <BrandMark />
                            </div>
                            <div className="min-w-0">
                                <p className="eyebrow">Plataforma</p>
                                <p className="truncate text-sm font-semibold">
                                    Administración de iglesias
                                </p>
                            </div>
                        </div>

                        <div className="flex items-center gap-2">
                            <span className="hidden items-center gap-1.5 rounded-full border border-primary/20 bg-primary/10 px-2.5 py-1 text-xs font-semibold text-primary sm:inline-flex">
                                <ShieldCheck className="size-3.5" />
                                Superusuario
                            </span>
                            <div className="hidden text-right sm:block">
                                <p className="text-sm leading-tight font-semibold">{admin?.name}</p>
                                <p className="text-xs leading-tight text-muted-foreground">
                                    {admin?.email}
                                </p>
                            </div>
                            <div className="lg:hidden">
                                <ThemeToggle />
                            </div>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="lg:hidden"
                                onClick={() => router.post('/admin/logout')}
                                aria-label="Cerrar sesión de plataforma"
                                title="Cerrar sesión"
                            >
                                <LogOut />
                            </Button>
                        </div>
                    </div>
                </header>

                {props.flash?.error && (
                    <div className="mx-4 mt-4 rounded-lg border border-destructive/25 bg-destructive/10 px-4 py-3 text-sm font-medium text-destructive sm:mx-6 lg:mx-8">
                        {props.flash.error}
                    </div>
                )}

                <main className="px-4 pt-6 pb-28 sm:px-6 lg:px-8 lg:pt-8 lg:pb-16">
                    <div className="mx-auto w-full max-w-6xl">{children}</div>
                </main>
            </div>

            <nav
                className="fixed inset-x-0 bottom-0 z-40 border-t border-border bg-background/95 pb-[env(safe-area-inset-bottom)] backdrop-blur lg:hidden"
                aria-label="Navegación de plataforma móvil"
            >
                <div className="mx-auto grid max-w-md grid-cols-4 gap-1 px-2 py-2">
                    {navItems.map((item) => {
                        const Icon = item.icon;
                        const active = item.href !== null && isActive(pathname, item.href);

                        if (item.href === null) {
                            return (
                                <span
                                    key={item.label}
                                    className="flex flex-col items-center gap-1 rounded-md px-1 py-1.5 text-[0.68rem] font-medium text-muted-foreground/50"
                                    aria-disabled="true"
                                >
                                    <Icon className="size-5" />
                                    {item.label}
                                </span>
                            );
                        }

                        return (
                            <Link
                                key={item.label}
                                href={item.href}
                                aria-current={active ? 'page' : undefined}
                                className={cn(
                                    'flex flex-col items-center gap-1 rounded-md px-1 py-1.5 text-[0.68rem] font-medium transition-colors',
                                    active
                                        ? 'bg-sidebar-primary text-sidebar-primary-foreground'
                                        : 'text-sidebar-foreground/80 hover:bg-sidebar-accent hover:text-sidebar-accent-foreground',
                                )}
                            >
                                <Icon className="size-5" />
                                {item.label}
                            </Link>
                        );
                    })}
                </div>
            </nav>
        </div>
    );
}

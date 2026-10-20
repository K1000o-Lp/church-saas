import { Link, router, usePage } from '@inertiajs/react';
import {
    CalendarDays,
    Church,
    CreditCard,
    HeartHandshake,
    LayoutDashboard,
    LogOut,
    Settings,
    Users,
    Wallet,
} from 'lucide-react';
import type { ComponentType, ReactNode } from 'react';

import { Brand } from '@/components/brand';
import { ThemeToggle } from '@/components/theme-toggle';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { PageProps } from '@/types';

type NavItem = {
    label: string;
    href: string | null;
    icon: ComponentType<{ className?: string }>;
};

const primaryNav: NavItem[] = [
    { label: 'Panel', href: '/dashboard', icon: LayoutDashboard },
    { label: 'Personas', href: null, icon: Users },
    { label: 'Eventos', href: null, icon: CalendarDays },
    { label: 'Finanzas', href: null, icon: Wallet },
    { label: 'Servicios', href: null, icon: HeartHandshake },
];

const secondaryNav: NavItem[] = [{ label: 'Configuración', href: null, icon: Settings }];

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

function NavLink({
    item,
    pathname,
    onNavigate,
    compact = false,
}: {
    item: NavItem;
    pathname: string;
    onNavigate?: () => void;
    compact?: boolean;
}) {
    const Icon = item.icon;

    if (item.href === null) {
        return (
            <span
                className={cn(
                    'group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-muted-foreground/60',
                    compact && 'flex-col gap-1 rounded-md px-1 py-1.5 text-[0.68rem]',
                )}
                title="Disponible próximamente"
                aria-disabled="true"
            >
                <Icon className={compact ? 'size-5' : 'size-4'} />
                <span>{item.label}</span>
            </span>
        );
    }

    const active = isActive(pathname, item.href);

    return (
        <Link
            href={item.href}
            onClick={onNavigate}
            aria-current={active ? 'page' : undefined}
            className={cn(
                'group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors',
                compact && 'flex-col gap-1 rounded-md px-1 py-1.5 text-[0.68rem]',
                active
                    ? 'bg-sidebar-primary text-sidebar-primary-foreground'
                    : 'text-sidebar-foreground/80 hover:bg-sidebar-accent hover:text-sidebar-accent-foreground',
            )}
        >
            <Icon className={compact ? 'size-5' : 'size-4'} />
            <span>{item.label}</span>
        </Link>
    );
}

export function AppLayout({ children }: { children: ReactNode }) {
    const { props, url } = usePage<PageProps>();
    const { auth, currentChurch, flash } = props;
    const user = auth.user;
    const pathname = url.split('?')[0] ?? '/';
    const churchHref = currentChurch ? `/churches/${currentChurch.slug}` : '/dashboard';

    const mobileNav: NavItem[] = [
        { label: 'Panel', href: '/dashboard', icon: LayoutDashboard },
        { label: 'Iglesia', href: churchHref, icon: Church },
        { label: 'Finanzas', href: null, icon: Wallet },
        { label: 'Ajustes', href: null, icon: Settings },
    ];

    return (
        <div className="min-h-screen bg-background">
            <aside className="fixed inset-y-0 left-0 z-40 hidden w-[17rem] flex-col border-r border-sidebar-border bg-sidebar lg:flex">
                <div className="px-5 py-5">
                    <Brand />
                </div>

                <div className="px-3">
                    <div className="rounded-lg border border-sidebar-border bg-sidebar-accent/60 px-3 py-3">
                        <p className="eyebrow">{currentChurch ? 'Iglesia activa' : 'Sin contexto'}</p>
                        <p className="mt-1 truncate text-sm font-semibold text-sidebar-foreground">
                            {currentChurch?.name ?? 'Selecciona una iglesia'}
                        </p>
                        <p className="mt-0.5 text-xs text-muted-foreground">
                            {currentChurch?.role
                                ? `Rol: ${currentChurch.role}`
                                : 'Abre una iglesia desde el panel'}
                        </p>
                    </div>
                </div>

                <nav className="mt-6 flex-1 space-y-1 px-3" aria-label="Navegación principal">
                    <p className="px-3 pb-2 text-[0.68rem] font-semibold tracking-[0.16em] text-muted-foreground uppercase">
                        Operación
                    </p>
                    {primaryNav.map((item) => (
                        <NavLink key={item.label} item={item} pathname={pathname} />
                    ))}
                    <div className="my-4 border-t border-sidebar-border" />
                    {currentChurch &&
                        ['admin', 'finance'].includes(currentChurch.role ?? '') && (
                            <NavLink
                                item={{
                                    label: 'Plan',
                                    href: `/churches/${currentChurch.slug}/plan`,
                                    icon: CreditCard,
                                }}
                                pathname={pathname}
                            />
                        )}
                    {secondaryNav.map((item) => (
                        <NavLink key={item.label} item={item} pathname={pathname} />
                    ))}
                </nav>

                <div className="border-t border-sidebar-border p-3">
                    <div className="flex items-center gap-3 rounded-lg px-2 py-2">
                        <span className="grid size-9 shrink-0 place-items-center rounded-full bg-primary/10 text-xs font-bold text-primary">
                            {user ? initials(user.name) : '—'}
                        </span>
                        <span className="min-w-0 flex-1">
                            <span className="block truncate text-sm font-semibold">
                                {user?.name ?? 'Invitado'}
                            </span>
                            <span className="block truncate text-xs text-muted-foreground">
                                {user?.email}
                            </span>
                        </span>
                    </div>
                    <div className="mt-1 flex items-center justify-between px-1">
                        <ThemeToggle />
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            onClick={() => router.post('/logout')}
                            aria-label="Cerrar sesión"
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
                                <Brand compact />
                            </div>
                            <div className="hidden min-w-0 lg:block">
                                <p className="eyebrow">
                                    {currentChurch ? 'Contexto de iglesia' : 'Espacio de trabajo'}
                                </p>
                                <p className="truncate text-sm font-semibold">
                                    {currentChurch?.name ?? props.appName}
                                </p>
                            </div>
                        </div>

                        <div className="flex items-center gap-2">
                            <div className="hidden text-right sm:block">
                                <p className="text-sm leading-tight font-semibold">{user?.name}</p>
                                <p className="text-xs leading-tight text-muted-foreground">
                                    {user?.email}
                                </p>
                            </div>
                            <span className="hidden size-9 place-items-center rounded-full bg-primary/10 text-xs font-bold text-primary sm:grid lg:hidden">
                                {user ? initials(user.name) : '—'}
                            </span>
                            <div className="lg:hidden">
                                <ThemeToggle />
                            </div>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="lg:hidden"
                                onClick={() => router.post('/logout')}
                                aria-label="Cerrar sesión"
                                title="Cerrar sesión"
                            >
                                <LogOut />
                            </Button>
                        </div>
                    </div>
                </header>

                {flash?.success && (
                    <div className="mx-4 mt-4 rounded-lg border border-primary/25 bg-primary/10 px-4 py-3 text-sm font-medium text-primary sm:mx-6 lg:mx-8">
                        {flash.success}
                    </div>
                )}
                {flash?.error && (
                    <div className="mx-4 mt-4 rounded-lg border border-destructive/25 bg-destructive/10 px-4 py-3 text-sm font-medium text-destructive sm:mx-6 lg:mx-8">
                        {flash.error}
                    </div>
                )}

                <main className="px-4 pt-6 pb-28 sm:px-6 lg:px-8 lg:pt-8 lg:pb-12">
                    <div className="mx-auto w-full max-w-6xl">{children}</div>
                </main>
            </div>

            <nav
                className="fixed inset-x-0 bottom-0 z-40 border-t border-border bg-background/95 pb-[env(safe-area-inset-bottom)] backdrop-blur lg:hidden"
                aria-label="Navegación móvil"
            >
                <div className="mx-auto grid max-w-md grid-cols-4 gap-1 px-2 py-2">
                    {mobileNav.map((item) => (
                        <NavLink
                            key={item.label}
                            item={item}
                            pathname={pathname}
                            compact
                        />
                    ))}
                </div>
            </nav>
        </div>
    );
}

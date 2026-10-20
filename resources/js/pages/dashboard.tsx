import { Head, Link } from '@inertiajs/react';
import { ArrowUpRight, CalendarClock, Church, Coins, Plus } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { AppLayout } from '@/layouts/app-layout';
import type { MembershipSummary } from '@/types';

function roleLabel(role: string): string {
    const labels: Record<string, string> = {
        admin: 'Administrador',
        pastor: 'Pastor',
        finance: 'Finanzas',
        leader: 'Líder',
        volunteer: 'Servidor',
    };

    return labels[role] ?? role;
}

function ChurchCard({ membership }: { membership: MembershipSummary }) {
    const { church } = membership;

    return (
        <Link
            href={`/churches/${church.slug}`}
            className="group rounded-xl no-underline outline-none focus-visible:ring-2 focus-visible:ring-ring/60 focus-visible:ring-offset-2 focus-visible:ring-offset-background"
        >
            <Card className="h-full gap-4 py-5 transition-colors group-hover:border-primary/45 group-hover:bg-card">
                <CardHeader className="gap-3">
                    <div className="flex items-start justify-between gap-3">
                        <span className="grid size-11 place-items-center rounded-lg bg-primary/10 text-primary">
                            <Church className="size-5" />
                        </span>
                        <span className="rounded-full bg-secondary px-2.5 py-1 text-xs font-semibold text-secondary-foreground">
                            {roleLabel(membership.role)}
                        </span>
                    </div>
                    <div>
                        <CardTitle className="text-xl">{church.name}</CardTitle>
                        <p className="mt-1 text-sm text-muted-foreground">/{church.slug}</p>
                    </div>
                </CardHeader>

                <CardContent className="mt-auto flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-muted-foreground">
                    {church.timezone && (
                        <span className="inline-flex items-center gap-1.5">
                            <CalendarClock className="size-4" />
                            {church.timezone}
                        </span>
                    )}
                    {church.currencyCode && (
                        <span className="inline-flex items-center gap-1.5">
                            <Coins className="size-4" />
                            {church.currencyCode}
                        </span>
                    )}
                    <span className="ml-auto inline-flex items-center gap-1 text-sm font-semibold text-primary">
                        Abrir
                        <ArrowUpRight className="size-4 transition-transform group-hover:-translate-y-0.5 group-hover:translate-x-0.5" />
                    </span>
                </CardContent>
            </Card>
        </Link>
    );
}

export default function Dashboard({ memberships }: { memberships: MembershipSummary[] }) {
    return (
        <AppLayout>
            <Head title="Mis iglesias" />

            <div className="flex flex-wrap items-end justify-between gap-4">
                <div className="flex flex-col gap-2">
                    <p className="eyebrow">Panel</p>
                    <h1 className="font-display text-3xl font-semibold tracking-tight sm:text-4xl">
                        Mis iglesias
                    </h1>
                    <p className="max-w-2xl text-sm leading-relaxed text-muted-foreground sm:text-base">
                        Selecciona la iglesia con la que quieres trabajar. La verificación de
                        acceso se repite en cada petición.
                    </p>
                </div>
                {memberships.length > 0 && (
                    <Button asChild>
                        <Link href="/onboarding/church">
                            <Plus />
                            Crear iglesia
                        </Link>
                    </Button>
                )}
            </div>

            {memberships.length === 0 ? (
                <Card className="mt-8 items-center py-12 text-center">
                    <CardContent className="flex max-w-md flex-col items-center gap-4">
                        <span className="grid size-14 place-items-center rounded-full bg-secondary text-muted-foreground">
                            <Church className="size-6" />
                        </span>
                        <div>
                            <h2 className="font-display text-xl font-semibold">
                                No tienes membresías activas
                            </h2>
                            <p className="mt-2 text-sm leading-relaxed text-muted-foreground">
                                Crea tu primera iglesia para empezar. Quedarás como administrador y
                                podrás configurar monedas y operación. Si esperas una invitación,
                                aparecerá aquí cuando esté activa.
                            </p>
                        </div>
                        <Button asChild>
                            <Link href="/onboarding/church">
                                <Plus />
                                Crear mi iglesia
                            </Link>
                        </Button>
                    </CardContent>
                </Card>
            ) : (
                <div className="mt-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    {memberships.map((membership) => (
                        <ChurchCard key={membership.id} membership={membership} />
                    ))}
                </div>
            )}
        </AppLayout>
    );
}

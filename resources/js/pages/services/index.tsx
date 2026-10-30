import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, CalendarClock, HeartHandshake, Plus, Users } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Select } from '@/components/ui/select';
import { AppLayout } from '@/layouts/app-layout';
import { formatDateTime } from '@/lib/format';
import type { Pagination, ServicePlanSummary, ServiceStatusOption } from '@/types';

type Props = {
    church: {
        name: string;
        slug: string;
    };
    plans: ServicePlanSummary[];
    filters: {
        status: string;
    };
    statuses: ServiceStatusOption[];
    canManage: boolean;
    pagination: Pagination;
};

const statusStyles: Record<string, string> = {
    draft: 'border-border bg-secondary/60 text-foreground',
    published: 'border-primary/30 bg-primary/10 text-primary',
    cancelled: 'border-destructive/25 bg-destructive/10 text-destructive',
    completed: 'border-accent/40 bg-accent/10 text-accent',
};

export default function ServicesIndex({
    church,
    plans,
    filters,
    statuses,
    canManage,
    pagination,
}: Props) {
    const churchPath = `/churches/${church.slug}`;

    function filter(status: string) {
        router.get(`${churchPath}/services`, status === '' ? {} : { status }, {
            preserveState: true,
            preserveScroll: true,
        });
    }

    return (
        <AppLayout>
            <Head title={`Servicios · ${church.name}`} />

            <Button variant="ghost" size="sm" asChild>
                <Link href={churchPath}>
                    <ArrowLeft />
                    Volver a la iglesia
                </Link>
            </Button>

            <div className="mt-5 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <p className="eyebrow">Operación</p>
                    <h1 className="mt-2 font-display text-3xl font-semibold">Servicios</h1>
                    <p className="mt-2 max-w-2xl text-sm leading-relaxed text-muted-foreground">
                        Planifica cultos, orden del servicio y equipos de voluntarios.
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <Button variant="outline" asChild>
                        <Link href={`${churchPath}/services/teams`}>
                            <Users />
                            Equipos
                        </Link>
                    </Button>
                    {canManage && (
                        <Button asChild>
                            <Link href={`${churchPath}/services/create`}>
                                <Plus />
                                Nuevo culto
                            </Link>
                        </Button>
                    )}
                </div>
            </div>

            <div className="mt-6 flex justify-end">
                <div className="w-48">
                    <Select
                        value={filters.status}
                        onChange={(event) => filter(event.target.value)}
                        aria-label="Filtrar por estado"
                    >
                        <option value="">Todos</option>
                        {statuses.map((status) => (
                            <option key={status.value} value={status.value}>
                                {status.label}
                            </option>
                        ))}
                    </Select>
                </div>
            </div>

            <div className="mt-4">
                {plans.length === 0 ? (
                    <Card>
                        <CardContent className="py-12 text-center">
                            <HeartHandshake className="mx-auto size-9 text-muted-foreground" />
                            <p className="mt-3 text-sm font-semibold">
                                No hay cultos con estos filtros.
                            </p>
                        </CardContent>
                    </Card>
                ) : (
                    <ul className="space-y-3">
                        {plans.map((plan) => (
                            <li key={plan.id}>
                                <Link
                                    href={`${churchPath}/services/${plan.id}`}
                                    className="block rounded-xl border border-border bg-card px-4 py-4 transition-colors hover:border-primary/40"
                                >
                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                        <div>
                                            <div className="flex flex-wrap items-center gap-2">
                                                <p className="font-semibold">{plan.title}</p>
                                                <span
                                                    className={`rounded-full border px-2.5 py-0.5 text-xs font-semibold ${
                                                        statusStyles[plan.status] ??
                                                        'border-border bg-secondary/60'
                                                    }`}
                                                >
                                                    {plan.statusLabel}
                                                </span>
                                            </div>
                                            <p className="mt-1 flex items-center gap-2 text-sm text-muted-foreground">
                                                <CalendarClock className="size-4" />
                                                {formatDateTime(plan.startsAt)}
                                                {plan.location ? ` · ${plan.location}` : ''}
                                            </p>
                                        </div>
                                        <p className="text-sm text-muted-foreground">
                                            {plan.itemsCount} ítems
                                        </p>
                                    </div>
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
            </div>

            {pagination.lastPage > 1 && (
                <div className="mt-6 flex items-center justify-between text-sm">
                    <p className="text-muted-foreground">
                        Página {pagination.currentPage} de {pagination.lastPage}
                    </p>
                    <div className="flex gap-2">
                        {pagination.previousPageUrl && (
                            <Button variant="outline" size="sm" asChild>
                                <Link href={pagination.previousPageUrl}>Anterior</Link>
                            </Button>
                        )}
                        {pagination.nextPageUrl && (
                            <Button variant="outline" size="sm" asChild>
                                <Link href={pagination.nextPageUrl}>Siguiente</Link>
                            </Button>
                        )}
                    </div>
                </div>
            )}
        </AppLayout>
    );
}

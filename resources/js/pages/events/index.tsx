import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, CalendarDays, CalendarPlus, MapPin, Users } from 'lucide-react';
import type { FormEvent } from 'react';

import { EventStatusBadge } from '@/components/events/event-status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select } from '@/components/ui/select';
import { AppLayout } from '@/layouts/app-layout';
import { formatDateTime, formatMoney } from '@/lib/format';
import type { EventStatusOption, EventSummary, Pagination } from '@/types';

type Props = {
    church: {
        name: string;
        slug: string;
    };
    events: EventSummary[];
    filters: {
        search: string;
        status: string;
        from: string;
        to: string;
    };
    statuses: EventStatusOption[];
    canManage: boolean;
    pagination: Pagination;
};

export default function EventsIndex({
    church,
    events,
    filters,
    statuses,
    canManage,
    pagination,
}: Props) {
    const churchPath = `/churches/${church.slug}`;
    const filterForm = useForm({
        search: filters.search,
        status: filters.status,
        from: filters.from,
        to: filters.to,
    });

    function submitFilters(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        filterForm.get(`${churchPath}/events`, { preserveState: true, preserveScroll: true });
    }

    return (
        <AppLayout>
            <Head title={`Eventos · ${church.name}`} />

            <Button variant="ghost" size="sm" asChild>
                <Link href={churchPath}>
                    <ArrowLeft />
                    Volver a la iglesia
                </Link>
            </Button>

            <div className="mt-5 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <p className="eyebrow">Operación</p>
                    <h1 className="mt-2 font-display text-3xl font-semibold">Eventos</h1>
                    <p className="mt-2 max-w-2xl text-sm leading-relaxed text-muted-foreground">
                        Publica actividades, controla cupos y da seguimiento a las
                        inscripciones.
                    </p>
                </div>
                {canManage && (
                    <Button asChild>
                        <Link href={`${churchPath}/events/create`}>
                            <CalendarPlus />
                            Nuevo evento
                        </Link>
                    </Button>
                )}
            </div>

            <Card className="mt-6">
                <CardContent className="pt-6">
                    <form
                        className="grid gap-4 sm:grid-cols-2 lg:grid-cols-[1.4fr_1fr_1fr_1fr_auto]"
                        onSubmit={submitFilters}
                    >
                        <div className="space-y-2">
                            <Label htmlFor="search">Buscar</Label>
                            <Input
                                id="search"
                                placeholder="Título o lugar"
                                value={filterForm.data.search}
                                onChange={(event) =>
                                    filterForm.setData('search', event.target.value)
                                }
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="status">Estado</Label>
                            <Select
                                id="status"
                                value={filterForm.data.status}
                                onChange={(event) =>
                                    filterForm.setData('status', event.target.value)
                                }
                            >
                                <option value="">Todos</option>
                                {statuses.map((status) => (
                                    <option key={status.value} value={status.value}>
                                        {status.label}
                                    </option>
                                ))}
                            </Select>
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="from">Desde</Label>
                            <Input
                                id="from"
                                type="date"
                                value={filterForm.data.from}
                                onChange={(event) =>
                                    filterForm.setData('from', event.target.value)
                                }
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="to">Hasta</Label>
                            <Input
                                id="to"
                                type="date"
                                value={filterForm.data.to}
                                onChange={(event) =>
                                    filterForm.setData('to', event.target.value)
                                }
                            />
                        </div>
                        <div className="flex items-end">
                            <Button type="submit" variant="outline">
                                Filtrar
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>

            <div className="mt-6">
                {events.length === 0 ? (
                    <Card>
                        <CardContent className="py-12 text-center">
                            <CalendarDays className="mx-auto size-9 text-muted-foreground" />
                            <p className="mt-3 text-sm font-semibold">
                                No hay eventos con estos filtros.
                            </p>
                        </CardContent>
                    </Card>
                ) : (
                    <ul className="space-y-3">
                        {events.map((event) => (
                            <li key={event.id}>
                                <Link
                                    href={`${churchPath}/events/${event.id}`}
                                    className="block rounded-xl border border-border bg-card px-4 py-4 transition-colors hover:border-primary/40"
                                >
                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                        <div className="min-w-0">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <p className="font-semibold">{event.title}</p>
                                                <EventStatusBadge
                                                    status={event.status}
                                                    label={event.statusLabel}
                                                />
                                                {event.isPublic && (
                                                    <span className="rounded-full border border-border bg-secondary/60 px-2 py-0.5 text-[0.68rem] font-semibold">
                                                        Público
                                                    </span>
                                                )}
                                                {event.isPaid && event.price !== null && (
                                                    <span className="rounded-full border border-accent/40 bg-accent/10 px-2 py-0.5 text-[0.68rem] font-semibold text-accent">
                                                        {formatMoney(
                                                            event.price,
                                                            event.currencyCode ?? 'USD',
                                                        )}
                                                    </span>
                                                )}
                                            </div>
                                            <p className="mt-1 flex flex-wrap items-center gap-3 text-sm text-muted-foreground">
                                                <span className="inline-flex items-center gap-1">
                                                    <CalendarDays className="size-3.5" />
                                                    {formatDateTime(event.startsAt)}
                                                </span>
                                                {event.location && (
                                                    <span className="inline-flex items-center gap-1">
                                                        <MapPin className="size-3.5" />
                                                        {event.location}
                                                    </span>
                                                )}
                                            </p>
                                        </div>
                                        <div className="text-right text-sm">
                                            <p className="inline-flex items-center gap-1 font-semibold tabular-nums">
                                                <Users className="size-4 text-muted-foreground" />
                                                {event.occupied}
                                                {event.capacity !== null
                                                    ? ` / ${event.capacity}`
                                                    : ''}
                                            </p>
                                            <p className="mt-0.5 text-xs text-muted-foreground">
                                                {event.registrationsCount} inscripciones
                                            </p>
                                        </div>
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

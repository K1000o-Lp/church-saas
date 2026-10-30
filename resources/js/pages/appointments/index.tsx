import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, CalendarPlus, CalendarClock } from 'lucide-react';
import type { FormEvent } from 'react';

import { AppointmentStatusBadge } from '@/components/appointments/appointment-status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select } from '@/components/ui/select';
import { AppLayout } from '@/layouts/app-layout';
import { formatDateTime } from '@/lib/format';
import { useForm } from '@inertiajs/react';
import type {
    AppointmentHostOption,
    AppointmentStatusOption,
    AppointmentSummary,
    Pagination,
} from '@/types';

type Props = {
    church: {
        name: string;
        slug: string;
    };
    appointments: AppointmentSummary[];
    filters: {
        from: string;
        to: string;
        status: string;
        host: string;
    };
    statuses: AppointmentStatusOption[];
    hosts: AppointmentHostOption[];
    canManage: boolean;
    pagination: Pagination;
};

export default function AppointmentsIndex({
    church,
    appointments,
    filters,
    statuses,
    hosts,
    canManage,
    pagination,
}: Props) {
    const churchPath = `/churches/${church.slug}`;
    const filterForm = useForm({
        from: filters.from,
        to: filters.to,
        status: filters.status,
        host: filters.host,
    });

    function submitFilters(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        filterForm.get(`${churchPath}/appointments`, {
            preserveState: true,
            preserveScroll: true,
        });
    }

    return (
        <AppLayout>
            <Head title={`Citas · ${church.name}`} />

            <Button variant="ghost" size="sm" asChild>
                <Link href={churchPath}>
                    <ArrowLeft />
                    Volver a la iglesia
                </Link>
            </Button>

            <div className="mt-5 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <p className="eyebrow">Cuidado pastoral</p>
                    <h1 className="mt-2 font-display text-3xl font-semibold">Citas</h1>
                    <p className="mt-2 max-w-2xl text-sm leading-relaxed text-muted-foreground">
                        Agenda y da seguimiento a las citas entre líderes y personas de la
                        iglesia.
                    </p>
                </div>
                {canManage && (
                    <Button asChild>
                        <Link href={`${churchPath}/appointments/create`}>
                            <CalendarPlus />
                            Nueva cita
                        </Link>
                    </Button>
                )}
            </div>

            <Card className="mt-6">
                <CardContent className="pt-6">
                    <form
                        className="grid gap-4 sm:grid-cols-2 lg:grid-cols-[repeat(4,1fr)_auto]"
                        onSubmit={submitFilters}
                    >
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
                        {hosts.length > 1 && (
                            <div className="space-y-2">
                                <Label htmlFor="host">Líder</Label>
                                <Select
                                    id="host"
                                    value={filterForm.data.host}
                                    onChange={(event) =>
                                        filterForm.setData('host', event.target.value)
                                    }
                                >
                                    <option value="">Todos</option>
                                    {hosts.map((host) => (
                                        <option key={host.id} value={host.id}>
                                            {host.name ?? host.email ?? `Usuario ${host.id}`}
                                        </option>
                                    ))}
                                </Select>
                            </div>
                        )}
                        <div className="flex items-end">
                            <Button type="submit" variant="outline">
                                Filtrar
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>

            <div className="mt-6">
                {appointments.length === 0 ? (
                    <Card>
                        <CardContent className="py-12 text-center">
                            <CalendarClock className="mx-auto size-9 text-muted-foreground" />
                            <p className="mt-3 text-sm font-semibold">
                                No hay citas con estos filtros.
                            </p>
                        </CardContent>
                    </Card>
                ) : (
                    <ul className="space-y-3">
                        {appointments.map((appointment) => (
                            <li key={appointment.id}>
                                <Link
                                    href={`${churchPath}/appointments/${appointment.id}`}
                                    className="block rounded-xl border border-border bg-card px-4 py-4 transition-colors hover:border-primary/40"
                                >
                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                        <div>
                                            <p className="font-semibold">
                                                {appointment.person?.fullName ?? 'Persona'}
                                            </p>
                                            <p className="mt-0.5 text-sm text-muted-foreground">
                                                {appointment.subject ?? 'Sin motivo'} · con{' '}
                                                {appointment.host.name ?? 'Líder'}
                                            </p>
                                        </div>
                                        <div className="flex flex-col items-end gap-1.5">
                                            <AppointmentStatusBadge
                                                status={appointment.status}
                                                label={appointment.statusLabel}
                                            />
                                            <p className="text-sm tabular-nums">
                                                {formatDateTime(appointment.scheduledAt)}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {appointment.durationMinutes} min
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

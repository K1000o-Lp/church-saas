import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, ClipboardList, Trash2, UserPlus } from 'lucide-react';
import type { FormEvent } from 'react';

import { RegistrationStatusBadge } from '@/components/events/registration-status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select } from '@/components/ui/select';
import { AppLayout } from '@/layouts/app-layout';
import type {
    EventRegistrationSummary,
    PersonOption,
    RegistrationStatusOption,
    RegistrationStatusValue,
} from '@/types';

type EventSummaryProps = {
    id: number;
    title: string;
    status: string;
    statusLabel: string;
    capacity: number | null;
    occupied: number;
    isPaid: boolean;
    price: string | null;
    currencyCode: string | null;
};

type Props = {
    church: {
        name: string;
        slug: string;
    };
    event: EventSummaryProps;
    registrations: EventRegistrationSummary[];
    filters: {
        status: string;
    };
    statuses: RegistrationStatusOption[];
    people: PersonOption[];
    canManage: boolean;
};

export default function EventRegistrations({
    church,
    event,
    registrations,
    statuses,
    people,
    canManage,
}: Props) {
    const churchPath = `/churches/${church.slug}`;
    const eventPath = `${churchPath}/events/${event.id}`;
    const form = useForm({
        person_id: '',
        guest_name: '',
        guest_count: '0',
        status: 'registered',
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post(`${eventPath}/registrations`, {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    }

    function changeStatus(
        registration: EventRegistrationSummary,
        status: RegistrationStatusValue,
    ) {
        router.patch(
            `${eventPath}/registrations/${registration.id}`,
            { status },
            { preserveScroll: true },
        );
    }

    function cancelRegistration(registration: EventRegistrationSummary) {
        if (!window.confirm('¿Cancelar esta inscripción?')) {
            return;
        }

        router.delete(`${eventPath}/registrations/${registration.id}`, {
            preserveScroll: true,
        });
    }

    const errors = form.errors as Record<string, string>;

    return (
        <AppLayout>
            <Head title={`Inscripciones · ${event.title}`} />

            <Button variant="ghost" size="sm" asChild>
                <Link href={eventPath}>
                    <ArrowLeft />
                    Volver al evento
                </Link>
            </Button>

            <div className="mt-5">
                <p className="eyebrow">Inscripciones</p>
                <h1 className="mt-2 font-display text-3xl font-semibold">{event.title}</h1>
                <p className="mt-2 text-sm text-muted-foreground">
                    {event.occupied}
                    {event.capacity !== null ? ` / ${event.capacity}` : ''} cupos ocupados ·{' '}
                    {registrations.length} inscripciones
                    {event.isPaid ? ' · evento con costo' : ''}
                </p>
            </div>

            {canManage && (
                <Card className="mt-6">
                    <CardHeader>
                        <CardTitle>Agregar inscripción</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form
                            className="grid gap-4 sm:grid-cols-2 lg:grid-cols-[1.4fr_1fr_8rem_12rem_auto] lg:items-end"
                            onSubmit={submit}
                            noValidate
                        >
                            <div className="space-y-2">
                                <Label htmlFor="person_id">Persona</Label>
                                <Select
                                    id="person_id"
                                    value={form.data.person_id}
                                    onChange={(event) =>
                                        form.setData('person_id', event.target.value)
                                    }
                                >
                                    <option value="">Invitado externo</option>
                                    {people.map((person) => (
                                        <option key={person.id} value={person.id}>
                                            {person.fullName}
                                        </option>
                                    ))}
                                </Select>
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="guest_name">Nombre del invitado</Label>
                                <Input
                                    id="guest_name"
                                    value={form.data.guest_name}
                                    onChange={(event) =>
                                        form.setData('guest_name', event.target.value)
                                    }
                                />
                                {errors.person_id && (
                                    <p className="text-sm text-destructive">
                                        {errors.person_id}
                                    </p>
                                )}
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="guest_count">Acompañantes</Label>
                                <Input
                                    id="guest_count"
                                    type="number"
                                    min={0}
                                    max={20}
                                    value={form.data.guest_count}
                                    onChange={(event) =>
                                        form.setData('guest_count', event.target.value)
                                    }
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="status">Estado</Label>
                                <Select
                                    id="status"
                                    value={form.data.status}
                                    onChange={(event) =>
                                        form.setData('status', event.target.value)
                                    }
                                >
                                    {statuses.map((status) => (
                                        <option key={status.value} value={status.value}>
                                            {status.label}
                                        </option>
                                    ))}
                                </Select>
                            </div>
                            <Button type="submit" disabled={form.processing}>
                                <UserPlus />
                                Inscribir
                            </Button>
                        </form>
                    </CardContent>
                </Card>
            )}

            <div className="mt-6">
                {registrations.length === 0 ? (
                    <Card>
                        <CardContent className="py-12 text-center">
                            <ClipboardList className="mx-auto size-9 text-muted-foreground" />
                            <p className="mt-3 text-sm font-semibold">
                                Todavía no hay inscripciones.
                            </p>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="overflow-hidden rounded-xl border border-border bg-card">
                        <table className="w-full text-sm">
                            <thead className="bg-secondary/40 text-left text-xs tracking-wide text-muted-foreground uppercase">
                                <tr>
                                    <th className="px-4 py-3">Persona</th>
                                    <th className="px-4 py-3">Acompañantes</th>
                                    <th className="px-4 py-3">Estado</th>
                                    <th className="px-4 py-3">Pago</th>
                                    <th className="px-4 py-3" />
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {registrations.map((registration) => (
                                    <tr key={registration.id}>
                                        <td className="px-4 py-3">
                                            <p className="font-semibold">
                                                {registration.person?.fullName ??
                                                    registration.guestName ??
                                                    'Invitado'}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {registration.person?.email ?? 'Sin correo'}
                                            </p>
                                        </td>
                                        <td className="px-4 py-3 tabular-nums">
                                            {registration.guestCount}
                                        </td>
                                        <td className="px-4 py-3">
                                            {canManage ? (
                                                <Select
                                                    value={registration.status}
                                                    onChange={(event) =>
                                                        changeStatus(
                                                            registration,
                                                            event.target
                                                                .value as RegistrationStatusValue,
                                                        )
                                                    }
                                                >
                                                    {statuses.map((status) => (
                                                        <option
                                                            key={status.value}
                                                            value={status.value}
                                                        >
                                                            {status.label}
                                                        </option>
                                                    ))}
                                                </Select>
                                            ) : (
                                                <RegistrationStatusBadge
                                                    status={registration.status}
                                                    label={registration.statusLabel}
                                                />
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-xs text-muted-foreground">
                                            {registration.paymentStatusLabel}
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            {canManage && (
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() =>
                                                        cancelRegistration(registration)
                                                    }
                                                    aria-label="Cancelar inscripción"
                                                >
                                                    <Trash2 />
                                                </Button>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}

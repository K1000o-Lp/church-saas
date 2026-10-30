import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    CalendarClock,
    CheckCircle2,
    Clock,
    Pencil,
    UserRound,
    XCircle,
} from 'lucide-react';
import type { FormEvent } from 'react';

import { AppointmentStatusBadge } from '@/components/appointments/appointment-status-badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { AppLayout } from '@/layouts/app-layout';
import { dateTimeInputValue, formatDateTime } from '@/lib/format';
import type { AppointmentDetail } from '@/types';

type Props = {
    church: {
        name: string;
        slug: string;
    };
    appointment: AppointmentDetail;
    canManage: boolean;
    canViewNotes: boolean;
};

export default function AppointmentShow({
    church,
    appointment,
    canManage,
    canViewNotes,
}: Props) {
    const churchPath = `/churches/${church.slug}`;
    const appointmentPath = `${churchPath}/appointments/${appointment.id}`;
    const isOpen = ['scheduled', 'rescheduled'].includes(appointment.status);
    const rescheduleForm = useForm({
        scheduled_at: dateTimeInputValue(appointment.scheduledAt),
        duration_minutes: String(appointment.durationMinutes),
    });

    function complete() {
        if (!window.confirm('¿Marcar esta cita como completada?')) {
            return;
        }

        router.patch(`${appointmentPath}/complete`, {}, { preserveScroll: true });
    }

    function noShow() {
        if (!window.confirm('¿Marcar esta cita como no asistida?')) {
            return;
        }

        router.patch(`${appointmentPath}/no-show`, {}, { preserveScroll: true });
    }

    function cancel() {
        const reason = window.prompt('Motivo de la cancelación (opcional):') ?? '';

        router.patch(
            `${appointmentPath}/cancel`,
            { reason: reason.trim() === '' ? null : reason.trim() },
            { preserveScroll: true },
        );
    }

    function reschedule(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        rescheduleForm.patch(`${appointmentPath}/reschedule`, { preserveScroll: true });
    }

    const errors = rescheduleForm.errors as Record<string, string>;

    return (
        <AppLayout>
            <Head title={`Cita · ${church.name}`} />

            <Button variant="ghost" size="sm" asChild>
                <Link href={`${churchPath}/appointments`}>
                    <ArrowLeft />
                    Volver a citas
                </Link>
            </Button>

            <div className="mt-5 flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p className="eyebrow">Cita pastoral</p>
                    <h1 className="mt-2 font-display text-3xl font-semibold">
                        {appointment.person?.fullName ?? 'Persona'}
                    </h1>
                    <p className="mt-2 text-sm text-muted-foreground">
                        {appointment.subject ?? 'Sin motivo registrado'}
                    </p>
                    <div className="mt-3">
                        <AppointmentStatusBadge
                            status={appointment.status}
                            label={appointment.statusLabel}
                        />
                    </div>
                </div>

                {canManage && isOpen && (
                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" asChild>
                            <Link href={`${appointmentPath}/edit`}>
                                <Pencil />
                                Editar
                            </Link>
                        </Button>
                        <Button variant="outline" onClick={complete}>
                            <CheckCircle2 />
                            Completar
                        </Button>
                        <Button variant="outline" onClick={noShow}>
                            <XCircle />
                            No asistió
                        </Button>
                        <Button variant="destructive" onClick={cancel}>
                            <XCircle />
                            Cancelar
                        </Button>
                    </div>
                )}
            </div>

            <div className="mt-8 grid gap-6 lg:grid-cols-[1.5fr_1fr]">
                <Card>
                    <CardHeader>
                        <CardTitle>Detalles</CardTitle>
                        <CardDescription>Información de la cita y sus participantes.</CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                Fecha y hora
                            </p>
                            <p className="mt-1 flex items-center gap-2 text-sm">
                                <CalendarClock className="size-4 text-muted-foreground" />
                                {formatDateTime(appointment.scheduledAt)}
                            </p>
                        </div>
                        <div>
                            <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                Duración
                            </p>
                            <p className="mt-1 flex items-center gap-2 text-sm">
                                <Clock className="size-4 text-muted-foreground" />
                                {appointment.durationMinutes} minutos
                            </p>
                        </div>
                        <div>
                            <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                Atendida por
                            </p>
                            <p className="mt-1 flex items-center gap-2 text-sm">
                                <UserRound className="size-4 text-muted-foreground" />
                                {appointment.host.name ?? 'Líder'}
                            </p>
                        </div>
                        <div>
                            <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                Persona
                            </p>
                            <p className="mt-1 text-sm">
                                {appointment.person?.fullName ?? 'Persona'}
                                {appointment.person?.email
                                    ? ` · ${appointment.person.email}`
                                    : ''}
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <div className="space-y-6">
                    {canViewNotes && (
                        <Card>
                            <CardHeader>
                                <CardTitle>Notas privadas</CardTitle>
                                <CardDescription>
                                    Visibles solo para quien atiende la cita y el equipo pastoral.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                {appointment.notes ? (
                                    <p className="text-sm whitespace-pre-line">
                                        {appointment.notes}
                                    </p>
                                ) : (
                                    <p className="text-sm text-muted-foreground">
                                        Sin notas registradas.
                                    </p>
                                )}
                            </CardContent>
                        </Card>
                    )}

                    {appointment.cancellationReason && (
                        <Card>
                            <CardHeader>
                                <CardTitle>Motivo de cancelación</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p className="text-sm whitespace-pre-line">
                                    {appointment.cancellationReason}
                                </p>
                            </CardContent>
                        </Card>
                    )}

                    {canManage && isOpen && (
                        <Card>
                            <CardHeader>
                                <CardTitle>Reagendar</CardTitle>
                                <CardDescription>
                                    Mueve la cita a una nueva fecha sin crear otra.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <form className="space-y-4" onSubmit={reschedule} noValidate>
                                    <div className="space-y-2">
                                        <Label htmlFor="scheduled_at">Nueva fecha y hora</Label>
                                        <Input
                                            id="scheduled_at"
                                            type="datetime-local"
                                            value={rescheduleForm.data.scheduled_at}
                                            aria-invalid={
                                                errors.scheduled_at ? true : undefined
                                            }
                                            onChange={(event) =>
                                                rescheduleForm.setData(
                                                    'scheduled_at',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                        {errors.scheduled_at && (
                                            <p className="text-sm text-destructive">
                                                {errors.scheduled_at}
                                            </p>
                                        )}
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="duration_minutes">Duración</Label>
                                        <Input
                                            id="duration_minutes"
                                            type="number"
                                            min={15}
                                            max={480}
                                            step={15}
                                            value={rescheduleForm.data.duration_minutes}
                                            aria-invalid={
                                                errors.duration_minutes ? true : undefined
                                            }
                                            onChange={(event) =>
                                                rescheduleForm.setData(
                                                    'duration_minutes',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                        {errors.duration_minutes && (
                                            <p className="text-sm text-destructive">
                                                {errors.duration_minutes}
                                            </p>
                                        )}
                                    </div>
                                    <Button
                                        type="submit"
                                        variant="outline"
                                        disabled={rescheduleForm.processing}
                                    >
                                        {rescheduleForm.processing
                                            ? 'Reagendando…'
                                            : 'Reagendar cita'}
                                    </Button>
                                </form>
                            </CardContent>
                        </Card>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}

import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, ClipboardCheck } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { AppLayout } from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import type { AttendanceRow, AttendanceStatusOption } from '@/types';

type Props = {
    church: {
        name: string;
        slug: string;
    };
    event: {
        id: number;
        title: string;
        status: string;
        statusLabel: string;
        capacity: number | null;
        occupied: number;
    };
    registrations: AttendanceRow[];
    attendanceStatuses: AttendanceStatusOption[];
    canRecord: boolean;
};

const statusStyles: Record<string, string> = {
    present: 'border-primary/30 bg-primary/10 text-primary',
    absent: 'border-destructive/25 bg-destructive/10 text-destructive',
    late: 'border-accent/40 bg-accent/10 text-accent',
    excused: 'border-border bg-secondary/60 text-foreground',
};

export default function EventAttendance({
    church,
    event,
    registrations,
    attendanceStatuses,
    canRecord,
}: Props) {
    const churchPath = `/churches/${church.slug}`;
    const eventPath = `${churchPath}/events/${event.id}`;

    function record(row: AttendanceRow, status: AttendanceStatusOption['value']) {
        router.post(
            `${eventPath}/attendance`,
            {
                event_registration_id: row.id,
                person_id: row.person?.id ?? null,
                status,
            },
            { preserveScroll: true },
        );
    }

    return (
        <AppLayout>
            <Head title={`Asistencia · ${event.title}`} />

            <Button variant="ghost" size="sm" asChild>
                <Link href={eventPath}>
                    <ArrowLeft />
                    Volver al evento
                </Link>
            </Button>

            <div className="mt-5">
                <p className="eyebrow">Asistencia</p>
                <h1 className="mt-2 font-display text-3xl font-semibold">{event.title}</h1>
                <p className="mt-2 text-sm text-muted-foreground">
                    {event.occupied} personas registradas
                    {event.capacity !== null ? ` de ${event.capacity}` : ''}
                </p>
            </div>

            <div className="mt-6">
                {registrations.length === 0 ? (
                    <Card>
                        <CardContent className="py-12 text-center">
                            <ClipboardCheck className="mx-auto size-9 text-muted-foreground" />
                            <p className="mt-3 text-sm font-semibold">
                                No hay inscripciones para registrar asistencia.
                            </p>
                        </CardContent>
                    </Card>
                ) : (
                    <ul className="space-y-3">
                        {registrations.map((row) => (
                            <li
                                key={row.id}
                                className="rounded-xl border border-border bg-card px-4 py-4"
                            >
                                <div className="flex flex-wrap items-center justify-between gap-3">
                                    <div>
                                        <p className="font-semibold">
                                            {row.person?.fullName ?? row.guestName ?? 'Invitado'}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {row.statusLabel}
                                            {row.guestCount > 0
                                                ? ` · ${row.guestCount} acompañantes`
                                                : ''}
                                        </p>
                                    </div>
                                    <div className="flex flex-wrap items-center gap-2">
                                        {row.attendanceStatusLabel && (
                                            <span
                                                className={cn(
                                                    'rounded-full border px-2.5 py-0.5 text-xs font-semibold',
                                                    statusStyles[row.attendanceStatus ?? ''] ??
                                                        'border-border bg-secondary/60',
                                                )}
                                            >
                                                {row.attendanceStatusLabel}
                                            </span>
                                        )}
                                        {canRecord &&
                                            attendanceStatuses.map((status) => (
                                                <Button
                                                    key={status.value}
                                                    variant={
                                                        row.attendanceStatus === status.value
                                                            ? 'default'
                                                            : 'outline'
                                                    }
                                                    size="sm"
                                                    onClick={() => record(row, status.value)}
                                                >
                                                    {status.label}
                                                </Button>
                                            ))}
                                    </div>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </AppLayout>
    );
}

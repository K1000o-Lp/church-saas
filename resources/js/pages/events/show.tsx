import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    CalendarDays,
    CheckCircle2,
    ClipboardList,
    MapPin,
    ReceiptText,
    Pencil,
    ScanLine,
    Users,
    XCircle,
} from 'lucide-react';

import { EventStatusBadge } from '@/components/events/event-status-badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { AppLayout } from '@/layouts/app-layout';
import { formatDateTime, formatMoney } from '@/lib/format';
import type { EventDetail } from '@/types';

type Props = {
    church: {
        name: string;
        slug: string;
    };
    event: EventDetail;
    canManage: boolean;
};

export default function EventShow({ church, event, canManage }: Props) {
    const churchPath = `/churches/${church.slug}`;
    const eventPath = `${churchPath}/events/${event.id}`;
    const isOpen = ['draft', 'published'].includes(event.status);

    function publish() {
        router.patch(`${eventPath}/publish`, {}, { preserveScroll: true });
    }

    function cancel() {
        if (!window.confirm('¿Cancelar este evento? Las inscripciones se conservan.')) {
            return;
        }

        router.patch(`${eventPath}/cancel`, {}, { preserveScroll: true });
    }

    function complete() {
        if (!window.confirm('¿Marcar este evento como completado?')) {
            return;
        }

        router.patch(`${eventPath}/complete`, {}, { preserveScroll: true });
    }

    return (
        <AppLayout>
            <Head title={`${event.title} · ${church.name}`} />

            <Button variant="ghost" size="sm" asChild>
                <Link href={`${churchPath}/events`}>
                    <ArrowLeft />
                    Volver a eventos
                </Link>
            </Button>

            <div className="mt-5 flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p className="eyebrow">Evento</p>
                    <div className="mt-2 flex flex-wrap items-center gap-2">
                        <h1 className="font-display text-3xl font-semibold">{event.title}</h1>
                        <EventStatusBadge status={event.status} label={event.statusLabel} />
                    </div>
                    <p className="mt-2 max-w-2xl text-sm leading-relaxed text-muted-foreground">
                        {event.description ?? 'Sin descripción.'}
                    </p>
                </div>

                {canManage && (
                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" asChild>
                            <Link href={`${eventPath}/edit`}>
                                <Pencil />
                                Editar
                            </Link>
                        </Button>
                        {event.status === 'draft' && (
                            <Button variant="outline" onClick={publish}>
                                <CheckCircle2 />
                                Publicar
                            </Button>
                        )}
                        {isOpen && (
                            <Button variant="destructive" onClick={cancel}>
                                <XCircle />
                                Cancelar
                            </Button>
                        )}
                        {event.status === 'published' && (
                            <Button variant="outline" onClick={complete}>
                                <CheckCircle2 />
                                Completar
                            </Button>
                        )}
                    </div>
                )}
            </div>

            <div className="mt-8 grid gap-6 lg:grid-cols-[1.5fr_1fr]">
                <Card>
                    <CardHeader>
                        <CardTitle>Detalles</CardTitle>
                        <CardDescription>Información operativa del evento.</CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                Inicio
                            </p>
                            <p className="mt-1 flex items-center gap-2 text-sm">
                                <CalendarDays className="size-4 text-muted-foreground" />
                                {formatDateTime(event.startsAt)}
                            </p>
                        </div>
                        <div>
                            <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                Fin
                            </p>
                            <p className="mt-1 text-sm">{formatDateTime(event.endsAt)}</p>
                        </div>
                        <div>
                            <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                Lugar
                            </p>
                            <p className="mt-1 flex items-center gap-2 text-sm">
                                <MapPin className="size-4 text-muted-foreground" />
                                {event.location ?? 'Sin lugar'}
                            </p>
                        </div>
                        <div>
                            <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                Cupo
                            </p>
                            <p className="mt-1 flex items-center gap-2 text-sm">
                                <Users className="size-4 text-muted-foreground" />
                                {event.occupied}
                                {event.capacity !== null ? ` / ${event.capacity}` : ' inscritos'}
                                {event.isFull && (
                                    <span className="text-xs font-semibold text-accent">
                                        Cupo lleno
                                    </span>
                                )}
                            </p>
                        </div>
                        {event.isPaid && event.price !== null && (
                            <div>
                                <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                    Costo
                                </p>
                                <p className="mt-1 text-sm font-semibold">
                                    {formatMoney(event.price, event.currencyCode ?? 'USD')}
                                </p>
                            </div>
                        )}
                        <div>
                            <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                Registro
                            </p>
                            <p className="mt-1 text-sm">
                                {event.registrationIsOpen ? 'Abierto' : 'Cerrado'}
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Gestión</CardTitle>
                        <CardDescription>Inscripciones y asistencia.</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        <Button variant="outline" className="w-full justify-start" asChild>
                            <Link href={`${eventPath}/registrations`}>
                                <ClipboardList />
                                Ver inscripciones ({event.registrationsCount})
                            </Link>
                        </Button>
                        <Button variant="outline" className="w-full justify-start" asChild>
                            <Link href={`${eventPath}/attendance`}>
                                <Users />
                                Registrar asistencia
                            </Link>
                        </Button>
                        {event.isPaid && (
                            <Button variant="outline" className="w-full justify-start" asChild>
                                <Link href={`${eventPath}/receipts`}>
                                    <ReceiptText />
                                    Comprobantes de pago
                                </Link>
                            </Button>
                        )}
                        <Button variant="outline" className="w-full justify-start" asChild>
                            <Link href={`${eventPath}/check-in`}>
                                <ScanLine />
                                Check-in de entradas
                            </Link>
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}

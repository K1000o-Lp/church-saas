import { Head, Link } from '@inertiajs/react';
import { CalendarDays, MapPin, Ticket } from 'lucide-react';
import QRCode from 'qrcode';
import { useEffect, useState } from 'react';

import { PublicLayout } from '@/layouts/public-layout';
import { formatDateTime } from '@/lib/format';
import type { PublicChurchBranding } from '@/types';

type Props = {
    church: PublicChurchBranding;
    event: {
        id: number;
        title: string;
        slug: string | null;
        startsAt: string | null;
        endsAt: string | null;
        location: string | null;
        status: string;
        statusLabel: string;
    };
    attendee: {
        name: string | null;
        guestCount: number;
        paymentStatus: string;
        paymentStatusLabel: string;
    };
    ticketUrl: string;
};

export default function PublicTicket({ church, event, attendee, ticketUrl }: Props) {
    const [qrCode, setQrCode] = useState<string | null>(null);

    useEffect(() => {
        let active = true;

        void QRCode.toDataURL(ticketUrl, {
            margin: 1,
            width: 320,
            color: { dark: '#111111', light: '#ffffff' },
        }).then((dataUrl) => {
            if (active) {
                setQrCode(dataUrl);
            }
        });

        return () => {
            active = false;
        };
    }, [ticketUrl]);

    return (
        <PublicLayout churchName={church.name} logoUrl={church.logoUrl}>
            <Head title={`Entrada · ${event.title}`} />

            <div className="mx-auto max-w-xl text-center">
                <p className="eyebrow">Entrada digital</p>
                <h1 className="mt-2 font-display text-3xl font-semibold">{event.title}</h1>
                <p className="mt-2 text-sm text-muted-foreground">
                    {attendee.name ?? 'Invitado'}
                    {attendee.guestCount > 0
                        ? ` · ${attendee.guestCount} acompañantes`
                        : ''}
                </p>

                <div className="mt-6 rounded-2xl border border-border bg-card p-6">
                    <div className="mx-auto grid size-64 place-items-center rounded-xl bg-white p-3">
                        {qrCode ? (
                            <img
                                src={qrCode}
                                alt="Código QR de la entrada"
                                className="size-full"
                            />
                        ) : (
                            <Ticket className="size-12 text-muted-foreground" />
                        )}
                    </div>
                    <p className="mt-4 text-xs break-all text-muted-foreground">{ticketUrl}</p>
                </div>

                <dl className="mt-6 space-y-2 text-left text-sm">
                    <div className="flex items-center gap-3">
                        <CalendarDays className="size-4 text-muted-foreground" />
                        <div>
                            <dt className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                Fecha
                            </dt>
                            <dd>{formatDateTime(event.startsAt)}</dd>
                        </div>
                    </div>
                    {event.location && (
                        <div className="flex items-center gap-3">
                            <MapPin className="size-4 text-muted-foreground" />
                            <div>
                                <dt className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                    Lugar
                                </dt>
                                <dd>{event.location}</dd>
                            </div>
                        </div>
                    )}
                </dl>

                <p className="mt-6 text-xs text-muted-foreground">
                    Presenta este código en la entrada del evento.
                </p>

                {event.slug && (
                    <Link
                        href={`/iglesias/${church.slug}/eventos/${event.slug}`}
                        className="mt-4 inline-block text-sm font-semibold text-primary underline-offset-4 hover:underline"
                    >
                        Ver evento
                    </Link>
                )}
            </div>
        </PublicLayout>
    );
}

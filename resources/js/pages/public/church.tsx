import { Head, Link } from '@inertiajs/react';
import { CalendarDays, Clock, MapPin } from 'lucide-react';

import { PublicLayout } from '@/layouts/public-layout';
import { formatDateTime, formatMoney } from '@/lib/format';
import type { PublicChurchBranding, PublicEventSummary } from '@/types';

type Props = {
    church: PublicChurchBranding;
    events: PublicEventSummary[];
};

export default function PublicChurch({ church, events }: Props) {
    return (
        <PublicLayout churchName={church.name} logoUrl={church.logoUrl}>
            <Head title={church.name} />

            <section className="text-center">
                {church.logoUrl && (
                    <img
                        src={church.logoUrl}
                        alt={church.name}
                        className="mx-auto mb-5 h-20 w-auto max-w-48 object-contain"
                    />
                )}
                <p className="eyebrow">Bienvenido</p>
                <h1 className="mt-3 font-display text-4xl font-semibold sm:text-5xl">
                    {church.name}
                </h1>
                {church.tagline && (
                    <p className="mx-auto mt-4 max-w-2xl text-lg text-muted-foreground">
                        {church.tagline}
                    </p>
                )}
                {church.serviceTimes && (
                    <div className="mx-auto mt-6 max-w-xl rounded-xl border border-border bg-card px-5 py-4 text-left">
                        <p className="flex items-center gap-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                            <Clock className="size-4" />
                            Horarios
                        </p>
                        <p className="mt-2 text-sm whitespace-pre-line">
                            {church.serviceTimes}
                        </p>
                    </div>
                )}
            </section>

            <section className="mt-12">
                <h2 className="font-display text-2xl font-semibold">Próximos eventos</h2>

                {events.length === 0 ? (
                    <p className="mt-3 text-sm text-muted-foreground">
                        No hay eventos públicos por el momento.
                    </p>
                ) : (
                    <ul className="mt-4 grid gap-4 sm:grid-cols-2">
                        {events.map((event) => (
                            <li key={event.id}>
                                <Link
                                    href={`/iglesias/${church.slug}/eventos/${event.slug}`}
                                    className="block h-full rounded-xl border border-border bg-card px-5 py-4 transition-colors hover:border-primary/40"
                                >
                                    <p className="font-semibold">{event.title}</p>
                                    <p className="mt-2 flex items-center gap-2 text-sm text-muted-foreground">
                                        <CalendarDays className="size-4" />
                                        {formatDateTime(event.startsAt)}
                                    </p>
                                    {event.location && (
                                        <p className="mt-1 flex items-center gap-2 text-sm text-muted-foreground">
                                            <MapPin className="size-4" />
                                            {event.location}
                                        </p>
                                    )}
                                    {event.isPaid && event.price !== null && (
                                        <p className="mt-3 text-sm font-semibold text-accent">
                                            {formatMoney(
                                                event.price,
                                                event.currencyCode ?? 'USD',
                                            )}
                                        </p>
                                    )}
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
            </section>
        </PublicLayout>
    );
}

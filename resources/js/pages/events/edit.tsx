import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

import { EventForm } from '@/components/events/event-form';
import { Button } from '@/components/ui/button';
import { AppLayout } from '@/layouts/app-layout';
import type { EventCurrencyOption, EventDetail, EventStatusOption } from '@/types';

type Props = {
    church: {
        name: string;
        slug: string;
    };
    event: EventDetail;
    statuses: EventStatusOption[];
    currencies: EventCurrencyOption[];
};

export default function EditEvent({ church, event, statuses, currencies }: Props) {
    const churchPath = `/churches/${church.slug}`;

    return (
        <AppLayout>
            <Head title={`Editar evento · ${church.name}`} />

            <Button variant="ghost" size="sm" asChild>
                <Link href={`${churchPath}/events/${event.id}`}>
                    <ArrowLeft />
                    Volver al evento
                </Link>
            </Button>

            <div className="mt-5">
                <p className="eyebrow">Operación</p>
                <h1 className="mt-2 font-display text-3xl font-semibold">Editar evento</h1>
            </div>

            <div className="mt-8">
                <EventForm
                    action={`${churchPath}/events/${event.id}`}
                    method="put"
                    statuses={statuses}
                    currencies={currencies}
                    event={event}
                    submitLabel="Guardar cambios"
                />
            </div>
        </AppLayout>
    );
}

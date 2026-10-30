import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

import { EventForm } from '@/components/events/event-form';
import { Button } from '@/components/ui/button';
import { AppLayout } from '@/layouts/app-layout';
import type { EventCurrencyOption, EventStatusOption } from '@/types';

type Props = {
    church: {
        name: string;
        slug: string;
    };
    statuses: EventStatusOption[];
    currencies: EventCurrencyOption[];
};

export default function CreateEvent({ church, statuses, currencies }: Props) {
    const churchPath = `/churches/${church.slug}`;

    return (
        <AppLayout>
            <Head title={`Nuevo evento · ${church.name}`} />

            <Button variant="ghost" size="sm" asChild>
                <Link href={`${churchPath}/events`}>
                    <ArrowLeft />
                    Volver a eventos
                </Link>
            </Button>

            <div className="mt-5">
                <p className="eyebrow">Operación</p>
                <h1 className="mt-2 font-display text-3xl font-semibold">Nuevo evento</h1>
            </div>

            <div className="mt-8">
                <EventForm
                    action={`${churchPath}/events`}
                    method="post"
                    statuses={statuses}
                    currencies={currencies}
                    submitLabel="Crear evento"
                />
            </div>
        </AppLayout>
    );
}

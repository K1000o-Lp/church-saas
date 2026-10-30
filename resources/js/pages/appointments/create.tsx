import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

import { AppointmentForm } from '@/components/appointments/appointment-form';
import { Button } from '@/components/ui/button';
import { AppLayout } from '@/layouts/app-layout';
import type { AppointmentHostOption, AppointmentPersonOption } from '@/types';

type Props = {
    church: {
        name: string;
        slug: string;
    };
    people: AppointmentPersonOption[];
    hosts: AppointmentHostOption[];
    selectedPersonId: number | null;
};

export default function CreateAppointment({
    church,
    people,
    hosts,
    selectedPersonId,
}: Props) {
    const churchPath = `/churches/${church.slug}`;

    return (
        <AppLayout>
            <Head title={`Nueva cita · ${church.name}`} />

            <Button variant="ghost" size="sm" asChild>
                <Link href={`${churchPath}/appointments`}>
                    <ArrowLeft />
                    Volver a citas
                </Link>
            </Button>

            <div className="mt-5">
                <p className="eyebrow">Cuidado pastoral</p>
                <h1 className="mt-2 font-display text-3xl font-semibold">Nueva cita</h1>
                <p className="mt-2 max-w-2xl text-sm leading-relaxed text-muted-foreground">
                    Programa una cita en {church.name}. Las notas quedan visibles solo para
                    quien atiende la cita y el equipo pastoral.
                </p>
            </div>

            <div className="mt-8">
                <AppointmentForm
                    action={`${churchPath}/appointments`}
                    method="post"
                    people={people}
                    hosts={hosts}
                    selectedPersonId={selectedPersonId}
                    submitLabel="Programar cita"
                />
            </div>
        </AppLayout>
    );
}

import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

import { AppointmentForm } from '@/components/appointments/appointment-form';
import { Button } from '@/components/ui/button';
import { AppLayout } from '@/layouts/app-layout';
import type {
    AppointmentDetail,
    AppointmentHostOption,
    AppointmentPersonOption,
} from '@/types';

type Props = {
    church: {
        name: string;
        slug: string;
    };
    appointment: AppointmentDetail;
    people: AppointmentPersonOption[];
    hosts: AppointmentHostOption[];
};

export default function EditAppointment({ church, appointment, people, hosts }: Props) {
    const churchPath = `/churches/${church.slug}`;

    return (
        <AppLayout>
            <Head title={`Editar cita · ${church.name}`} />

            <Button variant="ghost" size="sm" asChild>
                <Link href={`${churchPath}/appointments/${appointment.id}`}>
                    <ArrowLeft />
                    Volver a la cita
                </Link>
            </Button>

            <div className="mt-5">
                <p className="eyebrow">Cuidado pastoral</p>
                <h1 className="mt-2 font-display text-3xl font-semibold">Editar cita</h1>
            </div>

            <div className="mt-8">
                <AppointmentForm
                    action={`${churchPath}/appointments/${appointment.id}`}
                    method="put"
                    people={people}
                    hosts={hosts}
                    appointment={appointment}
                    submitLabel="Guardar cambios"
                />
            </div>
        </AppLayout>
    );
}

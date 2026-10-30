import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

import { ServicePlanForm } from '@/components/services/service-plan-form';
import { Button } from '@/components/ui/button';
import { AppLayout } from '@/layouts/app-layout';
import type { ServiceStatusOption } from '@/types';

type Props = {
    church: { name: string; slug: string };
    statuses: ServiceStatusOption[];
};

export default function CreateService({ church, statuses }: Props) {
    const churchPath = `/churches/${church.slug}`;

    return (
        <AppLayout>
            <Head title={`Nuevo culto · ${church.name}`} />
            <Button variant="ghost" size="sm" asChild>
                <Link href={`${churchPath}/services`}>
                    <ArrowLeft />
                    Volver a servicios
                </Link>
            </Button>
            <div className="mt-5">
                <p className="eyebrow">Operación</p>
                <h1 className="mt-2 font-display text-3xl font-semibold">Nuevo culto</h1>
            </div>
            <div className="mt-8">
                <ServicePlanForm
                    action={`${churchPath}/services`}
                    method="post"
                    statuses={statuses}
                    submitLabel="Crear culto"
                />
            </div>
        </AppLayout>
    );
}

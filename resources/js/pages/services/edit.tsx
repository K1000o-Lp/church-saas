import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

import { ServicePlanForm } from '@/components/services/service-plan-form';
import { Button } from '@/components/ui/button';
import { AppLayout } from '@/layouts/app-layout';
import type { ServicePlanDetail, ServiceStatusOption } from '@/types';

type Props = {
    church: { name: string; slug: string };
    plan: ServicePlanDetail;
    statuses: ServiceStatusOption[];
};

export default function EditService({ church, plan, statuses }: Props) {
    const churchPath = `/churches/${church.slug}`;

    return (
        <AppLayout>
            <Head title={`Editar culto · ${church.name}`} />
            <Button variant="ghost" size="sm" asChild>
                <Link href={`${churchPath}/services/${plan.id}`}>
                    <ArrowLeft />
                    Volver al culto
                </Link>
            </Button>
            <div className="mt-5">
                <p className="eyebrow">Operación</p>
                <h1 className="mt-2 font-display text-3xl font-semibold">Editar culto</h1>
            </div>
            <div className="mt-8">
                <ServicePlanForm
                    action={`${churchPath}/services/${plan.id}`}
                    method="put"
                    statuses={statuses}
                    plan={plan}
                    submitLabel="Guardar cambios"
                />
            </div>
        </AppLayout>
    );
}

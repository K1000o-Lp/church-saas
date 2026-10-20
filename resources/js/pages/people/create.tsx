import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

import { PersonForm } from '@/components/people/person-form';
import { Button } from '@/components/ui/button';
import { AppLayout } from '@/layouts/app-layout';
import type { CustomFieldDefinitionSummary } from '@/types';

type Props = {
    church: {
        name: string;
        slug: string;
    };
    definitions: CustomFieldDefinitionSummary[];
};

export default function CreatePerson({ church, definitions }: Props) {
    const churchPath = `/churches/${church.slug}`;

    return (
        <AppLayout>
            <Head title={`Nueva persona · ${church.name}`} />

            <Button variant="ghost" size="sm" asChild>
                <Link href={`${churchPath}/people`}>
                    <ArrowLeft />
                    Volver a personas
                </Link>
            </Button>

            <div className="mt-5">
                <p className="eyebrow">Directorio</p>
                <h1 className="mt-2 font-display text-3xl font-semibold">Nueva persona</h1>
                <p className="mt-2 max-w-2xl text-sm leading-relaxed text-muted-foreground">
                    Registra a una persona en {church.name}. Los campos marcados con asterisco
                    son obligatorios.
                </p>
            </div>

            <div className="mt-8">
                <PersonForm
                    action={`${churchPath}/people`}
                    method="post"
                    definitions={definitions}
                    submitLabel="Crear persona"
                />
            </div>
        </AppLayout>
    );
}

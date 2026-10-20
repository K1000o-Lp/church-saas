import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

import { PersonForm } from '@/components/people/person-form';
import { Button } from '@/components/ui/button';
import { AppLayout } from '@/layouts/app-layout';
import type {
    CustomFieldDefinitionSummary,
    CustomFieldInputValue,
    PersonSummary,
} from '@/types';

type Props = {
    church: {
        name: string;
        slug: string;
    };
    person: PersonSummary;
    definitions: CustomFieldDefinitionSummary[];
    values: Record<string, CustomFieldInputValue>;
};

export default function EditPerson({ church, person, definitions, values }: Props) {
    const churchPath = `/churches/${church.slug}`;

    return (
        <AppLayout>
            <Head title={`Editar ${person.fullName} · ${church.name}`} />

            <Button variant="ghost" size="sm" asChild>
                <Link href={`${churchPath}/people/${person.id}`}>
                    <ArrowLeft />
                    Volver a la persona
                </Link>
            </Button>

            <div className="mt-5">
                <p className="eyebrow">Directorio</p>
                <h1 className="mt-2 font-display text-3xl font-semibold">
                    Editar {person.fullName}
                </h1>
                <p className="mt-2 max-w-2xl text-sm leading-relaxed text-muted-foreground">
                    Actualiza los datos de contacto y la información adicional de esta persona.
                </p>
            </div>

            <div className="mt-8">
                <PersonForm
                    action={`${churchPath}/people/${person.id}`}
                    method="put"
                    definitions={definitions}
                    person={person}
                    values={values}
                    submitLabel="Guardar cambios"
                />
            </div>
        </AppLayout>
    );
}

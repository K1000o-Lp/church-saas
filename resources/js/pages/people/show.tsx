import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Archive, Mail, Pencil, Phone, RotateCcw, UserRound } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { AppLayout } from '@/layouts/app-layout';
import { formatDateTime } from '@/lib/format';
import type { PersonCustomFieldValue, PersonSummary } from '@/types';

type Props = {
    church: {
        name: string;
        slug: string;
    };
    person: PersonSummary;
    customFields: PersonCustomFieldValue[];
    canUpdate: boolean;
    canArchive: boolean;
};

const statusLabels: Record<string, string> = {
    active: 'Activa',
    inactive: 'Inactiva',
    archived: 'Archivada',
};

export default function PersonShow({
    church,
    person,
    customFields,
    canUpdate,
    canArchive,
}: Props) {
    const churchPath = `/churches/${church.slug}`;
    const personPath = `${churchPath}/people/${person.id}`;

    function archive() {
        if (!window.confirm(`¿Archivar a ${person.fullName}?`)) {
            return;
        }

        router.patch(`${personPath}/archive`, {}, { preserveScroll: true });
    }

    function restore() {
        router.patch(`${personPath}/restore`, {}, { preserveScroll: true });
    }

    return (
        <AppLayout>
            <Head title={`${person.fullName} · ${church.name}`} />

            <Button variant="ghost" size="sm" asChild>
                <Link href={`${churchPath}/people`}>
                    <ArrowLeft />
                    Volver a personas
                </Link>
            </Button>

            <div className="mt-5 flex flex-wrap items-start justify-between gap-4">
                <div className="flex items-start gap-4">
                    <span className="grid size-14 shrink-0 place-items-center rounded-2xl bg-primary/10 text-primary">
                        <UserRound className="size-7" />
                    </span>
                    <div>
                        <p className="eyebrow">Persona</p>
                        <h1 className="mt-1 font-display text-3xl font-semibold">
                            {person.fullName}
                        </h1>
                        <p className="mt-2 text-sm text-muted-foreground">
                            {person.email ?? 'Sin correo'} · {person.phone ?? 'Sin teléfono'}
                        </p>
                        <span className="mt-3 inline-flex rounded-full border border-border bg-secondary/60 px-2.5 py-0.5 text-xs font-semibold">
                            {statusLabels[person.status] ?? person.status}
                        </span>
                    </div>
                </div>

                <div className="flex flex-wrap gap-2">
                    {canUpdate && (
                        <Button variant="outline" size="sm" asChild>
                            <Link href={`${personPath}/edit`}>
                                <Pencil />
                                Editar
                            </Link>
                        </Button>
                    )}
                    {canArchive && person.status !== 'archived' && (
                        <Button variant="ghost" size="sm" onClick={archive}>
                            <Archive />
                            Archivar
                        </Button>
                    )}
                    {canArchive && person.status === 'archived' && (
                        <Button variant="outline" size="sm" onClick={restore}>
                            <RotateCcw />
                            Restaurar
                        </Button>
                    )}
                </div>
            </div>

            <div className="mt-6 grid gap-6 lg:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Contacto</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="flex items-start gap-3">
                            <Mail className="mt-0.5 size-4 text-muted-foreground" />
                            <div>
                                <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                    Correo
                                </p>
                                <p className="mt-1 text-sm font-semibold">
                                    {person.email ?? 'Sin correo'}
                                </p>
                            </div>
                        </div>
                        <div className="flex items-start gap-3">
                            <Phone className="mt-0.5 size-4 text-muted-foreground" />
                            <div>
                                <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                    Teléfono
                                </p>
                                <p className="mt-1 text-sm font-semibold">
                                    {person.phone ?? 'Sin teléfono'}
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Información adicional</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {customFields.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No hay campos personalizados con valores para esta persona.
                            </p>
                        ) : (
                            <dl className="space-y-4">
                                {customFields.map((field) => (
                                    <div key={field.key}>
                                        <dt className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                            {field.label}
                                        </dt>
                                        <dd className="mt-1 text-sm font-semibold">{field.value}</dd>
                                    </div>
                                ))}
                            </dl>
                        )}
                    </CardContent>
                </Card>
            </div>

            <p className="mt-6 text-xs text-muted-foreground">
                Creada el {formatDateTime(person.createdAt ?? null)}
                {person.updatedAt ? ` · Actualizada el ${formatDateTime(person.updatedAt)}` : ''}
            </p>
        </AppLayout>
    );
}

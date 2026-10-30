import { Head, Link, router, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import {
    ArrowLeft,
    Archive,
    CalendarPlus,
    Mail,
    Pencil,
    Phone,
    RotateCcw,
    Target,
    Trash2,
    UserPlus,
    UserRound,
    Users,
} from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Select } from '@/components/ui/select';
import { AppLayout } from '@/layouts/app-layout';
import { formatDateTime } from '@/lib/format';
import type {
    PersonCustomFieldValue,
    PersonOption,
    PersonPipelineStage,
    PersonRelationshipSummary,
    PersonSummary,
    RelationshipTypeOption,
} from '@/types';

type Props = {
    church: {
        name: string;
        slug: string;
    };
    person: PersonSummary;
    customFields: PersonCustomFieldValue[];
    canUpdate: boolean;
    canArchive: boolean;
    canScheduleAppointment: boolean;
    relationships: PersonRelationshipSummary[];
    relationshipTypes: RelationshipTypeOption[];
    relationshipPeople: PersonOption[];
    canUpdateRelationships: boolean;
    pipeline: PersonPipelineStage[] | null;
    canManagePipeline: boolean;
};

const statusLabels: Record<string, string> = {
    active: 'Activa',
    inactive: 'Inactiva',
    archived: 'Archivada',
    visitor: 'Visita',
};

export default function PersonShow({
    church,
    person,
    customFields,
    canUpdate,
    canArchive,
    canScheduleAppointment,
    relationships,
    relationshipTypes,
    relationshipPeople,
    canUpdateRelationships,
    pipeline,
    canManagePipeline,
}: Props) {
    const churchPath = `/churches/${church.slug}`;
    const personPath = `${churchPath}/people/${person.id}`;
    const relationshipForm = useForm({
        related_person_id: '',
        relationship: relationshipTypes[0]?.value ?? 'spouse',
    });

    function archive() {
        if (!window.confirm(`¿Archivar a ${person.fullName}?`)) {
            return;
        }

        router.patch(`${personPath}/archive`, {}, { preserveScroll: true });
    }

    function restore() {
        router.patch(`${personPath}/restore`, {}, { preserveScroll: true });
    }

    function addRelationship(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        relationshipForm.post(`${personPath}/relationships`, {
            preserveScroll: true,
            onSuccess: () => relationshipForm.reset('related_person_id'),
        });
    }

    function completeStage(stage: PersonPipelineStage) {
        router.post(`${personPath}/pipeline/${stage.id}`, {}, { preserveScroll: true });
    }

    function reopenStage(stage: PersonPipelineStage) {
        router.delete(`${personPath}/pipeline/${stage.id}`, { preserveScroll: true });
    }

    function removeRelationship(relationship: PersonRelationshipSummary) {
        if (!window.confirm('¿Eliminar esta relación familiar?')) {
            return;
        }

        router.delete(`${personPath}/relationships/${relationship.id}`, {
            preserveScroll: true,
        });
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
                    {canScheduleAppointment && (
                        <Button variant="outline" size="sm" asChild>
                            <Link href={`${churchPath}/appointments/create?person=${person.id}`}>
                                <CalendarPlus />
                                Agendar cita
                            </Link>
                        </Button>
                    )}
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

            <Card className="mt-6">
                <CardHeader>
                    <div className="flex items-center gap-2">
                        <Users className="size-4 text-muted-foreground" />
                        <CardTitle>Familia y vínculos</CardTitle>
                    </div>
                </CardHeader>
                <CardContent className="space-y-5">
                    {relationships.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            Sin relaciones familiares registradas.
                        </p>
                    ) : (
                        <ul className="divide-y divide-border">
                            {relationships.map((relationship) => (
                                <li
                                    key={relationship.id}
                                    className="flex flex-wrap items-center justify-between gap-3 py-3 first:pt-0"
                                >
                                    <div>
                                        <p className="text-sm font-semibold">
                                            {relationship.relatedPerson.fullName ?? 'Persona'}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {relationship.relationshipLabel}
                                            {relationship.relatedPerson.email
                                                ? ` · ${relationship.relatedPerson.email}`
                                                : ''}
                                        </p>
                                    </div>
                                    {canUpdateRelationships && (
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => removeRelationship(relationship)}
                                            aria-label="Eliminar relación"
                                        >
                                            <Trash2 />
                                            Eliminar
                                        </Button>
                                    )}
                                </li>
                            ))}
                        </ul>
                    )}

                    {canUpdateRelationships && (
                        <form
                            className="grid gap-3 sm:grid-cols-[1fr_12rem_auto] sm:items-end"
                            onSubmit={addRelationship}
                            noValidate
                        >
                            <div className="space-y-2">
                                <Label htmlFor="related_person_id">Persona</Label>
                                <Select
                                    id="related_person_id"
                                    value={relationshipForm.data.related_person_id}
                                    aria-invalid={
                                        relationshipForm.errors.related_person_id
                                            ? true
                                            : undefined
                                    }
                                    onChange={(event) =>
                                        relationshipForm.setData(
                                            'related_person_id',
                                            event.target.value,
                                        )
                                    }
                                >
                                    <option value="">Selecciona una persona</option>
                                    {relationshipPeople.map((option) => (
                                        <option key={option.id} value={option.id}>
                                            {option.fullName}
                                            {option.email ? ` · ${option.email}` : ''}
                                        </option>
                                    ))}
                                </Select>
                                {relationshipForm.errors.related_person_id && (
                                    <p className="text-sm text-destructive">
                                        {relationshipForm.errors.related_person_id}
                                    </p>
                                )}
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="relationship">Relación</Label>
                                <Select
                                    id="relationship"
                                    value={relationshipForm.data.relationship}
                                    onChange={(event) =>
                                        relationshipForm.setData(
                                            'relationship',
                                            event.target.value,
                                        )
                                    }
                                >
                                    {relationshipTypes.map((type) => (
                                        <option key={type.value} value={type.value}>
                                            {type.label}
                                        </option>
                                    ))}
                                </Select>
                            </div>
                            <Button type="submit" variant="outline" disabled={relationshipForm.processing}>
                                <UserPlus />
                                Agregar
                            </Button>
                        </form>
                    )}
                </CardContent>
            </Card>

            {pipeline !== null && (
                <Card className="mt-6">
                    <CardHeader>
                        <div className="flex items-center gap-2">
                            <Target className="size-4 text-muted-foreground" />
                            <CardTitle>Etapas de crecimiento</CardTitle>
                        </div>
                    </CardHeader>
                    <CardContent>
                        {pipeline.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                La iglesia todavía no configuró etapas de discipulado.
                            </p>
                        ) : (
                            <ul className="divide-y divide-border">
                                {pipeline.map((stage) => (
                                    <li
                                        key={stage.id}
                                        className="flex flex-wrap items-center justify-between gap-3 py-3 first:pt-0 last:pb-0"
                                    >
                                        <div>
                                            <p className="text-sm font-semibold">
                                                {stage.name}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {stage.completedAt
                                                    ? `Completada el ${formatDateTime(stage.completedAt)}`
                                                    : 'Pendiente'}
                                            </p>
                                        </div>
                                        {canManagePipeline && (
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() =>
                                                    stage.completedAt
                                                        ? reopenStage(stage)
                                                        : completeStage(stage)
                                                }
                                            >
                                                {stage.completedAt ? 'Reabrir' : 'Completar'}
                                            </Button>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>
            )}

            <p className="mt-6 text-xs text-muted-foreground">
                Creada el {formatDateTime(person.createdAt ?? null)}
                {person.updatedAt ? ` · Actualizada el ${formatDateTime(person.updatedAt)}` : ''}
            </p>
        </AppLayout>
    );
}

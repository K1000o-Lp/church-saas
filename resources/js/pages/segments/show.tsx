import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Pencil, Plus, Search, Trash2, Users } from 'lucide-react';
import type { FormEvent } from 'react';

import { SegmentPagination } from '@/components/segments/pagination';
import { PersonTable } from '@/components/segments/person-table';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { AppLayout } from '@/layouts/app-layout';
import type { Pagination, PersonSummary, SegmentDetail } from '@/types';

type Props = {
    church: {
        name: string;
        slug: string;
    };
    segment: SegmentDetail;
    people: PersonSummary[];
    candidates: PersonSummary[];
    filters: {
        search: string;
    };
    pagination: Pagination | null;
    candidatePagination: Pagination | null;
    canManage: boolean;
};

export default function SegmentShow({
    church,
    segment,
    people,
    candidates,
    filters,
    pagination,
    candidatePagination,
    canManage,
}: Props) {
    const churchPath = `/churches/${church.slug}`;
    const segmentPath = `${churchPath}/segments/${segment.id}`;
    const filterForm = useForm({ search: filters.search });
    const isDynamic = segment.type === 'dynamic';

    function submitSearch(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        filterForm.get(segmentPath, { preserveState: true, preserveScroll: true });
    }

    function addMember(person: PersonSummary) {
        router.post(
            `${segmentPath}/members`,
            { person_id: person.id },
            { preserveScroll: true },
        );
    }

    function removeMember(person: PersonSummary) {
        if (!window.confirm(`¿Quitar a ${person.fullName} del segmento?`)) {
            return;
        }

        router.delete(`${segmentPath}/members/${person.id}`, { preserveScroll: true });
    }

    function toggle() {
        router.patch(`${segmentPath}/toggle`, {}, { preserveScroll: true });
    }

    function destroy() {
        if (
            !window.confirm(
                `¿Eliminar el segmento "${segment.name}"? Esta acción no se puede deshacer.`,
            )
        ) {
            return;
        }

        router.delete(segmentPath);
    }

    return (
        <AppLayout>
            <Head title={`${segment.name} · ${church.name}`} />

            <Button variant="ghost" size="sm" asChild>
                <Link href={`${churchPath}/segments`}>
                    <ArrowLeft />
                    Volver a segmentos
                </Link>
            </Button>

            <div className="mt-5 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <div className="flex flex-wrap items-center gap-2">
                        <p className="eyebrow">Segmento</p>
                        <span className="rounded-full border border-primary/30 bg-primary/10 px-2 py-0.5 text-xs font-semibold text-primary">
                            {segment.typeLabel}
                        </span>
                        {!segment.isActive && (
                            <span className="rounded-full border border-destructive/30 bg-destructive/10 px-2 py-0.5 text-xs font-semibold text-destructive">
                                Inactivo
                            </span>
                        )}
                    </div>
                    <h1 className="mt-2 font-display text-3xl font-semibold">{segment.name}</h1>
                    {segment.description && (
                        <p className="mt-2 max-w-2xl text-sm leading-relaxed text-muted-foreground">
                            {segment.description}
                        </p>
                    )}
                    <p className="mt-2 text-sm font-semibold text-muted-foreground">
                        {segment.memberCount}{' '}
                        {segment.memberCount === 1 ? 'persona' : 'personas'}
                    </p>
                </div>

                {canManage && (
                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" asChild>
                            <Link href={`${segmentPath}/edit`}>
                                <Pencil />
                                Editar
                            </Link>
                        </Button>
                        <Button variant="ghost" onClick={toggle}>
                            {segment.isActive ? 'Desactivar' : 'Activar'}
                        </Button>
                        <Button variant="ghost" onClick={destroy}>
                            <Trash2 />
                            Eliminar
                        </Button>
                    </div>
                )}
            </div>

            {isDynamic ? (
                <Card className="mt-6">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Users className="size-5 text-primary" />
                            Regla del segmento
                        </CardTitle>
                        <CardDescription>
                            Se calcula automáticamente. Las personas archivadas no se incluyen.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <p className="text-sm font-medium">
                            {segment.rulesSummary ?? 'Sin condiciones'}
                        </p>
                    </CardContent>
                </Card>
            ) : (
                <Card className="mt-6">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Users className="size-5 text-primary" />
                            Miembros
                        </CardTitle>
                        <CardDescription>
                            Las personas archivadas se conservan en la lista, pero no cuentan para
                            el plan.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <p className="text-sm text-muted-foreground">
                            Este segmento es manual: agrega o quita personas desde aquí.
                        </p>
                    </CardContent>
                </Card>
            )}

            <Card className="mt-4">
                <CardContent className="pt-6">
                    <form
                        className="grid gap-4 sm:grid-cols-[1fr_auto] sm:items-end"
                        onSubmit={submitSearch}
                    >
                        <div className="space-y-2">
                            <Label htmlFor="search">Buscar personas</Label>
                            <div className="relative">
                                <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    id="search"
                                    className="pl-9"
                                    placeholder="Nombre, correo o teléfono"
                                    value={filterForm.data.search}
                                    onChange={(event) =>
                                        filterForm.setData('search', event.target.value)
                                    }
                                />
                            </div>
                        </div>
                        <Button type="submit" variant="secondary">
                            Filtrar
                        </Button>
                    </form>
                </CardContent>
            </Card>

            <div className="mt-6">
                <h2 className="font-display text-xl font-semibold">
                    {isDynamic ? 'Personas que coinciden' : 'Personas en el segmento'}
                </h2>
                <div className="mt-3">
                    <PersonTable
                        churchPath={churchPath}
                        people={people}
                        emptyMessage={
                            isDynamic
                                ? 'Ninguna persona coincide con las reglas del segmento.'
                                : 'Todavía no hay personas en este segmento.'
                        }
                        renderAction={
                            canManage && !isDynamic
                                ? (person) => (
                                      <Button
                                          variant="ghost"
                                          size="sm"
                                          onClick={() => removeMember(person)}
                                      >
                                          Quitar
                                      </Button>
                                  )
                                : undefined
                        }
                    />
                </div>
                {pagination && (
                    <SegmentPagination
                        pagination={pagination}
                        label={isDynamic ? 'personas' : 'miembros'}
                    />
                )}
            </div>

            {!isDynamic && canManage && (
                <div className="mt-8">
                    <h2 className="font-display text-xl font-semibold">Agregar personas</h2>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Busca personas de la iglesia y agrégalas al segmento.
                    </p>
                    <div className="mt-3">
                        <PersonTable
                            churchPath={churchPath}
                            people={candidates}
                            emptyMessage="No hay personas disponibles para agregar."
                            renderAction={(person) => (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => addMember(person)}
                                >
                                    <Plus />
                                    Agregar
                                </Button>
                            )}
                        />
                    </div>
                    {candidatePagination && (
                        <SegmentPagination
                            pagination={candidatePagination}
                            label="candidatos"
                        />
                    )}
                </div>
            )}
        </AppLayout>
    );
}

import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Plus, Search, UserRound } from 'lucide-react';
import type { FormEvent } from 'react';

import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select } from '@/components/ui/select';
import { AppLayout } from '@/layouts/app-layout';
import { formatDate } from '@/lib/format';
import type { Pagination, PersonSummary, SegmentFilterOption } from '@/types';

type Props = {
    church: {
        name: string;
        slug: string;
    };
    people: PersonSummary[];
    filters: {
        search: string;
        status: string;
        segment: string;
    };
    segments: SegmentFilterOption[];
    statuses: {
        value: string;
        label: string;
    }[];
    pagination: Pagination;
    canCreate: boolean;
    canArchive: boolean;
};

const statusLabels: Record<string, string> = {
    active: 'Activa',
    inactive: 'Inactiva',
    archived: 'Archivada',
    visitor: 'Visita',
};

export default function PeopleIndex({
    church,
    people,
    filters,
    statuses,
    segments,
    pagination,
    canCreate,
    canArchive,
}: Props) {
    const churchPath = `/churches/${church.slug}`;
    const filterForm = useForm({
        search: filters.search,
        status: filters.status,
        segment: filters.segment,
    });

    function submitFilters(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        filterForm.get(`${churchPath}/people`, { preserveState: true, preserveScroll: true });
    }

    function archive(person: PersonSummary) {
        if (!window.confirm(`¿Archivar a ${person.fullName}?`)) {
            return;
        }

        router.patch(`${churchPath}/people/${person.id}/archive`, {}, { preserveScroll: true });
    }

    return (
        <AppLayout>
            <Head title={`Personas · ${church.name}`} />

            <Button variant="ghost" size="sm" asChild>
                <Link href={churchPath}>
                    <ArrowLeft />
                    Volver a la iglesia
                </Link>
            </Button>

            <div className="mt-5 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <p className="eyebrow">Directorio</p>
                    <h1 className="mt-2 font-display text-3xl font-semibold">Personas</h1>
                    <p className="mt-2 max-w-2xl text-sm leading-relaxed text-muted-foreground">
                        Personas activas, inactivas y archivadas de {church.name}. Los datos de
                        otras iglesias no están disponibles en esta sesión.
                    </p>
                </div>
                {canCreate && (
                    <Button asChild>
                        <Link href={`${churchPath}/people/create`}>
                            <Plus />
                            Nueva persona
                        </Link>
                    </Button>
                )}
            </div>

            <Card className="mt-6">
                <CardContent className="pt-6">
                    <form
                        className={`grid gap-4 sm:grid-cols-2 sm:items-end ${
                            segments.length > 0
                                ? 'lg:grid-cols-[1fr_12rem_14rem_auto]'
                                : 'lg:grid-cols-[1fr_12rem_auto]'
                        }`}
                        onSubmit={submitFilters}
                    >
                        <div className="space-y-2">
                            <Label htmlFor="search">Buscar</Label>
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

                        <div className="space-y-2">
                            <Label htmlFor="status">Estado</Label>
                            <Select
                                id="status"
                                value={filterForm.data.status}
                                onChange={(event) =>
                                    filterForm.setData('status', event.target.value)
                                }
                            >
                                <option value="">Todos</option>
                                {statuses.map((status) => (
                                    <option key={status.value} value={status.value}>
                                        {status.label}
                                    </option>
                                ))}
                            </Select>
                        </div>

                        {segments.length > 0 && (
                            <div className="space-y-2">
                                <Label htmlFor="segment">Segmento</Label>
                                <Select
                                    id="segment"
                                    value={filterForm.data.segment}
                                    onChange={(event) =>
                                        filterForm.setData('segment', event.target.value)
                                    }
                                >
                                    <option value="">Todos</option>
                                    {segments.map((segment) => (
                                        <option key={segment.id} value={String(segment.id)}>
                                            {segment.name}
                                            {segment.isActive ? '' : ' (inactivo)'}
                                        </option>
                                    ))}
                                </Select>
                            </div>
                        )}

                        <Button type="submit" variant="secondary">
                            Filtrar
                        </Button>
                    </form>
                </CardContent>
            </Card>

            <div className="mt-6">
                {people.length === 0 ? (
                    <Card>
                        <CardContent className="grid place-items-center gap-3 py-16 text-center">
                            <span className="grid size-12 place-items-center rounded-full bg-secondary text-muted-foreground">
                                <UserRound className="size-6" />
                            </span>
                            <div>
                                <p className="font-display text-xl font-semibold">
                                    No encontramos personas
                                </p>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Ajusta los filtros o agrega la primera persona de la iglesia.
                                </p>
                            </div>
                            {canCreate && (
                                <Button asChild size="sm">
                                    <Link href={`${churchPath}/people/create`}>
                                        <Plus />
                                        Nueva persona
                                    </Link>
                                </Button>
                            )}
                        </CardContent>
                    </Card>
                ) : (
                    <div className="overflow-x-auto rounded-xl border border-border bg-card">
                        <table className="w-full min-w-[46rem] text-sm">
                            <thead>
                                <tr className="border-b border-border text-left text-xs tracking-wide text-muted-foreground uppercase">
                                    <th className="px-4 py-3 font-semibold">Persona</th>
                                    <th className="px-4 py-3 font-semibold">Contacto</th>
                                    <th className="px-4 py-3 font-semibold">Estado</th>
                                    <th className="px-4 py-3 font-semibold">Registro</th>
                                    <th className="px-4 py-3 font-semibold">Acciones</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {people.map((person) => (
                                    <tr key={person.id}>
                                        <td className="px-4 py-3">
                                            <Link
                                                href={`${churchPath}/people/${person.id}`}
                                                className="font-semibold text-foreground hover:text-primary"
                                            >
                                                {person.fullName}
                                            </Link>
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">
                                            <p>{person.email ?? 'Sin correo'}</p>
                                            <p className="text-xs">{person.phone ?? 'Sin teléfono'}</p>
                                        </td>
                                        <td className="px-4 py-3">
                                            <span className="rounded-full border border-border bg-secondary/60 px-2 py-0.5 text-xs font-semibold">
                                                {statusLabels[person.status] ?? person.status}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">
                                            {formatDate(person.createdAt ?? null)}
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex gap-2">
                                                <Button variant="outline" size="sm" asChild>
                                                    <Link href={`${churchPath}/people/${person.id}`}>
                                                        Ver
                                                    </Link>
                                                </Button>
                                                {canArchive && person.status !== 'archived' && (
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => archive(person)}
                                                    >
                                                        Archivar
                                                    </Button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>

            {pagination.lastPage > 1 && (
                <div className="mt-5 flex items-center justify-between gap-3 text-sm">
                    <p className="text-muted-foreground">
                        Página {pagination.currentPage} de {pagination.lastPage} ·{' '}
                        {pagination.total} personas
                    </p>
                    <div className="flex gap-2">
                        {pagination.previousPageUrl && (
                            <Button variant="outline" size="sm" asChild>
                                <Link href={pagination.previousPageUrl}>Anterior</Link>
                            </Button>
                        )}
                        {pagination.nextPageUrl && (
                            <Button variant="outline" size="sm" asChild>
                                <Link href={pagination.nextPageUrl}>Siguiente</Link>
                            </Button>
                        )}
                    </div>
                </div>
            )}
        </AppLayout>
    );
}

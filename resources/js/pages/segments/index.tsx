import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Filter, Plus } from 'lucide-react';
import type { FormEvent } from 'react';

import { SegmentPagination } from '@/components/segments/pagination';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select } from '@/components/ui/select';
import { AppLayout } from '@/layouts/app-layout';
import type { Pagination, SegmentSummary, SegmentTypeOption } from '@/types';

type Props = {
    church: {
        name: string;
        slug: string;
    };
    segments: SegmentSummary[];
    filters: {
        search: string;
        type: string;
    };
    types: SegmentTypeOption[];
    pagination: Pagination;
    canManage: boolean;
};

const typeStyles: Record<string, string> = {
    dynamic: 'border-primary/30 bg-primary/10 text-primary',
    static: 'border-border bg-secondary/60 text-foreground',
};

export default function SegmentsIndex({
    church,
    segments,
    filters,
    types,
    pagination,
    canManage,
}: Props) {
    const churchPath = `/churches/${church.slug}`;
    const filterForm = useForm({
        search: filters.search,
        type: filters.type,
    });

    function submitFilters(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        filterForm.get(`${churchPath}/segments`, { preserveState: true, preserveScroll: true });
    }

    function toggle(segment: SegmentSummary) {
        router.patch(`${churchPath}/segments/${segment.id}/toggle`, {}, { preserveScroll: true });
    }

    function destroy(segment: SegmentSummary) {
        if (
            !window.confirm(
                `¿Eliminar el segmento "${segment.name}"? Esta acción no se puede deshacer.`,
            )
        ) {
            return;
        }

        router.delete(`${churchPath}/segments/${segment.id}`, { preserveScroll: true });
    }

    return (
        <AppLayout>
            <Head title={`Segmentos · ${church.name}`} />

            <Button variant="ghost" size="sm" asChild>
                <Link href={churchPath}>
                    <ArrowLeft />
                    Volver a la iglesia
                </Link>
            </Button>

            <div className="mt-5 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <p className="eyebrow">Directorio</p>
                    <h1 className="mt-2 font-display text-3xl font-semibold">Segmentos</h1>
                    <p className="mt-2 max-w-2xl text-sm leading-relaxed text-muted-foreground">
                        Agrupa personas con reglas automáticas o elige sus miembros a mano. Cada
                        iglesia decide cómo llamar a sus zonas, grupos o divisiones.
                    </p>
                </div>
                {canManage && (
                    <Button asChild>
                        <Link href={`${churchPath}/segments/create`}>
                            <Plus />
                            Nuevo segmento
                        </Link>
                    </Button>
                )}
            </div>

            <Card className="mt-6">
                <CardContent className="pt-6">
                    <form
                        className="grid gap-4 sm:grid-cols-[1fr_14rem_auto] sm:items-end"
                        onSubmit={submitFilters}
                    >
                        <div className="space-y-2">
                            <Label htmlFor="search">Buscar</Label>
                            <Input
                                id="search"
                                placeholder="Nombre o descripción"
                                value={filterForm.data.search}
                                onChange={(event) =>
                                    filterForm.setData('search', event.target.value)
                                }
                            />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="type">Tipo</Label>
                            <Select
                                id="type"
                                value={filterForm.data.type}
                                onChange={(event) =>
                                    filterForm.setData('type', event.target.value)
                                }
                            >
                                <option value="">Todos</option>
                                {types.map((type) => (
                                    <option key={type.value} value={type.value}>
                                        {type.label}
                                    </option>
                                ))}
                            </Select>
                        </div>

                        <Button type="submit" variant="secondary">
                            <Filter />
                            Filtrar
                        </Button>
                    </form>
                </CardContent>
            </Card>

            <div className="mt-6">
                {segments.length === 0 ? (
                    <Card>
                        <CardContent className="grid place-items-center gap-3 py-16 text-center">
                            <span className="grid size-12 place-items-center rounded-full bg-secondary text-muted-foreground">
                                <Filter className="size-6" />
                            </span>
                            <div>
                                <p className="font-display text-xl font-semibold">
                                    Todavía no hay segmentos
                                </p>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Crea un segmento dinámico o estático para organizar a las
                                    personas de la iglesia.
                                </p>
                            </div>
                            {canManage && (
                                <Button asChild size="sm">
                                    <Link href={`${churchPath}/segments/create`}>
                                        <Plus />
                                        Nuevo segmento
                                    </Link>
                                </Button>
                            )}
                        </CardContent>
                    </Card>
                ) : (
                    <div className="space-y-3">
                        {segments.map((segment) => (
                            <Card key={segment.id}>
                                <CardContent className="flex flex-wrap items-start justify-between gap-4 py-5">
                                    <div className="min-w-0 flex-1">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <Link
                                                href={`${churchPath}/segments/${segment.id}`}
                                                className="font-display text-lg font-semibold hover:text-primary"
                                            >
                                                {segment.name}
                                            </Link>
                                            <span
                                                className={`rounded-full border px-2 py-0.5 text-xs font-semibold ${
                                                    typeStyles[segment.type] ?? ''
                                                }`}
                                            >
                                                {segment.typeLabel}
                                            </span>
                                            {!segment.isActive && (
                                                <span className="rounded-full border border-destructive/30 bg-destructive/10 px-2 py-0.5 text-xs font-semibold text-destructive">
                                                    Inactivo
                                                </span>
                                            )}
                                        </div>
                                        {segment.description && (
                                            <p className="mt-1 text-sm text-muted-foreground">
                                                {segment.description}
                                            </p>
                                        )}
                                        <p className="mt-2 text-sm text-muted-foreground">
                                            {segment.memberCount}{' '}
                                            {segment.memberCount === 1 ? 'persona' : 'personas'}
                                            {segment.rulesSummary
                                                ? ` · ${segment.rulesSummary}`
                                                : ' · Miembros elegidos manualmente'}
                                        </p>
                                    </div>

                                    <div className="flex flex-wrap gap-2">
                                        <Button variant="outline" size="sm" asChild>
                                            <Link href={`${churchPath}/segments/${segment.id}`}>
                                                Ver
                                            </Link>
                                        </Button>
                                        {canManage && (
                                            <>
                                                <Button variant="ghost" size="sm" asChild>
                                                    <Link
                                                        href={`${churchPath}/segments/${segment.id}/edit`}
                                                    >
                                                        Editar
                                                    </Link>
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => toggle(segment)}
                                                >
                                                    {segment.isActive ? 'Desactivar' : 'Activar'}
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => destroy(segment)}
                                                >
                                                    Eliminar
                                                </Button>
                                            </>
                                        )}
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
            </div>

            <SegmentPagination pagination={pagination} label="segmentos" />
        </AppLayout>
    );
}

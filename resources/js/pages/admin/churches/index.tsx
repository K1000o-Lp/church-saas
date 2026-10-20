import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, Building2, Search } from 'lucide-react';
import type { FormEvent } from 'react';

import { ChurchStatusBadge } from '@/components/admin/church-status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select } from '@/components/ui/select';
import { AdminLayout } from '@/layouts/admin-layout';
import type {
    AdminChurchSummary,
    AdminPagination,
    StatusOption,
} from '@/types';

type Props = {
    churches: AdminChurchSummary[];
    filters: {
        search: string;
        status: string;
    };
    statuses: StatusOption[];
    pagination: AdminPagination;
};

function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Intl.DateTimeFormat('es', { dateStyle: 'medium' }).format(new Date(value));
}

export default function AdminChurchesIndex({
    churches,
    filters,
    statuses,
    pagination,
}: Props) {
    const form = useForm({
        search: filters.search,
        status: filters.status,
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.get('/admin/churches', {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }

    return (
        <AdminLayout>
            <Head title="Iglesias · Administración" />

            <div className="flex flex-wrap items-end justify-between gap-4">
                <div className="flex flex-col gap-2">
                    <p className="eyebrow">Tenants</p>
                    <h1 className="font-display text-3xl font-semibold tracking-tight sm:text-4xl">
                        Iglesias
                    </h1>
                    <p className="max-w-2xl text-sm leading-relaxed text-muted-foreground sm:text-base">
                        Metadatos y conteos agregados. No se exponen personas, notas, montos ni
                        contenido pastoral.
                    </p>
                </div>
                <p className="text-sm text-muted-foreground">
                    {pagination.total} {pagination.total === 1 ? 'iglesia' : 'iglesias'}
                </p>
            </div>

            <Card className="mt-6 gap-0 py-0">
                <CardContent className="p-4">
                    <form
                        className="grid gap-3 sm:grid-cols-[1fr_14rem_auto_auto]"
                        onSubmit={submit}
                    >
                        <div className="space-y-1.5">
                            <Label htmlFor="search">Buscar</Label>
                            <div className="relative">
                                <Search
                                    aria-hidden="true"
                                    className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                />
                                <Input
                                    id="search"
                                    name="search"
                                    value={form.data.search}
                                    placeholder="Nombre o dirección"
                                    className="pl-9"
                                    onChange={(event) =>
                                        form.setData('search', event.target.value)
                                    }
                                />
                            </div>
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="status">Estado</Label>
                            <Select
                                id="status"
                                name="status"
                                value={form.data.status}
                                onChange={(event) =>
                                    form.setData('status', event.target.value)
                                }
                            >
                                <option value="">Todas (sin archivadas)</option>
                                {statuses.map((status) => (
                                    <option key={status.value} value={status.value}>
                                        {status.label}
                                    </option>
                                ))}
                            </Select>
                        </div>

                        <div className="flex items-end">
                            <Button type="submit" className="w-full sm:w-auto" disabled={form.processing}>
                                Filtrar
                            </Button>
                        </div>

                        <div className="flex items-end">
                            <Button variant="ghost" className="w-full sm:w-auto" asChild>
                                <Link href="/admin/churches">Limpiar</Link>
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>

            <div className="mt-6 overflow-hidden rounded-xl border border-border bg-card">
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[820px] text-left text-sm">
                        <thead className="border-b border-border bg-secondary/50 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                            <tr>
                                <th scope="col" className="px-4 py-3">
                                    Iglesia
                                </th>
                                <th scope="col" className="px-4 py-3">
                                    Estado
                                </th>
                                <th scope="col" className="px-4 py-3">
                                    País / zona
                                </th>
                                <th scope="col" className="px-4 py-3 text-right">
                                    Personas
                                </th>
                                <th scope="col" className="px-4 py-3 text-right">
                                    Miembros
                                </th>
                                <th scope="col" className="px-4 py-3">
                                    Creada
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-border">
                            {churches.map((church) => (
                                <tr key={church.id} className="align-top">
                                    <td className="px-4 py-4">
                                        <Link
                                            href={`/admin/churches/${church.slug}`}
                                            className="group inline-flex flex-col gap-0.5 outline-none focus-visible:ring-2 focus-visible:ring-ring/60 focus-visible:ring-offset-2 focus-visible:ring-offset-background"
                                        >
                                            <span className="font-semibold text-foreground group-hover:text-primary">
                                                {church.name}
                                            </span>
                                            <span className="text-xs text-muted-foreground">
                                                /{church.slug}
                                            </span>
                                        </Link>
                                    </td>
                                    <td className="px-4 py-4">
                                        <ChurchStatusBadge
                                            status={church.status}
                                            label={church.statusLabel}
                                        />
                                    </td>
                                    <td className="px-4 py-4 text-muted-foreground">
                                        <span className="block">{church.countryCode ?? '—'}</span>
                                        <span className="text-xs">{church.timezone ?? '—'}</span>
                                    </td>
                                    <td className="px-4 py-4 text-right font-semibold tabular-nums">
                                        {church.metrics.people}
                                    </td>
                                    <td className="px-4 py-4 text-right font-semibold tabular-nums">
                                        {church.metrics.activeMemberships}
                                    </td>
                                    <td className="px-4 py-4 text-muted-foreground">
                                        {formatDate(church.createdAt)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {churches.length === 0 && (
                    <div className="flex flex-col items-center gap-3 px-6 py-14 text-center">
                        <span className="grid size-12 place-items-center rounded-full bg-secondary text-muted-foreground">
                            <Building2 className="size-5" />
                        </span>
                        <div>
                            <p className="font-display text-lg font-semibold">
                                No hay iglesias con estos filtros
                            </p>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Ajusta la búsqueda o el estado seleccionado.
                            </p>
                        </div>
                    </div>
                )}
            </div>

            <div className="mt-5 flex flex-wrap items-center justify-between gap-3">
                <p className="text-sm text-muted-foreground">
                    Página {pagination.currentPage} de {pagination.lastPage}
                </p>
                <div className="flex items-center gap-2">
                    {pagination.previousPageUrl ? (
                        <Button variant="outline" size="sm" asChild>
                            <Link href={pagination.previousPageUrl} preserveScroll>
                                <ArrowLeft />
                                Anterior
                            </Link>
                        </Button>
                    ) : (
                        <Button variant="outline" size="sm" disabled>
                            <ArrowLeft />
                            Anterior
                        </Button>
                    )}

                    {pagination.nextPageUrl ? (
                        <Button variant="outline" size="sm" asChild>
                            <Link href={pagination.nextPageUrl} preserveScroll>
                                Siguiente
                                <ArrowRight />
                            </Link>
                        </Button>
                    ) : (
                        <Button variant="outline" size="sm" disabled>
                            Siguiente
                            <ArrowRight />
                        </Button>
                    )}
                </div>
            </div>
        </AdminLayout>
    );
}

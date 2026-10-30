import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowRight, ReceiptText, Search } from 'lucide-react';
import type { FormEvent } from 'react';

import { InvoiceStatusBadge } from '@/components/admin/invoice-status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select } from '@/components/ui/select';
import { AdminLayout } from '@/layouts/admin-layout';
import { formatDate, formatMoney } from '@/lib/format';
import type {
    AdminInvoice,
    AdminPagination,
    InvoiceStatusOption,
} from '@/types';

type Props = {
    invoices: AdminInvoice[];
    filters: {
        search: string;
        status: string;
    };
    statuses: InvoiceStatusOption[];
    pagination: AdminPagination;
};

export default function AdminInvoicesIndex({
    invoices,
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
        form.get('/admin/invoices', { preserveState: true, preserveScroll: true });
    }

    return (
        <AdminLayout>
            <Head title="Facturación · Administración" />

            <div className="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p className="eyebrow">Plataforma</p>
                    <h1 className="mt-2 font-display text-3xl font-semibold tracking-tight sm:text-4xl">
                        Facturación
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Facturas manuales en USD, pagos registrados y estados. El historial se
                        conserva tras una purga.
                    </p>
                </div>
            </div>

            <Card className="mt-6">
                <CardHeader>
                    <CardTitle>Filtros</CardTitle>
                </CardHeader>
                <CardContent>
                    <form className="grid gap-3 sm:grid-cols-[1fr_14rem_auto]" onSubmit={submit}>
                        <div className="space-y-2">
                            <Label htmlFor="billing-search">Buscar</Label>
                            <Input
                                id="billing-search"
                                placeholder="Número, iglesia o slug"
                                value={form.data.search}
                                onChange={(event) => form.setData('search', event.target.value)}
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="billing-status">Estado</Label>
                            <Select
                                id="billing-status"
                                value={form.data.status}
                                onChange={(event) => form.setData('status', event.target.value)}
                            >
                                <option value="">Todos</option>
                                {statuses.map((status) => (
                                    <option key={status.value} value={status.value}>
                                        {status.label}
                                    </option>
                                ))}
                            </Select>
                        </div>
                        <div className="flex items-end">
                            <Button type="submit" disabled={form.processing}>
                                <Search />
                                Filtrar
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>

            <Card className="mt-6">
                <CardHeader>
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <CardTitle>Facturas</CardTitle>
                        <p className="text-sm text-muted-foreground">
                            {pagination.total} factura(s)
                        </p>
                    </div>
                </CardHeader>
                <CardContent>
                    {invoices.length === 0 ? (
                        <div className="py-10 text-center">
                            <ReceiptText className="mx-auto size-8 text-muted-foreground" />
                            <p className="mt-3 text-sm font-semibold">
                                No hay facturas para estos filtros.
                            </p>
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[52rem] text-sm">
                                <thead>
                                    <tr className="border-b border-border text-left text-xs tracking-wide text-muted-foreground uppercase">
                                        <th className="pb-3 pr-4 font-semibold">Número</th>
                                        <th className="pb-3 pr-4 font-semibold">Iglesia</th>
                                        <th className="pb-3 pr-4 font-semibold">Estado</th>
                                        <th className="pb-3 pr-4 text-right font-semibold">
                                            Total
                                        </th>
                                        <th className="pb-3 pr-4 text-right font-semibold">
                                            Pagado
                                        </th>
                                        <th className="pb-3 pr-4 text-right font-semibold">
                                            Saldo
                                        </th>
                                        <th className="pb-3 pr-4 font-semibold">Vence</th>
                                        <th className="pb-3 font-semibold" />
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {invoices.map((invoice) => (
                                        <tr key={invoice.id}>
                                            <td className="py-3 pr-4 font-mono text-xs font-semibold">
                                                {invoice.number}
                                            </td>
                                            <td className="py-3 pr-4">
                                                <p className="font-semibold">
                                                    {invoice.church.name}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    /{invoice.church.slug}
                                                </p>
                                            </td>
                                            <td className="py-3 pr-4">
                                                <InvoiceStatusBadge
                                                    status={invoice.status}
                                                    label={invoice.statusLabel}
                                                />
                                            </td>
                                            <td className="py-3 pr-4 text-right tabular-nums">
                                                {formatMoney(
                                                    invoice.amount,
                                                    invoice.currencyCode,
                                                )}
                                            </td>
                                            <td className="py-3 pr-4 text-right tabular-nums">
                                                {formatMoney(
                                                    invoice.paid,
                                                    invoice.currencyCode,
                                                )}
                                            </td>
                                            <td className="py-3 pr-4 text-right tabular-nums">
                                                {formatMoney(
                                                    invoice.remaining,
                                                    invoice.currencyCode,
                                                )}
                                            </td>
                                            <td className="py-3 pr-4 text-muted-foreground">
                                                {formatDate(invoice.dueAt)}
                                            </td>
                                            <td className="py-3 text-right">
                                                {invoice.church.exists ? (
                                                    <Button variant="ghost" size="sm" asChild>
                                                        <Link
                                                            href={`/admin/churches/${invoice.church.slug}/invoices`}
                                                        >
                                                            Gestionar
                                                            <ArrowRight />
                                                        </Link>
                                                    </Button>
                                                ) : (
                                                    <span className="text-xs text-muted-foreground">
                                                        Iglesia purgada
                                                    </span>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}

                    {pagination.lastPage > 1 && (
                        <div className="mt-4 flex items-center justify-between gap-3 border-t border-border pt-4 text-sm">
                            <p className="text-muted-foreground">
                                Página {pagination.currentPage} de {pagination.lastPage}
                            </p>
                            <div className="flex gap-2">
                                {pagination.previousPageUrl && (
                                    <Button variant="outline" size="sm" asChild>
                                        <Link href={pagination.previousPageUrl} preserveScroll>
                                            Anterior
                                        </Link>
                                    </Button>
                                )}
                                {pagination.nextPageUrl && (
                                    <Button variant="outline" size="sm" asChild>
                                        <Link href={pagination.nextPageUrl} preserveScroll>
                                            Siguiente
                                        </Link>
                                    </Button>
                                )}
                            </div>
                        </div>
                    )}
                </CardContent>
            </Card>
        </AdminLayout>
    );
}

import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Layers, ReceiptText, ShieldAlert } from 'lucide-react';

import { AdminInvoiceCard } from '@/components/admin/admin-invoice-card';
import { ChurchStatusBadge } from '@/components/admin/church-status-badge';
import { IssueInvoiceForm } from '@/components/admin/issue-invoice-form';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { AdminLayout } from '@/layouts/admin-layout';
import type {
    AdminInvoice,
    ChurchStatusValue,
    InvoiceStatusOption,
    PaymentMethodOption,
} from '@/types';

type Props = {
    church: {
        id: number;
        name: string;
        slug: string;
        status: ChurchStatusValue;
        statusLabel: string;
        currencyCode: string | null;
    };
    invoices: AdminInvoice[];
    statuses: InvoiceStatusOption[];
    paymentMethods: PaymentMethodOption[];
    canEdit: boolean;
};

export default function AdminChurchInvoices({
    church,
    invoices,
    statuses,
    paymentMethods,
    canEdit,
}: Props) {
    const outstanding = invoices
        .filter((invoice) => ['issued', 'overdue'].includes(invoice.status))
        .reduce((total, invoice) => total + invoice.remainingMinor, 0);

    return (
        <AdminLayout>
            <Head title={`Facturación · ${church.name}`} />

            <Button variant="ghost" size="sm" asChild>
                <Link href={`/admin/churches/${church.slug}`}>
                    <ArrowLeft />
                    {church.name}
                </Link>
            </Button>

            <div className="mt-5 flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p className="eyebrow">Plataforma · Facturación manual</p>
                    <div className="mt-2 flex flex-wrap items-center gap-3">
                        <h1 className="font-display text-3xl font-semibold tracking-tight sm:text-4xl">
                            Facturas
                        </h1>
                        <ChurchStatusBadge status={church.status} label={church.statusLabel} />
                    </div>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {church.name} · /{church.slug}
                    </p>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    <Button variant="outline" size="sm" asChild>
                        <Link href={`/admin/churches/${church.slug}/plan`}>
                            <Layers />
                            Plan
                        </Link>
                    </Button>
                    <Button variant="outline" size="sm" asChild>
                        <Link href="/admin/invoices">
                            <ReceiptText />
                            Todas las facturas
                        </Link>
                    </Button>
                </div>
            </div>

            {!canEdit && (
                <div className="mt-5 flex items-start gap-3 rounded-xl border border-accent/30 bg-accent/10 px-4 py-3 text-sm">
                    <ShieldAlert className="mt-0.5 size-4 shrink-0 text-accent" />
                    <p>
                        La iglesia está archivada. Restáurala para emitir facturas, registrar pagos
                        o cambiar estados.
                    </p>
                </div>
            )}

            <div className="mt-6 grid gap-3 sm:grid-cols-3">
                <div className="rounded-xl border border-border bg-card px-4 py-3">
                    <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                        Facturas
                    </p>
                    <p className="mt-2 font-display text-2xl font-semibold tabular-nums">
                        {invoices.length}
                    </p>
                </div>
                <div className="rounded-xl border border-border bg-card px-4 py-3">
                    <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                        Vencidas o emitidas
                    </p>
                    <p className="mt-2 font-display text-2xl font-semibold tabular-nums">
                        {
                            invoices.filter((invoice) =>
                                ['issued', 'overdue'].includes(invoice.status),
                            ).length
                        }
                    </p>
                </div>
                <div className="rounded-xl border border-border bg-card px-4 py-3">
                    <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                        Saldo pendiente
                    </p>
                    <p className="mt-2 font-display text-2xl font-semibold tabular-nums">
                        {new Intl.NumberFormat('es', {
                            style: 'currency',
                            currency: 'USD',
                            maximumFractionDigits: 2,
                        }).format(outstanding / 100)}
                    </p>
                </div>
            </div>

            <div className="mt-6 grid gap-6 lg:grid-cols-[1.6fr_1fr]">
                <div className="space-y-4">
                    {invoices.length === 0 ? (
                        <Card>
                            <CardContent className="py-10 text-center">
                                <ReceiptText className="mx-auto size-8 text-muted-foreground" />
                                <p className="mt-3 text-sm font-semibold">
                                    Esta iglesia todavía no tiene facturas.
                                </p>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Emite la primera factura desde el panel lateral.
                                </p>
                            </CardContent>
                        </Card>
                    ) : (
                        invoices.map((invoice) => (
                            <AdminInvoiceCard
                                key={invoice.id}
                                invoice={invoice}
                                statuses={statuses}
                                paymentMethods={paymentMethods}
                                canEdit={canEdit}
                            />
                        ))
                    )}
                </div>

                <div className="space-y-4">
                    <IssueInvoiceForm churchSlug={church.slug} canEdit={canEdit} />
                </div>
            </div>
        </AdminLayout>
    );
}

import { CreditCard, FileText, History } from 'lucide-react';

import { InvoicePaymentForm } from '@/components/admin/invoice-payment-form';
import { InvoiceStatusBadge } from '@/components/admin/invoice-status-badge';
import { InvoiceStatusForm } from '@/components/admin/invoice-status-form';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { formatDate, formatDateTime, formatMoney } from '@/lib/format';
import type { AdminInvoice, InvoiceStatusOption, PaymentMethodOption } from '@/types';

type Props = {
    invoice: AdminInvoice;
    statuses: InvoiceStatusOption[];
    paymentMethods: PaymentMethodOption[];
    canEdit: boolean;
};

function Detail({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <dt className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                {label}
            </dt>
            <dd className="mt-1 text-sm">{value}</dd>
        </div>
    );
}

export function AdminInvoiceCard({ invoice, statuses, paymentMethods, canEdit }: Props) {
    const canAcceptPayments = ['issued', 'overdue'].includes(invoice.status);
    const amount = Number(invoice.amount);
    const paid = Number(invoice.paid);
    const remaining = Number(invoice.remaining);
    const progress = amount === 0 ? 0 : Math.min(100, Math.round((paid / amount) * 100));

    return (
        <Card>
            <CardHeader>
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div className="flex flex-wrap items-center gap-2">
                            <CardTitle className="font-mono text-base">{invoice.number}</CardTitle>
                            <InvoiceStatusBadge
                                status={invoice.status}
                                label={invoice.statusLabel}
                            />
                        </div>
                        <CardDescription>
                            {invoice.description ?? 'Sin concepto'} · {invoice.church.name}
                        </CardDescription>
                    </div>
                    <div className="text-right">
                        <p className="font-display text-2xl font-semibold tabular-nums">
                            {formatMoney(invoice.amount, invoice.currencyCode)}
                        </p>
                        <p className="text-xs text-muted-foreground">
                            Pagado {formatMoney(invoice.paid, invoice.currencyCode)}
                        </p>
                    </div>
                </div>
            </CardHeader>

            <CardContent className="space-y-5">
                <div>
                    <div
                        className="h-2 overflow-hidden rounded-full bg-secondary"
                        role="progressbar"
                        aria-valuenow={progress}
                        aria-valuemin={0}
                        aria-valuemax={100}
                        aria-label="Progreso de pago"
                    >
                        <div
                            className="h-full rounded-full bg-primary transition-[width]"
                            style={{ width: `${progress}%` }}
                        />
                    </div>
                    <p className="mt-1.5 text-xs text-muted-foreground">
                        Saldo pendiente{' '}
                        <strong className="text-foreground">
                            {formatMoney(invoice.remaining, invoice.currencyCode)}
                        </strong>
                    </p>
                </div>

                <dl className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <Detail label="Emitida" value={formatDate(invoice.issuedAt)} />
                    <Detail label="Vence" value={formatDate(invoice.dueAt)} />
                    <Detail label="Pagada" value={formatDate(invoice.paidAt)} />
                    <Detail
                        label="Emitida por"
                        value={invoice.issuedBy ?? 'Administración'}
                    />
                </dl>

                {invoice.notes && (
                    <div className="flex items-start gap-2 rounded-lg border border-border bg-secondary/40 px-3 py-2.5 text-sm">
                        <FileText className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                        <p className="whitespace-pre-line">{invoice.notes}</p>
                    </div>
                )}

                {invoice.status === 'void' && (
                    <div className="rounded-lg border border-destructive/25 bg-destructive/10 px-3 py-2.5 text-sm text-destructive">
                        Anulada {formatDateTime(invoice.voidedAt)}
                        {invoice.voidReason ? ` · ${invoice.voidReason}` : ''}
                    </div>
                )}

                <div>
                    <div className="flex items-center gap-2">
                        <History className="size-4 text-muted-foreground" />
                        <h3 className="text-sm font-semibold">Pagos registrados</h3>
                    </div>
                    {invoice.payments.length === 0 ? (
                        <p className="mt-2 text-sm text-muted-foreground">
                            Todavía no hay pagos para esta factura.
                        </p>
                    ) : (
                        <ul className="mt-2 divide-y divide-border">
                            {invoice.payments.map((payment) => (
                                <li
                                    key={payment.id}
                                    className="flex flex-wrap items-start justify-between gap-2 py-2.5 first:pt-0 last:pb-0"
                                >
                                    <div className="flex items-start gap-2.5">
                                        <CreditCard className="mt-0.5 size-4 text-muted-foreground" />
                                        <div>
                                            <p className="text-sm font-semibold">
                                                {formatMoney(
                                                    payment.amount,
                                                    payment.currencyCode,
                                                )}{' '}
                                                · {payment.methodLabel}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {formatDateTime(payment.paidAt)}
                                                {payment.reference
                                                    ? ` · Ref. ${payment.reference}`
                                                    : ''}
                                                {payment.recordedBy
                                                    ? ` · ${payment.recordedBy}`
                                                    : ''}
                                            </p>
                                            {payment.notes && (
                                                <p className="mt-1 text-xs text-muted-foreground">
                                                    {payment.notes}
                                                </p>
                                            )}
                                        </div>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>

                {canEdit && (
                    <div className="grid gap-4 xl:grid-cols-2">
                        {canAcceptPayments && remaining > 0 && (
                            <InvoicePaymentForm
                                key={`${invoice.id}-payment-${invoice.remaining}`}
                                invoiceId={invoice.id}
                                remaining={invoice.remaining}
                                currencyCode={invoice.currencyCode}
                                methods={paymentMethods}
                                canEdit={canEdit}
                            />
                        )}
                        {canEdit && (
                            <InvoiceStatusForm
                                key={`${invoice.id}-status-${invoice.status}`}
                                invoiceId={invoice.id}
                                status={invoice.status}
                                statuses={statuses}
                                canEdit={canEdit}
                            />
                        )}
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

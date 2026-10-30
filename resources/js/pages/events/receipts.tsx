import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Download, ReceiptText } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Select } from '@/components/ui/select';
import { AppLayout } from '@/layouts/app-layout';
import { formatDateTime, formatMoney } from '@/lib/format';
import type { AdminPaymentReceipt } from '@/types';

type Props = {
    church: {
        name: string;
        slug: string;
    };
    event: {
        id: number;
        title: string;
        slug: string | null;
        status: string;
        statusLabel: string;
        isPaid: boolean;
        price: string | null;
        currencyCode: string | null;
    };
    receipts: AdminPaymentReceipt[];
    filters: {
        status: string;
    };
    statuses: Array<{ value: string; label: string }>;
    canManage: boolean;
};

const statusStyles: Record<string, string> = {
    pending: 'border-accent/40 bg-accent/10 text-accent',
    verified: 'border-primary/30 bg-primary/10 text-primary',
    rejected: 'border-destructive/25 bg-destructive/10 text-destructive',
};

export default function EventReceipts({
    church,
    event,
    receipts,
    filters,
    statuses,
    canManage,
}: Props) {
    const churchPath = `/churches/${church.slug}`;
    const eventPath = `${churchPath}/events/${event.id}`;

    function filter(status: string) {
        router.get(`${eventPath}/receipts`, status === '' ? {} : { status }, {
            preserveState: true,
            preserveScroll: true,
        });
    }

    function verify(receipt: AdminPaymentReceipt) {
        if (!window.confirm('¿Verificar este comprobante y aprobar el pago?')) {
            return;
        }

        router.patch(`${eventPath}/receipts/${receipt.id}/verify`, {}, {
            preserveScroll: true,
        });
    }

    function reject(receipt: AdminPaymentReceipt) {
        const reason = window.prompt('Motivo del rechazo (opcional):') ?? '';

        router.patch(
            `${eventPath}/receipts/${receipt.id}/reject`,
            { reason: reason.trim() === '' ? null : reason.trim() },
            { preserveScroll: true },
        );
    }

    return (
        <AppLayout>
            <Head title={`Comprobantes · ${event.title}`} />

            <Button variant="ghost" size="sm" asChild>
                <Link href={eventPath}>
                    <ArrowLeft />
                    Volver al evento
                </Link>
            </Button>

            <div className="mt-5 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <p className="eyebrow">Pagos</p>
                    <h1 className="mt-2 font-display text-3xl font-semibold">
                        Comprobantes
                    </h1>
                    <p className="mt-2 text-sm text-muted-foreground">{event.title}</p>
                </div>
                <div className="w-48">
                    <Select
                        value={filters.status}
                        onChange={(changeEvent) => filter(changeEvent.target.value)}
                        aria-label="Filtrar por estado"
                    >
                        <option value="">Todos</option>
                        {statuses.map((status) => (
                            <option key={status.value} value={status.value}>
                                {status.label}
                            </option>
                        ))}
                    </Select>
                </div>
            </div>

            <div className="mt-6">
                {receipts.length === 0 ? (
                    <Card>
                        <CardContent className="py-12 text-center">
                            <ReceiptText className="mx-auto size-9 text-muted-foreground" />
                            <p className="mt-3 text-sm font-semibold">
                                No hay comprobantes con estos filtros.
                            </p>
                        </CardContent>
                    </Card>
                ) : (
                    <ul className="space-y-3">
                        {receipts.map((receipt) => (
                            <li
                                key={receipt.id}
                                className="rounded-xl border border-border bg-card px-4 py-4"
                            >
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <p className="font-semibold">
                                            {receipt.person?.fullName ??
                                                receipt.guestName ??
                                                'Inscripción'}
                                        </p>
                                        <p className="mt-0.5 text-sm text-muted-foreground">
                                            {formatMoney(receipt.amount, receipt.currencyCode)}
                                            {receipt.referenceNumber
                                                ? ` · Ref. ${receipt.referenceNumber}`
                                                : ''}
                                        </p>
                                        <p className="mt-0.5 text-xs text-muted-foreground">
                                            Enviado {formatDateTime(receipt.submittedAt)}
                                        </p>
                                        {receipt.rejectionReason && (
                                            <p className="mt-1 text-xs text-destructive">
                                                {receipt.rejectionReason}
                                            </p>
                                        )}
                                    </div>
                                    <div className="flex flex-wrap items-center gap-2">
                                        <span
                                            className={`rounded-full border px-2.5 py-0.5 text-xs font-semibold ${
                                                statusStyles[receipt.status] ??
                                                'border-border bg-secondary/60'
                                            }`}
                                        >
                                            {receipt.status === 'pending'
                                                ? 'Pendiente'
                                                : receipt.status === 'verified'
                                                  ? 'Verificado'
                                                  : 'Rechazado'}
                                        </span>
                                        <Button variant="outline" size="sm" asChild>
                                            <a
                                                href={`${eventPath}/receipts/${receipt.id}/download`}
                                            >
                                                <Download />
                                                Archivo
                                            </a>
                                        </Button>
                                        {canManage && receipt.status === 'pending' && (
                                            <>
                                                <Button
                                                    size="sm"
                                                    onClick={() => verify(receipt)}
                                                >
                                                    Verificar
                                                </Button>
                                                <Button
                                                    variant="destructive"
                                                    size="sm"
                                                    onClick={() => reject(receipt)}
                                                >
                                                    Rechazar
                                                </Button>
                                            </>
                                        )}
                                    </div>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </AppLayout>
    );
}

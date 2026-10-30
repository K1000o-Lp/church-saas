import { Head, Link, useForm } from '@inertiajs/react';
import { CheckCircle2, FileUp, ReceiptText, Ticket, XCircle } from 'lucide-react';
import type { ChangeEvent, FormEvent } from 'react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { PublicLayout } from '@/layouts/public-layout';
import { formatDateTime, formatMoney } from '@/lib/format';
import type { PaymentReceiptSummary, PublicChurchBranding } from '@/types';

type Props = {
    token: string;
    church: PublicChurchBranding;
    event: {
        id: number;
        title: string;
        slug: string | null;
        price: string | null;
        currencyCode: string | null;
    };
    registration: {
        status: string;
        paymentStatus: string;
        paymentStatusLabel: string;
    };
    receipts: PaymentReceiptSummary[];
    ticketUrl: string | null;
    canUpload: boolean;
};

const statusStyles: Record<string, string> = {
    pending: 'border-accent/40 bg-accent/10 text-accent',
    verified: 'border-primary/30 bg-primary/10 text-primary',
    rejected: 'border-destructive/25 bg-destructive/10 text-destructive',
};

export default function PublicReceipt({
    token,
    church,
    event,
    registration,
    receipts,
    ticketUrl,
    canUpload,
}: Props) {
    const form = useForm({
        amount: event.price ?? '',
        reference_number: '',
        file: null as File | null,
        website: '',
    });

    const errors = form.errors as Record<string, string>;

    function submit(submitEvent: FormEvent<HTMLFormElement>) {
        submitEvent.preventDefault();
        form.post(`/comprobantes/${token}`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => form.reset('file', 'reference_number'),
        });
    }

    function selectFile(changeEvent: ChangeEvent<HTMLInputElement>) {
        form.setData('file', changeEvent.target.files?.[0] ?? null);
    }

    return (
        <PublicLayout churchName={church.name} logoUrl={church.logoUrl}>
            <Head title={`Comprobante · ${event.title}`} />

            <div className="mx-auto max-w-2xl">
                <Link
                    href={`/iglesias/${church.slug}/eventos/${event.slug}`}
                    className="text-sm font-semibold text-primary underline-offset-4 hover:underline"
                >
                    ← Volver al evento
                </Link>

                <div className="mt-5 flex items-start gap-3">
                    <span className="grid size-11 shrink-0 place-items-center rounded-xl bg-primary/10 text-primary">
                        <ReceiptText className="size-5" />
                    </span>
                    <div>
                        <p className="eyebrow">Comprobante de pago</p>
                        <h1 className="mt-1 font-display text-3xl font-semibold">
                            {event.title}
                        </h1>
                        <p className="mt-2 text-sm text-muted-foreground">
                            Estado del pago: <strong>{registration.paymentStatusLabel}</strong>
                        </p>
                    </div>
                </div>

                {receipts.length > 0 && (
                    <ul className="mt-6 space-y-2">
                        {receipts.map((receipt) => (
                            <li
                                key={receipt.id}
                                className="flex flex-wrap items-start justify-between gap-3 rounded-lg border border-border bg-card px-4 py-3"
                            >
                                <div>
                                    <p className="text-sm font-semibold">
                                        {formatMoney(receipt.amount, receipt.currencyCode)}
                                        {receipt.referenceNumber
                                            ? ` · Ref. ${receipt.referenceNumber}`
                                            : ''}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        Enviado {formatDateTime(receipt.submittedAt)}
                                    </p>
                                    {receipt.rejectionReason && (
                                        <p className="mt-1 text-xs text-destructive">
                                            {receipt.rejectionReason}
                                        </p>
                                    )}
                                </div>
                                <span
                                    className={`rounded-full border px-2.5 py-0.5 text-xs font-semibold ${
                                        statusStyles[receipt.status] ??
                                        'border-border bg-secondary/60'
                                    }`}
                                >
                                    {receipt.status === 'pending'
                                        ? 'En revisión'
                                        : receipt.status === 'verified'
                                          ? 'Verificado'
                                          : 'Rechazado'}
                                </span>
                            </li>
                        ))}
                    </ul>
                )}

                {registration.paymentStatus === 'paid' ? (
                    <div className="mt-6 flex items-start gap-3 rounded-xl border border-primary/25 bg-primary/10 px-4 py-4 text-sm text-primary">
                        <CheckCircle2 className="mt-0.5 size-5 shrink-0" />
                        <div className="space-y-3">
                            <p>Tu pago fue verificado. ¡Nos vemos en el evento!</p>
                            {ticketUrl && (
                                <Link
                                    href={ticketUrl}
                                    className="inline-flex h-9 items-center rounded-lg bg-primary px-3 text-sm font-semibold text-primary-foreground"
                                >
                                    <Ticket className="mr-1.5 size-4" />
                                    Ver mi entrada
                                </Link>
                            )}
                        </div>
                    </div>
                ) : canUpload ? (
                    <form
                        className="mt-6 space-y-4 rounded-xl border border-border bg-card p-5"
                        onSubmit={submit}
                        noValidate
                    >
                        <div>
                            <h2 className="font-display text-xl font-semibold">
                                Subir comprobante
                            </h2>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Adjunta una foto o PDF del pago. La iglesia lo revisará y
                                confirmará tu inscripción.
                            </p>
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="amount">Monto pagado</Label>
                            <Input
                                id="amount"
                                inputMode="decimal"
                                value={form.data.amount}
                                aria-invalid={errors.amount ? true : undefined}
                                onChange={(changeEvent) =>
                                    form.setData('amount', changeEvent.target.value)
                                }
                            />
                            {errors.amount && (
                                <p className="text-sm text-destructive">{errors.amount}</p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="reference_number">Número de referencia</Label>
                            <Input
                                id="reference_number"
                                value={form.data.reference_number}
                                onChange={(changeEvent) =>
                                    form.setData('reference_number', changeEvent.target.value)
                                }
                            />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="file">Archivo</Label>
                            <Input
                                id="file"
                                type="file"
                                accept="image/jpeg,image/png,image/webp,application/pdf"
                                onChange={selectFile}
                            />
                            {errors.file && (
                                <p className="text-sm text-destructive">{errors.file}</p>
                            )}
                        </div>

                        <input
                            type="text"
                            name="website"
                            tabIndex={-1}
                            autoComplete="off"
                            className="hidden"
                            value={form.data.website}
                            onChange={(changeEvent) =>
                                form.setData('website', changeEvent.target.value)
                            }
                        />

                        <Button type="submit" disabled={form.processing}>
                            <FileUp />
                            {form.processing ? 'Enviando…' : 'Enviar comprobante'}
                        </Button>
                    </form>
                ) : (
                    <div className="mt-6 flex items-start gap-3 rounded-xl border border-destructive/25 bg-destructive/10 px-4 py-4 text-sm text-destructive">
                        <XCircle className="mt-0.5 size-5 shrink-0" />
                        <p>
                            El comprobante fue rechazado. Contacta a la iglesia para más
                            información.
                        </p>
                    </div>
                )}
            </div>
        </PublicLayout>
    );
}

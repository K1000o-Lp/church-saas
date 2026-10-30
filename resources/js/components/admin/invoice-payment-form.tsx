import { useForm } from '@inertiajs/react';
import { Banknote } from 'lucide-react';
import type { FormEvent } from 'react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { dateInputValue, formatMoney } from '@/lib/format';
import type { PaymentMethodOption, PaymentMethodValue } from '@/types';

type Props = {
    invoiceId: number;
    remaining: string;
    currencyCode: string;
    methods: PaymentMethodOption[];
    canEdit: boolean;
};

export function InvoicePaymentForm({
    invoiceId,
    remaining,
    currencyCode,
    methods,
    canEdit,
}: Props) {
    const form = useForm({
        amount: remaining,
        method: methods[0]?.value ?? 'transfer',
        reference: '',
        notes: '',
        paid_at: dateInputValue(),
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post(`/admin/invoices/${invoiceId}/payments`, {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    }

    return (
        <form
            className="space-y-3 rounded-lg border border-border bg-background/60 p-4"
            onSubmit={submit}
            noValidate
        >
            <div className="flex items-center gap-2">
                <Banknote className="size-4 text-muted-foreground" />
                <h3 className="text-sm font-semibold">Registrar pago</h3>
                <span className="text-xs text-muted-foreground">
                    Saldo {formatMoney(remaining, currencyCode)}
                </span>
            </div>

            <div className="grid gap-3 sm:grid-cols-2">
                <div className="space-y-2">
                    <Label htmlFor={`payment-amount-${invoiceId}`}>Monto (USD)</Label>
                    <Input
                        id={`payment-amount-${invoiceId}`}
                        inputMode="decimal"
                        value={form.data.amount}
                        required
                        aria-invalid={form.errors.amount ? true : undefined}
                        onChange={(event) => form.setData('amount', event.target.value)}
                    />
                    {form.errors.amount && (
                        <p className="text-sm text-destructive">{form.errors.amount}</p>
                    )}
                </div>

                <div className="space-y-2">
                    <Label htmlFor={`payment-method-${invoiceId}`}>Método</Label>
                    <Select
                        id={`payment-method-${invoiceId}`}
                        value={form.data.method}
                        aria-invalid={form.errors.method ? true : undefined}
                        onChange={(event) =>
                            form.setData('method', event.target.value as PaymentMethodValue)
                        }
                    >
                        {methods.map((method) => (
                            <option key={method.value} value={method.value}>
                                {method.label}
                            </option>
                        ))}
                    </Select>
                    {form.errors.method && (
                        <p className="text-sm text-destructive">{form.errors.method}</p>
                    )}
                </div>

                <div className="space-y-2">
                    <Label htmlFor={`payment-reference-${invoiceId}`}>Referencia</Label>
                    <Input
                        id={`payment-reference-${invoiceId}`}
                        placeholder="Nº de transferencia, Zelle, etc."
                        value={form.data.reference}
                        aria-invalid={form.errors.reference ? true : undefined}
                        onChange={(event) => form.setData('reference', event.target.value)}
                    />
                    {form.errors.reference && (
                        <p className="text-sm text-destructive">{form.errors.reference}</p>
                    )}
                </div>

                <div className="space-y-2">
                    <Label htmlFor={`payment-date-${invoiceId}`}>Fecha del pago</Label>
                    <Input
                        id={`payment-date-${invoiceId}`}
                        type="date"
                        value={form.data.paid_at}
                        aria-invalid={form.errors.paid_at ? true : undefined}
                        onChange={(event) => form.setData('paid_at', event.target.value)}
                    />
                    {form.errors.paid_at && (
                        <p className="text-sm text-destructive">{form.errors.paid_at}</p>
                    )}
                </div>
            </div>

            <div className="space-y-2">
                <Label htmlFor={`payment-notes-${invoiceId}`}>Notas</Label>
                <Textarea
                    id={`payment-notes-${invoiceId}`}
                    className="min-h-16"
                    value={form.data.notes}
                    aria-invalid={form.errors.notes ? true : undefined}
                    onChange={(event) => form.setData('notes', event.target.value)}
                />
                {form.errors.notes && (
                    <p className="text-sm text-destructive">{form.errors.notes}</p>
                )}
            </div>

            <Button
                type="submit"
                size="sm"
                variant="outline"
                disabled={!canEdit || form.processing}
            >
                {form.processing ? 'Registrando…' : 'Registrar pago'}
            </Button>
        </form>
    );
}

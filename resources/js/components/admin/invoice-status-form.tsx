import { useForm } from '@inertiajs/react';
import { ArrowRightLeft } from 'lucide-react';
import type { FormEvent } from 'react';

import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Select } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import type { InvoiceStatusOption, InvoiceStatusValue } from '@/types';

type Props = {
    invoiceId: number;
    status: InvoiceStatusValue;
    statuses: InvoiceStatusOption[];
    canEdit: boolean;
};

const allowedTransitions: Record<InvoiceStatusValue, InvoiceStatusValue[]> = {
    issued: ['paid', 'overdue', 'void'],
    overdue: ['paid', 'void'],
    paid: [],
    void: [],
};

export function InvoiceStatusForm({ invoiceId, status, statuses, canEdit }: Props) {
    const allowed = allowedTransitions[status];
    const form = useForm({
        status: allowed[0] ?? status,
        reason: '',
    });

    if (allowed.length === 0) {
        return null;
    }

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.put(`/admin/invoices/${invoiceId}/status`, {
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
                <ArrowRightLeft className="size-4 text-muted-foreground" />
                <h3 className="text-sm font-semibold">Cambiar estado</h3>
            </div>

            <div className="grid gap-3 sm:grid-cols-2">
                <div className="space-y-2">
                    <Label htmlFor={`invoice-status-${invoiceId}`}>Nuevo estado</Label>
                    <Select
                        id={`invoice-status-${invoiceId}`}
                        value={form.data.status}
                        aria-invalid={form.errors.status ? true : undefined}
                        onChange={(event) =>
                            form.setData('status', event.target.value as InvoiceStatusValue)
                        }
                    >
                        {allowed.map((value) => (
                            <option key={value} value={value}>
                                {statuses.find((option) => option.value === value)?.label ?? value}
                            </option>
                        ))}
                    </Select>
                    {form.errors.status && (
                        <p className="text-sm text-destructive">{form.errors.status}</p>
                    )}
                </div>
            </div>

            {form.data.status === 'void' && (
                <div className="space-y-2">
                    <Label htmlFor={`invoice-void-reason-${invoiceId}`}>Motivo de anulación</Label>
                    <Textarea
                        id={`invoice-void-reason-${invoiceId}`}
                        className="min-h-16"
                        value={form.data.reason}
                        aria-invalid={form.errors.reason ? true : undefined}
                        onChange={(event) => form.setData('reason', event.target.value)}
                    />
                    {form.errors.reason && (
                        <p className="text-sm text-destructive">{form.errors.reason}</p>
                    )}
                </div>
            )}

            <Button
                type="submit"
                size="sm"
                variant="outline"
                disabled={!canEdit || form.processing}
            >
                {form.processing ? 'Actualizando…' : 'Actualizar estado'}
            </Button>
        </form>
    );
}

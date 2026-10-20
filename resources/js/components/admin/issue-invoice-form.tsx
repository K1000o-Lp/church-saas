import { useForm } from '@inertiajs/react';
import { Receipt } from 'lucide-react';
import type { FormEvent } from 'react';

import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { dateInputValue } from '@/lib/format';

type Props = {
    churchSlug: string;
    canEdit: boolean;
};

export function IssueInvoiceForm({ churchSlug, canEdit }: Props) {
    const form = useForm({
        amount: '',
        description: '',
        due_at: dateInputValue(30),
        notes: '',
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post(`/admin/churches/${churchSlug}/invoices`, {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    }

    return (
        <Card>
            <CardHeader>
                <div className="flex items-center gap-2">
                    <Receipt className="size-4 text-primary" />
                    <CardTitle>Emitir factura</CardTitle>
                </div>
                <CardDescription>
                    Montos en USD, en unidad menor en el servidor. Sin impuestos ni PDF en esta
                    fase.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <form className="space-y-4" onSubmit={submit} noValidate>
                    <div className="space-y-2">
                        <Label htmlFor="invoice-amount">Monto (USD)</Label>
                        <Input
                            id="invoice-amount"
                            inputMode="decimal"
                            placeholder="150.00"
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
                        <Label htmlFor="invoice-description">Concepto</Label>
                        <Input
                            id="invoice-description"
                            placeholder="Suscripción mensual"
                            value={form.data.description}
                            aria-invalid={form.errors.description ? true : undefined}
                            onChange={(event) => form.setData('description', event.target.value)}
                        />
                        {form.errors.description && (
                            <p className="text-sm text-destructive">{form.errors.description}</p>
                        )}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="invoice-due-at">Vencimiento</Label>
                        <Input
                            id="invoice-due-at"
                            type="date"
                            value={form.data.due_at}
                            aria-invalid={form.errors.due_at ? true : undefined}
                            onChange={(event) => form.setData('due_at', event.target.value)}
                        />
                        {form.errors.due_at && (
                            <p className="text-sm text-destructive">{form.errors.due_at}</p>
                        )}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="invoice-notes">Notas</Label>
                        <Textarea
                            id="invoice-notes"
                            placeholder="Detalle interno o referencia para la iglesia."
                            value={form.data.notes}
                            aria-invalid={form.errors.notes ? true : undefined}
                            onChange={(event) => form.setData('notes', event.target.value)}
                        />
                        {form.errors.notes && (
                            <p className="text-sm text-destructive">{form.errors.notes}</p>
                        )}
                    </div>

                    <Button type="submit" className="w-full" disabled={!canEdit || form.processing}>
                        {form.processing ? 'Emitiendo…' : 'Emitir factura'}
                    </Button>
                </form>
            </CardContent>
        </Card>
    );
}

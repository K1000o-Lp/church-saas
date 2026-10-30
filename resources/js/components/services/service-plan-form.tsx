import { useForm } from '@inertiajs/react';
import { Save } from 'lucide-react';
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
import { Select } from '@/components/ui/select';
import { dateTimeInputValue } from '@/lib/format';
import type {
    ServicePlanDetail,
    ServiceStatusOption,
    ServiceStatusValue,
} from '@/types';

type Props = {
    action: string;
    method: 'post' | 'put';
    statuses: ServiceStatusOption[];
    plan?: ServicePlanDetail;
    submitLabel: string;
};

export function ServicePlanForm({ action, method, statuses, plan, submitLabel }: Props) {
    const form = useForm({
        title: plan?.title ?? '',
        starts_at: dateTimeInputValue(plan?.startsAt ?? null),
        ends_at: dateTimeInputValue(plan?.endsAt ?? null),
        location: plan?.location ?? '',
        status: plan?.status ?? 'draft',
    });

    const errors = form.errors as Record<string, string>;

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (method === 'put') {
            form.put(action);
        } else {
            form.post(action);
        }
    }

    return (
        <form className="space-y-6" onSubmit={submit} noValidate>
            <Card>
                <CardHeader>
                    <CardTitle>Datos del culto</CardTitle>
                    <CardDescription>
                        Programa el servicio y prepara su orden con equipos y voluntarios.
                    </CardDescription>
                </CardHeader>
                <CardContent className="grid gap-5 sm:grid-cols-2">
                    <div className="space-y-2 sm:col-span-2">
                        <Label htmlFor="title">Título</Label>
                        <Input
                            id="title"
                            value={form.data.title}
                            aria-invalid={errors.title ? true : undefined}
                            onChange={(event) => form.setData('title', event.target.value)}
                        />
                        {errors.title && (
                            <p className="text-sm text-destructive">{errors.title}</p>
                        )}
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="starts_at">Inicio</Label>
                        <Input
                            id="starts_at"
                            type="datetime-local"
                            value={form.data.starts_at}
                            onChange={(event) =>
                                form.setData('starts_at', event.target.value)
                            }
                        />
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="ends_at">Fin</Label>
                        <Input
                            id="ends_at"
                            type="datetime-local"
                            value={form.data.ends_at}
                            onChange={(event) => form.setData('ends_at', event.target.value)}
                        />
                        {errors.ends_at && (
                            <p className="text-sm text-destructive">{errors.ends_at}</p>
                        )}
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="location">Lugar</Label>
                        <Input
                            id="location"
                            value={form.data.location}
                            onChange={(event) => form.setData('location', event.target.value)}
                        />
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="status">Estado</Label>
                        <Select
                            id="status"
                            value={form.data.status}
                            onChange={(event) =>
                                form.setData(
                                    'status',
                                    event.target.value as ServiceStatusValue,
                                )
                            }
                        >
                            {statuses.map((status) => (
                                <option key={status.value} value={status.value}>
                                    {status.label}
                                </option>
                            ))}
                        </Select>
                    </div>
                </CardContent>
            </Card>

            <Button type="submit" disabled={form.processing}>
                <Save />
                {form.processing ? 'Guardando…' : submitLabel}
            </Button>
        </form>
    );
}

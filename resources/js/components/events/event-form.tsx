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
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { dateTimeInputValue } from '@/lib/format';
import type {
    EventCurrencyOption,
    EventDetail,
    EventStatusOption,
    EventStatusValue,
} from '@/types';

type Props = {
    action: string;
    method: 'post' | 'put';
    statuses: EventStatusOption[];
    currencies: EventCurrencyOption[];
    event?: EventDetail;
    submitLabel: string;
};

export function EventForm({
    action,
    method,
    statuses,
    currencies,
    event,
    submitLabel,
}: Props) {
    const form = useForm({
        title: event?.title ?? '',
        description: event?.description ?? '',
        location: event?.location ?? '',
        starts_at: dateTimeInputValue(event?.startsAt ?? null),
        ends_at: dateTimeInputValue(event?.endsAt ?? null),
        timezone: event?.timezone ?? '',
        status: event?.status ?? 'draft',
        capacity: event?.capacity !== null && event?.capacity !== undefined ? String(event.capacity) : '',
        is_public: event?.isPublic ?? false,
        is_paid: event?.isPaid ?? false,
        price: event?.price ?? '',
        currency_code: event?.currencyCode ?? currencies[0]?.code ?? '',
        registration_opens_at: dateTimeInputValue(event?.registrationOpensAt ?? null),
        registration_closes_at: dateTimeInputValue(event?.registrationClosesAt ?? null),
    });

    const errors = form.errors as Record<string, string>;

    function submit(submitEvent: FormEvent<HTMLFormElement>) {
        submitEvent.preventDefault();

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
                    <CardTitle>Datos del evento</CardTitle>
                    <CardDescription>
                        Información visible para el equipo y, si lo publicas, en el portal.
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

                    <div className="space-y-2 sm:col-span-2">
                        <Label htmlFor="description">Descripción</Label>
                        <Textarea
                            id="description"
                            className="min-h-24"
                            value={form.data.description}
                            onChange={(event) =>
                                form.setData('description', event.target.value)
                            }
                        />
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
                        <Label htmlFor="timezone">Zona horaria</Label>
                        <Input
                            id="timezone"
                            placeholder="America/Caracas"
                            value={form.data.timezone}
                            onChange={(event) => form.setData('timezone', event.target.value)}
                        />
                        {errors.timezone && (
                            <p className="text-sm text-destructive">{errors.timezone}</p>
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
                        {errors.starts_at && (
                            <p className="text-sm text-destructive">{errors.starts_at}</p>
                        )}
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
                        <Label htmlFor="status">Estado</Label>
                        <Select
                            id="status"
                            value={form.data.status}
                            onChange={(event) =>
                                form.setData(
                                    'status',
                                    event.target.value as EventStatusValue,
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

                    <div className="space-y-2">
                        <Label htmlFor="capacity">Cupo (opcional)</Label>
                        <Input
                            id="capacity"
                            type="number"
                            min={1}
                            value={form.data.capacity}
                            onChange={(event) => form.setData('capacity', event.target.value)}
                        />
                        {errors.capacity && (
                            <p className="text-sm text-destructive">{errors.capacity}</p>
                        )}
                    </div>

                    <div className="flex items-center gap-3 sm:col-span-2">
                        <Checkbox
                            id="is_public"
                            checked={form.data.is_public}
                            onCheckedChange={(checked) =>
                                form.setData('is_public', checked === true)
                            }
                        />
                        <Label htmlFor="is_public">Visible en el portal público</Label>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Inscripciones y pago</CardTitle>
                    <CardDescription>
                        Define si hay cupo, ventana de registro y cobro por inscripción.
                    </CardDescription>
                </CardHeader>
                <CardContent className="grid gap-5 sm:grid-cols-2">
                    <div className="space-y-2">
                        <Label htmlFor="registration_opens_at">Apertura de registro</Label>
                        <Input
                            id="registration_opens_at"
                            type="datetime-local"
                            value={form.data.registration_opens_at}
                            onChange={(event) =>
                                form.setData('registration_opens_at', event.target.value)
                            }
                        />
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="registration_closes_at">Cierre de registro</Label>
                        <Input
                            id="registration_closes_at"
                            type="datetime-local"
                            value={form.data.registration_closes_at}
                            onChange={(event) =>
                                form.setData('registration_closes_at', event.target.value)
                            }
                        />
                        {errors.registration_closes_at && (
                            <p className="text-sm text-destructive">
                                {errors.registration_closes_at}
                            </p>
                        )}
                    </div>

                    <div className="flex items-center gap-3 sm:col-span-2">
                        <Checkbox
                            id="is_paid"
                            checked={form.data.is_paid}
                            onCheckedChange={(checked) =>
                                form.setData('is_paid', checked === true)
                            }
                        />
                        <Label htmlFor="is_paid">Evento con costo</Label>
                    </div>

                    {form.data.is_paid && (
                        <>
                            <div className="space-y-2">
                                <Label htmlFor="price">Precio</Label>
                                <Input
                                    id="price"
                                    inputMode="decimal"
                                    value={form.data.price}
                                    aria-invalid={errors.price ? true : undefined}
                                    onChange={(event) =>
                                        form.setData('price', event.target.value)
                                    }
                                />
                                {errors.price && (
                                    <p className="text-sm text-destructive">{errors.price}</p>
                                )}
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="currency_code">Moneda</Label>
                                <Select
                                    id="currency_code"
                                    value={form.data.currency_code}
                                    onChange={(event) =>
                                        form.setData('currency_code', event.target.value)
                                    }
                                >
                                    {currencies.map((currency) => (
                                        <option key={currency.code} value={currency.code}>
                                            {currency.code}
                                        </option>
                                    ))}
                                </Select>
                                {errors.currency_code && (
                                    <p className="text-sm text-destructive">
                                        {errors.currency_code}
                                    </p>
                                )}
                            </div>
                        </>
                    )}
                </CardContent>
            </Card>

            <Button type="submit" disabled={form.processing}>
                <Save />
                {form.processing ? 'Guardando…' : submitLabel}
            </Button>
        </form>
    );
}

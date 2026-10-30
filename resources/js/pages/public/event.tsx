import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, CalendarDays, MapPin, Users } from 'lucide-react';
import type { FormEvent } from 'react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { PublicLayout } from '@/layouts/public-layout';
import { formatDateTime, formatMoney } from '@/lib/format';
import type { PublicChurchBranding, PublicEventDetail } from '@/types';

type Props = {
    church: PublicChurchBranding;
    event: PublicEventDetail;
};

export default function PublicEvent({ church, event }: Props) {
    const form = useForm({
        first_name: '',
        last_name: '',
        email: '',
        phone: '',
        guest_count: '0',
        website: '',
    });

    const errors = form.errors as Record<string, string>;

    function submit(submitEvent: FormEvent<HTMLFormElement>) {
        submitEvent.preventDefault();
        form.post(`/iglesias/${church.slug}/eventos/${event.slug}/registro`, {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    }

    return (
        <PublicLayout churchName={church.name} logoUrl={church.logoUrl}>
            <Head title={`${event.title} · ${church.name}`} />

            <Button variant="ghost" size="sm" asChild>
                <Link href={`/iglesias/${church.slug}`}>
                    <ArrowLeft />
                    Volver a {church.name}
                </Link>
            </Button>

            <div className="mt-5 grid gap-8 lg:grid-cols-[1.4fr_1fr]">
                <div>
                    <p className="eyebrow">Evento</p>
                    <h1 className="mt-3 font-display text-4xl font-semibold">
                        {event.title}
                    </h1>
                    {event.description && (
                        <p className="mt-4 text-base leading-relaxed whitespace-pre-line text-muted-foreground">
                            {event.description}
                        </p>
                    )}

                    <dl className="mt-6 space-y-3 text-sm">
                        <div className="flex items-center gap-3">
                            <CalendarDays className="size-4 text-muted-foreground" />
                            <div>
                                <dt className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                    Fecha
                                </dt>
                                <dd>{formatDateTime(event.startsAt)}</dd>
                            </div>
                        </div>
                        {event.location && (
                            <div className="flex items-center gap-3">
                                <MapPin className="size-4 text-muted-foreground" />
                                <div>
                                    <dt className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                        Lugar
                                    </dt>
                                    <dd>{event.location}</dd>
                                </div>
                            </div>
                        )}
                        <div className="flex items-center gap-3">
                            <Users className="size-4 text-muted-foreground" />
                            <div>
                                <dt className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                    Cupo
                                </dt>
                                <dd>
                                    {event.capacity === null
                                        ? 'Sin límite de cupo'
                                        : event.isFull
                                          ? 'Cupo lleno (lista de espera)'
                                          : `${event.spotsLeft} cupos disponibles`}
                                </dd>
                            </div>
                        </div>
                    </dl>

                    {event.isPaid && event.price !== null && (
                        <p className="mt-6 inline-flex rounded-full border border-accent/40 bg-accent/10 px-4 py-2 text-sm font-semibold text-accent">
                            Costo: {formatMoney(event.price, event.currencyCode ?? 'USD')}
                        </p>
                    )}
                </div>

                <div className="rounded-xl border border-border bg-card p-5">
                    <h2 className="font-display text-xl font-semibold">Inscríbete</h2>

                    {!event.registrationIsOpen ? (
                        <p className="mt-3 text-sm text-muted-foreground">
                            El registro para este evento está cerrado.
                        </p>
                    ) : (
                        <form className="mt-4 space-y-4" onSubmit={submit} noValidate>
                            <div className="space-y-2">
                                <Label htmlFor="first_name">Nombre</Label>
                                <Input
                                    id="first_name"
                                    value={form.data.first_name}
                                    aria-invalid={errors.first_name ? true : undefined}
                                    onChange={(changeEvent) =>
                                        form.setData('first_name', changeEvent.target.value)
                                    }
                                />
                                {errors.first_name && (
                                    <p className="text-sm text-destructive">
                                        {errors.first_name}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="last_name">Apellido</Label>
                                <Input
                                    id="last_name"
                                    value={form.data.last_name}
                                    onChange={(changeEvent) =>
                                        form.setData('last_name', changeEvent.target.value)
                                    }
                                />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="email">Correo</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    value={form.data.email}
                                    aria-invalid={errors.email ? true : undefined}
                                    onChange={(changeEvent) =>
                                        form.setData('email', changeEvent.target.value)
                                    }
                                />
                                {errors.email && (
                                    <p className="text-sm text-destructive">{errors.email}</p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="phone">Teléfono</Label>
                                <Input
                                    id="phone"
                                    value={form.data.phone}
                                    onChange={(changeEvent) =>
                                        form.setData('phone', changeEvent.target.value)
                                    }
                                />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="guest_count">Acompañantes</Label>
                                <Input
                                    id="guest_count"
                                    type="number"
                                    min={0}
                                    max={20}
                                    value={form.data.guest_count}
                                    onChange={(changeEvent) =>
                                        form.setData('guest_count', changeEvent.target.value)
                                    }
                                />
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

                            <Button type="submit" className="w-full" disabled={form.processing}>
                                {form.processing ? 'Enviando…' : 'Registrarme'}
                            </Button>
                        </form>
                    )}
                </div>
            </div>
        </PublicLayout>
    );
}

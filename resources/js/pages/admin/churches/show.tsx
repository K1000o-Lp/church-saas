import { Head, Link, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    CalendarDays,
    Coins,
    HeartHandshake,
    History,
    MapPin,
    ShieldAlert,
    UserCheck,
    Users,
    Workflow,
} from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';

import { ChurchStatusBadge } from '@/components/admin/church-status-badge';
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
import { AdminLayout } from '@/layouts/admin-layout';
import type {
    AdminAuditEntry,
    AdminChurchDetail,
    ChurchCurrencySummary,
    CountryOption,
    CurrencyOption,
    LocaleOption,
    StatusOption,
    ChurchStatusValue,
} from '@/types';

type Props = {
    church: AdminChurchDetail;
    currencies: ChurchCurrencySummary[];
    auditEntries: AdminAuditEntry[];
    canEdit: boolean;
    options: {
        countries: CountryOption[];
        currencies: CurrencyOption[];
        locales: LocaleOption[];
        statuses: StatusOption[];
    };
};

type AuditMetadata = {
    from?: string;
    to?: string;
    reason?: string | null;
    changes?: Record<string, unknown>;
};

function formatDateTime(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Intl.DateTimeFormat('es', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Intl.DateTimeFormat('es', { dateStyle: 'medium' }).format(new Date(value));
}

function Metric({
    icon: Icon,
    label,
    value,
}: {
    icon: typeof Users;
    label: string;
    value: number;
}) {
    return (
        <div className="rounded-xl border border-border bg-card px-4 py-3">
            <div className="flex items-center gap-2 text-muted-foreground">
                <Icon className="size-4" />
                <span className="text-xs font-semibold tracking-wide uppercase">{label}</span>
            </div>
            <p className="mt-2 font-display text-2xl font-semibold tabular-nums">{value}</p>
        </div>
    );
}

function Meta({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div>
            <dt className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                {label}
            </dt>
            <dd className="mt-1 text-sm">{children}</dd>
        </div>
    );
}

export default function AdminChurchShow({
    church,
    currencies,
    auditEntries,
    canEdit,
    options,
}: Props) {
    const defaultCurrency =
        currencies.find((currency) => currency.isDefault)?.code ?? church.currencyCode ?? 'USD';
    const additionalCurrencies = currencies
        .filter((currency) => !currency.isDefault)
        .map((currency) => currency.code);

    const form = useForm({
        name: church.name,
        country_code: church.countryCode ?? 'VE',
        timezone: church.timezone ?? 'America/Caracas',
        locale: church.locale,
        currency_code: defaultCurrency,
        additional_currencies: additionalCurrencies,
    });

    const statusForm = useForm({
        status: church.status,
        reason: church.statusReason ?? '',
    });

    const additionalCurrenciesError =
        form.errors.additional_currencies ?? form.errors['additional_currencies.0'];

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.put(`/admin/churches/${church.slug}`);
    }

    function submitStatus(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        statusForm.put(`/admin/churches/${church.slug}/status`);
    }

    function handleCountryChange(value: string) {
        const country = options.countries.find((option) => option.code === value);

        if (!country) {
            return;
        }

        form.setData({
            ...form.data,
            country_code: country.code,
            timezone: country.timezone,
        });
    }

    function handlePrimaryCurrencyChange(value: string) {
        form.setData({
            ...form.data,
            currency_code: value,
            additional_currencies: form.data.additional_currencies.filter(
                (currency) => currency !== value,
            ),
        });
    }

    function toggleAdditionalCurrency(code: string, checked: boolean) {
        form.setData(
            'additional_currencies',
            checked
                ? [...form.data.additional_currencies, code]
                : form.data.additional_currencies.filter((currency) => currency !== code),
        );
    }

    function statusLabel(value: string | undefined): string {
        if (!value) {
            return '—';
        }

        return options.statuses.find((status) => status.value === value)?.label ?? value;
    }

    function auditDetail(entry: AdminAuditEntry): string | null {
        const metadata = entry.metadata as AuditMetadata;

        if (metadata.from && metadata.to) {
            const reason = metadata.reason ? ` · ${metadata.reason}` : '';

            return `${statusLabel(metadata.from)} → ${statusLabel(metadata.to)}${reason}`;
        }

        if (metadata.changes && Object.keys(metadata.changes).length > 0) {
            return `Campos: ${Object.keys(metadata.changes).join(', ')}`;
        }

        return null;
    }

    const actionLabels: Record<string, string> = {
        'church.updated': 'Datos actualizados',
        'church.status_changed': 'Estado cambiado',
        'church.subscription_updated': 'Suscripción actualizada',
        'church.limit_overrides_updated': 'Límites actualizados',
        'church.feature_overrides_updated': 'Funciones actualizadas',
        'church.purged': 'Iglesia purgada',
        'invoice.issued': 'Factura emitida',
        'invoice.status_changed': 'Estado de factura cambiado',
        'payment.recorded': 'Pago registrado',
    };

    return (
        <AdminLayout>
            <Head title={`${church.name} · Administración`} />

            <Button variant="ghost" size="sm" asChild>
                <Link href="/admin/churches">
                    <ArrowLeft />
                    Iglesias
                </Link>
            </Button>

            <div className="mt-5 flex flex-wrap items-start justify-between gap-4">
                <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-3">
                        <h1 className="font-display text-3xl font-semibold tracking-tight sm:text-4xl">
                            {church.name}
                        </h1>
                        <ChurchStatusBadge status={church.status} label={church.statusLabel} />
                    </div>
                    <p className="mt-1 text-sm text-muted-foreground">/{church.slug}</p>
                </div>
                <div className="flex items-center gap-3">
                    <Button variant="outline" size="sm" asChild>
                        <Link href={`/admin/churches/${church.slug}/invoices`}>
                            Facturación
                        </Link>
                    </Button>
                    <Button variant="outline" size="sm" asChild>
                        <Link href={`/admin/churches/${church.slug}/plan`}>
                            Plan y límites
                        </Link>
                    </Button>
                    <p className="text-sm text-muted-foreground">
                        Creada {formatDate(church.createdAt)}
                    </p>
                </div>
            </div>

            {!canEdit && (
                <div className="mt-5 flex items-start gap-3 rounded-xl border border-accent/30 bg-accent/10 px-4 py-3 text-sm">
                    <ShieldAlert className="mt-0.5 size-4 shrink-0 text-accent" />
                    <p>
                        Esta iglesia está archivada. Los datos son de solo lectura. Para editarla,
                        cambia el estado a <strong>Activa</strong>, <strong>Suspendida</strong> o{' '}
                        <strong>Inactiva</strong>.
                    </p>
                </div>
            )}

            <section className="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <Metric icon={Users} label="Personas" value={church.metrics.people} />
                <Metric
                    icon={UserCheck}
                    label="Miembros activos"
                    value={church.metrics.activeMemberships}
                />
                <Metric icon={CalendarDays} label="Eventos" value={church.metrics.events} />
                <Metric
                    icon={HeartHandshake}
                    label="Planes de servicio"
                    value={church.metrics.servicePlans}
                />
                <Metric icon={Workflow} label="Workflows" value={church.metrics.workflows} />
            </section>

            <div className="mt-6 grid gap-6 lg:grid-cols-[1.55fr_1fr]">
                <div className="space-y-6">
                    {canEdit ? (
                        <Card>
                            <CardHeader>
                                <CardTitle>Datos de la iglesia</CardTitle>
                                <CardDescription>
                                    La dirección pública no se puede cambiar desde el backoffice.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <form className="space-y-5" onSubmit={submit} noValidate>
                                    <div className="grid gap-5 sm:grid-cols-2">
                                        <div className="space-y-2">
                                            <Label htmlFor="name">Nombre</Label>
                                            <Input
                                                id="name"
                                                value={form.data.name}
                                                required
                                                aria-invalid={
                                                    form.errors.name ? true : undefined
                                                }
                                                onChange={(event) =>
                                                    form.setData('name', event.target.value)
                                                }
                                            />
                                            {form.errors.name && (
                                                <p className="text-sm text-destructive">
                                                    {form.errors.name}
                                                </p>
                                            )}
                                        </div>

                                        <div className="space-y-2">
                                            <Label htmlFor="slug">Dirección</Label>
                                            <Input id="slug" value={`/churches/${church.slug}`} readOnly disabled />
                                        </div>

                                        <div className="space-y-2">
                                            <Label htmlFor="country">País</Label>
                                            <Select
                                                id="country"
                                                value={form.data.country_code}
                                                aria-invalid={
                                                    form.errors.country_code ? true : undefined
                                                }
                                                onChange={(event) =>
                                                    handleCountryChange(event.target.value)
                                                }
                                            >
                                                {options.countries.map((country) => (
                                                    <option key={country.code} value={country.code}>
                                                        {country.name}
                                                    </option>
                                                ))}
                                            </Select>
                                            {form.errors.country_code && (
                                                <p className="text-sm text-destructive">
                                                    {form.errors.country_code}
                                                </p>
                                            )}
                                        </div>

                                        <div className="space-y-2">
                                            <Label htmlFor="timezone">Zona horaria</Label>
                                            <Input
                                                id="timezone"
                                                value={form.data.timezone}
                                                required
                                                aria-invalid={
                                                    form.errors.timezone ? true : undefined
                                                }
                                                onChange={(event) =>
                                                    form.setData('timezone', event.target.value)
                                                }
                                            />
                                            {form.errors.timezone && (
                                                <p className="text-sm text-destructive">
                                                    {form.errors.timezone}
                                                </p>
                                            )}
                                        </div>

                                        <div className="space-y-2">
                                            <Label htmlFor="locale">Locale</Label>
                                            <Select
                                                id="locale"
                                                value={form.data.locale}
                                                aria-invalid={
                                                    form.errors.locale ? true : undefined
                                                }
                                                onChange={(event) =>
                                                    form.setData('locale', event.target.value)
                                                }
                                            >
                                                {options.locales.map((locale) => (
                                                    <option key={locale.code} value={locale.code}>
                                                        {locale.name}
                                                    </option>
                                                ))}
                                            </Select>
                                            {form.errors.locale && (
                                                <p className="text-sm text-destructive">
                                                    {form.errors.locale}
                                                </p>
                                            )}
                                        </div>

                                        <div className="space-y-2">
                                            <Label htmlFor="currency">Moneda principal</Label>
                                            <Select
                                                id="currency"
                                                value={form.data.currency_code}
                                                aria-invalid={
                                                    form.errors.currency_code ? true : undefined
                                                }
                                                onChange={(event) =>
                                                    handlePrimaryCurrencyChange(event.target.value)
                                                }
                                            >
                                                {options.currencies.map((currency) => (
                                                    <option key={currency.code} value={currency.code}>
                                                        {currency.code} · {currency.name}
                                                    </option>
                                                ))}
                                            </Select>
                                            {form.errors.currency_code && (
                                                <p className="text-sm text-destructive">
                                                    {form.errors.currency_code}
                                                </p>
                                            )}
                                        </div>
                                    </div>

                                    <fieldset className="space-y-2">
                                        <legend className="text-sm font-semibold">
                                            Monedas adicionales
                                        </legend>
                                        <p className="text-xs text-muted-foreground">
                                            No se puede quitar una moneda con contribuciones
                                            asociadas.
                                        </p>
                                        <div className="grid gap-2 sm:grid-cols-2">
                                            {options.currencies.map((currency) => {
                                                const isPrimary =
                                                    form.data.currency_code === currency.code;
                                                const checked =
                                                    form.data.additional_currencies.includes(
                                                        currency.code,
                                                    );

                                                return (
                                                    <div
                                                        key={currency.code}
                                                        className="flex items-center gap-2.5 rounded-lg border border-border bg-background/60 px-3 py-2.5"
                                                    >
                                                        <Checkbox
                                                            id={`currency-${currency.code}`}
                                                            checked={checked}
                                                            disabled={isPrimary}
                                                            onCheckedChange={(value) =>
                                                                toggleAdditionalCurrency(
                                                                    currency.code,
                                                                    value === true,
                                                                )
                                                            }
                                                        />
                                                        <Label
                                                            htmlFor={`currency-${currency.code}`}
                                                            className="font-normal"
                                                        >
                                                            <span className="font-semibold">
                                                                {currency.code}
                                                            </span>
                                                            <span className="text-muted-foreground">
                                                                {' '}
                                                                · {currency.name}
                                                            </span>
                                                        </Label>
                                                    </div>
                                                );
                                            })}
                                        </div>
                                        {additionalCurrenciesError && (
                                            <p className="text-sm text-destructive">
                                                {additionalCurrenciesError}
                                            </p>
                                        )}
                                    </fieldset>

                                    <div className="flex justify-end">
                                        <Button type="submit" disabled={form.processing}>
                                            {form.processing ? 'Guardando…' : 'Guardar cambios'}
                                        </Button>
                                    </div>
                                </form>
                            </CardContent>
                        </Card>
                    ) : (
                        <Card>
                            <CardHeader>
                                <CardTitle>Datos de la iglesia</CardTitle>
                                <CardDescription>
                                    Iglesia archivada: solo lectura hasta reactivarla.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <dl className="grid gap-4 sm:grid-cols-2">
                                    <Meta label="Nombre">{church.name}</Meta>
                                    <Meta label="Dirección">/{church.slug}</Meta>
                                    <Meta label="País">{church.countryCode ?? '—'}</Meta>
                                    <Meta label="Zona horaria">{church.timezone ?? '—'}</Meta>
                                    <Meta label="Locale">{church.locale}</Meta>
                                    <Meta label="Moneda principal">{church.currencyCode ?? '—'}</Meta>
                                </dl>
                            </CardContent>
                        </Card>
                    )}

                    <Card>
                        <CardHeader>
                            <CardTitle>Historial de auditoría</CardTitle>
                            <CardDescription>
                                Últimas 20 acciones de plataforma sobre esta iglesia.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {auditEntries.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    Todavía no hay acciones registradas.
                                </p>
                            ) : (
                                <ul className="divide-y divide-border">
                                    {auditEntries.map((entry) => {
                                        const detail = auditDetail(entry);

                                        return (
                                            <li key={entry.id} className="py-3 first:pt-0 last:pb-0">
                                                <div className="flex flex-wrap items-start justify-between gap-2">
                                                    <div className="flex items-start gap-2.5">
                                                        <History className="mt-0.5 size-4 text-muted-foreground" />
                                                        <div>
                                                            <p className="text-sm font-semibold">
                                                                {actionLabels[entry.action] ??
                                                                    entry.action}
                                                            </p>
                                                            <p className="text-xs text-muted-foreground">
                                                                {entry.actor ?? 'Sistema'}
                                                                {entry.actorEmail
                                                                    ? ` · ${entry.actorEmail}`
                                                                    : ''}
                                                            </p>
                                                            {detail && (
                                                                <p className="mt-1 text-xs text-muted-foreground">
                                                                    {detail}
                                                                </p>
                                                            )}
                                                        </div>
                                                    </div>
                                                    <time className="text-xs text-muted-foreground">
                                                        {formatDateTime(entry.createdAt)}
                                                    </time>
                                                </div>
                                            </li>
                                        );
                                    })}
                                </ul>
                            )}
                        </CardContent>
                    </Card>
                </div>

                <div className="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Estado y acceso</CardTitle>
                            <CardDescription>
                                Suspender o inactivar bloquea el acceso en la siguiente petición.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form className="space-y-4" onSubmit={submitStatus} noValidate>
                                <div className="space-y-2">
                                    <Label htmlFor="status">Estado</Label>
                                    <Select
                                        id="status"
                                        value={statusForm.data.status}
                                        aria-invalid={
                                            statusForm.errors.status ? true : undefined
                                        }
                                        onChange={(event) =>
                                            statusForm.setData(
                                                'status',
                                                event.target.value as ChurchStatusValue,
                                            )
                                        }
                                    >
                                        {options.statuses.map((status) => (
                                            <option key={status.value} value={status.value}>
                                                {status.label}
                                            </option>
                                        ))}
                                    </Select>
                                    {statusForm.errors.status && (
                                        <p className="text-sm text-destructive">
                                            {statusForm.errors.status}
                                        </p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="reason">Motivo</Label>
                                    <Textarea
                                        id="reason"
                                        value={statusForm.data.reason}
                                        placeholder="Obligatorio salvo para reactivar la iglesia."
                                        aria-invalid={
                                            statusForm.errors.reason ? true : undefined
                                        }
                                        onChange={(event) =>
                                            statusForm.setData('reason', event.target.value)
                                        }
                                    />
                                    {statusForm.errors.reason && (
                                        <p className="text-sm text-destructive">
                                            {statusForm.errors.reason}
                                        </p>
                                    )}
                                </div>

                                <Button
                                    type="submit"
                                    className="w-full"
                                    variant={church.status === 'active' ? 'outline' : 'default'}
                                    disabled={statusForm.processing}
                                >
                                    {statusForm.processing
                                        ? 'Actualizando…'
                                        : church.status === 'active'
                                          ? 'Actualizar estado'
                                          : 'Cambiar estado'}
                                </Button>
                            </form>

                            <dl className="mt-5 grid gap-3 border-t border-border pt-4 text-sm">
                                <Meta label="Último cambio">
                                    {formatDateTime(church.statusChangedAt)}
                                </Meta>
                                <Meta label="Responsable">
                                    {church.statusChangedBy ?? '—'}
                                </Meta>
                                <Meta label="Motivo actual">
                                    {church.statusReason ?? '—'}
                                </Meta>
                                {church.archivedAt && (
                                    <Meta label="Archivada">
                                        {formatDateTime(church.archivedAt)}
                                    </Meta>
                                )}
                            </dl>
                        </CardContent>
                    </Card>

                    <Card className="bg-secondary/40">
                        <CardHeader>
                            <CardTitle>Metadatos</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="flex flex-wrap gap-2">
                                {currencies.map((currency) => (
                                    <span
                                        key={currency.code}
                                        className="inline-flex items-center gap-1.5 rounded-full border border-border bg-card px-2.5 py-1 text-xs font-semibold"
                                    >
                                        <Coins className="size-3.5 text-muted-foreground" />
                                        {currency.code}
                                        {currency.isDefault && (
                                            <span className="font-normal text-muted-foreground">
                                                · principal
                                            </span>
                                        )}
                                    </span>
                                ))}
                            </div>

                            <dl className="grid gap-3 text-sm">
                                <Meta label="País y zona">
                                    <span className="inline-flex items-center gap-1.5">
                                        <MapPin className="size-3.5 text-muted-foreground" />
                                        {church.countryCode ?? '—'} · {church.timezone ?? '—'}
                                    </span>
                                </Meta>
                                <Meta label="Creada">{formatDateTime(church.createdAt)}</Meta>
                                <Meta label="Última actualización">
                                    {formatDateTime(church.updatedAt)}
                                </Meta>
                            </dl>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AdminLayout>
    );
}

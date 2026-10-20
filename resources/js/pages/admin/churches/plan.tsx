import { Head, Link, useForm } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeft,
    Gauge,
    Layers,
    Settings2,
    ShieldCheck,
} from 'lucide-react';
import type { FormEvent } from 'react';

import { ChurchStatusBadge } from '@/components/admin/church-status-badge';
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
import { Textarea } from '@/components/ui/textarea';
import { AdminLayout } from '@/layouts/admin-layout';
import type {
    AdminPlanFeature,
    AdminPlanOption,
    AdminPlanResource,
    AdminSubscriptionDetail,
    ChurchStatusValue,
} from '@/types';

type SubscriptionStatusOption = {
    value: string;
    label: string;
};

type Props = {
    church: {
        id: number;
        name: string;
        slug: string;
        status: ChurchStatusValue;
        statusLabel: string;
    };
    subscription: AdminSubscriptionDetail;
    plans: AdminPlanOption[];
    resources: AdminPlanResource[];
    features: AdminPlanFeature[];
    statuses: SubscriptionStatusOption[];
    canEdit: boolean;
};

type LimitMode = 'inherit' | 'unlimited' | 'custom';

type LimitEntry = {
    mode: LimitMode;
    value: number | string;
};

const limitModeOptions: Array<{ value: LimitMode; label: string }> = [
    { value: 'inherit', label: 'Heredar del plan' },
    { value: 'unlimited', label: 'Ilimitado' },
    { value: 'custom', label: 'Valor personalizado' },
];

const featureModeOptions: Array<{ value: string; label: string }> = [
    { value: 'inherit', label: 'Heredar del plan' },
    { value: 'enabled', label: 'Habilitar' },
    { value: 'disabled', label: 'Deshabilitar' },
];

function formatPrice(priceMinor: number, currencyCode: string): string {
    return new Intl.NumberFormat('es', {
        style: 'currency',
        currency: currencyCode,
        maximumFractionDigits: 2,
    }).format(priceMinor / 100);
}

function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Intl.DateTimeFormat('es', { dateStyle: 'medium' }).format(new Date(value));
}

function limitLabel(limit: number | null): string {
    return limit === null ? 'Ilimitado' : String(limit);
}

export default function AdminChurchPlan({
    church,
    subscription,
    plans,
    resources,
    features,
    statuses,
    canEdit,
}: Props) {
    const subscriptionForm = useForm({
        plan_id: subscription.planId ?? plans[0]?.id ?? 0,
        status: subscription.status ?? 'active',
        notes: subscription.notes ?? '',
    });

    const limitForm = useForm({
        limits: Object.fromEntries(
            resources.map((resource) => [
                resource.key,
                {
                    mode: resource.override.mode,
                    value: resource.override.limitValue ?? '',
                } as LimitEntry,
            ]),
        ),
        note: '',
    });

    const featureForm = useForm({
        features: Object.fromEntries(
            features.map((feature) => [feature.key, feature.override.mode]),
        ) as Record<string, string>,
        note: '',
    });

    const exceeded = resources.filter((resource) => resource.exceeded);
    const limitError = (resource: string): string | undefined =>
        (limitForm.errors as Record<string, string | undefined>)[
            `limits.${resource}.value`
        ];

    function submitSubscription(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        subscriptionForm.put(`/admin/churches/${church.slug}/plan/subscription`);
    }

    function submitLimits(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        limitForm.put(`/admin/churches/${church.slug}/plan/limits`);
    }

    function submitFeatures(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        featureForm.put(`/admin/churches/${church.slug}/plan/features`);
    }

    function updateLimitMode(resource: string, mode: LimitMode) {
        const current = limitForm.data.limits[resource] ?? {
            mode: 'inherit',
            value: '',
        };

        limitForm.setData('limits', {
            ...limitForm.data.limits,
            [resource]: {
                ...current,
                mode,
            },
        });
    }

    function updateLimitValue(resource: string, value: string) {
        const current = limitForm.data.limits[resource] ?? {
            mode: 'inherit',
            value: '',
        };

        limitForm.setData('limits', {
            ...limitForm.data.limits,
            [resource]: {
                ...current,
                value,
            },
        });
    }

    function updateFeatureMode(feature: string, mode: string) {
        featureForm.setData('features', {
            ...featureForm.data.features,
            [feature]: mode,
        });
    }

    return (
        <AdminLayout>
            <Head title={`Plan · ${church.name}`} />

            <Button variant="ghost" size="sm" asChild>
                <Link href={`/admin/churches/${church.slug}`}>
                    <ArrowLeft />
                    {church.name}
                </Link>
            </Button>

            <div className="mt-5 flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p className="eyebrow">Plataforma</p>
                    <div className="mt-2 flex flex-wrap items-center gap-3">
                        <h1 className="font-display text-3xl font-semibold tracking-tight sm:text-4xl">
                            Plan y límites
                        </h1>
                        <ChurchStatusBadge status={church.status} label={church.statusLabel} />
                    </div>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {church.name} · /{church.slug}
                    </p>
                </div>
            </div>

            {!canEdit && (
                <div className="mt-5 flex items-start gap-3 rounded-xl border border-accent/30 bg-accent/10 px-4 py-3 text-sm">
                    <ShieldCheck className="mt-0.5 size-4 shrink-0 text-accent" />
                    <p>
                        La iglesia está archivada. Restáurala para cambiar el plan, los límites o
                        las funciones.
                    </p>
                </div>
            )}

            {exceeded.length > 0 && (
                <div className="mt-5 flex items-start gap-3 rounded-xl border border-destructive/25 bg-destructive/10 px-4 py-3 text-sm">
                    <AlertTriangle className="mt-0.5 size-4 shrink-0 text-destructive" />
                    <p>
                        Uso por encima del límite en{' '}
                        <strong>{exceeded.map((item) => item.label.toLowerCase()).join(', ')}</strong>.
                        Los datos existentes se conservan; las nuevas altas quedarán bloqueadas
                        hasta ampliar el límite con un override o un plan superior.
                    </p>
                </div>
            )}

            <div className="mt-6 grid gap-6 lg:grid-cols-[1fr_1.5fr]">
                <Card>
                    <CardHeader>
                        <div className="flex items-center gap-2">
                            <Layers className="size-4 text-primary" />
                            <CardTitle>Suscripción</CardTitle>
                        </div>
                        <CardDescription>
                            El cambio de plan no elimina datos. Puede dejar recursos por encima del
                            nuevo límite.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form className="space-y-4" onSubmit={submitSubscription} noValidate>
                            <div className="space-y-2">
                                <Label htmlFor="plan">Plan</Label>
                                <Select
                                    id="plan"
                                    value={subscriptionForm.data.plan_id}
                                    disabled={!canEdit}
                                    onChange={(event) =>
                                        subscriptionForm.setData(
                                            'plan_id',
                                            Number(event.target.value),
                                        )
                                    }
                                >
                                    {plans.map((plan) => (
                                        <option key={plan.id} value={plan.id}>
                                            {plan.name} ·{' '}
                                            {plan.priceMinor === 0
                                                ? 'sin costo'
                                                : `${formatPrice(plan.priceMinor, plan.currencyCode)}/mes`}
                                        </option>
                                    ))}
                                </Select>
                                {subscriptionForm.errors.plan_id && (
                                    <p className="text-sm text-destructive">
                                        {subscriptionForm.errors.plan_id}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="subscription-status">Estado</Label>
                                <Select
                                    id="subscription-status"
                                    value={subscriptionForm.data.status}
                                    disabled={!canEdit}
                                    onChange={(event) =>
                                        subscriptionForm.setData('status', event.target.value)
                                    }
                                >
                                    {statuses.map((status) => (
                                        <option key={status.value} value={status.value}>
                                            {status.label}
                                        </option>
                                    ))}
                                </Select>
                                {subscriptionForm.errors.status && (
                                    <p className="text-sm text-destructive">
                                        {subscriptionForm.errors.status}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="subscription-notes">Notas</Label>
                                <Textarea
                                    id="subscription-notes"
                                    value={subscriptionForm.data.notes}
                                    disabled={!canEdit}
                                    placeholder="Motivo del cambio, acuerdo comercial u observaciones."
                                    onChange={(event) =>
                                        subscriptionForm.setData('notes', event.target.value)
                                    }
                                />
                                {subscriptionForm.errors.notes && (
                                    <p className="text-sm text-destructive">
                                        {subscriptionForm.errors.notes}
                                    </p>
                                )}
                            </div>

                            <Button
                                type="submit"
                                className="w-full"
                                disabled={!canEdit || subscriptionForm.processing}
                            >
                                {subscriptionForm.processing ? 'Guardando…' : 'Guardar suscripción'}
                            </Button>
                        </form>

                        <dl className="mt-5 grid gap-3 border-t border-border pt-4 text-sm">
                            <div className="flex justify-between gap-3">
                                <dt className="text-muted-foreground">Plan actual</dt>
                                <dd className="font-semibold">{subscription.planName ?? '—'}</dd>
                            </div>
                            <div className="flex justify-between gap-3">
                                <dt className="text-muted-foreground">Inicio</dt>
                                <dd>{formatDate(subscription.startedAt)}</dd>
                            </div>
                            <div className="flex justify-between gap-3">
                                <dt className="text-muted-foreground">Último cambio</dt>
                                <dd>{formatDate(subscription.planChangedAt)}</dd>
                            </div>
                            <div className="flex justify-between gap-3">
                                <dt className="text-muted-foreground">Asignado por</dt>
                                <dd>{subscription.assignedBy ?? 'Sistema'}</dd>
                            </div>
                        </dl>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <div className="flex items-center gap-2">
                            <Gauge className="size-4 text-primary" />
                            <CardTitle>Uso actual</CardTitle>
                        </div>
                        <CardDescription>
                            Consumo calculado en el servidor con los datos de la iglesia.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b border-border text-left text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                        <th className="pb-2">Recurso</th>
                                        <th className="pb-2 text-right">Uso</th>
                                        <th className="pb-2 text-right">Límite efectivo</th>
                                        <th className="pb-2 text-right">Plan</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {resources.map((resource) => (
                                        <tr key={resource.key}>
                                            <td className="py-2.5">
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <span className="font-medium">
                                                        {resource.label}
                                                    </span>
                                                    {resource.source === 'override' && (
                                                        <span className="rounded-full border border-accent/40 bg-accent/10 px-2 py-0.5 text-[0.65rem] font-semibold text-accent">
                                                            Override
                                                        </span>
                                                    )}
                                                </div>
                                            </td>
                                            <td
                                                className={
                                                    resource.exceeded
                                                        ? 'py-2.5 text-right font-semibold tabular-nums text-destructive'
                                                        : 'py-2.5 text-right font-semibold tabular-nums'
                                                }
                                            >
                                                {resource.used}
                                            </td>
                                            <td className="py-2.5 text-right tabular-nums">
                                                {limitLabel(resource.limit)}
                                            </td>
                                            <td className="py-2.5 text-right tabular-nums text-muted-foreground">
                                                {limitLabel(resource.planLimit)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <div className="mt-6 grid gap-6 lg:grid-cols-2">
                <Card>
                    <CardHeader>
                        <div className="flex items-center gap-2">
                            <Settings2 className="size-4 text-primary" />
                            <CardTitle>Overrides de límites</CardTitle>
                        </div>
                        <CardDescription>
                            Un override de iglesia reemplaza el límite del plan. “Heredar” elimina
                            el override.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form className="space-y-4" onSubmit={submitLimits} noValidate>
                            {resources.map((resource) => (
                                <div
                                    key={resource.key}
                                    className="grid gap-2 rounded-lg border border-border bg-background/60 px-3 py-3 sm:grid-cols-[1fr_9rem_6rem]"
                                >
                                    <div>
                                        <p className="text-sm font-semibold">{resource.label}</p>
                                        <p className="text-xs text-muted-foreground">
                                            Plan: {limitLabel(resource.planLimit)} · Uso:{' '}
                                            {resource.used}
                                        </p>
                                    </div>
                                    <Select
                                        aria-label={`Modo de límite para ${resource.label}`}
                                        value={limitForm.data.limits[resource.key]?.mode ?? 'inherit'}
                                        disabled={!canEdit}
                                        onChange={(event) =>
                                            updateLimitMode(
                                                resource.key,
                                                event.target.value as LimitMode,
                                            )
                                        }
                                    >
                                        {limitModeOptions.map((option) => (
                                            <option key={option.value} value={option.value}>
                                                {option.label}
                                            </option>
                                        ))}
                                    </Select>
                                    <Input
                                        type="number"
                                        min={0}
                                        aria-label={`Valor de límite para ${resource.label}`}
                                        placeholder="Valor"
                                        value={limitForm.data.limits[resource.key]?.value ?? ''}
                                        disabled={
                                            !canEdit ||
                                            limitForm.data.limits[resource.key]?.mode !== 'custom'
                                        }
                                        onChange={(event) =>
                                            updateLimitValue(resource.key, event.target.value)
                                        }
                                    />
                                    {limitError(resource.key) && (
                                        <p className="text-sm text-destructive sm:col-span-3">
                                            {limitError(resource.key)}
                                        </p>
                                    )}
                                </div>
                            ))}

                            <div className="space-y-2">
                                <Label htmlFor="limits-note">Nota</Label>
                                <Input
                                    id="limits-note"
                                    value={limitForm.data.note}
                                    disabled={!canEdit}
                                    placeholder="Motivo o referencia del ajuste."
                                    onChange={(event) =>
                                        limitForm.setData('note', event.target.value)
                                    }
                                />
                            </div>

                            <Button
                                type="submit"
                                className="w-full"
                                variant="outline"
                                disabled={!canEdit || limitForm.processing}
                            >
                                {limitForm.processing ? 'Guardando…' : 'Guardar overrides de límites'}
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <div className="flex items-center gap-2">
                            <Settings2 className="size-4 text-primary" />
                            <CardTitle>Overrides de funciones</CardTitle>
                        </div>
                        <CardDescription>
                            Habilitar o deshabilitar funciones por iglesia. Deshabilitar no borra
                            datos existentes.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form className="space-y-4" onSubmit={submitFeatures} noValidate>
                            {features.map((feature) => (
                                <div
                                    key={feature.key}
                                    className="grid gap-2 rounded-lg border border-border bg-background/60 px-3 py-3 sm:grid-cols-[1fr_10rem]"
                                >
                                    <div>
                                        <div className="flex flex-wrap items-center gap-2">
                                            <p className="text-sm font-semibold">{feature.label}</p>
                                            <span className="rounded-full border border-border bg-card px-2 py-0.5 text-[0.65rem] font-semibold text-muted-foreground">
                                                {feature.enabled ? 'Activa' : 'Inactiva'}
                                            </span>
                                        </div>
                                        <p className="text-xs text-muted-foreground">
                                            {feature.description}
                                        </p>
                                    </div>
                                    <Select
                                        aria-label={`Modo de función para ${feature.label}`}
                                        value={featureForm.data.features[feature.key] ?? 'inherit'}
                                        disabled={!canEdit}
                                        onChange={(event) =>
                                            updateFeatureMode(feature.key, event.target.value)
                                        }
                                    >
                                        {featureModeOptions.map((option) => (
                                            <option key={option.value} value={option.value}>
                                                {option.label}
                                            </option>
                                        ))}
                                    </Select>
                                </div>
                            ))}

                            <div className="space-y-2">
                                <Label htmlFor="features-note">Nota</Label>
                                <Input
                                    id="features-note"
                                    value={featureForm.data.note}
                                    disabled={!canEdit}
                                    placeholder="Motivo o referencia del ajuste."
                                    onChange={(event) =>
                                        featureForm.setData('note', event.target.value)
                                    }
                                />
                            </div>

                            <Button
                                type="submit"
                                className="w-full"
                                variant="outline"
                                disabled={!canEdit || featureForm.processing}
                            >
                                {featureForm.processing
                                    ? 'Guardando…'
                                    : 'Guardar overrides de funciones'}
                            </Button>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}

import { Head, Link } from '@inertiajs/react';
import {
    ArrowLeft,
    CheckCircle2,
    CircleSlash,
    Gauge,
    ShieldCheck,
    Sparkles,
} from 'lucide-react';

import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { AppLayout } from '@/layouts/app-layout';
import type { PlanOverview, PlanUsageItem } from '@/types';

type Props = {
    church: {
        name: string;
        slug: string;
    };
    membership: {
        role: string;
    };
    plan: PlanOverview;
};

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

function UsageRow({ item }: { item: PlanUsageItem }) {
    const percent =
        item.limit === null || item.limit === 0
            ? item.used > 0 && item.limit === 0
                ? 100
                : 0
            : Math.min(100, Math.round((item.used / item.limit) * 100));

    return (
        <li className="space-y-2 py-4 first:pt-0 last:pb-0">
            <div className="flex flex-wrap items-baseline justify-between gap-2">
                <div className="flex items-center gap-2">
                    <p className="text-sm font-semibold">{item.label}</p>
                    {item.source === 'override' && (
                        <span className="rounded-full border border-accent/40 bg-accent/10 px-2 py-0.5 text-[0.68rem] font-semibold text-accent">
                            Ajuste de plataforma
                        </span>
                    )}
                </div>
                <p className="text-sm tabular-nums">
                    <span className={item.exceeded ? 'font-semibold text-destructive' : 'font-semibold'}>
                        {item.used}
                    </span>
                    <span className="text-muted-foreground">
                        {' '}
                        / {item.unlimited ? 'Ilimitado' : item.limit} {item.unit}
                    </span>
                </p>
            </div>
            <div
                className="h-2 overflow-hidden rounded-full bg-secondary"
                role="progressbar"
                aria-label={`${item.label}: ${item.used} de ${item.limit ?? 'ilimitado'}`}
                aria-valuenow={item.used}
                aria-valuemin={0}
                aria-valuemax={item.limit ?? undefined}
            >
                <div
                    className={
                        item.exceeded
                            ? 'h-full rounded-full bg-destructive'
                            : 'h-full rounded-full bg-primary'
                    }
                    style={{ width: `${percent}%` }}
                />
            </div>
            <p className="text-xs text-muted-foreground">{item.description}</p>
        </li>
    );
}

export default function ChurchPlan({ church, membership, plan }: Props) {
    const usageItems = Object.values(plan.usage);
    const exceeded = usageItems.filter((item) => item.exceeded);
    const enabledFeatures = plan.features.filter((feature) => feature.enabled);
    const subscription = plan.subscription;

    return (
        <AppLayout>
            <Head title={`Plan · ${church.name}`} />

            <Button variant="ghost" size="sm" asChild>
                <Link href={`/churches/${church.slug}`}>
                    <ArrowLeft />
                    {church.name}
                </Link>
            </Button>

            <div className="mt-5 flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p className="eyebrow">Suscripción</p>
                    <h1 className="mt-2 font-display text-3xl font-semibold tracking-tight sm:text-4xl">
                        Plan y uso
                    </h1>
                    <p className="mt-2 max-w-2xl text-sm leading-relaxed text-muted-foreground">
                        Consulta el plan actual, el consumo frente a sus límites y las funciones
                        habilitadas. Tu rol es <strong className="text-foreground">{membership.role}</strong>.
                    </p>
                </div>
            </div>

            {exceeded.length > 0 && (
                <div className="mt-5 flex items-start gap-3 rounded-xl border border-destructive/25 bg-destructive/10 px-4 py-3 text-sm">
                    <Gauge className="mt-0.5 size-4 shrink-0 text-destructive" />
                    <p>
                        El uso supera el límite en{' '}
                        <strong>{exceeded.map((item) => item.label.toLowerCase()).join(', ')}</strong>.
                        Los datos existentes se conservan, pero no se podrán crear registros nuevos
                        de esos recursos hasta ampliar el plan o recibir un ajuste.
                    </p>
                </div>
            )}

            <div className="mt-6 grid gap-6 lg:grid-cols-[1fr_1.4fr]">
                <Card className="bg-primary text-primary-foreground">
                    <CardHeader>
                        <p className="text-xs font-semibold tracking-[0.16em] text-accent uppercase">
                            Plan actual
                        </p>
                        <CardTitle className="mt-1 font-display text-3xl">
                            {plan.plan?.name ?? 'Sin plan'}
                        </CardTitle>
                        <CardDescription className="text-primary-foreground/75">
                            {plan.plan?.description ?? 'La iglesia no tiene un plan asignado.'}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-5">
                        {plan.plan && (
                            <p className="font-display text-2xl font-semibold">
                                {plan.plan.priceMinor === 0
                                    ? 'Sin costo'
                                    : `${formatPrice(plan.plan.priceMinor, plan.plan.currencyCode)} / ${plan.plan.billingInterval === 'year' ? 'año' : 'mes'}`}
                            </p>
                        )}

                        <dl className="grid gap-3 border-t border-primary-foreground/15 pt-4 text-sm">
                            <div className="flex items-center justify-between gap-3">
                                <dt className="text-primary-foreground/70">Estado</dt>
                                <dd className="font-semibold">
                                    {subscription?.statusLabel ?? 'Sin suscripción'}
                                </dd>
                            </div>
                            <div className="flex items-center justify-between gap-3">
                                <dt className="text-primary-foreground/70">Inicio</dt>
                                <dd className="font-semibold">
                                    {formatDate(subscription?.startedAt ?? null)}
                                </dd>
                            </div>
                            <div className="flex items-center justify-between gap-3">
                                <dt className="text-primary-foreground/70">Último cambio de plan</dt>
                                <dd className="font-semibold">
                                    {formatDate(subscription?.planChangedAt ?? null)}
                                </dd>
                            </div>
                        </dl>

                        <div className="flex items-start gap-2 rounded-lg border border-primary-foreground/15 bg-primary-foreground/5 px-3 py-2.5 text-xs leading-relaxed text-primary-foreground/80">
                            <ShieldCheck className="mt-0.5 size-3.5 shrink-0" />
                            <p>
                                Para cambiar de plan o solicitar un ajuste de límites, contacta al
                                equipo de la plataforma.
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Uso frente a límites</CardTitle>
                        <CardDescription>
                            El consumo se calcula con los datos actuales de la iglesia.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ul className="divide-y divide-border">
                            {usageItems.map((item) => (
                                <UsageRow key={item.key} item={item} />
                            ))}
                        </ul>
                    </CardContent>
                </Card>
            </div>

            <Card className="mt-6">
                <CardHeader>
                    <div className="flex items-center gap-2">
                        <Sparkles className="size-4 text-accent" />
                        <CardTitle>Funciones del plan</CardTitle>
                    </div>
                    <CardDescription>
                        {enabledFeatures.length} de {plan.features.length} funciones habilitadas.
                        Deshabilitar una función no borra los datos existentes.
                    </CardDescription>
                </CardHeader>
                <CardContent className="grid gap-3 sm:grid-cols-2">
                    {plan.features.map((feature) => (
                        <div
                            key={feature.key}
                            className="flex items-start gap-3 rounded-lg border border-border bg-background/60 px-4 py-3"
                        >
                            {feature.enabled ? (
                                <CheckCircle2 className="mt-0.5 size-4 shrink-0 text-primary" />
                            ) : (
                                <CircleSlash className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                            )}
                            <div>
                                <div className="flex flex-wrap items-center gap-2">
                                    <p className="text-sm font-semibold">{feature.label}</p>
                                    {feature.source === 'override' && (
                                        <span className="rounded-full border border-accent/40 bg-accent/10 px-2 py-0.5 text-[0.68rem] font-semibold text-accent">
                                            Ajuste
                                        </span>
                                    )}
                                </div>
                                <p className="mt-0.5 text-xs text-muted-foreground">
                                    {feature.description}
                                </p>
                                <p className="mt-1 text-xs font-semibold">
                                    {feature.enabled ? 'Habilitada' : 'No incluida'}
                                </p>
                            </div>
                        </div>
                    ))}
                </CardContent>
            </Card>
        </AppLayout>
    );
}

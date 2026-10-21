import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    CheckCircle2,
    CircleSlash,
    Gauge,
    ShieldCheck,
    Sparkles,
} from 'lucide-react';
import { useState } from 'react';

import { AvailablePlanCard } from '@/components/plans/available-plan-card';
import { UsageRow } from '@/components/plans/usage-row';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { AppLayout } from '@/layouts/app-layout';
import { formatDate, formatMoneyMinor } from '@/lib/format';
import type { AvailablePlan, PageProps, PlanOverview } from '@/types';

type Props = {
    church: {
        name: string;
        slug: string;
    };
    membership: {
        role: string;
    };
    plan: PlanOverview;
    availablePlans: AvailablePlan[];
    canChangePlan: boolean;
};

export default function ChurchPlan({
    church,
    membership,
    plan,
    availablePlans,
    canChangePlan,
}: Props) {
    const { errors } = usePage<PageProps>().props;
    const [processingPlan, setProcessingPlan] = useState<string | null>(null);
    const usageItems = Object.values(plan.usage);
    const exceeded = usageItems.filter((item) => item.exceeded);
    const enabledFeatures = plan.features.filter((feature) => feature.enabled);
    const subscription = plan.subscription;
    const isFree = plan.plan?.isFree ?? false;

    function changePlan(nextPlan: AvailablePlan) {
        const confirmed = window.confirm(
            `¿Cambiar al plan ${nextPlan.name}? Se aplica de inmediato. Los datos existentes no se borran; si el nuevo plan tiene límites menores, no podrás crear registros por encima del límite. La facturación se coordina por separado con la plataforma.`,
        );

        if (!confirmed) {
            return;
        }

        setProcessingPlan(nextPlan.key);
        router.put(
            `/churches/${church.slug}/plan`,
            { plan: nextPlan.key },
            {
                preserveScroll: true,
                onFinish: () => setProcessingPlan(null),
            },
        );
    }

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
                        habilitadas. Tu rol es{' '}
                        <strong className="text-foreground">{membership.role}</strong>.
                    </p>
                </div>
            </div>

            {isFree && (
                <div className="mt-5 flex items-start gap-3 rounded-xl border border-accent/30 bg-accent/10 px-4 py-3 text-sm">
                    <Sparkles className="mt-0.5 size-4 shrink-0 text-accent" />
                    <div>
                        <p className="font-semibold">
                            Estás en el plan gratuito {plan.plan?.name}.
                        </p>
                        <p className="mt-1 text-muted-foreground">
                            Conoce Crecimiento y Ministerio Pro para ampliar límites y habilitar
                            funciones. Puedes cambiar cuando quieras.
                        </p>
                    </div>
                </div>
            )}

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
                                {plan.plan.isFree
                                    ? 'Gratis'
                                    : `${formatMoneyMinor(plan.plan.priceMinor, plan.plan.currencyCode)} / ${plan.plan.billingInterval === 'year' ? 'año' : 'mes'}`}
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
                                Los cambios de plan se aplican de inmediato. La facturación del
                                plan se coordina por separado con el equipo de la plataforma.
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
                        <CardTitle>Planes disponibles</CardTitle>
                    </div>
                    <CardDescription>
                        Compara límites y funciones. El cambio se aplica de inmediato; la
                        facturación se gestiona manualmente desde la plataforma.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    {errors.plan && (
                        <p className="mb-4 rounded-lg border border-destructive/25 bg-destructive/10 px-3 py-2 text-sm text-destructive">
                            {errors.plan}
                        </p>
                    )}

                    <div className="grid gap-4 lg:grid-cols-3">
                        {availablePlans.map((availablePlan) => (
                            <AvailablePlanCard
                                key={availablePlan.key}
                                plan={availablePlan}
                                resources={usageItems}
                                featureDefinitions={plan.features}
                                canChangePlan={canChangePlan}
                                processing={processingPlan === availablePlan.key}
                                onSelect={changePlan}
                            />
                        ))}
                    </div>
                </CardContent>
            </Card>

            <Card className="mt-6">
                <CardHeader>
                    <div className="flex items-center gap-2">
                        <Sparkles className="size-4 text-accent" />
                        <CardTitle>Funciones del plan actual</CardTitle>
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

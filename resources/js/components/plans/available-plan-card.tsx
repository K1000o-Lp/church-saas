import { CheckCircle2 } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import { formatMoney } from '@/lib/format';
import type { AvailablePlan, PlanFeatureItem, PlanUsageItem } from '@/types';

type Props = {
    plan: AvailablePlan;
    resources: PlanUsageItem[];
    featureDefinitions: PlanFeatureItem[];
    canChangePlan: boolean;
    processing: boolean;
    onSelect: (plan: AvailablePlan) => void;
};

export function AvailablePlanCard({
    plan,
    resources,
    featureDefinitions,
    canChangePlan,
    processing,
    onSelect,
}: Props) {
    const includedFeatures = featureDefinitions.filter((feature) =>
        plan.features.includes(feature.key),
    );

    return (
        <Card
            className={cn(
                'h-full gap-4 py-5',
                plan.isCurrent && 'border-primary/40 bg-primary/5',
            )}
        >
            <CardHeader className="gap-3">
                <div className="flex items-start justify-between gap-3">
                    <div>
                        <CardTitle className="text-xl">{plan.name}</CardTitle>
                        <CardDescription className="mt-1">{plan.description}</CardDescription>
                    </div>
                    {plan.isCurrent && (
                        <span className="shrink-0 rounded-full border border-primary/30 bg-primary/10 px-2.5 py-1 text-xs font-semibold text-primary">
                            Plan actual
                        </span>
                    )}
                </div>
                <p className="font-display text-2xl font-semibold">
                    {plan.isFree ? (
                        'Gratis'
                    ) : (
                        <>
                            {formatMoney(plan.price, plan.currencyCode)}
                            <span className="ml-1 text-sm font-normal text-muted-foreground">
                                / {plan.billingInterval === 'year' ? 'año' : 'mes'}
                            </span>
                        </>
                    )}
                </p>
            </CardHeader>

            <CardContent className="flex flex-1 flex-col gap-5">
                <dl className="grid grid-cols-2 gap-x-4 gap-y-2 text-xs">
                    {resources.map((resource) => {
                        const limit = plan.limits[resource.key] ?? null;

                        return (
                            <div key={resource.key} className="flex justify-between gap-2">
                                <dt className="text-muted-foreground">{resource.label}</dt>
                                <dd className="text-right font-semibold tabular-nums">
                                    {limit === null ? 'Ilimitado' : limit}
                                </dd>
                            </div>
                        );
                    })}
                </dl>

                <div className="space-y-2">
                    <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                        Funciones incluidas
                    </p>
                    {includedFeatures.length === 0 ? (
                        <p className="text-xs text-muted-foreground">
                            Sin funciones opcionales. Incluye la operación base.
                        </p>
                    ) : (
                        <ul className="space-y-1.5">
                            {includedFeatures.map((feature) => (
                                <li key={feature.key} className="flex items-start gap-2 text-xs">
                                    <CheckCircle2 className="mt-0.5 size-3.5 shrink-0 text-primary" />
                                    <span>{feature.label}</span>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>

                <div className="mt-auto">
                    {plan.isCurrent ? (
                        <Button variant="outline" className="w-full" disabled>
                            Plan actual
                        </Button>
                    ) : canChangePlan ? (
                        <Button
                            className="w-full"
                            disabled={processing}
                            onClick={() => onSelect(plan)}
                        >
                            {processing ? 'Cambiando…' : 'Cambiar a este plan'}
                        </Button>
                    ) : (
                        <p className="text-xs leading-relaxed text-muted-foreground">
                            Solo un administrador puede cambiar el plan de la iglesia.
                        </p>
                    )}
                </div>
            </CardContent>
        </Card>
    );
}

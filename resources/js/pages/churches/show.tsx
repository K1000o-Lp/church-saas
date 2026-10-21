import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    CalendarClock,
    Coins,
    CreditCard,
    Globe2,
    History,
    Plus,
    ShieldCheck,
} from 'lucide-react';

import { InvoiceStatusBadge } from '@/components/admin/invoice-status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { AppLayout } from '@/layouts/app-layout';
import { formatDate, formatMoneyMinor } from '@/lib/format';
import type { ChurchBilling, ChurchCurrencySummary, ChurchSummary, PageProps } from '@/types';

type Props = {
    church: ChurchSummary;
    membership: {
        role: string;
    };
    currencies: ChurchCurrencySummary[];
    billing: ChurchBilling;
};

function Detail({
    icon: Icon,
    label,
    value,
}: {
    icon: typeof Globe2;
    label: string;
    value: string | null;
}) {
    return (
        <div className="flex items-start gap-3 rounded-lg border border-border bg-background/60 p-4">
            <span className="mt-0.5 grid size-9 shrink-0 place-items-center rounded-md bg-secondary text-muted-foreground">
                <Icon className="size-4" />
            </span>
            <div className="min-w-0">
                <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                    {label}
                </p>
                <p className="mt-1 truncate text-sm font-semibold">{value ?? 'Pendiente'}</p>
            </div>
        </div>
    );
}

export default function ChurchShow({ church, membership, currencies, billing }: Props) {
    const currentChurch = usePage<PageProps>().props.currentChurch;
    const permissions = currentChurch?.permissions ?? [];
    const plan = currentChurch?.plan ?? null;
    const canManageMembers = permissions.includes('church.members.manage');
    const canManageCustomFields = permissions.includes('people.custom_fields.manage');

    return (
        <AppLayout>
            <Head title={church.name} />

            <div className="flex flex-wrap items-center justify-between gap-3">
                <Button variant="ghost" size="sm" asChild>
                    <Link href="/dashboard">
                        <ArrowLeft />
                        Mis iglesias
                    </Link>
                </Button>
                <div className="flex flex-wrap items-center gap-2">
                    <span className="rounded-full border border-primary/20 bg-primary/10 px-3 py-1 text-xs font-semibold text-primary">
                        Sesión activa · {membership.role}
                    </span>
                    {plan && (
                        <span className="rounded-full border border-accent/30 bg-accent/10 px-3 py-1 text-xs font-semibold text-accent">
                            Plan {plan.name}
                            {plan.isFree ? ' · Gratis' : ''}
                        </span>
                    )}
                    {permissions.includes('church.plan.view') && (
                        <Button variant="outline" size="sm" asChild>
                            <Link href={`/churches/${church.slug}/plan`}>
                                <CreditCard />
                                Plan y uso
                            </Link>
                        </Button>
                    )}
                    {canManageMembers && (
                        <Button variant="outline" size="sm" asChild>
                            <Link href={`/churches/${church.slug}/settings/members`}>
                                <ShieldCheck />
                                Miembros
                            </Link>
                        </Button>
                    )}
                    {canManageCustomFields && (
                        <Button variant="outline" size="sm" asChild>
                            <Link href={`/churches/${church.slug}/settings/custom-fields`}>
                                <Plus />
                                Campos
                            </Link>
                        </Button>
                    )}
                    {permissions.includes('church.audit.view') && (
                        <Button variant="outline" size="sm" asChild>
                            <Link href={`/churches/${church.slug}/settings/audit`}>
                                <History />
                                Auditoría
                            </Link>
                        </Button>
                    )}
                    <Button variant="outline" size="sm" asChild>
                        <Link href="/onboarding/church">
                            <Plus />
                            Crear otra iglesia
                        </Link>
                    </Button>
                </div>
            </div>

            <section className="mt-5 overflow-hidden rounded-xl border border-border bg-primary text-primary-foreground">
                <div className="relative px-6 py-8 sm:px-10 sm:py-10">
                    <div
                        aria-hidden="true"
                        className="pointer-events-none absolute -top-20 -right-16 size-64 rounded-full border border-primary-foreground/10"
                    />
                    <div
                        aria-hidden="true"
                        className="pointer-events-none absolute right-8 -bottom-16 size-40 rounded-full bg-accent/20 blur-2xl"
                    />
                    <p className="text-xs font-semibold tracking-[0.16em] text-accent uppercase">
                        {church.countryCode ?? 'País pendiente'}
                        {church.currencyCode ? ` · ${church.currencyCode}` : ''}
                    </p>
                    <h1 className="mt-3 max-w-2xl font-display text-4xl leading-tight font-semibold text-balance sm:text-5xl">
                        {church.name}
                    </h1>
                    <p className="mt-4 max-w-xl text-sm leading-relaxed text-primary-foreground/75 sm:text-base">
                        Contexto de iglesia resuelto para esta petición. Los datos de otras
                        iglesias permanecen fuera del alcance.
                    </p>
                </div>
            </section>

            <div className="mt-6 grid gap-6 lg:grid-cols-[1.5fr_1fr]">
                <Card>
                    <CardHeader>
                        <CardTitle>Identidad de la iglesia</CardTitle>
                        <p className="text-sm text-muted-foreground">
                            Datos base usados para resolver el tenant y presentar la operación.
                        </p>
                    </CardHeader>
                    <CardContent className="grid gap-3 sm:grid-cols-2">
                        <Detail icon={Globe2} label="País" value={church.countryCode} />
                        <Detail icon={Coins} label="Moneda" value={church.currencyCode} />
                        <Detail icon={CalendarClock} label="Zona horaria" value={church.timezone} />
                        <Detail icon={ShieldCheck} label="Tu rol" value={membership.role} />
                    </CardContent>
                </Card>

                <Card className="bg-secondary/40">
                    <CardHeader>
                        <CardTitle>Monedas admitidas</CardTitle>
                        <p className="text-sm text-muted-foreground">
                            Catálogo configurado al crear la iglesia.
                        </p>
                    </CardHeader>
                    <CardContent className="space-y-4 text-sm leading-relaxed text-muted-foreground">
                        {currencies.length > 0 ? (
                            <ul className="flex flex-wrap gap-2">
                                {currencies.map((currency) => (
                                    <li
                                        key={currency.code}
                                        className="rounded-full border border-border bg-card px-3 py-1 text-xs font-semibold text-foreground"
                                    >
                                        {currency.code}
                                        {currency.isDefault && (
                                            <span className="ml-1 font-normal text-muted-foreground">
                                                · principal
                                            </span>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        ) : (
                            <p>No hay monedas configuradas.</p>
                        )}
                        <p>
                            Tu membresía es <strong className="text-foreground">{membership.role}</strong>.
                            {canManageMembers
                                ? ' Puedes invitar personas y administrar roles desde la configuración de miembros.'
                                : ' Un administrador puede invitarte a otras iglesias o ajustar tu rol.'}
                        </p>
                        {canManageMembers && (
                            <Button className="w-full" variant="secondary" asChild>
                                <Link href={`/churches/${church.slug}/settings/members`}>
                                    Administrar miembros
                                </Link>
                            </Button>
                        )}
                        <Button className="w-full" variant="secondary" asChild>
                            <Link href="/dashboard">Volver a mis iglesias</Link>
                        </Button>
                    </CardContent>
                </Card>
            </div>

            {billing && (
                <Card className="mt-6">
                    <CardHeader>
                        <CardTitle>Historial de facturas</CardTitle>
                        <p className="text-sm text-muted-foreground">
                            Facturación manual en USD. Los pagos y estados los registra la
                            administración de la plataforma.
                        </p>
                    </CardHeader>
                    <CardContent>
                        {billing.invoices.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Todavía no hay facturas emitidas para esta iglesia.
                            </p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[44rem] text-sm">
                                    <thead>
                                        <tr className="border-b border-border text-left text-xs tracking-wide text-muted-foreground uppercase">
                                            <th className="pb-3 pr-4 font-semibold">Factura</th>
                                            <th className="pb-3 pr-4 font-semibold">Estado</th>
                                            <th className="pb-3 pr-4 text-right font-semibold">
                                                Total
                                            </th>
                                            <th className="pb-3 pr-4 text-right font-semibold">
                                                Pagado
                                            </th>
                                            <th className="pb-3 pr-4 text-right font-semibold">
                                                Saldo
                                            </th>
                                            <th className="pb-3 font-semibold">Emitida</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-border">
                                        {billing.invoices.map((invoice) => (
                                            <tr key={invoice.id}>
                                                <td className="py-3 pr-4">
                                                    <p className="font-mono text-xs font-semibold">
                                                        {invoice.number}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {invoice.description ?? 'Sin concepto'}
                                                    </p>
                                                </td>
                                                <td className="py-3 pr-4">
                                                    <InvoiceStatusBadge
                                                        status={invoice.status}
                                                        label={invoice.statusLabel}
                                                    />
                                                </td>
                                                <td className="py-3 pr-4 text-right tabular-nums">
                                                    {formatMoneyMinor(
                                                        invoice.amountMinor,
                                                        invoice.currencyCode,
                                                    )}
                                                </td>
                                                <td className="py-3 pr-4 text-right tabular-nums">
                                                    {formatMoneyMinor(
                                                        invoice.paidMinor,
                                                        invoice.currencyCode,
                                                    )}
                                                </td>
                                                <td className="py-3 pr-4 text-right tabular-nums">
                                                    {formatMoneyMinor(
                                                        invoice.remainingMinor,
                                                        invoice.currencyCode,
                                                    )}
                                                </td>
                                                <td className="py-3 text-muted-foreground">
                                                    {formatDate(invoice.issuedAt)}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </CardContent>
                </Card>
            )}
        </AppLayout>
    );
}

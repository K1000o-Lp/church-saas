import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, CalendarClock, CheckCircle2, MapPin, Pencil, Plus, XCircle } from 'lucide-react';
import type { FormEvent } from 'react';

import { ServiceItemCard } from '@/components/services/service-item-card';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { AppLayout } from '@/layouts/app-layout';
import { formatDateTime } from '@/lib/format';
import type {
    AssignmentStatusOption,
    MinistryTeamOption,
    PersonOption,
    ServiceItemSummary,
    ServicePlanDetail,
    ServiceStatusOption,
} from '@/types';

type Props = {
    church: { name: string; slug: string };
    plan: ServicePlanDetail;
    items: ServiceItemSummary[];
    teams: MinistryTeamOption[];
    people: PersonOption[];
    assignmentStatuses: AssignmentStatusOption[];
    statuses: ServiceStatusOption[];
    canManage: boolean;
    canManageVolunteers: boolean;
};

export default function ServiceShow({
    church,
    plan,
    items,
    teams,
    people,
    assignmentStatuses,
    canManage,
    canManageVolunteers,
}: Props) {
    const churchPath = `/churches/${church.slug}`;
    const servicePath = `${churchPath}/services/${plan.id}`;
    const itemForm = useForm({
        title: '',
        position: String(items.length + 1),
        ministry_team_id: '',
        notes: '',
    });
    const itemErrors = itemForm.errors as Record<string, string>;

    function addItem(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        itemForm.post(`${servicePath}/items`, {
            preserveScroll: true,
            onSuccess: () => itemForm.reset('title', 'notes'),
        });
    }

    function changeStatus(action: 'publish' | 'cancel' | 'complete') {
        if (action !== 'publish' && !window.confirm('¿Actualizar el estado del culto?')) {
            return;
        }

        router.patch(`${servicePath}/${action}`, {}, { preserveScroll: true });
    }

    return (
        <AppLayout>
            <Head title={`${plan.title} · ${church.name}`} />

            <Button variant="ghost" size="sm" asChild>
                <Link href={`${churchPath}/services`}>
                    <ArrowLeft />
                    Volver a servicios
                </Link>
            </Button>

            <div className="mt-5 flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p className="eyebrow">Culto</p>
                    <h1 className="mt-2 font-display text-3xl font-semibold">{plan.title}</h1>
                    <p className="mt-2 flex flex-wrap items-center gap-3 text-sm text-muted-foreground">
                        <span className="inline-flex items-center gap-1">
                            <CalendarClock className="size-4" />
                            {formatDateTime(plan.startsAt)}
                        </span>
                        {plan.location && (
                            <span className="inline-flex items-center gap-1">
                                <MapPin className="size-4" />
                                {plan.location}
                            </span>
                        )}
                        <span className="inline-flex rounded-full border border-border bg-secondary/60 px-2.5 py-0.5 text-xs font-semibold">
                            {plan.statusLabel}
                        </span>
                    </p>
                </div>

                {canManage && (
                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" asChild>
                            <Link href={`${servicePath}/edit`}>
                                <Pencil />
                                Editar
                            </Link>
                        </Button>
                        {plan.status === 'draft' && (
                            <Button variant="outline" onClick={() => changeStatus('publish')}>
                                <CheckCircle2 />
                                Publicar
                            </Button>
                        )}
                        {['draft', 'published'].includes(plan.status) && (
                            <Button variant="destructive" onClick={() => changeStatus('cancel')}>
                                <XCircle />
                                Cancelar
                            </Button>
                        )}
                        {plan.status === 'published' && (
                            <Button variant="outline" onClick={() => changeStatus('complete')}>
                                <CheckCircle2 />
                                Completar
                            </Button>
                        )}
                    </div>
                )}
            </div>

            {canManage && (
                <Card className="mt-8">
                    <CardHeader>
                        <CardTitle>Agregar ítem</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form
                            className="grid gap-3 sm:grid-cols-2 lg:grid-cols-[1.4fr_7rem_1fr_auto] lg:items-end"
                            onSubmit={addItem}
                            noValidate
                        >
                            <div className="space-y-2">
                                <Label htmlFor="title">Título</Label>
                                <Input
                                    id="title"
                                    value={itemForm.data.title}
                                    onChange={(event) =>
                                        itemForm.setData('title', event.target.value)
                                    }
                                />
                                {itemErrors.title && (
                                    <p className="text-sm text-destructive">{itemErrors.title}</p>
                                )}
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="position">Orden</Label>
                                <Input
                                    id="position"
                                    type="number"
                                    min={0}
                                    value={itemForm.data.position}
                                    onChange={(event) =>
                                        itemForm.setData('position', event.target.value)
                                    }
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="team">Equipo</Label>
                                <Select
                                    id="team"
                                    value={itemForm.data.ministry_team_id}
                                    onChange={(event) =>
                                        itemForm.setData('ministry_team_id', event.target.value)
                                    }
                                >
                                    <option value="">Sin equipo</option>
                                    {teams.map((team) => (
                                        <option key={team.id} value={team.id}>
                                            {team.name}
                                        </option>
                                    ))}
                                </Select>
                            </div>
                            <Button type="submit" disabled={itemForm.processing}>
                                <Plus />
                                Agregar
                            </Button>
                            <div className="space-y-2 sm:col-span-2 lg:col-span-4">
                                <Label htmlFor="notes">Notas</Label>
                                <Textarea
                                    id="notes"
                                    className="min-h-16"
                                    value={itemForm.data.notes}
                                    onChange={(event) =>
                                        itemForm.setData('notes', event.target.value)
                                    }
                                />
                            </div>
                        </form>
                    </CardContent>
                </Card>
            )}

            <div className="mt-6 space-y-4">
                {items.length === 0 ? (
                    <Card>
                        <CardContent className="py-10 text-center text-sm text-muted-foreground">
                            Todavía no hay ítems en este culto.
                        </CardContent>
                    </Card>
                ) : (
                    items.map((item) => (
                        <ServiceItemCard
                            key={item.id}
                            churchSlug={church.slug}
                            serviceId={plan.id}
                            item={item}
                            teams={teams}
                            people={people}
                            assignmentStatuses={assignmentStatuses}
                            canManage={canManage}
                            canManageVolunteers={canManageVolunteers}
                        />
                    ))
                )}
            </div>
        </AppLayout>
    );
}

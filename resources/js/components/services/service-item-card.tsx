import { router, useForm } from '@inertiajs/react';
import { Save, Trash2, UserPlus } from 'lucide-react';
import type { FormEvent } from 'react';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import type {
    AssignmentStatusOption,
    MinistryTeamOption,
    PersonOption,
    ServiceItemSummary,
    VolunteerAssignmentSummary,
} from '@/types';

type Props = {
    churchSlug: string;
    serviceId: number;
    item: ServiceItemSummary;
    teams: MinistryTeamOption[];
    people: PersonOption[];
    assignmentStatuses: AssignmentStatusOption[];
    canManage: boolean;
    canManageVolunteers: boolean;
};

export function ServiceItemCard({
    churchSlug,
    serviceId,
    item,
    teams,
    people,
    assignmentStatuses,
    canManage,
    canManageVolunteers,
}: Props) {
    const base = `/churches/${churchSlug}/services/${serviceId}/items/${item.id}`;
    const itemForm = useForm({
        title: item.title,
        position: String(item.position),
        ministry_team_id: item.team?.id !== undefined ? String(item.team.id) : '',
        notes: item.notes ?? '',
    });
    const assignmentForm = useForm({
        person_id: '',
        role: '',
        status: 'invited',
    });

    const itemErrors = itemForm.errors as Record<string, string>;
    const assignmentErrors = assignmentForm.errors as Record<string, string>;

    function saveItem(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        itemForm.put(base, { preserveScroll: true });
    }

    function removeItem() {
        if (!window.confirm(`¿Eliminar el ítem "${item.title}"?`)) {
            return;
        }

        itemForm.delete(base, { preserveScroll: true });
    }

    function addAssignment(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        assignmentForm.post(`${base}/assignments`, {
            preserveScroll: true,
            onSuccess: () => assignmentForm.reset(),
        });
    }

    function changeStatus(
        assignment: VolunteerAssignmentSummary,
        status: string,
    ) {
        router.patch(
            `${base}/assignments/${assignment.id}`,
            { status },
            { preserveScroll: true },
        );
    }

    function removeAssignment(assignment: VolunteerAssignmentSummary) {
        itemForm.delete(`${base}/assignments/${assignment.id}`, { preserveScroll: true });
    }

    return (
        <Card>
            <CardHeader>
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <CardTitle className="text-base">
                        {item.position}. {item.title}
                        {item.team && (
                            <span className="ml-2 text-xs font-normal text-muted-foreground">
                                {item.team.name}
                            </span>
                        )}
                    </CardTitle>
                    {canManage && (
                        <Button variant="ghost" size="sm" onClick={removeItem}>
                            <Trash2 />
                            Eliminar
                        </Button>
                    )}
                </div>
            </CardHeader>
            <CardContent className="space-y-4">
                {canManage && (
                    <details className="rounded-lg border border-border bg-background/60 px-3 py-2">
                        <summary className="cursor-pointer text-sm font-semibold">
                            Editar ítem
                        </summary>
                        <form className="mt-3 grid gap-3 sm:grid-cols-2" onSubmit={saveItem} noValidate>
                            <div className="space-y-2">
                                <Label htmlFor={`title-${item.id}`}>Título</Label>
                                <Input
                                    id={`title-${item.id}`}
                                    value={itemForm.data.title}
                                    onChange={(event) =>
                                        itemForm.setData('title', event.target.value)
                                    }
                                />
                                {itemErrors.title && (
                                    <p className="text-sm text-destructive">
                                        {itemErrors.title}
                                    </p>
                                )}
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor={`position-${item.id}`}>Orden</Label>
                                <Input
                                    id={`position-${item.id}`}
                                    type="number"
                                    min={0}
                                    value={itemForm.data.position}
                                    onChange={(event) =>
                                        itemForm.setData('position', event.target.value)
                                    }
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor={`team-${item.id}`}>Equipo</Label>
                                <Select
                                    id={`team-${item.id}`}
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
                            <div className="space-y-2 sm:col-span-2">
                                <Label htmlFor={`notes-${item.id}`}>Notas</Label>
                                <Textarea
                                    id={`notes-${item.id}`}
                                    value={itemForm.data.notes}
                                    onChange={(event) =>
                                        itemForm.setData('notes', event.target.value)
                                    }
                                />
                            </div>
                            <Button type="submit" size="sm" disabled={itemForm.processing}>
                                <Save />
                                Guardar ítem
                            </Button>
                        </form>
                    </details>
                )}

                <div>
                    <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                        Voluntarios
                    </p>
                    {item.assignments.length === 0 ? (
                        <p className="mt-2 text-sm text-muted-foreground">
                            Sin voluntarios asignados.
                        </p>
                    ) : (
                        <ul className="mt-2 divide-y divide-border">
                            {item.assignments.map((assignment) => (
                                <li
                                    key={assignment.id}
                                    className="flex flex-wrap items-center justify-between gap-2 py-2 first:pt-0 last:pb-0"
                                >
                                    <div>
                                        <p className="text-sm font-semibold">
                                            {assignment.person?.fullName ?? 'Persona'}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {assignment.role ?? 'Sin rol'}
                                        </p>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        {canManageVolunteers ? (
                                            <Select
                                                value={assignment.status}
                                                onChange={(event) =>
                                                    changeStatus(assignment, event.target.value)
                                                }
                                            >
                                                {assignmentStatuses.map((status) => (
                                                    <option
                                                        key={status.value}
                                                        value={status.value}
                                                    >
                                                        {status.label}
                                                    </option>
                                                ))}
                                            </Select>
                                        ) : (
                                            <span className="text-xs text-muted-foreground">
                                                {assignment.statusLabel}
                                            </span>
                                        )}
                                        {canManageVolunteers && (
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => removeAssignment(assignment)}
                                                aria-label="Eliminar asignación"
                                            >
                                                <Trash2 />
                                            </Button>
                                        )}
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}

                    {canManageVolunteers && (
                        <form
                            className="mt-3 grid gap-2 sm:grid-cols-[1.2fr_1fr_10rem_auto] sm:items-end"
                            onSubmit={addAssignment}
                            noValidate
                        >
                            <div className="space-y-1">
                                <Label htmlFor={`person-${item.id}`}>Persona</Label>
                                <Select
                                    id={`person-${item.id}`}
                                    value={assignmentForm.data.person_id}
                                    onChange={(event) =>
                                        assignmentForm.setData('person_id', event.target.value)
                                    }
                                >
                                    <option value="">Selecciona</option>
                                    {people.map((person) => (
                                        <option key={person.id} value={person.id}>
                                            {person.fullName}
                                        </option>
                                    ))}
                                </Select>
                                {assignmentErrors.person_id && (
                                    <p className="text-xs text-destructive">
                                        {assignmentErrors.person_id}
                                    </p>
                                )}
                            </div>
                            <div className="space-y-1">
                                <Label htmlFor={`role-${item.id}`}>Rol</Label>
                                <Input
                                    id={`role-${item.id}`}
                                    value={assignmentForm.data.role}
                                    onChange={(event) =>
                                        assignmentForm.setData('role', event.target.value)
                                    }
                                />
                            </div>
                            <div className="space-y-1">
                                <Label htmlFor={`status-${item.id}`}>Estado</Label>
                                <Select
                                    id={`status-${item.id}`}
                                    value={assignmentForm.data.status}
                                    onChange={(event) =>
                                        assignmentForm.setData('status', event.target.value)
                                    }
                                >
                                    {assignmentStatuses.map((status) => (
                                        <option key={status.value} value={status.value}>
                                            {status.label}
                                        </option>
                                    ))}
                                </Select>
                            </div>
                            <Button type="submit" size="sm" disabled={assignmentForm.processing}>
                                <UserPlus />
                                Asignar
                            </Button>
                        </form>
                    )}
                </div>
            </CardContent>
        </Card>
    );
}

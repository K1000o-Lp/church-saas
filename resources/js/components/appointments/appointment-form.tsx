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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { dateTimeInputValue } from '@/lib/format';
import type {
    AppointmentDetail,
    AppointmentHostOption,
    AppointmentPersonOption,
} from '@/types';

type Props = {
    action: string;
    method: 'post' | 'put';
    people: AppointmentPersonOption[];
    hosts: AppointmentHostOption[];
    appointment?: AppointmentDetail;
    selectedPersonId?: number | null;
    submitLabel: string;
};

export function AppointmentForm({
    action,
    method,
    people,
    hosts,
    appointment,
    selectedPersonId,
    submitLabel,
}: Props) {
    const form = useForm({
        person_id:
            appointment?.person?.id !== undefined
                ? String(appointment.person.id)
                : selectedPersonId !== undefined && selectedPersonId !== null
                  ? String(selectedPersonId)
                  : '',
        host_user_id:
            appointment?.host.id !== undefined ? String(appointment.host.id) : '',
        scheduled_at: dateTimeInputValue(appointment?.scheduledAt ?? null),
        duration_minutes: String(appointment?.durationMinutes ?? 60),
        subject: appointment?.subject ?? '',
        notes: appointment?.notes ?? '',
    });

    const errors = form.errors as Record<string, string>;

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

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
                    <CardTitle>Detalles de la cita</CardTitle>
                    <CardDescription>
                        Define con quién es la cita, quién la atiende y cuándo se realizará.
                    </CardDescription>
                </CardHeader>
                <CardContent className="grid gap-5 sm:grid-cols-2">
                    <div className="space-y-2">
                        <Label htmlFor="person_id">Persona</Label>
                        <Select
                            id="person_id"
                            value={form.data.person_id}
                            aria-invalid={errors.person_id ? true : undefined}
                            onChange={(event) =>
                                form.setData('person_id', event.target.value)
                            }
                        >
                            <option value="">Selecciona una persona</option>
                            {people.map((person) => (
                                <option key={person.id} value={person.id}>
                                    {person.fullName}
                                    {person.email ? ` · ${person.email}` : ''}
                                </option>
                            ))}
                        </Select>
                        {errors.person_id && (
                            <p className="text-sm text-destructive">{errors.person_id}</p>
                        )}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="host_user_id">Atendida por</Label>
                        <Select
                            id="host_user_id"
                            value={form.data.host_user_id}
                            aria-invalid={errors.host_user_id ? true : undefined}
                            onChange={(event) =>
                                form.setData('host_user_id', event.target.value)
                            }
                        >
                            <option value="">Selecciona un líder</option>
                            {hosts.map((host) => (
                                <option key={host.id} value={host.id}>
                                    {host.name ?? host.email ?? `Usuario ${host.id}`}
                                </option>
                            ))}
                        </Select>
                        {errors.host_user_id && (
                            <p className="text-sm text-destructive">{errors.host_user_id}</p>
                        )}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="scheduled_at">Fecha y hora</Label>
                        <Input
                            id="scheduled_at"
                            type="datetime-local"
                            value={form.data.scheduled_at}
                            aria-invalid={errors.scheduled_at ? true : undefined}
                            onChange={(event) =>
                                form.setData('scheduled_at', event.target.value)
                            }
                        />
                        {errors.scheduled_at && (
                            <p className="text-sm text-destructive">{errors.scheduled_at}</p>
                        )}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="duration_minutes">Duración (minutos)</Label>
                        <Input
                            id="duration_minutes"
                            type="number"
                            min={15}
                            max={480}
                            step={15}
                            value={form.data.duration_minutes}
                            aria-invalid={errors.duration_minutes ? true : undefined}
                            onChange={(event) =>
                                form.setData('duration_minutes', event.target.value)
                            }
                        />
                        {errors.duration_minutes && (
                            <p className="text-sm text-destructive">
                                {errors.duration_minutes}
                            </p>
                        )}
                    </div>

                    <div className="space-y-2 sm:col-span-2">
                        <Label htmlFor="subject">Motivo</Label>
                        <Input
                            id="subject"
                            placeholder="Consejería, visita pastoral, seguimiento…"
                            value={form.data.subject}
                            aria-invalid={errors.subject ? true : undefined}
                            onChange={(event) => form.setData('subject', event.target.value)}
                        />
                        {errors.subject && (
                            <p className="text-sm text-destructive">{errors.subject}</p>
                        )}
                    </div>

                    <div className="space-y-2 sm:col-span-2">
                        <Label htmlFor="notes">Notas privadas</Label>
                        <Textarea
                            id="notes"
                            className="min-h-28"
                            placeholder="Visible solo para quien atiende la cita y Pastoral."
                            value={form.data.notes}
                            aria-invalid={errors.notes ? true : undefined}
                            onChange={(event) => form.setData('notes', event.target.value)}
                        />
                        {errors.notes && (
                            <p className="text-sm text-destructive">{errors.notes}</p>
                        )}
                    </div>
                </CardContent>
            </Card>

            <Button type="submit" disabled={form.processing}>
                <Save />
                {form.processing ? 'Guardando…' : submitLabel}
            </Button>
        </form>
    );
}

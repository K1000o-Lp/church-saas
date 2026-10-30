import { useForm } from '@inertiajs/react';
import { Save } from 'lucide-react';
import type { FormEvent } from 'react';

import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import type { MinistryTeamSummary } from '@/types';

export function TeamRow({
    churchSlug,
    team,
    canManage,
}: {
    churchSlug: string;
    team: MinistryTeamSummary;
    canManage: boolean;
}) {
    const form = useForm({
        name: team.name,
        description: team.description ?? '',
        is_active: team.isActive,
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.put(`/churches/${churchSlug}/services/teams/${team.id}`, {
            preserveScroll: true,
        });
    }

    return (
        <form
            className="grid gap-3 rounded-xl border border-border bg-card px-4 py-4 sm:grid-cols-[1fr_1.4fr_auto_auto] sm:items-center"
            onSubmit={submit}
            noValidate
        >
            <div>
                <Input
                    aria-label="Nombre del equipo"
                    value={form.data.name}
                    disabled={!canManage}
                    onChange={(event) => form.setData('name', event.target.value)}
                />
                <p className="mt-1 text-xs text-muted-foreground">
                    {team.itemsCount} ítems de servicio
                </p>
            </div>
            <Textarea
                aria-label="Descripción"
                className="min-h-9"
                value={form.data.description}
                disabled={!canManage}
                onChange={(event) => form.setData('description', event.target.value)}
            />
            <div className="flex items-center gap-2">
                <Checkbox
                    id={`team-active-${team.id}`}
                    checked={form.data.is_active}
                    disabled={!canManage}
                    onCheckedChange={(checked) =>
                        form.setData('is_active', checked === true)
                    }
                />
                <label htmlFor={`team-active-${team.id}`} className="text-xs font-semibold">
                    Activo
                </label>
            </div>
            {canManage && (
                <Button type="submit" variant="outline" size="sm" disabled={form.processing}>
                    <Save />
                    Guardar
                </Button>
            )}
        </form>
    );
}

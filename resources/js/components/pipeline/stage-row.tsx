import { useForm } from '@inertiajs/react';
import { Save } from 'lucide-react';
import type { FormEvent } from 'react';

import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import type { PipelineStageSummary } from '@/types';

export function PipelineStageRow({
    churchSlug,
    stage,
    canManage,
}: {
    churchSlug: string;
    stage: PipelineStageSummary;
    canManage: boolean;
}) {
    const form = useForm({
        name: stage.name,
        position: String(stage.position),
        is_active: stage.isActive,
    });

    const errors = form.errors as Record<string, string>;

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.put(`/churches/${churchSlug}/settings/pipeline/${stage.id}`, {
            preserveScroll: true,
        });
    }

    return (
        <form
            className="grid gap-3 rounded-lg border border-border bg-background/60 px-4 py-3 sm:grid-cols-[1fr_6rem_auto_auto] sm:items-center"
            onSubmit={submit}
            noValidate
        >
            <div>
                <Input
                    aria-label="Nombre de la etapa"
                    value={form.data.name}
                    disabled={!canManage}
                    onChange={(event) => form.setData('name', event.target.value)}
                />
                {errors.name && (
                    <p className="mt-1 text-xs text-destructive">{errors.name}</p>
                )}
                <p className="mt-1 text-xs text-muted-foreground">
                    {stage.completedCount} personas completaron esta etapa
                </p>
            </div>
            <Input
                aria-label="Posición"
                type="number"
                min={0}
                value={form.data.position}
                disabled={!canManage}
                onChange={(event) => form.setData('position', event.target.value)}
            />
            <div className="flex items-center gap-2">
                <Checkbox
                    id={`active-${stage.id}`}
                    checked={form.data.is_active}
                    disabled={!canManage}
                    onCheckedChange={(checked) =>
                        form.setData('is_active', checked === true)
                    }
                />
                <label htmlFor={`active-${stage.id}`} className="text-xs font-semibold">
                    Activa
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

import { router, useForm } from '@inertiajs/react';
import { ChevronDown, Save } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';

import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { CustomFieldDefinitionSummary } from '@/types';

type Props = {
    churchPath: string;
    definition: CustomFieldDefinitionSummary;
};

function parseOptions(value: string): string[] {
    return value
        .split(',')
        .map((option) => option.trim())
        .filter(Boolean);
}

export function CustomFieldDefinitionRow({ churchPath, definition }: Props) {
    const [optionsText, setOptionsText] = useState(
        definition.options.map((option) => option.value).join(', '),
    );
    const form = useForm({
        label: definition.label,
        options: definition.options.map((option) => option.value),
        is_required: definition.isRequired,
        is_active: definition.isActive,
    });
    const errors = form.errors as Record<string, string>;
    const requiresOptions = definition.type === 'select' || definition.type === 'multiselect';

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            options: requiresOptions ? parseOptions(optionsText) : [],
        }));
        form.put(`${churchPath}/settings/custom-fields/${definition.id}`, {
            preserveScroll: true,
        });
    }

    function toggle() {
        router.patch(
            `${churchPath}/settings/custom-fields/${definition.id}/toggle`,
            {},
            { preserveScroll: true },
        );
    }

    return (
        <details className="rounded-lg border border-border bg-card">
            <summary className="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-3">
                <div>
                    <p className="font-semibold">
                        {definition.label}
                        {definition.isRequired && (
                            <span className="ml-2 text-xs font-normal text-destructive">
                                Obligatorio
                            </span>
                        )}
                    </p>
                    <p className="mt-0.5 text-xs text-muted-foreground">
                        <span className="font-mono">{definition.key}</span> · {definition.typeLabel}
                    </p>
                </div>
                <div className="flex items-center gap-2">
                    <span
                        className={
                            definition.isActive
                                ? 'rounded-full border border-primary/20 bg-primary/10 px-2 py-0.5 text-xs font-semibold text-primary'
                                : 'rounded-full border border-border bg-secondary/60 px-2 py-0.5 text-xs font-semibold text-muted-foreground'
                        }
                    >
                        {definition.isActive ? 'Activo' : 'Inactivo'}
                    </span>
                    <ChevronDown className="size-4 text-muted-foreground" />
                </div>
            </summary>

            <form className="space-y-5 border-t border-border px-4 py-4" onSubmit={submit}>
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="space-y-2">
                        <Label htmlFor={`label-${definition.id}`}>Etiqueta</Label>
                        <Input
                            id={`label-${definition.id}`}
                            value={form.data.label}
                            aria-invalid={errors.label ? true : undefined}
                            onChange={(event) => form.setData('label', event.target.value)}
                        />
                        {errors.label && (
                            <p className="text-sm text-destructive">{errors.label}</p>
                        )}
                    </div>

                    {requiresOptions && (
                        <div className="space-y-2">
                            <Label htmlFor={`options-${definition.id}`}>
                                Opciones separadas por coma
                            </Label>
                            <Input
                                id={`options-${definition.id}`}
                                value={optionsText}
                                aria-invalid={errors.options ? true : undefined}
                                onChange={(event) => {
                                    setOptionsText(event.target.value);
                                    form.setData('options', parseOptions(event.target.value));
                                }}
                            />
                            {errors.options && (
                                <p className="text-sm text-destructive">{errors.options}</p>
                            )}
                        </div>
                    )}
                </div>

                <div className="flex flex-wrap items-center gap-5">
                    <label className="flex items-center gap-2 text-sm font-semibold">
                        <Checkbox
                            checked={form.data.is_required}
                            onCheckedChange={(checked) =>
                                form.setData('is_required', checked === true)
                            }
                        />
                        Obligatorio
                    </label>
                    <label className="flex items-center gap-2 text-sm font-semibold">
                        <Checkbox
                            checked={form.data.is_active}
                            onCheckedChange={(checked) =>
                                form.setData('is_active', checked === true)
                            }
                        />
                        Activo
                    </label>
                </div>

                <div className="flex flex-wrap justify-end gap-2">
                    <Button type="button" variant="ghost" size="sm" onClick={toggle}>
                        {definition.isActive ? 'Desactivar' : 'Activar'}
                    </Button>
                    <Button type="submit" size="sm" disabled={form.processing}>
                        <Save />
                        Guardar
                    </Button>
                </div>
            </form>
        </details>
    );
}

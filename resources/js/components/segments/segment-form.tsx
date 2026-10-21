import { Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Save } from 'lucide-react';
import type { FormEvent } from 'react';

import { SegmentRuleBuilder, emptyRuleSet } from '@/components/segments/rule-builder';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import type {
    SegmentField,
    SegmentRuleSet,
    SegmentTypeOption,
    SegmentTypeValue,
} from '@/types';

type FormData = {
    name: string;
    description: string;
    type: SegmentTypeValue;
    rules: SegmentRuleSet | null;
};

type Props = {
    church: {
        name: string;
        slug: string;
    };
    mode: 'create' | 'edit';
    segment?: {
        id: number;
        name: string;
        description: string | null;
        type: SegmentTypeValue;
        typeLabel: string;
        rules: SegmentRuleSet | null;
    };
    types: SegmentTypeOption[];
    fields: SegmentField[];
};

export function SegmentForm({ church, mode, segment, types, fields }: Props) {
    const churchPath = `/churches/${church.slug}`;
    const initialType = segment?.type ?? 'dynamic';
    const form = useForm<FormData>({
        name: segment?.name ?? '',
        description: segment?.description ?? '',
        type: initialType,
        rules:
            segment?.rules ??
            (initialType === 'dynamic' ? emptyRuleSet(fields) : null),
    });
    const errors = form.errors as Record<string, string>;
    const isDynamic = form.data.type === 'dynamic';
    const typeDescription = types.find((type) => type.value === form.data.type)?.description;

    function changeType(type: SegmentTypeValue) {
        form.setData({
            ...form.data,
            type,
            rules:
                type === 'dynamic'
                    ? (form.data.rules ?? emptyRuleSet(fields))
                    : null,
        });
    }

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (mode === 'create') {
            form.post(`${churchPath}/segments`, { preserveScroll: true });

            return;
        }

        form.put(`${churchPath}/segments/${segment?.id}`, { preserveScroll: true });
    }

    const backHref =
        mode === 'create' || !segment
            ? `${churchPath}/segments`
            : `${churchPath}/segments/${segment.id}`;

    return (
        <form className="space-y-6" onSubmit={submit} noValidate>
            <Card>
                <CardHeader>
                    <CardTitle>Datos del segmento</CardTitle>
                    <CardDescription>
                        Usa un nombre que el equipo reconozca, por ejemplo zona, grupo o división.
                    </CardDescription>
                </CardHeader>
                <CardContent className="grid gap-4 sm:grid-cols-2">
                    <div className="space-y-2">
                        <Label htmlFor="name">Nombre</Label>
                        <Input
                            id="name"
                            value={form.data.name}
                            placeholder="Zona este"
                            aria-invalid={errors.name ? true : undefined}
                            onChange={(event) => form.setData('name', event.target.value)}
                        />
                        {errors.name && <p className="text-sm text-destructive">{errors.name}</p>}
                    </div>

                    {mode === 'create' ? (
                        <div className="space-y-2">
                            <Label htmlFor="type">Tipo</Label>
                            <Select
                                id="type"
                                value={form.data.type}
                                onChange={(event) =>
                                    changeType(event.target.value as SegmentTypeValue)
                                }
                            >
                                {types.map((type) => (
                                    <option key={type.value} value={type.value}>
                                        {type.label}
                                    </option>
                                ))}
                            </Select>
                            {typeDescription && (
                                <p className="text-xs text-muted-foreground">{typeDescription}</p>
                            )}
                        </div>
                    ) : (
                        <div className="space-y-2">
                            <Label>Tipo</Label>
                            <p className="flex h-10 items-center text-sm font-semibold">
                                {segment?.typeLabel}
                            </p>
                            <p className="text-xs text-muted-foreground">
                                El tipo no se puede cambiar después de crear el segmento.
                            </p>
                        </div>
                    )}

                    <div className="space-y-2 sm:col-span-2">
                        <Label htmlFor="description">Descripción</Label>
                        <Textarea
                            id="description"
                            value={form.data.description}
                            placeholder="Personas que viven en la zona este."
                            aria-invalid={errors.description ? true : undefined}
                            onChange={(event) => form.setData('description', event.target.value)}
                        />
                        {errors.description && (
                            <p className="text-sm text-destructive">{errors.description}</p>
                        )}
                    </div>
                </CardContent>
            </Card>

            {isDynamic ? (
                <SegmentRuleBuilder
                    churchPath={churchPath}
                    fields={fields}
                    value={form.data.rules ?? emptyRuleSet(fields)}
                    onChange={(rules) => form.setData('rules', rules)}
                    error={errors.rules}
                />
            ) : (
                <Card>
                    <CardHeader>
                        <CardTitle>Miembros</CardTitle>
                        <CardDescription>
                            Los segmentos estáticos se llenan a mano. Después de guardar, agrega
                            personas desde el detalle del segmento.
                        </CardDescription>
                    </CardHeader>
                </Card>
            )}

            <div className="flex flex-wrap items-center justify-between gap-3">
                <Button variant="ghost" asChild>
                    <Link href={backHref}>
                        <ArrowLeft />
                        Cancelar
                    </Link>
                </Button>
                <Button
                    type="submit"
                    disabled={
                        form.processing ||
                        (isDynamic && (form.data.rules?.conditions.length ?? 0) === 0)
                    }
                >
                    <Save />
                    {mode === 'create' ? 'Crear segmento' : 'Guardar cambios'}
                </Button>
            </div>
        </form>
    );
}

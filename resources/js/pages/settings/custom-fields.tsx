import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, ListPlus, Plus } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';

import { CustomFieldDefinitionRow } from '@/components/settings/custom-field-definition-row';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select } from '@/components/ui/select';
import { AppLayout } from '@/layouts/app-layout';
import type { CustomFieldDefinitionSummary, RoleOption } from '@/types';

type Props = {
    church: {
        name: string;
        slug: string;
    };
    definitions: CustomFieldDefinitionSummary[];
    types: RoleOption[];
};

function parseOptions(value: string): string[] {
    return value
        .split(',')
        .map((option) => option.trim())
        .filter(Boolean);
}

export default function CustomFieldsSettings({ church, definitions, types }: Props) {
    const churchPath = `/churches/${church.slug}`;
    const [optionsText, setOptionsText] = useState('');
    const form = useForm({
        key: '',
        label: '',
        type: 'text',
        options: [] as string[],
        is_required: false,
        is_active: true,
    });
    const errors = form.errors as Record<string, string>;
    const requiresOptions = form.data.type === 'select' || form.data.type === 'multiselect';

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            options: requiresOptions ? parseOptions(optionsText) : [],
        }));
        form.post(`${churchPath}/settings/custom-fields`, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setOptionsText('');
            },
        });
    }

    function changeType(type: string) {
        const nextRequires = type === 'select' || type === 'multiselect';

        form.setData({
            ...form.data,
            type,
            options: nextRequires ? parseOptions(optionsText) : [],
        });
    }

    return (
        <AppLayout>
            <Head title={`Campos personalizados · ${church.name}`} />

            <Button variant="ghost" size="sm" asChild>
                <Link href={`${churchPath}/people`}>
                    <ArrowLeft />
                    Volver a personas
                </Link>
            </Button>

            <div className="mt-5">
                <p className="eyebrow">Configuración</p>
                <h1 className="mt-2 font-display text-3xl font-semibold">
                    Campos personalizados
                </h1>
                <p className="mt-2 max-w-2xl text-sm leading-relaxed text-muted-foreground">
                    Define información adicional para las personas de {church.name}. Desactivar
                    un campo no borra los valores ya guardados.
                </p>
            </div>

            <Card className="mt-6">
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <ListPlus className="size-5 text-primary" />
                        Crear campo
                    </CardTitle>
                    <CardDescription>
                        La clave interna no se puede cambiar después. Usa minúsculas, números y
                        guiones bajos.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <form className="grid gap-4 sm:grid-cols-2" onSubmit={submit} noValidate>
                        <div className="space-y-2">
                            <Label htmlFor="key">Clave interna</Label>
                            <Input
                                id="key"
                                value={form.data.key}
                                placeholder="fecha_nacimiento"
                                aria-invalid={errors.key ? true : undefined}
                                onChange={(event) => form.setData('key', event.target.value)}
                            />
                            {errors.key && (
                                <p className="text-sm text-destructive">{errors.key}</p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="label">Etiqueta visible</Label>
                            <Input
                                id="label"
                                value={form.data.label}
                                placeholder="Fecha de nacimiento"
                                aria-invalid={errors.label ? true : undefined}
                                onChange={(event) => form.setData('label', event.target.value)}
                            />
                            {errors.label && (
                                <p className="text-sm text-destructive">{errors.label}</p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="type">Tipo</Label>
                            <Select
                                id="type"
                                value={form.data.type}
                                onChange={(event) => changeType(event.target.value)}
                            >
                                {types.map((type) => (
                                    <option key={type.value} value={type.value}>
                                        {type.label}
                                    </option>
                                ))}
                            </Select>
                            {errors.type && (
                                <p className="text-sm text-destructive">{errors.type}</p>
                            )}
                        </div>

                        {requiresOptions && (
                            <div className="space-y-2">
                                <Label htmlFor="options">Opciones separadas por coma</Label>
                                <Input
                                    id="options"
                                    value={optionsText}
                                    placeholder="Soltero, Casado, Viudo"
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

                        <div className="flex flex-wrap items-center gap-5 sm:col-span-2">
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
                            <Button
                                type="submit"
                                className="ml-auto"
                                disabled={form.processing}
                            >
                                <Plus />
                                Crear campo
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>

            <div className="mt-6 space-y-3">
                <h2 className="font-display text-xl font-semibold">Campos definidos</h2>
                {definitions.length === 0 ? (
                    <Card>
                        <CardContent className="py-10 text-center text-sm text-muted-foreground">
                            Todavía no hay campos personalizados.
                        </CardContent>
                    </Card>
                ) : (
                    definitions.map((definition) => (
                        <CustomFieldDefinitionRow
                            key={definition.id}
                            churchPath={churchPath}
                            definition={definition}
                        />
                    ))
                )}
            </div>
        </AppLayout>
    );
}

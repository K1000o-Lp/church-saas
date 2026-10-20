import { useForm } from '@inertiajs/react';
import { Save } from 'lucide-react';
import type { FormEvent } from 'react';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { CustomFieldInput } from '@/components/people/custom-field-input';
import type {
    CustomFieldDefinitionSummary,
    CustomFieldInputValue,
    PersonSummary,
} from '@/types';

type Props = {
    action: string;
    method: 'post' | 'put';
    definitions: CustomFieldDefinitionSummary[];
    person?: PersonSummary;
    values?: Record<string, CustomFieldInputValue>;
    submitLabel: string;
};

type FormData = {
    first_name: string;
    last_name: string;
    email: string;
    phone: string;
    custom_fields: Record<string, CustomFieldInputValue>;
};

export function PersonForm({
    action,
    method,
    definitions,
    person,
    values = {},
    submitLabel,
}: Props) {
    const form = useForm<FormData>({
        first_name: person?.firstName ?? '',
        last_name: person?.lastName ?? '',
        email: person?.email ?? '',
        phone: person?.phone ?? '',
        custom_fields: values,
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

    function setCustomField(key: string, value: CustomFieldInputValue) {
        form.setData('custom_fields', {
            ...form.data.custom_fields,
            [key]: value,
        });
    }

    return (
        <form className="space-y-6" onSubmit={submit} noValidate>
            <Card>
                <CardHeader>
                    <CardTitle>Datos de contacto</CardTitle>
                    <CardDescription>
                        Solo los campos necesarios para identificar y contactar a la persona.
                    </CardDescription>
                </CardHeader>
                <CardContent className="grid gap-5 sm:grid-cols-2">
                    <div className="space-y-2">
                        <Label htmlFor="first_name">Nombre</Label>
                        <Input
                            id="first_name"
                            autoComplete="given-name"
                            autoFocus
                            required
                            value={form.data.first_name}
                            aria-invalid={errors.first_name ? true : undefined}
                            onChange={(event) => form.setData('first_name', event.target.value)}
                        />
                        {errors.first_name && (
                            <p className="text-sm text-destructive">{errors.first_name}</p>
                        )}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="last_name">Apellido</Label>
                        <Input
                            id="last_name"
                            autoComplete="family-name"
                            value={form.data.last_name}
                            aria-invalid={errors.last_name ? true : undefined}
                            onChange={(event) => form.setData('last_name', event.target.value)}
                        />
                        {errors.last_name && (
                            <p className="text-sm text-destructive">{errors.last_name}</p>
                        )}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="email">Correo electrónico</Label>
                        <Input
                            id="email"
                            type="email"
                            autoComplete="email"
                            value={form.data.email}
                            aria-invalid={errors.email ? true : undefined}
                            onChange={(event) => form.setData('email', event.target.value)}
                        />
                        {errors.email && (
                            <p className="text-sm text-destructive">{errors.email}</p>
                        )}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="phone">Teléfono</Label>
                        <Input
                            id="phone"
                            type="tel"
                            autoComplete="tel"
                            value={form.data.phone}
                            aria-invalid={errors.phone ? true : undefined}
                            onChange={(event) => form.setData('phone', event.target.value)}
                        />
                        {errors.phone && (
                            <p className="text-sm text-destructive">{errors.phone}</p>
                        )}
                    </div>
                </CardContent>
            </Card>

            {definitions.length > 0 && (
                <Card>
                    <CardHeader>
                        <CardTitle>Información adicional</CardTitle>
                        <CardDescription>
                            Campos definidos por esta iglesia. Los campos desactivados no se
                            muestran, pero conservan sus valores históricos.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-5 sm:grid-cols-2">
                        {definitions.map((definition) => (
                            <CustomFieldInput
                                key={definition.key}
                                definition={definition}
                                value={form.data.custom_fields[definition.key] ?? null}
                                error={errors[`custom_fields.${definition.key}`]}
                                onChange={(value) => setCustomField(definition.key, value)}
                            />
                        ))}
                    </CardContent>
                </Card>
            )}

            {errors.plan && (
                <p className="rounded-lg border border-destructive/25 bg-destructive/10 px-3 py-2 text-sm text-destructive">
                    {errors.plan}
                </p>
            )}

            <div className="flex justify-end">
                <Button type="submit" disabled={form.processing}>
                    <Save />
                    {submitLabel}
                </Button>
            </div>
        </form>
    );
}

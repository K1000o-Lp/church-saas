import { Plus, Users } from 'lucide-react';
import { useState } from 'react';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Select } from '@/components/ui/select';
import { ConditionRow, defaultValue } from '@/components/segments/condition-row';
import type { SegmentCondition, SegmentField, SegmentRuleSet } from '@/types';

type Props = {
    churchPath: string;
    fields: SegmentField[];
    value: SegmentRuleSet;
    onChange: (rules: SegmentRuleSet) => void;
    error?: string;
};

export function emptyRuleSet(fields: SegmentField[]): SegmentRuleSet {
    const field = fields[0];

    if (!field) {
        return { version: 1, match: 'all', conditions: [] };
    }

    return { version: 1, match: 'all', conditions: [makeCondition(field)] };
}

function makeCondition(field: SegmentField): SegmentCondition {
    const operator = field.operators[0] ?? null;

    return {
        source: field.source,
        field: field.key,
        operator: operator?.value ?? '',
        value: defaultValue(field, operator),
    };
}

export function SegmentRuleBuilder({ churchPath, fields, value, onChange, error }: Props) {
    const [count, setCount] = useState<number | null>(null);
    const [previewError, setPreviewError] = useState<string | null>(null);
    const [previewing, setPreviewing] = useState(false);

    function addCondition() {
        const field = fields[0];

        if (!field || value.conditions.length >= 20) {
            return;
        }

        onChange({ ...value, conditions: [...value.conditions, makeCondition(field)] });
        setCount(null);
    }

    function updateCondition(index: number, condition: SegmentCondition) {
        onChange({
            ...value,
            conditions: value.conditions.map((item, position) =>
                position === index ? condition : item,
            ),
        });
        setCount(null);
    }

    function removeCondition(index: number) {
        onChange({
            ...value,
            conditions: value.conditions.filter((_, position) => position !== index),
        });
        setCount(null);
    }

    async function preview() {
        setPreviewing(true);
        setPreviewError(null);

        try {
            const response = await fetch(
                `${churchPath}/segments/preview?rules=${encodeURIComponent(JSON.stringify(value))}`,
                { headers: { Accept: 'application/json' }, cache: 'no-store' },
            );
            const data = (await response.json()) as { count?: number; message?: string };

            if (!response.ok) {
                setPreviewError(data.message ?? 'No se pudo contar las coincidencias.');

                return;
            }

            setCount(typeof data.count === 'number' ? data.count : 0);
        } catch {
            setPreviewError('No se pudo contar las coincidencias.');
        } finally {
            setPreviewing(false);
        }
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <Users className="size-5 text-primary" />
                    Reglas del segmento
                </CardTitle>
                <CardDescription>
                    Las personas se calculan automáticamente. Las archivadas nunca se incluyen.
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
                {fields.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        No hay campos disponibles. Crea campos personalizados primero.
                    </p>
                ) : (
                    <>
                        <div className="flex flex-wrap items-end gap-3">
                            <div className="space-y-2">
                                <Label htmlFor="match">Debe cumplir</Label>
                                <Select
                                    id="match"
                                    value={value.match}
                                    onChange={(event) => {
                                        onChange({
                                            ...value,
                                            match: event.target.value === 'any' ? 'any' : 'all',
                                        });
                                        setCount(null);
                                    }}
                                >
                                    <option value="all">Todas las condiciones</option>
                                    <option value="any">Alguna condición</option>
                                </Select>
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={addCondition}
                                disabled={value.conditions.length >= 20}
                            >
                                <Plus />
                                Agregar condición
                            </Button>
                        </div>

                        <div className="space-y-3">
                            {value.conditions.map((condition, index) => (
                                <ConditionRow
                                    key={`${condition.source}:${condition.field}:${index}`}
                                    condition={condition}
                                    fields={fields}
                                    onChange={(next) => updateCondition(index, next)}
                                    onRemove={() => removeCondition(index)}
                                    canRemove={value.conditions.length > 1}
                                />
                            ))}
                        </div>

                        {value.conditions.length === 0 && (
                            <p className="text-sm text-destructive">
                                Agrega al menos una condición.
                            </p>
                        )}

                        <div className="flex flex-wrap items-center gap-3">
                            <Button
                                type="button"
                                variant="secondary"
                                onClick={preview}
                                disabled={previewing || value.conditions.length === 0}
                            >
                                <Users />
                                {previewing ? 'Contando…' : 'Contar coincidencias'}
                            </Button>
                            {count !== null && (
                                <p className="text-sm font-semibold text-primary">
                                    {count} {count === 1 ? 'persona coincide' : 'personas coinciden'}
                                </p>
                            )}
                            {previewError && (
                                <p className="text-sm text-destructive">{previewError}</p>
                            )}
                        </div>
                    </>
                )}

                {error && <p className="text-sm text-destructive">{error}</p>}
            </CardContent>
        </Card>
    );
}

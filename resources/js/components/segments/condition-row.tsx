import { Trash2 } from 'lucide-react';
import type { ChangeEvent } from 'react';

import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select } from '@/components/ui/select';
import type {
    SegmentCondition,
    SegmentField,
    SegmentOperatorOption,
} from '@/types';

type Props = {
    condition: SegmentCondition;
    fields: SegmentField[];
    onChange: (condition: SegmentCondition) => void;
    onRemove: () => void;
    canRemove: boolean;
};

function findField(condition: SegmentCondition, fields: SegmentField[]): SegmentField | null {
    return (
        fields.find(
            (field) => field.source === condition.source && field.key === condition.field,
        ) ?? null
    );
}

export function defaultValue(
    field: SegmentField | null,
    operator: SegmentOperatorOption | null,
): SegmentCondition['value'] {
    if (!field || !operator || !operator.requiresValue) {
        return null;
    }

    if (operator.value === 'between') {
        return ['', ''];
    }

    if (field.type === 'select') {
        return field.options[0]?.value ?? '';
    }

    if (field.type === 'multiselect') {
        return [];
    }

    if (
        operator.value === 'any_of' ||
        operator.value === 'contains_any' ||
        operator.value === 'contains_all'
    ) {
        return [];
    }

    return '';
}

export function ConditionRow({ condition, fields, onChange, onRemove, canRemove }: Props) {
    const field = findField(condition, fields);
    const operator =
        field?.operators.find((option) => option.value === condition.operator) ??
        field?.operators[0] ??
        null;
    const value = condition.value;
    const selected = Array.isArray(value) ? value : [];

    function changeField(event: ChangeEvent<HTMLSelectElement>) {
        const next = fields.find(
            (candidate) => `${candidate.source}:${candidate.key}` === event.target.value,
        );

        if (!next) {
            return;
        }

        const nextOperator = next.operators[0] ?? null;

        onChange({
            source: next.source,
            field: next.key,
            operator: nextOperator?.value ?? '',
            value: defaultValue(next, nextOperator),
        });
    }

    function changeOperator(event: ChangeEvent<HTMLSelectElement>) {
        const next = field?.operators.find((option) => option.value === event.target.value) ?? null;

        onChange({
            ...condition,
            operator: next?.value ?? '',
            value: defaultValue(field, next),
        });
    }

    function toggleOption(option: string, checked: boolean) {
        const next = checked
            ? Array.from(new Set([...selected, option]))
            : selected.filter((item) => item !== option);

        onChange({ ...condition, value: next });
    }

    function renderValue() {
        if (!field || !operator) {
            return null;
        }

        if (!operator.requiresValue) {
            return (
                <p className="flex h-10 items-center text-sm text-muted-foreground">
                    No requiere valor
                </p>
            );
        }

        if (operator.value === 'between') {
            const from = Array.isArray(value) ? (value[0] ?? '') : '';
            const to = Array.isArray(value) ? (value[1] ?? '') : '';
            const type = field.type === 'number' ? 'number' : 'date';

            return (
                <div className="flex items-center gap-2">
                    <Input
                        type={type}
                        value={from}
                        aria-label="Desde"
                        onChange={(event) =>
                            onChange({ ...condition, value: [event.target.value, to] })
                        }
                    />
                    <span className="text-sm text-muted-foreground">y</span>
                    <Input
                        type={type}
                        value={to}
                        aria-label="Hasta"
                        onChange={(event) =>
                            onChange({ ...condition, value: [from, event.target.value] })
                        }
                    />
                </div>
            );
        }

        if (
            field.type === 'multiselect' ||
            operator.value === 'any_of' ||
            operator.value === 'contains_any' ||
            operator.value === 'contains_all'
        ) {
            if (field.options.length === 0) {
                return <p className="text-sm text-muted-foreground">Sin opciones disponibles.</p>;
            }

            return (
                <div className="flex flex-wrap gap-3">
                    {field.options.map((option) => (
                        <label
                            key={option.value}
                            className="flex items-center gap-2 text-sm font-medium"
                        >
                            <Checkbox
                                checked={selected.includes(option.value)}
                                onCheckedChange={(checked) =>
                                    toggleOption(option.value, checked === true)
                                }
                            />
                            {option.label}
                        </label>
                    ))}
                </div>
            );
        }

        if (field.type === 'select') {
            return (
                <Select
                    value={typeof value === 'string' ? value : ''}
                    aria-label="Valor"
                    onChange={(event) => onChange({ ...condition, value: event.target.value })}
                >
                    {field.options.map((option) => (
                        <option key={option.value} value={option.value}>
                            {option.label}
                        </option>
                    ))}
                </Select>
            );
        }

        return (
            <Input
                type={field.type === 'number' ? 'number' : field.type === 'date' ? 'date' : 'text'}
                value={typeof value === 'string' ? value : ''}
                aria-label="Valor"
                onChange={(event) => onChange({ ...condition, value: event.target.value })}
            />
        );
    }

    return (
        <div className="grid gap-3 rounded-lg border border-border bg-secondary/20 p-3 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_minmax(0,1.4fr)_auto] lg:items-end">
            <div className="space-y-2">
                <Label>Campo</Label>
                <Select
                    value={field ? `${field.source}:${field.key}` : `${condition.source}:${condition.field}`}
                    onChange={changeField}
                    aria-label="Campo"
                >
                    {!field && (
                        <option value={`${condition.source}:${condition.field}`} disabled>
                            Campo no disponible ({condition.field})
                        </option>
                    )}
                    <optgroup label="Personas">
                        {fields
                            .filter((candidate) => candidate.source === 'person')
                            .map((candidate) => (
                                <option
                                    key={`person:${candidate.key}`}
                                    value={`person:${candidate.key}`}
                                >
                                    {candidate.label}
                                </option>
                            ))}
                    </optgroup>
                    <optgroup label="Campos personalizados">
                        {fields
                            .filter((candidate) => candidate.source === 'custom')
                            .map((candidate) => (
                                <option
                                    key={`custom:${candidate.key}`}
                                    value={`custom:${candidate.key}`}
                                >
                                    {candidate.label}
                                </option>
                            ))}
                    </optgroup>
                </Select>
            </div>

            <div className="space-y-2">
                <Label>Operador</Label>
                <Select
                    value={operator?.value ?? ''}
                    onChange={changeOperator}
                    aria-label="Operador"
                >
                    {(field?.operators ?? []).map((option) => (
                        <option key={option.value} value={option.value}>
                            {option.label}
                        </option>
                    ))}
                </Select>
            </div>

            <div className="space-y-2">
                <Label>Valor</Label>
                {renderValue()}
            </div>

            <Button
                type="button"
                variant="ghost"
                size="icon"
                onClick={onRemove}
                disabled={!canRemove}
                aria-label="Quitar condición"
                title="Quitar condición"
            >
                <Trash2 />
            </Button>
        </div>
    );
}

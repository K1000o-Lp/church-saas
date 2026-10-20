import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import type {
    CustomFieldDefinitionSummary,
    CustomFieldInputValue,
} from '@/types';

type Props = {
    definition: CustomFieldDefinitionSummary;
    value: CustomFieldInputValue;
    error?: string;
    onChange: (value: CustomFieldInputValue) => void;
};

export function CustomFieldInput({ definition, value, error, onChange }: Props) {
    const inputId = `custom-field-${definition.key}`;
    const errorId = `${inputId}-error`;
    const ariaInvalid = error ? true : undefined;

    if (definition.type === 'textarea') {
        return (
            <div className="space-y-2">
                <Label htmlFor={inputId}>
                    {definition.label}
                    {definition.isRequired && <span className="ml-1 text-destructive">*</span>}
                </Label>
                <Textarea
                    id={inputId}
                    value={String(value ?? '')}
                    aria-invalid={ariaInvalid}
                    aria-describedby={error ? errorId : undefined}
                    onChange={(event) => onChange(event.target.value)}
                />
                {error && (
                    <p id={errorId} className="text-sm text-destructive">
                        {error}
                    </p>
                )}
            </div>
        );
    }

    if (definition.type === 'select') {
        return (
            <div className="space-y-2">
                <Label htmlFor={inputId}>
                    {definition.label}
                    {definition.isRequired && <span className="ml-1 text-destructive">*</span>}
                </Label>
                <Select
                    id={inputId}
                    value={String(value ?? '')}
                    aria-invalid={ariaInvalid}
                    aria-describedby={error ? errorId : undefined}
                    onChange={(event) => onChange(event.target.value)}
                >
                    <option value="">Selecciona una opción</option>
                    {definition.options.map((option) => (
                        <option key={option.value} value={option.value}>
                            {option.label}
                        </option>
                    ))}
                </Select>
                {error && (
                    <p id={errorId} className="text-sm text-destructive">
                        {error}
                    </p>
                )}
            </div>
        );
    }

    if (definition.type === 'multiselect') {
        const selected = Array.isArray(value) ? value : [];

        return (
            <fieldset className="space-y-2">
                <legend className="text-sm leading-none font-semibold text-foreground">
                    {definition.label}
                    {definition.isRequired && <span className="ml-1 text-destructive">*</span>}
                </legend>
                <div className="grid gap-2 rounded-lg border border-border p-3 sm:grid-cols-2">
                    {definition.options.map((option) => (
                        <label
                            key={option.value}
                            className="flex items-center gap-2 text-sm"
                            htmlFor={`${inputId}-${option.value}`}
                        >
                            <Checkbox
                                id={`${inputId}-${option.value}`}
                                checked={selected.includes(option.value)}
                                onCheckedChange={(checked) =>
                                    onChange(
                                        checked === true
                                            ? [...selected, option.value]
                                            : selected.filter((item) => item !== option.value),
                                    )
                                }
                            />
                            {option.label}
                        </label>
                    ))}
                </div>
                {error && (
                    <p id={errorId} className="text-sm text-destructive">
                        {error}
                    </p>
                )}
            </fieldset>
        );
    }

    if (definition.type === 'boolean') {
        return (
            <div className="space-y-2">
                <label className="flex items-center gap-2.5 text-sm font-semibold" htmlFor={inputId}>
                    <Checkbox
                        id={inputId}
                        checked={value === true}
                        onCheckedChange={(checked) => onChange(checked === true)}
                    />
                    {definition.label}
                </label>
                {error && (
                    <p id={errorId} className="text-sm text-destructive">
                        {error}
                    </p>
                )}
            </div>
        );
    }

    return (
        <div className="space-y-2">
            <Label htmlFor={inputId}>
                {definition.label}
                {definition.isRequired && <span className="ml-1 text-destructive">*</span>}
            </Label>
            <Input
                id={inputId}
                type={definition.type === 'number' ? 'number' : definition.type === 'date' ? 'date' : 'text'}
                value={String(value ?? '')}
                aria-invalid={ariaInvalid}
                aria-describedby={error ? errorId : undefined}
                onChange={(event) => onChange(event.target.value)}
            />
            {error && (
                <p id={errorId} className="text-sm text-destructive">
                    {error}
                </p>
            )}
        </div>
    );
}

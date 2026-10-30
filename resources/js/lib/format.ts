export function formatMoney(amount: string | number, currencyCode: string): string {
    return new Intl.NumberFormat('es', {
        style: 'currency',
        currency: currencyCode,
        maximumFractionDigits: 2,
    }).format(Number(amount));
}

export function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Intl.DateTimeFormat('es', { dateStyle: 'medium' }).format(new Date(value));
}

export function formatDateTime(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Intl.DateTimeFormat('es', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

export function dateInputValue(offsetDays = 0): string {
    const date = new Date();
    date.setDate(date.getDate() + offsetDays);

    return date.toLocaleDateString('en-CA');
}

export function dateTimeInputValue(value: string | null): string {
    if (!value) {
        return '';
    }

    const date = new Date(value);
    const pad = (part: number) => String(part).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

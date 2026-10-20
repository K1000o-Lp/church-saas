export function formatMoneyMinor(amountMinor: number, currencyCode: string): string {
    return new Intl.NumberFormat('es', {
        style: 'currency',
        currency: currencyCode,
        maximumFractionDigits: 2,
    }).format(amountMinor / 100);
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

import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Coins, Globe2, ShieldCheck } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select } from '@/components/ui/select';
import { AppLayout } from '@/layouts/app-layout';
import type { CountryOption, CurrencyOption } from '@/types';

type InitialPlan = {
    name: string;
    currencyLimit: number | null;
};

type Props = {
    countries: CountryOption[];
    currencies: CurrencyOption[];
    hasMemberships: boolean;
    initialPlan: InitialPlan;
};

function slugify(value: string): string {
    return value
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .slice(0, 80);
}

export default function ChurchOnboarding({
    countries,
    currencies,
    hasMemberships,
    initialPlan,
}: Props) {
    const initialCountry = countries[0];
    const [slugTouched, setSlugTouched] = useState(false);
    const [currencyTouched, setCurrencyTouched] = useState(false);

    const form = useForm({
        name: '',
        slug: '',
        country_code: initialCountry?.code ?? 'VE',
        timezone: initialCountry?.timezone ?? 'America/Caracas',
        currency_code: initialCountry?.currencyCode ?? 'USD',
        additional_currencies: [] as string[],
    });

    const selectedCountry = countries.find((country) => country.code === form.data.country_code);
    const selectedCurrency = currencies.find(
        (currency) => currency.code === form.data.currency_code,
    );
    const additionalCurrenciesError =
        form.errors.additional_currencies ?? form.errors['additional_currencies.0'];
    const maxAdditionalCurrencies =
        initialPlan.currencyLimit === null
            ? currencies.length - 1
            : Math.max(0, initialPlan.currencyLimit - 1);
    const canAddAdditionalCurrencies = maxAdditionalCurrencies > 0;

    function handleNameChange(value: string) {
        form.setData({
            ...form.data,
            name: value,
            slug: slugTouched ? form.data.slug : slugify(value),
        });
    }

    function handleSlugChange(value: string) {
        const nextSlug = slugify(value);

        setSlugTouched(nextSlug.length > 0);
        form.setData({ ...form.data, slug: nextSlug });
    }

    function handleCountryChange(value: string) {
        const country = countries.find((option) => option.code === value);

        if (!country) {
            return;
        }

        form.setData({
            ...form.data,
            country_code: country.code,
            timezone: country.timezone,
            currency_code: currencyTouched ? form.data.currency_code : country.currencyCode,
        });
    }

    function handlePrimaryCurrencyChange(value: string) {
        setCurrencyTouched(true);
        form.setData({
            ...form.data,
            currency_code: value,
            additional_currencies: form.data.additional_currencies.filter(
                (currency) => currency !== value,
            ),
        });
    }

    function toggleAdditionalCurrency(code: string, checked: boolean) {
        if (
            checked &&
            form.data.additional_currencies.length >= maxAdditionalCurrencies
        ) {
            return;
        }

        form.setData(
            'additional_currencies',
            checked
                ? [...form.data.additional_currencies, code]
                : form.data.additional_currencies.filter((currency) => currency !== code),
        );
    }

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post('/onboarding/church');
    }

    return (
        <AppLayout>
            <Head title={hasMemberships ? 'Crear iglesia' : 'Configura tu iglesia'} />

            <Button variant="ghost" size="sm" asChild>
                <Link href="/dashboard">
                    <ArrowLeft />
                    Mis iglesias
                </Link>
            </Button>

            <div className="mt-5 flex flex-col gap-2">
                <p className="eyebrow">Onboarding</p>
                <h1 className="font-display text-3xl font-semibold tracking-tight sm:text-4xl">
                    {hasMemberships ? 'Crea otra iglesia' : 'Configura tu iglesia'}
                </h1>
                <p className="max-w-2xl text-sm leading-relaxed text-muted-foreground sm:text-base">
                    {hasMemberships
                        ? 'La nueva iglesia tendrá un espacio de datos independiente. Quedarás como administrador de esta iglesia.'
                        : 'Completa los datos base. Al terminar serás administrador de la iglesia y podrás invitar a tu equipo cuando habilitemos las invitaciones.'}
                </p>
            </div>

            <form
                className="mt-8 grid gap-6 lg:grid-cols-[1.6fr_1fr]"
                onSubmit={submit}
                noValidate
            >
                <div className="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Identidad</CardTitle>
                            <CardDescription>
                                El nombre y la dirección identifican la iglesia dentro de la
                                plataforma.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-5">
                            <div className="space-y-2">
                                <Label htmlFor="name">Nombre de la iglesia</Label>
                                <Input
                                    id="name"
                                    name="name"
                                    value={form.data.name}
                                    autoComplete="organization"
                                    autoFocus
                                    required
                                    aria-invalid={form.errors.name ? true : undefined}
                                    aria-describedby={form.errors.name ? 'name-error' : undefined}
                                    onChange={(event) => handleNameChange(event.target.value)}
                                />
                                {form.errors.name && (
                                    <p id="name-error" className="text-sm text-destructive">
                                        {form.errors.name}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="slug">Dirección</Label>
                                <div className="flex items-center gap-2">
                                    <span
                                        aria-hidden="true"
                                        className="text-sm text-muted-foreground"
                                    >
                                        /churches/
                                    </span>
                                    <Input
                                        id="slug"
                                        name="slug"
                                        value={form.data.slug}
                                        autoComplete="off"
                                        spellCheck={false}
                                        required
                                        aria-invalid={form.errors.slug ? true : undefined}
                                        aria-describedby={
                                            form.errors.slug ? 'slug-error' : 'slug-help'
                                        }
                                        onChange={(event) => handleSlugChange(event.target.value)}
                                    />
                                </div>
                                <p id="slug-help" className="text-xs text-muted-foreground">
                                    Solo letras, números y guiones. Se genera desde el nombre y
                                    puedes ajustarla.
                                </p>
                                {form.errors.slug && (
                                    <p id="slug-error" className="text-sm text-destructive">
                                        {form.errors.slug}
                                    </p>
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Ubicación y operación</CardTitle>
                            <CardDescription>
                                País y zona horaria se usan para presentar fechas y organizar
                                servicios.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-5 sm:grid-cols-2">
                            <div className="space-y-2">
                                <Label htmlFor="country">País</Label>
                                <Select
                                    id="country"
                                    name="country_code"
                                    value={form.data.country_code}
                                    aria-invalid={form.errors.country_code ? true : undefined}
                                    onChange={(event) => handleCountryChange(event.target.value)}
                                >
                                    {countries.map((country) => (
                                        <option key={country.code} value={country.code}>
                                            {country.name}
                                        </option>
                                    ))}
                                </Select>
                                {form.errors.country_code && (
                                    <p className="text-sm text-destructive">
                                        {form.errors.country_code}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="timezone">Zona horaria</Label>
                                <Input
                                    id="timezone"
                                    name="timezone"
                                    value={form.data.timezone}
                                    autoComplete="off"
                                    spellCheck={false}
                                    required
                                    aria-invalid={form.errors.timezone ? true : undefined}
                                    aria-describedby="timezone-help"
                                    onChange={(event) =>
                                        form.setData('timezone', event.target.value)
                                    }
                                />
                                <p id="timezone-help" className="text-xs text-muted-foreground">
                                    Formato IANA, por ejemplo America/Caracas.
                                </p>
                                {form.errors.timezone && (
                                    <p className="text-sm text-destructive">
                                        {form.errors.timezone}
                                    </p>
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Monedas</CardTitle>
                            <CardDescription>
                                La moneda principal se registra como predeterminada. El plan{' '}
                                {initialPlan.name} permite{' '}
                                {initialPlan.currencyLimit === null
                                    ? 'monedas ilimitadas'
                                    : `${initialPlan.currencyLimit} moneda(s)`}
                                .
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-5">
                            <div className="space-y-2">
                                <Label htmlFor="currency">Moneda principal</Label>
                                <Select
                                    id="currency"
                                    name="currency_code"
                                    value={form.data.currency_code}
                                    aria-invalid={form.errors.currency_code ? true : undefined}
                                    onChange={(event) =>
                                        handlePrimaryCurrencyChange(event.target.value)
                                    }
                                >
                                    {currencies.map((currency) => (
                                        <option key={currency.code} value={currency.code}>
                                            {currency.code} · {currency.name}
                                        </option>
                                    ))}
                                </Select>
                                {form.errors.currency_code && (
                                    <p className="text-sm text-destructive">
                                        {form.errors.currency_code}
                                    </p>
                                )}
                            </div>

                            <fieldset className="space-y-2">
                                <legend className="text-sm font-semibold">Monedas adicionales</legend>
                                <p className="text-xs text-muted-foreground">
                                    {canAddAdditionalCurrencies
                                        ? `Opcional. Puedes agregar hasta ${maxAdditionalCurrencies} moneda(s) adicional(es).`
                                        : 'Tu plan inicial no incluye monedas adicionales. Podrás habilitarlas al cambiar de plan.'}
                                </p>
                                <div className="grid gap-2 sm:grid-cols-2">
                                    {currencies.map((currency) => {
                                        const isPrimary =
                                            form.data.currency_code === currency.code;
                                        const checked =
                                            form.data.additional_currencies.includes(
                                                currency.code,
                                            );

                                        return (
                                            <div
                                                key={currency.code}
                                                className="flex items-center gap-2.5 rounded-lg border border-border bg-background/60 px-3 py-2.5"
                                            >
                                                <Checkbox
                                                    id={`additional-${currency.code}`}
                                                    checked={checked}
                                                    disabled={
                                                        isPrimary ||
                                                        (!checked &&
                                                            !canAddAdditionalCurrencies)
                                                    }
                                                    onCheckedChange={(value) =>
                                                        toggleAdditionalCurrency(
                                                            currency.code,
                                                            value === true,
                                                        )
                                                    }
                                                />
                                                <Label
                                                    htmlFor={`additional-${currency.code}`}
                                                    className="font-normal"
                                                >
                                                    <span className="font-semibold">
                                                        {currency.code}
                                                    </span>
                                                    <span className="text-muted-foreground">
                                                        {' '}
                                                        · {currency.name}
                                                    </span>
                                                    {isPrimary && (
                                                        <span className="text-xs text-muted-foreground">
                                                            {' '}
                                                            (principal)
                                                        </span>
                                                    )}
                                                </Label>
                                            </div>
                                        );
                                    })}
                                </div>
                                {additionalCurrenciesError && (
                                    <p className="text-sm text-destructive">
                                        {additionalCurrenciesError}
                                    </p>
                                )}
                            </fieldset>
                        </CardContent>
                    </Card>
                </div>

                <aside className="lg:sticky lg:top-24 lg:self-start">
                    <Card className="bg-secondary/40">
                        <CardHeader>
                            <CardTitle>Resumen</CardTitle>
                            <CardDescription>
                                Revisa el contexto antes de crear la iglesia.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4 text-sm">
                            <div className="flex items-start gap-3">
                                <Globe2 className="mt-0.5 size-4 text-primary" />
                                <div>
                                    <p className="font-semibold">
                                        {selectedCountry?.name ?? 'País pendiente'}
                                    </p>
                                    <p className="text-muted-foreground">{form.data.timezone}</p>
                                </div>
                            </div>
                            <div className="flex items-start gap-3">
                                <Coins className="mt-0.5 size-4 text-primary" />
                                <div>
                                    <p className="font-semibold">
                                        {selectedCurrency
                                            ? `${selectedCurrency.code} · ${selectedCurrency.name}`
                                            : 'Moneda pendiente'}
                                    </p>
                                    <p className="text-muted-foreground">
                                        {form.data.additional_currencies.length > 0
                                            ? `Adicionales: ${form.data.additional_currencies.join(', ')}`
                                            : 'Sin monedas adicionales'}
                                    </p>
                                </div>
                            </div>
                            <div className="flex items-start gap-3">
                                <ShieldCheck className="mt-0.5 size-4 text-primary" />
                                <div>
                                    <p className="font-semibold">Administrador</p>
                                    <p className="text-muted-foreground">
                                        Tu membresía se crea con acceso completo a esta iglesia.
                                    </p>
                                </div>
                            </div>

                            <Button
                                type="submit"
                                size="lg"
                                className="w-full"
                                disabled={form.processing}
                            >
                                {form.processing ? 'Creando iglesia…' : 'Crear iglesia'}
                            </Button>
                            <p className="text-xs leading-relaxed text-muted-foreground">
                                La iglesia, tu membresía y las monedas se guardan en una sola
                                transacción. Si algo falla, no queda un registro parcial.
                            </p>
                        </CardContent>
                    </Card>
                </aside>
            </form>
        </AppLayout>
    );
}

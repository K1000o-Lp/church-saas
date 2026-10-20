import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowRight, LockKeyhole, ShieldCheck } from 'lucide-react';
import type { FormEvent } from 'react';

import { BrandMark } from '@/components/brand';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { PageProps } from '@/types';

export default function AdminLogin() {
    const { props } = usePage<PageProps>();
    const form = useForm({
        email: '',
        password: '',
        remember: false,
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post('/admin/login', {
            onFinish: () => form.reset('password'),
        });
    }

    return (
        <>
            <Head title="Acceso de plataforma" />

            <div className="grid min-h-screen lg:grid-cols-[1fr_1.1fr]">
                <section className="relative hidden overflow-hidden bg-primary px-12 py-10 text-primary-foreground lg:flex lg:flex-col lg:justify-between">
                    <div
                        aria-hidden="true"
                        className="pointer-events-none absolute -top-32 -right-24 size-[28rem] rounded-full border border-primary-foreground/10"
                    />
                    <div
                        aria-hidden="true"
                        className="pointer-events-none absolute -right-10 bottom-10 size-72 rounded-full bg-accent/15 blur-3xl"
                    />

                    <div className="relative flex items-center gap-3">
                        <BrandMark className="bg-primary-foreground/10 text-primary-foreground backdrop-blur" />
                        <div className="leading-none">
                            <p className="font-display text-base font-semibold">Church SaaS</p>
                            <p className="mt-1 text-[0.68rem] font-semibold tracking-[0.18em] text-primary-foreground/60 uppercase">
                                Plataforma
                            </p>
                        </div>
                    </div>

                    <div className="relative max-w-lg">
                        <p className="text-xs font-semibold tracking-[0.16em] text-accent uppercase">
                            Acceso exclusivo
                        </p>
                        <h1 className="mt-4 font-display text-5xl leading-[1.05] font-semibold text-balance">
                            Administración de tenants.
                        </h1>
                        <p className="mt-6 max-w-md text-base leading-relaxed text-primary-foreground/75">
                            Este espacio administra iglesias, estados y configuración base. No
                            expone personas, notas pastorales, contribuciones ni contenido de las
                            iglesias.
                        </p>
                    </div>

                    <div className="relative grid gap-3 text-sm text-primary-foreground/70">
                        <p className="flex items-center gap-2">
                            <ShieldCheck className="size-4 text-accent" />
                            Cuenta separada del acceso de iglesia
                        </p>
                        <p className="flex items-center gap-2">
                            <LockKeyhole className="size-4 text-accent" />
                            Cada acción administrativa queda auditada
                        </p>
                    </div>
                </section>

                <section className="flex min-h-screen flex-col px-5 py-8 sm:px-10">
                    <div className="lg:hidden">
                        <div className="flex items-center gap-3">
                            <BrandMark />
                            <span className="font-display text-base font-semibold">
                                Church SaaS · Plataforma
                            </span>
                        </div>
                    </div>

                    <div className="m-auto w-full max-w-sm py-12">
                        <p className="eyebrow">Backoffice</p>
                        <h1 className="mt-2 font-display text-3xl font-semibold">
                            Acceso de plataforma
                        </h1>
                        <p className="mt-2 text-sm text-muted-foreground">
                            Ingresa con tu cuenta exclusiva de administración.
                        </p>

                        {props.flash?.error && (
                            <div className="mt-5 rounded-lg border border-destructive/25 bg-destructive/10 px-4 py-3 text-sm font-medium text-destructive">
                                {props.flash.error}
                            </div>
                        )}

                        <form className="mt-8 space-y-5" onSubmit={submit} noValidate>
                            <div className="space-y-2">
                                <Label htmlFor="email">Correo de plataforma</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    value={form.data.email}
                                    autoComplete="username"
                                    autoFocus
                                    required
                                    aria-invalid={form.errors.email ? true : undefined}
                                    onChange={(event) => form.setData('email', event.target.value)}
                                />
                                {form.errors.email && (
                                    <p className="text-sm text-destructive">{form.errors.email}</p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="password">Contraseña</Label>
                                <Input
                                    id="password"
                                    type="password"
                                    name="password"
                                    value={form.data.password}
                                    autoComplete="current-password"
                                    required
                                    aria-invalid={form.errors.password ? true : undefined}
                                    onChange={(event) =>
                                        form.setData('password', event.target.value)
                                    }
                                />
                                {form.errors.password && (
                                    <p className="text-sm text-destructive">
                                        {form.errors.password}
                                    </p>
                                )}
                            </div>

                            <div className="flex items-center gap-2.5">
                                <Checkbox
                                    id="remember"
                                    checked={form.data.remember}
                                    onCheckedChange={(checked) =>
                                        form.setData('remember', checked === true)
                                    }
                                />
                                <Label
                                    htmlFor="remember"
                                    className="font-normal text-muted-foreground"
                                >
                                    Recordarme en este dispositivo
                                </Label>
                            </div>

                            <Button
                                type="submit"
                                size="lg"
                                className="w-full"
                                disabled={form.processing}
                            >
                                Entrar al backoffice
                                <ArrowRight />
                            </Button>
                        </form>

                        <p className="mt-8 border-t border-border pt-6 text-xs leading-relaxed text-muted-foreground">
                            Si eres miembro de una iglesia, usa el{' '}
                            <Link href="/login" className="font-semibold text-primary underline-offset-4 hover:underline">
                                acceso de iglesias
                            </Link>
                            . Las cuentas de plataforma no tienen membresías ni acceso a datos de
                            iglesia.
                        </p>
                    </div>
                </section>
            </div>
        </>
    );
}

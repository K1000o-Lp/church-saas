import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import type { FormEvent } from 'react';

import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { AuthLayout } from '@/layouts/auth-layout';
import type { PageProps } from '@/types';

export default function Login() {
    const { flash } = usePage<PageProps>().props;
    const form = useForm({
        email: '',
        password: '',
        remember: false,
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post('/login', {
            onFinish: () => form.reset('password'),
        });
    }

    return (
        <>
            <Head title="Iniciar sesión" />

            <AuthLayout
                eyebrow="Acceso"
                title="Iniciar sesión"
                description="Accede a la gestión de tus iglesias."
            >
                {flash.success && (
                    <p className="mt-6 rounded-lg border border-primary/25 bg-primary/10 px-3 py-2 text-sm font-medium text-primary">
                        {flash.success}
                    </p>
                )}
                {flash.error && (
                    <p className="mt-6 rounded-lg border border-destructive/25 bg-destructive/10 px-3 py-2 text-sm font-medium text-destructive">
                        {flash.error}
                    </p>
                )}

                <form className="mt-8 space-y-5" onSubmit={submit} noValidate>
                    <div className="space-y-2">
                        <Label htmlFor="email">Correo electrónico</Label>
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
                            onChange={(event) => form.setData('password', event.target.value)}
                        />
                        {form.errors.password && (
                            <p className="text-sm text-destructive">{form.errors.password}</p>
                        )}
                    </div>

                    <div className="flex items-center justify-between gap-3">
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
                        <Link
                            href="/forgot-password"
                            className="text-sm font-semibold text-primary hover:underline"
                        >
                            ¿Olvidaste tu contraseña?
                        </Link>
                    </div>

                    <Button
                        type="submit"
                        size="lg"
                        className="w-full"
                        disabled={form.processing}
                    >
                        Entrar
                        <ArrowRight />
                    </Button>
                </form>

                <p className="mt-6 text-sm text-muted-foreground">
                    ¿Aún no tienes cuenta?{' '}
                    <Link href="/register" className="font-semibold text-primary hover:underline">
                        Crear cuenta gratis
                    </Link>
                </p>

                <p className="mt-8 border-t border-border pt-6 text-xs leading-relaxed text-muted-foreground">
                    Crea una cuenta para configurar tu iglesia o acepta una invitación si ya
                    perteneces a un equipo. La verificación de acceso se repite en cada petición.
                </p>
            </AuthLayout>
        </>
    );
}

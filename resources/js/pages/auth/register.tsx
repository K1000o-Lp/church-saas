import { Head, Link, useForm } from '@inertiajs/react';
import { Sparkles } from 'lucide-react';
import type { FormEvent } from 'react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { AuthLayout } from '@/layouts/auth-layout';

export default function Register() {
    const form = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post('/register', {
            onFinish: () => form.reset('password', 'password_confirmation'),
        });
    }

    return (
        <>
            <Head title="Crear cuenta gratis" />

            <AuthLayout
                eyebrow="Registro"
                title="Crear cuenta gratis"
                description="Empieza a organizar tu iglesia en minutos. No necesitas tarjeta."
            >
                <div className="mt-6 flex items-start gap-3 rounded-lg border border-accent/30 bg-accent/10 px-3 py-2.5 text-sm">
                    <Sparkles className="mt-0.5 size-4 shrink-0 text-accent" />
                    <p>
                        Tu iglesia empieza en el plan <strong>Comunidad</strong>, gratis. Podrás
                        cambiarte a otro plan cuando lo necesites.
                    </p>
                </div>

                <form className="mt-8 space-y-5" onSubmit={submit} noValidate>
                    <div className="space-y-2">
                        <Label htmlFor="name">Nombre completo</Label>
                        <Input
                            id="name"
                            name="name"
                            value={form.data.name}
                            autoComplete="name"
                            autoFocus
                            required
                            aria-invalid={form.errors.name ? true : undefined}
                            onChange={(event) => form.setData('name', event.target.value)}
                        />
                        {form.errors.name && (
                            <p className="text-sm text-destructive">{form.errors.name}</p>
                        )}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="email">Correo electrónico</Label>
                        <Input
                            id="email"
                            type="email"
                            name="email"
                            value={form.data.email}
                            autoComplete="username"
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
                            autoComplete="new-password"
                            required
                            aria-invalid={form.errors.password ? true : undefined}
                            onChange={(event) => form.setData('password', event.target.value)}
                        />
                        {form.errors.password && (
                            <p className="text-sm text-destructive">{form.errors.password}</p>
                        )}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="password_confirmation">Confirmar contraseña</Label>
                        <Input
                            id="password_confirmation"
                            type="password"
                            name="password_confirmation"
                            value={form.data.password_confirmation}
                            autoComplete="new-password"
                            required
                            onChange={(event) =>
                                form.setData('password_confirmation', event.target.value)
                            }
                        />
                    </div>

                    <Button
                        type="submit"
                        size="lg"
                        className="w-full"
                        disabled={form.processing}
                    >
                        Crear cuenta gratis
                    </Button>
                </form>

                <p className="mt-6 text-sm text-muted-foreground">
                    ¿Ya tienes una cuenta?{' '}
                    <Link href="/login" className="font-semibold text-primary hover:underline">
                        Iniciar sesión
                    </Link>
                </p>

                <p className="mt-8 border-t border-border pt-6 text-xs leading-relaxed text-muted-foreground">
                    Al crear tu cuenta aceptas configurar tu iglesia con el correo indicado. Un
                    administrador podrá invitarte a otras iglesias después.
                </p>
            </AuthLayout>
        </>
    );
}

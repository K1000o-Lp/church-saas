import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, KeyRound } from 'lucide-react';
import type { FormEvent } from 'react';

import { Brand } from '@/components/brand';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Props = {
    token: string;
    email: string;
};

export default function ResetPassword({ token, email }: Props) {
    const form = useForm({
        token,
        email,
        password: '',
        password_confirmation: '',
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post('/reset-password', {
            onFinish: () => form.reset('password', 'password_confirmation'),
        });
    }

    return (
        <>
            <Head title="Nueva contraseña" />

            <div className="flex min-h-screen flex-col px-5 py-8 sm:px-10">
                <Brand />

                <div className="m-auto w-full max-w-sm py-12">
                    <Link
                        href="/login"
                        className="inline-flex items-center gap-2 text-sm font-semibold text-muted-foreground hover:text-foreground"
                    >
                        <ArrowLeft className="size-4" />
                        Volver al inicio de sesión
                    </Link>

                    <Card className="mt-6">
                        <CardHeader>
                            <span className="grid size-11 place-items-center rounded-xl bg-primary/10 text-primary">
                                <KeyRound className="size-5" />
                            </span>
                            <CardTitle className="mt-2">Crea una contraseña nueva</CardTitle>
                            <CardDescription>
                                Usa al menos 10 caracteres, con letras y números. Al guardar, se cerrarán las sesiones
                                abiertas de esta cuenta.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form className="space-y-5" onSubmit={submit} noValidate>
                                <div className="space-y-2">
                                    <Label htmlFor="email">Correo electrónico</Label>
                                    <Input
                                        id="email"
                                        type="email"
                                        autoComplete="username"
                                        required
                                        value={form.data.email}
                                        aria-invalid={form.errors.email ? true : undefined}
                                        onChange={(event) =>
                                            form.setData('email', event.target.value)
                                        }
                                    />
                                    {form.errors.email && (
                                        <p className="text-sm text-destructive">
                                            {form.errors.email}
                                        </p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="password">Nueva contraseña</Label>
                                    <Input
                                        id="password"
                                        type="password"
                                        autoComplete="new-password"
                                        autoFocus
                                        required
                                        value={form.data.password}
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

                                <div className="space-y-2">
                                    <Label htmlFor="password_confirmation">
                                        Confirmar contraseña
                                    </Label>
                                    <Input
                                        id="password_confirmation"
                                        type="password"
                                        autoComplete="new-password"
                                        required
                                        value={form.data.password_confirmation}
                                        onChange={(event) =>
                                            form.setData(
                                                'password_confirmation',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </div>

                                <Button
                                    type="submit"
                                    size="lg"
                                    className="w-full"
                                    disabled={form.processing}
                                >
                                    Guardar contraseña
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

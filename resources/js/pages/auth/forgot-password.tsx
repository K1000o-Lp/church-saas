import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, Mail } from 'lucide-react';
import type { FormEvent } from 'react';

import { Brand } from '@/components/brand';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { PageProps } from '@/types';

export default function ForgotPassword() {
    const { flash } = usePage<PageProps>().props;
    const form = useForm({ email: '' });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post('/forgot-password');
    }

    return (
        <>
            <Head title="Recuperar contraseña" />

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
                                <Mail className="size-5" />
                            </span>
                            <CardTitle className="mt-2">Recuperar contraseña</CardTitle>
                            <CardDescription>
                                Escribe el correo de tu cuenta y te enviaremos un enlace para
                                crear una contraseña nueva.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {flash.success && (
                                <p className="mb-4 rounded-lg border border-primary/25 bg-primary/10 px-3 py-2 text-sm font-medium text-primary">
                                    {flash.success}
                                </p>
                            )}

                            <form className="space-y-5" onSubmit={submit} noValidate>
                                <div className="space-y-2">
                                    <Label htmlFor="email">Correo electrónico</Label>
                                    <Input
                                        id="email"
                                        type="email"
                                        autoComplete="username"
                                        autoFocus
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

                                <Button
                                    type="submit"
                                    size="lg"
                                    className="w-full"
                                    disabled={form.processing}
                                >
                                    Enviar enlace
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

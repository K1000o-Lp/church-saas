import { Head, router, useForm, usePage } from '@inertiajs/react';
import { LogOut, MailCheck } from 'lucide-react';

import { Brand } from '@/components/brand';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import type { PageProps } from '@/types';

export default function VerifyEmail() {
    const { auth, flash } = usePage<PageProps>().props;
    const form = useForm({});

    function resend() {
        form.post('/email/verification-notification');
    }

    function logout() {
        router.post('/logout');
    }

    return (
        <>
            <Head title="Verifica tu correo" />

            <div className="flex min-h-screen flex-col px-5 py-8 sm:px-10">
                <Brand />

                <div className="m-auto w-full max-w-sm py-12">
                    <Card>
                        <CardHeader>
                            <span className="grid size-11 place-items-center rounded-xl bg-primary/10 text-primary">
                                <MailCheck className="size-5" />
                            </span>
                            <CardTitle className="mt-2">Verifica tu correo</CardTitle>
                            <CardDescription>
                                Enviamos un enlace a{' '}
                                <strong className="text-foreground">{auth.user?.email}</strong>.
                                Ábrelo para activar tu cuenta y configurar tu iglesia.
                            </CardDescription>
                        </CardHeader>

                        <CardContent className="space-y-4">
                            {flash.success && (
                                <p className="rounded-lg border border-primary/25 bg-primary/10 px-3 py-2 text-sm font-medium text-primary">
                                    {flash.success}
                                </p>
                            )}
                            {flash.error && (
                                <p className="rounded-lg border border-destructive/25 bg-destructive/10 px-3 py-2 text-sm font-medium text-destructive">
                                    {flash.error}
                                </p>
                            )}

                            <Button
                                type="button"
                                size="lg"
                                className="w-full"
                                disabled={form.processing}
                                onClick={resend}
                            >
                                Reenviar enlace de verificación
                            </Button>

                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                className="w-full"
                                onClick={logout}
                            >
                                <LogOut />
                                Cerrar sesión
                            </Button>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, Ban, LogOut, ShieldAlert } from 'lucide-react';

import { Brand } from '@/components/brand';
import { ThemeToggle } from '@/components/theme-toggle';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import type { ChurchStatusValue, PageProps } from '@/types';

type Props = {
    church: {
        name: string;
        slug: string;
    };
    status: ChurchStatusValue;
    statusLabel: string;
};

const messages: Record<ChurchStatusValue, string> = {
    active: 'La iglesia está disponible.',
    suspended:
        'El acceso a esta iglesia está suspendido temporalmente. Contacta al administrador de la plataforma para más información.',
    inactive:
        'Esta iglesia está inactiva. Sus datos se conservan, pero el acceso está bloqueado.',
    archived:
        'Esta iglesia está archivada. Sus datos se conservan para retención, pero el acceso está bloqueado.',
};

export default function ChurchUnavailable({ church, status, statusLabel }: Props) {
    const { props } = usePage<PageProps>();
    const user = props.auth.user;

    return (
        <>
            <Head title={statusLabel} />

            <div className="min-h-screen bg-background">
                <header className="border-b border-border bg-background/85 backdrop-blur">
                    <div className="mx-auto flex h-16 w-full max-w-5xl items-center justify-between gap-4 px-4 sm:px-6">
                        <Brand />
                        <div className="flex items-center gap-2">
                            <div className="hidden text-right sm:block">
                                <p className="text-sm leading-tight font-semibold">{user?.name}</p>
                                <p className="text-xs leading-tight text-muted-foreground">
                                    {user?.email}
                                </p>
                            </div>
                            <ThemeToggle />
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                onClick={() => router.post('/logout')}
                                aria-label="Cerrar sesión"
                                title="Cerrar sesión"
                            >
                                <LogOut />
                            </Button>
                        </div>
                    </div>
                </header>

                <main className="mx-auto flex min-h-[calc(100vh-4rem)] w-full max-w-5xl items-center px-4 py-12 sm:px-6">
                    <Card className="mx-auto w-full max-w-xl">
                        <CardContent className="flex flex-col items-center gap-5 px-6 py-10 text-center">
                            <span className="grid size-16 place-items-center rounded-full bg-accent/10 text-accent">
                                <ShieldAlert className="size-7" />
                            </span>

                            <div>
                                <p className="eyebrow">{church.name}</p>
                                <h1 className="mt-2 font-display text-3xl font-semibold">
                                    {statusLabel}
                                </h1>
                                <p className="mt-3 text-sm leading-relaxed text-muted-foreground">
                                    {messages[status]}
                                </p>
                            </div>

                            <div className="w-full rounded-lg border border-border bg-secondary/40 px-4 py-3 text-left text-xs leading-relaxed text-muted-foreground">
                                <p className="flex items-center gap-2 font-semibold text-foreground">
                                    <Ban className="size-4" />
                                    Acceso bloqueado
                                </p>
                                <p className="mt-1">
                                    Las sesiones activas pierden acceso en la siguiente petición.
                                    Los datos no se eliminan por este cambio de estado.
                                </p>
                            </div>

                            <div className="flex w-full flex-col gap-2 sm:flex-row">
                                <Button className="flex-1" asChild>
                                    <Link href="/dashboard">
                                        <ArrowLeft />
                                        Volver a mis iglesias
                                    </Link>
                                </Button>
                                <Button
                                    variant="outline"
                                    className="flex-1"
                                    onClick={() => router.post('/logout')}
                                >
                                    <LogOut />
                                    Cerrar sesión
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </main>
            </div>
        </>
    );
}

import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { CalendarClock, CheckCircle2, Mail, ShieldAlert, UserPlus } from 'lucide-react';
import type { FormEvent } from 'react';

import { Brand } from '@/components/brand';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { PageProps } from '@/types';

type Invitation = {
    churchName: string | null;
    churchSlug: string | null;
    email: string;
    role: string;
    status: string;
    expiresAt: string | null;
};

type Props = {
    token: string;
    invitation: Invitation;
    requiresRegistration: boolean;
    isAuthenticated: boolean;
};

const statusMessages: Record<string, { title: string; description: string }> = {
    accepted: {
        title: 'Invitación ya aceptada',
        description: 'Esta invitación ya fue utilizada. Inicia sesión para entrar a la iglesia.',
    },
    revoked: {
        title: 'Invitación revocada',
        description: 'Un administrador revocó esta invitación. Solicita una nueva si aún necesitas acceso.',
    },
    expired: {
        title: 'Invitación vencida',
        description: 'Esta invitación expiró. Solicita a un administrador que te envíe otra.',
    },
    church_unavailable: {
        title: 'Iglesia no disponible',
        description: 'La iglesia no está activa en este momento. Intenta más tarde o contacta a la administración.',
    },
};

export default function InvitationShow({
    token,
    invitation,
    requiresRegistration,
    isAuthenticated,
}: Props) {
    const { errors } = usePage<PageProps>().props;
    const form = useForm({
        name: '',
        password: '',
        password_confirmation: '',
    });

    const statusMessage = statusMessages[invitation.status];
    const isPending = invitation.status === 'pending' && !statusMessage;

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post(`/invitations/${token}`, {
            onFinish: () => form.reset('password', 'password_confirmation'),
        });
    }

    return (
        <>
            <Head title={invitation.churchName ? `Invitación a ${invitation.churchName}` : 'Invitación'} />

            <div className="flex min-h-screen flex-col px-5 py-8 sm:px-10">
                <Brand />

                <div className="m-auto w-full max-w-lg py-12">
                    <Card>
                        <CardHeader>
                            <span className="grid size-11 place-items-center rounded-xl bg-primary/10 text-primary">
                                {isPending ? (
                                    <Mail className="size-5" />
                                ) : (
                                    <ShieldAlert className="size-5" />
                                )}
                            </span>
                            <CardTitle className="mt-2">
                                {statusMessage?.title ??
                                    (invitation.churchName
                                        ? `Invitación a ${invitation.churchName}`
                                        : 'Invitación a una iglesia')}
                            </CardTitle>
                            <CardDescription>
                                {statusMessage?.description ??
                                    'Revisa los datos antes de aceptar. La invitación está vinculada a este correo electrónico.'}
                            </CardDescription>
                        </CardHeader>

                        <CardContent className="space-y-6">
                            <dl className="grid gap-3 rounded-lg border border-border bg-secondary/30 p-4 text-sm sm:grid-cols-2">
                                <div>
                                    <dt className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                        Correo
                                    </dt>
                                    <dd className="mt-1 break-all font-semibold">
                                        {invitation.email}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                        Rol
                                    </dt>
                                    <dd className="mt-1 font-semibold">{invitation.role}</dd>
                                </div>
                                <div className="sm:col-span-2">
                                    <dt className="flex items-center gap-1.5 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                        <CalendarClock className="size-3.5" />
                                        Vence
                                    </dt>
                                    <dd className="mt-1 font-semibold">
                                        {invitation.expiresAt
                                            ? new Intl.DateTimeFormat('es', {
                                                  dateStyle: 'long',
                                                  timeStyle: 'short',
                                              }).format(new Date(invitation.expiresAt))
                                            : 'Sin fecha'}
                                    </dd>
                                </div>
                            </dl>

                            {errors.invitation && (
                                <p className="rounded-lg border border-destructive/25 bg-destructive/10 px-3 py-2 text-sm font-medium text-destructive">
                                    {errors.invitation}
                                </p>
                            )}

                            {isPending && isAuthenticated && (
                                <form onSubmit={submit}>
                                    <Button
                                        type="submit"
                                        size="lg"
                                        className="w-full"
                                        disabled={form.processing}
                                    >
                                        <CheckCircle2 />
                                        Aceptar invitación
                                    </Button>
                                </form>
                            )}

                            {isPending && !isAuthenticated && requiresRegistration && (
                                <form className="space-y-5" onSubmit={submit} noValidate>
                                    <div className="space-y-2">
                                        <Label htmlFor="name">Tu nombre</Label>
                                        <Input
                                            id="name"
                                            autoComplete="name"
                                            autoFocus
                                            required
                                            value={form.data.name}
                                            aria-invalid={form.errors.name ? true : undefined}
                                            onChange={(event) =>
                                                form.setData('name', event.target.value)
                                            }
                                        />
                                        {form.errors.name && (
                                            <p className="text-sm text-destructive">
                                                {form.errors.name}
                                            </p>
                                        )}
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="password">Crea una contraseña</Label>
                                        <Input
                                            id="password"
                                            type="password"
                                            autoComplete="new-password"
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
                                        <p className="text-xs text-muted-foreground">
                                            Mínimo 10 caracteres, con letras y números.
                                        </p>
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
                                        <UserPlus />
                                        Crear cuenta y aceptar
                                    </Button>
                                    <p className="text-xs leading-relaxed text-muted-foreground">
                                        Al crear tu cuenta aceptas unirte a esta iglesia con el rol
                                        indicado. Un administrador podrá cambiarlo después.
                                    </p>
                                </form>
                            )}

                            {isPending && !isAuthenticated && !requiresRegistration && (
                                <Button asChild size="lg" className="w-full">
                                    <Link href="/login">Iniciar sesión para aceptar</Link>
                                </Button>
                            )}

                            {!isPending && (
                                <Button asChild variant="outline" className="w-full">
                                    <Link href="/login">Ir al inicio de sesión</Link>
                                </Button>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

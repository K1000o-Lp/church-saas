import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, MailPlus, RotateCw, Trash2, UserMinus, Users } from 'lucide-react';
import type { FormEvent } from 'react';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select } from '@/components/ui/select';
import { AppLayout } from '@/layouts/app-layout';
import { formatDate } from '@/lib/format';
import type {
    ChurchInvitationSummary,
    ChurchMemberSummary,
    PageProps,
    RoleOption,
} from '@/types';

type Props = {
    church: {
        name: string;
        slug: string;
    };
    members: ChurchMemberSummary[];
    invitations: ChurchInvitationSummary[];
    roles: RoleOption[];
    limits: {
        used: number;
        limit: number | null;
    };
};

const statusLabels: Record<string, string> = {
    active: 'Activo',
    suspended: 'Suspendido',
    invited: 'Invitado',
};

export default function ChurchMembers({ church, members, invitations, roles, limits }: Props) {
    const { errors } = usePage<PageProps>().props;
    const form = useForm({ email: '', role: 'member' });
    const churchPath = `/churches/${church.slug}`;

    function submitInvitation(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post(`${churchPath}/invitations`, {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    }

    function updateRole(membershipId: number, role: string) {
        router.put(
            `${churchPath}/members/${membershipId}`,
            { role },
            { preserveScroll: true },
        );
    }

    function removeMember(membershipId: number, name: string | null) {
        if (!window.confirm(`¿Eliminar a ${name ?? 'este miembro'} de la iglesia?`)) {
            return;
        }

        router.delete(`${churchPath}/members/${membershipId}`, { preserveScroll: true });
    }

    function resendInvitation(invitationId: number) {
        router.post(
            `${churchPath}/invitations/${invitationId}/resend`,
            {},
            { preserveScroll: true },
        );
    }

    function revokeInvitation(invitationId: number) {
        if (!window.confirm('¿Revocar esta invitación?')) {
            return;
        }

        router.delete(`${churchPath}/invitations/${invitationId}`, { preserveScroll: true });
    }

    return (
        <AppLayout>
            <Head title={`Miembros · ${church.name}`} />

            <Button variant="ghost" size="sm" asChild>
                <Link href={churchPath}>
                    <ArrowLeft />
                    Volver a la iglesia
                </Link>
            </Button>

            <div className="mt-5 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <p className="eyebrow">Configuración</p>
                    <h1 className="mt-2 font-display text-3xl font-semibold">Miembros y acceso</h1>
                    <p className="mt-2 max-w-2xl text-sm leading-relaxed text-muted-foreground">
                        Administra quién puede entrar a {church.name}. Los cambios de rol aplican
                        en la siguiente petición del usuario.
                    </p>
                </div>
                <span className="rounded-full border border-border bg-card px-3 py-1 text-xs font-semibold text-muted-foreground">
                    {limits.used} de {limits.limit ?? '∞'} usuarios activos
                </span>
            </div>

            <Card className="mt-6">
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <MailPlus className="size-5 text-primary" />
                        Invitar a una persona
                    </CardTitle>
                    <CardDescription>
                        La invitación vence en 7 días y solo puede usarse con el correo indicado.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <form
                        className="grid gap-4 sm:grid-cols-[1fr_12rem_auto] sm:items-end"
                        onSubmit={submitInvitation}
                        noValidate
                    >
                        <div className="space-y-2">
                            <Label htmlFor="invite-email">Correo electrónico</Label>
                            <Input
                                id="invite-email"
                                type="email"
                                value={form.data.email}
                                required
                                aria-invalid={form.errors.email ? true : undefined}
                                onChange={(event) => form.setData('email', event.target.value)}
                            />
                            {form.errors.email && (
                                <p className="text-sm text-destructive">{form.errors.email}</p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="invite-role">Rol</Label>
                            <Select
                                id="invite-role"
                                value={form.data.role}
                                onChange={(event) => form.setData('role', event.target.value)}
                            >
                                {roles.map((role) => (
                                    <option key={role.value} value={role.value}>
                                        {role.label}
                                    </option>
                                ))}
                            </Select>
                        </div>

                        <Button type="submit" disabled={form.processing}>
                            Enviar invitación
                        </Button>
                    </form>
                </CardContent>
            </Card>

            <Card className="mt-6">
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <Users className="size-5 text-primary" />
                        Miembros
                    </CardTitle>
                    <CardDescription>
                        La iglesia debe conservar al menos un administrador activo.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    {errors.role && (
                        <p className="mb-4 rounded-lg border border-destructive/25 bg-destructive/10 px-3 py-2 text-sm text-destructive">
                            {errors.role}
                        </p>
                    )}
                    {errors.member && (
                        <p className="mb-4 rounded-lg border border-destructive/25 bg-destructive/10 px-3 py-2 text-sm text-destructive">
                            {errors.member}
                        </p>
                    )}

                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[44rem] text-sm">
                            <thead>
                                <tr className="border-b border-border text-left text-xs tracking-wide text-muted-foreground uppercase">
                                    <th className="pb-3 pr-4 font-semibold">Persona</th>
                                    <th className="pb-3 pr-4 font-semibold">Estado</th>
                                    <th className="pb-3 pr-4 font-semibold">Rol</th>
                                    <th className="pb-3 font-semibold">Acciones</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {members.map((member) => (
                                    <tr key={member.id}>
                                        <td className="py-3 pr-4">
                                            <p className="font-semibold">
                                                {member.name}
                                                {member.isCurrentUser && (
                                                    <span className="ml-2 text-xs font-normal text-muted-foreground">
                                                        Tú
                                                    </span>
                                                )}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {member.email}
                                            </p>
                                        </td>
                                        <td className="py-3 pr-4">
                                            <span className="rounded-full border border-border bg-secondary/60 px-2 py-0.5 text-xs font-semibold">
                                                {statusLabels[member.status] ?? member.status}
                                            </span>
                                        </td>
                                        <td className="py-3 pr-4">
                                            <Select
                                                aria-label={`Rol de ${member.name ?? member.email}`}
                                                value={member.role}
                                                onChange={(event) =>
                                                    updateRole(member.id, event.target.value)
                                                }
                                            >
                                                {roles.map((role) => (
                                                    <option key={role.value} value={role.value}>
                                                        {role.label}
                                                    </option>
                                                ))}
                                            </Select>
                                        </td>
                                        <td className="py-3">
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                disabled={member.isCurrentUser}
                                                title={
                                                    member.isCurrentUser
                                                        ? 'No puedes eliminar tu propia membresía'
                                                        : 'Eliminar miembro'
                                                }
                                                onClick={() => removeMember(member.id, member.name)}
                                            >
                                                <UserMinus />
                                                Eliminar
                                            </Button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>

            <Card className="mt-6">
                <CardHeader>
                    <CardTitle>Invitaciones</CardTitle>
                    <CardDescription>
                        Pendientes y vencidas recientes. Puedes reenviar o revocar una invitación.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    {invitations.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            No hay invitaciones pendientes.
                        </p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[40rem] text-sm">
                                <thead>
                                    <tr className="border-b border-border text-left text-xs tracking-wide text-muted-foreground uppercase">
                                        <th className="pb-3 pr-4 font-semibold">Correo</th>
                                        <th className="pb-3 pr-4 font-semibold">Rol</th>
                                        <th className="pb-3 pr-4 font-semibold">Vence</th>
                                        <th className="pb-3 font-semibold">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {invitations.map((invitation) => (
                                        <tr key={invitation.id}>
                                            <td className="py-3 pr-4 font-semibold">
                                                {invitation.email}
                                            </td>
                                            <td className="py-3 pr-4">{invitation.roleLabel}</td>
                                            <td className="py-3 pr-4 text-muted-foreground">
                                                {invitation.expired
                                                    ? 'Vencida'
                                                    : formatDate(invitation.expiresAt)}
                                            </td>
                                            <td className="py-3">
                                                {invitation.expired ? (
                                                    <span className="text-xs font-semibold text-muted-foreground">
                                                        Vencida
                                                    </span>
                                                ) : (
                                                    <div className="flex flex-wrap gap-2">
                                                        <Button
                                                            type="button"
                                                            variant="outline"
                                                            size="sm"
                                                            onClick={() =>
                                                                resendInvitation(invitation.id)
                                                            }
                                                        >
                                                            <RotateCw />
                                                            Reenviar
                                                        </Button>
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() =>
                                                                revokeInvitation(invitation.id)
                                                            }
                                                        >
                                                            <Trash2 />
                                                            Revocar
                                                        </Button>
                                                    </div>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                    {errors.invitation && (
                        <p className="mt-4 text-sm text-destructive">{errors.invitation}</p>
                    )}
                    <p className="mt-4 text-xs text-muted-foreground">
                        Los reenvíos también fallan si la invitación ya venció o fue revocada.
                    </p>
                </CardContent>
            </Card>

        </AppLayout>
    );
}

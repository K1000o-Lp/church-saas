import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, History } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { AppLayout } from '@/layouts/app-layout';
import { formatDateTime } from '@/lib/format';
import type { Pagination } from '@/types';

type AuditEntry = {
    id: number;
    action: string;
    actor: string | null;
    actorEmail: string | null;
    actorRole: string | null;
    subjectType: string | null;
    subjectId: number | null;
    metadata: Record<string, unknown>;
    createdAt: string | null;
};

type Props = {
    church: {
        name: string;
        slug: string;
    };
    entries: AuditEntry[];
    pagination: Pagination;
};

const actionLabels: Record<string, string> = {
    'person.created': 'Persona creada',
    'person.updated': 'Persona actualizada',
    'person.archived': 'Persona archivada',
    'person.restored': 'Persona restaurada',
    'custom_field.created': 'Campo creado',
    'custom_field.updated': 'Campo actualizado',
    'custom_field.deactivated': 'Campo desactivado',
    'membership.role_changed': 'Rol de miembro actualizado',
    'membership.removed': 'Miembro eliminado',
    'invitation.created': 'Invitación creada',
    'invitation.revoked': 'Invitación revocada',
    'invitation.accepted': 'Invitación aceptada',
    'plan.changed': 'Plan cambiado',
};

const subjectLabels: Record<string, string> = {
    'App\\Models\\Person': 'Persona',
    'App\\Models\\ChurchInvitation': 'Invitación',
    'App\\Models\\CustomFieldDefinition': 'Campo personalizado',
    'App\\Models\\ChurchMembership': 'Membresía',
    'App\\Models\\ChurchSubscription': 'Suscripción',
};

export default function ChurchAudit({ church, entries, pagination }: Props) {
    const churchPath = `/churches/${church.slug}`;

    return (
        <AppLayout>
            <Head title={`Auditoría · ${church.name}`} />

            <Button variant="ghost" size="sm" asChild>
                <Link href={churchPath}>
                    <ArrowLeft />
                    Volver a la iglesia
                </Link>
            </Button>

            <div className="mt-5">
                <p className="eyebrow">Configuración</p>
                <h1 className="mt-2 font-display text-3xl font-semibold">Auditoría</h1>
                <p className="mt-2 max-w-2xl text-sm leading-relaxed text-muted-foreground">
                    Acciones sensibles registradas para {church.name}. Los metadatos se guardan
                    con límites y sin secretos, contraseñas ni notas pastorales.
                </p>
            </div>

            <Card className="mt-6">
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <History className="size-5 text-primary" />
                        Actividad reciente
                    </CardTitle>
                    <CardDescription>
                        {pagination.total} registro(s). Se muestran los más recientes primero.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    {entries.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            Todavía no hay acciones auditadas.
                        </p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[52rem] text-sm">
                                <thead>
                                    <tr className="border-b border-border text-left text-xs tracking-wide text-muted-foreground uppercase">
                                        <th className="pb-3 pr-4 font-semibold">Fecha</th>
                                        <th className="pb-3 pr-4 font-semibold">Acción</th>
                                        <th className="pb-3 pr-4 font-semibold">Actor</th>
                                        <th className="pb-3 pr-4 font-semibold">Objeto</th>
                                        <th className="pb-3 font-semibold">Detalle</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {entries.map((entry) => (
                                        <tr key={entry.id} className="align-top">
                                            <td className="py-3 pr-4 whitespace-nowrap text-muted-foreground">
                                                {formatDateTime(entry.createdAt)}
                                            </td>
                                            <td className="py-3 pr-4 font-semibold">
                                                {actionLabels[entry.action] ?? entry.action}
                                            </td>
                                            <td className="py-3 pr-4">
                                                <p>{entry.actor ?? 'Sistema'}</p>
                                                <p className="text-xs text-muted-foreground">
                                                    {entry.actorEmail ?? entry.actorRole ?? '—'}
                                                </p>
                                            </td>
                                            <td className="py-3 pr-4">
                                                <p>
                                                    {entry.subjectType
                                                        ? subjectLabels[entry.subjectType] ??
                                                          entry.subjectType
                                                        : '—'}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {entry.subjectId ?? '—'}
                                                </p>
                                            </td>
                                            <td className="py-3">
                                                {Object.keys(entry.metadata).length === 0 ? (
                                                    <span className="text-muted-foreground">—</span>
                                                ) : (
                                                    <details>
                                                        <summary className="cursor-pointer text-xs font-semibold text-primary">
                                                            Ver metadatos
                                                        </summary>
                                                        <pre className="mt-2 max-w-md overflow-x-auto rounded-md bg-secondary/50 p-2 text-xs">
                                                            {JSON.stringify(entry.metadata, null, 2)}
                                                        </pre>
                                                    </details>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}

                    {pagination.lastPage > 1 && (
                        <div className="mt-5 flex items-center justify-between gap-3 text-sm">
                            <p className="text-muted-foreground">
                                Página {pagination.currentPage} de {pagination.lastPage}
                            </p>
                            <div className="flex gap-2">
                                {pagination.previousPageUrl && (
                                    <Button variant="outline" size="sm" asChild>
                                        <Link href={pagination.previousPageUrl}>Anterior</Link>
                                    </Button>
                                )}
                                {pagination.nextPageUrl && (
                                    <Button variant="outline" size="sm" asChild>
                                        <Link href={pagination.nextPageUrl}>Siguiente</Link>
                                    </Button>
                                )}
                            </div>
                        </div>
                    )}
                </CardContent>
            </Card>
        </AppLayout>
    );
}

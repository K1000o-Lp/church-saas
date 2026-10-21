import { Link } from '@inertiajs/react';
import { UserRound } from 'lucide-react';
import type { ReactNode } from 'react';

import { Card, CardContent } from '@/components/ui/card';
import { formatDate } from '@/lib/format';
import type { PersonSummary } from '@/types';

const statusLabels: Record<string, string> = {
    active: 'Activa',
    inactive: 'Inactiva',
    archived: 'Archivada',
};

type Props = {
    churchPath: string;
    people: PersonSummary[];
    emptyMessage: string;
    renderAction?: (person: PersonSummary) => ReactNode;
};

export function PersonTable({ churchPath, people, emptyMessage, renderAction }: Props) {
    if (people.length === 0) {
        return (
            <Card>
                <CardContent className="grid place-items-center gap-2 py-10 text-center">
                    <span className="grid size-11 place-items-center rounded-full bg-secondary text-muted-foreground">
                        <UserRound className="size-5" />
                    </span>
                    <p className="text-sm text-muted-foreground">{emptyMessage}</p>
                </CardContent>
            </Card>
        );
    }

    return (
        <div className="overflow-x-auto rounded-xl border border-border bg-card">
            <table className="w-full min-w-[42rem] text-sm">
                <thead>
                    <tr className="border-b border-border text-left text-xs tracking-wide text-muted-foreground uppercase">
                        <th className="px-4 py-3 font-semibold">Persona</th>
                        <th className="px-4 py-3 font-semibold">Contacto</th>
                        <th className="px-4 py-3 font-semibold">Estado</th>
                        <th className="px-4 py-3 font-semibold">Registro</th>
                        {renderAction && <th className="px-4 py-3 font-semibold">Acciones</th>}
                    </tr>
                </thead>
                <tbody className="divide-y divide-border">
                    {people.map((person) => (
                        <tr key={person.id}>
                            <td className="px-4 py-3">
                                <Link
                                    href={`${churchPath}/people/${person.id}`}
                                    className="font-semibold text-foreground hover:text-primary"
                                >
                                    {person.fullName}
                                </Link>
                            </td>
                            <td className="px-4 py-3 text-muted-foreground">
                                <p>{person.email ?? 'Sin correo'}</p>
                                <p className="text-xs">{person.phone ?? 'Sin teléfono'}</p>
                            </td>
                            <td className="px-4 py-3">
                                <span
                                    className={
                                        person.status === 'archived'
                                            ? 'rounded-full border border-destructive/30 bg-destructive/10 px-2 py-0.5 text-xs font-semibold text-destructive'
                                            : 'rounded-full border border-border bg-secondary/60 px-2 py-0.5 text-xs font-semibold'
                                    }
                                >
                                    {statusLabels[person.status] ?? person.status}
                                </span>
                            </td>
                            <td className="px-4 py-3 text-muted-foreground">
                                {formatDate(person.createdAt ?? null)}
                            </td>
                            {renderAction && (
                                <td className="px-4 py-3">
                                    <div className="flex gap-2">{renderAction(person)}</div>
                                </td>
                            )}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

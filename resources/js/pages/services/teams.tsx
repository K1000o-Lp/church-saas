import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Plus, Users } from 'lucide-react';
import type { FormEvent } from 'react';

import { TeamRow } from '@/components/services/team-row';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { AppLayout } from '@/layouts/app-layout';
import type { MinistryTeamSummary } from '@/types';

type Props = {
    church: { name: string; slug: string };
    teams: MinistryTeamSummary[];
    canManage: boolean;
};

export default function ServiceTeams({ church, teams, canManage }: Props) {
    const churchPath = `/churches/${church.slug}`;
    const form = useForm({ name: '', description: '', is_active: true });
    const errors = form.errors as Record<string, string>;

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post(`${churchPath}/services/teams`, {
            preserveScroll: true,
            onSuccess: () => form.reset('name', 'description'),
        });
    }

    return (
        <AppLayout>
            <Head title={`Equipos · ${church.name}`} />
            <Button variant="ghost" size="sm" asChild>
                <Link href={`${churchPath}/services`}>
                    <ArrowLeft />
                    Volver a servicios
                </Link>
            </Button>

            <div className="mt-5">
                <p className="eyebrow">Servicios</p>
                <h1 className="mt-2 flex items-center gap-2 font-display text-3xl font-semibold">
                    <Users className="size-7 text-accent" />
                    Equipos
                </h1>
            </div>

            {canManage && (
                <Card className="mt-6">
                    <CardHeader>
                        <CardTitle>Nuevo equipo</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form
                            className="grid gap-4 sm:grid-cols-[1fr_1.4fr_auto] sm:items-end"
                            onSubmit={submit}
                            noValidate
                        >
                            <div className="space-y-2">
                                <Label htmlFor="name">Nombre</Label>
                                <Input
                                    id="name"
                                    value={form.data.name}
                                    onChange={(event) => form.setData('name', event.target.value)}
                                />
                                {errors.name && (
                                    <p className="text-sm text-destructive">{errors.name}</p>
                                )}
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="description">Descripción</Label>
                                <Input
                                    id="description"
                                    value={form.data.description}
                                    onChange={(event) =>
                                        form.setData('description', event.target.value)
                                    }
                                />
                            </div>
                            <Button type="submit" disabled={form.processing}>
                                <Plus />
                                Agregar
                            </Button>
                        </form>
                    </CardContent>
                </Card>
            )}

            <div className="mt-6 space-y-3">
                {teams.length === 0 ? (
                    <Card>
                        <CardContent className="py-10 text-center text-sm text-muted-foreground">
                            Todavía no hay equipos.
                        </CardContent>
                    </Card>
                ) : (
                    teams.map((team) => (
                        <TeamRow
                            key={team.id}
                            churchSlug={church.slug}
                            team={team}
                            canManage={canManage}
                        />
                    ))
                )}
            </div>
        </AppLayout>
    );
}

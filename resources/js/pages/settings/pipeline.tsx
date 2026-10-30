import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Plus, Target } from 'lucide-react';
import type { FormEvent } from 'react';

import { PipelineStageRow } from '@/components/pipeline/stage-row';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { AppLayout } from '@/layouts/app-layout';
import type { PipelineStageSummary } from '@/types';

type Props = {
    church: {
        name: string;
        slug: string;
    };
    stages: PipelineStageSummary[];
    canManage: boolean;
};

export default function PipelineSettings({ church, stages, canManage }: Props) {
    const churchPath = `/churches/${church.slug}`;
    const form = useForm({
        name: '',
        position: String(stages.length),
        is_active: true,
    });

    const errors = form.errors as Record<string, string>;

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post(`${churchPath}/settings/pipeline`, {
            preserveScroll: true,
            onSuccess: () => form.reset('name'),
        });
    }

    return (
        <AppLayout>
            <Head title={`Crecimiento · ${church.name}`} />

            <Button variant="ghost" size="sm" asChild>
                <Link href={churchPath}>
                    <ArrowLeft />
                    Volver a la iglesia
                </Link>
            </Button>

            <div className="mt-5">
                <p className="eyebrow">Discipulado</p>
                <h1 className="mt-2 flex items-center gap-2 font-display text-3xl font-semibold">
                    <Target className="size-7 text-accent" />
                    Etapas de crecimiento
                </h1>
                <p className="mt-2 max-w-2xl text-sm leading-relaxed text-muted-foreground">
                    Define el camino de discipulado de la iglesia. Cada etapa se marca por
                    persona desde su ficha.
                </p>
            </div>

            <Card className="mt-8">
                <CardHeader>
                    <CardTitle>Nueva etapa</CardTitle>
                    <CardDescription>
                        Ejemplos: Nuevo, Bautizado, Curso de membresía, Líder.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <form
                        className="grid gap-4 sm:grid-cols-[1fr_8rem_auto] sm:items-end"
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
                            <Label htmlFor="position">Orden</Label>
                            <Input
                                id="position"
                                type="number"
                                min={0}
                                value={form.data.position}
                                onChange={(event) =>
                                    form.setData('position', event.target.value)
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

            <div className="mt-6 space-y-3">
                {stages.length === 0 ? (
                    <Card>
                        <CardContent className="py-10 text-center text-sm text-muted-foreground">
                            Todavía no hay etapas configuradas.
                        </CardContent>
                    </Card>
                ) : (
                    stages.map((stage) => (
                        <PipelineStageRow
                            key={stage.id}
                            churchSlug={church.slug}
                            stage={stage}
                            canManage={canManage}
                        />
                    ))
                )}
            </div>
        </AppLayout>
    );
}

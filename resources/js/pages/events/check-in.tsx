import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, QrCode, ScanLine } from 'lucide-react';
import type { FormEvent } from 'react';

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
import { formatDateTime } from '@/lib/format';

type Props = {
    church: {
        name: string;
        slug: string;
    };
    event: {
        id: number;
        title: string;
        status: string;
        statusLabel: string;
        isPaid: boolean;
    };
    recentCheckIns: Array<{
        id: number;
        personName: string | null;
        recordedAt: string | null;
    }>;
};

export default function EventCheckIn({ church, event, recentCheckIns }: Props) {
    const churchPath = `/churches/${church.slug}`;
    const eventPath = `${churchPath}/events/${event.id}`;
    const form = useForm({ code: '' });
    const errors = form.errors as Record<string, string>;

    function submit(submitEvent: FormEvent<HTMLFormElement>) {
        submitEvent.preventDefault();
        form.post(`${eventPath}/check-in`, {
            preserveScroll: true,
            onSuccess: () => form.reset('code'),
        });
    }

    return (
        <AppLayout>
            <Head title={`Check-in · ${event.title}`} />

            <Button variant="ghost" size="sm" asChild>
                <Link href={eventPath}>
                    <ArrowLeft />
                    Volver al evento
                </Link>
            </Button>

            <div className="mt-5">
                <p className="eyebrow">Check-in</p>
                <h1 className="mt-2 flex items-center gap-2 font-display text-3xl font-semibold">
                    <ScanLine className="size-7 text-accent" />
                    {event.title}
                </h1>
                <p className="mt-2 text-sm text-muted-foreground">
                    Escanea el QR de la entrada o pega el enlace completo.
                </p>
            </div>

            <div className="mt-8 grid gap-6 lg:grid-cols-[1.2fr_1fr]">
                <Card>
                    <CardHeader>
                        <CardTitle>Registrar entrada</CardTitle>
                        <CardDescription>
                            Cada entrada válida marca la asistencia como presente.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form className="space-y-4" onSubmit={submit} noValidate>
                            <div className="space-y-2">
                                <Label htmlFor="code">Código o URL de la entrada</Label>
                                <Input
                                    id="code"
                                    autoFocus
                                    autoComplete="off"
                                    placeholder="Pega el enlace o el código"
                                    value={form.data.code}
                                    aria-invalid={errors.code ? true : undefined}
                                    onChange={(changeEvent) =>
                                        form.setData('code', changeEvent.target.value)
                                    }
                                />
                                {errors.code && (
                                    <p className="text-sm text-destructive">{errors.code}</p>
                                )}
                            </div>
                            <Button type="submit" disabled={form.processing}>
                                <QrCode />
                                {form.processing ? 'Registrando…' : 'Registrar check-in'}
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Últimos check-ins</CardTitle>
                        <CardDescription>Registros recientes de este evento.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {recentCheckIns.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Todavía no hay check-ins.
                            </p>
                        ) : (
                            <ul className="divide-y divide-border">
                                {recentCheckIns.map((entry) => (
                                    <li
                                        key={entry.id}
                                        className="flex items-center justify-between gap-3 py-2.5 first:pt-0 last:pb-0"
                                    >
                                        <span className="text-sm font-semibold">
                                            {entry.personName ?? 'Invitado'}
                                        </span>
                                        <span className="text-xs text-muted-foreground">
                                            {formatDateTime(entry.recordedAt)}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}

import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, ImagePlus, Palette, Save } from 'lucide-react';
import type { ChangeEvent, FormEvent } from 'react';

import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { AppLayout } from '@/layouts/app-layout';
import type { ChurchBrandingSettings } from '@/types';

type Props = {
    church: {
        name: string;
        slug: string;
    };
    branding: ChurchBrandingSettings;
};

export default function ChurchBranding({ church, branding }: Props) {
    const churchPath = `/churches/${church.slug}`;
    const form = useForm({
        tagline: branding.tagline ?? '',
        primary_color: branding.primaryColor ?? '#1f4d3a',
        accent_color: branding.accentColor ?? '#b8862f',
        service_times: branding.serviceTimes ?? '',
        logo: null as File | null,
        remove_logo: false,
    });

    const errors = form.errors as Record<string, string>;

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post(`${churchPath}/settings/branding`, {
            forceFormData: true,
            preserveScroll: true,
        });
    }

    function selectLogo(event: ChangeEvent<HTMLInputElement>) {
        form.setData('logo', event.target.files?.[0] ?? null);
    }

    return (
        <AppLayout>
            <Head title={`Marca · ${church.name}`} />

            <Button variant="ghost" size="sm" asChild>
                <Link href={churchPath}>
                    <ArrowLeft />
                    Volver a la iglesia
                </Link>
            </Button>

            <div className="mt-5">
                <p className="eyebrow">Configuración</p>
                <h1 className="mt-2 flex items-center gap-2 font-display text-3xl font-semibold">
                    <Palette className="size-7 text-accent" />
                    Marca de la iglesia
                </h1>
                <p className="mt-2 max-w-2xl text-sm leading-relaxed text-muted-foreground">
                    Personaliza el portal público con el logo y los colores de la iglesia.
                </p>
            </div>

            <form className="mt-8 max-w-3xl space-y-6" onSubmit={submit} noValidate>
                <Card>
                    <CardHeader>
                        <CardTitle>Identidad visual</CardTitle>
                        <CardDescription>
                            El logo se muestra en el portal público. Usa imágenes cuadradas o
                            horizontales con fondo transparente.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-5 sm:grid-cols-2">
                        <div className="space-y-2 sm:col-span-2">
                            <Label htmlFor="tagline">Lema o descripción breve</Label>
                            <Input
                                id="tagline"
                                value={form.data.tagline}
                                onChange={(event) =>
                                    form.setData('tagline', event.target.value)
                                }
                            />
                            {errors.tagline && (
                                <p className="text-sm text-destructive">{errors.tagline}</p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="primary_color">Color principal</Label>
                            <div className="flex items-center gap-2">
                                <Input
                                    id="primary_color"
                                    type="color"
                                    className="h-10 w-16 p-1"
                                    value={form.data.primary_color}
                                    onChange={(event) =>
                                        form.setData('primary_color', event.target.value)
                                    }
                                />
                                <Input
                                    value={form.data.primary_color}
                                    onChange={(event) =>
                                        form.setData('primary_color', event.target.value)
                                    }
                                />
                            </div>
                            {errors.primary_color && (
                                <p className="text-sm text-destructive">
                                    {errors.primary_color}
                                </p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="accent_color">Color de acento</Label>
                            <div className="flex items-center gap-2">
                                <Input
                                    id="accent_color"
                                    type="color"
                                    className="h-10 w-16 p-1"
                                    value={form.data.accent_color}
                                    onChange={(event) =>
                                        form.setData('accent_color', event.target.value)
                                    }
                                />
                                <Input
                                    value={form.data.accent_color}
                                    onChange={(event) =>
                                        form.setData('accent_color', event.target.value)
                                    }
                                />
                            </div>
                            {errors.accent_color && (
                                <p className="text-sm text-destructive">
                                    {errors.accent_color}
                                </p>
                            )}
                        </div>

                        <div className="space-y-2 sm:col-span-2">
                            <Label htmlFor="service_times">Horarios</Label>
                            <Textarea
                                id="service_times"
                                className="min-h-24"
                                placeholder={'Domingo 10:00 AM\nMiércoles 7:00 PM'}
                                value={form.data.service_times}
                                onChange={(event) =>
                                    form.setData('service_times', event.target.value)
                                }
                            />
                        </div>

                        <div className="space-y-3 sm:col-span-2">
                            <Label htmlFor="logo">Logo</Label>
                            {branding.logoUrl && (
                                <img
                                    src={branding.logoUrl}
                                    alt="Logo actual"
                                    className="h-16 w-auto max-w-40 rounded-lg border border-border bg-background object-contain p-2"
                                />
                            )}
                            <Input
                                id="logo"
                                type="file"
                                accept="image/png,image/jpeg,image/webp"
                                onChange={selectLogo}
                            />
                            {errors.logo && (
                                <p className="text-sm text-destructive">{errors.logo}</p>
                            )}
                            {branding.logoUrl && (
                                <div className="flex items-center gap-2">
                                    <Checkbox
                                        id="remove_logo"
                                        checked={form.data.remove_logo}
                                        onCheckedChange={(checked) =>
                                            form.setData('remove_logo', checked === true)
                                        }
                                    />
                                    <Label htmlFor="remove_logo">Quitar logo actual</Label>
                                </div>
                            )}
                        </div>
                    </CardContent>
                </Card>

                <Button type="submit" disabled={form.processing}>
                    {form.data.logo ? <ImagePlus /> : <Save />}
                    {form.processing ? 'Guardando…' : 'Guardar marca'}
                </Button>
            </form>
        </AppLayout>
    );
}

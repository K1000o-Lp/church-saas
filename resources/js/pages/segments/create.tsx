import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

import { SegmentForm } from '@/components/segments/segment-form';
import { Button } from '@/components/ui/button';
import { AppLayout } from '@/layouts/app-layout';
import type { SegmentField, SegmentTypeOption } from '@/types';

type Props = {
    church: {
        name: string;
        slug: string;
    };
    types: SegmentTypeOption[];
    fields: SegmentField[];
};

export default function SegmentCreate({ church, types, fields }: Props) {
    return (
        <AppLayout>
            <Head title={`Nuevo segmento · ${church.name}`} />

            <Button variant="ghost" size="sm" asChild>
                <Link href={`/churches/${church.slug}/segments`}>
                    <ArrowLeft />
                    Volver a segmentos
                </Link>
            </Button>

            <div className="mt-5">
                <p className="eyebrow">Segmentos</p>
                <h1 className="mt-2 font-display text-3xl font-semibold">Nuevo segmento</h1>
                <p className="mt-2 max-w-2xl text-sm leading-relaxed text-muted-foreground">
                    Define si las personas se agrupan por reglas automáticas o si eliges sus
                    miembros manualmente.
                </p>
            </div>

            <div className="mt-6">
                <SegmentForm mode="create" church={church} types={types} fields={fields} />
            </div>
        </AppLayout>
    );
}

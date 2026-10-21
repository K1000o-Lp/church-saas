import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

import { SegmentForm } from '@/components/segments/segment-form';
import { Button } from '@/components/ui/button';
import { AppLayout } from '@/layouts/app-layout';
import type {
    SegmentField,
    SegmentRuleSet,
    SegmentTypeOption,
    SegmentTypeValue,
} from '@/types';

type Props = {
    church: {
        name: string;
        slug: string;
    };
    segment: {
        id: number;
        name: string;
        description: string | null;
        type: SegmentTypeValue;
        typeLabel: string;
        rules: SegmentRuleSet | null;
    };
    types: SegmentTypeOption[];
    fields: SegmentField[];
};

export default function SegmentEdit({ church, segment, types, fields }: Props) {
    return (
        <AppLayout>
            <Head title={`Editar ${segment.name} · ${church.name}`} />

            <Button variant="ghost" size="sm" asChild>
                <Link href={`/churches/${church.slug}/segments/${segment.id}`}>
                    <ArrowLeft />
                    Volver al segmento
                </Link>
            </Button>

            <div className="mt-5">
                <p className="eyebrow">Segmentos</p>
                <h1 className="mt-2 font-display text-3xl font-semibold">Editar segmento</h1>
                <p className="mt-2 max-w-2xl text-sm leading-relaxed text-muted-foreground">
                    Cambia el nombre, la descripción o las reglas. El tipo se define al crear el
                    segmento.
                </p>
            </div>

            <div className="mt-6">
                <SegmentForm
                    mode="edit"
                    church={church}
                    segment={segment}
                    types={types}
                    fields={fields}
                />
            </div>
        </AppLayout>
    );
}

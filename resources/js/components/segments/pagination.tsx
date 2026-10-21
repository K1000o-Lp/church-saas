import { Link } from '@inertiajs/react';

import { Button } from '@/components/ui/button';
import type { Pagination as PaginationType } from '@/types';

type Props = {
    pagination: PaginationType;
    label: string;
};

export function SegmentPagination({ pagination, label }: Props) {
    if (pagination.lastPage <= 1) {
        return null;
    }

    return (
        <div className="mt-5 flex items-center justify-between gap-3 text-sm">
            <p className="text-muted-foreground">
                Página {pagination.currentPage} de {pagination.lastPage} · {pagination.total}{' '}
                {label}
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
    );
}

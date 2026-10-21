import { LockKeyhole, ShieldCheck } from 'lucide-react';
import type { ReactNode } from 'react';

import { Brand, BrandMark } from '@/components/brand';

type Props = {
    eyebrow: string;
    title: string;
    description: string;
    children: ReactNode;
};

export function AuthLayout({ eyebrow, title, description, children }: Props) {
    return (
        <div className="grid min-h-screen lg:grid-cols-[1.05fr_1fr]">
            <section className="relative hidden overflow-hidden bg-primary px-12 py-10 text-primary-foreground lg:flex lg:flex-col lg:justify-between">
                <div
                    aria-hidden="true"
                    className="pointer-events-none absolute -top-32 -right-24 size-[28rem] rounded-full border border-primary-foreground/10"
                />
                <div
                    aria-hidden="true"
                    className="pointer-events-none absolute -right-10 bottom-10 size-72 rounded-full bg-accent/15 blur-3xl"
                />
                <div
                    aria-hidden="true"
                    className="pointer-events-none absolute top-1/3 -left-24 size-64 rounded-full border border-accent/20"
                />

                <div className="relative flex items-center gap-3">
                    <BrandMark className="bg-primary-foreground/10 text-primary-foreground backdrop-blur" />
                    <div className="leading-none">
                        <p className="font-display text-base font-semibold">Church SaaS</p>
                        <p className="mt-1 text-[0.68rem] font-semibold tracking-[0.18em] text-primary-foreground/60 uppercase">
                            Gestión pastoral
                        </p>
                    </div>
                </div>

                <div className="relative max-w-lg">
                    <p className="text-xs font-semibold tracking-[0.16em] text-accent uppercase">
                        Administra con propósito
                    </p>
                    <h1 className="mt-4 font-display text-5xl leading-[1.05] font-semibold text-balance">
                        Una plataforma para cuidar mejor a tu iglesia.
                    </h1>
                    <p className="mt-6 max-w-md text-base leading-relaxed text-primary-foreground/75">
                        Personas, membresías, eventos, finanzas y seguimiento pastoral en un
                        solo lugar, con los datos de cada iglesia debidamente aislados.
                    </p>
                </div>

                <div className="relative grid gap-3 text-sm text-primary-foreground/70">
                    <p className="flex items-center gap-2">
                        <ShieldCheck className="size-4 text-accent" />
                        Acceso por membresía verificado en cada petición
                    </p>
                    <p className="flex items-center gap-2">
                        <LockKeyhole className="size-4 text-accent" />
                        Contexto de iglesia resuelto en el servidor
                    </p>
                </div>
            </section>

            <section className="flex min-h-screen flex-col px-5 py-8 sm:px-10">
                <div className="lg:hidden">
                    <Brand />
                </div>

                <div className="m-auto w-full max-w-sm py-12">
                    <p className="eyebrow">{eyebrow}</p>
                    <h1 className="mt-2 font-display text-3xl font-semibold">{title}</h1>
                    <p className="mt-2 text-sm text-muted-foreground">{description}</p>

                    {children}
                </div>
            </section>
        </div>
    );
}

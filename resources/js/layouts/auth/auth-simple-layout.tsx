import { Link } from '@inertiajs/react';
import {
    ArrowLeft,
    LockKeyhole,
    MessageCircle,
    ShieldCheck,
} from 'lucide-react';
import PublicBrand from '@/components/social/public-brand';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

export default function AuthSimpleLayout({
    children,
    title,
    description,
    eyebrow = 'Your community, your rules',
}: AuthLayoutProps) {
    return (
        <main className="min-h-svh bg-background text-foreground lg:grid lg:grid-cols-[minmax(27rem,0.92fr)_minmax(31rem,1.08fr)]">
            <section className="relative hidden min-h-svh overflow-hidden bg-[#091325] p-9 text-white lg:flex lg:flex-col xl:p-12">
                <div
                    className="pointer-events-none absolute inset-0 opacity-75"
                    style={{
                        backgroundImage:
                            'radial-gradient(circle at 18% 12%, rgba(58, 118, 255, .42), transparent 34%), radial-gradient(circle at 92% 80%, rgba(68, 224, 183, .17), transparent 35%)',
                    }}
                />
                <div className="pointer-events-none absolute inset-0 bg-[linear-gradient(rgba(255,255,255,.035)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,.035)_1px,transparent_1px)] [mask-image:linear-gradient(to_bottom,black,transparent_80%)] bg-[size:42px_42px]" />

                <PublicBrand inverse className="relative z-10 text-white" />

                <div className="relative z-10 my-auto py-12">
                    <p className="text-[0.68rem] font-extrabold tracking-[0.17em] text-[#72e4c0] uppercase">
                        Open-source social infrastructure
                    </p>
                    <h2 className="mt-5 max-w-xl text-[clamp(2.9rem,4.8vw,5rem)] leading-[0.92] font-black tracking-[-0.06em] text-balance">
                        A better place for people to belong.
                    </h2>
                    <p className="mt-6 max-w-lg text-base leading-7 text-white/64 xl:text-lg">
                        Calm conversations, member-owned identity, and the tools
                        communities need to grow without giving up control.
                    </p>

                    <div className="relative mt-10 max-w-[34rem] rounded-[1.8rem] border border-white/10 bg-white/[0.075] p-3 shadow-[0_36px_90px_-36px_rgba(0,0,0,.8)] backdrop-blur-xl xl:p-4">
                        <div className="flex items-center justify-between px-2 py-1.5">
                            <div className="flex items-center gap-2">
                                <span className="size-2 rounded-full bg-[#72e4c0]" />
                                <span className="text-[0.67rem] font-extrabold tracking-[0.14em] text-white/55 uppercase">
                                    Live community
                                </span>
                            </div>
                            <span className="rounded-full border border-white/10 px-2.5 py-1 text-[0.62rem] font-bold text-white/55">
                                chronological
                            </span>
                        </div>
                        <article className="mt-2 rounded-[1.25rem] bg-white p-4 text-[#101827] xl:p-5">
                            <div className="flex items-center gap-3">
                                <span className="flex size-10 items-center justify-center rounded-full bg-primary text-[0.68rem] font-black text-white">
                                    AM
                                </span>
                                <div>
                                    <p className="text-sm font-black tracking-[-0.02em]">
                                        Andrew Matia
                                    </p>
                                    <p className="text-[0.68rem] font-semibold text-slate-500">
                                        Makers Circle · now
                                    </p>
                                </div>
                            </div>
                            <p className="mt-4 text-[0.92rem] leading-6 text-slate-700">
                                What makes a community feel worth returning to?
                                Clear purpose, genuine people, and room to shape
                                what comes next.
                            </p>
                            <div className="mt-4 flex items-center gap-4 border-t border-slate-100 pt-3 text-[0.68rem] font-bold text-slate-500">
                                <span className="inline-flex items-center gap-1.5">
                                    <MessageCircle className="size-3.5" /> 12
                                    replies
                                </span>
                                <span className="inline-flex items-center gap-1.5">
                                    <ShieldCheck className="size-3.5 text-emerald-600" />
                                    Member-led
                                </span>
                            </div>
                        </article>
                    </div>
                </div>

                <div className="relative z-10 flex items-center gap-2 text-xs font-semibold text-white/50">
                    <LockKeyhole className="size-4 text-[#72e4c0]" />
                    Self-hosted · Privacy-first · GPL-3.0
                </div>
            </section>

            <section className="flex min-h-svh flex-col px-5 py-5 sm:px-8 sm:py-7 lg:px-12 xl:px-20">
                <div className="flex items-center justify-between lg:justify-end">
                    <PublicBrand className="lg:hidden" />
                    <Link
                        href={home()}
                        className="social-focus inline-flex min-h-10 items-center gap-2 rounded-full px-3 text-xs font-extrabold text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground sm:text-sm"
                    >
                        <ArrowLeft className="size-4" aria-hidden="true" />
                        Back to home
                    </Link>
                </div>

                <div className="mx-auto flex w-full max-w-[28rem] flex-1 flex-col justify-center py-10 sm:py-14">
                    <div className="mb-8">
                        <p className="text-[0.68rem] font-extrabold tracking-[0.16em] text-primary uppercase">
                            {eyebrow}
                        </p>
                        <h1 className="mt-3 text-4xl leading-[0.98] font-black tracking-[-0.055em] text-balance sm:text-[2.75rem]">
                            {title}
                        </h1>
                        {description && (
                            <p className="mt-4 max-w-md text-[0.95rem] leading-6 text-muted-foreground">
                                {description}
                            </p>
                        )}
                    </div>

                    <div>{children}</div>
                </div>

                <p className="mx-auto w-full max-w-[28rem] pb-2 text-center text-[0.68rem] leading-5 font-semibold text-muted-foreground sm:text-left">
                    Your account is protected with secure authentication and
                    rate-limited sign-in attempts.
                </p>
            </section>
        </main>
    );
}

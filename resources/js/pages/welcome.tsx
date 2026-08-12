import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    Blocks,
    CalendarDays,
    Camera,
    Check,
    ChevronRight,
    Globe2,
    Heart,
    MessageCircle,
    Play,
    Search,
    ShieldCheck,
    Sparkles,
    UsersRound,
} from 'lucide-react';
import PublicBrand from '@/components/social/public-brand';
import type { User } from '@/types';

type WelcomeProps = {
    auth: { user: User | null };
};

const foundations = [
    {
        icon: UsersRound,
        title: 'Identity people own',
        body: 'Rich profiles, follows, privacy controls, and portable API contracts are part of the core.',
        accent: 'bg-primary/[0.09] text-primary',
    },
    {
        icon: MessageCircle,
        title: 'Conversation with context',
        body: 'Chronological feeds, focused replies, media, polls, shares, and saved posts without an opaque ranking system.',
        accent: 'bg-mint/30 text-emerald-800 dark:text-mint',
    },
    {
        icon: ShieldCheck,
        title: 'Safety built in',
        body: 'Blocking, reporting, moderation queues, account appeals, and auditable operator tools ship together.',
        accent: 'bg-coral/15 text-orange-700 dark:text-coral',
    },
];

const directions = [
    {
        icon: Camera,
        label: 'Visual communities',
        detail: 'Media-led discovery',
    },
    { icon: Play, label: 'Creator networks', detail: 'Focused audiences' },
    { icon: Globe2, label: 'Local platforms', detail: 'People nearby' },
    { icon: Blocks, label: 'Niche products', detail: 'Your own model' },
];

const productSignals = [
    ['Laravel 13', 'Native foundation'],
    ['GPL-3.0', 'Open source'],
    ['Self-hosted', 'Your data'],
];

export default function Welcome() {
    const { auth } = usePage<WelcomeProps>().props;
    const primaryHref = auth.user ? '/feed' : '/register';

    return (
        <>
            <Head title="Open social infrastructure for Laravel">
                <meta
                    name="description"
                    content="Lineweb Social is a Laravel-native, self-hosted foundation for modern communities and social products."
                />
            </Head>

            <div className="min-h-screen overflow-hidden bg-background text-foreground">
                <header className="relative z-30 mx-auto flex max-w-[90rem] items-center justify-between px-5 py-5 sm:px-8 lg:px-12">
                    <PublicBrand />

                    <nav
                        className="hidden items-center gap-1 lg:flex"
                        aria-label="Main navigation"
                    >
                        <a
                            href="#platform"
                            className="social-focus rounded-full px-4 py-2.5 text-sm font-bold text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground"
                        >
                            Platform
                        </a>
                        <a
                            href="#principles"
                            className="social-focus rounded-full px-4 py-2.5 text-sm font-bold text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground"
                        >
                            Why open
                        </a>
                        <a
                            href="https://github.com/drewmt/lineweb-social"
                            className="social-focus rounded-full px-4 py-2.5 text-sm font-bold text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground"
                        >
                            GitHub
                        </a>
                    </nav>

                    <div className="flex items-center gap-1.5">
                        {!auth.user && (
                            <Link
                                href="/login"
                                className="social-focus hidden rounded-full px-4 py-2.5 text-sm font-extrabold transition-colors hover:bg-secondary sm:inline-flex"
                            >
                                Log in
                            </Link>
                        )}
                        <Link
                            href={primaryHref}
                            className="social-focus inline-flex min-h-11 items-center gap-2 rounded-full bg-foreground px-4.5 text-sm font-extrabold text-background transition-transform hover:-translate-y-0.5"
                        >
                            {auth.user ? 'Open feed' : 'Join now'}
                            <ArrowRight className="size-4" aria-hidden="true" />
                        </Link>
                    </div>
                </header>

                <main>
                    <section className="relative mx-auto grid max-w-[90rem] gap-12 px-5 pt-14 pb-20 sm:px-8 sm:pt-20 lg:grid-cols-[minmax(0,.9fr)_minmax(37rem,1.1fr)] lg:items-center lg:gap-9 lg:px-12 lg:pt-24 lg:pb-28">
                        <div className="pointer-events-none absolute -top-56 right-[-18rem] size-[52rem] rounded-full bg-primary/[0.075] blur-3xl" />
                        <div className="pointer-events-none absolute top-32 left-[-24rem] size-[34rem] rounded-full bg-mint/15 blur-3xl" />

                        <div className="relative z-10">
                            <p className="social-eyebrow">
                                <span className="size-1.5 rounded-full bg-primary" />
                                The open social foundation for Laravel
                            </p>
                            <h1 className="mt-5 max-w-3xl text-[clamp(3.55rem,6.4vw,6.9rem)] leading-[0.86] font-black tracking-[-0.075em] text-balance">
                                Own the network.
                                <span className="block text-primary">
                                    Shape the experience.
                                </span>
                            </h1>
                            <p className="mt-7 max-w-xl text-lg leading-8 text-muted-foreground sm:text-xl">
                                Launch a modern community or social startup on a
                                self-hosted core where identity, conversation,
                                privacy, and moderation already work together.
                            </p>
                            <div className="mt-8 flex flex-wrap gap-3">
                                <Link
                                    href={primaryHref}
                                    className="social-focus inline-flex min-h-13 items-center gap-2 rounded-full bg-primary px-6 text-sm font-extrabold text-primary-foreground shadow-[0_18px_38px_-20px_color-mix(in_oklab,var(--primary)_90%,transparent)] transition-transform hover:-translate-y-0.5"
                                >
                                    {auth.user
                                        ? 'Enter your community'
                                        : 'Start your community'}
                                    <ArrowRight
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                </Link>
                                <a
                                    href="#platform"
                                    className="social-focus inline-flex min-h-13 items-center gap-2 rounded-full border border-border bg-card px-6 text-sm font-extrabold transition-colors hover:bg-secondary"
                                >
                                    See what is inside
                                    <ChevronRight
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                </a>
                            </div>
                            <div className="mt-8 flex flex-wrap gap-x-5 gap-y-2 text-xs font-bold text-muted-foreground">
                                {[
                                    'Self-hosted',
                                    'Chronological',
                                    'Extensible',
                                ].map((item) => (
                                    <span
                                        key={item}
                                        className="inline-flex items-center gap-1.5"
                                    >
                                        <Check
                                            className="size-3.5 text-primary"
                                            strokeWidth={3}
                                            aria-hidden="true"
                                        />
                                        {item}
                                    </span>
                                ))}
                            </div>
                        </div>

                        <div className="relative z-10 mx-auto w-full max-w-[46rem] lg:mr-0">
                            <div className="absolute -inset-10 -z-10 rounded-[5rem] bg-[radial-gradient(circle_at_48%_46%,oklch(0.86_0.11_250_/_0.36),transparent_66%)]" />
                            <div className="relative overflow-hidden rounded-[2rem] border border-border/85 bg-card shadow-[0_40px_100px_-48px_rgba(15,23,42,.65)]">
                                <div className="flex h-11 items-center gap-1.5 border-b border-border/70 px-4">
                                    <span className="size-2.5 rounded-full bg-coral/85" />
                                    <span className="size-2.5 rounded-full bg-amber-400/80" />
                                    <span className="size-2.5 rounded-full bg-mint" />
                                    <span className="mx-auto rounded-full bg-secondary px-10 py-1.5 text-[0.58rem] font-bold text-muted-foreground">
                                        your-community.social
                                    </span>
                                </div>

                                <div className="grid min-h-[28rem] grid-cols-[9.5rem_minmax(0,1fr)] sm:min-h-[31rem] sm:grid-cols-[11.5rem_minmax(0,1fr)]">
                                    <aside className="border-r border-border/70 bg-secondary/45 p-3 sm:p-4">
                                        <div className="flex items-center gap-2 px-1">
                                            <span className="flex size-8 items-center justify-center rounded-xl bg-primary text-white">
                                                <MessageCircle className="size-4" />
                                            </span>
                                            <span className="hidden text-xs font-black tracking-[-0.025em] sm:block">
                                                Your social
                                            </span>
                                        </div>
                                        <div className="mt-7 space-y-1.5">
                                            {[
                                                [UsersRound, 'Home', true],
                                                [Search, 'Discover', false],
                                                [
                                                    MessageCircle,
                                                    'Messages',
                                                    false,
                                                ],
                                                [CalendarDays, 'Events', false],
                                            ].map(([Icon, label, active]) => {
                                                const ItemIcon =
                                                    Icon as typeof UsersRound;

                                                return (
                                                    <div
                                                        key={label as string}
                                                        className={`flex items-center gap-2 rounded-xl px-2.5 py-2.5 text-[0.67rem] font-extrabold sm:text-xs ${active ? 'bg-primary text-white shadow-sm' : 'text-muted-foreground'}`}
                                                    >
                                                        <ItemIcon className="size-4 shrink-0" />
                                                        <span className="truncate">
                                                            {label as string}
                                                        </span>
                                                    </div>
                                                );
                                            })}
                                        </div>
                                        <div className="mt-7 border-t border-border/70 pt-4">
                                            <p className="px-1 text-[0.56rem] font-extrabold tracking-[0.12em] text-muted-foreground uppercase">
                                                Your spaces
                                            </p>
                                            {[
                                                ['makers-studio', 'Makers'],
                                                ['local-founders', 'Local'],
                                            ].map(([image, label]) => (
                                                <div
                                                    key={image}
                                                    className="mt-3 flex items-center gap-2"
                                                >
                                                    <img
                                                        src={`/images/space-covers/${image}.webp`}
                                                        alt=""
                                                        className="size-8 rounded-lg object-cover"
                                                    />
                                                    <span className="truncate text-[0.62rem] font-bold sm:text-[0.68rem]">
                                                        {label}
                                                    </span>
                                                </div>
                                            ))}
                                        </div>
                                    </aside>

                                    <div className="min-w-0 p-3 sm:p-5">
                                        <div className="flex items-center justify-between">
                                            <div>
                                                <p className="text-[0.58rem] font-extrabold tracking-[0.14em] text-primary uppercase sm:text-[0.65rem]">
                                                    Your home
                                                </p>
                                                <h2 className="mt-1 text-lg font-black tracking-[-0.04em] sm:text-2xl">
                                                    Good conversations, in
                                                    order.
                                                </h2>
                                            </div>
                                            <span className="flex size-9 shrink-0 items-center justify-center rounded-full bg-foreground text-[0.65rem] font-black text-background">
                                                AM
                                            </span>
                                        </div>

                                        <div className="mt-4 flex gap-2 overflow-hidden">
                                            {[
                                                ['makers-studio', 'Makers'],
                                                ['local-founders', 'Local'],
                                                [
                                                    'open-source-meetup',
                                                    'Open source',
                                                ],
                                            ].map(([image, label]) => (
                                                <div
                                                    key={image}
                                                    className="min-w-[5.4rem] flex-1"
                                                >
                                                    <img
                                                        src={`/images/space-covers/${image}.webp`}
                                                        alt=""
                                                        className="aspect-[1.3] w-full rounded-xl object-cover"
                                                    />
                                                    <p className="mt-1.5 truncate text-[0.6rem] font-extrabold sm:text-[0.68rem]">
                                                        {label}
                                                    </p>
                                                </div>
                                            ))}
                                        </div>

                                        <article className="mt-4 rounded-[1.35rem] border border-border/70 bg-background p-3.5 sm:p-4">
                                            <div className="flex items-center gap-2.5">
                                                <span className="flex size-9 items-center justify-center rounded-full bg-primary text-[0.65rem] font-black text-white">
                                                    AM
                                                </span>
                                                <div>
                                                    <p className="text-xs font-extrabold sm:text-sm">
                                                        Andrew Matia
                                                    </p>
                                                    <p className="text-[0.58rem] font-semibold text-muted-foreground sm:text-[0.65rem]">
                                                        Makers Circle · now
                                                    </p>
                                                </div>
                                            </div>
                                            <p className="mt-3 text-xs leading-5 sm:text-[0.9rem] sm:leading-6">
                                                A strong community gives people
                                                a clear place, a clear purpose,
                                                and room to build something
                                                better.
                                            </p>
                                            <div className="mt-4 flex items-center gap-4 border-t border-border/60 pt-3 text-[0.62rem] font-bold text-muted-foreground sm:text-[0.68rem]">
                                                <span className="inline-flex items-center gap-1.5">
                                                    <Heart className="size-3.5" />
                                                    Like
                                                </span>
                                                <span className="inline-flex items-center gap-1.5">
                                                    <MessageCircle className="size-3.5" />
                                                    8 replies
                                                </span>
                                            </div>
                                        </article>
                                    </div>
                                </div>
                            </div>

                            <div className="absolute -right-2 -bottom-10 hidden w-48 rotate-2 rounded-[1.35rem] bg-[#091325] p-4.5 text-white shadow-2xl sm:block">
                                <ShieldCheck className="size-5 text-mint" />
                                <p className="mt-3 text-xs font-black">
                                    Safety belongs in core.
                                </p>
                                <p className="mt-1 text-[0.65rem] leading-4 text-white/55">
                                    Privacy and moderation are product behavior,
                                    not add-ons.
                                </p>
                            </div>
                        </div>
                    </section>

                    <section className="border-y border-border/70 bg-card/70">
                        <div className="mx-auto grid max-w-[90rem] grid-cols-3 divide-x divide-border/70">
                            {productSignals.map(([value, label]) => (
                                <div
                                    key={value}
                                    className="px-3 py-6 text-center sm:px-8 sm:py-8"
                                >
                                    <p className="text-sm font-black tracking-[-0.025em] sm:text-lg">
                                        {value}
                                    </p>
                                    <p className="mt-1 text-[0.62rem] font-bold text-muted-foreground sm:text-xs">
                                        {label}
                                    </p>
                                </div>
                            ))}
                        </div>
                    </section>

                    <section
                        id="platform"
                        className="mx-auto max-w-[90rem] scroll-mt-10 px-5 py-20 sm:px-8 lg:px-12 lg:py-28"
                    >
                        <div className="grid gap-8 lg:grid-cols-[.78fr_1.22fr] lg:items-end">
                            <div>
                                <p className="social-eyebrow">
                                    Core before add-ons
                                </p>
                                <h2 className="mt-4 text-4xl leading-[0.96] font-black tracking-[-0.06em] text-balance sm:text-6xl">
                                    The difficult social boundaries, already
                                    connected.
                                </h2>
                            </div>
                            <p className="max-w-2xl text-lg leading-8 text-muted-foreground lg:justify-self-end">
                                Start from coherent identity, visibility,
                                conversation, and safety—not a collection of
                                unrelated screens your team must reconcile
                                later.
                            </p>
                        </div>

                        <div className="mt-10 grid gap-3 lg:grid-cols-3">
                            {foundations.map((item) => (
                                <article
                                    key={item.title}
                                    className="rounded-[1.75rem] border border-border/75 bg-card p-6 shadow-[0_18px_50px_-38px_rgba(15,23,42,.45)] sm:p-7"
                                >
                                    <span
                                        className={`flex size-11 items-center justify-center rounded-2xl ${item.accent}`}
                                    >
                                        <item.icon
                                            className="size-5"
                                            aria-hidden="true"
                                        />
                                    </span>
                                    <h3 className="mt-6 text-xl font-black tracking-[-0.035em]">
                                        {item.title}
                                    </h3>
                                    <p className="mt-2 text-sm leading-6 text-muted-foreground">
                                        {item.body}
                                    </p>
                                </article>
                            ))}
                        </div>
                    </section>

                    <section
                        id="principles"
                        className="mx-3 overflow-hidden rounded-[2rem] bg-[#091325] text-white sm:mx-5 lg:mx-8"
                    >
                        <div className="mx-auto grid max-w-[86rem] gap-12 px-6 py-14 sm:px-10 lg:grid-cols-[.8fr_1.2fr] lg:items-center lg:px-14 lg:py-20">
                            <div>
                                <p className="text-[0.68rem] font-extrabold tracking-[0.17em] text-mint uppercase">
                                    One open core. Many products.
                                </p>
                                <h2 className="mt-4 text-4xl leading-[0.96] font-black tracking-[-0.055em] text-balance sm:text-6xl">
                                    Your community should feel like yours.
                                </h2>
                                <p className="mt-5 max-w-xl text-base leading-7 text-white/62">
                                    Build the visual, professional, creator, or
                                    local experience your audience needs while
                                    the shared social contract stays dependable
                                    below.
                                </p>
                            </div>

                            <div className="grid gap-2 sm:grid-cols-2">
                                {directions.map((direction, index) => (
                                    <article
                                        key={direction.label}
                                        className="group flex min-h-40 flex-col justify-between rounded-[1.45rem] border border-white/10 bg-white/[0.065] p-5 transition-colors hover:bg-white/[0.095]"
                                    >
                                        <div className="flex items-start justify-between">
                                            <span className="flex size-10 items-center justify-center rounded-2xl bg-white text-[#091325]">
                                                <direction.icon className="size-5" />
                                            </span>
                                            <span className="text-[0.62rem] font-black text-white/35">
                                                0{index + 1}
                                            </span>
                                        </div>
                                        <div>
                                            <h3 className="font-black tracking-[-0.025em]">
                                                {direction.label}
                                            </h3>
                                            <p className="mt-1 text-xs font-semibold text-white/48">
                                                {direction.detail}
                                            </p>
                                        </div>
                                    </article>
                                ))}
                            </div>
                        </div>
                    </section>

                    <section className="mx-auto max-w-[90rem] px-5 py-16 sm:px-8 lg:px-12 lg:py-20">
                        <div className="grid gap-7 rounded-[2rem] border border-border/75 bg-card p-6 sm:p-9 lg:grid-cols-[1fr_auto] lg:items-center lg:p-12">
                            <div>
                                <span className="inline-flex size-11 items-center justify-center rounded-2xl bg-primary/[0.09] text-primary">
                                    <Sparkles className="size-5" />
                                </span>
                                <h2 className="mt-5 max-w-3xl text-3xl leading-[1] font-black tracking-[-0.05em] text-balance sm:text-5xl">
                                    Start with a real social product. Make the
                                    next version unmistakably yours.
                                </h2>
                            </div>
                            <Link
                                href={primaryHref}
                                className="social-focus inline-flex min-h-13 shrink-0 items-center justify-center gap-2 rounded-full bg-primary px-6 text-sm font-extrabold text-white transition-transform hover:-translate-y-0.5"
                            >
                                {auth.user
                                    ? 'Open your feed'
                                    : 'Create account'}
                                <ArrowRight
                                    className="size-4"
                                    aria-hidden="true"
                                />
                            </Link>
                        </div>
                    </section>
                </main>

                <footer className="mx-auto flex max-w-[90rem] flex-col gap-3 px-5 pb-8 text-xs font-semibold text-muted-foreground sm:flex-row sm:items-center sm:justify-between sm:px-8 lg:px-12">
                    <p>Built for communities that want to own their future.</p>
                    <div className="flex items-center gap-4">
                        <a
                            href="https://github.com/drewmt/lineweb-social"
                            className="social-focus rounded-md hover:text-foreground"
                        >
                            Source
                        </a>
                        <span>Open-source beta · GPL-3.0-or-later</span>
                    </div>
                </footer>
            </div>
        </>
    );
}

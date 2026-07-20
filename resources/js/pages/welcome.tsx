import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    Blocks,
    Camera,
    Check,
    Globe2,
    MessageCircle,
    Play,
    ShieldCheck,
    UsersRound,
} from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import type { User } from '@/types';

type WelcomeProps = {
    auth: { user: User | null };
};

const foundations = [
    {
        icon: UsersRound,
        title: 'Spaces with boundaries',
        body: 'Public, private, and hidden communities with roles and permissions enforced on the server.',
    },
    {
        icon: MessageCircle,
        title: 'Conversation that makes sense',
        body: 'Chronological posts and comments without an opaque algorithm deciding what members should see.',
    },
    {
        icon: ShieldCheck,
        title: 'Safety in the foundation',
        body: 'Privacy, muting, blocking, reporting, and auditable moderation are core product behavior.',
    },
];

const directions = [
    { icon: Camera, label: 'Visual communities', tone: 'bg-coral/18' },
    { icon: Play, label: 'Creator networks', tone: 'bg-primary/10' },
    { icon: Globe2, label: 'Local social', tone: 'bg-mint/30' },
    { icon: Blocks, label: 'Niche platforms', tone: 'bg-secondary' },
];

export default function Welcome() {
    const { auth } = usePage<WelcomeProps>().props;
    const primaryHref = auth.user ? '/feed' : '/register';

    return (
        <>
            <Head title="Open social infrastructure for Laravel">
                <meta
                    name="description"
                    content="A Laravel-native, self-hosted foundation for modern community and social products."
                />
            </Head>
            <div className="min-h-screen overflow-hidden bg-background text-foreground">
                <header className="relative z-20 mx-auto flex max-w-[88rem] items-center justify-between px-5 py-5 sm:px-8 lg:px-12">
                    <Link
                        href="/"
                        className="social-focus flex items-center gap-3 rounded-2xl"
                    >
                        <span className="flex size-10 items-center justify-center rounded-[1rem] bg-primary text-primary-foreground shadow-[0_12px_26px_-15px_color-mix(in_oklab,var(--primary)_80%,transparent)]">
                            <AppLogoIcon className="size-6" />
                        </span>
                        <span>
                            <span className="block text-sm leading-none font-black tracking-[-0.035em]">
                                Lineweb Social
                            </span>
                            <span className="mt-1 block text-[0.61rem] leading-none font-extrabold tracking-[0.15em] text-muted-foreground uppercase">
                                Open social
                            </span>
                        </span>
                    </Link>
                    <nav
                        className="flex items-center gap-1.5"
                        aria-label="Account"
                    >
                        {!auth.user && (
                            <Link
                                href="/login"
                                className="social-focus rounded-full px-4 py-2.5 text-sm font-extrabold hover:bg-secondary"
                            >
                                Log in
                            </Link>
                        )}
                        <Link
                            href={primaryHref}
                            className="social-focus inline-flex min-h-11 items-center gap-2 rounded-full bg-foreground px-4.5 text-sm font-extrabold text-background transition-transform hover:-translate-y-0.5"
                        >
                            {auth.user ? 'Open your feed' : 'Create account'}
                            <ArrowRight className="size-4" aria-hidden="true" />
                        </Link>
                    </nav>
                </header>

                <main>
                    <section className="relative mx-auto grid max-w-[88rem] gap-12 px-5 pt-14 pb-20 sm:px-8 sm:pt-20 lg:grid-cols-[minmax(0,1.03fr)_minmax(31rem,.97fr)] lg:items-center lg:px-12 lg:pt-24 lg:pb-28">
                        <div className="pointer-events-none absolute -top-32 right-[-20%] h-[38rem] w-[50rem] rounded-full bg-primary/[0.075] blur-3xl" />
                        <div className="relative z-10">
                            <p className="social-eyebrow">
                                <span className="size-1.5 rounded-full bg-primary" />
                                Laravel-native social foundation
                            </p>
                            <h1 className="mt-5 max-w-4xl text-[clamp(3.3rem,7vw,6.8rem)] leading-[0.88] font-black tracking-[-0.07em] text-balance">
                                Build the social product people want to stay in.
                            </h1>
                            <p className="mt-7 max-w-2xl text-lg leading-8 text-muted-foreground sm:text-xl">
                                A self-hosted, extensible core for communities,
                                creator networks, local platforms, and the next
                                social experience your team can imagine.
                            </p>
                            <div className="mt-8 flex flex-wrap gap-3">
                                <Link
                                    href={primaryHref}
                                    className="social-focus inline-flex min-h-13 items-center gap-2 rounded-full bg-primary px-6 text-sm font-extrabold text-primary-foreground shadow-[0_18px_38px_-20px_color-mix(in_oklab,var(--primary)_90%,transparent)] transition-transform hover:-translate-y-0.5"
                                >
                                    {auth.user
                                        ? 'Enter the community'
                                        : 'Explore the platform'}
                                    <ArrowRight
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                </Link>
                                {!auth.user && (
                                    <Link
                                        href="/login"
                                        className="social-focus inline-flex min-h-13 items-center rounded-full border border-border bg-card px-6 text-sm font-extrabold hover:bg-secondary"
                                    >
                                        I already have an account
                                    </Link>
                                )}
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

                        <div className="relative z-10 mx-auto w-full max-w-[38rem] lg:mr-0">
                            <div className="absolute -inset-8 -z-10 rounded-[4rem] bg-[radial-gradient(circle_at_50%_45%,oklch(0.86_0.1_250_/_0.38),transparent_64%)]" />
                            <div className="relative ml-auto w-[88%] overflow-hidden rounded-[2rem] border border-border/80 bg-card p-3 shadow-[0_36px_90px_-44px_rgba(15,23,42,.55)] sm:p-4">
                                <div className="flex items-center justify-between px-2 py-2">
                                    <div>
                                        <p className="text-[0.65rem] font-extrabold tracking-[0.15em] text-primary uppercase">
                                            Your home
                                        </p>
                                        <p className="mt-0.5 font-black tracking-tight">
                                            Good conversations, in order.
                                        </p>
                                    </div>
                                    <span className="flex size-9 items-center justify-center rounded-full bg-foreground text-[0.65rem] font-black text-background">
                                        AM
                                    </span>
                                </div>
                                <div className="mt-2 grid grid-cols-3 gap-2">
                                    {[
                                        ['makers-studio', 'Makers'],
                                        ['local-founders', 'Local'],
                                        ['open-source-meetup', 'Open source'],
                                    ].map(([image, label]) => (
                                        <div key={image} className="min-w-0">
                                            <img
                                                src={`/images/space-covers/${image}.webp`}
                                                alt=""
                                                className="aspect-[1.05] w-full rounded-xl object-cover"
                                            />
                                            <p className="mt-1.5 truncate text-[0.66rem] font-extrabold">
                                                {label}
                                            </p>
                                        </div>
                                    ))}
                                </div>
                                <article className="mt-3 rounded-[1.35rem] bg-background p-4 ring-1 ring-border/65">
                                    <div className="flex items-center gap-2.5">
                                        <span className="flex size-9 items-center justify-center rounded-full bg-primary text-[0.65rem] font-black text-primary-foreground">
                                            AM
                                        </span>
                                        <div>
                                            <p className="text-sm font-extrabold">
                                                Andrew Matia
                                            </p>
                                            <p className="text-[0.65rem] font-semibold text-muted-foreground">
                                                Makers Circle · now
                                            </p>
                                        </div>
                                    </div>
                                    <p className="mt-3 text-[0.92rem] leading-6">
                                        The strongest community software gives
                                        people a clear place, a clear purpose,
                                        and room to build something better.
                                    </p>
                                    <div className="mt-4 flex gap-2 text-[0.65rem] font-bold text-muted-foreground">
                                        <span className="rounded-full bg-secondary px-2.5 py-1.5">
                                            Conversation
                                        </span>
                                        <span className="rounded-full bg-secondary px-2.5 py-1.5">
                                            Chronological
                                        </span>
                                    </div>
                                </article>
                            </div>
                            <div className="absolute top-14 -left-3 hidden w-36 rotate-[-4deg] rounded-[1.25rem] border border-border/70 bg-card p-2.5 shadow-xl sm:block">
                                <img
                                    src="/images/people-community.webp"
                                    alt=""
                                    className="h-20 w-full rounded-xl object-cover"
                                />
                                <p className="mt-2 text-xs font-black">
                                    People first
                                </p>
                                <p className="mt-0.5 text-[0.6rem] text-muted-foreground">
                                    Discovery with privacy.
                                </p>
                            </div>
                            <div className="absolute -right-2 -bottom-8 hidden w-44 rotate-2 rounded-[1.2rem] bg-foreground p-4 text-background shadow-xl sm:block">
                                <ShieldCheck className="size-5 text-mint" />
                                <p className="mt-3 text-xs font-black">
                                    Safety belongs in core.
                                </p>
                                <p className="mt-1 text-[0.65rem] leading-4 text-background/60">
                                    Not bolted on after launch.
                                </p>
                            </div>
                        </div>
                    </section>

                    <section className="border-y border-border/70 bg-card/65">
                        <div className="mx-auto grid max-w-[88rem] md:grid-cols-3">
                            {foundations.map((item, index) => (
                                <article
                                    key={item.title}
                                    className={`p-6 sm:p-8 lg:p-10 ${index > 0 ? 'border-t border-border/70 md:border-t-0 md:border-l' : ''}`}
                                >
                                    <span className="flex size-11 items-center justify-center rounded-2xl bg-primary/[0.09] text-primary">
                                        <item.icon
                                            className="size-5"
                                            aria-hidden="true"
                                        />
                                    </span>
                                    <h2 className="mt-5 text-xl font-black tracking-[-0.03em]">
                                        {item.title}
                                    </h2>
                                    <p className="mt-2 max-w-sm text-sm leading-6 text-muted-foreground">
                                        {item.body}
                                    </p>
                                </article>
                            ))}
                        </div>
                    </section>

                    <section className="mx-auto max-w-[88rem] px-5 py-20 sm:px-8 lg:px-12 lg:py-28">
                        <div className="grid gap-10 lg:grid-cols-[.76fr_1.24fr] lg:items-end">
                            <div>
                                <p className="social-eyebrow">One open core</p>
                                <h2 className="mt-4 text-4xl leading-[0.98] font-black tracking-[-0.055em] sm:text-6xl">
                                    Your product should not look like everyone
                                    else’s.
                                </h2>
                            </div>
                            <p className="max-w-2xl text-lg leading-8 text-muted-foreground lg:justify-self-end">
                                Identity, Spaces, privacy, safety, conversation,
                                and moderation form the dependable base. Teams
                                can build photo-led, video-led, professional, or
                                local experiences above it without rewriting the
                                social contract underneath.
                            </p>
                        </div>
                        <div className="mt-10 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            {directions.map((direction, index) => (
                                <article
                                    key={direction.label}
                                    className={`${direction.tone} group flex min-h-52 flex-col justify-between overflow-hidden rounded-[1.7rem] p-5 ring-1 ring-border/55`}
                                >
                                    <div className="flex items-start justify-between">
                                        <span className="flex size-11 items-center justify-center rounded-2xl bg-card text-foreground shadow-sm">
                                            <direction.icon className="size-5" />
                                        </span>
                                        <span className="text-xs font-black text-muted-foreground">
                                            0{index + 1}
                                        </span>
                                    </div>
                                    <div>
                                        <h3 className="text-xl font-black tracking-[-0.035em]">
                                            {direction.label}
                                        </h3>
                                        <p className="mt-1.5 text-sm leading-5 text-muted-foreground">
                                            A distinct experience on shared,
                                            dependable foundations.
                                        </p>
                                    </div>
                                </article>
                            ))}
                        </div>
                    </section>

                    <section className="mx-auto max-w-[88rem] px-5 pb-8 sm:px-8 lg:px-12">
                        <div className="overflow-hidden rounded-[2rem] bg-foreground px-6 py-10 text-background sm:px-10 lg:flex lg:items-center lg:justify-between lg:gap-10 lg:px-14 lg:py-14">
                            <div>
                                <p className="text-[0.68rem] font-extrabold tracking-[0.15em] text-mint uppercase">
                                    Build from a stronger starting point
                                </p>
                                <h2 className="mt-3 max-w-3xl text-3xl font-black tracking-[-0.045em] sm:text-5xl">
                                    A modern social foundation, ready to become
                                    yours.
                                </h2>
                            </div>
                            <Link
                                href={primaryHref}
                                className="social-focus mt-7 inline-flex min-h-13 shrink-0 items-center gap-2 rounded-full bg-background px-6 text-sm font-extrabold text-foreground transition-transform hover:-translate-y-0.5 lg:mt-0"
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

                <footer className="mx-auto flex max-w-[88rem] flex-col gap-2 px-5 py-8 text-xs font-semibold text-muted-foreground sm:flex-row sm:items-center sm:justify-between sm:px-8 lg:px-12">
                    <p>Built for communities that want to own their future.</p>
                    <p>Open-source alpha · GPL-3.0-or-later</p>
                </footer>
            </div>
        </>
    );
}

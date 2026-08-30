import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowRight,
    Check,
    CircleCheck,
    Compass,
    MessageSquareText,
    Route,
    UserRound,
    UsersRound,
} from 'lucide-react';
import { AvatarMark } from '@/components/social/avatar-mark';
import { CommunitySignal } from '@/components/social/community-signal';
import { SpaceCover } from '@/components/social/space-cover';
import { Button } from '@/components/ui/button';

type Progress = {
    completed: number;
    total: number;
    percent: number;
};

type OnboardingStep = {
    key: 'profile' | 'space' | 'people' | 'post';
    title: string;
    description: string;
    href: string;
    action: string;
    complete: boolean;
};

type SuggestedSpace = {
    name: string;
    slug: string;
    description: string | null;
    memberCount: number;
};

type SuggestedPerson = {
    name: string;
    handle: string;
    headline: string | null;
    sharedSpaceCount: number;
};

type OnboardingProps = {
    progress: Progress;
    steps: OnboardingStep[];
    spaces: SuggestedSpace[];
    people: SuggestedPerson[];
};

const stepIcons = {
    profile: UserRound,
    space: UsersRound,
    people: Compass,
    post: MessageSquareText,
} as const;

function ProgressRing({ progress }: { progress: Progress }) {
    const radius = 43;
    const circumference = 2 * Math.PI * radius;
    const offset = circumference * (1 - progress.percent / 100);

    return (
        <div className="relative grid size-28 shrink-0 place-items-center sm:size-32">
            <svg
                className="absolute inset-0 size-full -rotate-90"
                viewBox="0 0 104 104"
                aria-hidden="true"
            >
                <circle
                    cx="52"
                    cy="52"
                    r={radius}
                    fill="none"
                    stroke="currentColor"
                    strokeWidth="7"
                    className="text-white/12"
                />
                <circle
                    cx="52"
                    cy="52"
                    r={radius}
                    fill="none"
                    stroke="currentColor"
                    strokeWidth="7"
                    strokeLinecap="round"
                    strokeDasharray={circumference}
                    strokeDashoffset={offset}
                    className="text-mint transition-[stroke-dashoffset] duration-500"
                />
            </svg>
            <div className="text-center">
                <strong className="block text-2xl font-black tracking-tight text-white">
                    {progress.completed}/{progress.total}
                </strong>
                <span className="text-[0.65rem] font-extrabold tracking-[0.13em] text-white/55 uppercase">
                    complete
                </span>
            </div>
        </div>
    );
}

function JourneyStep({ step, index }: { step: OnboardingStep; index: number }) {
    const Icon = stepIcons[step.key];

    return (
        <article
            className={`social-card flex min-h-56 flex-col rounded-[1.45rem] p-5 transition-colors sm:p-6 ${step.complete ? 'border-mint/30 bg-mint/[0.045]' : 'social-card-interactive'}`}
        >
            <div className="flex items-start justify-between gap-4">
                <span
                    className={`flex size-11 items-center justify-center rounded-2xl ${step.complete ? 'bg-mint text-slate-950' : 'bg-primary/10 text-primary'}`}
                >
                    {step.complete ? (
                        <Check className="size-5" aria-hidden="true" />
                    ) : (
                        <Icon className="size-5" aria-hidden="true" />
                    )}
                </span>
                <span className="text-xs font-black text-muted-foreground/65">
                    0{index + 1}
                </span>
            </div>
            <h2 className="mt-5 text-lg font-black tracking-[-0.025em]">
                {step.title}
            </h2>
            <p className="mt-2 text-sm leading-6 text-muted-foreground">
                {step.description}
            </p>
            {step.complete ? (
                <span className="mt-auto flex min-h-11 items-center gap-2 pt-5 text-sm font-extrabold text-emerald-700 dark:text-emerald-300">
                    <CircleCheck className="size-4" aria-hidden="true" /> Done
                </span>
            ) : (
                <Link
                    href={step.href}
                    className="social-focus mt-auto flex min-h-11 items-center justify-between pt-5 text-sm font-extrabold text-primary"
                >
                    {step.action}
                    <ArrowRight className="size-4" aria-hidden="true" />
                </Link>
            )}
        </article>
    );
}

export default function CommunityOnboarding({
    progress,
    steps,
    spaces,
    people,
}: OnboardingProps) {
    const joinSpace = (slug: string) => {
        router.post(`/spaces/${slug}/membership`, {}, { preserveScroll: true });
    };

    const followPerson = (handle: string) => {
        router.put(`/people/${handle}/follow`, {}, { preserveScroll: true });
    };

    const dismiss = () => {
        router.post('/getting-started/dismiss');
    };

    return (
        <>
            <Head title="Getting started" />
            <main className="social-page">
                <section
                    className="social-card relative overflow-hidden rounded-[1.8rem] bg-slate-950 p-6 text-white sm:p-9 lg:p-10"
                    aria-labelledby="onboarding-title"
                >
                    <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_86%_12%,oklch(0.78_0.16_181_/_0.2),transparent_35%),radial-gradient(circle_at_8%_100%,oklch(0.58_0.2_264_/_0.24),transparent_42%)]" />
                    <div className="relative flex flex-col gap-8 sm:flex-row sm:items-center sm:justify-between">
                        <div className="max-w-2xl">
                            <div className="flex items-center gap-2 text-xs font-extrabold tracking-[0.15em] text-mint uppercase">
                                <CommunitySignal /> Start with purpose
                            </div>
                            <h1
                                id="onboarding-title"
                                className="mt-4 text-4xl leading-[0.95] font-black tracking-[-0.055em] sm:text-5xl lg:text-6xl"
                            >
                                Your community begins with four human actions.
                            </h1>
                            <p className="mt-5 max-w-xl text-sm leading-6 text-white/62 sm:text-base sm:leading-7">
                                No algorithmic checklist and no forced tour. Set
                                up your identity, find a space, connect with one
                                person, and start a useful conversation.
                            </p>
                        </div>
                        <ProgressRing progress={progress} />
                    </div>
                    <div className="relative mt-8 flex flex-wrap items-center gap-3 border-t border-white/10 pt-5">
                        <Link
                            href="/feed"
                            className="social-focus inline-flex min-h-11 items-center gap-2 rounded-xl bg-white px-4 py-2 text-sm font-extrabold text-slate-950 transition-colors hover:bg-white/90"
                        >
                            Open the feed
                            <ArrowRight className="size-4" aria-hidden="true" />
                        </Link>
                        <button
                            type="button"
                            onClick={dismiss}
                            className="social-focus min-h-11 rounded-xl px-4 py-2 text-sm font-bold text-white/65 transition-colors hover:bg-white/8 hover:text-white"
                        >
                            Hide this guide
                        </button>
                    </div>
                </section>

                <section className="mt-5" aria-labelledby="journey-title">
                    <div className="mb-4 flex items-end justify-between gap-4 px-1">
                        <div>
                            <p className="text-[0.68rem] font-extrabold tracking-[0.15em] text-primary uppercase">
                                Your route in
                            </p>
                            <h2
                                id="journey-title"
                                className="mt-1 text-2xl font-black tracking-tight"
                            >
                                A useful first session
                            </h2>
                        </div>
                        <span className="hidden text-xs font-bold text-muted-foreground sm:block">
                            You stay in control of every step
                        </span>
                    </div>
                    <div className="grid gap-3 sm:grid-cols-2 2xl:grid-cols-4">
                        {steps.map((step, index) => (
                            <JourneyStep
                                key={step.key}
                                step={step}
                                index={index}
                            />
                        ))}
                    </div>
                </section>

                <div className="mt-8 grid gap-7 xl:grid-cols-[1.15fr_0.85fr]">
                    <section aria-labelledby="spaces-title">
                        <div className="mb-4 flex items-end justify-between gap-3 px-1">
                            <div>
                                <p className="text-[0.68rem] font-extrabold tracking-[0.15em] text-primary uppercase">
                                    Begin somewhere
                                </p>
                                <h2
                                    id="spaces-title"
                                    className="mt-1 text-2xl font-black tracking-tight"
                                >
                                    Open spaces
                                </h2>
                            </div>
                            <Link
                                href="/spaces"
                                className="social-focus text-sm font-extrabold text-primary"
                            >
                                View all
                            </Link>
                        </div>
                        {spaces.length > 0 ? (
                            <div className="grid gap-3 sm:grid-cols-2">
                                {spaces.map((space) => (
                                    <article
                                        key={space.slug}
                                        className="social-card social-card-interactive overflow-hidden rounded-[1.35rem]"
                                    >
                                        <div className="relative h-28 overflow-hidden bg-secondary/70">
                                            <SpaceCover
                                                seed={space.slug}
                                                className="absolute inset-0"
                                            />
                                        </div>
                                        <div className="p-4 sm:p-5">
                                            <h3 className="text-lg font-black tracking-tight">
                                                {space.name}
                                            </h3>
                                            <p className="mt-1.5 line-clamp-2 min-h-12 text-sm leading-6 text-muted-foreground">
                                                {space.description ??
                                                    'A focused home for useful conversations.'}
                                            </p>
                                            <div className="mt-5 flex items-center justify-between gap-3">
                                                <span className="flex items-center gap-1.5 text-xs font-bold text-muted-foreground">
                                                    <UsersRound
                                                        className="size-3.5"
                                                        aria-hidden="true"
                                                    />
                                                    {space.memberCount}{' '}
                                                    {space.memberCount === 1
                                                        ? 'member'
                                                        : 'members'}
                                                </span>
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    onClick={() =>
                                                        joinSpace(space.slug)
                                                    }
                                                >
                                                    Join
                                                </Button>
                                            </div>
                                        </div>
                                    </article>
                                ))}
                            </div>
                        ) : (
                            <div className="social-card rounded-[1.35rem] p-7 text-center sm:p-10">
                                <Route
                                    className="mx-auto size-7 text-primary"
                                    aria-hidden="true"
                                />
                                <h3 className="mt-3 font-black">
                                    No open spaces yet
                                </h3>
                                <p className="mx-auto mt-2 max-w-md text-sm leading-6 text-muted-foreground">
                                    Create the first focused space or return
                                    when your community operator has opened one.
                                </p>
                                <Link
                                    href="/spaces"
                                    className="social-focus mt-4 inline-flex min-h-11 items-center gap-2 text-sm font-extrabold text-primary"
                                >
                                    Create a space
                                    <ArrowRight
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                </Link>
                            </div>
                        )}
                    </section>

                    <section aria-labelledby="people-title">
                        <div className="mb-4 flex items-end justify-between gap-3 px-1">
                            <div>
                                <p className="text-[0.68rem] font-extrabold tracking-[0.15em] text-primary uppercase">
                                    Connect deliberately
                                </p>
                                <h2
                                    id="people-title"
                                    className="mt-1 text-2xl font-black tracking-tight"
                                >
                                    People you can meet
                                </h2>
                            </div>
                            <Link
                                href="/people"
                                className="social-focus text-sm font-extrabold text-primary"
                            >
                                View all
                            </Link>
                        </div>
                        {people.length > 0 ? (
                            <div className="social-card overflow-hidden rounded-[1.35rem]">
                                {people.map((person, index) => (
                                    <article
                                        key={person.handle}
                                        className={`flex items-center gap-3 p-4 sm:p-5 ${index > 0 ? 'border-t' : ''}`}
                                    >
                                        <AvatarMark
                                            name={person.name}
                                            className="size-11 shrink-0"
                                        />
                                        <div className="min-w-0 flex-1">
                                            <Link
                                                href={`/people/${person.handle}`}
                                                className="social-focus block truncate text-sm font-black hover:text-primary"
                                            >
                                                {person.name}
                                            </Link>
                                            <p className="truncate text-xs font-bold text-primary">
                                                @{person.handle}
                                            </p>
                                            <p className="mt-1 truncate text-xs text-muted-foreground">
                                                {person.headline ??
                                                    (person.sharedSpaceCount > 0
                                                        ? `${person.sharedSpaceCount} shared spaces`
                                                        : 'Open profile')}
                                            </p>
                                        </div>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={() =>
                                                followPerson(person.handle)
                                            }
                                        >
                                            Follow
                                        </Button>
                                    </article>
                                ))}
                            </div>
                        ) : (
                            <div className="social-card rounded-[1.35rem] p-7 text-center sm:p-10">
                                <UserRound
                                    className="mx-auto size-7 text-primary"
                                    aria-hidden="true"
                                />
                                <h3 className="mt-3 font-black">
                                    Your next connection stays intentional
                                </h3>
                                <p className="mx-auto mt-2 max-w-md text-sm leading-6 text-muted-foreground">
                                    Profiles appear only when their privacy and
                                    discovery settings allow it.
                                </p>
                            </div>
                        )}
                    </section>
                </div>
            </main>
        </>
    );
}

CommunityOnboarding.layout = {
    breadcrumbs: [{ title: 'Getting started', href: '/getting-started' }],
};

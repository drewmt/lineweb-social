import { Head, Link, useForm } from '@inertiajs/react';
import {
    ArrowRight,
    CalendarClock,
    CheckCircle2,
    LockKeyhole,
    ShieldCheck,
    UserRoundPlus,
    UsersRound,
} from 'lucide-react';
import PublicBrand from '@/components/social/public-brand';
import { SpaceCover } from '@/components/social/space-cover';
import { Button } from '@/components/ui/button';

type InviteLinkProps = {
    inviteLink: {
        space: {
            name: string;
            slug: string;
            description: string | null;
        };
        creator: string | null;
        expiresAt: string;
        remainingUses: number;
        available: boolean;
        alreadyMember: boolean;
    };
    viewer: {
        signedIn: boolean;
        verified: boolean;
        suspended: boolean;
    };
    loginUrl: string;
    registerUrl: string;
    acceptUrl: string;
    spaceUrl: string | null;
};

const expiryLabel = (value: string) =>
    new Intl.DateTimeFormat(undefined, {
        dateStyle: 'long',
        timeStyle: 'short',
    }).format(new Date(value));

export default function SpaceInviteLink({
    inviteLink,
    viewer,
    loginUrl,
    registerUrl,
    acceptUrl,
    spaceUrl,
}: InviteLinkProps) {
    const { post, processing, errors } = useForm({ invite_link: '' });

    return (
        <main className="relative min-h-svh overflow-hidden bg-[#f4f7fb] text-[#13213a] dark:bg-[#09111f] dark:text-white">
            <Head title={`Join ${inviteLink.space.name}`} />

            <div className="pointer-events-none absolute inset-x-0 top-0 h-[32rem] bg-[radial-gradient(circle_at_15%_5%,rgba(45,108,255,.18),transparent_35%),radial-gradient(circle_at_90%_8%,rgba(41,196,151,.14),transparent_34%)]" />

            <div className="relative mx-auto flex min-h-svh w-full max-w-6xl flex-col px-4 py-5 sm:px-7 sm:py-7">
                <PublicBrand />

                <div className="my-auto grid items-center gap-6 py-8 lg:grid-cols-[minmax(0,1.05fr)_minmax(24rem,.95fr)] lg:gap-12">
                    <section>
                        <p className="text-[0.7rem] font-black tracking-[0.16em] text-primary uppercase">
                            Private community invitation
                        </p>
                        <h1 className="mt-4 max-w-2xl text-[clamp(2.65rem,7vw,5.4rem)] leading-[0.9] font-black tracking-[-0.065em] text-balance">
                            Your seat in {inviteLink.space.name} is ready.
                        </h1>
                        <p className="mt-6 max-w-xl text-base leading-7 text-slate-600 sm:text-lg dark:text-white/62">
                            {inviteLink.space.description ||
                                'Join the people, conversations, and shared work inside this community Space.'}
                        </p>

                        <div className="mt-7 flex flex-wrap gap-2.5 text-xs font-extrabold text-slate-600 dark:text-white/65">
                            <span className="inline-flex min-h-10 items-center gap-2 rounded-full border border-slate-200 bg-white/75 px-3.5 shadow-sm dark:border-white/10 dark:bg-white/6">
                                <CalendarClock className="size-4 text-primary" />
                                Expires {expiryLabel(inviteLink.expiresAt)}
                            </span>
                            <span className="inline-flex min-h-10 items-center gap-2 rounded-full border border-slate-200 bg-white/75 px-3.5 shadow-sm dark:border-white/10 dark:bg-white/6">
                                <UsersRound className="size-4 text-primary" />
                                {inviteLink.remainingUses}{' '}
                                {inviteLink.remainingUses === 1
                                    ? 'place remains'
                                    : 'places remain'}
                            </span>
                        </div>
                    </section>

                    <section className="overflow-hidden rounded-[2rem] border border-white/70 bg-white shadow-[0_30px_90px_-42px_rgba(20,43,82,.55)] dark:border-white/10 dark:bg-[#111c2d]">
                        <div className="relative h-44 overflow-hidden sm:h-52">
                            <SpaceCover
                                seed={inviteLink.space.slug}
                                className="absolute inset-0 h-full w-full"
                            />
                            <div className="absolute inset-0 bg-gradient-to-t from-[#101b2d]/85 via-[#101b2d]/15 to-transparent" />
                            <div className="absolute right-4 bottom-4 left-4 text-white">
                                <p className="text-xs font-extrabold text-white/68">
                                    Invited by{' '}
                                    {inviteLink.creator || 'the community team'}
                                </p>
                                <h2 className="mt-1 text-2xl font-black tracking-[-0.035em]">
                                    {inviteLink.space.name}
                                </h2>
                            </div>
                        </div>

                        <div className="p-5 sm:p-6">
                            {!inviteLink.available &&
                            !inviteLink.alreadyMember ? (
                                <div className="rounded-2xl bg-secondary p-4">
                                    <LockKeyhole className="size-6 text-muted-foreground" />
                                    <h3 className="mt-3 font-extrabold">
                                        This link is no longer available
                                    </h3>
                                    <p className="mt-1 text-sm leading-6 text-muted-foreground">
                                        Ask the Space team for a new invitation.
                                    </p>
                                </div>
                            ) : inviteLink.alreadyMember && spaceUrl ? (
                                <>
                                    <div className="flex items-start gap-3 rounded-2xl bg-emerald-500/9 p-4">
                                        <CheckCircle2 className="mt-0.5 size-5 shrink-0 text-emerald-600 dark:text-emerald-300" />
                                        <div>
                                            <h3 className="font-extrabold">
                                                You are already a member
                                            </h3>
                                            <p className="mt-1 text-sm leading-6 text-muted-foreground">
                                                Continue directly to the Space.
                                            </p>
                                        </div>
                                    </div>
                                    <Button
                                        asChild
                                        size="lg"
                                        className="mt-4 w-full"
                                    >
                                        <Link href={spaceUrl}>
                                            Open Space
                                            <ArrowRight className="size-4" />
                                        </Link>
                                    </Button>
                                </>
                            ) : !viewer.signedIn ? (
                                <>
                                    <div className="flex items-start gap-3 rounded-2xl bg-primary/8 p-4">
                                        <UserRoundPlus className="mt-0.5 size-5 shrink-0 text-primary" />
                                        <div>
                                            <h3 className="font-extrabold">
                                                Join with your own identity
                                            </h3>
                                            <p className="mt-1 text-sm leading-6 text-muted-foreground">
                                                Sign in or create a verified
                                                account. We will bring you back
                                                here.
                                            </p>
                                        </div>
                                    </div>
                                    <div className="mt-4 grid gap-2 sm:grid-cols-2">
                                        <Button asChild size="lg">
                                            <Link href={registerUrl}>
                                                Create account
                                            </Link>
                                        </Button>
                                        <Button
                                            asChild
                                            size="lg"
                                            variant="outline"
                                        >
                                            <Link href={loginUrl}>Log in</Link>
                                        </Button>
                                    </div>
                                </>
                            ) : viewer.suspended ? (
                                <Button asChild size="lg" className="w-full">
                                    <Link href="/account-status">
                                        Review account status
                                    </Link>
                                </Button>
                            ) : !viewer.verified ? (
                                <>
                                    <p className="text-sm leading-6 text-muted-foreground">
                                        Verify your email first. Your invitation
                                        will remain ready for you.
                                    </p>
                                    <Button
                                        asChild
                                        size="lg"
                                        className="mt-4 w-full"
                                    >
                                        <Link href="/verify-email">
                                            Verify email
                                        </Link>
                                    </Button>
                                </>
                            ) : (
                                <>
                                    <div className="flex items-start gap-3 rounded-2xl bg-primary/8 p-4">
                                        <ShieldCheck className="mt-0.5 size-5 shrink-0 text-primary" />
                                        <div>
                                            <h3 className="font-extrabold">
                                                Join as a member
                                            </h3>
                                            <p className="mt-1 text-sm leading-6 text-muted-foreground">
                                                Membership gives you access to
                                                the Space according to its
                                                community rules.
                                            </p>
                                        </div>
                                    </div>
                                    {errors.invite_link && (
                                        <p className="mt-3 text-sm font-semibold text-destructive">
                                            {errors.invite_link}
                                        </p>
                                    )}
                                    <Button
                                        type="button"
                                        size="lg"
                                        disabled={processing}
                                        onClick={() => post(acceptUrl)}
                                        className="mt-4 w-full"
                                    >
                                        {processing
                                            ? 'Joining...'
                                            : `Join ${inviteLink.space.name}`}
                                        <ArrowRight className="size-4" />
                                    </Button>
                                </>
                            )}

                            <p className="mt-4 flex items-center justify-center gap-2 text-center text-[0.68rem] leading-5 font-semibold text-muted-foreground">
                                <LockKeyhole className="size-3.5 shrink-0" />
                                The link is access-limited and can be revoked by
                                the Space team.
                            </p>
                        </div>
                    </section>
                </div>
            </div>
        </main>
    );
}

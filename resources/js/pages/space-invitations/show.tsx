import { Head, Link, useForm } from '@inertiajs/react';
import { CalendarClock, ShieldCheck, UsersRound } from 'lucide-react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';

type InvitationProps = {
    invitation: {
        space: { name: string; description: string | null };
        inviter: string | null;
        role: 'member' | 'moderator';
        expiresAt: string;
        available: boolean;
    };
    acceptUrl: string;
};

export default function SpaceInvitation({
    invitation,
    acceptUrl,
}: InvitationProps) {
    const { post, processing, errors } = useForm({ invitation: '' });

    const accept = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        post(acceptUrl);
    };

    return (
        <>
            <Head title={`Invitation to ${invitation.space.name}`} />
            <main className="flex min-h-full items-center justify-center bg-muted/55 px-4 py-10">
                <section className="w-full max-w-xl rounded-3xl border bg-card p-6 shadow-sm sm:p-8">
                    <div className="flex size-12 items-center justify-center rounded-full bg-primary text-primary-foreground">
                        <UsersRound className="size-5" aria-hidden="true" />
                    </div>
                    <p className="mt-5 text-sm font-semibold text-primary">
                        Space invitation
                    </p>
                    <h1 className="mt-1 text-2xl font-bold tracking-[-0.025em] sm:text-3xl">
                        Join {invitation.space.name}
                    </h1>
                    <p className="mt-3 leading-7 text-muted-foreground">
                        {invitation.space.description ??
                            'A community space is waiting for you.'}
                    </p>

                    <div className="mt-6 grid gap-3 rounded-2xl bg-muted/70 p-4 text-sm sm:grid-cols-2">
                        <div className="flex items-center gap-2">
                            <ShieldCheck
                                className="size-4 text-primary"
                                aria-hidden="true"
                            />
                            Join as {invitation.role}
                        </div>
                        <div className="flex items-center gap-2">
                            <CalendarClock
                                className="size-4 text-primary"
                                aria-hidden="true"
                            />
                            Expires{' '}
                            {new Intl.DateTimeFormat(undefined, {
                                dateStyle: 'medium',
                            }).format(new Date(invitation.expiresAt))}
                        </div>
                    </div>

                    <p className="mt-4 text-sm text-muted-foreground">
                        Invited by {invitation.inviter ?? 'a former member'}.
                    </p>

                    {errors.invitation && (
                        <p className="mt-4 rounded-xl border border-destructive/20 bg-destructive/5 px-3 py-2 text-sm text-destructive">
                            {errors.invitation}
                        </p>
                    )}

                    <div className="mt-7 flex flex-wrap gap-3">
                        {invitation.available ? (
                            <form onSubmit={accept}>
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    className="rounded-full px-6"
                                >
                                    Accept invitation
                                </Button>
                            </form>
                        ) : (
                            <p className="text-sm font-medium text-muted-foreground">
                                This invitation is no longer available.
                            </p>
                        )}
                        <Button
                            asChild
                            type="button"
                            variant="outline"
                            className="rounded-full px-6"
                        >
                            <Link href="/spaces">Not now</Link>
                        </Button>
                    </div>
                </section>
            </main>
        </>
    );
}

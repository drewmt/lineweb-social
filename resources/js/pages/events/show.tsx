import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    CalendarDays,
    Clock3,
    ExternalLink,
    MapPin,
    Radio,
    ShieldCheck,
    UsersRound,
} from 'lucide-react';
import { useState } from 'react';
import {
    EventRsvpControls,
    eventTimeLabel,
} from '@/components/social/space-event-card';
import type { SpaceEventSummary } from '@/components/social/space-event-card';
import { Button } from '@/components/ui/button';

type EventShowProps = { event: SpaceEventSummary; status?: string };

export default function EventShow({ event, status }: EventShowProps) {
    const [cancelling, setCancelling] = useState(false);

    const cancel = () => {
        if (
            !window.confirm(
                'Cancel this event? Existing RSVPs will remain as a private historical record.',
            )
        ) {
            return;
        }

        setCancelling(true);
        router.patch(
            `/events/${event.id}/cancel`,
            {},
            { preserveScroll: true, onFinish: () => setCancelling(false) },
        );
    };

    return (
        <>
            <Head title={event.title} />
            <main className="social-page">
                <div className="mx-auto max-w-5xl">
                    <Link
                        href={`/spaces/${event.space.slug}/events`}
                        className="social-focus mb-4 inline-flex min-h-10 items-center gap-2 rounded-full px-2 text-sm font-bold text-primary hover:bg-primary/8"
                    >
                        <ArrowLeft className="size-4" aria-hidden="true" /> All{' '}
                        {event.space.name} events
                    </Link>

                    {status && (
                        <div
                            role="status"
                            className="mb-4 rounded-2xl border border-primary/20 bg-primary/8 px-4 py-3 text-sm font-bold"
                        >
                            {status}
                        </div>
                    )}

                    <div className="grid items-start gap-5 lg:grid-cols-[minmax(0,1fr)_20rem]">
                        <article className="social-card overflow-hidden rounded-[1.75rem]">
                            <header className="relative overflow-hidden bg-foreground px-5 py-7 text-background sm:px-8 sm:py-10">
                                <div
                                    className="pointer-events-none absolute -top-12 -right-10 size-52 rounded-full border-[2.2rem] border-mint/15"
                                    aria-hidden="true"
                                />
                                <div className="relative">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <span className="rounded-full bg-coral px-3 py-1.5 text-[0.68rem] font-black tracking-[0.08em] text-white uppercase">
                                            Space event
                                        </span>
                                        {event.cancelledAt && (
                                            <span className="rounded-full bg-white/10 px-3 py-1.5 text-[0.68rem] font-black uppercase">
                                                Cancelled
                                            </span>
                                        )}
                                    </div>
                                    <h1 className="mt-4 max-w-3xl text-3xl leading-tight font-black tracking-[-0.05em] sm:text-5xl">
                                        {event.title}
                                    </h1>
                                    <Link
                                        href={`/spaces/${event.space.slug}`}
                                        className="social-focus mt-4 inline-flex rounded-md text-sm font-extrabold text-mint hover:underline"
                                    >
                                        Hosted by {event.space.name}
                                    </Link>
                                </div>
                            </header>

                            <div className="p-5 sm:p-8">
                                <div className="grid gap-3 sm:grid-cols-2">
                                    <div className="rounded-2xl bg-secondary/55 p-4">
                                        <Clock3
                                            className="size-5 text-primary"
                                            aria-hidden="true"
                                        />
                                        <p className="mt-3 text-sm font-black">
                                            When
                                        </p>
                                        <time
                                            dateTime={event.startsAt}
                                            className="mt-1 block text-sm leading-6 text-muted-foreground"
                                        >
                                            {eventTimeLabel(event)}
                                        </time>
                                    </div>
                                    <div className="rounded-2xl bg-secondary/55 p-4">
                                        {event.venue ? (
                                            <MapPin
                                                className="size-5 text-primary"
                                                aria-hidden="true"
                                            />
                                        ) : (
                                            <Radio
                                                className="size-5 text-primary"
                                                aria-hidden="true"
                                            />
                                        )}
                                        <p className="mt-3 text-sm font-black">
                                            Where
                                        </p>
                                        <p className="mt-1 text-sm leading-6 text-muted-foreground">
                                            {event.venue ?? 'Online event'}
                                        </p>
                                    </div>
                                </div>

                                {event.description && (
                                    <section
                                        className="mt-7"
                                        aria-labelledby="event-about-title"
                                    >
                                        <h2
                                            id="event-about-title"
                                            className="text-lg font-black tracking-tight"
                                        >
                                            About this event
                                        </h2>
                                        <p className="mt-3 text-[1.01rem] leading-8 whitespace-pre-wrap text-foreground/85">
                                            {event.description}
                                        </p>
                                    </section>
                                )}

                                {event.onlineUrl &&
                                    event.cancelledAt === null && (
                                        <a
                                            href={event.onlineUrl}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="social-focus mt-7 inline-flex min-h-11 items-center gap-2 rounded-xl bg-secondary px-4 text-sm font-extrabold text-primary transition-colors hover:bg-primary/10"
                                        >
                                            <ExternalLink
                                                className="size-4"
                                                aria-hidden="true"
                                            />{' '}
                                            Open secure event link
                                        </a>
                                    )}
                            </div>
                        </article>

                        <aside className="space-y-4 lg:sticky lg:top-6">
                            <section
                                className="social-card rounded-[1.4rem] p-5"
                                aria-labelledby="rsvp-title"
                            >
                                <p className="text-[0.66rem] font-extrabold tracking-[0.14em] text-primary uppercase">
                                    Your response
                                </p>
                                <h2
                                    id="rsvp-title"
                                    className="mt-1 text-xl font-black tracking-tight"
                                >
                                    Join the moment
                                </h2>
                                <p className="mt-2 text-sm leading-6 text-muted-foreground">
                                    Your identity stays private; only aggregate
                                    counts are shown.
                                </p>
                                <div className="mt-4">
                                    <EventRsvpControls event={event} />
                                </div>
                                <div className="mt-5 grid grid-cols-2 gap-2 border-t pt-4 text-center">
                                    <div className="rounded-xl bg-primary/8 px-2 py-3">
                                        <strong className="block text-lg font-black text-primary">
                                            {event.goingCount.toLocaleString()}
                                        </strong>
                                        <span className="text-[0.68rem] font-bold text-muted-foreground">
                                            Going
                                        </span>
                                    </div>
                                    <div className="rounded-xl bg-secondary px-2 py-3">
                                        <strong className="block text-lg font-black">
                                            {event.interestedCount.toLocaleString()}
                                        </strong>
                                        <span className="text-[0.68rem] font-bold text-muted-foreground">
                                            Interested
                                        </span>
                                    </div>
                                </div>
                                {event.capacity !== null && (
                                    <p className="mt-3 flex items-center gap-2 text-xs font-bold text-muted-foreground">
                                        <UsersRound
                                            className="size-4"
                                            aria-hidden="true"
                                        />{' '}
                                        {Math.max(
                                            0,
                                            event.capacity - event.goingCount,
                                        ).toLocaleString()}{' '}
                                        of {event.capacity.toLocaleString()}{' '}
                                        spots remain
                                    </p>
                                )}
                            </section>

                            <section className="rounded-[1.35rem] border border-primary/15 bg-primary/[0.045] p-4">
                                <div className="flex items-start gap-3">
                                    <ShieldCheck
                                        className="mt-0.5 size-5 shrink-0 text-primary"
                                        aria-hidden="true"
                                    />
                                    <p className="text-xs leading-5 text-muted-foreground">
                                        No public attendee list. Event access
                                        always follows the current Space
                                        visibility and membership policy.
                                    </p>
                                </div>
                            </section>

                            {event.canCancel && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={cancel}
                                    disabled={cancelling}
                                    className="w-full"
                                >
                                    <CalendarDays
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    {cancelling
                                        ? 'Cancelling…'
                                        : 'Cancel event'}
                                </Button>
                            )}
                        </aside>
                    </div>
                </div>
            </main>
        </>
    );
}

EventShow.layout = {
    breadcrumbs: [
        { title: 'Spaces', href: '/spaces' },
        { title: 'Event', href: '#' },
    ],
};

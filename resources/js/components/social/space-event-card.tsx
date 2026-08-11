import { Link, router } from '@inertiajs/react';
import {
    ArrowUpRight,
    CalendarDays,
    Check,
    Clock3,
    Heart,
    MapPin,
    Radio,
    UsersRound,
    X,
} from 'lucide-react';
import { useState } from 'react';

export type SpaceEventSummary = {
    id: number;
    url: string;
    title: string;
    description: string | null;
    startsAt: string;
    endsAt: string;
    timezone: string;
    venue: string | null;
    onlineUrl: string | null;
    capacity: number | null;
    cancelledAt: string | null;
    goingCount: number;
    interestedCount: number;
    viewerStatus: 'going' | 'interested' | null;
    canRsvp: boolean;
    canRemoveRsvp: boolean;
    canCancel: boolean;
    isFull: boolean;
    space: { name: string; slug: string };
};

const eventDate = (event: SpaceEventSummary) => {
    const date = new Date(event.startsAt);

    return {
        month: new Intl.DateTimeFormat(undefined, {
            month: 'short',
            timeZone: event.timezone,
        }).format(date),
        day: new Intl.DateTimeFormat(undefined, {
            day: '2-digit',
            timeZone: event.timezone,
        }).format(date),
        weekday: new Intl.DateTimeFormat(undefined, {
            weekday: 'short',
            timeZone: event.timezone,
        }).format(date),
    };
};

export const eventTimeLabel = (event: SpaceEventSummary) => {
    const date = new Intl.DateTimeFormat(undefined, {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        timeZone: event.timezone,
        timeZoneName: 'short',
    }).format(new Date(event.startsAt));
    const end = new Intl.DateTimeFormat(undefined, {
        hour: 'numeric',
        minute: '2-digit',
        timeZone: event.timezone,
    }).format(new Date(event.endsAt));

    return `${date} – ${end}`;
};

export function EventRsvpControls({
    event,
    compact = false,
}: {
    event: SpaceEventSummary;
    compact?: boolean;
}) {
    const [processing, setProcessing] = useState(false);

    const respond = (status: 'going' | 'interested') => {
        setProcessing(true);
        router.put(
            `/events/${event.id}/rsvp`,
            { status },
            {
                preserveScroll: true,
                onFinish: () => setProcessing(false),
            },
        );
    };
    const clear = () => {
        setProcessing(true);
        router.delete(`/events/${event.id}/rsvp`, {
            preserveScroll: true,
            onFinish: () => setProcessing(false),
        });
    };

    if (event.cancelledAt !== null) {
        return (
            <span className="inline-flex min-h-10 items-center gap-2 rounded-xl bg-coral/10 px-3 text-xs font-extrabold text-coral">
                <X className="size-4" aria-hidden="true" /> Cancelled
            </span>
        );
    }

    if (!event.canRsvp && event.viewerStatus === null) {
        return null;
    }

    return (
        <div
            className={`flex flex-wrap items-center gap-2 ${compact ? 'mt-3' : ''}`}
        >
            <button
                type="button"
                onClick={() => respond('going')}
                disabled={
                    processing ||
                    !event.canRsvp ||
                    (event.isFull && event.viewerStatus !== 'going')
                }
                aria-pressed={event.viewerStatus === 'going'}
                title={
                    event.isFull && event.viewerStatus !== 'going'
                        ? 'This event is full'
                        : undefined
                }
                className={`social-focus inline-flex min-h-11 items-center gap-2 rounded-xl px-3.5 text-sm font-extrabold transition-colors disabled:cursor-not-allowed disabled:opacity-50 ${
                    event.viewerStatus === 'going'
                        ? 'bg-primary text-primary-foreground'
                        : 'bg-primary/10 text-primary hover:bg-primary/15'
                }`}
            >
                <Check className="size-4" aria-hidden="true" />
                {event.viewerStatus === 'going'
                    ? 'Going'
                    : event.isFull
                      ? 'Full'
                      : 'Going'}
            </button>
            <button
                type="button"
                onClick={() => respond('interested')}
                disabled={processing || !event.canRsvp}
                aria-pressed={event.viewerStatus === 'interested'}
                className={`social-focus inline-flex min-h-11 items-center gap-2 rounded-xl px-3.5 text-sm font-extrabold transition-colors disabled:cursor-not-allowed disabled:opacity-50 ${
                    event.viewerStatus === 'interested'
                        ? 'bg-mint text-ink'
                        : 'bg-secondary text-foreground hover:bg-secondary/75'
                }`}
            >
                <Heart
                    className={`size-4 ${event.viewerStatus === 'interested' ? 'fill-current' : ''}`}
                    aria-hidden="true"
                />
                Interested
            </button>
            {event.canRemoveRsvp && (
                <button
                    type="button"
                    onClick={clear}
                    disabled={processing}
                    className="social-focus min-h-11 rounded-xl px-3 text-xs font-bold text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground disabled:opacity-50"
                >
                    Clear RSVP
                </button>
            )}
        </div>
    );
}

export function SpaceEventCard({
    event,
    compact = false,
    showRsvp = true,
}: {
    event: SpaceEventSummary;
    compact?: boolean;
    showRsvp?: boolean;
}) {
    const date = eventDate(event);
    const attendanceTotal = event.goingCount + event.interestedCount;
    const capacityPercent =
        event.capacity === null
            ? null
            : Math.min(
                  100,
                  Math.round((event.goingCount / event.capacity) * 100),
              );

    return (
        <article
            className={`social-card social-card-interactive overflow-hidden ${compact ? 'rounded-[1.25rem] p-4' : 'rounded-[1.5rem] p-4 sm:p-5'}`}
        >
            <div className="flex items-start gap-3.5 sm:gap-4">
                <time
                    dateTime={event.startsAt}
                    className="flex w-[3.65rem] shrink-0 flex-col overflow-hidden rounded-2xl border bg-background text-center shadow-[0_8px_24px_-20px_rgba(15,23,42,.75)]"
                >
                    <span className="bg-coral px-1 py-1.5 text-[0.62rem] font-black tracking-[0.1em] text-white uppercase">
                        {date.month}
                    </span>
                    <span className="pt-1.5 text-xl leading-none font-black tracking-[-0.04em]">
                        {date.day}
                    </span>
                    <span className="pb-1.5 text-[0.62rem] font-bold text-muted-foreground uppercase">
                        {date.weekday}
                    </span>
                </time>

                <div className="min-w-0 flex-1">
                    <div className="flex items-start justify-between gap-3">
                        <div className="min-w-0">
                            <p className="text-[0.66rem] font-extrabold tracking-[0.12em] text-primary uppercase">
                                {event.space.name}
                            </p>
                            <h3 className="mt-1 text-lg leading-6 font-black tracking-[-0.025em] sm:text-xl">
                                <Link
                                    href={event.url}
                                    className="social-focus rounded-md hover:text-primary"
                                >
                                    {event.title}
                                </Link>
                            </h3>
                        </div>
                        <Link
                            href={event.url}
                            aria-label={`Open ${event.title}`}
                            className="social-focus inline-flex size-10 shrink-0 items-center justify-center rounded-xl bg-secondary text-muted-foreground transition-colors hover:bg-primary/10 hover:text-primary"
                        >
                            <ArrowUpRight
                                className="size-4"
                                aria-hidden="true"
                            />
                        </Link>
                    </div>

                    <div className="mt-3 space-y-2 text-xs font-semibold text-muted-foreground sm:text-sm">
                        <p className="flex items-start gap-2">
                            <Clock3
                                className="mt-0.5 size-4 shrink-0"
                                aria-hidden="true"
                            />
                            <span>{eventTimeLabel(event)}</span>
                        </p>
                        {event.venue && (
                            <p className="flex items-start gap-2">
                                <MapPin
                                    className="mt-0.5 size-4 shrink-0"
                                    aria-hidden="true"
                                />
                                <span>{event.venue}</span>
                            </p>
                        )}
                        {event.onlineUrl && (
                            <p className="flex items-center gap-2">
                                <Radio
                                    className="size-4 shrink-0"
                                    aria-hidden="true"
                                />
                                <span>Online access available</span>
                            </p>
                        )}
                    </div>
                </div>
            </div>

            {!compact && event.description && (
                <p className="mt-4 line-clamp-3 text-sm leading-6 text-foreground/80">
                    {event.description}
                </p>
            )}

            <div className="mt-4 flex flex-wrap items-center justify-between gap-3 border-t pt-3.5">
                <div className="flex flex-wrap items-center gap-3 text-xs font-bold text-muted-foreground">
                    <span className="inline-flex items-center gap-1.5">
                        <UsersRound className="size-4" aria-hidden="true" />
                        {event.goingCount.toLocaleString()} going
                    </span>
                    {event.interestedCount > 0 && (
                        <span>
                            {event.interestedCount.toLocaleString()} interested
                        </span>
                    )}
                    {event.capacity !== null && (
                        <span>
                            {Math.max(
                                0,
                                event.capacity - event.goingCount,
                            ).toLocaleString()}{' '}
                            spots left
                        </span>
                    )}
                </div>
                {attendanceTotal === 0 && (
                    <span className="text-[0.68rem] font-bold text-muted-foreground">
                        Be the first to respond
                    </span>
                )}
            </div>

            {capacityPercent !== null && (
                <div
                    className="mt-3 h-1.5 overflow-hidden rounded-full bg-secondary"
                    role="progressbar"
                    aria-label="Event capacity"
                    aria-valuenow={event.goingCount}
                    aria-valuemin={0}
                    aria-valuemax={event.capacity ?? undefined}
                >
                    <div
                        className="h-full rounded-full bg-primary transition-[width] duration-200"
                        style={{ width: `${capacityPercent}%` }}
                    />
                </div>
            )}

            {showRsvp && <EventRsvpControls event={event} compact />}
        </article>
    );
}

export function SpaceEventsPreview({
    events,
    spaceSlug,
    canManage,
}: {
    events: SpaceEventSummary[];
    spaceSlug: string;
    canManage: boolean;
}) {
    if (events.length === 0 && !canManage) {
        return null;
    }

    return (
        <section className="mb-4 sm:mb-5" aria-labelledby="space-events-title">
            <div className="mb-3 flex items-end justify-between gap-3 px-1">
                <div>
                    <p className="text-[0.68rem] font-extrabold tracking-[0.15em] text-coral uppercase">
                        Meet beyond the feed
                    </p>
                    <h2
                        id="space-events-title"
                        className="mt-0.5 text-lg font-black tracking-tight"
                    >
                        Upcoming events
                    </h2>
                </div>
                <Link
                    href={`/spaces/${spaceSlug}/events`}
                    className="social-focus inline-flex min-h-9 items-center gap-1.5 rounded-full px-3 text-xs font-extrabold text-primary transition-colors hover:bg-primary/8"
                >
                    {events.length === 0 ? 'Create event' : 'See all'}
                    <CalendarDays className="size-3.5" aria-hidden="true" />
                </Link>
            </div>
            {events.length === 0 ? (
                <Link
                    href={`/spaces/${spaceSlug}/events#new-event`}
                    className="social-card social-focus flex min-h-24 items-center gap-4 rounded-[1.25rem] p-4 transition-colors hover:border-primary/25"
                >
                    <span className="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-coral/10 text-coral">
                        <CalendarDays className="size-5" aria-hidden="true" />
                    </span>
                    <span>
                        <span className="block text-sm font-black">
                            Plan the first gathering
                        </span>
                        <span className="mt-1 block text-xs leading-5 text-muted-foreground">
                            Publish an official Space event and let members RSVP
                            privately.
                        </span>
                    </span>
                </Link>
            ) : (
                <div className="-mx-3 flex snap-x scroll-px-4 [scrollbar-width:none] gap-3 overflow-x-auto px-4 pb-1 sm:mx-0 sm:grid sm:grid-cols-1 sm:px-0 lg:grid-cols-2 [&::-webkit-scrollbar]:hidden">
                    {events.map((event) => (
                        <div
                            key={event.id}
                            className="w-[19rem] shrink-0 snap-start sm:w-auto"
                        >
                            <SpaceEventCard event={event} compact />
                        </div>
                    ))}
                </div>
            )}
        </section>
    );
}

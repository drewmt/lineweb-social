import { Head, Link, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    CalendarDays,
    Clock3,
    Globe2,
    MapPin,
    Radio,
    ShieldCheck,
    UsersRound,
} from 'lucide-react';
import { useEffect } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { SpaceEventCard } from '@/components/social/space-event-card';
import type { SpaceEventSummary } from '@/components/social/space-event-card';
import { Button } from '@/components/ui/button';

type SpaceSummary = {
    name: string;
    slug: string;
    description: string | null;
    visibility: 'public' | 'private' | 'hidden';
    memberCount: number;
    isMember: boolean;
    isOwner: boolean;
    canManage: boolean;
};

type EventsIndexProps = {
    space: SpaceSummary;
    upcomingEvents: SpaceEventSummary[];
    pastEvents: SpaceEventSummary[];
    status?: string;
};

function CreateEventForm({ space }: { space: SpaceSummary }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        title: '',
        description: '',
        starts_at: '',
        ends_at: '',
        timezone: 'UTC',
        venue: '',
        online_url: '',
        capacity: '',
    });

    useEffect(() => {
        const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone;

        if (timezone) {
            setData('timezone', timezone);
        }
    }, [setData]);

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        post(`/spaces/${space.slug}/events`, {
            preserveScroll: true,
            onSuccess: () => reset(),
        });
    };

    return (
        <aside
            id="new-event"
            className="social-card scroll-mt-24 rounded-[1.5rem] p-4 sm:p-5 lg:sticky lg:top-6"
            aria-labelledby="new-event-title"
        >
            <div className="flex items-start gap-3">
                <span className="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-coral text-white shadow-[0_14px_26px_-18px_rgba(244,95,76,.9)]">
                    <CalendarDays className="size-5" aria-hidden="true" />
                </span>
                <div>
                    <p className="text-[0.66rem] font-extrabold tracking-[0.14em] text-coral uppercase">
                        Official Space event
                    </p>
                    <h2
                        id="new-event-title"
                        className="mt-0.5 text-xl font-black tracking-tight"
                    >
                        Plan a gathering
                    </h2>
                </div>
            </div>
            <p className="mt-3 text-sm leading-6 text-muted-foreground">
                RSVPs stay private. Members see only aggregate attendance and
                remaining capacity.
            </p>

            <form onSubmit={submit} className="mt-5 space-y-4">
                <label className="block">
                    <span className="text-sm font-extrabold">Event title</span>
                    <input
                        value={data.title}
                        onChange={(event) =>
                            setData('title', event.target.value)
                        }
                        required
                        minLength={3}
                        maxLength={120}
                        placeholder="Open source community night"
                        className="social-inset social-focus mt-2 h-11 w-full px-3.5 text-sm"
                    />
                    <InputError className="mt-2" message={errors.title} />
                </label>

                <label className="block">
                    <span className="text-sm font-extrabold">
                        What will happen?
                    </span>
                    <textarea
                        value={data.description}
                        onChange={(event) =>
                            setData('description', event.target.value)
                        }
                        maxLength={2000}
                        rows={4}
                        placeholder="Set clear expectations, format, and anything members should bring."
                        className="social-inset social-focus mt-2 w-full resize-y px-3.5 py-3 text-sm leading-6"
                    />
                    <div className="mt-1.5 flex justify-between gap-3">
                        <InputError message={errors.description} />
                        <span className="ml-auto text-xs text-muted-foreground">
                            {data.description.length}/2000
                        </span>
                    </div>
                </label>

                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2">
                    <label className="block">
                        <span className="flex items-center gap-1.5 text-sm font-extrabold">
                            <Clock3
                                className="size-4 text-primary"
                                aria-hidden="true"
                            />{' '}
                            Start
                        </span>
                        <input
                            type="datetime-local"
                            value={data.starts_at}
                            onChange={(event) =>
                                setData('starts_at', event.target.value)
                            }
                            required
                            className="social-inset social-focus mt-2 h-11 w-full px-3 text-sm"
                        />
                        <InputError
                            className="mt-2"
                            message={errors.starts_at}
                        />
                    </label>
                    <label className="block">
                        <span className="text-sm font-extrabold">End</span>
                        <input
                            type="datetime-local"
                            value={data.ends_at}
                            onChange={(event) =>
                                setData('ends_at', event.target.value)
                            }
                            required
                            className="social-inset social-focus mt-2 h-11 w-full px-3 text-sm"
                        />
                        <InputError className="mt-2" message={errors.ends_at} />
                    </label>
                </div>

                <input type="hidden" name="timezone" value={data.timezone} />
                <InputError message={errors.timezone} />
                <p className="-mt-2 text-[0.68rem] font-semibold text-muted-foreground">
                    Times will be published in{' '}
                    {data.timezone.replaceAll('_', ' ')}.
                </p>

                <label className="block">
                    <span className="flex items-center gap-1.5 text-sm font-extrabold">
                        <MapPin
                            className="size-4 text-primary"
                            aria-hidden="true"
                        />{' '}
                        Venue
                    </span>
                    <input
                        value={data.venue}
                        onChange={(event) =>
                            setData('venue', event.target.value)
                        }
                        maxLength={160}
                        placeholder="Venue or meeting point"
                        className="social-inset social-focus mt-2 h-11 w-full px-3.5 text-sm"
                    />
                    <InputError className="mt-2" message={errors.venue} />
                </label>

                <label className="block">
                    <span className="flex items-center gap-1.5 text-sm font-extrabold">
                        <Radio
                            className="size-4 text-primary"
                            aria-hidden="true"
                        />{' '}
                        Online link
                    </span>
                    <input
                        type="url"
                        value={data.online_url}
                        onChange={(event) =>
                            setData('online_url', event.target.value)
                        }
                        maxLength={2048}
                        placeholder="https://…"
                        className="social-inset social-focus mt-2 h-11 w-full px-3.5 text-sm"
                    />
                    <InputError className="mt-2" message={errors.online_url} />
                    <span className="mt-1.5 block text-[0.68rem] leading-4 text-muted-foreground">
                        Add a venue, a secure online link, or both. The platform
                        never fetches this URL.
                    </span>
                </label>

                <label className="block">
                    <span className="flex items-center gap-1.5 text-sm font-extrabold">
                        <UsersRound
                            className="size-4 text-primary"
                            aria-hidden="true"
                        />{' '}
                        Capacity
                    </span>
                    <input
                        type="number"
                        inputMode="numeric"
                        min={2}
                        max={10000}
                        value={data.capacity}
                        onChange={(event) =>
                            setData('capacity', event.target.value)
                        }
                        placeholder="Unlimited"
                        className="social-inset social-focus mt-2 h-11 w-full px-3.5 text-sm"
                    />
                    <InputError className="mt-2" message={errors.capacity} />
                </label>

                <Button
                    type="submit"
                    disabled={processing}
                    className="min-h-11 w-full"
                >
                    <CalendarDays className="size-4" aria-hidden="true" />
                    {processing ? 'Publishing…' : 'Publish event'}
                </Button>
            </form>
        </aside>
    );
}

export default function EventsIndex({
    space,
    upcomingEvents,
    pastEvents,
    status,
}: EventsIndexProps) {
    return (
        <>
            <Head title={`${space.name} events`} />
            <main className="social-page">
                <div className="mx-auto max-w-6xl">
                    <header className="relative mb-5 overflow-hidden rounded-[1.75rem] border bg-foreground px-5 py-6 text-background sm:px-7 sm:py-8">
                        <div
                            className="pointer-events-none absolute top-0 right-0 h-full w-56 opacity-20"
                            aria-hidden="true"
                        >
                            <div className="absolute top-7 right-10 size-24 rounded-full border-[1.4rem] border-mint" />
                            <div className="absolute right-28 bottom-5 size-16 rotate-12 rounded-[1.25rem] bg-coral" />
                        </div>
                        <div className="relative">
                            <Link
                                href={`/spaces/${space.slug}`}
                                className="social-focus inline-flex min-h-9 items-center gap-2 rounded-full text-sm font-bold text-mint hover:underline"
                            >
                                <ArrowLeft
                                    className="size-4"
                                    aria-hidden="true"
                                />{' '}
                                Back to space
                            </Link>
                            <p className="mt-5 text-[0.68rem] font-extrabold tracking-[0.15em] text-coral uppercase">
                                Meet beyond the timeline
                            </p>
                            <h1 className="mt-1 max-w-3xl text-3xl font-black tracking-[-0.05em] sm:text-5xl">
                                {space.name} events
                            </h1>
                            <p className="mt-3 max-w-2xl text-sm leading-6 text-background/68 sm:text-base">
                                Real gatherings for this community, ordered by
                                time — with private member RSVPs and no attendee
                                directory.
                            </p>
                            <div className="mt-5 flex flex-wrap gap-2 text-[0.68rem] font-extrabold text-background/75">
                                <span className="rounded-full bg-white/[0.08] px-3 py-1.5">
                                    {upcomingEvents.length} upcoming
                                </span>
                                <span className="rounded-full bg-white/[0.08] px-3 py-1.5">
                                    {space.visibility === 'public' ? (
                                        <Globe2 className="mr-1 inline size-3" />
                                    ) : (
                                        <ShieldCheck className="mr-1 inline size-3" />
                                    )}
                                    {space.visibility} space
                                </span>
                            </div>
                        </div>
                    </header>

                    {status && (
                        <div
                            role="status"
                            className="mb-5 rounded-2xl border border-primary/20 bg-primary/8 px-4 py-3 text-sm font-bold"
                        >
                            {status}
                        </div>
                    )}

                    <div
                        className={`grid items-start gap-5 ${space.canManage ? 'lg:grid-cols-[minmax(0,1fr)_23rem]' : ''}`}
                    >
                        <div className="min-w-0 space-y-8">
                            <section aria-labelledby="upcoming-title">
                                <div className="mb-4 flex items-end justify-between gap-3 px-1">
                                    <div>
                                        <p className="text-[0.66rem] font-extrabold tracking-[0.14em] text-primary uppercase">
                                            On the calendar
                                        </p>
                                        <h2
                                            id="upcoming-title"
                                            className="mt-0.5 text-xl font-black tracking-tight"
                                        >
                                            Upcoming
                                        </h2>
                                    </div>
                                </div>
                                {upcomingEvents.length === 0 ? (
                                    <div className="social-card rounded-[1.4rem] px-6 py-12 text-center">
                                        <span className="mx-auto flex size-14 items-center justify-center rounded-2xl bg-coral/10 text-coral">
                                            <CalendarDays
                                                className="size-6"
                                                aria-hidden="true"
                                            />
                                        </span>
                                        <h3 className="mt-4 text-lg font-black">
                                            Nothing scheduled yet.
                                        </h3>
                                        <p className="mx-auto mt-2 max-w-md text-sm leading-6 text-muted-foreground">
                                            {space.canManage
                                                ? 'Use the event studio to give members something meaningful to join.'
                                                : 'Space organizers have not published the next gathering yet.'}
                                        </p>
                                    </div>
                                ) : (
                                    <div className="space-y-3">
                                        {upcomingEvents.map((event) => (
                                            <SpaceEventCard
                                                key={event.id}
                                                event={event}
                                            />
                                        ))}
                                    </div>
                                )}
                            </section>

                            {pastEvents.length > 0 && (
                                <section aria-labelledby="past-title">
                                    <div className="mb-4 px-1">
                                        <p className="text-[0.66rem] font-extrabold tracking-[0.14em] text-muted-foreground uppercase">
                                            Community memory
                                        </p>
                                        <h2
                                            id="past-title"
                                            className="mt-0.5 text-xl font-black tracking-tight"
                                        >
                                            Past events
                                        </h2>
                                    </div>
                                    <div className="grid gap-3 sm:grid-cols-2">
                                        {pastEvents.map((event) => (
                                            <SpaceEventCard
                                                key={event.id}
                                                event={event}
                                                compact
                                                showRsvp={false}
                                            />
                                        ))}
                                    </div>
                                </section>
                            )}
                        </div>
                        {space.canManage && <CreateEventForm space={space} />}
                    </div>
                </div>
            </main>
        </>
    );
}

EventsIndex.layout = {
    breadcrumbs: [
        { title: 'Spaces', href: '/spaces' },
        { title: 'Events', href: '#' },
    ],
};

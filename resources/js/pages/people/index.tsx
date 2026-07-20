import { Head, Link } from '@inertiajs/react';
import { ArrowRight, MapPin, Search, UsersRound } from 'lucide-react';
import { useMemo, useState } from 'react';
import { AvatarMark } from '@/components/social/avatar-mark';
import { CommunitySignal } from '@/components/social/community-signal';

type Person = {
    name: string;
    handle: string;
    headline: string | null;
    bio: string | null;
    location: string | null;
    sharedSpaceCount: number;
};

export default function People({ people }: { people: Person[] }) {
    const [query, setQuery] = useState('');
    const filteredPeople = useMemo(() => {
        const normalized = query.trim().toLocaleLowerCase();

        if (normalized === '') {
            return people;
        }

        return people.filter(
            (person) =>
                person.name.toLocaleLowerCase().includes(normalized) ||
                person.handle.toLocaleLowerCase().includes(normalized) ||
                person.headline?.toLocaleLowerCase().includes(normalized) ===
                    true ||
                person.bio?.toLocaleLowerCase().includes(normalized) === true ||
                person.location?.toLocaleLowerCase().includes(normalized) ===
                    true,
        );
    }, [people, query]);

    return (
        <>
            <Head title="People" />
            <main className="social-page">
                <section
                    className="social-card relative mb-6 min-h-64 overflow-hidden rounded-[1.8rem] bg-foreground p-6 text-background sm:flex sm:items-end sm:p-9 lg:min-h-72"
                    aria-labelledby="people-title"
                >
                    <img
                        src="/images/people-community.webp"
                        alt="Community members having a conversation"
                        className="absolute inset-y-0 right-0 hidden h-full w-[52%] [mask-image:linear-gradient(to_right,transparent,black_24%)] object-cover object-[62%_center] opacity-90 sm:block"
                    />
                    <div className="absolute inset-0 bg-[radial-gradient(circle_at_18%_0%,oklch(0.52_0.2_263_/_0.45),transparent_48%)]" />
                    <div className="relative z-10 max-w-xl">
                        <div className="flex items-center gap-2 text-xs font-extrabold tracking-[0.14em] text-mint uppercase">
                            <CommunitySignal />
                            People, not metrics
                        </div>
                        <h1
                            id="people-title"
                            className="mt-3 text-4xl leading-[0.94] font-black tracking-[-0.055em] sm:text-5xl"
                        >
                            Meet the humans
                            <br />
                            behind the spaces.
                        </h1>
                        <p className="mt-4 max-w-lg text-sm leading-6 text-background/65 sm:text-base">
                            Discover members who chose to be found and whose
                            privacy settings allow you to connect.
                        </p>
                    </div>
                </section>

                <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <label className="social-input-surface flex h-12 w-full items-center gap-3 px-4 sm:max-w-md sm:flex-1">
                        <Search
                            className="size-4 shrink-0 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <span className="sr-only">Search people</span>
                        <input
                            type="search"
                            value={query}
                            onChange={(event) => setQuery(event.target.value)}
                            placeholder="Search people"
                            className="min-w-0 flex-1 bg-transparent text-sm outline-none placeholder:text-muted-foreground"
                        />
                    </label>
                    <div className="flex items-center gap-2 px-1 text-xs font-bold text-muted-foreground">
                        <span className="size-2 rounded-full bg-mint ring-4 ring-mint/20" />
                        {filteredPeople.length.toLocaleString()}{' '}
                        {filteredPeople.length === 1 ? 'person' : 'people'}{' '}
                        visible to you
                    </div>
                </div>

                {filteredPeople.length === 0 ? (
                    <section className="social-card rounded-[1.35rem] px-6 py-14 text-center">
                        <UsersRound
                            className="mx-auto size-8 text-primary"
                            aria-hidden="true"
                        />
                        <h2 className="mt-4 text-lg font-extrabold">
                            No matching people
                        </h2>
                        <p className="mx-auto mt-2 max-w-md text-sm leading-6 text-muted-foreground">
                            Try a different search. Private and undiscoverable
                            profiles never appear here.
                        </p>
                    </section>
                ) : (
                    <section
                        className="grid gap-3 sm:grid-cols-2 2xl:grid-cols-3"
                        aria-label="Discoverable people"
                    >
                        {filteredPeople.map((person) => (
                            <article
                                key={person.handle}
                                className="social-card social-card-interactive group flex min-h-72 flex-col rounded-[1.45rem] p-4 sm:p-5"
                            >
                                <div className="flex items-start gap-3">
                                    <AvatarMark
                                        name={person.name}
                                        className="size-14 text-base"
                                    />
                                    <div className="min-w-0 flex-1 pt-1">
                                        <h2 className="truncate text-lg font-black tracking-tight">
                                            {person.name}
                                        </h2>
                                        <p className="truncate text-sm font-bold text-primary">
                                            @{person.handle}
                                        </p>
                                    </div>
                                </div>
                                {person.headline && (
                                    <p className="mt-4 line-clamp-2 text-sm leading-5 font-extrabold text-foreground/85">
                                        {person.headline}
                                    </p>
                                )}
                                {person.bio && (
                                    <p className="mt-2 line-clamp-2 text-sm leading-6 text-muted-foreground">
                                        {person.bio}
                                    </p>
                                )}
                                <div className="mt-4 flex min-h-6 flex-wrap items-center gap-x-3 gap-y-1 text-xs font-bold text-muted-foreground">
                                    {person.location && (
                                        <span className="inline-flex items-center gap-1">
                                            <MapPin
                                                className="size-3.5"
                                                aria-hidden="true"
                                            />
                                            {person.location}
                                        </span>
                                    )}
                                    {person.sharedSpaceCount > 0 && (
                                        <span>
                                            {person.sharedSpaceCount} shared{' '}
                                            {person.sharedSpaceCount === 1
                                                ? 'space'
                                                : 'spaces'}
                                        </span>
                                    )}
                                </div>
                                <Link
                                    href={`/people/${person.handle}`}
                                    className="social-inset social-focus mt-auto flex min-h-11 items-center justify-between px-4 py-2.5 text-sm font-extrabold text-primary transition-[color,border-color,background-color] group-hover:border-primary/20 group-hover:bg-primary/8"
                                >
                                    View profile{' '}
                                    <ArrowRight
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                </Link>
                            </article>
                        ))}
                    </section>
                )}
            </main>
        </>
    );
}

People.layout = { breadcrumbs: [{ title: 'People', href: '/people' }] };

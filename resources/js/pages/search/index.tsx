import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowRight,
    Compass,
    Globe2,
    Hash,
    LockKeyhole,
    LoaderCircle,
    MapPin,
    MessageSquareText,
    Search as SearchIcon,
    ShieldCheck,
    UsersRound,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { AvatarMark } from '@/components/social/avatar-mark';
import { SpaceCover } from '@/components/social/space-cover';
import { Button } from '@/components/ui/button';

type SearchPost = {
    id: number;
    url: string;
    body: string;
    publishedAt: string | null;
    author: {
        name: string;
        handle: string;
        profileVisible: boolean;
    };
    space: {
        name: string;
        slug: string;
    };
};

type SearchSpace = {
    name: string;
    slug: string;
    description: string | null;
    visibility: 'public' | 'private' | 'hidden';
    memberCount: number;
    isMember: boolean;
};

type SearchPerson = {
    name: string;
    handle: string;
    headline: string | null;
    bio: string | null;
    location: string | null;
    sharedSpaceCount: number;
};

type SearchTopic = {
    name: string;
    url: string;
    visiblePostCount: number;
};

type SearchResults = {
    posts: SearchPost[];
    spaces: SearchSpace[];
    people: SearchPerson[];
    topics: SearchTopic[];
};

type SearchProps = {
    query: string;
    type: SearchType;
    minimumQueryLength: number;
    results: SearchResults;
    pagination: {
        currentPage: number;
        previousUrl: string | null;
        nextUrl: string | null;
        limitReached: boolean;
    } | null;
};

type SearchType = 'all' | keyof SearchResults;

const searchTypes = [
    { value: 'all', label: 'All', icon: SearchIcon },
    { value: 'posts', label: 'Posts', icon: MessageSquareText },
    { value: 'spaces', label: 'Spaces', icon: Compass },
    { value: 'people', label: 'People', icon: UsersRound },
    { value: 'topics', label: 'Topics', icon: Hash },
] as const;

const searchUrl = (query: string, type: SearchType) =>
    `/search?${new URLSearchParams({ q: query, type })}`;

const visibilityDetails = {
    public: { label: 'Public', icon: Globe2 },
    private: { label: 'Private', icon: LockKeyhole },
    hidden: { label: 'Hidden', icon: ShieldCheck },
} as const;

const publishedLabel = (value: string | null) => {
    if (!value) {
        return 'Recently';
    }

    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
    }).format(new Date(value));
};

function SearchStart({ minimumQueryLength }: { minimumQueryLength: number }) {
    const discoveryAreas = [
        {
            icon: MessageSquareText,
            title: 'Conversations',
            copy: 'Find useful posts across every Space you can currently open.',
        },
        {
            icon: Compass,
            title: 'Spaces',
            copy: 'Move quickly between public communities and your private circles.',
        },
        {
            icon: UsersRound,
            title: 'People',
            copy: 'Discover members who chose to be found and are visible to you.',
        },
        {
            icon: Hash,
            title: 'Topics',
            copy: 'Follow themes across the conversations you can currently open.',
        },
    ];

    return (
        <section
            className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4"
            aria-label="Search areas"
        >
            {discoveryAreas.map((area, index) => {
                const Icon = area.icon;

                return (
                    <article
                        key={area.title}
                        className="social-card rounded-[1.35rem] p-5 sm:p-6"
                    >
                        <div className="flex items-start justify-between gap-4">
                            <span className="flex size-11 items-center justify-center rounded-2xl bg-secondary text-primary">
                                <Icon className="size-5" aria-hidden="true" />
                            </span>
                            <span className="text-xs font-black text-muted-foreground/55 tabular-nums">
                                0{index + 1}
                            </span>
                        </div>
                        <h2 className="mt-7 text-lg font-black tracking-tight">
                            {area.title}
                        </h2>
                        <p className="mt-2 text-sm leading-6 text-muted-foreground">
                            {area.copy}
                        </p>
                    </article>
                );
            })}
            <p className="sr-only">
                Enter at least {minimumQueryLength} characters to search.
            </p>
        </section>
    );
}

function PostResults({ posts }: { posts: SearchPost[] }) {
    return (
        <section aria-labelledby="post-results-title">
            <div className="mb-3 flex items-end justify-between px-1">
                <div>
                    <p className="social-eyebrow">Conversations</p>
                    <h2
                        id="post-results-title"
                        className="mt-1 text-2xl font-black tracking-[-0.035em]"
                    >
                        Posts worth opening
                    </h2>
                </div>
                <span className="text-xs font-extrabold text-muted-foreground tabular-nums">
                    {posts.length} shown
                </span>
            </div>

            {posts.length === 0 ? (
                <div className="social-card rounded-[1.35rem] p-6 text-sm leading-6 text-muted-foreground">
                    No visible conversations match this search.
                </div>
            ) : (
                <div className="space-y-3">
                    {posts.map((post) => (
                        <article
                            key={post.id}
                            className="social-card social-card-interactive overflow-hidden rounded-[1.35rem]"
                        >
                            <div className="p-4 sm:p-5">
                                <div className="flex items-start gap-3">
                                    <AvatarMark
                                        name={post.author.name}
                                        className="size-11 shrink-0 text-xs"
                                    />
                                    <div className="min-w-0 flex-1">
                                        <div className="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                                            {post.author.profileVisible ? (
                                                <Link
                                                    href={`/people/${post.author.handle}`}
                                                    className="social-focus rounded-md font-extrabold hover:text-primary"
                                                >
                                                    {post.author.name}
                                                </Link>
                                            ) : (
                                                <span className="font-extrabold">
                                                    {post.author.name}
                                                </span>
                                            )}
                                            <span className="text-xs font-semibold text-muted-foreground">
                                                {publishedLabel(
                                                    post.publishedAt,
                                                )}
                                            </span>
                                        </div>
                                        <Link
                                            href={`/spaces/${post.space.slug}`}
                                            className="social-focus mt-0.5 inline-flex rounded-md text-xs font-bold text-primary hover:underline"
                                        >
                                            {post.space.name}
                                        </Link>
                                    </div>
                                </div>

                                <p className="mt-4 line-clamp-4 text-[0.95rem] leading-7 text-foreground/88">
                                    {post.body}
                                </p>
                            </div>
                            <Link
                                href={post.url}
                                className="social-focus group flex min-h-12 items-center justify-between border-t border-border/65 bg-secondary/38 px-4 text-sm font-extrabold transition-colors hover:bg-primary/[0.07] hover:text-primary sm:px-5"
                            >
                                Open conversation
                                <ArrowRight
                                    className="size-4 transition-transform group-hover:translate-x-0.5"
                                    aria-hidden="true"
                                />
                            </Link>
                        </article>
                    ))}
                </div>
            )}
        </section>
    );
}

function TopicResults({ topics }: { topics: SearchTopic[] }) {
    return (
        <section aria-labelledby="topic-results-title">
            <div className="mb-3 flex items-center justify-between px-1">
                <h2
                    id="topic-results-title"
                    className="text-lg font-black tracking-tight"
                >
                    Topics
                </h2>
                <span className="text-xs font-extrabold text-muted-foreground tabular-nums">
                    {topics.length}
                </span>
            </div>
            {topics.length === 0 ? (
                <div className="social-card rounded-[1.35rem] p-5 text-sm leading-6 text-muted-foreground">
                    No visible topics match.
                </div>
            ) : (
                <div className="social-card grid gap-px overflow-hidden rounded-[1.35rem] bg-border/60 sm:grid-cols-2 lg:grid-cols-1">
                    {topics.map((topic) => (
                        <Link
                            key={topic.name}
                            href={topic.url}
                            className="social-focus group flex min-h-20 items-center gap-3 bg-card p-3.5 transition-colors hover:bg-primary/[0.055]"
                        >
                            <span className="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-primary/10 text-primary transition-transform group-hover:scale-[1.03]">
                                <Hash
                                    className="size-5"
                                    strokeWidth={2.4}
                                    aria-hidden="true"
                                />
                            </span>
                            <span className="min-w-0 flex-1">
                                <span className="block truncate font-black tracking-tight">
                                    #{topic.name}
                                </span>
                                <span className="mt-0.5 block text-xs font-semibold text-muted-foreground">
                                    {topic.visiblePostCount.toLocaleString()}{' '}
                                    visible{' '}
                                    {topic.visiblePostCount === 1
                                        ? 'conversation'
                                        : 'conversations'}
                                </span>
                            </span>
                            <ArrowRight
                                className="size-4 shrink-0 text-muted-foreground transition-transform group-hover:translate-x-0.5 group-hover:text-primary"
                                aria-hidden="true"
                            />
                        </Link>
                    ))}
                </div>
            )}
        </section>
    );
}

function SpaceResults({ spaces }: { spaces: SearchSpace[] }) {
    return (
        <section aria-labelledby="space-results-title">
            <div className="mb-3 flex items-center justify-between px-1">
                <h2
                    id="space-results-title"
                    className="text-lg font-black tracking-tight"
                >
                    Spaces
                </h2>
                <span className="text-xs font-extrabold text-muted-foreground tabular-nums">
                    {spaces.length}
                </span>
            </div>
            {spaces.length === 0 ? (
                <div className="social-card rounded-[1.35rem] p-5 text-sm leading-6 text-muted-foreground">
                    No accessible Spaces match.
                </div>
            ) : (
                <div className="social-card overflow-hidden rounded-[1.35rem]">
                    {spaces.map((space) => {
                        const visibility = visibilityDetails[space.visibility];
                        const VisibilityIcon = visibility.icon;

                        return (
                            <Link
                                key={space.slug}
                                href={`/spaces/${space.slug}`}
                                className="social-focus group grid min-h-28 grid-cols-[5rem_minmax(0,1fr)] gap-3 p-3 transition-colors hover:bg-secondary/48"
                            >
                                <span className="relative overflow-hidden rounded-2xl bg-secondary">
                                    <SpaceCover
                                        seed={space.slug}
                                        className="absolute inset-0 transition-transform duration-300 group-hover:scale-105"
                                    />
                                </span>
                                <span className="min-w-0 py-1">
                                    <span className="flex items-start justify-between gap-2">
                                        <span className="line-clamp-1 font-black tracking-tight">
                                            {space.name}
                                        </span>
                                        <ArrowRight
                                            className="mt-0.5 size-4 shrink-0 text-muted-foreground transition-transform group-hover:translate-x-0.5 group-hover:text-primary"
                                            aria-hidden="true"
                                        />
                                    </span>
                                    <span className="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-[0.68rem] font-bold text-muted-foreground">
                                        <span className="inline-flex items-center gap-1">
                                            <VisibilityIcon
                                                className="size-3"
                                                aria-hidden="true"
                                            />
                                            {visibility.label}
                                        </span>
                                        <span>
                                            {space.memberCount.toLocaleString()}{' '}
                                            {space.memberCount === 1
                                                ? 'member'
                                                : 'members'}
                                        </span>
                                        {space.isMember && (
                                            <span className="text-primary">
                                                Joined
                                            </span>
                                        )}
                                    </span>
                                    {space.description && (
                                        <span className="mt-1.5 line-clamp-1 block text-xs text-muted-foreground">
                                            {space.description}
                                        </span>
                                    )}
                                </span>
                            </Link>
                        );
                    })}
                </div>
            )}
        </section>
    );
}

function PeopleResults({ people }: { people: SearchPerson[] }) {
    return (
        <section aria-labelledby="people-results-title">
            <div className="mb-3 flex items-center justify-between px-1">
                <h2
                    id="people-results-title"
                    className="text-lg font-black tracking-tight"
                >
                    People
                </h2>
                <span className="text-xs font-extrabold text-muted-foreground tabular-nums">
                    {people.length}
                </span>
            </div>
            {people.length === 0 ? (
                <div className="social-card rounded-[1.35rem] p-5 text-sm leading-6 text-muted-foreground">
                    No discoverable people match.
                </div>
            ) : (
                <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-1">
                    {people.map((person) => (
                        <Link
                            key={person.handle}
                            href={`/people/${person.handle}`}
                            className="social-card social-card-interactive social-focus group flex min-h-24 items-center gap-3 rounded-[1.35rem] p-3.5"
                        >
                            <AvatarMark
                                name={person.name}
                                className="size-12 shrink-0 text-sm"
                            />
                            <span className="min-w-0 flex-1">
                                <span className="block truncate font-black tracking-tight">
                                    {person.name}
                                </span>
                                <span className="block truncate text-xs font-bold text-primary">
                                    @{person.handle}
                                </span>
                                {(person.headline ?? person.bio) && (
                                    <span className="mt-1 line-clamp-1 block text-xs font-semibold text-foreground/75">
                                        {person.headline ?? person.bio}
                                    </span>
                                )}
                                <span className="mt-1 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-[0.68rem] font-semibold text-muted-foreground">
                                    {person.location && (
                                        <span className="inline-flex items-center gap-1">
                                            <MapPin
                                                className="size-3"
                                                aria-hidden="true"
                                            />
                                            {person.location}
                                        </span>
                                    )}
                                    {person.sharedSpaceCount > 0 && (
                                        <span>
                                            {person.sharedSpaceCount} shared{' '}
                                            {person.sharedSpaceCount === 1
                                                ? 'Space'
                                                : 'Spaces'}
                                        </span>
                                    )}
                                </span>
                            </span>
                            <ArrowRight
                                className="size-4 shrink-0 text-muted-foreground transition-transform group-hover:translate-x-0.5 group-hover:text-primary"
                                aria-hidden="true"
                            />
                        </Link>
                    ))}
                </div>
            )}
        </section>
    );
}

export default function Search({
    query,
    type,
    minimumQueryLength,
    results,
    pagination,
}: SearchProps) {
    const [searching, setSearching] = useState(false);
    const totalResults =
        results.posts.length +
        results.spaces.length +
        results.people.length +
        results.topics.length;
    const hasSearched = Array.from(query).length >= minimumQueryLength;

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const value = new FormData(event.currentTarget).get('q');

        router.get(
            '/search',
            { q: typeof value === 'string' ? value.trim() : '', type },
            {
                preserveScroll: true,
                preserveState: true,
                onStart: () => setSearching(true),
                onFinish: () => setSearching(false),
            },
        );
    };

    return (
        <>
            <Head title={hasSearched ? `Search: ${query}` : 'Search'} />
            <main className="social-page max-w-[82rem]" aria-busy={searching}>
                <header className="social-card mb-5 rounded-[1.5rem] px-4 pt-5 pb-1 sm:px-7 sm:pt-7">
                    <div className="max-w-4xl">
                        <p className="social-eyebrow">Community search</p>
                        <h1 className="mt-2 text-2xl leading-tight font-black tracking-[-0.04em] sm:text-3xl">
                            Good conversations start here.
                        </h1>
                        <p className="mt-2 text-sm leading-6 text-muted-foreground">
                            Find a post, meet your people, or explore a new
                            Space.
                        </p>

                        <form
                            onSubmit={submit}
                            role="search"
                            className="mt-5 flex gap-2"
                        >
                            <label className="flex min-h-12 min-w-0 flex-1 items-center gap-2 rounded-xl border border-border bg-background px-3 text-foreground transition-shadow focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/20 sm:gap-3 sm:px-4">
                                <SearchIcon
                                    className="size-5 shrink-0 text-primary"
                                    aria-hidden="true"
                                />
                                <span className="sr-only">
                                    Search Lineweb Social
                                </span>
                                <input
                                    key={query}
                                    type="search"
                                    name="q"
                                    defaultValue={query}
                                    minLength={minimumQueryLength}
                                    maxLength={100}
                                    autoComplete="off"
                                    placeholder="What are you looking for?"
                                    className="min-w-0 flex-1 bg-transparent text-base font-semibold outline-none placeholder:text-muted-foreground"
                                />
                            </label>
                            <Button
                                type="submit"
                                disabled={searching}
                                className="min-h-12 shrink-0 rounded-xl px-4 sm:px-6"
                            >
                                {searching ? (
                                    <LoaderCircle
                                        className="size-4 animate-spin motion-reduce:animate-none"
                                        aria-hidden="true"
                                    />
                                ) : null}
                                {searching ? 'Searching' : 'Search'}
                            </Button>
                        </form>
                        <p className="mt-3 flex items-center gap-1.5 text-xs leading-5 text-muted-foreground">
                            <LockKeyhole
                                className="size-3.5 shrink-0"
                                aria-hidden="true"
                            />
                            Results respect your Space access and privacy
                            settings.
                        </p>
                    </div>
                    <nav
                        aria-label="Search categories"
                        className="-mx-4 mt-4 flex gap-1 overflow-x-auto border-t border-border/65 px-4 pt-2 pb-1 sm:-mx-7 sm:px-7"
                    >
                        {searchTypes.map((category) => (
                            <Link
                                key={category.value}
                                href={searchUrl(query, category.value)}
                                aria-current={
                                    type === category.value ? 'page' : undefined
                                }
                                className={`social-focus flex min-h-11 shrink-0 items-center gap-2 rounded-xl px-3 text-sm font-bold transition-colors sm:px-4 ${type === category.value ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-secondary hover:text-foreground'}`}
                            >
                                <category.icon
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                {category.label}
                            </Link>
                        ))}
                    </nav>
                </header>

                {!hasSearched ? (
                    <SearchStart minimumQueryLength={minimumQueryLength} />
                ) : totalResults === 0 ? (
                    <section className="social-card rounded-[1.5rem] px-6 py-16 text-center">
                        <span className="mx-auto flex size-14 items-center justify-center rounded-2xl bg-secondary text-primary">
                            <SearchIcon className="size-6" aria-hidden="true" />
                        </span>
                        <h2 className="mt-5 text-2xl font-black tracking-tight">
                            {pagination && pagination.currentPage > 1
                                ? 'No results on this page'
                                : `No ${type === 'all' ? 'results' : type} found for “${query}”`}
                        </h2>
                        <p className="mx-auto mt-2 max-w-lg text-sm leading-6 text-muted-foreground">
                            Try a broader phrase or check the Spaces and People
                            directories. Visibility and safety settings are
                            always applied before results appear.
                        </p>
                        <div className="mt-6 flex flex-wrap justify-center gap-2">
                            {type !== 'all' && (
                                <Link
                                    href={searchUrl(query, 'all')}
                                    className="social-chip"
                                >
                                    <SearchIcon
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    Search all categories
                                </Link>
                            )}
                            {pagination && pagination.currentPage > 1 && (
                                <Link
                                    href={searchUrl(query, type)}
                                    className="social-chip"
                                >
                                    Back to first page
                                </Link>
                            )}
                            <Link href="/spaces" className="social-chip">
                                <Compass
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Browse Spaces
                            </Link>
                            <Link href="/people" className="social-chip">
                                <UsersRound
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Browse People
                            </Link>
                        </div>
                    </section>
                ) : (
                    <>
                        <div className="mb-4 flex items-center justify-between px-1">
                            <p className="text-sm font-extrabold" role="status">
                                {totalResults.toLocaleString()}{' '}
                                {totalResults === 1 ? 'result' : 'results'}{' '}
                                shown for “{query}”
                            </p>
                            <p className="hidden text-xs font-bold text-muted-foreground sm:block">
                                Results respect your current access
                            </p>
                        </div>
                        {type === 'all' ? (
                            <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1.4fr)_minmax(19rem,.8fr)]">
                                <div className="space-y-3">
                                    <PostResults posts={results.posts} />
                                    {results.posts.length > 0 && (
                                        <Link
                                            href={searchUrl(query, 'posts')}
                                            className="social-chip"
                                        >
                                            View all posts{' '}
                                            <ArrowRight
                                                className="size-4"
                                                aria-hidden="true"
                                            />
                                        </Link>
                                    )}
                                </div>
                                <aside className="space-y-6">
                                    <div className="space-y-3">
                                        <TopicResults topics={results.topics} />
                                        {results.topics.length > 0 && (
                                            <Link
                                                href={searchUrl(
                                                    query,
                                                    'topics',
                                                )}
                                                className="social-chip"
                                            >
                                                View all topics{' '}
                                                <ArrowRight
                                                    className="size-4"
                                                    aria-hidden="true"
                                                />
                                            </Link>
                                        )}
                                    </div>
                                    <div className="space-y-3">
                                        <SpaceResults spaces={results.spaces} />
                                        {results.spaces.length > 0 && (
                                            <Link
                                                href={searchUrl(
                                                    query,
                                                    'spaces',
                                                )}
                                                className="social-chip"
                                            >
                                                View all Spaces{' '}
                                                <ArrowRight
                                                    className="size-4"
                                                    aria-hidden="true"
                                                />
                                            </Link>
                                        )}
                                    </div>
                                    <div className="space-y-3">
                                        <PeopleResults
                                            people={results.people}
                                        />
                                        {results.people.length > 0 && (
                                            <Link
                                                href={searchUrl(
                                                    query,
                                                    'people',
                                                )}
                                                className="social-chip"
                                            >
                                                View all people{' '}
                                                <ArrowRight
                                                    className="size-4"
                                                    aria-hidden="true"
                                                />
                                            </Link>
                                        )}
                                    </div>
                                </aside>
                            </div>
                        ) : (
                            <div className="max-w-4xl">
                                {type === 'posts' && (
                                    <PostResults posts={results.posts} />
                                )}
                                {type === 'spaces' && (
                                    <SpaceResults spaces={results.spaces} />
                                )}
                                {type === 'people' && (
                                    <PeopleResults people={results.people} />
                                )}
                                {type === 'topics' && (
                                    <TopicResults topics={results.topics} />
                                )}
                            </div>
                        )}
                    </>
                )}
                {pagination && (
                    <nav
                        aria-label="Search result pages"
                        className="mt-6 max-w-4xl border-t border-border pt-4"
                    >
                        <div className="flex items-center justify-between gap-3">
                            {pagination.previousUrl ? (
                                <Button
                                    asChild
                                    variant="outline"
                                    className="min-h-11 rounded-xl"
                                >
                                    <Link href={pagination.previousUrl}>
                                        <ArrowLeft
                                            className="size-4"
                                            aria-hidden="true"
                                        />{' '}
                                        Previous
                                    </Link>
                                </Button>
                            ) : (
                                <Button
                                    variant="outline"
                                    disabled
                                    className="min-h-11 rounded-xl"
                                >
                                    <ArrowLeft
                                        className="size-4"
                                        aria-hidden="true"
                                    />{' '}
                                    Previous
                                </Button>
                            )}
                            <span className="text-sm font-semibold text-muted-foreground">
                                Page {pagination.currentPage}
                            </span>
                            {pagination.nextUrl ? (
                                <Button
                                    asChild
                                    variant="outline"
                                    className="min-h-11 rounded-xl"
                                >
                                    <Link href={pagination.nextUrl}>
                                        Next{' '}
                                        <ArrowRight
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                    </Link>
                                </Button>
                            ) : (
                                <Button
                                    variant="outline"
                                    disabled
                                    className="min-h-11 rounded-xl"
                                >
                                    Next{' '}
                                    <ArrowRight
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                </Button>
                            )}
                        </div>
                        {pagination.limitReached && (
                            <p className="mt-3 text-sm text-muted-foreground">
                                Refine your search to narrow the remaining
                                results.
                            </p>
                        )}
                    </nav>
                )}
            </main>
        </>
    );
}

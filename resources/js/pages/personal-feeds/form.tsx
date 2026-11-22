import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Check, LockKeyhole, Plus, Search, X } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { sourceKinds, sourceLabels } from './types';
import type { FeedSource, PersonalFeed, SourceKind, Sources } from './types';

export default function PersonalFeedForm({
    feed,
    selectedSources,
    maxSources,
}: {
    feed: PersonalFeed | null;
    selectedSources: Sources;
    maxSources: number;
}) {
    const [selection, setSelection] = useState(selectedSources);
    const [query, setQuery] = useState('');
    const [searching, setSearching] = useState(false);
    const [searchError, setSearchError] = useState(false);
    const [results, setResults] = useState<Sources | null>(null);
    const form = useForm<{
        name: string;
        sources: Record<SourceKind, number[]>;
    }>({
        name: feed?.name ?? '',
        sources: { people: [], spaces: [], topics: [] },
    });
    const count = sourceKinds.reduce(
        (total, kind) => total + selection[kind].length,
        0,
    );
    const sourceError = Object.entries(form.errors).find(
        ([key]) => key === 'sources' || key.startsWith('sources.'),
    )?.[1];
    const title = feed ? 'Edit feed' : 'Create a feed';

    const search = async () => {
        if (searching) {
            return;
        }

        setSearching(true);
        setSearchError(false);
        setResults(null);

        try {
            const response = await fetch(
                `/my-feeds/sources?${new URLSearchParams({ q: query.trim() })}`,
                {
                    headers: { Accept: 'application/json' },
                },
            );

            if (!response.ok) {
                throw new Error('Search failed');
            }

            setResults((await response.json()) as Sources);
        } catch {
            setSearchError(true);
        } finally {
            setSearching(false);
        }
    };

    const toggle = (kind: SourceKind, source: FeedSource) => {
        const selected = selection[kind].some(({ id }) => id === source.id);

        if (!selected && count >= maxSources) {
            return;
        }

        setSelection({
            ...selection,
            [kind]: selected
                ? selection[kind].filter(({ id }) => id !== source.id)
                : [...selection[kind], source],
        });
    };

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            sources: {
                people: selection.people.map(({ id }) => id),
                spaces: selection.spaces.map(({ id }) => id),
                topics: selection.topics.map(({ id }) => id),
            },
        }));

        if (feed) {
            form.patch(feed.url);
        } else {
            form.post('/my-feeds');
        }
    };

    return (
        <>
            <Head title={title} />
            <main className="social-page max-w-4xl">
                <Link
                    href={feed?.url ?? '/my-feeds'}
                    className="social-focus mb-3 inline-flex min-h-11 items-center gap-2 rounded-lg px-2 text-sm font-bold text-primary"
                >
                    <ArrowLeft className="size-4" aria-hidden="true" />{' '}
                    {feed ? 'Back to feed' : 'My feeds'}
                </Link>
                <header className="social-page-heading">
                    <p className="social-eyebrow">
                        <LockKeyhole className="size-3.5" aria-hidden="true" />{' '}
                        Private timeline
                    </p>
                    <h1 className="mt-2 text-3xl font-black tracking-[-0.045em] sm:text-4xl">
                        {title}
                    </h1>
                    <p className="mt-2 text-sm leading-6 text-muted-foreground">
                        Choose sources to see their visible posts together,
                        newest first.
                    </p>
                </header>
                <form className="mt-5 space-y-4" onSubmit={submit}>
                    <div className="social-card space-y-2 rounded-2xl p-4 sm:p-5">
                        <Label htmlFor="personal-feed-name">Feed name</Label>
                        <Input
                            id="personal-feed-name"
                            value={form.data.name}
                            onChange={(event) =>
                                form.setData('name', event.target.value)
                            }
                            placeholder="e.g. Studio reading"
                            required
                            maxLength={80}
                            className="min-h-11"
                            aria-invalid={Boolean(form.errors.name)}
                            aria-describedby={
                                form.errors.name
                                    ? 'personal-feed-name-error'
                                    : undefined
                            }
                        />
                        <InputError
                            id="personal-feed-name-error"
                            role="alert"
                            message={form.errors.name}
                        />
                    </div>

                    <section
                        aria-labelledby="personal-feed-selection"
                        className="social-card space-y-3 rounded-2xl p-4 sm:p-5"
                    >
                        <div className="flex flex-wrap items-baseline justify-between gap-2">
                            <h2
                                id="personal-feed-selection"
                                className="text-base font-black"
                            >
                                Selected sources
                            </h2>
                            <p
                                className="text-sm text-muted-foreground"
                                role="status"
                            >
                                {count} of {maxSources} selected
                            </p>
                        </div>
                        {count === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No sources selected. You can add them now or
                                come back later.
                            </p>
                        ) : (
                            sourceKinds.map(
                                (kind) =>
                                    selection[kind].length > 0 && (
                                        <ul
                                            key={kind}
                                            aria-label={`Selected ${sourceLabels[kind].toLowerCase()}`}
                                            className="flex flex-wrap gap-2"
                                        >
                                            {selection[kind].map((source) => (
                                                <li
                                                    key={source.id}
                                                    className="max-w-full"
                                                >
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        className="h-auto min-h-11 max-w-full rounded-full py-2 text-left whitespace-normal"
                                                        aria-label={`Remove ${source.label}`}
                                                        onClick={() =>
                                                            toggle(kind, source)
                                                        }
                                                    >
                                                        <span className="wrap-anywhere">
                                                            {source.label}
                                                        </span>
                                                        <X aria-hidden="true" />
                                                    </Button>
                                                </li>
                                            ))}
                                        </ul>
                                    ),
                            )
                        )}
                        {feed && (
                            <p className="text-xs leading-5 text-muted-foreground">
                                Only available sources appear here. Saving keeps
                                only the sources shown in your selection.
                            </p>
                        )}
                        <InputError role="alert" message={sourceError} />
                    </section>

                    <section
                        aria-labelledby="personal-feed-source-search"
                        className="social-card rounded-2xl p-4 sm:p-5"
                    >
                        <h2
                            id="personal-feed-source-search"
                            className="mb-3 text-base font-black"
                        >
                            Find sources
                        </h2>
                        <Label htmlFor="personal-feed-query">
                            Search people, spaces or topics
                        </Label>
                        <div className="mt-2 flex flex-wrap gap-2">
                            <Input
                                id="personal-feed-query"
                                type="search"
                                value={query}
                                maxLength={80}
                                className="min-h-11 min-w-0 flex-1"
                                onChange={(event) =>
                                    setQuery(event.target.value)
                                }
                                onKeyDown={(event) => {
                                    if (event.key === 'Enter') {
                                        event.preventDefault();
                                        void search();
                                    }
                                }}
                                placeholder="e.g. design or #design"
                            />
                            <Button
                                type="button"
                                variant="outline"
                                onClick={search}
                                disabled={searching}
                                className="min-h-11"
                            >
                                <Search aria-hidden="true" /> Search
                            </Button>
                        </div>
                        <div className="mt-3 space-y-3" aria-busy={searching}>
                            {searching && (
                                <p
                                    className="text-sm text-muted-foreground"
                                    role="status"
                                >
                                    Finding sources…
                                </p>
                            )}
                            {searchError && (
                                <p
                                    className="text-sm text-destructive"
                                    role="alert"
                                >
                                    Could not load sources. Please try searching
                                    again.
                                </p>
                            )}
                            {results &&
                                sourceKinds.every(
                                    (kind) => results[kind].length === 0,
                                ) && (
                                    <p
                                        className="text-sm text-muted-foreground"
                                        role="status"
                                    >
                                        No available sources match your search.
                                    </p>
                                )}
                            {results &&
                                sourceKinds.map(
                                    (kind) =>
                                        results[kind].length > 0 && (
                                            <section
                                                key={kind}
                                                aria-label={sourceLabels[kind]}
                                            >
                                                <h3 className="mb-1 text-xs font-extrabold tracking-wide text-muted-foreground uppercase">
                                                    {sourceLabels[kind]}
                                                </h3>
                                                <ul className="divide-y divide-border/70">
                                                    {results[kind].map(
                                                        (source) => {
                                                            const selected =
                                                                selection[
                                                                    kind
                                                                ].some(
                                                                    ({ id }) =>
                                                                        id ===
                                                                        source.id,
                                                                );
                                                            const Icon =
                                                                selected
                                                                    ? Check
                                                                    : Plus;

                                                            return (
                                                                <li
                                                                    key={
                                                                        source.id
                                                                    }
                                                                >
                                                                    <button
                                                                        type="button"
                                                                        aria-pressed={
                                                                            selected
                                                                        }
                                                                        aria-label={`Select ${source.label}`}
                                                                        disabled={
                                                                            !selected &&
                                                                            count >=
                                                                                maxSources
                                                                        }
                                                                        onClick={() =>
                                                                            toggle(
                                                                                kind,
                                                                                source,
                                                                            )
                                                                        }
                                                                        className="social-focus flex min-h-11 w-full items-center gap-3 rounded-lg px-2 py-2 text-left hover:bg-secondary disabled:cursor-not-allowed disabled:opacity-50"
                                                                    >
                                                                        <span className="min-w-0 flex-1 text-sm font-bold wrap-anywhere">
                                                                            {
                                                                                source.label
                                                                            }
                                                                            {kind !==
                                                                                'topics' && (
                                                                                <span className="ml-2 text-xs font-normal text-muted-foreground">
                                                                                    {kind ===
                                                                                    'people'
                                                                                        ? '@'
                                                                                        : ''}
                                                                                    {
                                                                                        source.reference
                                                                                    }
                                                                                </span>
                                                                            )}
                                                                        </span>
                                                                        <Icon
                                                                            className="size-4 shrink-0 text-primary"
                                                                            aria-hidden="true"
                                                                        />
                                                                    </button>
                                                                </li>
                                                            );
                                                        },
                                                    )}
                                                </ul>
                                            </section>
                                        ),
                                )}
                        </div>
                    </section>

                    <div className="flex flex-wrap justify-end gap-2">
                        <Button asChild variant="outline" className="min-h-11">
                            <Link href={feed?.url ?? '/my-feeds'}>Cancel</Link>
                        </Button>
                        <Button
                            type="submit"
                            className="min-h-11"
                            disabled={form.processing}
                        >
                            {feed ? 'Save changes' : 'Create feed'}
                        </Button>
                    </div>
                </form>
            </main>
        </>
    );
}

PersonalFeedForm.layout = {
    breadcrumbs: [{ title: 'My feeds', href: '/my-feeds' }],
};

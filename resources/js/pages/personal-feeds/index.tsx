import { Head, Link } from '@inertiajs/react';
import { ArrowRight, ListFilter, LockKeyhole, Plus } from 'lucide-react';
import { Button } from '@/components/ui/button';
import type { PersonalFeed } from './types';

export default function PersonalFeedsIndex({
    feeds,
    maxFeeds,
}: {
    feeds: PersonalFeed[];
    maxFeeds: number;
}) {
    return (
        <>
            <Head title="My feeds" />
            <main className="social-page max-w-4xl">
                <header className="social-page-heading flex flex-wrap items-end justify-between gap-4">
                    <div className="min-w-0">
                        <p className="social-eyebrow">
                            <LockKeyhole
                                className="size-3.5"
                                aria-hidden="true"
                            />
                            Only you can see these feeds
                        </p>
                        <h1 className="mt-2 text-3xl font-black tracking-[-0.045em] sm:text-4xl">
                            My feeds
                        </h1>
                        <p className="mt-2 max-w-xl text-sm leading-6 text-muted-foreground sm:text-base">
                            Give a timeline its own name and choose the people,
                            spaces and topics you want to read together.
                        </p>
                    </div>
                    {feeds.length > 0 && feeds.length < maxFeeds && (
                        <Button asChild className="min-h-11 rounded-xl">
                            <Link href="/my-feeds/create">
                                <Plus aria-hidden="true" /> New feed
                            </Link>
                        </Button>
                    )}
                </header>

                {feeds.length === 0 ? (
                    <section className="social-card mt-5 rounded-[1.5rem] px-6 py-12 text-center">
                        <span className="mx-auto flex size-14 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                            <ListFilter className="size-6" aria-hidden="true" />
                        </span>
                        <h2 className="mt-4 text-lg font-black">
                            No personal feeds yet
                        </h2>
                        <p className="mx-auto mt-2 max-w-sm text-sm leading-6 text-muted-foreground">
                            Start with a subject you care about and collect its
                            sources in one place.
                        </p>
                        <Button asChild className="mt-5 min-h-11 rounded-xl">
                            <Link href="/my-feeds/create">Create a feed</Link>
                        </Button>
                    </section>
                ) : (
                    <section className="mt-6" aria-labelledby="my-feeds-list">
                        <div className="mb-3 flex items-baseline justify-between gap-3 px-1">
                            <h2
                                id="my-feeds-list"
                                className="text-sm font-black"
                            >
                                Your timelines
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                {feeds.length} of {maxFeeds}
                            </p>
                        </div>
                        <ul className="space-y-2">
                            {feeds.map((feed) => (
                                <li key={feed.id}>
                                    <Link
                                        href={feed.url}
                                        className="social-card social-card-interactive social-focus grid min-h-16 grid-cols-[auto_minmax(0,1fr)_auto] items-center gap-3 rounded-2xl px-4 py-3"
                                    >
                                        <ListFilter
                                            className="size-5 text-primary"
                                            aria-hidden="true"
                                        />
                                        <span className="font-extrabold wrap-anywhere">
                                            {feed.name}
                                        </span>
                                        <ArrowRight
                                            className="size-4 text-muted-foreground"
                                            aria-hidden="true"
                                        />
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </section>
                )}
            </main>
        </>
    );
}

PersonalFeedsIndex.layout = {
    breadcrumbs: [{ title: 'My feeds', href: '/my-feeds' }],
};

import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, Film, MessageCircle } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { AvatarMark } from '@/components/social/avatar-mark';
import { PostVideo } from '@/components/social/post-video';
import type { PostVideoData } from '@/components/social/post-video';

type Reel = {
    id: number;
    url: string;
    body: string;
    publishedAt: string;
    video: PostVideoData;
    author: { name: string; handle: string };
    space: { name: string; slug: string };
    commentsCount: number;
};

export default function Reels({
    items,
    nextCursor,
}: {
    items: Reel[];
    nextCursor: string | null;
}) {
    const [activeId, setActiveId] = useState<number | null>(
        items[0]?.id ?? null,
    );
    const cards = useRef(new Map<number, HTMLElement>());
    const currentActiveId =
        activeId === null || items.some((item) => item.id === activeId)
            ? activeId
            : (items[0]?.id ?? null);

    useEffect(() => {
        if (!('IntersectionObserver' in window)) {
            return;
        }

        const visibility = new Map<number, number>();
        const observer = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    const id = Number(
                        entry.target.getAttribute('data-reel-id'),
                    );
                    visibility.set(
                        id,
                        entry.isIntersecting ? entry.intersectionRatio : 0,
                    );
                });
                const mostVisible = [...visibility.entries()].sort(
                    (first, second) => second[1] - first[1],
                )[0];

                if (mostVisible && mostVisible[1] >= 0.25) {
                    setActiveId(mostVisible[0]);
                } else {
                    setActiveId(null);
                }
            },
            { threshold: [0.25, 0.5, 0.75] },
        );

        cards.current.forEach((element) => observer.observe(element));

        return () => observer.disconnect();
    }, [items]);

    return (
        <>
            <Head title="Reels" />
            <main className="social-page max-w-6xl">
                <header className="mb-6 flex flex-wrap items-end justify-between gap-4 px-1">
                    <div>
                        <p className="text-xs font-extrabold tracking-[0.12em] text-primary uppercase">
                            Your community, in motion
                        </p>
                        <h1 className="mt-1 text-3xl font-black tracking-[-0.04em] sm:text-4xl">
                            Reels
                        </h1>
                        <p className="mt-2 max-w-xl text-sm leading-6 text-muted-foreground">
                            Short videos from Spaces you can access, newest
                            first. No algorithmic ranking.
                        </p>
                    </div>
                    <Link
                        href="/feed"
                        className="social-focus inline-flex min-h-11 items-center gap-2 rounded-xl border border-border px-4 text-sm font-bold hover:bg-secondary"
                    >
                        <ArrowLeft className="size-4" aria-hidden="true" />
                        Back to feed
                    </Link>
                </header>

                {items.length === 0 ? (
                    <section className="social-card mx-auto max-w-2xl rounded-[1.5rem] px-6 py-12 text-center">
                        <Film
                            className="mx-auto size-9 text-primary"
                            aria-hidden="true"
                        />
                        <h2 className="mt-4 text-xl font-extrabold">
                            No videos here yet
                        </h2>
                        <p className="mx-auto mt-2 max-w-md text-sm leading-6 text-muted-foreground">
                            Ready videos from the communities you can see will
                            appear here.
                        </p>
                        <Link
                            href="/feed"
                            className="social-focus mt-6 inline-flex min-h-11 items-center rounded-xl bg-primary px-5 text-sm font-bold text-primary-foreground"
                        >
                            Explore the feed
                        </Link>
                    </section>
                ) : (
                    <div className="mx-auto max-w-[42rem] space-y-8 md:space-y-12">
                        {items.map((item) => (
                            <article
                                key={item.id}
                                data-reel-id={item.id}
                                ref={(node) => {
                                    if (node) {
                                        cards.current.set(item.id, node);
                                    } else {
                                        cards.current.delete(item.id);
                                    }
                                }}
                                className="min-w-0 snap-start scroll-mt-4"
                            >
                                <div className="mb-3 flex items-center gap-3 px-1">
                                    <AvatarMark
                                        name={item.author.name}
                                        className="size-10"
                                    />
                                    <div className="min-w-0 flex-1">
                                        <Link
                                            href={`/people/${item.author.handle}`}
                                            className="social-focus block truncate text-sm font-extrabold hover:underline"
                                        >
                                            {item.author.name}
                                        </Link>
                                        <Link
                                            href={`/spaces/${item.space.slug}`}
                                            className="social-focus block truncate text-xs text-muted-foreground hover:underline"
                                        >
                                            {item.space.name}
                                        </Link>
                                    </div>
                                    <span className="text-xs text-muted-foreground">
                                        {new Intl.DateTimeFormat(undefined, {
                                            dateStyle: 'medium',
                                        }).format(new Date(item.publishedAt))}
                                    </span>
                                </div>
                                <PostVideo
                                    video={item.video}
                                    active={currentActiveId === item.id}
                                />
                                <div className="px-1 pt-4">
                                    <p className="text-sm leading-6 text-foreground/90">
                                        {item.body}
                                    </p>
                                    <details className="mt-2 text-sm text-muted-foreground">
                                        <summary className="social-focus w-fit cursor-pointer rounded-lg py-2 font-bold">
                                            Video description
                                        </summary>
                                        <p className="max-w-prose pb-2 leading-6">
                                            {item.video.description}
                                        </p>
                                    </details>
                                    <Link
                                        href={item.url}
                                        className="social-focus mt-3 inline-flex min-h-11 items-center gap-2 rounded-xl border border-border px-4 text-sm font-bold hover:bg-secondary"
                                    >
                                        <MessageCircle
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        View post and conversation
                                        {item.commentsCount > 0 && (
                                            <span className="text-muted-foreground">
                                                {item.commentsCount}
                                            </span>
                                        )}
                                    </Link>
                                </div>
                            </article>
                        ))}
                        {nextCursor && (
                            <Link
                                href={`/reels?cursor=${encodeURIComponent(nextCursor)}`}
                                className="social-focus flex min-h-12 items-center justify-center gap-2 rounded-xl border border-border bg-card px-4 text-sm font-extrabold hover:bg-secondary"
                            >
                                More videos
                                <ArrowRight
                                    className="size-4"
                                    aria-hidden="true"
                                />
                            </Link>
                        )}
                    </div>
                )}
            </main>
        </>
    );
}

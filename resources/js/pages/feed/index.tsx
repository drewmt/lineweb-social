import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    ChevronDown,
    Flag,
    Globe2,
    LockKeyhole,
    Send,
    UsersRound,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { AvatarMark } from '@/components/social/avatar-mark';
import { CommentThread } from '@/components/social/comment-thread';
import type { SocialComment } from '@/components/social/comment-thread';
import { CommunitySignal } from '@/components/social/community-signal';
import { SpaceCover } from '@/components/social/space-cover';
import { Button } from '@/components/ui/button';
import type { Auth } from '@/types';

type Space = {
    name: string;
    slug: string;
    description: string | null;
    visibility: 'public' | 'private' | 'hidden';
    memberCount: number;
    isMember: boolean;
    isOwner: boolean;
    canManage: boolean;
};

type FeedPost = {
    id: number;
    body: string;
    publishedAt: string | null;
    canComment: boolean;
    canReport: boolean;
    hasReported: boolean;
    commentsCount: number;
    comments: SocialComment[];
    author: { name: string; handle: string; profileVisible: boolean };
    space: { name: string; slug: string };
};

type ReportReason = {
    value: string;
    label: string;
};

type FeedProps = {
    spaces: Space[];
    posts: FeedPost[];
    reportReasons: ReportReason[];
    selectedSpace: string | null;
    status?: string;
};

const publishedLabel = (value: string | null) => {
    if (!value) {
        return 'Draft';
    }

    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
};

function SpacePulse({ spaces }: { spaces: Space[] }) {
    if (spaces.length === 0) {
        return null;
    }

    return (
        <section className="mb-4 sm:mb-5" aria-labelledby="space-pulse-title">
            <div className="mb-3 flex items-end justify-between px-1">
                <div>
                    <p className="text-[0.68rem] font-extrabold tracking-[0.16em] text-primary uppercase">
                        Space pulse
                    </p>
                    <h2
                        id="space-pulse-title"
                        className="mt-0.5 text-lg font-extrabold tracking-tight"
                    >
                        Drop into your circles
                    </h2>
                </div>
                <Link
                    href="/spaces"
                    className="social-focus rounded-full px-2 py-1 text-xs font-bold text-primary hover:bg-primary/8"
                >
                    Explore all
                </Link>
            </div>
            <div className="-mx-3 flex snap-x scroll-px-4 [scrollbar-width:none] gap-2.5 overflow-x-auto px-4 pb-1 sm:mx-0 sm:scroll-px-0 sm:px-0 [&::-webkit-scrollbar]:hidden">
                {spaces.slice(0, 8).map((space) => (
                    <Link
                        key={space.slug}
                        href={`/spaces/${space.slug}`}
                        className="social-card social-card-interactive social-focus group flex w-40 shrink-0 snap-start flex-col overflow-hidden rounded-[1.25rem] sm:w-44"
                    >
                        <div className="relative h-[4.75rem] shrink-0 overflow-hidden bg-secondary/70">
                            <SpaceCover
                                seed={space.slug}
                                className="absolute inset-0 transition-transform duration-300 group-hover:scale-[1.025]"
                            />
                        </div>
                        <div className="flex min-h-[4.5rem] flex-col px-3.5 py-3">
                            <span className="line-clamp-2 text-sm leading-5 font-extrabold tracking-[-0.01em]">
                                {space.name}
                            </span>
                            <span className="mt-1.5 text-[0.68rem] leading-4 font-semibold text-muted-foreground">
                                {space.memberCount.toLocaleString()}{' '}
                                {space.memberCount === 1 ? 'member' : 'members'}
                            </span>
                        </div>
                    </Link>
                ))}
            </div>
        </section>
    );
}

function Composer({ spaces }: { spaces: Space[] }) {
    const { auth } = usePage<{ auth: Auth }>().props;
    const { data, setData, post, processing, errors, reset } = useForm({
        body: '',
        space: spaces[0]?.slug ?? '',
    });

    const publish = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (!data.space) {
            return;
        }

        post(`/spaces/${encodeURIComponent(data.space)}/posts`, {
            preserveScroll: true,
            onSuccess: () => reset('body'),
        });
    };

    if (spaces.length === 0) {
        return null;
    }

    return (
        <form
            id="compose"
            onSubmit={publish}
            className="social-card scroll-mt-24 overflow-hidden rounded-[1.35rem] transition-[border-color,box-shadow] focus-within:border-primary/25 focus-within:shadow-[0_1px_2px_rgba(15,23,42,0.03),0_20px_42px_-30px_rgba(37,99,235,0.24)]"
        >
            <div className="flex items-center gap-3 px-4 pt-4 sm:px-5 sm:pt-5">
                <AvatarMark name={auth.user.name} className="size-11" />
                <div className="min-w-0 flex-1">
                    <p className="text-sm font-black tracking-[-0.015em]">
                        Share an update
                    </p>
                    <div className="mt-0.5 flex min-w-0 items-center gap-1.5 text-xs font-medium text-muted-foreground">
                        <span className="shrink-0">Posting to</span>
                        {spaces.length > 1 ? (
                            <label className="relative -my-2 flex min-h-11 min-w-0 items-center">
                                <span className="sr-only">Choose a space</span>
                                <select
                                    value={data.space}
                                    onChange={(event) =>
                                        setData('space', event.target.value)
                                    }
                                    className="social-focus h-8 max-w-44 appearance-none rounded-lg bg-secondary/65 py-1 pr-7 pl-2 text-xs font-extrabold text-foreground"
                                >
                                    {spaces.map((space) => (
                                        <option
                                            key={space.slug}
                                            value={space.slug}
                                        >
                                            {space.name}
                                        </option>
                                    ))}
                                </select>
                                <ChevronDown
                                    className="pointer-events-none absolute top-1/2 right-2 size-3.5 -translate-y-1/2 text-muted-foreground"
                                    aria-hidden="true"
                                />
                            </label>
                        ) : (
                            <span className="truncate font-extrabold text-foreground">
                                {spaces[0]?.name}
                            </span>
                        )}
                    </div>
                </div>
            </div>
            <div className="px-4 pt-1 sm:px-5">
                <textarea
                    name="body"
                    value={data.body}
                    onChange={(event) => setData('body', event.target.value)}
                    maxLength={2000}
                    rows={4}
                    required
                    placeholder="What is worth sharing today?"
                    className="min-h-28 w-full resize-y bg-transparent px-0 py-4 text-[1.02rem] leading-7 outline-none placeholder:text-muted-foreground/70"
                />
                <InputError className="pb-3" message={errors.body} />
            </div>
            <div className="flex items-center justify-between gap-3 border-t border-border/65 bg-secondary/28 px-4 py-3 sm:px-5">
                <span className="text-xs font-semibold text-muted-foreground">
                    {data.body.length.toLocaleString()} / 2,000
                </span>
                <Button
                    type="submit"
                    disabled={processing || data.body.trim() === ''}
                    className="h-11 rounded-xl px-5"
                >
                    <Send className="size-4" aria-hidden="true" />
                    Publish
                </Button>
            </div>
        </form>
    );
}

function PostCard({
    item,
    reportReasons,
}: {
    item: FeedPost;
    reportReasons: ReportReason[];
}) {
    const [reporting, setReporting] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({
        reason: '',
        details: '',
    });

    const submitReport = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        post(`/posts/${item.id}/reports`, {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                setReporting(false);
            },
        });
    };

    return (
        <article className="social-card rounded-[1.35rem] p-4 sm:p-5">
            <header className="flex items-start gap-3">
                <AvatarMark name={item.author.name} className="size-11" />
                <div className="min-w-0 flex-1">
                    <div className="flex min-w-0 flex-wrap items-center gap-x-2 gap-y-0.5">
                        {item.author.profileVisible ? (
                            <Link
                                href={`/people/${item.author.handle}`}
                                className="truncate font-extrabold tracking-tight hover:underline"
                            >
                                {item.author.name}
                            </Link>
                        ) : (
                            <span className="truncate font-extrabold tracking-tight">
                                {item.author.name}
                            </span>
                        )}
                        <span
                            aria-hidden="true"
                            className="text-muted-foreground"
                        >
                            ·
                        </span>
                        <Link
                            href={`/spaces/${item.space.slug}`}
                            className="truncate text-sm font-bold text-primary hover:underline"
                        >
                            {item.space.name}
                        </Link>
                    </div>
                    <time
                        dateTime={item.publishedAt ?? undefined}
                        className="text-xs font-medium text-muted-foreground"
                    >
                        {publishedLabel(item.publishedAt)}
                    </time>
                </div>
                {item.hasReported ? (
                    <span className="inline-flex min-h-9 shrink-0 items-center gap-1.5 rounded-xl bg-secondary px-3 text-xs font-bold text-muted-foreground">
                        <Flag className="size-3.5" aria-hidden="true" />
                        Reported
                    </span>
                ) : (
                    item.canReport && (
                        <button
                            type="button"
                            onClick={() => setReporting((open) => !open)}
                            aria-expanded={reporting}
                            className="social-focus inline-flex min-h-9 shrink-0 items-center gap-1.5 rounded-xl px-3 text-xs font-bold text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground"
                        >
                            <Flag className="size-3.5" aria-hidden="true" />
                            Report
                        </button>
                    )
                )}
            </header>
            <p className="mt-4 text-[1.01rem] leading-7 whitespace-pre-wrap text-foreground/92 sm:text-[1.04rem] sm:leading-8">
                {item.body}
            </p>
            {reporting && (
                <form
                    onSubmit={submitReport}
                    className="mt-5 border-t pt-4"
                    aria-label="Report this post"
                >
                    <div className="flex flex-wrap items-end gap-3">
                        <label className="min-w-48 flex-1 text-sm font-bold">
                            What is the concern?
                            <select
                                value={data.reason}
                                onChange={(event) =>
                                    setData('reason', event.target.value)
                                }
                                required
                                className="social-inset social-focus mt-2 h-11 w-full px-3 text-sm font-semibold"
                            >
                                <option value="">Choose a reason</option>
                                {reportReasons.map((reason) => (
                                    <option
                                        key={reason.value}
                                        value={reason.value}
                                    >
                                        {reason.label}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <p className="max-w-xs text-xs leading-5 text-muted-foreground">
                            Reports are private and visible only to Space
                            moderators.
                        </p>
                    </div>
                    <InputError className="mt-2" message={errors.reason} />
                    <label className="mt-3 block text-sm font-bold">
                        Details{' '}
                        <span className="font-medium text-muted-foreground">
                            {data.reason === 'other'
                                ? '(required)'
                                : '(optional)'}
                        </span>
                        <textarea
                            value={data.details}
                            onChange={(event) =>
                                setData('details', event.target.value)
                            }
                            required={data.reason === 'other'}
                            maxLength={750}
                            rows={3}
                            placeholder="Give moderators enough context to make a fair decision."
                            className="social-inset social-focus mt-2 w-full resize-y px-3.5 py-3 text-sm leading-6"
                        />
                    </label>
                    <InputError className="mt-2" message={errors.details} />
                    <div className="mt-3 flex flex-wrap justify-end gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => {
                                reset();
                                setReporting(false);
                            }}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            disabled={processing || data.reason === ''}
                        >
                            <Flag className="size-4" aria-hidden="true" />
                            Send report
                        </Button>
                    </div>
                </form>
            )}
            <CommentThread
                postId={item.id}
                comments={item.comments}
                commentsCount={item.commentsCount}
                canComment={item.canComment}
                reportReasons={reportReasons}
            />
        </article>
    );
}

function FeedRail({
    spaces,
    selectedSpace,
}: {
    spaces: Space[];
    selectedSpace: string | null;
}) {
    return (
        <aside
            className="hidden space-y-4 xl:sticky xl:top-6 xl:block xl:self-start"
            aria-label="Community context"
        >
            <div className="social-card rounded-[1.35rem] p-4">
                <div className="flex items-center justify-between px-1">
                    <h2 className="font-extrabold tracking-tight">
                        Your spaces
                    </h2>
                    <span className="rounded-full bg-secondary px-2.5 py-1 text-xs font-extrabold">
                        {spaces.length}
                    </span>
                </div>
                <div className="mt-3 space-y-1.5">
                    {spaces.map((space) => (
                        <Link
                            key={space.slug}
                            href={`/spaces/${space.slug}`}
                            className={`social-focus flex items-center gap-3 rounded-2xl p-2.5 transition-colors ${selectedSpace === space.slug ? 'bg-primary/8' : 'hover:bg-secondary'}`}
                        >
                            <span className="h-10 w-12 shrink-0 overflow-hidden rounded-xl bg-primary/8">
                                <SpaceCover seed={space.slug} />
                            </span>
                            <span className="min-w-0 flex-1">
                                <span className="block truncate text-sm font-extrabold">
                                    {space.name}
                                </span>
                                <span className="block text-xs font-medium text-muted-foreground">
                                    {space.memberCount.toLocaleString()} members
                                </span>
                            </span>
                            {space.visibility === 'public' ? (
                                <Globe2
                                    className="size-3.5 text-muted-foreground"
                                    aria-label="Public space"
                                />
                            ) : (
                                <LockKeyhole
                                    className="size-3.5 text-muted-foreground"
                                    aria-label={`${space.visibility} space`}
                                />
                            )}
                        </Link>
                    ))}
                </div>
                <Link
                    href="/spaces"
                    className="social-focus mt-3 flex min-h-10 items-center justify-center gap-2 rounded-xl bg-secondary/55 px-3 py-2 text-sm font-extrabold text-primary transition-colors hover:bg-primary/10"
                >
                    Discover more{' '}
                    <ArrowRight className="size-4" aria-hidden="true" />
                </Link>
            </div>

            <div className="social-card overflow-hidden rounded-[1.35rem] border-foreground bg-foreground p-5 text-background">
                <CommunitySignal className="text-mint" />
                <p className="mt-5 text-[0.68rem] font-extrabold tracking-[0.14em] text-mint uppercase">
                    Open by design
                </p>
                <p className="mt-2 text-xl font-black tracking-[-0.03em]">
                    The feed follows time, not attention.
                </p>
                <p className="mt-3 text-sm leading-6 text-background/65">
                    Communities keep control of their conversations, members,
                    and pace.
                </p>
            </div>
        </aside>
    );
}

export default function Feed({
    spaces,
    posts,
    reportReasons,
    selectedSpace,
    status,
}: FeedProps) {
    const selected = spaces.find((space) => space.slug === selectedSpace);
    const postingSpaces = selected
        ? selected.isMember
            ? [selected]
            : []
        : spaces.filter((space) => space.isMember);

    const joinSelected = () =>
        selected &&
        router.post(`/spaces/${encodeURIComponent(selected.slug)}/membership`);
    const leaveSelected = () =>
        selected &&
        router.delete(
            `/spaces/${encodeURIComponent(selected.slug)}/membership`,
        );

    return (
        <>
            <Head title={selected ? selected.name : 'Home'} />
            <main className="social-page">
                <div className="grid items-start gap-5 xl:grid-cols-[minmax(0,44rem)_20rem] xl:justify-center">
                    <div className="min-w-0">
                        <header className="mb-4 px-1 sm:mb-5">
                            <div className="flex flex-wrap items-end justify-between gap-3">
                                <div>
                                    <div className="flex items-center gap-2 text-xs font-extrabold tracking-[0.12em] text-primary uppercase">
                                        <CommunitySignal />
                                        {selected
                                            ? 'Inside this space'
                                            : 'Your social home'}
                                    </div>
                                    <h1 className="mt-1 text-2xl font-black tracking-[-0.035em] sm:text-[2rem]">
                                        {selected?.name ?? 'Good to see you.'}
                                    </h1>
                                    <p className="mt-1 max-w-xl text-sm leading-6 text-muted-foreground">
                                        {selected?.description ??
                                            'Fresh conversations from the communities you chose — always chronological.'}
                                    </p>
                                </div>
                                {selected && (
                                    <div className="flex flex-wrap items-center gap-2">
                                        {selected.canManage && (
                                            <Button asChild variant="outline">
                                                <Link
                                                    href={`/spaces/${selected.slug}/manage`}
                                                >
                                                    Manage
                                                </Link>
                                            </Button>
                                        )}
                                        {!selected.isMember &&
                                            selected.visibility ===
                                                'public' && (
                                                <Button onClick={joinSelected}>
                                                    Join space
                                                </Button>
                                            )}
                                        {selected.isMember &&
                                            !selected.isOwner && (
                                                <Button
                                                    onClick={leaveSelected}
                                                    variant="outline"
                                                >
                                                    Leave
                                                </Button>
                                            )}
                                        <Link
                                            href="/feed"
                                            className="social-focus rounded-xl px-3 py-2 text-sm font-bold text-primary transition-colors hover:bg-primary/8"
                                        >
                                            Full feed
                                        </Link>
                                    </div>
                                )}
                            </div>
                        </header>

                        {!selected && (
                            <SpacePulse
                                spaces={spaces.filter(
                                    (space) => space.isMember,
                                )}
                            />
                        )}
                        {status && (
                            <div
                                role="status"
                                className="mb-4 rounded-2xl border border-primary/20 bg-primary/8 px-4 py-3 text-sm font-bold"
                            >
                                {status}
                            </div>
                        )}

                        <section
                            className="space-y-3 sm:space-y-4"
                            aria-label="Community feed"
                        >
                            <Composer spaces={postingSpaces} />
                            {posts.length === 0 ? (
                                <div className="social-card rounded-[1.35rem] px-6 py-12 text-center">
                                    <div className="mx-auto flex size-14 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                                        <UsersRound
                                            className="size-6"
                                            aria-hidden="true"
                                        />
                                    </div>
                                    <h2 className="mt-4 text-lg font-extrabold">
                                        The room is ready.
                                    </h2>
                                    <p className="mx-auto mt-2 max-w-md text-sm leading-6 text-muted-foreground">
                                        Be the first member to start a
                                        thoughtful conversation here.
                                    </p>
                                </div>
                            ) : (
                                posts.map((item) => (
                                    <PostCard
                                        key={item.id}
                                        item={item}
                                        reportReasons={reportReasons}
                                    />
                                ))
                            )}
                        </section>
                    </div>
                    <FeedRail spaces={spaces} selectedSpace={selectedSpace} />
                </div>
            </main>
        </>
    );
}

Feed.layout = {
    breadcrumbs: [{ title: 'Feed', href: '/feed' }],
};

import { Head, Link, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    ChevronLeft,
    ChevronRight,
    Flag,
    Globe2,
    LockKeyhole,
    MessageCircle,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { AvatarMark } from '@/components/social/avatar-mark';
import {
    CommentComposer,
    CommentRow,
} from '@/components/social/comment-thread';
import type {
    ReportReason,
    SocialComment,
} from '@/components/social/comment-thread';
import { CommunitySignal } from '@/components/social/community-signal';
import { SpaceCover } from '@/components/social/space-cover';
import { Button } from '@/components/ui/button';

type ConversationPost = {
    id: number;
    url: string;
    body: string;
    publishedAt: string | null;
    isDraft: boolean;
    isHidden: boolean;
    canComment: boolean;
    canReport: boolean;
    hasReported: boolean;
    commentsCount: number;
    author: { name: string; handle: string; profileVisible: boolean };
    space: {
        name: string;
        slug: string;
        description: string | null;
        visibility: 'public' | 'private' | 'hidden';
        memberCount: number;
    };
};

type ConversationPage = {
    data: SocialComment[];
    meta: {
        currentPage: number;
        lastPage: number;
        perPage: number;
        total: number;
    };
    links: {
        newer: string | null;
        older: string | null;
    };
};

type ShowPostProps = {
    post: ConversationPost;
    comments: ConversationPage;
    reportReasons: ReportReason[];
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

const anchored = (url: string) => `${url}#conversation`;

export default function ShowPost({
    post,
    comments,
    reportReasons,
    status,
}: ShowPostProps) {
    const [reporting, setReporting] = useState(false);
    const {
        data,
        setData,
        post: submit,
        processing,
        errors,
        reset,
    } = useForm({
        reason: '',
        details: '',
    });

    const submitReport = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        submit(`/posts/${post.id}/reports`, {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                setReporting(false);
            },
        });
    };

    const isLatestPage = comments.meta.currentPage === 1;
    const spaceUrl = `/spaces/${encodeURIComponent(post.space.slug)}`;

    return (
        <>
            <Head title={`Conversation in ${post.space.name}`} />
            <main className="social-page max-w-[82rem]">
                <div className="grid items-start gap-5 xl:grid-cols-[minmax(0,46rem)_19rem] xl:justify-center">
                    <div className="min-w-0">
                        <header className="mb-4 px-1 sm:mb-5">
                            <Link
                                href={spaceUrl}
                                className="social-focus inline-flex min-h-10 items-center gap-2 rounded-xl pr-3 text-sm font-extrabold text-primary transition-colors hover:bg-primary/8"
                            >
                                <ArrowLeft
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                {post.space.name}
                            </Link>
                            <div className="mt-2 flex flex-wrap items-end justify-between gap-3">
                                <div>
                                    <div className="social-eyebrow">
                                        <CommunitySignal />
                                        Post permalink
                                    </div>
                                    <h1 className="mt-1 text-2xl font-black tracking-[-0.035em] sm:text-[2rem]">
                                        Conversation
                                    </h1>
                                </div>
                                <span className="inline-flex min-h-9 items-center rounded-full bg-secondary px-3 text-xs font-extrabold text-muted-foreground">
                                    {post.commentsCount.toLocaleString()}{' '}
                                    {post.commentsCount === 1
                                        ? 'comment'
                                        : 'comments'}
                                </span>
                            </div>
                        </header>

                        {status && (
                            <div
                                role="status"
                                className="mb-4 rounded-2xl border border-primary/20 bg-primary/8 px-4 py-3 text-sm font-bold"
                            >
                                {status}
                            </div>
                        )}

                        <article className="social-card overflow-hidden rounded-[1.35rem]">
                            {(post.isDraft || post.isHidden) && (
                                <div className="border-b border-coral/20 bg-coral/10 px-4 py-3 text-sm font-bold sm:px-5">
                                    {post.isDraft
                                        ? 'This draft is visible only to its author and Space moderators.'
                                        : 'This post is hidden from community feeds.'}
                                </div>
                            )}
                            <div className="p-4 sm:p-5">
                                <header className="flex items-start gap-3">
                                    <AvatarMark
                                        name={post.author.name}
                                        className="size-11"
                                    />
                                    <div className="min-w-0 flex-1">
                                        {post.author.profileVisible ? (
                                            <Link
                                                href={`/people/${post.author.handle}`}
                                                className="social-focus inline-flex rounded-md font-extrabold tracking-tight hover:underline"
                                            >
                                                {post.author.name}
                                            </Link>
                                        ) : (
                                            <span className="font-extrabold tracking-tight">
                                                {post.author.name}
                                            </span>
                                        )}
                                        <div className="mt-0.5 flex flex-wrap items-center gap-x-2 text-xs font-medium text-muted-foreground">
                                            <time
                                                dateTime={
                                                    post.publishedAt ??
                                                    undefined
                                                }
                                            >
                                                {publishedLabel(
                                                    post.publishedAt,
                                                )}
                                            </time>
                                            <span aria-hidden="true">·</span>
                                            <Link
                                                href={spaceUrl}
                                                className="font-bold text-primary hover:underline"
                                            >
                                                {post.space.name}
                                            </Link>
                                        </div>
                                    </div>
                                    {post.hasReported ? (
                                        <span className="inline-flex min-h-9 shrink-0 items-center gap-1.5 rounded-xl bg-secondary px-3 text-xs font-bold text-muted-foreground">
                                            <Flag
                                                className="size-3.5"
                                                aria-hidden="true"
                                            />
                                            Reported
                                        </span>
                                    ) : (
                                        post.canReport && (
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    setReporting(
                                                        (open) => !open,
                                                    )
                                                }
                                                aria-expanded={reporting}
                                                className="social-focus inline-flex min-h-9 shrink-0 items-center gap-1.5 rounded-xl px-3 text-xs font-bold text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground"
                                            >
                                                <Flag
                                                    className="size-3.5"
                                                    aria-hidden="true"
                                                />
                                                Report
                                            </button>
                                        )
                                    )}
                                </header>

                                <p className="mt-5 text-[1.04rem] leading-8 whitespace-pre-wrap text-foreground/92">
                                    {post.body}
                                </p>

                                {reporting && (
                                    <form
                                        onSubmit={submitReport}
                                        className="mt-5 border-t pt-4"
                                        aria-label="Report this post"
                                    >
                                        <label className="block text-sm font-bold">
                                            What is the concern?
                                            <select
                                                value={data.reason}
                                                onChange={(event) =>
                                                    setData(
                                                        'reason',
                                                        event.target.value,
                                                    )
                                                }
                                                required
                                                className="social-inset social-focus mt-2 h-11 w-full px-3 text-sm font-semibold"
                                            >
                                                <option value="">
                                                    Choose a reason
                                                </option>
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
                                        <InputError
                                            className="mt-2"
                                            message={errors.reason}
                                        />
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
                                                    setData(
                                                        'details',
                                                        event.target.value,
                                                    )
                                                }
                                                required={
                                                    data.reason === 'other'
                                                }
                                                maxLength={750}
                                                rows={3}
                                                className="social-inset social-focus mt-2 w-full resize-y px-3.5 py-3 text-sm leading-6"
                                            />
                                        </label>
                                        <InputError
                                            className="mt-2"
                                            message={errors.details}
                                        />
                                        <div className="mt-3 flex justify-end gap-2">
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
                                                disabled={
                                                    processing ||
                                                    data.reason === ''
                                                }
                                            >
                                                <Flag
                                                    className="size-4"
                                                    aria-hidden="true"
                                                />
                                                Send report
                                            </Button>
                                        </div>
                                    </form>
                                )}
                            </div>
                        </article>

                        <section
                            id="conversation"
                            className="social-card mt-4 scroll-mt-20 rounded-[1.35rem] p-4 sm:mt-5 sm:p-5"
                            aria-labelledby="conversation-title"
                        >
                            <div className="flex flex-wrap items-end justify-between gap-3 border-b pb-4">
                                <div>
                                    <p className="social-eyebrow">
                                        Chronological
                                    </p>
                                    <h2
                                        id="conversation-title"
                                        className="mt-1 text-xl font-black tracking-[-0.025em]"
                                    >
                                        Full conversation
                                    </h2>
                                </div>
                                {comments.meta.lastPage > 1 && (
                                    <span className="text-xs font-bold text-muted-foreground">
                                        Page {comments.meta.currentPage} of{' '}
                                        {comments.meta.lastPage}
                                    </span>
                                )}
                            </div>

                            {comments.data.length === 0 ? (
                                <div className="py-10 text-center">
                                    <div className="mx-auto flex size-12 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                                        <MessageCircle
                                            className="size-5"
                                            aria-hidden="true"
                                        />
                                    </div>
                                    <h3 className="mt-3 font-extrabold">
                                        Start the conversation.
                                    </h3>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        Be the first member to add a thoughtful
                                        reply.
                                    </p>
                                </div>
                            ) : (
                                <div className="mt-4 space-y-3">
                                    {comments.data.map((comment) => (
                                        <CommentRow
                                            key={comment.id}
                                            comment={comment}
                                            reportReasons={reportReasons}
                                        />
                                    ))}
                                </div>
                            )}

                            {comments.meta.lastPage > 1 && (
                                <nav
                                    className="mt-5 flex items-center justify-between gap-3 border-t pt-4"
                                    aria-label="Conversation pages"
                                >
                                    {comments.links.newer ? (
                                        <Link
                                            href={anchored(
                                                comments.links.newer,
                                            )}
                                            className="social-focus inline-flex min-h-11 items-center gap-2 rounded-xl border border-border/80 px-3.5 text-sm font-extrabold transition-colors hover:border-primary/20 hover:bg-secondary"
                                        >
                                            <ChevronLeft
                                                className="size-4"
                                                aria-hidden="true"
                                            />
                                            Newer
                                        </Link>
                                    ) : (
                                        <span />
                                    )}
                                    {comments.links.older && (
                                        <Link
                                            href={anchored(
                                                comments.links.older,
                                            )}
                                            className="social-focus inline-flex min-h-11 items-center gap-2 rounded-xl border border-border/80 px-3.5 text-sm font-extrabold transition-colors hover:border-primary/20 hover:bg-secondary"
                                        >
                                            Older
                                            <ChevronRight
                                                className="size-4"
                                                aria-hidden="true"
                                            />
                                        </Link>
                                    )}
                                </nav>
                            )}

                            {post.canComment && isLatestPage ? (
                                <div className="mt-5 border-t pt-2">
                                    <CommentComposer postId={post.id} />
                                </div>
                            ) : post.canComment ? (
                                <Link
                                    href={`${post.url}#conversation`}
                                    className="social-focus mt-5 flex min-h-11 items-center justify-center rounded-xl bg-primary px-4 text-sm font-extrabold text-primary-foreground transition-opacity hover:opacity-92"
                                >
                                    Return to the latest comments to reply
                                </Link>
                            ) : (
                                <p className="mt-5 rounded-xl bg-secondary/45 px-3 py-2.5 text-xs font-semibold text-muted-foreground">
                                    Join this Space to take part in the
                                    conversation.
                                </p>
                            )}
                        </section>
                    </div>

                    <aside
                        className="hidden space-y-4 xl:sticky xl:top-6 xl:block xl:self-start"
                        aria-label="Space context"
                    >
                        <Link
                            href={spaceUrl}
                            className="social-card social-card-interactive social-focus group block overflow-hidden rounded-[1.35rem]"
                        >
                            <div className="relative h-32 overflow-hidden bg-secondary">
                                <SpaceCover
                                    seed={post.space.slug}
                                    className="absolute inset-0 transition-transform duration-300 group-hover:scale-[1.025]"
                                />
                            </div>
                            <div className="p-4">
                                <div className="flex items-center gap-2 text-[0.68rem] font-extrabold tracking-[0.12em] text-primary uppercase">
                                    {post.space.visibility === 'public' ? (
                                        <Globe2
                                            className="size-3.5"
                                            aria-hidden="true"
                                        />
                                    ) : (
                                        <LockKeyhole
                                            className="size-3.5"
                                            aria-hidden="true"
                                        />
                                    )}
                                    {post.space.visibility} Space
                                </div>
                                <h2 className="mt-2 text-lg font-black tracking-[-0.02em]">
                                    {post.space.name}
                                </h2>
                                {post.space.description && (
                                    <p className="mt-2 line-clamp-3 text-sm leading-6 text-muted-foreground">
                                        {post.space.description}
                                    </p>
                                )}
                                <p className="mt-3 text-xs font-bold text-muted-foreground">
                                    {post.space.memberCount.toLocaleString()}{' '}
                                    {post.space.memberCount === 1
                                        ? 'member'
                                        : 'members'}
                                </p>
                            </div>
                        </Link>

                        <div className="social-card rounded-[1.35rem] p-5">
                            <CommunitySignal className="text-primary" />
                            <p className="mt-4 text-sm font-black tracking-[-0.01em]">
                                Conversations stay chronological.
                            </p>
                            <p className="mt-2 text-sm leading-6 text-muted-foreground">
                                No ranking, popularity score, or hidden
                                engagement model decides which replies you can
                                reach.
                            </p>
                        </div>
                    </aside>
                </div>
            </main>
        </>
    );
}

ShowPost.layout = {
    breadcrumbs: [
        { title: 'Feed', href: '/feed' },
        { title: 'Conversation', href: '#' },
    ],
};

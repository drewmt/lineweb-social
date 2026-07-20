import { Link, useForm } from '@inertiajs/react';
import { ArrowRight, Flag, MessageCircle, Send } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { AvatarMark } from '@/components/social/avatar-mark';
import { Button } from '@/components/ui/button';

export type SocialComment = {
    id: number;
    body: string;
    publishedAt: string;
    canReport: boolean;
    hasReported: boolean;
    author: { name: string; handle: string; profileVisible: boolean };
};

export type ReportReason = {
    value: string;
    label: string;
};

type CommentThreadProps = {
    postId: number;
    postUrl: string;
    comments: SocialComment[];
    commentsCount: number;
    canComment: boolean;
    reportReasons: ReportReason[];
};

const commentDateLabel = (value: string) =>
    new Intl.DateTimeFormat(undefined, {
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    }).format(new Date(value));

function CommentReport({
    comment,
    reasons,
    onClose,
}: {
    comment: SocialComment;
    reasons: ReportReason[];
    onClose: () => void;
}) {
    const { data, setData, post, processing, errors, reset } = useForm({
        reason: '',
        details: '',
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        post(`/comments/${comment.id}/reports`, {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                onClose();
            },
        });
    };

    return (
        <form
            onSubmit={submit}
            className="mt-3 rounded-2xl border bg-card p-3"
            aria-label="Report this comment"
        >
            <label className="block text-xs font-extrabold">
                What is the concern?
                <select
                    value={data.reason}
                    onChange={(event) => setData('reason', event.target.value)}
                    required
                    className="social-inset social-focus mt-2 h-11 w-full px-3 text-sm font-semibold"
                >
                    <option value="">Choose a reason</option>
                    {reasons.map((reason) => (
                        <option key={reason.value} value={reason.value}>
                            {reason.label}
                        </option>
                    ))}
                </select>
            </label>
            <InputError className="mt-2" message={errors.reason} />
            <label className="mt-3 block text-xs font-extrabold">
                Details{' '}
                <span className="font-medium text-muted-foreground">
                    {data.reason === 'other' ? '(required)' : '(optional)'}
                </span>
                <textarea
                    value={data.details}
                    onChange={(event) => setData('details', event.target.value)}
                    required={data.reason === 'other'}
                    maxLength={750}
                    rows={2}
                    className="social-inset social-focus mt-2 w-full resize-y px-3 py-2.5 text-sm leading-6"
                />
            </label>
            <InputError className="mt-2" message={errors.details} />
            <div className="mt-3 flex justify-end gap-2">
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={() => {
                        reset();
                        onClose();
                    }}
                >
                    Cancel
                </Button>
                <Button
                    type="submit"
                    size="sm"
                    disabled={processing || data.reason === ''}
                >
                    Send report
                </Button>
            </div>
        </form>
    );
}

export function CommentRow({
    comment,
    reportReasons,
}: {
    comment: SocialComment;
    reportReasons: ReportReason[];
}) {
    const [reporting, setReporting] = useState(false);

    return (
        <article className="group/comment flex items-start gap-2.5">
            <AvatarMark name={comment.author.name} className="mt-0.5 size-8" />
            <div className="min-w-0 flex-1">
                <div className="rounded-2xl rounded-tl-md bg-secondary/58 px-3.5 py-2.5">
                    <div className="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                        {comment.author.profileVisible ? (
                            <Link
                                href={`/people/${comment.author.handle}`}
                                className="text-sm font-extrabold hover:underline"
                            >
                                {comment.author.name}
                            </Link>
                        ) : (
                            <span className="text-sm font-extrabold">
                                {comment.author.name}
                            </span>
                        )}
                        <time
                            dateTime={comment.publishedAt}
                            className="text-[0.68rem] font-semibold text-muted-foreground"
                        >
                            {commentDateLabel(comment.publishedAt)}
                        </time>
                    </div>
                    <p className="mt-1 text-sm leading-6 whitespace-pre-wrap text-foreground/90">
                        {comment.body}
                    </p>
                </div>
                <div className="mt-1 flex min-h-7 items-center pl-2">
                    {comment.hasReported ? (
                        <span className="inline-flex items-center gap-1 text-[0.68rem] font-bold text-muted-foreground">
                            <Flag className="size-3" aria-hidden="true" />
                            Reported
                        </span>
                    ) : (
                        comment.canReport && (
                            <button
                                type="button"
                                onClick={() => setReporting((open) => !open)}
                                aria-expanded={reporting}
                                className="social-focus rounded-md px-1.5 py-1 text-[0.68rem] font-bold text-muted-foreground transition-colors hover:text-foreground"
                            >
                                Report
                            </button>
                        )
                    )}
                </div>
                {reporting && (
                    <CommentReport
                        comment={comment}
                        reasons={reportReasons}
                        onClose={() => setReporting(false)}
                    />
                )}
            </div>
        </article>
    );
}

export function CommentComposer({ postId }: { postId: number }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        body: '',
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        post(`/posts/${postId}/comments`, {
            preserveScroll: true,
            onSuccess: () => reset(),
        });
    };

    return (
        <form onSubmit={submit} className="mt-3 flex items-start gap-2.5">
            <AvatarMark name="You" className="mt-1 size-8" />
            <div className="min-w-0 flex-1">
                <div className="social-input-surface flex items-end gap-2 rounded-2xl p-1.5 pl-3.5">
                    <textarea
                        value={data.body}
                        onChange={(event) =>
                            setData('body', event.target.value)
                        }
                        maxLength={1000}
                        rows={1}
                        required
                        placeholder="Add to the conversation"
                        aria-label="Add a comment"
                        className="social-focus min-h-9 flex-1 resize-none border-0 bg-transparent py-2 text-sm leading-5 outline-none placeholder:text-muted-foreground/75 focus-visible:ring-0"
                    />
                    <Button
                        type="submit"
                        size="icon"
                        disabled={processing || data.body.trim() === ''}
                        aria-label="Publish comment"
                        className="size-10 shrink-0 rounded-xl"
                    >
                        <Send className="size-4" aria-hidden="true" />
                    </Button>
                </div>
                <InputError className="mt-1.5" message={errors.body} />
            </div>
        </form>
    );
}

export function CommentThread({
    postId,
    postUrl,
    comments,
    commentsCount,
    canComment,
    reportReasons,
}: CommentThreadProps) {
    const [expanded, setExpanded] = useState(comments.length > 0);
    const visibleComments = expanded ? comments : [];

    return (
        <section className="mt-4 border-t pt-3" aria-label="Post discussion">
            <div className="flex items-center justify-between gap-3">
                <button
                    type="button"
                    onClick={() => setExpanded((open) => !open)}
                    aria-expanded={expanded}
                    className="social-focus inline-flex min-h-10 items-center gap-2 rounded-xl px-2.5 text-sm font-extrabold text-muted-foreground transition-colors hover:bg-secondary/60 hover:text-foreground"
                >
                    <MessageCircle className="size-4" aria-hidden="true" />
                    {commentsCount === 0
                        ? 'Start a discussion'
                        : `${commentsCount.toLocaleString()} ${commentsCount === 1 ? 'comment' : 'comments'}`}
                </button>
                {commentsCount > comments.length && (
                    <Link
                        href={`${postUrl}#conversation`}
                        className="social-focus inline-flex min-h-10 items-center gap-1.5 rounded-xl px-2.5 text-xs font-extrabold text-primary transition-colors hover:bg-primary/8"
                    >
                        View all
                        <ArrowRight className="size-3.5" aria-hidden="true" />
                    </Link>
                )}
            </div>

            {expanded && (
                <div className="mt-3 space-y-2.5">
                    {visibleComments.map((comment) => (
                        <CommentRow
                            key={comment.id}
                            comment={comment}
                            reportReasons={reportReasons}
                        />
                    ))}
                    {canComment ? (
                        <CommentComposer postId={postId} />
                    ) : (
                        <p className="rounded-xl bg-secondary/45 px-3 py-2.5 text-xs font-semibold text-muted-foreground">
                            Join this Space to take part in the discussion.
                        </p>
                    )}
                </div>
            )}
        </section>
    );
}

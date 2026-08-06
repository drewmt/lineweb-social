import { Link, useForm } from '@inertiajs/react';
import {
    ArrowRight,
    CornerDownRight,
    Flag,
    MessageCircle,
    Send,
    X,
} from 'lucide-react';
import { useRef, useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { AuthoredContentMenu } from '@/components/social/authored-content-menu';
import { AvatarMark } from '@/components/social/avatar-mark';
import { MentionText } from '@/components/social/mention-text';
import type { ContentMention } from '@/components/social/mention-text';
import { Button } from '@/components/ui/button';

export type SocialComment = {
    id: number;
    body: string;
    mentions: ContentMention[];
    publishedAt: string;
    editedAt: string | null;
    isReply: boolean;
    replyTo: {
        id: number;
        author: {
            name: string;
            handle: string;
            profileVisible: boolean;
        };
    } | null;
    canReport: boolean;
    canEdit: boolean;
    canDelete: boolean;
    hasReported: boolean;
    author: { name: string; handle: string; profileVisible: boolean };
};

export type ReportReason = {
    value: string;
    label: string;
};

const COMMENT_PREVIEW_LENGTH = 180;

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
    canReply,
    isReplying,
    onReply,
    onCancelReply,
    postId,
}: {
    comment: SocialComment;
    reportReasons: ReportReason[];
    canReply: boolean;
    isReplying: boolean;
    onReply: () => void;
    onCancelReply: () => void;
    postId: number;
}) {
    const [reporting, setReporting] = useState(false);
    const replyButtonRef = useRef<HTMLButtonElement>(null);
    const hasLongBody = comment.body.length > COMMENT_PREVIEW_LENGTH;
    const [expanded, setExpanded] = useState(false);
    const previewBody =
        hasLongBody && !expanded
            ? `${comment.body.slice(0, COMMENT_PREVIEW_LENGTH)}…`
            : comment.body;
    const closeReply = () => {
        onCancelReply();
        window.requestAnimationFrame(() => replyButtonRef.current?.focus());
    };

    return (
        <article
            id={`comment-${comment.id}`}
            className={`group/comment flex scroll-mt-24 items-start gap-2.5 ${
                comment.replyTo
                    ? 'ml-4 border-l border-primary/18 pl-3 sm:ml-8'
                    : ''
            }`}
            aria-label={
                comment.replyTo
                    ? `Reply to ${comment.replyTo.author.name}`
                    : undefined
            }
        >
            <AvatarMark name={comment.author.name} className="mt-0.5 size-8" />
            <div className="min-w-0 flex-1">
                <div className="rounded-2xl rounded-tl-md bg-secondary/58 px-3.5 py-2.5">
                    {comment.replyTo && (
                        <p className="mb-1.5 flex items-center gap-1.5 text-[0.68rem] font-extrabold text-primary/85">
                            <CornerDownRight
                                className="size-3.5 shrink-0"
                                aria-hidden="true"
                            />
                            Replying to {comment.replyTo.author.name}
                        </p>
                    )}
                    <div className="flex items-start justify-between gap-2">
                        <div className="flex min-w-0 flex-wrap items-baseline gap-x-2 gap-y-0.5">
                            {comment.author.profileVisible ? (
                                <Link
                                    href={`/people/${comment.author.handle}`}
                                    className="truncate text-sm font-extrabold hover:underline"
                                >
                                    {comment.author.name}
                                </Link>
                            ) : (
                                <span className="truncate text-sm font-extrabold">
                                    {comment.author.name}
                                </span>
                            )}
                            <time
                                dateTime={comment.publishedAt}
                                className="text-[0.68rem] font-semibold text-muted-foreground"
                            >
                                {commentDateLabel(comment.publishedAt)}
                            </time>
                            {comment.editedAt && (
                                <span className="text-[0.68rem] font-semibold text-muted-foreground">
                                    Edited
                                </span>
                            )}
                        </div>
                        <AuthoredContentMenu
                            body={comment.body}
                            canEdit={comment.canEdit}
                            canDelete={comment.canDelete}
                            contentType="comment"
                            updateUrl={`/comments/${comment.id}`}
                            deleteUrl={`/comments/${comment.id}`}
                            maxLength={1000}
                            compact
                        />
                    </div>
                    <p className="mt-1 text-sm leading-6 whitespace-pre-wrap text-foreground/90">
                        <MentionText
                            body={previewBody}
                            mentions={comment.mentions}
                        />
                    </p>
                    {hasLongBody && (
                        <button
                            type="button"
                            className="social-focus mt-2 inline-flex min-h-7 items-center rounded-md px-2.5 py-0.5 text-[0.68rem] font-extrabold text-muted-foreground transition-colors hover:bg-secondary/70"
                            onClick={() => setExpanded((open) => !open)}
                            aria-expanded={expanded}
                        >
                            {expanded ? 'Show less' : 'Read more'}
                        </button>
                    )}
                </div>
                <div className="mt-0.5 flex min-h-11 flex-wrap items-center gap-0.5 pl-1">
                    {canReply && !comment.isReply && (
                        <button
                            ref={replyButtonRef}
                            type="button"
                            onClick={() => {
                                setReporting(false);
                                onReply();
                            }}
                            aria-expanded={isReplying}
                            className="social-focus inline-flex min-h-11 items-center rounded-lg px-2 text-[0.72rem] font-extrabold text-primary transition-colors hover:bg-primary/8"
                        >
                            Reply
                        </button>
                    )}
                    {comment.hasReported ? (
                        <span className="inline-flex min-h-11 items-center gap-1 px-2 text-[0.68rem] font-bold text-muted-foreground">
                            <Flag className="size-3" aria-hidden="true" />
                            Reported
                        </span>
                    ) : (
                        comment.canReport && (
                            <button
                                type="button"
                                onClick={() => setReporting((open) => !open)}
                                aria-expanded={reporting}
                                className="social-focus inline-flex min-h-11 items-center rounded-lg px-2 text-[0.68rem] font-bold text-muted-foreground transition-colors hover:bg-secondary/65 hover:text-foreground"
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
                {isReplying && (
                    <CommentComposer
                        postId={postId}
                        parent={{
                            id: comment.id,
                            name: comment.author.name,
                        }}
                        onPublished={closeReply}
                        onCancel={closeReply}
                    />
                )}
            </div>
        </article>
    );
}

export function CommentComposer({
    postId,
    parent,
    onPublished,
    onCancel,
}: {
    postId: number;
    parent?: { id: number; name: string };
    onPublished?: () => void;
    onCancel?: () => void;
}) {
    const { data, setData, post, processing, errors, reset } = useForm({
        body: '',
        parent_id: parent?.id ?? null,
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        post(`/posts/${postId}/comments`, {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                onPublished?.();
            },
        });
    };

    return (
        <form
            onSubmit={submit}
            className={
                parent ? 'mt-1.5 rounded-2xl bg-primary/6 p-2.5' : 'mt-3'
            }
            aria-label={parent ? `Reply to ${parent.name}` : 'Add a comment'}
        >
            {parent && (
                <div className="mb-2 flex min-h-9 items-center justify-between gap-3 px-1">
                    <p className="flex min-w-0 items-center gap-1.5 text-xs font-bold text-muted-foreground">
                        <CornerDownRight
                            className="size-3.5 shrink-0 text-primary"
                            aria-hidden="true"
                        />
                        <span className="truncate">
                            Replying to{' '}
                            <strong className="text-foreground">
                                {parent.name}
                            </strong>
                        </span>
                    </p>
                    <button
                        type="button"
                        onClick={onCancel}
                        aria-label={`Cancel reply to ${parent.name}`}
                        className="social-focus inline-flex size-11 shrink-0 items-center justify-center rounded-xl text-muted-foreground transition-colors hover:bg-background hover:text-foreground"
                    >
                        <X className="size-4" aria-hidden="true" />
                    </button>
                </div>
            )}
            <div className="flex items-start gap-2.5">
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
                            autoFocus={parent !== undefined}
                            placeholder={
                                parent
                                    ? `Reply to ${parent.name}`
                                    : 'Add to the conversation'
                            }
                            aria-label={
                                parent
                                    ? `Write a reply to ${parent.name}`
                                    : 'Add a comment'
                            }
                            className="social-focus min-h-9 flex-1 resize-none border-0 bg-transparent py-2 text-sm leading-5 outline-none placeholder:text-muted-foreground/75 focus-visible:ring-0"
                        />
                        <Button
                            type="submit"
                            size="icon"
                            disabled={processing || data.body.trim() === ''}
                            aria-label={
                                parent ? 'Publish reply' : 'Publish comment'
                            }
                            className="size-11 shrink-0 rounded-xl"
                        >
                            <Send className="size-4" aria-hidden="true" />
                        </Button>
                    </div>
                    <InputError className="mt-1.5" message={errors.body} />
                    <InputError className="mt-1.5" message={errors.parent_id} />
                </div>
            </div>
        </form>
    );
}

export function CommentList({
    postId,
    comments,
    canComment,
    reportReasons,
}: {
    postId: number;
    comments: SocialComment[];
    canComment: boolean;
    reportReasons: ReportReason[];
}) {
    const [replyingTo, setReplyingTo] = useState<number | null>(null);

    return comments.map((comment) => (
        <CommentRow
            key={comment.id}
            postId={postId}
            comment={comment}
            reportReasons={reportReasons}
            canReply={canComment}
            isReplying={replyingTo === comment.id}
            onReply={() =>
                setReplyingTo((current) =>
                    current === comment.id ? null : comment.id,
                )
            }
            onCancelReply={() => setReplyingTo(null)}
        />
    ));
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
                    <CommentList
                        postId={postId}
                        comments={visibleComments}
                        canComment={canComment}
                        reportReasons={reportReasons}
                    />
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

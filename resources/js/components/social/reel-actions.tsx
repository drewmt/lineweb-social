import { Link, router } from '@inertiajs/react';
import {
    Bookmark,
    Copy,
    Heart,
    Lightbulb,
    MessageCircle,
    PartyPopper,
    Share2,
} from 'lucide-react';
import { useRef, useState } from 'react';
import type {
    ReactionSummary,
    ReactionType,
} from '@/components/social/post-reactions';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';

const reactionIcons = {
    like: Heart,
    celebrate: PartyPopper,
    insightful: Lightbulb,
};

export function ReelActions({
    postId,
    url,
    commentsCount,
    isSaved,
    reactions,
    reactionTypes,
}: {
    postId: number;
    url: string;
    commentsCount: number;
    isSaved: boolean;
    reactions: ReactionSummary;
    reactionTypes: ReactionType[];
}) {
    const [reactionOverride, setReactionOverride] =
        useState<ReactionSummary | null>(null);
    const [savedOverride, setSavedOverride] = useState<boolean | null>(null);
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState('');
    const [shareUrl, setShareUrl] = useState('');
    const [copyStatus, setCopyStatus] = useState('');
    const linkInput = useRef<HTMLInputElement>(null);
    const current = reactionOverride ?? reactions;
    const saved = savedOverride ?? isSaved;
    const options = {
        only: ['status'],
        preserveScroll: true,
        preserveState: true,
        onStart: () => {
            setProcessing(true);
            setError('');
        },
        onError: () =>
            setError('This action could not be saved. Please try again.'),
        onFinish: () => setProcessing(false),
    };

    const react = (type: string) => {
        if (processing || !current.canReact) {
            return;
        }

        const nextType = current.viewerType === type ? null : type;
        const requestOptions = {
            ...options,
            onSuccess: () => {
                const counts = { ...current.counts };

                if (current.viewerType) {
                    counts[current.viewerType] = Math.max(
                        0,
                        (counts[current.viewerType] ?? 0) - 1,
                    );
                }

                if (nextType) {
                    counts[nextType] = (counts[nextType] ?? 0) + 1;
                }

                setReactionOverride({
                    ...current,
                    counts,
                    viewerType: nextType,
                    total: Math.max(
                        0,
                        current.total +
                            (nextType ? 1 : 0) -
                            (current.viewerType ? 1 : 0),
                    ),
                });
            },
        };

        if (nextType === null) {
            router.delete(`/posts/${postId}/reaction`, requestOptions);
        } else {
            router.put(
                `/posts/${postId}/reaction`,
                { type: nextType },
                requestOptions,
            );
        }
    };

    const toggleSaved = () => {
        if (processing) {
            return;
        }

        const requestOptions = {
            ...options,
            onSuccess: () => setSavedOverride(!saved),
        };

        if (saved) {
            router.delete(`/posts/${postId}/save`, requestOptions);
        } else {
            router.put(`/posts/${postId}/save`, {}, requestOptions);
        }
    };

    const copyLink = async () => {
        try {
            await navigator.clipboard.writeText(shareUrl);
            setCopyStatus('Link copied.');
        } catch {
            linkInput.current?.focus();
            linkInput.current?.select();
            setCopyStatus(
                'Copy is unavailable here. Select and copy the link above.',
            );
        }
    };

    return (
        <div className="mt-3 rounded-2xl border border-border/70 bg-card p-2 sm:p-3">
            <div className="grid grid-cols-3 gap-1" aria-label="Reel reactions">
                {reactionTypes.map((type) => {
                    const Icon =
                        reactionIcons[type.value as keyof typeof reactionIcons];

                    if (!Icon) {
                        return null;
                    }

                    const selected = current.viewerType === type.value;
                    const count = current.counts[type.value] ?? 0;

                    return (
                        <button
                            key={type.value}
                            type="button"
                            onClick={() => react(type.value)}
                            disabled={processing || !current.canReact}
                            aria-pressed={selected}
                            aria-label={`${type.label}${count ? `, ${count}` : ''}`}
                            className={`social-focus flex min-h-14 min-w-0 flex-col items-center justify-center gap-1 rounded-xl px-1 py-2 text-xs font-bold transition-colors disabled:opacity-60 sm:min-h-11 sm:flex-row sm:gap-2 sm:px-3 ${selected ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-secondary hover:text-foreground'}`}
                        >
                            <span className="flex items-center gap-1.5">
                                <Icon
                                    aria-hidden="true"
                                    className={`size-4 ${selected && type.value === 'like' ? 'fill-current' : ''}`}
                                />
                                {count > 0 && (
                                    <span className="tabular-nums">
                                        {count.toLocaleString()}
                                    </span>
                                )}
                            </span>
                            <span>{type.label}</span>
                        </button>
                    );
                })}
                {current.total > 0 && (
                    <span
                        className="col-span-3 px-2 pt-1 text-center text-xs text-muted-foreground"
                        aria-live="polite"
                    >
                        {current.total.toLocaleString()}{' '}
                        {current.total === 1 ? 'reaction' : 'reactions'}
                    </span>
                )}
            </div>
            <div className="mt-2 grid grid-cols-3 gap-1 border-t border-border/70 pt-2">
                <Link
                    href={url}
                    className="social-focus flex min-h-11 items-center justify-center gap-1.5 rounded-xl text-xs font-bold text-muted-foreground hover:bg-secondary hover:text-foreground"
                >
                    <MessageCircle className="size-4" aria-hidden="true" />
                    Comments{' '}
                    {commentsCount > 0 && (
                        <span className="tabular-nums">{commentsCount}</span>
                    )}
                </Link>
                <button
                    type="button"
                    onClick={toggleSaved}
                    disabled={processing}
                    aria-pressed={saved}
                    className="social-focus flex min-h-11 items-center justify-center gap-1.5 rounded-xl text-xs font-bold text-muted-foreground hover:bg-secondary hover:text-foreground disabled:opacity-60"
                >
                    <Bookmark
                        className={`size-4 ${saved ? 'fill-current text-primary' : ''}`}
                        aria-hidden="true"
                    />
                    {saved ? 'Saved' : 'Save'}
                </button>
                <Dialog
                    onOpenChange={(open) => {
                        if (open) {
                            setShareUrl(
                                new URL(url, window.location.origin).href,
                            );
                            setCopyStatus('');
                        }
                    }}
                >
                    <DialogTrigger asChild>
                        <button
                            type="button"
                            className="social-focus flex min-h-11 items-center justify-center gap-1.5 rounded-xl text-xs font-bold text-muted-foreground hover:bg-secondary hover:text-foreground"
                        >
                            <Share2 className="size-4" aria-hidden="true" />
                            Share
                        </button>
                    </DialogTrigger>
                    <DialogContent className="rounded-2xl">
                        <DialogHeader>
                            <DialogTitle>Share this Reel</DialogTitle>
                            <DialogDescription>
                                Send the post link, not the video file. Access
                                still follows this Space’s visibility rules.
                            </DialogDescription>
                        </DialogHeader>
                        <label
                            htmlFor={`reel-link-${postId}`}
                            className="text-sm font-bold"
                        >
                            Post link
                        </label>
                        <Input
                            id={`reel-link-${postId}`}
                            ref={linkInput}
                            value={shareUrl}
                            readOnly
                            onFocus={(event) => event.currentTarget.select()}
                            className="min-h-11"
                        />
                        <Button
                            type="button"
                            onClick={copyLink}
                            className="min-h-11 gap-2"
                        >
                            <Copy className="size-4" aria-hidden="true" />
                            Copy link
                        </Button>
                        <p
                            role="status"
                            className="min-h-5 text-sm text-muted-foreground"
                        >
                            {copyStatus}
                        </p>
                    </DialogContent>
                </Dialog>
            </div>
            {error && (
                <p role="alert" className="px-2 pt-2 text-sm text-destructive">
                    {error}
                </p>
            )}
        </div>
    );
}

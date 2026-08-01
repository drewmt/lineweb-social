import { Link, useForm } from '@inertiajs/react';
import { Repeat2 } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { PostGallery } from '@/components/social/post-image';
import type { PostMedia } from '@/components/social/post-image';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

export type SharedPost = {
    source: {
        id: number;
        url: string;
        body: string;
        mediaItems: PostMedia[];
        publishedAt: string | null;
        author: { name: string; handle: string; profileVisible: boolean };
        space: { name: string; slug: string };
    };
};

type ShareablePost = {
    id: number;
    body: string;
    canShare: boolean;
    author: { name: string };
    space: { name: string; slug: string };
};

export function SharedPostPreview({
    share,
    className = '',
}: {
    share: SharedPost | null;
    className?: string;
}) {
    if (!share) {
        return null;
    }

    const { source } = share;

    return (
        <section
            className={`overflow-hidden rounded-[1.15rem] border border-border/80 bg-secondary/32 ${className}`}
            aria-label={`Shared post from ${source.author.name}`}
        >
            <div className="flex items-center gap-2 border-b border-border/65 px-3.5 py-2.5 text-xs font-bold text-muted-foreground">
                <Repeat2 className="size-3.5 text-primary" aria-hidden="true" />
                <span>Shared from this Space</span>
            </div>
            <div className="p-3.5 sm:p-4">
                <div className="flex flex-wrap items-center gap-x-2 gap-y-0.5 text-sm">
                    {source.author.profileVisible ? (
                        <Link
                            href={`/people/${source.author.handle}`}
                            className="social-focus rounded font-extrabold hover:underline"
                        >
                            {source.author.name}
                        </Link>
                    ) : (
                        <span className="font-extrabold">
                            {source.author.name}
                        </span>
                    )}
                    <span className="text-muted-foreground" aria-hidden="true">
                        ·
                    </span>
                    <Link
                        href={`/spaces/${source.space.slug}`}
                        className="social-focus rounded font-bold text-primary hover:underline"
                    >
                        {source.space.name}
                    </Link>
                </div>
                <Link
                    href={source.url}
                    className="social-focus mt-2 block rounded-lg text-[0.95rem] leading-6 whitespace-pre-wrap text-foreground/90 hover:text-foreground"
                >
                    {source.body}
                </Link>
                {source.mediaItems.length > 0 && (
                    <PostGallery media={source.mediaItems} className="mt-3" />
                )}
            </div>
        </section>
    );
}

export function PostShareAction({
    post,
    compact = false,
}: {
    post: ShareablePost;
    compact?: boolean;
}) {
    const [open, setOpen] = useState(false);
    const {
        data,
        setData,
        post: submit,
        processing,
        errors,
        reset,
    } = useForm({
        body: '',
    });

    if (!post.canShare) {
        return null;
    }

    const share = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        submit(`/posts/${post.id}/shares`, {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                setOpen(false);
            },
        });
    };

    return (
        <>
            <button
                type="button"
                onClick={() => setOpen(true)}
                className={`social-focus inline-flex min-h-9 items-center gap-1.5 rounded-xl px-3 text-xs font-bold text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground ${
                    compact ? 'px-2.5' : ''
                }`}
            >
                <Repeat2 className="size-3.5" aria-hidden="true" />
                Repost
            </button>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="rounded-[1.45rem] border-border/80 p-5 sm:max-w-xl sm:p-6">
                    <DialogHeader>
                        <DialogTitle className="text-xl font-black tracking-tight">
                            Share with {post.space.name}
                        </DialogTitle>
                        <DialogDescription className="leading-6">
                            Add your perspective to quote this post, or leave it
                            empty for a clean repost. Shares stay in the same
                            Space, so its visibility rules are preserved.
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={share} className="space-y-4">
                        <label className="block">
                            <span className="text-sm font-bold">
                                Your note{' '}
                                <span className="font-medium text-muted-foreground">
                                    (optional)
                                </span>
                            </span>
                            <textarea
                                autoFocus
                                value={data.body}
                                onChange={(event) =>
                                    setData('body', event.target.value)
                                }
                                maxLength={2000}
                                rows={4}
                                placeholder="Add context, a question, or your take…"
                                className="social-inset social-focus mt-2 w-full resize-y px-4 py-3 text-[0.95rem] leading-7"
                            />
                        </label>
                        <div className="flex items-start justify-between gap-3">
                            <InputError message={errors.body} />
                            <span className="shrink-0 text-xs font-semibold text-muted-foreground">
                                {data.body.length.toLocaleString()} / 2,000
                            </span>
                        </div>
                        <div className="rounded-2xl border border-border/70 bg-secondary/28 px-3.5 py-3">
                            <p className="mb-1 text-xs font-extrabold tracking-[0.08em] text-muted-foreground uppercase">
                                Original post
                            </p>
                            <p className="text-sm font-extrabold">
                                {post.author.name}
                            </p>
                            <p className="mt-1 line-clamp-4 text-sm leading-6 text-muted-foreground">
                                {post.body}
                            </p>
                        </div>
                        <DialogFooter className="flex-row gap-2 sm:justify-end">
                            <Button
                                type="button"
                                variant="secondary"
                                className="h-11"
                                onClick={() => setOpen(false)}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                className="h-11"
                                disabled={processing}
                            >
                                <Repeat2
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                {processing
                                    ? 'Sharing…'
                                    : data.body.trim() === ''
                                      ? 'Repost'
                                      : 'Quote post'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

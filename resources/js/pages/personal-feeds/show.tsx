import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    Clock3,
    ListFilter,
    LockKeyhole,
    Pencil,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import type { ReactionType } from '@/components/social/post-reactions';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { PostCard } from '@/pages/feed/index';
import type { FeedPost, ReportReason } from '@/pages/feed/index';
import type { PersonalFeed } from './types';

export default function PersonalFeedShow({
    feed,
    sourceCount,
    posts,
    reportReasons,
    reactionTypes,
}: {
    feed: PersonalFeed;
    sourceCount: number;
    posts: FeedPost[];
    reportReasons: ReportReason[];
    reactionTypes: ReactionType[];
}) {
    const [deleteOpen, setDeleteOpen] = useState(false);
    const [deleting, setDeleting] = useState(false);

    const remove = () => {
        setDeleting(true);
        router.delete(feed.url, {
            onSuccess: () => setDeleteOpen(false),
            onFinish: () => setDeleting(false),
        });
    };

    return (
        <>
            <Head title={feed.name} />
            <main className="social-page max-w-3xl">
                <Link
                    href="/my-feeds"
                    className="social-focus mb-3 inline-flex min-h-11 items-center gap-2 rounded-lg px-2 text-sm font-bold text-primary"
                >
                    <ArrowLeft className="size-4" aria-hidden="true" /> My feeds
                </Link>
                <header className="social-page-heading">
                    <p className="social-eyebrow">
                        <LockKeyhole className="size-3.5" aria-hidden="true" />{' '}
                        Private timeline
                    </p>
                    <h1 className="mt-2 text-3xl font-black tracking-[-0.045em] wrap-anywhere sm:text-4xl">
                        {feed.name}
                    </h1>
                    <div className="mt-4 flex flex-wrap items-center justify-between gap-3">
                        <p className="inline-flex items-center gap-2 text-sm text-muted-foreground">
                            <Clock3 className="size-4" aria-hidden="true" />{' '}
                            Newest first · {sourceCount}{' '}
                            {sourceCount === 1 ? 'source' : 'sources'}
                        </p>
                        <div className="flex flex-wrap gap-2">
                            <Button
                                asChild
                                variant="outline"
                                className="min-h-11"
                            >
                                <Link href={feed.editUrl}>
                                    <Pencil aria-hidden="true" /> Edit feed
                                </Link>
                            </Button>
                            <Dialog
                                open={deleteOpen}
                                onOpenChange={(open) => {
                                    if (!deleting) {
                                        setDeleteOpen(open);
                                    }
                                }}
                            >
                                <DialogTrigger asChild>
                                    <Button
                                        variant="ghost"
                                        className="min-h-11"
                                        aria-label="Delete feed"
                                    >
                                        <Trash2 aria-hidden="true" />
                                    </Button>
                                </DialogTrigger>
                                <DialogContent>
                                    <DialogHeader>
                                        <DialogTitle>
                                            Delete this feed?
                                        </DialogTitle>
                                        <DialogDescription>
                                            The timeline and its selection will
                                            be removed. The original posts,
                                            follows and favorites will stay as
                                            they are.
                                        </DialogDescription>
                                    </DialogHeader>
                                    <DialogFooter>
                                        <DialogClose asChild>
                                            <Button
                                                variant="outline"
                                                className="min-h-11"
                                                disabled={deleting}
                                            >
                                                Cancel
                                            </Button>
                                        </DialogClose>
                                        <Button
                                            variant="destructive"
                                            className="min-h-11"
                                            onClick={remove}
                                            disabled={deleting}
                                        >
                                            Delete feed
                                        </Button>
                                    </DialogFooter>
                                </DialogContent>
                            </Dialog>
                        </div>
                    </div>
                </header>
                <section
                    aria-label="Feed posts"
                    className="mt-5 space-y-3 sm:space-y-4"
                >
                    {posts.length === 0 ? (
                        <div className="social-card rounded-[1.35rem] px-6 py-12 text-center">
                            <span className="mx-auto flex size-14 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                                <ListFilter
                                    className="size-6"
                                    aria-hidden="true"
                                />
                            </span>
                            <h2 className="mt-4 text-lg font-extrabold">
                                {sourceCount
                                    ? 'No visible posts yet'
                                    : 'No available sources yet'}
                            </h2>
                            <p className="mx-auto mt-2 max-w-sm text-sm leading-6 text-muted-foreground">
                                {sourceCount
                                    ? 'Posts from your selected sources will appear here when you can view them.'
                                    : 'Add people, spaces or topics to start reading this feed.'}
                            </p>
                            {!sourceCount && (
                                <Button asChild className="mt-4 min-h-11">
                                    <Link href={feed.editUrl}>Add sources</Link>
                                </Button>
                            )}
                        </div>
                    ) : (
                        posts.map((item) => (
                            <PostCard
                                key={item.id}
                                item={item}
                                reportReasons={reportReasons}
                                reactionTypes={reactionTypes}
                            />
                        ))
                    )}
                </section>
            </main>
        </>
    );
}

PersonalFeedShow.layout = {
    breadcrumbs: [{ title: 'My feeds', href: '/my-feeds' }],
};

import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowRight,
    FileText,
    Image as ImageIcon,
    LockKeyhole,
    Plus,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import type { PostMedia } from '@/components/social/post-image';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

type Draft = {
    id: number;
    body: string;
    updatedAt: string;
    editUrl: string;
    space: { name: string; slug: string };
    media: PostMedia | null;
};

type DraftsProps = {
    drafts: Draft[];
    limit: number;
    status?: string;
};

const updatedLabel = (value: string) =>
    new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));

export default function Drafts({ drafts, limit, status }: DraftsProps) {
    const [deleting, setDeleting] = useState<Draft | null>(null);

    const deleteDraft = () => {
        if (!deleting) {
            return;
        }

        router.delete(`/drafts/${deleting.id}`, {
            preserveScroll: true,
            onFinish: () => setDeleting(null),
        });
    };

    return (
        <>
            <Head title="Drafts" />
            <main className="social-page max-w-6xl">
                <header className="social-page-heading">
                    <div className="flex flex-wrap items-end justify-between gap-4">
                        <div>
                            <p className="social-eyebrow">
                                <LockKeyhole
                                    className="size-3.5"
                                    aria-hidden="true"
                                />
                                Private workspace
                            </p>
                            <h1 className="mt-2 text-3xl font-black tracking-[-0.045em] sm:text-4xl">
                                Your drafts
                            </h1>
                            <p className="mt-2 max-w-2xl text-sm leading-6 text-muted-foreground sm:text-base">
                                Shape an idea, keep its image and audience
                                private, then publish only when it is ready.
                            </p>
                        </div>
                        <Button asChild className="min-h-11 rounded-xl">
                            <Link href="/compose">
                                <Plus className="size-4" aria-hidden="true" />
                                New post
                            </Link>
                        </Button>
                    </div>
                </header>

                {status && (
                    <div
                        role="status"
                        className="mt-5 rounded-2xl border border-primary/20 bg-primary/8 px-4 py-3 text-sm font-bold"
                    >
                        {status}
                    </div>
                )}

                <div className="mt-5 flex items-center justify-between gap-3 px-1">
                    <p className="text-sm font-black">
                        {drafts.length.toLocaleString()}{' '}
                        {drafts.length === 1 ? 'draft' : 'drafts'}
                    </p>
                    <p className="text-xs font-semibold text-muted-foreground">
                        Up to {limit.toLocaleString()} private drafts
                    </p>
                </div>

                {drafts.length === 0 ? (
                    <section className="social-card mt-4 rounded-[1.75rem] px-6 py-16 text-center">
                        <span className="mx-auto flex size-14 items-center justify-center rounded-2xl bg-secondary text-primary">
                            <FileText className="size-6" aria-hidden="true" />
                        </span>
                        <h2 className="mt-4 text-xl font-black">
                            No unfinished posts.
                        </h2>
                        <p className="mx-auto mt-2 max-w-md text-sm leading-6 text-muted-foreground">
                            Start writing and save privately whenever you want
                            to return later.
                        </p>
                        <Button asChild className="mt-6 rounded-xl">
                            <Link href="/compose">Create your first post</Link>
                        </Button>
                    </section>
                ) : (
                    <section
                        className="mt-4 grid gap-4 md:grid-cols-2"
                        aria-label="Saved drafts"
                    >
                        {drafts.map((draft) => (
                            <article
                                key={draft.id}
                                className="social-card social-card-interactive group flex min-w-0 flex-col overflow-hidden rounded-[1.5rem]"
                            >
                                {draft.media ? (
                                    <Link
                                        href={draft.editUrl}
                                        className="relative block aspect-[16/8] overflow-hidden bg-secondary"
                                    >
                                        <img
                                            src={draft.media.url}
                                            alt={draft.media.alt}
                                            className="size-full object-cover transition-transform duration-300 group-hover:scale-[1.015]"
                                        />
                                        <span className="absolute top-3 left-3 inline-flex items-center gap-1.5 rounded-full bg-foreground/80 px-3 py-1.5 text-[0.68rem] font-extrabold text-background backdrop-blur">
                                            <ImageIcon
                                                className="size-3.5"
                                                aria-hidden="true"
                                            />
                                            Image draft
                                        </span>
                                    </Link>
                                ) : (
                                    <div className="h-2 bg-gradient-to-r from-primary via-primary/50 to-mint" />
                                )}
                                <div className="flex flex-1 flex-col p-5">
                                    <div className="flex items-start justify-between gap-3">
                                        <div className="min-w-0">
                                            <p className="truncate text-xs font-extrabold tracking-[0.1em] text-primary uppercase">
                                                {draft.space.name}
                                            </p>
                                            <p className="mt-1 text-xs font-semibold text-muted-foreground">
                                                Updated{' '}
                                                {updatedLabel(draft.updatedAt)}
                                            </p>
                                        </div>
                                        <span className="inline-flex shrink-0 items-center gap-1 rounded-full bg-secondary px-2.5 py-1 text-[0.65rem] font-extrabold text-muted-foreground">
                                            <LockKeyhole
                                                className="size-3"
                                                aria-hidden="true"
                                            />
                                            Private
                                        </span>
                                    </div>
                                    <Link
                                        href={draft.editUrl}
                                        className="social-focus mt-4 line-clamp-4 rounded-lg text-base leading-7 font-semibold tracking-[-0.01em] hover:text-primary"
                                    >
                                        {draft.body}
                                    </Link>
                                    <div className="mt-auto flex items-center justify-between gap-3 pt-5">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            onClick={() => setDeleting(draft)}
                                            className="h-11 rounded-xl px-3 text-muted-foreground hover:text-destructive"
                                        >
                                            <Trash2
                                                className="size-4"
                                                aria-hidden="true"
                                            />
                                            Delete
                                        </Button>
                                        <Button
                                            asChild
                                            variant="outline"
                                            className="h-11 rounded-xl"
                                        >
                                            <Link href={draft.editUrl}>
                                                Continue
                                                <ArrowRight
                                                    className="size-4"
                                                    aria-hidden="true"
                                                />
                                            </Link>
                                        </Button>
                                    </div>
                                </div>
                            </article>
                        ))}
                    </section>
                )}
            </main>

            <Dialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
            >
                <DialogContent className="rounded-[1.5rem] sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Delete this draft?</DialogTitle>
                        <DialogDescription>
                            Its text and private image will be permanently
                            removed. This cannot be undone.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                Keep draft
                            </Button>
                        </DialogClose>
                        <Button
                            type="button"
                            variant="destructive"
                            onClick={deleteDraft}
                        >
                            Delete draft
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

Drafts.layout = {
    breadcrumbs: [{ title: 'Drafts', href: '/drafts' }],
};

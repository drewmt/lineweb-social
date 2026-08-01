import { Head, Link, useForm, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    ChevronDown,
    FileText,
    LockKeyhole,
    Send,
    ShieldCheck,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { AvatarMark } from '@/components/social/avatar-mark';
import { PostGalleryEditor } from '@/components/social/post-gallery-editor';
import type {
    ExistingGalleryImage,
    PendingGalleryImage,
} from '@/components/social/post-gallery-editor';
import { PostPollEditor } from '@/components/social/post-poll';
import type { PostPollDraft } from '@/components/social/post-poll';
import { Button } from '@/components/ui/button';
import type { Auth } from '@/types';

type PostingSpace = {
    name: string;
    slug: string;
    visibility: 'public' | 'private' | 'hidden';
};

type Draft = {
    id: number;
    body: string;
    updatedAt: string;
    editUrl: string;
    space: { name: string; slug: string };
    mediaItems: ExistingGalleryImage[];
    poll: {
        question: string;
        options: string[];
        duration: number | null;
    } | null;
};

type ComposeProps = {
    spaces: PostingSpace[];
    selectedSpace: string | null;
    draft: Draft | null;
    status?: string;
};

type ComposerData = {
    body: string;
    space: string;
    images: File[];
    image_alts: string[];
    retained_media: number[];
    retained_media_alts: Record<string, string>;
    remove_image: boolean;
    poll_question: string;
    poll_options: string[];
    poll_duration: string;
};

const visibilityLabel = (value: PostingSpace['visibility']) =>
    value === 'public'
        ? 'Public Space'
        : value === 'private'
          ? 'Private Space'
          : 'Hidden Space';

export default function Compose({
    spaces,
    selectedSpace,
    draft,
    status,
}: ComposeProps) {
    const { auth, draftSummary } = usePage<{
        auth: Auth;
        draftSummary: { count: number };
    }>().props;
    const [existingMedia, setExistingMedia] = useState<ExistingGalleryImage[]>(
        draft?.mediaItems ?? [],
    );
    const [pendingMedia, setPendingMedia] = useState<PendingGalleryImage[]>([]);
    const previewUrls = useRef(new Set<string>());
    const form = useForm<ComposerData>({
        body: draft?.body ?? '',
        space: selectedSpace ?? spaces[0]?.slug ?? '',
        images: [],
        image_alts: [],
        retained_media: (draft?.mediaItems ?? []).map((item) => item.id),
        retained_media_alts: Object.fromEntries(
            (draft?.mediaItems ?? []).map((item) => [
                String(item.id),
                item.alt,
            ]),
        ),
        remove_image: false,
        poll_question: draft?.poll?.question ?? '',
        poll_options: draft?.poll?.options ?? [],
        poll_duration: draft?.poll?.duration?.toString() ?? '',
    });

    useEffect(
        () => () => {
            previewUrls.current.forEach((url) => URL.revokeObjectURL(url));
            previewUrls.current.clear();
        },
        [],
    );

    const syncExistingMedia = (items: ExistingGalleryImage[]) => {
        setExistingMedia(items);
        form.setData(
            'retained_media',
            items.map((item) => item.id),
        );
        form.setData(
            'retained_media_alts',
            Object.fromEntries(
                items.map((item) => [String(item.id), item.alt]),
            ),
        );
        form.setData(
            'remove_image',
            Boolean(draft?.mediaItems.length) && items.length === 0,
        );
    };

    const syncPendingMedia = (items: PendingGalleryImage[]) => {
        setPendingMedia(items);
        form.setData(
            'images',
            items.map((item) => item.file),
        );
        form.setData(
            'image_alts',
            items.map((item) => item.alt),
        );
    };

    const addFiles = (files: File[]) => {
        const available = Math.max(
            0,
            4 - existingMedia.length - pendingMedia.length,
        );
        const additions = files.slice(0, available).map((file, index) => {
            const url = URL.createObjectURL(file);
            previewUrls.current.add(url);

            return {
                key:
                    typeof crypto.randomUUID === 'function'
                        ? crypto.randomUUID()
                        : `${file.name}-${file.lastModified}-${index}`,
                file,
                url,
                alt: '',
            };
        });

        if (additions.length > 0) {
            syncPendingMedia([...pendingMedia, ...additions]);
        }
    };

    const updateExistingAlt = (id: number, alt: string) => {
        syncExistingMedia(
            existingMedia.map((item) =>
                item.id === id ? { ...item, alt } : item,
            ),
        );
    };

    const updatePendingAlt = (key: string, alt: string) => {
        syncPendingMedia(
            pendingMedia.map((item) =>
                item.key === key ? { ...item, alt } : item,
            ),
        );
    };

    const removeExisting = (id: number) => {
        syncExistingMedia(existingMedia.filter((item) => item.id !== id));
    };

    const removePending = (key: string) => {
        const removed = pendingMedia.find((item) => item.key === key);

        if (removed) {
            URL.revokeObjectURL(removed.url);
            previewUrls.current.delete(removed.url);
        }

        syncPendingMedia(pendingMedia.filter((item) => item.key !== key));
    };

    const clearGallery = () => {
        pendingMedia.forEach((item) => URL.revokeObjectURL(item.url));
        previewUrls.current.clear();
        setExistingMedia([]);
        setPendingMedia([]);
        form.setData('images', []);
        form.setData('image_alts', []);
        form.setData('retained_media', []);
        form.setData('retained_media_alts', {});
        form.setData('remove_image', false);
    };

    const saveDraft = () => {
        form.transform((data) =>
            draft ? { ...data, _method: 'patch' } : data,
        );
        form.post(draft ? `/drafts/${draft.id}` : '/drafts', {
            forceFormData: true,
            preserveScroll: true,
        });
    };

    const publish = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.transform((data) => data);
        form.post(
            draft
                ? `/drafts/${draft.id}/publish`
                : `/spaces/${encodeURIComponent(form.data.space)}/posts`,
            {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => {
                    if (!draft) {
                        clearGallery();
                        form.reset(
                            'body',
                            'poll_question',
                            'poll_options',
                            'poll_duration',
                        );
                    }
                },
            },
        );
    };

    const galleryErrors = form.errors as Record<string, string>;
    const imageError = Object.entries(galleryErrors).find(
        ([key]) => key === 'image' || key.startsWith('images'),
    )?.[1];
    const altError = Object.entries(galleryErrors).find(
        ([key]) =>
            key.startsWith('image_alts') ||
            key.startsWith('retained_media_alts'),
    )?.[1];
    const poll =
        form.data.poll_question !== '' ||
        form.data.poll_options.length > 0 ||
        form.data.poll_duration !== ''
            ? {
                  question: form.data.poll_question,
                  options: form.data.poll_options,
                  duration: form.data.poll_duration,
              }
            : null;
    const updatePoll = (value: PostPollDraft | null) => {
        form.setData('poll_question', value?.question ?? '');
        form.setData('poll_options', value?.options ?? []);
        form.setData('poll_duration', value?.duration ?? '');
    };
    const canSubmit =
        (form.data.body.trim() !== '' || poll !== null) &&
        form.data.space !== '' &&
        existingMedia.every((item) => item.alt.trim() !== '') &&
        pendingMedia.every((item) => item.alt.trim() !== '');

    return (
        <>
            <Head title={draft ? 'Edit draft' : 'Create a post'} />
            <main className="social-page max-w-6xl pt-3 sm:pt-7">
                <header className="mb-4 flex items-center justify-between gap-3 px-1 sm:mb-6">
                    <div className="flex min-w-0 items-center gap-3">
                        <Link
                            href="/feed"
                            aria-label="Back to feed"
                            className="social-focus flex size-11 shrink-0 items-center justify-center rounded-full border border-border/75 bg-card text-foreground transition-colors hover:border-primary/25 hover:bg-primary/6"
                        >
                            <ArrowLeft className="size-5" aria-hidden="true" />
                        </Link>
                        <div className="min-w-0">
                            <p className="social-eyebrow">
                                {draft ? (
                                    <FileText className="size-3.5" />
                                ) : (
                                    <Send className="size-3.5" />
                                )}
                                {draft ? 'Private draft' : 'New conversation'}
                            </p>
                            <h1 className="truncate text-xl font-black tracking-[-0.035em] sm:text-3xl">
                                <span className="sm:hidden">
                                    {draft ? 'Edit draft' : 'New post'}
                                </span>
                                <span className="hidden sm:inline">
                                    {draft
                                        ? 'Keep shaping your post'
                                        : 'Create a post'}
                                </span>
                            </h1>
                        </div>
                    </div>
                    <Button asChild variant="ghost" className="rounded-xl">
                        <Link href="/drafts">
                            Drafts
                            {draftSummary.count > 0 && (
                                <span className="rounded-full bg-secondary px-2 py-0.5 text-xs tabular-nums">
                                    {draftSummary.count}
                                </span>
                            )}
                        </Link>
                    </Button>
                </header>

                {status && (
                    <div
                        role="status"
                        className="mb-4 rounded-2xl border border-primary/20 bg-primary/8 px-4 py-3 text-sm font-bold"
                    >
                        {status}
                    </div>
                )}

                {spaces.length === 0 ? (
                    <section className="social-card rounded-[1.75rem] px-6 py-16 text-center">
                        <span className="mx-auto flex size-14 items-center justify-center rounded-2xl bg-secondary text-primary">
                            <ShieldCheck
                                className="size-6"
                                aria-hidden="true"
                            />
                        </span>
                        <h2 className="mt-4 text-xl font-black">
                            Join a Space before posting.
                        </h2>
                        <p className="mx-auto mt-2 max-w-md text-sm leading-6 text-muted-foreground">
                            Posts always belong to a community. Join an existing
                            Space or create one you can shape.
                        </p>
                        <Button asChild className="mt-6 rounded-xl">
                            <Link href="/spaces">Explore Spaces</Link>
                        </Button>
                    </section>
                ) : (
                    <div className="grid items-start gap-5 lg:grid-cols-[minmax(0,46rem)_18rem] lg:justify-center">
                        <form
                            onSubmit={publish}
                            className="social-card -mx-3 overflow-hidden rounded-none border-x-0 sm:mx-0 sm:rounded-[1.75rem] sm:border-x"
                        >
                            <div className="flex items-center gap-3 border-b border-border/65 px-4 py-4 sm:px-6 sm:py-5">
                                <AvatarMark
                                    name={auth.user.name}
                                    className="size-12"
                                />
                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-sm font-black">
                                        {auth.user.name}
                                    </p>
                                    <p className="mt-0.5 truncate text-xs font-semibold text-muted-foreground">
                                        @{auth.user.handle}
                                    </p>
                                </div>
                                <span className="inline-flex items-center gap-1.5 rounded-full bg-secondary/75 px-3 py-1.5 text-[0.68rem] font-extrabold text-muted-foreground">
                                    <LockKeyhole
                                        className="size-3.5"
                                        aria-hidden="true"
                                    />
                                    {draft ? 'Draft' : 'Not published'}
                                </span>
                            </div>

                            <div className="px-4 pt-5 sm:px-6">
                                <label className="block">
                                    <span className="text-[0.68rem] font-extrabold tracking-[0.12em] text-muted-foreground uppercase">
                                        Post to
                                    </span>
                                    <span className="relative mt-2 flex min-h-14 items-center">
                                        <select
                                            value={form.data.space}
                                            onChange={(event) =>
                                                form.setData(
                                                    'space',
                                                    event.target.value,
                                                )
                                            }
                                            className="social-input-surface social-focus h-14 w-full appearance-none px-4 pr-12 text-sm font-black"
                                        >
                                            {spaces.map((space) => (
                                                <option
                                                    key={space.slug}
                                                    value={space.slug}
                                                >
                                                    {space.name} ·{' '}
                                                    {visibilityLabel(
                                                        space.visibility,
                                                    )}
                                                </option>
                                            ))}
                                        </select>
                                        <ChevronDown
                                            className="pointer-events-none absolute right-4 size-4 text-muted-foreground"
                                            aria-hidden="true"
                                        />
                                    </span>
                                </label>
                                <InputError
                                    className="mt-2"
                                    message={form.errors.space}
                                />
                            </div>

                            <div className="px-4 sm:px-6">
                                <label htmlFor="post-body" className="sr-only">
                                    Post text
                                </label>
                                <textarea
                                    id="post-body"
                                    name="body"
                                    autoFocus
                                    value={form.data.body}
                                    onChange={(event) =>
                                        form.setData('body', event.target.value)
                                    }
                                    maxLength={2000}
                                    rows={5}
                                    placeholder="What is worth sharing with this community?"
                                    className="min-h-52 w-full resize-none bg-transparent py-6 text-[1.12rem] leading-8 font-medium tracking-[-0.01em] outline-none placeholder:text-muted-foreground/55 sm:min-h-56 sm:resize-y sm:text-xl sm:leading-9"
                                />
                                <InputError
                                    className="pb-3"
                                    message={form.errors.body}
                                />
                            </div>

                            <PostGalleryEditor
                                existing={existingMedia}
                                pending={pendingMedia}
                                onFiles={addFiles}
                                onExistingAlt={updateExistingAlt}
                                onPendingAlt={updatePendingAlt}
                                onRemoveExisting={removeExisting}
                                onRemovePending={removePending}
                                imageError={imageError}
                                altError={altError}
                            />
                            <PostPollEditor
                                value={poll}
                                onChange={updatePoll}
                                errors={galleryErrors}
                            />

                            <div className="sticky bottom-[5.75rem] z-20 flex items-center justify-between gap-3 border-t border-border/70 bg-card/94 px-4 py-3 backdrop-blur-xl sm:static sm:px-6 sm:py-4">
                                <div className="flex min-w-0 items-center gap-2">
                                    <span className="text-xs font-bold text-muted-foreground">
                                        {form.data.body.length.toLocaleString()}{' '}
                                        / 2,000
                                    </span>
                                    {existingMedia.length +
                                        pendingMedia.length >
                                        0 && (
                                        <span className="hidden text-xs font-bold text-muted-foreground sm:inline">
                                            ·{' '}
                                            {existingMedia.length +
                                                pendingMedia.length}{' '}
                                            images
                                        </span>
                                    )}
                                </div>
                                <div className="flex items-center gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        disabled={form.processing || !canSubmit}
                                        onClick={saveDraft}
                                        className="h-11 rounded-xl px-3 sm:px-4"
                                    >
                                        <FileText
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        Save draft
                                    </Button>
                                    <Button
                                        type="submit"
                                        disabled={form.processing || !canSubmit}
                                        className="h-11 rounded-xl px-4 sm:px-5"
                                    >
                                        <Send
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        Publish
                                    </Button>
                                </div>
                            </div>
                        </form>

                        <aside className="space-y-4 lg:sticky lg:top-5">
                            <div className="social-card rounded-[1.4rem] p-5">
                                <p className="flex items-center gap-2 text-xs font-extrabold tracking-[0.12em] text-primary uppercase">
                                    <LockKeyhole
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    Private by default
                                </p>
                                <p className="mt-3 text-sm leading-6 text-muted-foreground">
                                    Draft text and images are visible only to
                                    you. Mentions, topics, and notifications are
                                    created only when you publish.
                                </p>
                            </div>
                            <div className="rounded-[1.4rem] bg-foreground p-5 text-background">
                                <p className="text-xs font-extrabold tracking-[0.12em] text-mint uppercase">
                                    Calm publishing
                                </p>
                                <p className="mt-3 text-sm leading-6 text-background/68">
                                    Take your time. The selected Space can be
                                    changed before publication, and every image
                                    keeps its accessible description.
                                </p>
                            </div>
                        </aside>
                    </div>
                )}
            </main>
        </>
    );
}

Compose.layout = {
    breadcrumbs: [{ title: 'Compose', href: '/compose' }],
};

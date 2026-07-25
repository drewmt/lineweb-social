import { Head, Link, useForm, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    ChevronDown,
    FileText,
    ImagePlus,
    LockKeyhole,
    Send,
    ShieldCheck,
    X,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { AvatarMark } from '@/components/social/avatar-mark';
import type { PostMedia } from '@/components/social/post-image';
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
    media: PostMedia | null;
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
    image: File | null;
    image_alt: string;
    remove_image: boolean;
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
    const fileInput = useRef<HTMLInputElement>(null);
    const [previewUrl, setPreviewUrl] = useState<string | null>(null);
    const form = useForm<ComposerData>({
        body: draft?.body ?? '',
        space: selectedSpace ?? spaces[0]?.slug ?? '',
        image: null,
        image_alt: draft?.media?.alt ?? '',
        remove_image: false,
    });
    const visibleImage = previewUrl
        ? { url: previewUrl, alt: '' }
        : !form.data.remove_image && draft?.media
          ? draft.media
          : null;

    useEffect(
        () => () => {
            if (previewUrl) {
                URL.revokeObjectURL(previewUrl);
            }
        },
        [previewUrl],
    );

    const selectImage = (file: File | null) => {
        if (previewUrl) {
            URL.revokeObjectURL(previewUrl);
        }

        form.setData('image', file);
        form.setData('remove_image', false);
        setPreviewUrl(file ? URL.createObjectURL(file) : null);

        if (!file && !draft?.media) {
            form.setData('image_alt', '');
        }
    };

    const removeImage = () => {
        if (previewUrl) {
            URL.revokeObjectURL(previewUrl);
        }

        setPreviewUrl(null);
        form.setData('image', null);
        form.setData('image_alt', '');
        form.setData('remove_image', draft?.media !== null);

        if (fileInput.current) {
            fileInput.current.value = '';
        }
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
                        removeImage();
                        form.reset(
                            'body',
                            'image',
                            'image_alt',
                            'remove_image',
                        );
                    }
                },
            },
        );
    };

    const canSubmit =
        form.data.body.trim() !== '' &&
        form.data.space !== '' &&
        (!visibleImage || form.data.image_alt.trim() !== '');

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
                                    required
                                    placeholder="What is worth sharing with this community?"
                                    className="min-h-52 w-full resize-none bg-transparent py-6 text-[1.12rem] leading-8 font-medium tracking-[-0.01em] outline-none placeholder:text-muted-foreground/55 sm:min-h-56 sm:resize-y sm:text-xl sm:leading-9"
                                />
                                <InputError
                                    className="pb-3"
                                    message={form.errors.body}
                                />
                            </div>

                            {visibleImage && (
                                <div className="mx-4 mb-5 overflow-hidden rounded-[1.35rem] border border-border/75 bg-background sm:mx-6">
                                    <div className="relative aspect-[16/9] overflow-hidden bg-secondary">
                                        <img
                                            src={visibleImage.url}
                                            alt=""
                                            className="size-full object-cover"
                                        />
                                        <button
                                            type="button"
                                            onClick={removeImage}
                                            aria-label="Remove selected image"
                                            className="social-focus absolute top-3 right-3 flex size-11 items-center justify-center rounded-full bg-foreground/82 text-background shadow-lg backdrop-blur transition-colors hover:bg-foreground"
                                        >
                                            <X
                                                className="size-5"
                                                aria-hidden="true"
                                            />
                                        </button>
                                    </div>
                                    <label className="block px-4 py-4 text-sm font-extrabold">
                                        Alternative text
                                        <span className="mt-0.5 block text-xs leading-5 font-medium text-muted-foreground">
                                            Describe the image for members using
                                            screen readers.
                                        </span>
                                        <input
                                            type="text"
                                            value={form.data.image_alt}
                                            onChange={(event) =>
                                                form.setData(
                                                    'image_alt',
                                                    event.target.value,
                                                )
                                            }
                                            required
                                            maxLength={300}
                                            placeholder="A concise description of the image"
                                            className="social-input-surface social-focus mt-3 h-12 w-full px-4 text-sm font-semibold"
                                        />
                                    </label>
                                    <InputError
                                        className="px-4 pb-4"
                                        message={form.errors.image}
                                    />
                                    <InputError
                                        className="px-4 pb-4"
                                        message={form.errors.image_alt}
                                    />
                                </div>
                            )}

                            <div className="sticky bottom-[5.75rem] z-20 flex items-center justify-between gap-3 border-t border-border/70 bg-card/94 px-4 py-3 backdrop-blur-xl sm:static sm:px-6 sm:py-4">
                                <div className="flex min-w-0 items-center gap-2">
                                    <input
                                        ref={fileInput}
                                        type="file"
                                        name="image"
                                        accept="image/jpeg,image/png,image/webp"
                                        className="sr-only"
                                        onChange={(event) =>
                                            selectImage(
                                                event.target.files?.[0] ?? null,
                                            )
                                        }
                                    />
                                    {!visibleImage && (
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            aria-label="Add image"
                                            onClick={() =>
                                                fileInput.current?.click()
                                            }
                                            className="size-11 rounded-xl p-0 sm:w-auto sm:px-3"
                                        >
                                            <ImagePlus
                                                className="size-5"
                                                aria-hidden="true"
                                            />
                                            <span className="hidden sm:inline">
                                                Add image
                                            </span>
                                        </Button>
                                    )}
                                    <span className="hidden text-xs font-bold text-muted-foreground sm:inline">
                                        {form.data.body.length.toLocaleString()}{' '}
                                        / 2,000
                                    </span>
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

import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Clock3, ImagePlus, Send, ShieldCheck } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';

type Background = 'ink' | 'ocean' | 'violet' | 'sunset' | 'mint';

const backgroundClasses: Record<Background, string> = {
    ink: 'from-slate-950 via-slate-800 to-slate-950',
    ocean: 'from-cyan-500 via-blue-600 to-indigo-800',
    violet: 'from-fuchsia-500 via-violet-600 to-indigo-800',
    sunset: 'from-amber-400 via-orange-500 to-rose-600',
    mint: 'from-emerald-300 via-teal-500 to-cyan-700',
};

export default function CreateStory({
    spaces,
    backgrounds,
    activeLimit,
    lifetimeHours,
}: {
    spaces: { name: string; slug: string }[];
    backgrounds: Background[];
    activeLimit: number;
    lifetimeHours: number;
}) {
    const [preview, setPreview] = useState<string | null>(null);
    const { data, setData, post, processing, errors } = useForm<{
        space: string;
        body: string;
        background: Background;
        image: File | null;
        alt_text: string;
    }>({
        space: spaces[0]?.slug ?? '',
        body: '',
        background: backgrounds[0] ?? 'ink',
        image: null,
        alt_text: '',
    });

    useEffect(
        () => () => {
            if (preview) {
                URL.revokeObjectURL(preview);
            }
        },
        [preview],
    );

    const selectedSpace = useMemo(
        () => spaces.find((space) => space.slug === data.space),
        [data.space, spaces],
    );

    const chooseImage = (file: File | null) => {
        if (preview) {
            URL.revokeObjectURL(preview);
        }

        const nextPreview = file ? URL.createObjectURL(file) : null;

        setPreview(nextPreview);
        setData('image', file);
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (!data.space) {
            return;
        }

        post(`/spaces/${encodeURIComponent(data.space)}/stories`, {
            forceFormData: true,
        });
    };

    return (
        <>
            <Head title="Create a Story" />
            <main className="social-page">
                <div className="mx-auto max-w-5xl">
                    <Link
                        href="/feed"
                        className="social-focus mb-4 inline-flex min-h-10 items-center gap-2 rounded-xl px-3 text-sm font-extrabold text-muted-foreground hover:bg-secondary hover:text-foreground"
                    >
                        <ArrowLeft className="size-4" aria-hidden="true" />
                        Back to feed
                    </Link>
                    <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,24rem)_minmax(0,1fr)]">
                        <section aria-label="Story preview">
                            <div
                                className={`relative mx-auto aspect-[9/16] max-h-[68vh] overflow-hidden rounded-[2rem] bg-gradient-to-br shadow-[0_28px_70px_-35px_rgba(15,23,42,.75)] ${backgroundClasses[data.background]}`}
                            >
                                {preview && (
                                    <img
                                        src={preview}
                                        alt=""
                                        className="absolute inset-0 size-full object-cover"
                                    />
                                )}
                                <div className="absolute inset-x-0 top-0 h-24 bg-gradient-to-b from-black/45 to-transparent" />
                                <div className="absolute top-5 right-5 left-5 flex items-center justify-between text-xs font-extrabold text-white">
                                    <span>
                                        {selectedSpace?.name ?? 'Your Space'}
                                    </span>
                                    <span className="rounded-full bg-black/25 px-2.5 py-1 backdrop-blur-sm">
                                        24h
                                    </span>
                                </div>
                                {data.body && (
                                    <div className="absolute inset-x-5 bottom-7 rounded-2xl bg-black/35 px-4 py-3 text-center text-lg leading-7 font-black whitespace-pre-wrap text-white backdrop-blur-sm">
                                        {data.body}
                                    </div>
                                )}
                            </div>
                        </section>

                        <form
                            onSubmit={submit}
                            className="social-card order-first rounded-[1.75rem] p-5 sm:p-7 lg:order-none"
                        >
                            <p className="text-[0.68rem] font-extrabold tracking-[0.16em] text-primary uppercase">
                                A lightweight community moment
                            </p>
                            <h1 className="mt-1 text-3xl font-black tracking-[-0.045em]">
                                Create a Story
                            </h1>
                            <p className="mt-2 text-sm leading-6 text-muted-foreground">
                                Share one image, a short thought, or both. It is
                                permanently removed after {lifetimeHours} hours.
                            </p>

                            {spaces.length === 0 ? (
                                <div className="mt-6 rounded-2xl bg-secondary/70 p-4 text-sm leading-6">
                                    Join a Space before creating a Story.
                                </div>
                            ) : (
                                <>
                                    <label className="mt-6 block text-sm font-extrabold">
                                        Space
                                        <select
                                            value={data.space}
                                            onChange={(event) =>
                                                setData(
                                                    'space',
                                                    event.target.value,
                                                )
                                            }
                                            className="social-inset social-focus mt-2 h-12 w-full px-3.5 text-sm font-bold"
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
                                    </label>
                                    <InputError
                                        className="mt-2"
                                        message={errors.space}
                                    />

                                    <label className="mt-5 block text-sm font-extrabold">
                                        Message
                                        <textarea
                                            value={data.body}
                                            onChange={(event) =>
                                                setData(
                                                    'body',
                                                    event.target.value,
                                                )
                                            }
                                            maxLength={280}
                                            rows={4}
                                            placeholder="What is happening in your community?"
                                            className="social-inset social-focus mt-2 w-full resize-y px-3.5 py-3 text-sm leading-6"
                                        />
                                    </label>
                                    <div className="mt-1 flex items-start justify-between gap-3">
                                        <InputError message={errors.body} />
                                        <span className="ml-auto text-xs font-semibold text-muted-foreground">
                                            {data.body.length}/280
                                        </span>
                                    </div>

                                    <fieldset className="mt-5">
                                        <legend className="text-sm font-extrabold">
                                            Background
                                        </legend>
                                        <div className="mt-2 flex flex-wrap gap-2">
                                            {backgrounds.map((background) => (
                                                <button
                                                    key={background}
                                                    type="button"
                                                    onClick={() =>
                                                        setData(
                                                            'background',
                                                            background,
                                                        )
                                                    }
                                                    aria-label={`Use ${background} background`}
                                                    aria-pressed={
                                                        data.background ===
                                                        background
                                                    }
                                                    className={`social-focus size-11 rounded-full bg-gradient-to-br ${backgroundClasses[background]} ${data.background === background ? 'ring-2 ring-primary ring-offset-2 ring-offset-card' : ''}`}
                                                />
                                            ))}
                                        </div>
                                    </fieldset>

                                    <label className="social-focus mt-5 flex min-h-14 cursor-pointer items-center gap-3 rounded-2xl border border-dashed border-border bg-secondary/35 px-4 py-3 text-sm font-extrabold transition-colors hover:bg-secondary/65">
                                        <ImagePlus
                                            className="size-5 text-primary"
                                            aria-hidden="true"
                                        />
                                        <span className="min-w-0 flex-1 truncate">
                                            {data.image
                                                ? data.image.name
                                                : 'Add a photo'}
                                        </span>
                                        <input
                                            type="file"
                                            accept="image/jpeg,image/png,image/webp"
                                            className="sr-only"
                                            onChange={(event) =>
                                                chooseImage(
                                                    event.target.files?.[0] ??
                                                        null,
                                                )
                                            }
                                        />
                                    </label>
                                    <InputError
                                        className="mt-2"
                                        message={errors.image}
                                    />

                                    {data.image && (
                                        <label className="mt-4 block text-sm font-extrabold">
                                            Image description{' '}
                                            <span className="font-semibold text-muted-foreground">
                                                optional
                                            </span>
                                            <input
                                                value={data.alt_text}
                                                onChange={(event) =>
                                                    setData(
                                                        'alt_text',
                                                        event.target.value,
                                                    )
                                                }
                                                maxLength={300}
                                                placeholder="Describe the important visual details"
                                                className="social-inset social-focus mt-2 h-12 w-full px-3.5 text-sm font-semibold"
                                            />
                                        </label>
                                    )}
                                    <InputError
                                        className="mt-2"
                                        message={errors.alt_text}
                                    />

                                    <div className="mt-6 grid gap-2 rounded-2xl bg-secondary/55 p-4 text-xs leading-5 text-muted-foreground sm:grid-cols-2">
                                        <span className="flex gap-2">
                                            <Clock3
                                                className="mt-0.5 size-4 shrink-0 text-primary"
                                                aria-hidden="true"
                                            />
                                            Automatic permanent deletion after{' '}
                                            {lifetimeHours} hours
                                        </span>
                                        <span className="flex gap-2">
                                            <ShieldCheck
                                                className="mt-0.5 size-4 shrink-0 text-primary"
                                                aria-hidden="true"
                                            />
                                            Space access rules apply, with no
                                            viewer list
                                        </span>
                                    </div>

                                    <Button
                                        type="submit"
                                        disabled={
                                            processing ||
                                            (data.body.trim() === '' &&
                                                !data.image)
                                        }
                                        className="mt-6 h-12 w-full rounded-2xl"
                                    >
                                        <Send
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        Publish Story
                                    </Button>
                                    <p className="mt-3 text-center text-xs leading-5 text-muted-foreground">
                                        Up to {activeLimit} active Stories per
                                        Space.
                                    </p>
                                </>
                            )}
                        </form>
                    </div>
                </div>
            </main>
        </>
    );
}

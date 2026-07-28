import { ImagePlus, Images, X } from 'lucide-react';
import { useRef } from 'react';
import InputError from '@/components/input-error';
import type { PostMedia } from '@/components/social/post-image';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

export type ExistingGalleryImage = PostMedia & { id: number };

export type PendingGalleryImage = {
    key: string;
    file: File;
    url: string;
    alt: string;
};

export function PostGalleryEditor({
    existing,
    pending,
    onFiles,
    onExistingAlt,
    onPendingAlt,
    onRemoveExisting,
    onRemovePending,
    imageError,
    altError,
    compact = false,
}: {
    existing: ExistingGalleryImage[];
    pending: PendingGalleryImage[];
    onFiles: (files: File[]) => void;
    onExistingAlt: (id: number, alt: string) => void;
    onPendingAlt: (key: string, alt: string) => void;
    onRemoveExisting: (id: number) => void;
    onRemovePending: (key: string) => void;
    imageError?: string;
    altError?: string;
    compact?: boolean;
}) {
    const input = useRef<HTMLInputElement>(null);
    const total = existing.length + pending.length;
    const remaining = Math.max(0, 4 - total);

    return (
        <section
            className={cn(
                'border-t border-border/65 bg-secondary/22',
                compact ? 'px-4 py-4 sm:px-5' : 'px-4 py-5 sm:px-6',
            )}
            aria-labelledby="post-gallery-heading"
        >
            <div className="flex items-center justify-between gap-3">
                <div className="min-w-0">
                    <p
                        id="post-gallery-heading"
                        className="flex items-center gap-2 text-sm font-black"
                    >
                        <Images
                            className="size-4.5 text-primary"
                            aria-hidden="true"
                        />
                        Post gallery
                    </p>
                    <p className="mt-1 text-xs leading-5 font-medium text-muted-foreground">
                        Up to four private, normalized images. Each description
                        is required.
                    </p>
                </div>
                <span className="shrink-0 rounded-full border border-border/75 bg-card px-2.5 py-1 text-[0.68rem] font-extrabold text-muted-foreground tabular-nums">
                    {total} / 4
                </span>
            </div>

            {total > 0 && (
                <div className="mt-4 grid grid-cols-2 gap-3">
                    {existing.map((item, index) => (
                        <GalleryItem
                            key={`existing-${item.id}`}
                            src={item.url}
                            alt={item.alt}
                            position={index + 1}
                            onAlt={(alt) => onExistingAlt(item.id, alt)}
                            onRemove={() => onRemoveExisting(item.id)}
                        />
                    ))}
                    {pending.map((item, index) => (
                        <GalleryItem
                            key={item.key}
                            src={item.url}
                            alt={item.alt}
                            position={existing.length + index + 1}
                            onAlt={(alt) => onPendingAlt(item.key, alt)}
                            onRemove={() => onRemovePending(item.key)}
                            pending
                        />
                    ))}
                </div>
            )}

            <input
                ref={input}
                type="file"
                name="images"
                accept="image/jpeg,image/png,image/webp"
                multiple
                className="sr-only"
                onChange={(event) => {
                    onFiles(
                        Array.from(event.target.files ?? []).slice(
                            0,
                            remaining,
                        ),
                    );
                    event.target.value = '';
                }}
            />

            {remaining > 0 && (
                <Button
                    type="button"
                    variant={total === 0 ? 'outline' : 'ghost'}
                    onClick={() => input.current?.click()}
                    className={cn(
                        'mt-4 min-h-11 rounded-xl',
                        total === 0 && 'w-full border-dashed',
                    )}
                >
                    <ImagePlus className="size-4.5" aria-hidden="true" />
                    {total === 0
                        ? 'Choose images'
                        : `Add ${remaining === 1 ? 'one more' : 'more images'}`}
                </Button>
            )}

            <InputError className="mt-3" message={imageError} />
            <InputError className="mt-2" message={altError} />
        </section>
    );
}

function GalleryItem({
    src,
    alt,
    position,
    onAlt,
    onRemove,
    pending = false,
}: {
    src: string;
    alt: string;
    position: number;
    onAlt: (alt: string) => void;
    onRemove: () => void;
    pending?: boolean;
}) {
    return (
        <article className="min-w-0 overflow-hidden rounded-[1.1rem] border border-border/75 bg-card">
            <div className="relative aspect-[4/3] overflow-hidden bg-secondary">
                <img src={src} alt="" className="size-full object-cover" />
                <span className="absolute top-2 left-2 rounded-full bg-foreground/78 px-2 py-1 text-[0.65rem] font-extrabold text-background backdrop-blur">
                    {position}
                </span>
                {pending && (
                    <span className="absolute bottom-2 left-2 rounded-full bg-card/90 px-2 py-1 text-[0.62rem] font-extrabold text-foreground backdrop-blur">
                        New
                    </span>
                )}
                <button
                    type="button"
                    onClick={onRemove}
                    aria-label={`Remove image ${position}`}
                    className="social-focus absolute top-1 right-1 flex size-11 items-center justify-center rounded-full bg-foreground/78 text-background backdrop-blur transition-colors hover:bg-foreground"
                >
                    <X className="size-4.5" aria-hidden="true" />
                </button>
            </div>
            <label className="block p-3">
                <span className="text-[0.68rem] font-extrabold tracking-[0.08em] text-muted-foreground uppercase">
                    Alternative text
                </span>
                <input
                    type="text"
                    value={alt}
                    onChange={(event) => onAlt(event.target.value)}
                    required
                    maxLength={300}
                    aria-label={`Alternative text for image ${position}`}
                    placeholder="Describe this image"
                    className="social-input-surface social-focus mt-2 h-11 w-full px-3 text-sm font-semibold"
                />
            </label>
        </article>
    );
}

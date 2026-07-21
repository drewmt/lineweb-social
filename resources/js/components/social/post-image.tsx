import { cn } from '@/lib/utils';

export type PostMedia = {
    url: string;
    alt: string;
    width: number;
    height: number;
};

export function PostImage({
    media,
    className,
    eager = false,
}: {
    media: PostMedia;
    className?: string;
    eager?: boolean;
}) {
    const sourceRatio = media.width / media.height;
    const displayRatio = Math.min(16 / 9, Math.max(4 / 5, sourceRatio));

    return (
        <div
            className={cn(
                'relative overflow-hidden rounded-[1.1rem] bg-secondary/70',
                className,
            )}
            style={{ aspectRatio: displayRatio }}
        >
            <img
                src={media.url}
                alt={media.alt}
                width={media.width}
                height={media.height}
                loading={eager ? 'eager' : 'lazy'}
                decoding="async"
                className="absolute inset-0 size-full object-contain"
            />
        </div>
    );
}

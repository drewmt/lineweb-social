import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useRef, useState } from 'react';
import { cn } from '@/lib/utils';

export type PostMedia = {
    id?: number;
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

export function PostGallery({
    media,
    className,
    eager = false,
}: {
    media: PostMedia[];
    className?: string;
    eager?: boolean;
}) {
    const scroller = useRef<HTMLDivElement>(null);
    const [activeIndex, setActiveIndex] = useState(0);
    const items = media.slice(0, 4);
    const sourceRatio = items[0] ? items[0].width / items[0].height : 16 / 9;
    const displayRatio = Math.min(16 / 9, Math.max(4 / 5, sourceRatio));

    if (items.length === 0) {
        return null;
    }

    if (items.length === 1 && items[0]) {
        return (
            <PostImage media={items[0]} className={className} eager={eager} />
        );
    }

    const moveTo = (index: number) => {
        const nextIndex = Math.min(items.length - 1, Math.max(0, index));
        const element = scroller.current;

        if (!element) {
            return;
        }

        element.scrollTo({
            left: element.clientWidth * nextIndex,
            behavior: 'smooth',
        });
        setActiveIndex(nextIndex);
    };

    return (
        <div
            className={cn(
                'group/gallery relative overflow-hidden rounded-[1.1rem] bg-secondary/70',
                className,
            )}
        >
            <div
                ref={scroller}
                onScroll={(event) => {
                    const element = event.currentTarget;

                    if (element.clientWidth > 0) {
                        setActiveIndex(
                            Math.min(
                                items.length - 1,
                                Math.max(
                                    0,
                                    Math.round(
                                        element.scrollLeft /
                                            element.clientWidth,
                                    ),
                                ),
                            ),
                        );
                    }
                }}
                className="flex snap-x snap-mandatory [scrollbar-width:none] overflow-x-auto overscroll-x-contain [&::-webkit-scrollbar]:hidden"
                aria-label={`${items.length}-image post gallery`}
            >
                {items.map((item, index) => (
                    <figure
                        key={item.id ?? `${item.url}-${index}`}
                        className="relative min-w-full snap-center"
                        style={{ aspectRatio: displayRatio }}
                    >
                        <img
                            src={item.url}
                            alt={item.alt}
                            width={item.width}
                            height={item.height}
                            loading={eager && index === 0 ? 'eager' : 'lazy'}
                            decoding="async"
                            className="absolute inset-0 size-full object-contain"
                        />
                    </figure>
                ))}
            </div>

            <span className="pointer-events-none absolute top-3 right-3 rounded-full bg-foreground/78 px-2.5 py-1 text-[0.68rem] font-extrabold text-background shadow-sm backdrop-blur-md">
                {activeIndex + 1} / {items.length}
            </span>

            <button
                type="button"
                onClick={() => moveTo(activeIndex - 1)}
                disabled={activeIndex === 0}
                aria-label="Show previous image"
                className="social-focus absolute top-1/2 left-3 flex size-11 -translate-y-1/2 items-center justify-center rounded-full bg-card/90 text-foreground shadow-md backdrop-blur transition-[opacity,background-color] hover:bg-card disabled:pointer-events-none disabled:opacity-0"
            >
                <ChevronLeft className="size-5" aria-hidden="true" />
            </button>
            <button
                type="button"
                onClick={() => moveTo(activeIndex + 1)}
                disabled={activeIndex === items.length - 1}
                aria-label="Show next image"
                className="social-focus absolute top-1/2 right-3 flex size-11 -translate-y-1/2 items-center justify-center rounded-full bg-card/90 text-foreground shadow-md backdrop-blur transition-[opacity,background-color] hover:bg-card disabled:pointer-events-none disabled:opacity-0"
            >
                <ChevronRight className="size-5" aria-hidden="true" />
            </button>

            <div
                className="pointer-events-none absolute bottom-3 left-1/2 flex -translate-x-1/2 items-center gap-1.5 rounded-full bg-foreground/65 px-2 py-1.5 backdrop-blur-md"
                aria-hidden="true"
            >
                {items.map((item, index) => (
                    <span
                        key={item.id ?? `${item.url}-dot-${index}`}
                        className={cn(
                            'size-1.5 rounded-full bg-background/55 transition-[width,background-color]',
                            activeIndex === index && 'w-4 bg-background',
                        )}
                    />
                ))}
            </div>
        </div>
    );
}

import { cn } from '@/lib/utils';

const covers = [
    {
        src: '/images/space-covers/makers-studio.webp',
        position: '50% 48%',
    },
    {
        src: '/images/space-covers/open-source-meetup.webp',
        position: '50% 45%',
    },
    {
        src: '/images/space-covers/local-founders.webp',
        position: '50% 48%',
    },
] as const;

export function SpaceCover({
    seed,
    className,
}: {
    seed: string;
    className?: string;
}) {
    const index =
        (seed.split(/[-_]/)[0]?.length ?? seed.length) % covers.length;
    const cover = covers[index];

    return (
        <img
            src={cover.src}
            alt=""
            aria-hidden="true"
            loading="eager"
            decoding="async"
            className={cn('h-full w-full object-cover', className)}
            style={{ objectPosition: cover.position }}
        />
    );
}

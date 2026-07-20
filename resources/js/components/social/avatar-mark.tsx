import { cn } from '@/lib/utils';

const initials = (name: string) =>
    name
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part[0])
        .join('')
        .toUpperCase();

export function AvatarMark({
    name,
    className,
}: {
    name: string;
    className?: string;
}) {
    const hue = [...name].reduce(
        (total, character) => total + character.charCodeAt(0),
        0,
    );

    return (
        <span
            className={cn(
                'flex shrink-0 items-center justify-center rounded-full text-xs font-extrabold text-white ring-2 ring-card',
                className,
            )}
            style={{
                background: `linear-gradient(145deg, hsl(${hue % 360} 72% 55%), hsl(${(hue + 36) % 360} 70% 38%))`,
            }}
            aria-hidden="true"
        >
            {initials(name)}
        </span>
    );
}

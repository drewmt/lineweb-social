import { Link } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { cn } from '@/lib/utils';

type SocialLogoProps = {
    compact?: boolean;
    className?: string;
};

export function SocialLogo({ compact = false, className }: SocialLogoProps) {
    return (
        <Link
            href="/feed"
            prefetch
            aria-label="Lineweb Social home"
            className={cn(
                'group inline-flex items-center gap-3 rounded-2xl focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                className,
            )}
        >
            <span className="relative flex size-11 shrink-0 items-center justify-center overflow-hidden rounded-[1.15rem] bg-primary text-primary-foreground shadow-[0_10px_30px_-14px_color-mix(in_oklab,var(--primary)_80%,transparent)] transition-transform duration-200 group-hover:scale-[1.03] group-hover:-rotate-3">
                <span className="absolute -top-3 -right-2 size-7 rounded-full bg-mint/80 blur-[1px]" />
                <AppLogoIcon className="relative size-7" />
            </span>
            {!compact && (
                <span className="min-w-0">
                    <span className="block text-[1.08rem] leading-none font-extrabold tracking-[-0.04em]">
                        Lineweb Social
                    </span>
                    <span className="mt-1 block text-[0.66rem] leading-none font-bold tracking-[0.16em] text-muted-foreground uppercase">
                        Open social
                    </span>
                </span>
            )}
        </Link>
    );
}

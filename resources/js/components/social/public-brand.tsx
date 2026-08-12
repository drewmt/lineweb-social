import { Link } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { cn } from '@/lib/utils';

type PublicBrandProps = {
    className?: string;
    inverse?: boolean;
};

export default function PublicBrand({
    className,
    inverse = false,
}: PublicBrandProps) {
    return (
        <Link
            href="/"
            className={cn(
                'social-focus inline-flex items-center gap-3 rounded-2xl',
                className,
            )}
            aria-label="Lineweb Social home"
        >
            <span
                className={cn(
                    'flex size-10 items-center justify-center rounded-[0.95rem] shadow-[0_14px_28px_-16px_color-mix(in_oklab,var(--primary)_85%,transparent)]',
                    inverse
                        ? 'bg-white text-primary'
                        : 'bg-primary text-primary-foreground',
                )}
            >
                <AppLogoIcon className="size-6" />
            </span>
            <span>
                <span className="block text-sm leading-none font-black tracking-[-0.035em]">
                    Lineweb Social
                </span>
                <span
                    className={cn(
                        'mt-1 block text-[0.61rem] leading-none font-extrabold tracking-[0.15em] uppercase',
                        inverse ? 'text-white/55' : 'text-muted-foreground',
                    )}
                >
                    Open social
                </span>
            </span>
        </Link>
    );
}

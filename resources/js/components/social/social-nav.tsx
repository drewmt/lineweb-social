import { Link, usePage } from '@inertiajs/react';
import {
    Bell,
    Bookmark,
    Compass,
    Feather,
    Home,
    Search,
    Settings2,
    UserRound,
    UserRoundCheck,
    UsersRound,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { UserMenuContent } from '@/components/user-menu-content';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn } from '@/lib/utils';
import type { User } from '@/types';
import { SocialLogo } from './social-logo';

const navItems = [
    { title: 'Home', subtitle: 'Your timeline', href: '/feed', icon: Home },
    {
        title: 'Following',
        subtitle: 'People you chose',
        href: '/following',
        icon: UserRoundCheck,
    },
    {
        title: 'Search',
        subtitle: 'Across your community',
        href: '/search',
        icon: Search,
    },
    {
        title: 'Spaces',
        subtitle: 'Your communities',
        href: '/spaces',
        icon: Compass,
    },
    {
        title: 'People',
        subtitle: 'Discover members',
        href: '/people',
        icon: UsersRound,
    },
    {
        title: 'Saved',
        subtitle: 'Your private reading list',
        href: '/saved',
        icon: Bookmark,
    },
    {
        title: 'Notifications',
        subtitle: 'Updates that matter',
        href: '/notifications',
        icon: Bell,
    },
] as const;

const initials = (name: string) =>
    name
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part[0])
        .join('')
        .toUpperCase();

function UserButton({
    user,
    compact = false,
}: {
    user: User;
    compact?: boolean;
}) {
    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <button
                    type="button"
                    aria-label="Open account menu"
                    className={cn(
                        'social-focus group flex items-center text-left transition-[background-color,transform,border-color] active:translate-y-px',
                        compact
                            ? 'size-11 justify-center rounded-full'
                            : 'w-full gap-3 rounded-2xl border border-border/70 bg-card px-3 py-3 hover:border-primary/20 hover:bg-primary/[0.035]',
                    )}
                >
                    <span className="flex size-10 shrink-0 items-center justify-center rounded-full bg-foreground text-xs font-black text-background ring-2 ring-card">
                        {initials(user.name)}
                    </span>
                    {!compact && (
                        <>
                            <span className="min-w-0 flex-1">
                                <span className="block truncate text-sm font-extrabold">
                                    {user.name}
                                </span>
                                <span className="mt-0.5 block truncate text-xs text-muted-foreground">
                                    @{user.handle}
                                </span>
                            </span>
                            <Settings2
                                className="size-4 text-muted-foreground transition-transform group-hover:rotate-12"
                                aria-hidden="true"
                            />
                        </>
                    )}
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                className="w-64 rounded-2xl p-2"
                align={compact ? 'end' : 'start'}
                side={compact ? 'bottom' : 'right'}
                sideOffset={12}
            >
                <UserMenuContent user={user} />
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

export function DesktopSocialNav() {
    const { auth, notificationSummary } = usePage().props;
    const { isCurrentOrParentUrl } = useCurrentUrl();

    return (
        <aside className="fixed inset-y-0 left-0 z-40 hidden w-[18.5rem] border-r border-border/75 bg-card/92 p-4 backdrop-blur-xl lg:flex lg:flex-col">
            <div className="flex min-h-0 flex-1 flex-col rounded-[1.6rem] bg-background/75 p-3 ring-1 ring-border/55">
                <SocialLogo className="px-2 py-2" />

                <div className="mt-8 px-2 text-[0.65rem] font-extrabold tracking-[0.16em] text-muted-foreground uppercase">
                    Explore
                </div>
                <nav className="mt-2 space-y-1" aria-label="Primary navigation">
                    {navItems.map((item) => {
                        const active = isCurrentOrParentUrl(item.href);
                        const Icon = item.icon;
                        const unreadCount =
                            item.href === '/notifications'
                                ? notificationSummary.unreadCount
                                : 0;

                        return (
                            <Link
                                key={item.href}
                                href={item.href}
                                prefetch
                                aria-current={active ? 'page' : undefined}
                                className={cn(
                                    'social-focus group relative flex min-h-14 items-center gap-3 rounded-2xl px-2.5 transition-[color,background-color,transform] active:translate-y-px',
                                    active
                                        ? 'bg-primary/[0.09] text-primary'
                                        : 'text-muted-foreground hover:bg-card hover:text-foreground',
                                )}
                            >
                                <span
                                    className={cn(
                                        'flex size-9 shrink-0 items-center justify-center rounded-xl transition-[background-color,color,transform] group-hover:scale-[1.03]',
                                        active
                                            ? 'bg-primary text-primary-foreground shadow-[0_10px_22px_-14px_color-mix(in_oklab,var(--primary)_80%,transparent)]'
                                            : 'bg-card text-foreground ring-1 ring-border/65',
                                    )}
                                >
                                    <Icon
                                        className="size-4.5"
                                        strokeWidth={2.15}
                                    />
                                </span>
                                <span className="min-w-0 flex-1">
                                    <span className="block text-sm font-extrabold">
                                        {item.title}
                                    </span>
                                    <span className="mt-0.5 block text-[0.68rem] font-medium text-muted-foreground">
                                        {item.subtitle}
                                    </span>
                                </span>
                                {unreadCount > 0 && (
                                    <span className="flex min-w-6 items-center justify-center rounded-full bg-coral px-1.5 py-1 text-[0.62rem] font-black text-white tabular-nums">
                                        {unreadCount > 99 ? '99+' : unreadCount}
                                    </span>
                                )}
                                {active && (
                                    <span className="absolute top-1/2 -right-3 h-7 w-1 -translate-y-1/2 rounded-l-full bg-primary" />
                                )}
                            </Link>
                        );
                    })}
                </nav>

                <Button
                    asChild
                    size="lg"
                    className="mt-6 min-h-12 w-full rounded-2xl shadow-[0_16px_28px_-18px_color-mix(in_oklab,var(--primary)_85%,transparent)]"
                >
                    <Link href="/feed#compose">
                        <Feather className="size-4.5" aria-hidden="true" />
                        Share an update
                    </Link>
                </Button>

                <div className="mt-auto pt-5">
                    <div className="mb-3 rounded-2xl bg-foreground px-4 py-4 text-background">
                        <span className="inline-flex items-center gap-2 text-[0.65rem] font-extrabold tracking-[0.13em] text-mint uppercase">
                            <span className="size-1.5 rounded-full bg-mint" />
                            Community-owned
                        </span>
                        <p className="mt-2 text-sm leading-5 text-background/68">
                            Your members, conversations, and rules stay under
                            your control.
                        </p>
                    </div>
                    {auth.user && <UserButton user={auth.user} />}
                </div>
            </div>
        </aside>
    );
}

export function MobileSocialHeader() {
    const { auth, notificationSummary } = usePage().props;

    return (
        <header className="sticky top-0 z-40 flex h-[4.25rem] items-center justify-between border-b border-border/65 bg-card/88 px-4 backdrop-blur-xl lg:hidden">
            <SocialLogo compact />
            <span className="absolute left-1/2 hidden -translate-x-1/2 text-sm font-black tracking-[-0.025em] sm:block">
                Lineweb Social
            </span>
            {auth.user ? (
                <div className="flex items-center gap-1.5">
                    <Link
                        href="/search"
                        aria-label="Search"
                        className="social-focus flex size-11 items-center justify-center rounded-full bg-secondary text-primary transition-colors hover:bg-primary/10"
                    >
                        <Search className="size-5" aria-hidden="true" />
                    </Link>
                    <Link
                        href="/saved"
                        aria-label="Saved posts"
                        className="social-focus flex size-11 items-center justify-center rounded-full text-foreground transition-colors hover:bg-secondary"
                    >
                        <Bookmark className="size-5" aria-hidden="true" />
                    </Link>
                    <Link
                        href="/notifications"
                        aria-label={`Notifications${notificationSummary.unreadCount > 0 ? `, ${notificationSummary.unreadCount} unread` : ''}`}
                        className="social-focus relative flex size-11 items-center justify-center rounded-full text-foreground transition-colors hover:bg-secondary"
                    >
                        <Bell className="size-5" aria-hidden="true" />
                        {notificationSummary.unreadCount > 0 && (
                            <span className="absolute top-1.5 right-1.5 size-2.5 rounded-full bg-coral ring-2 ring-card" />
                        )}
                    </Link>
                    <UserButton user={auth.user} compact />
                </div>
            ) : (
                <Link
                    href="/login"
                    className="social-focus rounded-full px-3 py-2 text-sm font-extrabold"
                >
                    Log in
                </Link>
            )}
        </header>
    );
}

export function MobileSocialTabs() {
    const { auth } = usePage().props;
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const mobileItems = [
        { title: 'Home', href: '/feed', icon: Home },
        { title: 'Spaces', href: '/spaces', icon: Compass },
        { title: 'Post', href: '/feed#compose', icon: Feather, primary: true },
        { title: 'People', href: '/people', icon: UsersRound },
        {
            title: 'Profile',
            href: auth.user ? `/people/${auth.user.handle}` : '/login',
            icon: UserRound,
        },
    ] as const;

    return (
        <nav
            className="fixed inset-x-3 bottom-3 z-50 rounded-[1.45rem] border border-border/75 bg-card/94 px-2 pt-2 pb-[max(.5rem,env(safe-area-inset-bottom))] shadow-[0_18px_55px_-22px_rgba(15,23,42,.48)] backdrop-blur-xl lg:hidden"
            aria-label="Mobile navigation"
        >
            <div className="mx-auto grid max-w-md grid-cols-5">
                {mobileItems.map((item) => {
                    const active = isCurrentOrParentUrl(
                        item.href.split('#')[0],
                    );
                    const primary = 'primary' in item && item.primary;
                    const Icon = item.icon;

                    return (
                        <Link
                            key={item.title}
                            href={item.href}
                            aria-current={
                                active && !primary ? 'page' : undefined
                            }
                            className="social-focus group flex min-h-12 flex-col items-center justify-center gap-1 rounded-xl text-[0.62rem] font-extrabold text-muted-foreground"
                        >
                            <span
                                className={cn(
                                    'flex size-8 items-center justify-center rounded-xl transition-[transform,background-color,color] active:scale-90',
                                    primary
                                        ? '-mt-5 size-11 rounded-2xl bg-primary text-primary-foreground shadow-[0_12px_24px_-12px_color-mix(in_oklab,var(--primary)_90%,transparent)]'
                                        : active
                                          ? 'bg-foreground text-background'
                                          : 'group-hover:bg-secondary',
                                )}
                            >
                                <Icon
                                    className={primary ? 'size-5' : 'size-4.5'}
                                    strokeWidth={2.2}
                                />
                            </span>
                            {item.title}
                        </Link>
                    );
                })}
            </div>
        </nav>
    );
}

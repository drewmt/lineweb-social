import { Head, Link, router } from '@inertiajs/react';
import {
    BellOff,
    BellRing,
    Check,
    ChevronLeft,
    ChevronRight,
    Flag,
    MessageCircle,
    Settings2,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

type NotificationItem = {
    id: string;
    kind: 'comment_reply' | 'space_moderation' | 'unavailable';
    title: string;
    description: string;
    createdAt: string;
    readAt: string | null;
    available: boolean;
};

type NotificationsProps = {
    items: NotificationItem[];
    meta: {
        currentPage: number;
        lastPage: number;
        perPage: number;
        total: number;
    };
    links: {
        previous: string | null;
        next: string | null;
    };
    filter: 'all' | 'unread';
    notificationSummary: { unreadCount: number };
    status?: string;
};

const timestamp = (value: string) =>
    new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));

export default function Notifications({
    items,
    meta,
    links,
    filter,
    notificationSummary,
    status,
}: NotificationsProps) {
    const unreadCount = notificationSummary.unreadCount;

    return (
        <>
            <Head title="Notifications" />
            <main className="social-page max-w-5xl">
                <header className="social-page-heading">
                    <div className="flex flex-wrap items-end justify-between gap-4">
                        <div>
                            <p className="social-eyebrow">
                                <BellRing className="size-3.5" />
                                Your inbox
                            </p>
                            <h1 className="mt-2 text-3xl font-black tracking-[-0.04em] sm:text-4xl">
                                Notifications
                            </h1>
                            <p className="mt-2 max-w-2xl text-sm leading-6 text-muted-foreground sm:text-base">
                                Replies and moderation work that deserve your
                                attention — no engagement ranking or noise.
                            </p>
                        </div>
                        <Button
                            type="button"
                            variant="outline"
                            disabled={unreadCount === 0}
                            onClick={() =>
                                router.patch('/notifications/read-all')
                            }
                            className="min-h-11 rounded-xl"
                        >
                            <Check className="size-4" aria-hidden="true" />
                            Mark all read
                        </Button>
                    </div>
                </header>

                {status && (
                    <div
                        role="status"
                        className="mt-5 rounded-2xl border border-primary/20 bg-primary/8 px-4 py-3 text-sm font-bold"
                    >
                        {status}
                    </div>
                )}

                <div className="mt-5 grid items-start gap-5 lg:grid-cols-[minmax(0,1fr)_17rem]">
                    <section
                        className="social-card min-w-0 overflow-hidden rounded-[1.5rem]"
                        aria-labelledby="notification-list-title"
                    >
                        <div className="flex flex-wrap items-center justify-between gap-3 border-b px-4 py-4 sm:px-5">
                            <div>
                                <h2
                                    id="notification-list-title"
                                    className="font-black tracking-tight"
                                >
                                    Recent activity
                                </h2>
                                <p className="mt-0.5 text-xs font-semibold text-muted-foreground">
                                    {unreadCount.toLocaleString()} unread
                                </p>
                            </div>
                            <div className="flex rounded-xl bg-secondary/70 p-1">
                                <FilterLink
                                    href="/notifications"
                                    active={filter === 'all'}
                                >
                                    All
                                </FilterLink>
                                <FilterLink
                                    href="/notifications?filter=unread"
                                    active={filter === 'unread'}
                                >
                                    Unread
                                </FilterLink>
                            </div>
                        </div>

                        {items.length === 0 ? (
                            <div className="px-5 py-16 text-center">
                                <span className="mx-auto flex size-14 items-center justify-center rounded-2xl bg-secondary text-primary">
                                    <BellRing
                                        className="size-6"
                                        aria-hidden="true"
                                    />
                                </span>
                                <h3 className="mt-4 font-black">
                                    {filter === 'unread'
                                        ? 'You are all caught up.'
                                        : 'Nothing here yet.'}
                                </h3>
                                <p className="mx-auto mt-1 max-w-sm text-sm leading-6 text-muted-foreground">
                                    Useful replies and moderation alerts will
                                    appear here when they happen.
                                </p>
                            </div>
                        ) : (
                            <ul className="divide-y divide-border/75">
                                {items.map((item) => (
                                    <NotificationRow
                                        key={item.id}
                                        item={item}
                                    />
                                ))}
                            </ul>
                        )}

                        {meta.lastPage > 1 && (
                            <nav
                                className="flex items-center justify-between gap-3 border-t px-4 py-4 sm:px-5"
                                aria-label="Notification pages"
                            >
                                {links.previous ? (
                                    <PageLink href={links.previous}>
                                        <ChevronLeft className="size-4" />
                                        Newer
                                    </PageLink>
                                ) : (
                                    <span />
                                )}
                                <span className="text-xs font-bold text-muted-foreground">
                                    Page {meta.currentPage} of {meta.lastPage}
                                </span>
                                {links.next ? (
                                    <PageLink href={links.next}>
                                        Older
                                        <ChevronRight className="size-4" />
                                    </PageLink>
                                ) : (
                                    <span />
                                )}
                            </nav>
                        )}
                    </section>

                    <aside className="space-y-4 lg:sticky lg:top-5">
                        <div className="social-card rounded-[1.35rem] p-5">
                            <p className="text-xs font-extrabold tracking-[0.12em] text-primary uppercase">
                                Low-noise by design
                            </p>
                            <p className="mt-3 text-sm leading-6 text-muted-foreground">
                                This inbox contains direct replies and
                                moderation work. Likes, popularity scores, and
                                algorithmic nudges are intentionally absent.
                            </p>
                        </div>
                        <Button
                            asChild
                            variant="outline"
                            className="min-h-11 w-full justify-start rounded-xl"
                        >
                            <Link href="/settings/notifications">
                                <Settings2
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Notification settings
                            </Link>
                        </Button>
                    </aside>
                </div>
            </main>
        </>
    );
}

function FilterLink({
    href,
    active,
    children,
}: {
    href: string;
    active: boolean;
    children: string;
}) {
    return (
        <Link
            href={href}
            preserveState
            className={cn(
                'social-focus flex min-h-9 items-center rounded-lg px-3 text-xs font-extrabold transition-colors',
                active
                    ? 'bg-card text-foreground shadow-sm'
                    : 'text-muted-foreground hover:text-foreground',
            )}
        >
            {children}
        </Link>
    );
}

function NotificationRow({ item }: { item: NotificationItem }) {
    const unread = item.readAt === null;
    const Icon =
        item.kind === 'comment_reply'
            ? MessageCircle
            : item.kind === 'space_moderation'
              ? Flag
              : BellOff;

    return (
        <li
            className={cn(
                'relative px-4 py-4 transition-colors sm:px-5',
                unread && 'bg-primary/[0.035]',
            )}
        >
            {unread && (
                <span
                    className="absolute top-5 left-1.5 size-2 rounded-full bg-coral"
                    aria-label="Unread"
                />
            )}
            <div className="flex items-start gap-3.5">
                <span
                    className={cn(
                        'flex size-11 shrink-0 items-center justify-center rounded-2xl',
                        item.kind === 'comment_reply'
                            ? 'bg-primary/10 text-primary'
                            : item.kind === 'space_moderation'
                              ? 'bg-coral/12 text-coral'
                              : 'bg-secondary text-muted-foreground',
                    )}
                >
                    <Icon className="size-5" aria-hidden="true" />
                </span>
                <div className="min-w-0 flex-1">
                    <p
                        className={cn(
                            'text-sm tracking-tight',
                            unread ? 'font-black' : 'font-bold',
                        )}
                    >
                        {item.title}
                    </p>
                    <p className="mt-1 text-sm leading-6 text-muted-foreground">
                        {item.description}
                    </p>
                    <time
                        dateTime={item.createdAt}
                        className="mt-2 block text-xs font-semibold text-muted-foreground"
                    >
                        {timestamp(item.createdAt)}
                    </time>
                    <div className="mt-3 flex flex-wrap gap-2">
                        {item.available && (
                            <Button
                                type="button"
                                size="sm"
                                className="min-h-10 rounded-xl"
                                onClick={() =>
                                    router.post(
                                        `/notifications/${item.id}/open`,
                                    )
                                }
                            >
                                Open
                            </Button>
                        )}
                        {unread && (
                            <Button
                                type="button"
                                size="sm"
                                variant="ghost"
                                className="min-h-10 rounded-xl"
                                onClick={() =>
                                    router.patch(
                                        `/notifications/${item.id}/read`,
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                Mark as read
                            </Button>
                        )}
                    </div>
                </div>
            </div>
        </li>
    );
}

function PageLink({ href, children }: { href: string; children: ReactNode }) {
    return (
        <Link
            href={href}
            className="social-focus inline-flex min-h-10 items-center gap-1.5 rounded-xl border px-3 text-xs font-extrabold transition-colors hover:bg-secondary"
        >
            {children}
        </Link>
    );
}

Notifications.layout = {
    breadcrumbs: [{ title: 'Notifications', href: '/notifications' }],
};

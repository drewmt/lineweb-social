import { Head, Link, router } from '@inertiajs/react';
import {
    ChevronLeft,
    ChevronRight,
    CircleCheck,
    LockKeyhole,
    Search,
    ScrollText,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

type Category = 'all' | 'accounts' | 'safety' | 'access';

type AuditLog = {
    id: number;
    action: string;
    reason: string | null;
    actorName: string;
    subjectName: string;
    subjectHandle: string | null;
    createdAt: string;
};

type AuditPage = {
    data: AuditLog[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
    total: number;
};

type Props = {
    query: string;
    category: Category;
    counts: Record<Category, number>;
    logs: AuditPage;
};

const categories: Array<{ value: Category; label: string }> = [
    { value: 'all', label: 'All activity' },
    { value: 'accounts', label: 'Account access' },
    { value: 'safety', label: 'Safety decisions' },
    { value: 'access', label: 'Admin access' },
];

const auditLabels: Record<string, string> = {
    'member.suspended': 'Member suspended',
    'member.reinstated': 'Member reinstated',
    'administrator.granted': 'Administrator granted',
    'administrator.revoked': 'Administrator revoked',
    'direct_message_report.reviewing': 'Message report review started',
    'direct_message_report.resolved': 'Message report resolved',
    'direct_message_report.dismissed': 'Message report dismissed',
    'direct_message_report.reopened': 'Message report reopened',
};

const dateLabel = (value: string) =>
    new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));

const categoryHref = (query: string, category: Category) => {
    const params = new URLSearchParams();

    if (query) {
        params.set('q', query);
    }

    if (category !== 'all') {
        params.set('category', category);
    }

    return `/admin/audit${params.size > 0 ? `?${params.toString()}` : ''}`;
};

export default function AdminAudit({ query, category, counts, logs }: Props) {
    const [search, setSearch] = useState(query);

    const submitSearch = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        router.get(
            '/admin/audit',
            {
                ...(search.trim() ? { q: search.trim() } : {}),
                ...(category !== 'all' ? { category } : {}),
            },
            {
                preserveState: true,
                replace: true,
            },
        );
    };

    return (
        <>
            <Head title="Platform audit trail" />
            <div className="relative z-[1] mx-auto w-full max-w-[82rem] px-3 py-4 sm:px-6 sm:py-7 xl:px-8">
                <header className="max-w-3xl">
                    <p className="social-eyebrow">
                        <ScrollText className="size-3.5" aria-hidden="true" />
                        Accountability
                    </p>
                    <h1 className="mt-2 text-3xl font-black tracking-[-0.045em] sm:text-4xl">
                        Privileged audit trail
                    </h1>
                    <p className="mt-2 max-w-2xl text-sm leading-6 text-muted-foreground sm:text-base">
                        A read-only record of administrator access changes,
                        account restrictions, and private safety decisions.
                    </p>
                </header>

                <div className="mt-5 flex items-start gap-3 rounded-2xl border border-primary/15 bg-primary/[0.05] px-4 py-3 text-xs leading-5 text-muted-foreground sm:px-5">
                    <LockKeyhole
                        className="mt-0.5 size-4 shrink-0 text-primary"
                        aria-hidden="true"
                    />
                    <p>
                        Audit records are append-only. This interface provides
                        no edit, delete, or export action and never exposes
                        credentials or surrounding private conversations.
                    </p>
                </div>

                <nav
                    aria-label="Audit categories"
                    className="-mx-3 mt-5 flex gap-2 overflow-x-auto px-3 pb-1 sm:mx-0 sm:px-0"
                >
                    {categories.map((item) => (
                        <Link
                            key={item.value}
                            href={categoryHref(query, item.value)}
                            preserveState
                            className={cn(
                                'social-focus flex min-h-12 shrink-0 items-center gap-3 rounded-2xl border px-4 text-sm font-extrabold transition-colors',
                                category === item.value
                                    ? 'border-foreground bg-foreground text-background'
                                    : 'border-border/75 bg-card text-muted-foreground hover:border-primary/20 hover:bg-secondary/55 hover:text-foreground',
                            )}
                        >
                            {item.label}
                            <span
                                className={cn(
                                    'rounded-full px-2 py-0.5 text-xs tabular-nums',
                                    category === item.value
                                        ? 'bg-background/12 text-background'
                                        : 'bg-secondary text-foreground',
                                )}
                            >
                                {counts[item.value].toLocaleString()}
                            </span>
                        </Link>
                    ))}
                </nav>

                <section className="social-card mt-4 overflow-hidden rounded-[1.5rem]">
                    <div className="flex flex-col gap-4 border-b p-4 sm:p-5 lg:flex-row lg:items-end lg:justify-between">
                        <div>
                            <h2 className="text-lg font-black tracking-tight">
                                {logs.total.toLocaleString()}{' '}
                                {logs.total === 1 ? 'record' : 'records'}
                            </h2>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Newest decisions appear first.
                            </p>
                        </div>
                        <form
                            onSubmit={submitSearch}
                            className="flex w-full gap-2 lg:w-auto"
                        >
                            <label className="relative min-w-0 flex-1 lg:w-80">
                                <span className="sr-only">
                                    Search audit records
                                </span>
                                <Search
                                    className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                    aria-hidden="true"
                                />
                                <input
                                    type="search"
                                    value={search}
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                    maxLength={100}
                                    placeholder="Member, operator, or reason"
                                    className="social-inset social-focus h-11 w-full pl-10 text-sm"
                                />
                            </label>
                            <Button
                                type="submit"
                                variant="outline"
                                className="h-11 rounded-xl"
                            >
                                Search
                            </Button>
                        </form>
                    </div>

                    {logs.data.length === 0 ? (
                        <div className="px-5 py-16 text-center">
                            <CircleCheck className="mx-auto size-7 text-muted-foreground" />
                            <h3 className="mt-3 font-black">
                                No matching audit records
                            </h3>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Try another term or category.
                            </p>
                            {(query || category !== 'all') && (
                                <Button
                                    asChild
                                    variant="outline"
                                    className="mt-5"
                                >
                                    <Link href="/admin/audit">
                                        Clear filters
                                    </Link>
                                </Button>
                            )}
                        </div>
                    ) : (
                        <ol className="divide-y divide-border/75">
                            {logs.data.map((log) => (
                                <li
                                    key={log.id}
                                    className="grid gap-3 p-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:p-5"
                                >
                                    <div className="min-w-0">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span className="rounded-lg bg-secondary px-2.5 py-1 text-[0.66rem] font-extrabold tracking-[0.07em] text-muted-foreground uppercase">
                                                {categoryFor(log.action)}
                                            </span>
                                            <h3 className="text-sm font-black">
                                                {auditLabels[log.action] ??
                                                    log.action}
                                            </h3>
                                        </div>
                                        <p className="mt-2 text-xs leading-5 text-muted-foreground">
                                            <strong className="text-foreground">
                                                {log.actorName}
                                            </strong>{' '}
                                            acted on{' '}
                                            <strong className="text-foreground">
                                                {log.subjectName}
                                            </strong>
                                            {log.subjectHandle
                                                ? ` (@${log.subjectHandle})`
                                                : ''}
                                        </p>
                                        {log.reason && (
                                            <p className="mt-2 max-w-3xl text-sm leading-6">
                                                {log.reason}
                                            </p>
                                        )}
                                    </div>
                                    <time
                                        dateTime={log.createdAt}
                                        className="text-xs font-semibold whitespace-nowrap text-muted-foreground sm:pt-1"
                                    >
                                        {dateLabel(log.createdAt)}
                                    </time>
                                </li>
                            ))}
                        </ol>
                    )}

                    {logs.last_page > 1 && (
                        <nav
                            aria-label="Audit pages"
                            className="flex items-center justify-between gap-3 border-t px-4 py-4 sm:px-5"
                        >
                            {logs.prev_page_url ? (
                                <Button asChild variant="outline" size="sm">
                                    <Link href={logs.prev_page_url}>
                                        <ChevronLeft className="size-4" />
                                        Previous
                                    </Link>
                                </Button>
                            ) : (
                                <span />
                            )}
                            <span className="text-xs font-bold text-muted-foreground">
                                Page {logs.current_page} of {logs.last_page}
                            </span>
                            {logs.next_page_url ? (
                                <Button asChild variant="outline" size="sm">
                                    <Link href={logs.next_page_url}>
                                        Next
                                        <ChevronRight className="size-4" />
                                    </Link>
                                </Button>
                            ) : (
                                <span />
                            )}
                        </nav>
                    )}
                </section>
            </div>
        </>
    );
}

function categoryFor(action: string) {
    if (action.startsWith('direct_message_report.')) {
        return 'Safety';
    }

    if (action.startsWith('administrator.')) {
        return 'Admin access';
    }

    return 'Account access';
}

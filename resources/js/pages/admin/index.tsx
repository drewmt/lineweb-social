import { Head, Link, router } from '@inertiajs/react';
import {
    Building2,
    ChevronLeft,
    ChevronRight,
    CircleCheck,
    FileText,
    Flag,
    MessageSquareWarning,
    Search,
    ShieldCheck,
    SquareTerminal,
    Users,
    UserX,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { AccountActionDialog } from '@/components/admin/account-action-dialog';
import { MemberRow } from '@/components/admin/member-row';
import type { Member } from '@/components/admin/member-row';
import { Button } from '@/components/ui/button';

type Metrics = {
    membersTotal: number;
    membersVerified: number;
    membersSuspended: number;
    spacesTotal: number;
    postsTotal: number;
    commentsTotal: number;
    reportsActive: number;
    messageReportsActive: number;
};

type MembersPage = {
    data: Member[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
    total: number;
};

type AuditLog = {
    id: number;
    action: string;
    reason: string | null;
    actorName: string;
    subjectName: string;
    subjectHandle: string | null;
    createdAt: string;
};

type Props = {
    query: string;
    metrics: Metrics;
    members: MembersPage;
    auditLogs: AuditLog[];
    status?: string;
};

const formatDate = (value: string | null) =>
    value
        ? new Intl.DateTimeFormat(undefined, {
              dateStyle: 'medium',
              timeStyle: 'short',
          }).format(new Date(value))
        : '—';

const metricCards = (metrics: Metrics) => [
    {
        label: 'Members',
        value: metrics.membersTotal,
        detail: `${metrics.membersVerified.toLocaleString()} verified`,
        icon: Users,
    },
    {
        label: 'Suspended',
        value: metrics.membersSuspended,
        detail: 'Restricted accounts',
        icon: UserX,
    },
    {
        label: 'Spaces',
        value: metrics.spacesTotal,
        detail: 'Member communities',
        icon: Building2,
    },
    {
        label: 'Content',
        value: metrics.postsTotal + metrics.commentsTotal,
        detail: `${metrics.postsTotal.toLocaleString()} posts · ${metrics.commentsTotal.toLocaleString()} comments`,
        icon: FileText,
    },
    {
        label: 'Open reports',
        value: metrics.reportsActive,
        detail: 'Awaiting a decision',
        icon: Flag,
    },
    {
        label: 'Message safety',
        value: metrics.messageReportsActive,
        detail: 'Private evidence queue',
        icon: MessageSquareWarning,
        href: '/admin/message-reports',
    },
];

export default function AdminIndex({
    query,
    metrics,
    members,
    auditLogs,
    status,
}: Props) {
    const [search, setSearch] = useState(query);
    const [selectedMember, setSelectedMember] = useState<Member | null>(null);
    const [action, setAction] = useState<'suspend' | 'reinstate'>('suspend');

    const submitSearch = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        router.get('/admin', search.trim() ? { q: search.trim() } : {}, {
            preserveState: true,
            replace: true,
        });
    };

    const openAction = (
        member: Member,
        nextAction: 'suspend' | 'reinstate',
    ) => {
        setSelectedMember(member);
        setAction(nextAction);
    };

    return (
        <>
            <Head title="Platform administration" />
            <main className="social-page max-w-7xl">
                <header className="social-page-heading">
                    <div className="flex flex-wrap items-end justify-between gap-5">
                        <div>
                            <p className="social-eyebrow">
                                <ShieldCheck className="size-3.5" />
                                Private operator surface
                            </p>
                            <h1 className="mt-2 text-3xl font-black tracking-[-0.04em] sm:text-4xl">
                                Platform administration
                            </h1>
                            <p className="mt-2 max-w-2xl text-sm leading-6 text-muted-foreground sm:text-base">
                                Monitor the community, manage account access,
                                and keep every sensitive action accountable.
                            </p>
                        </div>
                        <div className="inline-flex items-center gap-2 rounded-full border bg-card px-3 py-2 text-xs font-extrabold text-muted-foreground">
                            <CircleCheck className="size-4 text-emerald-600" />
                            Audit trail active
                        </div>
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

                <section
                    className="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-6"
                    aria-label="Platform metrics"
                >
                    {metricCards(metrics).map((metric) => {
                        const Icon = metric.icon;

                        const content = (
                            <>
                                <div className="flex items-start justify-between gap-3">
                                    <div>
                                        <p className="text-xs font-extrabold tracking-[0.11em] text-muted-foreground uppercase">
                                            {metric.label}
                                        </p>
                                        <p className="mt-2 text-2xl font-black tracking-[-0.04em] tabular-nums">
                                            {metric.value.toLocaleString()}
                                        </p>
                                    </div>
                                    <span className="flex size-10 items-center justify-center rounded-xl bg-primary/9 text-primary">
                                        <Icon
                                            className="size-4.5"
                                            aria-hidden="true"
                                        />
                                    </span>
                                </div>
                                <p className="mt-3 text-xs font-semibold text-muted-foreground">
                                    {metric.detail}
                                </p>
                            </>
                        );

                        return 'href' in metric ? (
                            <Link
                                key={metric.label}
                                href={metric.href}
                                className="social-focus social-card rounded-[1.35rem] p-4 transition-colors hover:border-primary/25 hover:bg-primary/[0.04]"
                            >
                                {content}
                            </Link>
                        ) : (
                            <article
                                key={metric.label}
                                className="social-card rounded-[1.35rem] p-4"
                            >
                                {content}
                            </article>
                        );
                    })}
                </section>

                <div className="mt-5 grid items-start gap-5 xl:grid-cols-[minmax(0,1fr)_22rem]">
                    <section className="social-card min-w-0 overflow-hidden rounded-[1.5rem]">
                        <div className="border-b p-4 sm:p-5">
                            <div className="flex flex-wrap items-end justify-between gap-4">
                                <div>
                                    <h2 className="text-lg font-black tracking-tight">
                                        Members
                                    </h2>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        {members.total.toLocaleString()}{' '}
                                        accounts
                                    </p>
                                </div>
                                <form
                                    onSubmit={submitSearch}
                                    className="flex w-full gap-2 sm:w-auto"
                                >
                                    <label className="relative min-w-0 flex-1 sm:w-72">
                                        <span className="sr-only">
                                            Search members
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
                                            placeholder="Name, handle, or email"
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
                        </div>

                        {members.data.length === 0 ? (
                            <div className="px-5 py-16 text-center">
                                <Search className="mx-auto size-7 text-muted-foreground" />
                                <h3 className="mt-3 font-black">
                                    No matching members
                                </h3>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Try a different name, handle, or email.
                                </p>
                            </div>
                        ) : (
                            <ul className="divide-y divide-border/75">
                                {members.data.map((member) => (
                                    <MemberRow
                                        key={member.id}
                                        member={member}
                                        onAction={openAction}
                                    />
                                ))}
                            </ul>
                        )}

                        {members.last_page > 1 && (
                            <nav
                                aria-label="Member pages"
                                className="flex items-center justify-between gap-3 border-t px-4 py-4 sm:px-5"
                            >
                                {members.prev_page_url ? (
                                    <Button asChild variant="outline" size="sm">
                                        <Link href={members.prev_page_url}>
                                            <ChevronLeft className="size-4" />
                                            Previous
                                        </Link>
                                    </Button>
                                ) : (
                                    <span />
                                )}
                                <span className="text-xs font-bold text-muted-foreground">
                                    Page {members.current_page} of{' '}
                                    {members.last_page}
                                </span>
                                {members.next_page_url ? (
                                    <Button asChild variant="outline" size="sm">
                                        <Link href={members.next_page_url}>
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

                    <aside className="space-y-4 xl:sticky xl:top-5">
                        <section className="social-card rounded-[1.5rem] p-5">
                            <div className="flex items-center gap-3">
                                <span className="flex size-10 items-center justify-center rounded-xl bg-foreground text-background">
                                    <SquareTerminal
                                        className="size-4.5"
                                        aria-hidden="true"
                                    />
                                </span>
                                <div>
                                    <h2 className="font-black tracking-tight">
                                        Administrator access
                                    </h2>
                                    <p className="text-xs text-muted-foreground">
                                        Console-only by design
                                    </p>
                                </div>
                            </div>
                            <p className="mt-4 text-sm leading-6 text-muted-foreground">
                                Grant or revoke administrator access from a
                                trusted server shell. The web interface cannot
                                promote accounts.
                            </p>
                            <code className="mt-3 block overflow-x-auto rounded-xl bg-foreground px-3 py-3 text-[0.7rem] font-semibold text-background">
                                php artisan platform:administrator email
                            </code>
                        </section>

                        <section className="social-card overflow-hidden rounded-[1.5rem]">
                            <div className="border-b px-5 py-4">
                                <h2 className="font-black tracking-tight">
                                    Recent audit trail
                                </h2>
                                <p className="mt-1 text-xs text-muted-foreground">
                                    Latest privileged account actions
                                </p>
                            </div>
                            {auditLogs.length === 0 ? (
                                <p className="px-5 py-8 text-sm leading-6 text-muted-foreground">
                                    No privileged actions have been recorded
                                    yet.
                                </p>
                            ) : (
                                <ol className="divide-y divide-border/75">
                                    {auditLogs.map((log) => (
                                        <li key={log.id} className="px-5 py-4">
                                            <p className="text-sm font-extrabold">
                                                {auditLabel(log.action)}
                                            </p>
                                            <p className="mt-1 text-xs leading-5 text-muted-foreground">
                                                {log.actorName} ·{' '}
                                                {log.subjectName}
                                                {log.subjectHandle
                                                    ? ` (@${log.subjectHandle})`
                                                    : ''}
                                            </p>
                                            {log.reason && (
                                                <p className="mt-2 text-xs leading-5">
                                                    {log.reason}
                                                </p>
                                            )}
                                            <time
                                                dateTime={log.createdAt}
                                                className="mt-2 block text-[0.68rem] font-semibold text-muted-foreground"
                                            >
                                                {formatDate(log.createdAt)}
                                            </time>
                                        </li>
                                    ))}
                                </ol>
                            )}
                        </section>
                    </aside>
                </div>
            </main>

            <AccountActionDialog
                member={selectedMember}
                action={action}
                query={query}
                open={selectedMember !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setSelectedMember(null);
                    }
                }}
            />
        </>
    );
}

function auditLabel(action: string) {
    const labels: Record<string, string> = {
        'member.suspended': 'Member suspended',
        'member.reinstated': 'Member reinstated',
        'administrator.granted': 'Administrator granted',
        'administrator.revoked': 'Administrator revoked',
        'direct_message_report.reviewing': 'Message report review started',
        'direct_message_report.resolved': 'Message report resolved',
        'direct_message_report.dismissed': 'Message report dismissed',
        'direct_message_report.reopened': 'Message report reopened',
    };

    return labels[action] ?? action;
}

import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    Eye,
    MessageSquareWarning,
    RotateCcw,
    ShieldCheck,
    UserRoundSearch,
} from 'lucide-react';
import { useState } from 'react';
import { AvatarMark } from '@/components/social/avatar-mark';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

type ReportStatus = 'open' | 'reviewing' | 'resolved' | 'dismissed';
type ReportAction = 'review' | 'resolve' | 'dismiss' | 'reopen';
type Filter = 'active' | 'resolved' | 'dismissed' | 'all';

type Person = {
    name: string;
    handle: string;
};

type MessageReport = {
    id: number;
    actionUrl: string;
    reason: string;
    reasonLabel: string;
    details: string | null;
    status: ReportStatus;
    statusLabel: string;
    reporter: Person | null;
    reportedMember: Person | null;
    reviewerName: string | null;
    reviewerNote: string | null;
    createdAt: string;
    reviewedAt: string | null;
    message: {
        body: string;
        sentAt: string | null;
        sourceAvailable: boolean;
    };
};

type ReportsPage = {
    data: MessageReport[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
    total: number;
};

type Props = {
    filter: Filter;
    counts: Record<Filter, number>;
    reports: ReportsPage;
    status?: string;
};

const dateLabel = (value: string | null) =>
    value
        ? new Intl.DateTimeFormat(undefined, {
              dateStyle: 'medium',
              timeStyle: 'short',
          }).format(new Date(value))
        : 'Unknown time';

const statusTone: Record<ReportStatus, string> = {
    open: 'bg-coral/15 text-foreground',
    reviewing: 'bg-primary/10 text-primary',
    resolved: 'bg-mint/30 text-foreground',
    dismissed: 'bg-secondary text-muted-foreground',
};

const filters: Array<{ value: Filter; label: string }> = [
    { value: 'active', label: 'Active' },
    { value: 'resolved', label: 'Resolved' },
    { value: 'dismissed', label: 'Dismissed' },
    { value: 'all', label: 'All' },
];

export default function MessageReports({
    filter,
    counts,
    reports,
    status,
}: Props) {
    return (
        <>
            <Head title="Message safety reports" />
            <main className="social-page max-w-6xl">
                <header className="social-page-heading overflow-hidden">
                    <div className="flex flex-wrap items-end justify-between gap-5">
                        <div>
                            <Link
                                href="/admin"
                                className="social-focus inline-flex min-h-10 items-center gap-2 rounded-lg text-sm font-extrabold text-primary hover:underline"
                            >
                                <ArrowLeft
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Platform administration
                            </Link>
                            <p className="social-eyebrow mt-4">
                                <MessageSquareWarning className="size-3.5" />
                                Private trust &amp; safety
                            </p>
                            <h1 className="mt-2 text-3xl font-black tracking-[-0.045em] sm:text-4xl">
                                Message reports
                            </h1>
                            <p className="mt-2 max-w-2xl text-sm leading-6 text-muted-foreground sm:text-base">
                                Review only the submitted message evidence,
                                document the decision, and manage account access
                                separately.
                            </p>
                        </div>
                        <span className="inline-flex min-h-10 items-center gap-2 rounded-full border border-primary/20 bg-primary/[0.07] px-3.5 text-xs font-extrabold text-primary">
                            <ShieldCheck
                                className="size-4"
                                aria-hidden="true"
                            />
                            Evidence-limited view
                        </span>
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

                <nav
                    aria-label="Message report status"
                    className="mt-5 grid grid-cols-2 gap-2 sm:grid-cols-4"
                >
                    {filters.map((item) => (
                        <Link
                            key={item.value}
                            href={
                                item.value === 'active'
                                    ? '/admin/message-reports'
                                    : `/admin/message-reports?status=${item.value}`
                            }
                            preserveScroll
                            className={cn(
                                'social-focus social-card flex min-h-16 items-center justify-between rounded-2xl px-4 transition-colors hover:border-primary/25',
                                filter === item.value &&
                                    'border-primary/30 bg-primary/[0.07]',
                            )}
                        >
                            <span className="text-sm font-extrabold">
                                {item.label}
                            </span>
                            <span className="text-xl font-black tabular-nums">
                                {counts[item.value].toLocaleString()}
                            </span>
                        </Link>
                    ))}
                </nav>

                <div className="mt-5 flex items-start gap-3 rounded-2xl border border-border/75 bg-card px-4 py-3 text-xs leading-5 text-muted-foreground sm:px-5">
                    <ShieldCheck
                        className="mt-0.5 size-4 shrink-0 text-primary"
                        aria-hidden="true"
                    />
                    <p>
                        This queue contains the exact reported message and the
                        reporter context only. It does not grant access to the
                        surrounding private conversation. Closed evidence is
                        pruned after 180 days.
                    </p>
                </div>

                {reports.data.length === 0 ? (
                    <section className="social-card mt-5 rounded-[1.5rem] px-6 py-16 text-center">
                        <span className="mx-auto flex size-14 items-center justify-center rounded-2xl bg-mint/30">
                            <CheckCircle2
                                className="size-6"
                                aria-hidden="true"
                            />
                        </span>
                        <h2 className="mt-4 text-lg font-black">
                            No {filter === 'all' ? '' : filter} reports here
                        </h2>
                        <p className="mx-auto mt-2 max-w-md text-sm leading-6 text-muted-foreground">
                            New member reports appear here without becoming
                            visible to the sender or other community members.
                        </p>
                    </section>
                ) : (
                    <section
                        className="mt-5 space-y-4"
                        aria-label="Direct message reports"
                    >
                        {reports.data.map((report) => (
                            <MessageReportCard
                                key={report.id}
                                report={report}
                            />
                        ))}
                    </section>
                )}

                {reports.last_page > 1 && (
                    <nav
                        aria-label="Message report pages"
                        className="mt-5 flex items-center justify-between gap-3"
                    >
                        {reports.prev_page_url ? (
                            <Button asChild variant="outline">
                                <Link href={reports.prev_page_url}>
                                    <ChevronLeft className="size-4" />
                                    Previous
                                </Link>
                            </Button>
                        ) : (
                            <span />
                        )}
                        <span className="text-xs font-bold text-muted-foreground">
                            Page {reports.current_page} of {reports.last_page}
                        </span>
                        {reports.next_page_url ? (
                            <Button asChild variant="outline">
                                <Link href={reports.next_page_url}>
                                    Next
                                    <ChevronRight className="size-4" />
                                </Link>
                            </Button>
                        ) : (
                            <span />
                        )}
                    </nav>
                )}
            </main>
        </>
    );
}

function MessageReportCard({ report }: { report: MessageReport }) {
    const [note, setNote] = useState('');
    const [processing, setProcessing] = useState<ReportAction | null>(null);
    const [error, setError] = useState<string | null>(null);
    const isActive = report.status === 'open' || report.status === 'reviewing';

    const submit = (action: ReportAction) => {
        if (note.trim().length < 10) {
            setError('Add an administrator note of at least 10 characters.');

            return;
        }

        setError(null);
        setProcessing(action);
        router.patch(
            report.actionUrl,
            { action, note: note.trim() },
            {
                preserveScroll: true,
                onError: (errors) =>
                    setError(
                        String(
                            errors.note ??
                                errors.action ??
                                'The report could not be updated.',
                        ),
                    ),
                onFinish: () => setProcessing(null),
            },
        );
    };

    return (
        <article className="social-card overflow-hidden rounded-[1.5rem]">
            <header className="flex flex-wrap items-center justify-between gap-3 border-b border-border/75 bg-secondary/25 px-4 py-3 sm:px-5">
                <div className="flex flex-wrap items-center gap-2">
                    <span
                        className={cn(
                            'inline-flex min-h-8 items-center rounded-lg px-2.5 text-xs font-extrabold',
                            statusTone[report.status],
                        )}
                    >
                        {report.statusLabel}
                    </span>
                    <span className="text-sm font-extrabold">
                        {report.reasonLabel}
                    </span>
                </div>
                <time className="text-xs font-semibold text-muted-foreground">
                    Reported {dateLabel(report.createdAt)}
                </time>
            </header>

            <div className="grid gap-5 p-4 sm:p-5 lg:grid-cols-[minmax(0,1fr)_18rem]">
                <div className="min-w-0">
                    <p className="text-[0.68rem] font-extrabold tracking-[0.13em] text-muted-foreground uppercase">
                        Submitted message evidence
                    </p>
                    <blockquote className="mt-3 rounded-[1.2rem] rounded-bl-md border border-border/75 bg-background/60 px-4 py-3.5 text-sm leading-6 whitespace-pre-wrap">
                        {report.message.body}
                    </blockquote>
                    <div className="mt-2 flex flex-wrap items-center justify-between gap-2 text-xs font-semibold text-muted-foreground">
                        <time>{dateLabel(report.message.sentAt)}</time>
                        {!report.message.sourceAvailable && (
                            <span>Original account or message unavailable</span>
                        )}
                    </div>

                    <div className="mt-5 rounded-2xl border border-border/75 bg-secondary/20 p-4">
                        <p className="text-[0.68rem] font-extrabold tracking-[0.13em] text-muted-foreground uppercase">
                            Reporter context
                        </p>
                        <p className="mt-2 text-sm leading-6">
                            {report.details ??
                                'No additional context was submitted.'}
                        </p>
                    </div>

                    {report.reviewerName && (
                        <div className="mt-4 rounded-2xl border border-primary/15 bg-primary/[0.05] p-4">
                            <p className="text-[0.68rem] font-extrabold tracking-[0.13em] text-primary uppercase">
                                Latest administrator record
                            </p>
                            <p className="mt-2 text-sm leading-6">
                                <strong>{report.reviewerName}</strong>
                                {report.reviewerNote
                                    ? ` — ${report.reviewerNote}`
                                    : ''}
                            </p>
                            {report.reviewedAt && (
                                <time className="mt-2 block text-xs font-semibold text-muted-foreground">
                                    {dateLabel(report.reviewedAt)}
                                </time>
                            )}
                        </div>
                    )}
                </div>

                <aside className="space-y-4">
                    <PersonPanel label="Reported by" person={report.reporter} />
                    <PersonPanel
                        label="Message sender"
                        person={report.reportedMember}
                        operatorLink
                    />
                </aside>
            </div>

            <div className="border-t border-border/75 bg-background/35 p-4 sm:p-5">
                <label className="block text-sm font-bold">
                    Administrator note
                    <textarea
                        value={note}
                        onChange={(event) => setNote(event.target.value)}
                        required
                        minLength={10}
                        maxLength={500}
                        rows={3}
                        placeholder="Record the evidence review and reason for this action."
                        className="social-inset social-focus mt-2 w-full resize-y px-4 py-3 text-sm leading-6"
                    />
                </label>
                <div className="mt-2 flex items-start justify-between gap-3">
                    <div>
                        {error && (
                            <p
                                role="alert"
                                className="text-sm font-bold text-destructive"
                            >
                                {error}
                            </p>
                        )}
                        {!error && (
                            <p className="text-xs leading-5 text-muted-foreground">
                                Resolving a report does not automatically
                                suspend or delete an account.
                            </p>
                        )}
                    </div>
                    <span className="shrink-0 text-xs font-semibold text-muted-foreground">
                        {note.length} / 500
                    </span>
                </div>

                <div className="mt-4 flex flex-wrap justify-end gap-2">
                    {report.status === 'open' && (
                        <Button
                            type="button"
                            variant="outline"
                            disabled={processing !== null}
                            onClick={() => submit('review')}
                        >
                            <Eye className="size-4" aria-hidden="true" />
                            Start review
                        </Button>
                    )}
                    {isActive ? (
                        <>
                            <Button
                                type="button"
                                variant="outline"
                                disabled={processing !== null}
                                onClick={() => submit('dismiss')}
                            >
                                <CheckCircle2
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Dismiss
                            </Button>
                            <Button
                                type="button"
                                disabled={processing !== null}
                                onClick={() => submit('resolve')}
                            >
                                <ShieldCheck
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Resolve report
                            </Button>
                        </>
                    ) : (
                        <Button
                            type="button"
                            variant="outline"
                            disabled={processing !== null}
                            onClick={() => submit('reopen')}
                        >
                            <RotateCcw className="size-4" aria-hidden="true" />
                            Reopen report
                        </Button>
                    )}
                </div>
            </div>
        </article>
    );
}

function PersonPanel({
    label,
    person,
    operatorLink = false,
}: {
    label: string;
    person: Person | null;
    operatorLink?: boolean;
}) {
    return (
        <section className="rounded-2xl border border-border/75 p-4">
            <p className="text-[0.68rem] font-extrabold tracking-[0.13em] text-muted-foreground uppercase">
                {label}
            </p>
            {person ? (
                <>
                    <div className="mt-3 flex items-center gap-3">
                        <AvatarMark name={person.name} className="size-10" />
                        <div className="min-w-0">
                            <Link
                                href={`/people/${person.handle}`}
                                className="block truncate text-sm font-black hover:underline"
                            >
                                {person.name}
                            </Link>
                            <p className="truncate text-xs text-muted-foreground">
                                @{person.handle}
                            </p>
                        </div>
                    </div>
                    {operatorLink && (
                        <Button
                            asChild
                            variant="outline"
                            size="sm"
                            className="mt-3 w-full"
                        >
                            <Link
                                href={`/admin?q=${encodeURIComponent(person.handle)}`}
                            >
                                <UserRoundSearch className="size-4" />
                                Review account
                            </Link>
                        </Button>
                    )}
                </>
            ) : (
                <p className="mt-3 text-sm font-bold text-muted-foreground">
                    Former member
                </p>
            )}
        </section>
    );
}

MessageReports.layout = {
    breadcrumbs: [
        { title: 'Administration', href: '/admin' },
        { title: 'Message reports', href: '/admin/message-reports' },
    ],
};

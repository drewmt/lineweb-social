import { Head, Link, router } from '@inertiajs/react';
import {
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    CircleX,
    Eye,
    FileQuestion,
    ShieldCheck,
    UserRoundSearch,
} from 'lucide-react';
import { useState } from 'react';
import { AvatarMark } from '@/components/social/avatar-mark';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

type AppealStatus = 'open' | 'reviewing' | 'approved' | 'denied';
type AppealAction = 'review' | 'approve' | 'deny';
type Filter = 'active' | 'approved' | 'denied' | 'all';

type Appeal = {
    id: number;
    actionUrl: string;
    status: AppealStatus;
    statusLabel: string;
    statement: string;
    decisionMessage: string | null;
    submittedAt: string;
    reviewedAt: string | null;
    reviewerName: string | null;
    member: {
        name: string;
        handle: string;
        email: string;
        restricted: boolean;
        restrictedAt: string | null;
        internalReason: string | null;
    };
};

type AppealsPage = {
    data: Appeal[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
    total: number;
};

type Props = {
    filter: Filter;
    counts: Record<Filter, number>;
    appeals: AppealsPage;
    status?: string;
};

const filters: Array<{ value: Filter; label: string }> = [
    { value: 'active', label: 'Active' },
    { value: 'approved', label: 'Approved' },
    { value: 'denied', label: 'Not approved' },
    { value: 'all', label: 'All' },
];

const statusTone: Record<AppealStatus, string> = {
    open: 'bg-coral/15 text-foreground',
    reviewing: 'bg-primary/10 text-primary',
    approved: 'bg-mint/35 text-foreground',
    denied: 'bg-secondary text-muted-foreground',
};

const dateLabel = (value: string | null) =>
    value
        ? new Intl.DateTimeFormat(undefined, {
              dateStyle: 'medium',
              timeStyle: 'short',
          }).format(new Date(value))
        : 'Not available';

export default function AdminAppeals({
    filter,
    counts,
    appeals,
    status,
}: Props) {
    return (
        <>
            <Head title="Account appeals" />
            <div className="relative z-[1] mx-auto w-full max-w-[82rem] px-3 py-4 sm:px-6 sm:py-7 xl:px-8">
                <header className="max-w-3xl">
                    <p className="social-eyebrow">
                        <FileQuestion className="size-3.5" aria-hidden="true" />
                        Account access
                    </p>
                    <h1 className="mt-2 text-3xl font-black tracking-[-0.045em] sm:text-4xl">
                        Account appeals
                    </h1>
                    <p className="mt-2 max-w-2xl text-sm leading-6 text-muted-foreground sm:text-base">
                        Read the member’s own context, compare it with the
                        internal restriction record, and document a clear human
                        decision.
                    </p>
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
                    aria-label="Appeal status"
                    className="mt-5 grid grid-cols-2 gap-2 sm:grid-cols-4"
                >
                    {filters.map((item) => (
                        <Link
                            key={item.value}
                            href={
                                item.value === 'active'
                                    ? '/admin/appeals'
                                    : `/admin/appeals?status=${item.value}`
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

                <div className="mt-5 flex items-start gap-3 rounded-2xl border border-primary/15 bg-primary/[0.05] px-4 py-3 text-xs leading-5 text-muted-foreground sm:px-5">
                    <ShieldCheck
                        className="mt-0.5 size-4 shrink-0 text-primary"
                        aria-hidden="true"
                    />
                    <p>
                        Decisions are always made by an administrator. The
                        message entered below is visible to the member, so keep
                        private reports, reporter identities, and internal
                        evidence out of it.
                    </p>
                </div>

                {appeals.data.length === 0 ? (
                    <section className="social-card mt-5 rounded-[1.5rem] px-6 py-16 text-center">
                        <span className="mx-auto flex size-14 items-center justify-center rounded-2xl bg-mint/30">
                            <CheckCircle2 className="size-6" />
                        </span>
                        <h2 className="mt-4 text-lg font-black">
                            No {filter === 'all' ? '' : filter} appeals here
                        </h2>
                        <p className="mx-auto mt-2 max-w-md text-sm leading-6 text-muted-foreground">
                            New member appeals appear here without exposing the
                            internal restriction reason to the account holder.
                        </p>
                    </section>
                ) : (
                    <section
                        className="mt-5 space-y-4"
                        aria-label="Account appeals"
                    >
                        {appeals.data.map((appeal) => (
                            <AppealCard key={appeal.id} appeal={appeal} />
                        ))}
                    </section>
                )}

                {appeals.last_page > 1 && (
                    <nav
                        aria-label="Appeal pages"
                        className="mt-5 flex items-center justify-between gap-3"
                    >
                        {appeals.prev_page_url ? (
                            <Button asChild variant="outline">
                                <Link href={appeals.prev_page_url}>
                                    <ChevronLeft className="size-4" />
                                    Previous
                                </Link>
                            </Button>
                        ) : (
                            <span />
                        )}
                        <span className="text-xs font-bold text-muted-foreground">
                            Page {appeals.current_page} of {appeals.last_page}
                        </span>
                        {appeals.next_page_url ? (
                            <Button asChild variant="outline">
                                <Link href={appeals.next_page_url}>
                                    Next
                                    <ChevronRight className="size-4" />
                                </Link>
                            </Button>
                        ) : (
                            <span />
                        )}
                    </nav>
                )}
            </div>
        </>
    );
}

function AppealCard({ appeal }: { appeal: Appeal }) {
    const [message, setMessage] = useState('');
    const [processing, setProcessing] = useState<AppealAction | null>(null);
    const [error, setError] = useState<string | null>(null);
    const isActive = appeal.status === 'open' || appeal.status === 'reviewing';

    const submit = (action: AppealAction) => {
        if (message.trim().length < 10) {
            setError('Write the member a message of at least 10 characters.');

            return;
        }

        setError(null);
        setProcessing(action);
        router.patch(
            appeal.actionUrl,
            { action, decision_message: message.trim() },
            {
                preserveScroll: true,
                onError: (errors) =>
                    setError(
                        String(
                            errors.decision_message ??
                                errors.action ??
                                'The appeal could not be updated.',
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
                            statusTone[appeal.status],
                        )}
                    >
                        {appeal.statusLabel}
                    </span>
                    {!appeal.member.restricted && (
                        <span className="rounded-lg bg-mint/30 px-2.5 py-1.5 text-xs font-extrabold">
                            Access restored
                        </span>
                    )}
                </div>
                <time className="text-xs font-semibold text-muted-foreground">
                    Submitted {dateLabel(appeal.submittedAt)}
                </time>
            </header>

            <div className="grid gap-5 p-4 sm:p-5 lg:grid-cols-[minmax(0,1fr)_19rem]">
                <div className="min-w-0 space-y-4">
                    <section>
                        <p className="text-[0.68rem] font-extrabold tracking-[0.13em] text-muted-foreground uppercase">
                            Member statement
                        </p>
                        <blockquote className="mt-3 rounded-[1.2rem] border border-border/75 bg-background/60 px-4 py-3.5 text-sm leading-6 whitespace-pre-wrap">
                            {appeal.statement}
                        </blockquote>
                    </section>

                    <section className="rounded-2xl border border-coral/30 bg-coral/[0.055] p-4">
                        <p className="text-[0.68rem] font-extrabold tracking-[0.13em] text-muted-foreground uppercase">
                            Internal restriction record
                        </p>
                        <p className="mt-2 text-sm leading-6">
                            {appeal.member.internalReason ??
                                'No internal reason is available.'}
                        </p>
                        <time className="mt-2 block text-xs font-semibold text-muted-foreground">
                            Restricted {dateLabel(appeal.member.restrictedAt)}
                        </time>
                    </section>

                    {appeal.decisionMessage && (
                        <section className="rounded-2xl border border-primary/15 bg-primary/[0.05] p-4">
                            <p className="text-[0.68rem] font-extrabold tracking-[0.13em] text-primary uppercase">
                                Latest member-visible response
                            </p>
                            <p className="mt-2 text-sm leading-6">
                                {appeal.decisionMessage}
                            </p>
                            <p className="mt-2 text-xs font-semibold text-muted-foreground">
                                {appeal.reviewerName ?? 'Administrator'}
                                {appeal.reviewedAt
                                    ? ` · ${dateLabel(appeal.reviewedAt)}`
                                    : ''}
                            </p>
                        </section>
                    )}
                </div>

                <aside className="rounded-2xl border border-border/75 p-4">
                    <p className="text-[0.68rem] font-extrabold tracking-[0.13em] text-muted-foreground uppercase">
                        Account holder
                    </p>
                    <div className="mt-3 flex items-center gap-3">
                        <AvatarMark
                            name={appeal.member.name}
                            className="size-11"
                        />
                        <div className="min-w-0">
                            <p className="truncate text-sm font-black">
                                {appeal.member.name}
                            </p>
                            <p className="truncate text-xs text-muted-foreground">
                                @{appeal.member.handle}
                            </p>
                        </div>
                    </div>
                    <p className="mt-3 text-xs leading-5 break-all text-muted-foreground">
                        {appeal.member.email}
                    </p>
                    <Button
                        asChild
                        variant="outline"
                        size="sm"
                        className="mt-4 w-full"
                    >
                        <Link
                            href={`/admin/members?q=${encodeURIComponent(appeal.member.handle)}`}
                        >
                            <UserRoundSearch className="size-4" />
                            Review account
                        </Link>
                    </Button>
                </aside>
            </div>

            {isActive && (
                <div className="border-t border-border/75 bg-background/35 p-4 sm:p-5">
                    <label className="block text-sm font-bold">
                        Message visible to the member
                        <textarea
                            value={message}
                            onChange={(event) => setMessage(event.target.value)}
                            required
                            minLength={10}
                            maxLength={500}
                            rows={3}
                            placeholder="Explain the review state or final decision without exposing private evidence."
                            className="social-inset social-focus mt-2 w-full resize-y px-4 py-3 text-sm leading-6"
                        />
                    </label>
                    <div className="mt-2 flex items-start justify-between gap-3">
                        <div>
                            {error ? (
                                <p
                                    role="alert"
                                    className="text-sm font-bold text-destructive"
                                >
                                    {error}
                                </p>
                            ) : (
                                <p className="text-xs leading-5 text-muted-foreground">
                                    Approving explicitly restores access.
                                    Denying keeps the restriction active.
                                </p>
                            )}
                        </div>
                        <span className="shrink-0 text-xs font-semibold text-muted-foreground">
                            {message.length} / 500
                        </span>
                    </div>

                    <div className="mt-4 flex flex-wrap justify-end gap-2">
                        {appeal.status === 'open' && (
                            <Button
                                type="button"
                                variant="outline"
                                disabled={processing !== null}
                                onClick={() => submit('review')}
                            >
                                <Eye className="size-4" />
                                Start review
                            </Button>
                        )}
                        <Button
                            type="button"
                            variant="outline"
                            disabled={processing !== null}
                            onClick={() => submit('deny')}
                        >
                            <CircleX className="size-4" />
                            Keep restricted
                        </Button>
                        <Button
                            type="button"
                            disabled={processing !== null}
                            onClick={() => submit('approve')}
                        >
                            <ShieldCheck className="size-4" />
                            Approve &amp; restore
                        </Button>
                    </div>
                </div>
            )}
        </article>
    );
}

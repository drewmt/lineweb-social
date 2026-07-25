import { Head, Link } from '@inertiajs/react';
import {
    ArrowRight,
    Building2,
    CircleCheck,
    FileText,
    MessageSquareWarning,
    ScrollText,
    ShieldCheck,
    ShieldEllipsis,
    UserRoundCheck,
    UsersRound,
} from 'lucide-react';
import { cn } from '@/lib/utils';

type Metrics = {
    membersTotal: number;
    membersVerified: number;
    membersSuspended: number;
    administratorsTotal: number;
    spacesTotal: number;
    postsTotal: number;
    commentsTotal: number;
    communityReportsActive: number;
    messageReportsActive: number;
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
    metrics: Metrics;
    auditLogs: AuditLog[];
    status?: string;
};

const formatDate = (value: string) =>
    new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));

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

export default function AdminIndex({ metrics, auditLogs, status }: Props) {
    const overviewMetrics = [
        {
            label: 'Members',
            value: metrics.membersTotal,
            detail: `${metrics.membersVerified.toLocaleString()} verified`,
            icon: UsersRound,
        },
        {
            label: 'Spaces',
            value: metrics.spacesTotal,
            detail: 'Member-led communities',
            icon: Building2,
        },
        {
            label: 'Published activity',
            value: metrics.postsTotal + metrics.commentsTotal,
            detail: `${metrics.postsTotal.toLocaleString()} posts · ${metrics.commentsTotal.toLocaleString()} comments`,
            icon: FileText,
        },
        {
            label: 'Administrators',
            value: metrics.administratorsTotal,
            detail:
                metrics.administratorsTotal > 1
                    ? 'Operator resilience in place'
                    : 'Add a second trusted operator',
            icon: ShieldCheck,
            warning: metrics.administratorsTotal === 1,
        },
    ];

    return (
        <>
            <Head title="Administration overview" />
            <div className="relative z-[1] mx-auto w-full max-w-[88rem] px-3 py-4 sm:px-6 sm:py-7 xl:px-8">
                <header className="overflow-hidden rounded-[1.7rem] border border-foreground bg-foreground px-5 py-6 text-background sm:px-8 sm:py-8">
                    <div className="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                        <div className="max-w-3xl">
                            <p className="inline-flex items-center gap-2 text-[0.68rem] font-extrabold tracking-[0.15em] text-mint uppercase">
                                <span className="size-1.5 rounded-full bg-mint" />
                                Platform operations
                            </p>
                            <h1 className="mt-3 text-3xl font-black tracking-[-0.045em] sm:text-4xl">
                                Keep the community healthy.
                            </h1>
                            <p className="mt-3 max-w-2xl text-sm leading-6 text-background/68 sm:text-base">
                                A focused view of account access, private safety
                                reviews, and the privileged decisions that keep
                                the platform accountable.
                            </p>
                        </div>
                        <div className="inline-flex w-fit items-center gap-2 rounded-full border border-white/12 bg-white/[0.07] px-3.5 py-2.5 text-xs font-extrabold">
                            <CircleCheck
                                className="size-4 text-mint"
                                aria-hidden="true"
                            />
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
                    aria-label="Platform metrics"
                    className="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4"
                >
                    {overviewMetrics.map((metric) => {
                        const Icon = metric.icon;

                        return (
                            <article
                                key={metric.label}
                                className={cn(
                                    'social-card rounded-[1.35rem] p-4 sm:p-5',
                                    metric.warning &&
                                        'border-coral/45 bg-coral/[0.045]',
                                )}
                            >
                                <div className="flex items-start justify-between gap-3">
                                    <div>
                                        <p className="text-xs font-extrabold tracking-[0.11em] text-muted-foreground uppercase">
                                            {metric.label}
                                        </p>
                                        <p className="mt-2 text-3xl font-black tracking-[-0.05em] tabular-nums">
                                            {metric.value.toLocaleString()}
                                        </p>
                                    </div>
                                    <span
                                        className={cn(
                                            'flex size-10 items-center justify-center rounded-xl',
                                            metric.warning
                                                ? 'bg-coral/15 text-foreground'
                                                : 'bg-primary/9 text-primary',
                                        )}
                                    >
                                        <Icon
                                            className="size-4.5"
                                            aria-hidden="true"
                                        />
                                    </span>
                                </div>
                                <p className="mt-3 text-xs font-semibold text-muted-foreground">
                                    {metric.detail}
                                </p>
                            </article>
                        );
                    })}
                </section>

                <div className="mt-5 grid items-start gap-5 xl:grid-cols-[minmax(0,1fr)_24rem]">
                    <section>
                        <div className="mb-3 flex items-end justify-between gap-4 px-1">
                            <div>
                                <h2 className="text-xl font-black tracking-[-0.03em]">
                                    Action center
                                </h2>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    The queues that need an operator decision.
                                </p>
                            </div>
                        </div>

                        <div className="grid gap-3 lg:grid-cols-2">
                            <OperationCard
                                href="/admin/members?status=suspended"
                                icon={UserRoundCheck}
                                label="Account access"
                                title={`${metrics.membersSuspended.toLocaleString()} suspended`}
                                detail="Review restrictions and restore access only after a documented decision."
                                cta="Open members"
                                urgent={metrics.membersSuspended > 0}
                            />
                            <OperationCard
                                href="/admin/message-reports"
                                icon={MessageSquareWarning}
                                label="Private safety"
                                title={`${metrics.messageReportsActive.toLocaleString()} active reports`}
                                detail="Review only the exact message evidence members chose to submit."
                                cta="Open safety queue"
                                urgent={metrics.messageReportsActive > 0}
                            />
                        </div>

                        <section className="social-card mt-3 rounded-[1.35rem] p-5 sm:p-6">
                            <div className="flex items-start gap-4">
                                <span className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-secondary text-foreground">
                                    <ShieldEllipsis
                                        className="size-5"
                                        aria-hidden="true"
                                    />
                                </span>
                                <div>
                                    <h3 className="font-black tracking-tight">
                                        Space moderation stays decentralized
                                    </h3>
                                    <p className="mt-2 text-sm leading-6 text-muted-foreground">
                                        {metrics.communityReportsActive.toLocaleString()}{' '}
                                        active post or comment reports remain
                                        with the responsible Space teams.
                                        Platform administrators do not receive a
                                        hidden global content-moderation
                                        override.
                                    </p>
                                </div>
                            </div>
                        </section>
                    </section>

                    <aside className="social-card overflow-hidden rounded-[1.5rem] xl:sticky xl:top-20">
                        <div className="flex items-center justify-between gap-3 border-b px-5 py-4">
                            <div>
                                <h2 className="font-black tracking-tight">
                                    Recent audit
                                </h2>
                                <p className="mt-1 text-xs text-muted-foreground">
                                    Latest privileged actions
                                </p>
                            </div>
                            <ScrollText
                                className="size-5 text-primary"
                                aria-hidden="true"
                            />
                        </div>

                        {auditLogs.length === 0 ? (
                            <div className="px-5 py-10 text-center">
                                <CircleCheck className="mx-auto size-6 text-muted-foreground" />
                                <p className="mt-3 text-sm font-bold">
                                    No privileged actions yet
                                </p>
                                <p className="mt-1 text-xs leading-5 text-muted-foreground">
                                    New operator decisions will appear here.
                                </p>
                            </div>
                        ) : (
                            <ol className="divide-y divide-border/75">
                                {auditLogs.map((log) => (
                                    <li key={log.id} className="px-5 py-4">
                                        <p className="text-sm font-extrabold">
                                            {auditLabels[log.action] ??
                                                log.action}
                                        </p>
                                        <p className="mt-1 text-xs leading-5 text-muted-foreground">
                                            {log.actorName} · {log.subjectName}
                                            {log.subjectHandle
                                                ? ` (@${log.subjectHandle})`
                                                : ''}
                                        </p>
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

                        <Link
                            href="/admin/audit"
                            className="social-focus flex min-h-12 items-center justify-between border-t px-5 text-sm font-extrabold text-primary transition-colors hover:bg-primary/[0.05]"
                        >
                            View complete audit
                            <ArrowRight className="size-4" aria-hidden="true" />
                        </Link>
                    </aside>
                </div>
            </div>
        </>
    );
}

function OperationCard({
    href,
    icon: Icon,
    label,
    title,
    detail,
    cta,
    urgent,
}: {
    href: string;
    icon: typeof UserRoundCheck;
    label: string;
    title: string;
    detail: string;
    cta: string;
    urgent: boolean;
}) {
    return (
        <Link
            href={href}
            className="social-card social-card-interactive social-focus group flex min-h-56 flex-col rounded-[1.45rem] p-5 sm:p-6"
        >
            <div className="flex items-center justify-between gap-3">
                <span
                    className={cn(
                        'flex size-11 items-center justify-center rounded-xl',
                        urgent
                            ? 'bg-coral/15 text-foreground'
                            : 'bg-mint/25 text-foreground',
                    )}
                >
                    <Icon className="size-5" aria-hidden="true" />
                </span>
                <span
                    className={cn(
                        'rounded-full px-2.5 py-1 text-[0.65rem] font-extrabold tracking-[0.08em] uppercase',
                        urgent
                            ? 'bg-coral/15 text-foreground'
                            : 'bg-secondary text-muted-foreground',
                    )}
                >
                    {urgent ? 'Needs review' : 'Clear'}
                </span>
            </div>
            <p className="mt-6 text-[0.68rem] font-extrabold tracking-[0.13em] text-muted-foreground uppercase">
                {label}
            </p>
            <h3 className="mt-1 text-xl font-black tracking-[-0.035em]">
                {title}
            </h3>
            <p className="mt-2 text-sm leading-6 text-muted-foreground">
                {detail}
            </p>
            <span className="mt-auto inline-flex items-center gap-2 pt-5 text-sm font-extrabold text-primary">
                {cta}
                <ArrowRight
                    className="size-4 transition-transform group-hover:translate-x-0.5"
                    aria-hidden="true"
                />
            </span>
        </Link>
    );
}

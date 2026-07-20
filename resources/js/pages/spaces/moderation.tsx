import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    CheckCircle2,
    Eye,
    EyeOff,
    Flag,
    RotateCcw,
    ShieldCheck,
} from 'lucide-react';
import { useState } from 'react';
import { AvatarMark } from '@/components/social/avatar-mark';
import { Button } from '@/components/ui/button';

type ReportStatus = 'open' | 'reviewing' | 'resolved' | 'dismissed';
type ReportAction = 'review' | 'hide' | 'dismiss' | 'reopen';

type ModerationReport = {
    contentType: 'post' | 'comment';
    contentLabel: 'Post' | 'Comment';
    actionUrl: string;
    id: number;
    reason: string;
    reasonLabel: string;
    details: string | null;
    status: ReportStatus;
    statusLabel: string;
    reporter: { name: string } | null;
    reviewer: string | null;
    moderatorNote: string | null;
    createdAt: string;
    reviewedAt: string | null;
    content: {
        id: number;
        body: string;
        author: { name: string; handle: string };
        hiddenAt: string | null;
        postContext: { id: number; body: string } | null;
    };
};

type ModerationProps = {
    space: { name: string; slug: string };
    reports: ModerationReport[];
    counts: { active: number; resolved: number; dismissed: number };
    status?: string;
};

const dateLabel = (value: string) =>
    new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));

const statusTone: Record<ReportStatus, string> = {
    open: 'bg-coral/12 text-foreground',
    reviewing: 'bg-primary/10 text-primary',
    resolved: 'bg-mint/25 text-foreground',
    dismissed: 'bg-secondary text-muted-foreground',
};

function ReportCard({ report }: { report: ModerationReport }) {
    const [note, setNote] = useState('');
    const [processing, setProcessing] = useState<ReportAction | null>(null);
    const [error, setError] = useState<string | null>(null);
    const isActive = report.status === 'open' || report.status === 'reviewing';

    const submit = (action: ReportAction) => {
        const needsNote = action !== 'review';

        if (needsNote && note.trim().length < 3) {
            setError('Add a short moderator note before making this decision.');

            return;
        }

        setError(null);
        setProcessing(action);
        router.patch(
            report.actionUrl,
            { action, note: note.trim() || null },
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
        <article className="social-card overflow-hidden rounded-[1.35rem]">
            <div className="border-b bg-secondary/30 px-4 py-3 sm:px-5">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <div className="flex flex-wrap items-center gap-2">
                        <span
                            className={`inline-flex min-h-8 items-center rounded-lg px-2.5 text-xs font-extrabold ${statusTone[report.status]}`}
                        >
                            {report.statusLabel}
                        </span>
                        <span className="text-sm font-extrabold">
                            {report.contentLabel} · {report.reasonLabel}
                        </span>
                    </div>
                    <time className="text-xs font-semibold text-muted-foreground">
                        {dateLabel(report.createdAt)}
                    </time>
                </div>
            </div>

            <div className="p-4 sm:p-5">
                <div className="flex items-start gap-3">
                    <AvatarMark
                        name={report.content.author.name}
                        className="size-10"
                    />
                    <div className="min-w-0 flex-1">
                        <Link
                            href={`/people/${report.content.author.handle}`}
                            className="font-extrabold hover:underline"
                        >
                            {report.content.author.name}
                        </Link>
                        <p className="mt-2 text-[0.98rem] leading-7 whitespace-pre-wrap">
                            {report.content.body}
                        </p>
                        {report.content.postContext && (
                            <div className="mt-3 border-l-2 border-primary/25 pl-3 text-xs leading-5 text-muted-foreground">
                                <span className="font-extrabold text-foreground">
                                    On post
                                </span>{' '}
                                {report.content.postContext.body}
                            </div>
                        )}
                        {report.content.hiddenAt && (
                            <p className="mt-3 inline-flex items-center gap-1.5 text-xs font-bold text-muted-foreground">
                                <EyeOff
                                    className="size-3.5"
                                    aria-hidden="true"
                                />
                                {report.contentLabel} hidden from community
                                surfaces
                            </p>
                        )}
                    </div>
                </div>

                <div className="mt-5 grid gap-3 rounded-2xl border bg-secondary/20 p-4 text-sm sm:grid-cols-2">
                    <div>
                        <p className="text-xs font-extrabold tracking-[0.1em] text-muted-foreground uppercase">
                            Reported by
                        </p>
                        <p className="mt-1 font-bold">
                            {report.reporter?.name ?? 'Former member'}
                        </p>
                    </div>
                    <div>
                        <p className="text-xs font-extrabold tracking-[0.1em] text-muted-foreground uppercase">
                            Context
                        </p>
                        <p className="mt-1 leading-6">
                            {report.details ??
                                'No additional details provided.'}
                        </p>
                    </div>
                    {report.reviewer && (
                        <div className="sm:col-span-2">
                            <p className="text-xs font-extrabold tracking-[0.1em] text-muted-foreground uppercase">
                                Latest decision
                            </p>
                            <p className="mt-1 leading-6">
                                {report.reviewer}
                                {report.moderatorNote
                                    ? ` — ${report.moderatorNote}`
                                    : ' started reviewing this report.'}
                            </p>
                        </div>
                    )}
                </div>

                <div className="mt-5 border-t pt-4">
                    <label className="block text-sm font-bold">
                        Moderator note{' '}
                        <span className="font-medium text-muted-foreground">
                            (required for decisions)
                        </span>
                        <textarea
                            value={note}
                            onChange={(event) => setNote(event.target.value)}
                            maxLength={500}
                            rows={2}
                            placeholder="Record the reason for the moderation decision."
                            className="social-inset social-focus mt-2 w-full resize-y px-3.5 py-3 text-sm leading-6"
                        />
                    </label>
                    {error && (
                        <p
                            role="alert"
                            className="mt-2 text-sm font-bold text-destructive"
                        >
                            {error}
                        </p>
                    )}
                    <div className="mt-3 flex flex-wrap justify-end gap-2">
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
                                    onClick={() => submit('hide')}
                                >
                                    <EyeOff
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    Hide {report.contentLabel.toLowerCase()}
                                </Button>
                            </>
                        ) : (
                            <Button
                                type="button"
                                variant="outline"
                                disabled={processing !== null}
                                onClick={() => submit('reopen')}
                            >
                                <RotateCcw
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Reopen report
                            </Button>
                        )}
                    </div>
                </div>
            </div>
        </article>
    );
}

export default function Moderation({
    space,
    reports,
    counts,
    status,
}: ModerationProps) {
    return (
        <>
            <Head title={`Moderation · ${space.name}`} />

            <main className="social-page">
                <div className="mx-auto max-w-5xl">
                    <header className="mb-5 flex flex-wrap items-end justify-between gap-4 px-1">
                        <div>
                            <Link
                                href={`/spaces/${space.slug}/manage`}
                                className="social-focus inline-flex items-center gap-2 rounded-lg text-sm font-bold text-primary hover:underline"
                            >
                                <ArrowLeft
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Space management
                            </Link>
                            <div className="mt-4 flex items-start gap-3">
                                <div className="flex size-11 items-center justify-center rounded-2xl bg-primary text-primary-foreground">
                                    <ShieldCheck
                                        className="size-5"
                                        aria-hidden="true"
                                    />
                                </div>
                                <div>
                                    <p className="text-xs font-extrabold tracking-[0.14em] text-primary uppercase">
                                        Trust & safety
                                    </p>
                                    <h1 className="mt-1 text-2xl font-black tracking-[-0.035em] sm:text-4xl">
                                        {space.name} reports
                                    </h1>
                                    <p className="mt-1.5 max-w-2xl text-sm leading-6 text-muted-foreground">
                                        Review member concerns, document every
                                        decision, and keep moderation
                                        accountable.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </header>

                    <section
                        className="mb-5 grid grid-cols-3 gap-2 sm:gap-3"
                        aria-label="Report summary"
                    >
                        {[
                            ['Active', counts.active],
                            ['Hidden', counts.resolved],
                            ['Dismissed', counts.dismissed],
                        ].map(([label, count]) => (
                            <div
                                key={label}
                                className="social-card rounded-2xl p-3 text-center sm:p-4"
                            >
                                <p className="text-xl font-black sm:text-2xl">
                                    {count}
                                </p>
                                <p className="mt-0.5 text-xs font-bold text-muted-foreground">
                                    {label}
                                </p>
                            </div>
                        ))}
                    </section>

                    {status && (
                        <div
                            role="status"
                            className="mb-5 rounded-2xl border border-primary/20 bg-primary/8 px-4 py-3 text-sm font-bold"
                        >
                            {status}
                        </div>
                    )}

                    {reports.length === 0 ? (
                        <div className="social-card rounded-[1.35rem] px-6 py-14 text-center">
                            <div className="mx-auto flex size-14 items-center justify-center rounded-2xl bg-mint/30">
                                <Flag className="size-6" aria-hidden="true" />
                            </div>
                            <h2 className="mt-4 text-lg font-extrabold">
                                No reports to review
                            </h2>
                            <p className="mx-auto mt-2 max-w-md text-sm leading-6 text-muted-foreground">
                                New member reports will appear here without
                                exposing them on public community surfaces.
                            </p>
                        </div>
                    ) : (
                        <section
                            className="space-y-4"
                            aria-label="Content reports"
                        >
                            {reports.map((report) => (
                                <ReportCard
                                    key={`${report.contentType}-${report.id}`}
                                    report={report}
                                />
                            ))}
                        </section>
                    )}
                </div>
            </main>
        </>
    );
}

Moderation.layout = {
    breadcrumbs: [
        { title: 'Spaces', href: '/spaces' },
        { title: 'Moderation', href: '#' },
    ],
};

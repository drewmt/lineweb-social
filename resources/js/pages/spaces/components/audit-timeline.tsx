import { History } from 'lucide-react';
import type { AuditEntry } from '../management-types';

type AuditTimelineProps = {
    entries: AuditEntry[];
};

const actionLabels: Record<string, string> = {
    'invitation.sent': 'sent an invitation',
    'invitation.accepted': 'accepted an invitation',
    'invitation.revoked': 'cancelled an invitation',
    'member.role_changed': 'changed a member role',
    'member.removed': 'removed a member',
    'ownership.transferred': 'transferred ownership',
    'post_report.review_started': 'started reviewing a post report',
    'post_report.resolved': 'hid a reported post',
    'post_report.dismissed': 'dismissed a post report',
    'post_report.reopened': 'reopened a post report',
};

const dateLabel = (value: string) =>
    new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));

export function AuditTimeline({ entries }: AuditTimelineProps) {
    return (
        <section className="social-card rounded-[1.35rem] p-4 sm:p-5">
            <div className="mb-4 flex items-start gap-3">
                <div className="flex size-9 shrink-0 items-center justify-center rounded-xl bg-secondary text-secondary-foreground">
                    <History className="size-4" aria-hidden="true" />
                </div>
                <div>
                    <h2 className="font-extrabold tracking-tight">
                        Activity log
                    </h2>
                    <p className="mt-1 text-sm text-muted-foreground">
                        A durable record of privileged Space actions.
                    </p>
                </div>
            </div>

            {entries.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    No management activity yet.
                </p>
            ) : (
                <ol className="space-y-4 border-l pl-4">
                    {entries.map((entry) => (
                        <li key={entry.id} className="relative text-sm">
                            <span className="absolute top-1.5 -left-[1.22rem] size-2 rounded-full bg-primary ring-4 ring-card" />
                            <p className="leading-6">
                                <strong>{entry.actor}</strong>{' '}
                                {actionLabels[entry.action] ?? entry.action}
                                {entry.subject && (
                                    <>
                                        {' '}
                                        for <strong>{entry.subject}</strong>
                                    </>
                                )}
                            </p>
                            {entry.reason && (
                                <p className="mt-1 rounded-lg bg-muted/70 px-2.5 py-2 text-xs leading-5 text-muted-foreground">
                                    Reason: {entry.reason}
                                </p>
                            )}
                            <time
                                dateTime={entry.createdAt}
                                className="mt-1 block text-xs text-muted-foreground"
                            >
                                {dateLabel(entry.createdAt)}
                            </time>
                        </li>
                    ))}
                </ol>
            )}
        </section>
    );
}

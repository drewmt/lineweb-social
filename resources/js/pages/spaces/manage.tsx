import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Flag, ShieldCheck, UsersRound } from 'lucide-react';
import { SpaceCover } from '@/components/social/space-cover';
import { AuditTimeline } from './components/audit-timeline';
import { InvitationPanel } from './components/invitation-panel';
import { MemberList } from './components/member-list';
import type {
    AuditEntry,
    ManagedMember,
    ManagedSpace,
    PendingInvitation,
} from './management-types';

type ManageSpaceProps = {
    space: ManagedSpace;
    members: ManagedMember[];
    invitations: PendingInvitation[];
    audit: AuditEntry[];
    permissions: {
        canInviteModerators: boolean;
        canTransferOwnership: boolean;
    };
    openReportsCount: number;
    status?: string;
};

export default function ManageSpace({
    space,
    members,
    invitations,
    audit,
    permissions,
    openReportsCount,
    status,
}: ManageSpaceProps) {
    return (
        <>
            <Head title={`Manage ${space.name}`} />

            <main className="social-page">
                <div className="mx-auto max-w-6xl">
                    <header className="social-card relative mb-5 overflow-hidden rounded-[1.8rem]">
                        <SpaceCover
                            seed={space.slug}
                            className="absolute top-0 right-0 h-full w-72 translate-x-12 scale-125 opacity-90 sm:w-[28rem]"
                        />
                        <div className="relative z-10 bg-gradient-to-r from-card via-card/95 to-card/25 p-5 sm:p-7">
                            <Link
                                href={`/spaces/${space.slug}`}
                                className="social-focus mb-5 inline-flex items-center gap-2 rounded-full text-sm font-bold text-primary hover:underline"
                            >
                                <ArrowLeft
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Back to space
                            </Link>
                            <div className="flex flex-wrap items-start justify-between gap-4">
                                <div className="flex items-start gap-3">
                                    <div className="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-primary text-primary-foreground shadow-lg shadow-primary/15">
                                        <ShieldCheck
                                            className="size-5"
                                            aria-hidden="true"
                                        />
                                    </div>
                                    <div>
                                        <p className="text-xs font-extrabold tracking-[0.14em] text-primary uppercase">
                                            Space management
                                        </p>
                                        <h1 className="mt-1 text-2xl font-black tracking-[-0.035em] sm:text-4xl">
                                            {space.name}
                                        </h1>
                                        <p className="mt-1.5 max-w-2xl text-sm leading-6 text-muted-foreground">
                                            Invite people, shape the team, and
                                            keep every sensitive action
                                            accountable.
                                        </p>
                                        <span className="mt-3 inline-flex items-center gap-1.5 rounded-full bg-secondary px-3 py-1.5 text-xs font-bold text-muted-foreground">
                                            <UsersRound
                                                className="size-3.5"
                                                aria-hidden="true"
                                            />
                                            {members.length}{' '}
                                            {members.length === 1
                                                ? 'member'
                                                : 'members'}
                                        </span>
                                    </div>
                                </div>
                                <Link
                                    href={`/spaces/${space.slug}/moderation`}
                                    className="social-focus inline-flex min-h-11 items-center gap-2 rounded-xl border bg-card/80 px-4 text-sm font-extrabold shadow-sm transition-colors hover:bg-secondary"
                                >
                                    <Flag
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    Reports
                                    {openReportsCount > 0 && (
                                        <span className="inline-flex min-w-6 items-center justify-center rounded-lg bg-coral/15 px-1.5 py-0.5 text-xs">
                                            {openReportsCount}
                                        </span>
                                    )}
                                </Link>
                            </div>
                        </div>
                    </header>

                    {status && (
                        <div
                            role="status"
                            className="mb-5 rounded-2xl border border-primary/20 bg-primary/8 px-4 py-3 text-sm font-bold"
                        >
                            {status}
                        </div>
                    )}

                    <div className="grid gap-5 lg:grid-cols-[minmax(0,1fr)_22rem]">
                        <div className="space-y-5">
                            <InvitationPanel
                                spaceSlug={space.slug}
                                invitations={invitations}
                                canInviteModerators={
                                    permissions.canInviteModerators
                                }
                            />
                            <MemberList
                                spaceSlug={space.slug}
                                members={members}
                            />
                        </div>
                        <aside className="lg:sticky lg:top-6 lg:self-start">
                            <AuditTimeline entries={audit} />
                        </aside>
                    </div>
                </div>
            </main>
        </>
    );
}

ManageSpace.layout = {
    breadcrumbs: [
        { title: 'Spaces', href: '/spaces' },
        { title: 'Manage members', href: '#' },
    ],
};

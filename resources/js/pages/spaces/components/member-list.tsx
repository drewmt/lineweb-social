import { router } from '@inertiajs/react';
import { Crown, ShieldCheck, UserMinus, UsersRound } from 'lucide-react';
import { useState } from 'react';
import { AvatarMark } from '@/components/social/avatar-mark';
import { Button } from '@/components/ui/button';
import type { ManagedMember, SpaceRole } from '../management-types';

type MemberListProps = {
    spaceSlug: string;
    members: ManagedMember[];
};

function RoleMark({ role }: { role: SpaceRole }) {
    if (role === 'owner') {
        return <Crown className="size-3.5" aria-hidden="true" />;
    }

    if (role === 'moderator') {
        return <ShieldCheck className="size-3.5" aria-hidden="true" />;
    }

    return <UsersRound className="size-3.5" aria-hidden="true" />;
}

function MemberRow({
    member,
    spaceSlug,
}: {
    member: ManagedMember;
    spaceSlug: string;
}) {
    const [removing, setRemoving] = useState(false);
    const [reason, setReason] = useState('');
    const [confirmingTransfer, setConfirmingTransfer] = useState(false);

    const updateRole = (role: Exclude<SpaceRole, 'owner'>) => {
        router.patch(
            `/spaces/${spaceSlug}/members/${member.id}/role`,
            { role },
            { preserveScroll: true },
        );
    };

    const remove = () => {
        router.delete(`/spaces/${spaceSlug}/members/${member.id}`, {
            data: { reason },
            preserveScroll: true,
            onSuccess: () => {
                setRemoving(false);
                setReason('');
            },
        });
    };

    const transferOwnership = () => {
        router.put(
            `/spaces/${spaceSlug}/owner`,
            { member_id: member.id },
            { preserveScroll: true },
        );
    };

    return (
        <li className="rounded-2xl border border-border/70 bg-background p-3.5 transition-colors hover:bg-secondary/35">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex min-w-0 items-center gap-3">
                    <AvatarMark name={member.name} className="size-10" />
                    <div className="min-w-0">
                        <p className="truncate text-sm font-extrabold">
                            {member.name}
                        </p>
                        <span className="mt-0.5 inline-flex items-center gap-1 text-xs font-medium text-muted-foreground capitalize">
                            <RoleMark role={member.role} />
                            {member.role}
                        </span>
                    </div>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    {member.canChangeRole && (
                        <select
                            aria-label={`Role for ${member.name}`}
                            value={member.role}
                            onChange={(event) =>
                                updateRole(
                                    event.target.value as Exclude<
                                        SpaceRole,
                                        'owner'
                                    >,
                                )
                            }
                            className="h-8 rounded-full border bg-background px-3 text-xs font-semibold outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        >
                            <option value="member">Member</option>
                            <option value="moderator">Moderator</option>
                        </select>
                    )}
                    {member.canReceiveOwnership && (
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={() => setConfirmingTransfer(true)}
                        >
                            Transfer ownership
                        </Button>
                    )}
                    {member.canRemove && (
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            className="text-destructive hover:text-destructive"
                            onClick={() => setRemoving(true)}
                        >
                            <UserMinus className="size-4" aria-hidden="true" />
                            Remove
                        </Button>
                    )}
                </div>
            </div>

            {confirmingTransfer && (
                <div className="mt-3 rounded-xl border border-primary/20 bg-primary/8 p-3 text-sm">
                    <p>
                        Make <strong>{member.name}</strong> the owner? You will
                        remain a moderator.
                    </p>
                    <div className="mt-3 flex gap-2">
                        <Button
                            type="button"
                            size="sm"
                            onClick={transferOwnership}
                        >
                            Confirm transfer
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            onClick={() => setConfirmingTransfer(false)}
                        >
                            Keep current owner
                        </Button>
                    </div>
                </div>
            )}

            {removing && (
                <div className="mt-3 rounded-xl border border-destructive/20 bg-destructive/5 p-3">
                    <label
                        htmlFor={`remove-reason-${member.id}`}
                        className="text-sm font-semibold"
                    >
                        Why is {member.name} being removed?
                    </label>
                    <textarea
                        id={`remove-reason-${member.id}`}
                        value={reason}
                        onChange={(event) => setReason(event.target.value)}
                        rows={2}
                        maxLength={500}
                        className="mt-2 w-full resize-y rounded-xl border bg-background px-3 py-2 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        placeholder="A clear reason is required for the audit trail."
                    />
                    <div className="mt-2 flex gap-2">
                        <Button
                            type="button"
                            variant="destructive"
                            size="sm"
                            disabled={reason.trim().length < 3}
                            onClick={remove}
                        >
                            Remove member
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={() => setRemoving(false)}
                        >
                            Cancel
                        </Button>
                    </div>
                </div>
            )}
        </li>
    );
}

export function MemberList({ spaceSlug, members }: MemberListProps) {
    return (
        <section className="social-card rounded-[1.35rem] p-4 sm:p-5">
            <div className="mb-4 flex items-center justify-between gap-3">
                <div>
                    <h2 className="font-extrabold tracking-tight">Members</h2>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Roles and access are enforced on the server.
                    </p>
                </div>
                <span className="rounded-full bg-secondary px-2.5 py-1 text-xs font-extrabold">
                    {members.length}
                </span>
            </div>
            <ul className="space-y-2">
                {members.map((member) => (
                    <MemberRow
                        key={member.id}
                        member={member}
                        spaceSlug={spaceSlug}
                    />
                ))}
            </ul>
        </section>
    );
}

import { Link } from '@inertiajs/react';
import { Ban, RotateCcw, ShieldCheck, UserCheck } from 'lucide-react';
import { Button } from '@/components/ui/button';

export type Member = {
    id: number;
    name: string;
    handle: string;
    email: string;
    platformRole: 'member' | 'administrator';
    emailVerifiedAt: string | null;
    suspendedAt: string | null;
    suspensionReason: string | null;
    joinedAt: string | null;
    isSelf: boolean;
    canSuspend: boolean;
};

const formatDate = (value: string | null) =>
    value
        ? new Intl.DateTimeFormat(undefined, {
              dateStyle: 'medium',
              timeStyle: 'short',
          }).format(new Date(value))
        : '—';

export function MemberRow({
    member,
    onAction,
}: {
    member: Member;
    onAction: (member: Member, action: 'suspend' | 'reinstate') => void;
}) {
    return (
        <li className="p-4 sm:p-5">
            <div className="flex flex-col gap-4 lg:flex-row lg:items-center">
                <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <Link
                            href={`/people/${member.handle}`}
                            className="social-focus rounded-md font-black tracking-tight hover:text-primary"
                        >
                            {member.name}
                        </Link>
                        {member.platformRole === 'administrator' && (
                            <span className="inline-flex items-center gap-1 rounded-full bg-primary/10 px-2 py-1 text-[0.66rem] font-extrabold text-primary">
                                <ShieldCheck className="size-3" />
                                Administrator
                            </span>
                        )}
                        {member.suspendedAt && (
                            <span className="inline-flex items-center gap-1 rounded-full bg-destructive/10 px-2 py-1 text-[0.66rem] font-extrabold text-destructive">
                                <Ban className="size-3" />
                                Suspended
                            </span>
                        )}
                        {!member.emailVerifiedAt && (
                            <span className="rounded-full bg-secondary px-2 py-1 text-[0.66rem] font-extrabold text-muted-foreground">
                                Unverified
                            </span>
                        )}
                        {member.isSelf && (
                            <span className="rounded-full bg-secondary px-2 py-1 text-[0.66rem] font-extrabold">
                                You
                            </span>
                        )}
                    </div>
                    <p className="mt-1 truncate text-sm text-muted-foreground">
                        @{member.handle} · {member.email}
                    </p>
                    <p className="mt-2 text-xs font-semibold text-muted-foreground">
                        Joined {formatDate(member.joinedAt)}
                        {member.suspendedAt
                            ? ` · Suspended ${formatDate(member.suspendedAt)}`
                            : ''}
                    </p>
                    {member.suspendedAt && member.suspensionReason && (
                        <p className="mt-2 line-clamp-2 text-xs leading-5 text-foreground/80">
                            <span className="font-extrabold">
                                Current reason:
                            </span>{' '}
                            {member.suspensionReason}
                        </p>
                    )}
                </div>

                <div className="shrink-0">
                    {member.suspendedAt ? (
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onAction(member, 'reinstate')}
                            className="min-h-10 w-full rounded-xl sm:w-auto"
                        >
                            <RotateCcw className="size-4" />
                            Reinstate
                        </Button>
                    ) : member.canSuspend ? (
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onAction(member, 'suspend')}
                            className="min-h-10 w-full rounded-xl text-destructive hover:text-destructive sm:w-auto"
                        >
                            <Ban className="size-4" />
                            Suspend
                        </Button>
                    ) : (
                        <span className="inline-flex items-center gap-1.5 px-2 text-xs font-bold text-muted-foreground">
                            <UserCheck className="size-4" />
                            Protected
                        </span>
                    )}
                </div>
            </div>
        </li>
    );
}

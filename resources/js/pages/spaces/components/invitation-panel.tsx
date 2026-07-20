import { router, useForm } from '@inertiajs/react';
import { MailPlus, Send, X } from 'lucide-react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { PendingInvitation, SpaceRole } from '../management-types';

type InvitationPanelProps = {
    spaceSlug: string;
    invitations: PendingInvitation[];
    canInviteModerators: boolean;
};

const expiryLabel = (value: string) =>
    new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(
        new Date(value),
    );

export function InvitationPanel({
    spaceSlug,
    invitations,
    canInviteModerators,
}: InvitationPanelProps) {
    const { data, setData, post, processing, errors, reset } = useForm<{
        email: string;
        role: Exclude<SpaceRole, 'owner'>;
    }>({
        email: '',
        role: 'member',
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        post(`/spaces/${spaceSlug}/invitations`, {
            preserveScroll: true,
            onSuccess: () => reset('email'),
        });
    };

    return (
        <section className="social-card rounded-[1.35rem] p-4 sm:p-5">
            <div className="mb-5 flex items-start gap-3">
                <div className="flex size-10 shrink-0 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                    <MailPlus className="size-5" aria-hidden="true" />
                </div>
                <div>
                    <h2 className="font-extrabold tracking-tight">
                        Invite people
                    </h2>
                    <p className="mt-1 text-sm leading-6 text-muted-foreground">
                        Invitations expire after seven days and can only be used
                        by the matching verified account.
                    </p>
                </div>
            </div>

            <form
                onSubmit={submit}
                className="grid gap-3 sm:grid-cols-[1fr_10rem_auto]"
            >
                <div>
                    <label htmlFor="invite-email" className="sr-only">
                        Email address
                    </label>
                    <Input
                        id="invite-email"
                        type="email"
                        required
                        autoComplete="email"
                        value={data.email}
                        onChange={(event) =>
                            setData('email', event.target.value)
                        }
                        placeholder="person@example.com"
                        className="rounded-xl"
                    />
                    <InputError className="mt-2" message={errors.email} />
                </div>
                <div>
                    <label htmlFor="invite-role" className="sr-only">
                        Starting role
                    </label>
                    <select
                        id="invite-role"
                        value={data.role}
                        onChange={(event) =>
                            setData(
                                'role',
                                event.target.value as Exclude<
                                    SpaceRole,
                                    'owner'
                                >,
                            )
                        }
                        className="h-9 w-full rounded-xl border bg-background px-3 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    >
                        <option value="member">Member</option>
                        {canInviteModerators && (
                            <option value="moderator">Moderator</option>
                        )}
                    </select>
                    <InputError className="mt-2" message={errors.role} />
                </div>
                <Button
                    type="submit"
                    disabled={processing}
                    className="rounded-xl"
                >
                    <Send className="size-4" aria-hidden="true" />
                    Send
                </Button>
            </form>

            <div className="mt-6 border-t pt-5">
                <h3 className="text-sm font-semibold">Pending invitations</h3>
                {invitations.length === 0 ? (
                    <p className="mt-2 text-sm text-muted-foreground">
                        No invitations are waiting for a response.
                    </p>
                ) : (
                    <ul className="mt-3 space-y-2">
                        {invitations.map((invitation) => (
                            <li
                                key={invitation.id}
                                className="flex flex-col gap-3 rounded-xl bg-muted/65 px-3.5 py-3 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <div className="min-w-0">
                                    <p className="truncate text-sm font-medium">
                                        {invitation.email}
                                    </p>
                                    <p className="mt-0.5 text-xs text-muted-foreground">
                                        {invitation.role} · expires{' '}
                                        {expiryLabel(invitation.expiresAt)}
                                    </p>
                                </div>
                                {invitation.canCancel && (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        className="self-start text-muted-foreground sm:self-auto"
                                        onClick={() =>
                                            router.delete(
                                                `/spaces/${spaceSlug}/invitations/${invitation.id}`,
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        <X
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        Cancel
                                    </Button>
                                )}
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </section>
    );
}

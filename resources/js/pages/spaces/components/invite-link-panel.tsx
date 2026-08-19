import { router, useForm } from '@inertiajs/react';
import {
    Check,
    Copy,
    Link2,
    ShieldCheck,
    UserRoundPlus,
    X,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { CreatedInviteLink, ManagedInviteLink } from '../management-types';

type InviteLinkPanelProps = {
    spaceSlug: string;
    inviteLinks: ManagedInviteLink[];
    createdInviteLink?: CreatedInviteLink | null;
};

const dateLabel = (value: string) =>
    new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(
        new Date(value),
    );

export function InviteLinkPanel({
    spaceSlug,
    inviteLinks,
    createdInviteLink,
}: InviteLinkPanelProps) {
    const [copyState, setCopyState] = useState<'idle' | 'copied' | 'error'>(
        'idle',
    );
    const { data, setData, post, processing, errors, reset } = useForm({
        label: '',
        expires_in_days: 7,
        max_uses: 25,
        invite_link: '',
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        post(`/spaces/${spaceSlug}/invite-links`, {
            preserveScroll: true,
            onSuccess: () => reset(),
        });
    };

    const copyLink = async () => {
        if (!createdInviteLink) {
            return;
        }

        try {
            await navigator.clipboard.writeText(createdInviteLink.url);
            setCopyState('copied');
        } catch {
            setCopyState('error');
        }
    };

    return (
        <section className="social-card overflow-hidden rounded-[1.35rem]">
            <div className="border-b bg-[linear-gradient(135deg,hsl(var(--primary)/.09),transparent_58%)] p-4 sm:p-5">
                <div className="flex items-start gap-3">
                    <div className="flex size-10 shrink-0 items-center justify-center rounded-2xl bg-primary text-primary-foreground shadow-md shadow-primary/15">
                        <Link2 className="size-5" aria-hidden="true" />
                    </div>
                    <div>
                        <p className="text-[0.65rem] font-extrabold tracking-[0.14em] text-primary uppercase">
                            Fast onboarding
                        </p>
                        <h2 className="mt-1 font-extrabold tracking-tight">
                            Share an invite link
                        </h2>
                        <p className="mt-1 max-w-2xl text-sm leading-6 text-muted-foreground">
                            Bring a group into this Space with one expiring,
                            limited-use link. Every person joins as a member.
                        </p>
                    </div>
                </div>
            </div>

            <div className="p-4 sm:p-5">
                {createdInviteLink && (
                    <div className="mb-5 rounded-2xl border border-emerald-500/25 bg-emerald-500/8 p-3.5 sm:p-4">
                        <div className="flex items-start gap-3">
                            <ShieldCheck className="mt-0.5 size-5 shrink-0 text-emerald-700 dark:text-emerald-300" />
                            <div className="min-w-0 flex-1">
                                <p className="text-sm font-extrabold">
                                    Your new link is ready
                                </p>
                                <p className="mt-1 text-xs leading-5 text-muted-foreground">
                                    Copy it now. For safety, the full link is
                                    shown only once.
                                </p>
                                <div className="mt-3 flex flex-col gap-2 sm:flex-row">
                                    <Input
                                        readOnly
                                        value={createdInviteLink.url}
                                        onFocus={(event) =>
                                            event.currentTarget.select()
                                        }
                                        aria-label="New invite link"
                                        className="min-w-0 rounded-xl bg-background font-mono text-xs"
                                    />
                                    <Button
                                        type="button"
                                        onClick={copyLink}
                                        className="min-h-11 shrink-0 rounded-xl"
                                    >
                                        {copyState === 'copied' ? (
                                            <Check className="size-4" />
                                        ) : (
                                            <Copy className="size-4" />
                                        )}
                                        {copyState === 'copied'
                                            ? 'Copied'
                                            : 'Copy link'}
                                    </Button>
                                </div>
                                <p
                                    aria-live="polite"
                                    className="mt-2 text-xs font-semibold text-muted-foreground"
                                >
                                    {copyState === 'error'
                                        ? 'Copy was blocked by the browser. Select the link and copy it manually.'
                                        : `Expires ${dateLabel(createdInviteLink.expiresAt)} with up to ${createdInviteLink.maxUses} uses.`}
                                </p>
                            </div>
                        </div>
                    </div>
                )}

                <form
                    onSubmit={submit}
                    className="grid gap-3 md:grid-cols-[minmax(0,1fr)_9.5rem_8rem_auto]"
                >
                    <div>
                        <label
                            htmlFor="invite-link-label"
                            className="mb-1.5 block text-xs font-bold text-muted-foreground"
                        >
                            Internal label
                        </label>
                        <Input
                            id="invite-link-label"
                            value={data.label}
                            maxLength={80}
                            onChange={(event) =>
                                setData('label', event.target.value)
                            }
                            placeholder="September cohort"
                            className="h-11 rounded-xl"
                        />
                        <InputError className="mt-2" message={errors.label} />
                    </div>
                    <div>
                        <label
                            htmlFor="invite-link-expiry"
                            className="mb-1.5 block text-xs font-bold text-muted-foreground"
                        >
                            Expires in
                        </label>
                        <select
                            id="invite-link-expiry"
                            value={data.expires_in_days}
                            onChange={(event) =>
                                setData(
                                    'expires_in_days',
                                    Number(event.target.value),
                                )
                            }
                            className="h-11 w-full rounded-xl border bg-background px-3 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        >
                            <option value={1}>1 day</option>
                            <option value={7}>7 days</option>
                            <option value={14}>14 days</option>
                            <option value={30}>30 days</option>
                        </select>
                        <InputError
                            className="mt-2"
                            message={errors.expires_in_days}
                        />
                    </div>
                    <div>
                        <label
                            htmlFor="invite-link-uses"
                            className="mb-1.5 block text-xs font-bold text-muted-foreground"
                        >
                            Max uses
                        </label>
                        <Input
                            id="invite-link-uses"
                            type="number"
                            min={1}
                            max={100}
                            required
                            value={data.max_uses}
                            onChange={(event) =>
                                setData('max_uses', Number(event.target.value))
                            }
                            className="h-11 rounded-xl"
                        />
                        <InputError
                            className="mt-2"
                            message={errors.max_uses}
                        />
                    </div>
                    <Button
                        type="submit"
                        disabled={processing}
                        className="min-h-11 rounded-xl md:mt-[1.38rem]"
                    >
                        <UserRoundPlus className="size-4" aria-hidden="true" />
                        Create
                    </Button>
                </form>
                <InputError className="mt-3" message={errors.invite_link} />

                <div className="mt-6 border-t pt-5">
                    <div className="flex items-center justify-between gap-3">
                        <h3 className="text-sm font-semibold">Active links</h3>
                        <span className="rounded-full bg-secondary px-2.5 py-1 text-[0.68rem] font-extrabold text-muted-foreground">
                            {inviteLinks.length} active
                        </span>
                    </div>
                    {inviteLinks.length === 0 ? (
                        <p className="mt-2 text-sm text-muted-foreground">
                            No shareable links are active.
                        </p>
                    ) : (
                        <ul className="mt-3 grid gap-2 sm:grid-cols-2">
                            {inviteLinks.map((inviteLink) => (
                                <li
                                    key={inviteLink.id}
                                    className="rounded-2xl border bg-muted/35 p-3.5"
                                >
                                    <div className="flex items-start justify-between gap-3">
                                        <div className="min-w-0">
                                            <p className="truncate text-sm font-extrabold">
                                                {inviteLink.label ||
                                                    'Community invite'}
                                            </p>
                                            <p className="mt-1 text-xs leading-5 text-muted-foreground">
                                                {inviteLink.usesCount} of{' '}
                                                {inviteLink.maxUses} used,
                                                expires{' '}
                                                {dateLabel(
                                                    inviteLink.expiresAt,
                                                )}
                                            </p>
                                        </div>
                                        {inviteLink.canRevoke && (
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                aria-label={`Revoke ${inviteLink.label || 'invite link'}`}
                                                className="size-10 shrink-0 rounded-xl text-muted-foreground hover:text-destructive"
                                                onClick={() =>
                                                    router.delete(
                                                        `/spaces/${spaceSlug}/invite-links/${inviteLink.id}`,
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    )
                                                }
                                            >
                                                <X
                                                    className="size-4"
                                                    aria-hidden="true"
                                                />
                                            </Button>
                                        )}
                                    </div>
                                    <div className="mt-3 h-1.5 overflow-hidden rounded-full bg-border/80">
                                        <div
                                            className="h-full rounded-full bg-primary"
                                            style={{
                                                width: `${Math.min(100, (inviteLink.usesCount / inviteLink.maxUses) * 100)}%`,
                                            }}
                                        />
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </div>
        </section>
    );
}

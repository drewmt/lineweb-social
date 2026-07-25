import { Head, Link, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    Check,
    CheckCircle2,
    Clock3,
    Download,
    LogOut,
    MessageSquareText,
    ShieldAlert,
    ShieldCheck,
} from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import DeleteUser from '@/components/delete-user';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { logout } from '@/routes';

type AppealStatus = 'open' | 'reviewing' | 'approved' | 'denied';

type Props = {
    account: {
        handle: string;
        emailVerified: boolean;
        restricted: boolean;
        restrictedAt: string | null;
    };
    appeal: {
        status: AppealStatus;
        statusLabel: string;
        statement: string;
        decisionMessage: string | null;
        submittedAt: string;
        reviewedAt: string | null;
    } | null;
    canAppeal: boolean;
    deletionBlockers: Array<{
        name: string;
        manage_url: null;
    }>;
    status?: string;
};

const dateLabel = (value: string | null) =>
    value
        ? new Intl.DateTimeFormat(undefined, {
              dateStyle: 'medium',
              timeStyle: 'short',
          }).format(new Date(value))
        : 'Not available';

export default function AccountStatus({
    account,
    appeal,
    canAppeal,
    deletionBlockers,
    status,
}: Props) {
    const form = useForm({ statement: '' });
    const restricted = account.restricted;

    const submitAppeal = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post('/account-status/appeals', {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    return (
        <>
            <Head
                title={
                    restricted
                        ? 'Account access restricted'
                        : 'Account in good standing'
                }
            />
            <main className="min-h-svh bg-[linear-gradient(180deg,oklch(0.98_0.012_248),oklch(1_0_0)_34rem)] text-foreground">
                <header className="border-b border-border/70 bg-background/82 backdrop-blur-xl">
                    <div className="mx-auto flex h-16 w-full max-w-6xl items-center justify-between gap-4 px-4 sm:px-6">
                        <Link
                            href={restricted ? '/account-status' : '/feed'}
                            className="social-focus inline-flex items-center gap-2.5 rounded-xl"
                            aria-label="Lineweb Social"
                        >
                            <span className="flex size-9 items-center justify-center rounded-xl bg-foreground text-background">
                                <AppLogoIcon className="size-5" />
                            </span>
                            <span className="text-sm font-black tracking-[-0.025em]">
                                Lineweb Social
                            </span>
                        </Link>
                        <span className="rounded-full border border-border/75 bg-background px-3 py-1.5 text-xs font-bold text-muted-foreground">
                            @{account.handle}
                        </span>
                    </div>
                </header>

                <div className="mx-auto grid w-full max-w-6xl gap-5 px-3 py-5 sm:px-6 sm:py-8 lg:grid-cols-[minmax(0,1fr)_20rem]">
                    <div className="min-w-0 space-y-5">
                        <section
                            className={cn(
                                'overflow-hidden rounded-[1.75rem] border p-5 shadow-[0_16px_50px_-32px_rgba(15,23,42,0.35)] sm:p-8',
                                restricted
                                    ? 'border-coral/45 bg-card'
                                    : 'border-primary/20 bg-card',
                            )}
                        >
                            <div className="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
                                <div className="max-w-2xl">
                                    <span
                                        className={cn(
                                            'flex size-12 items-center justify-center rounded-2xl',
                                            restricted
                                                ? 'bg-coral/18 text-foreground'
                                                : 'bg-mint/35 text-foreground',
                                        )}
                                    >
                                        {restricted ? (
                                            <ShieldAlert
                                                className="size-6"
                                                aria-hidden="true"
                                            />
                                        ) : (
                                            <ShieldCheck
                                                className="size-6"
                                                aria-hidden="true"
                                            />
                                        )}
                                    </span>
                                    <p className="mt-5 text-[0.68rem] font-extrabold tracking-[0.15em] text-muted-foreground uppercase">
                                        Account status
                                    </p>
                                    <h1 className="mt-2 text-3xl font-black tracking-[-0.045em] sm:text-4xl">
                                        {restricted
                                            ? 'Community access is paused.'
                                            : 'Your account is in good standing.'}
                                    </h1>
                                    <p className="mt-3 max-w-xl text-sm leading-6 text-muted-foreground sm:text-base">
                                        {restricted
                                            ? 'Publishing, interactions, private messages, and API access are unavailable while this restriction is active. Your data rights remain available.'
                                            : 'You can publish, join conversations, manage Spaces, and use all community features normally.'}
                                    </p>
                                </div>
                                <span
                                    className={cn(
                                        'inline-flex min-h-10 w-fit items-center gap-2 rounded-full px-3.5 text-xs font-extrabold',
                                        restricted
                                            ? 'bg-coral/16 text-foreground'
                                            : 'bg-mint/32 text-foreground',
                                    )}
                                >
                                    <span
                                        className={cn(
                                            'size-2 rounded-full',
                                            restricted
                                                ? 'bg-coral'
                                                : 'bg-emerald-500',
                                        )}
                                    />
                                    {restricted ? 'Restricted' : 'Active'}
                                </span>
                            </div>
                            {restricted && account.restrictedAt && (
                                <p className="mt-6 border-t border-border/70 pt-4 text-xs font-semibold text-muted-foreground">
                                    Restriction began{' '}
                                    {dateLabel(account.restrictedAt)}
                                </p>
                            )}
                        </section>

                        {status && (
                            <div
                                role="status"
                                className="rounded-2xl border border-primary/20 bg-primary/[0.07] px-4 py-3 text-sm font-bold"
                            >
                                {status}
                            </div>
                        )}

                        {restricted && (
                            <section className="social-card rounded-[1.6rem] p-5 sm:p-7">
                                <div className="flex items-start gap-3">
                                    <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                                        <MessageSquareText
                                            className="size-5"
                                            aria-hidden="true"
                                        />
                                    </span>
                                    <div>
                                        <h2 className="text-xl font-black tracking-[-0.03em]">
                                            Human review
                                        </h2>
                                        <p className="mt-1 text-sm leading-6 text-muted-foreground">
                                            You can submit one appeal for this
                                            restriction. A platform
                                            administrator reviews it; no
                                            automated system makes the final
                                            decision.
                                        </p>
                                    </div>
                                </div>

                                {appeal && <AppealProgress appeal={appeal} />}

                                {canAppeal && (
                                    <form
                                        onSubmit={submitAppeal}
                                        className="mt-6 border-t border-border/70 pt-6"
                                    >
                                        <label
                                            htmlFor="appeal-statement"
                                            className="text-sm font-black"
                                        >
                                            Why should this restriction be
                                            reviewed?
                                        </label>
                                        <p className="mt-1 text-xs leading-5 text-muted-foreground">
                                            Give concise context that helps a
                                            person understand what may have been
                                            missed. Do not include passwords or
                                            private credentials.
                                        </p>
                                        <textarea
                                            id="appeal-statement"
                                            value={form.data.statement}
                                            onChange={(event) =>
                                                form.setData(
                                                    'statement',
                                                    event.target.value,
                                                )
                                            }
                                            rows={6}
                                            minLength={20}
                                            maxLength={2000}
                                            required
                                            autoFocus
                                            placeholder="Explain the context you want the administrator to consider…"
                                            className="social-inset social-focus mt-3 w-full resize-y px-4 py-3 text-sm leading-6"
                                        />
                                        <div className="mt-2 flex items-start justify-between gap-3">
                                            <InputError
                                                message={form.errors.statement}
                                            />
                                            <span className="ml-auto shrink-0 text-xs font-semibold text-muted-foreground">
                                                {form.data.statement.length} /
                                                2,000
                                            </span>
                                        </div>
                                        <Button
                                            type="submit"
                                            disabled={form.processing}
                                            className="mt-4 min-h-11 w-full rounded-xl sm:w-auto"
                                        >
                                            <MessageSquareText className="size-4" />
                                            Submit for human review
                                        </Button>
                                    </form>
                                )}
                            </section>
                        )}

                        {!restricted && appeal && (
                            <section className="social-card rounded-[1.6rem] p-5 sm:p-7">
                                <h2 className="text-lg font-black">
                                    Latest account review
                                </h2>
                                <AppealProgress appeal={appeal} />
                            </section>
                        )}
                    </div>

                    <aside className="space-y-4">
                        <section className="social-card rounded-[1.5rem] p-5">
                            <h2 className="font-black tracking-tight">
                                {restricted
                                    ? 'Your account data'
                                    : 'Account controls'}
                            </h2>
                            <p className="mt-2 text-xs leading-5 text-muted-foreground">
                                {restricted
                                    ? 'A restriction never removes your access to export or delete your personal data.'
                                    : 'Review your profile, privacy, security, and account status at any time.'}
                            </p>

                            <div className="mt-4 space-y-2">
                                {!restricted && (
                                    <>
                                        <Button
                                            asChild
                                            className="min-h-11 w-full rounded-xl"
                                        >
                                            <Link href="/feed">
                                                <ArrowLeft className="size-4" />
                                                Back to community
                                            </Link>
                                        </Button>
                                        <Button
                                            asChild
                                            variant="outline"
                                            className="min-h-11 w-full rounded-xl"
                                        >
                                            <Link href="/settings/profile">
                                                Account settings
                                            </Link>
                                        </Button>
                                    </>
                                )}
                                {account.emailVerified ? (
                                    <Button
                                        asChild
                                        variant="outline"
                                        className="min-h-11 w-full rounded-xl"
                                    >
                                        <a href="/settings/data-export">
                                            <Download className="size-4" />
                                            Download your data
                                        </a>
                                    </Button>
                                ) : (
                                    <p className="rounded-xl bg-secondary p-3 text-xs leading-5 text-muted-foreground">
                                        Verify the account email before
                                        requesting a data export or deletion.
                                    </p>
                                )}
                            </div>
                        </section>

                        {restricted && account.emailVerified && (
                            <section className="social-card rounded-[1.5rem] p-5">
                                <DeleteUser
                                    deletionBlockers={deletionBlockers}
                                />
                            </section>
                        )}

                        <Button
                            asChild
                            variant="ghost"
                            className="min-h-11 w-full rounded-xl"
                        >
                            <Link href={logout()} as="button">
                                <LogOut className="size-4" />
                                Log out
                            </Link>
                        </Button>
                    </aside>
                </div>
            </main>
        </>
    );
}

function AppealProgress({ appeal }: { appeal: NonNullable<Props['appeal']> }) {
    const final = appeal.status === 'approved' || appeal.status === 'denied';
    const steps = [
        {
            label: 'Submitted',
            complete: true,
            current: appeal.status === 'open',
        },
        {
            label: 'Human review',
            complete:
                appeal.status === 'reviewing' ||
                appeal.status === 'approved' ||
                appeal.status === 'denied',
            current: appeal.status === 'reviewing',
        },
        {
            label: 'Decision',
            complete: final,
            current: final,
        },
    ];

    return (
        <div className="mt-6">
            <ol aria-label="Appeal progress" className="grid grid-cols-3 gap-2">
                {steps.map((step, index) => (
                    <li key={step.label} className="min-w-0">
                        <div className="flex items-center">
                            <span
                                className={cn(
                                    'flex size-8 shrink-0 items-center justify-center rounded-full border text-xs font-black',
                                    step.complete
                                        ? 'border-primary bg-primary text-primary-foreground'
                                        : 'border-border bg-background text-muted-foreground',
                                )}
                            >
                                {step.complete ? (
                                    <Check className="size-4" />
                                ) : (
                                    index + 1
                                )}
                            </span>
                            {index < steps.length - 1 && (
                                <span
                                    className={cn(
                                        'h-px min-w-2 flex-1',
                                        steps[index + 1].complete
                                            ? 'bg-primary'
                                            : 'bg-border',
                                    )}
                                />
                            )}
                        </div>
                        <p
                            className={cn(
                                'mt-2 truncate text-[0.68rem] font-extrabold',
                                step.current
                                    ? 'text-foreground'
                                    : 'text-muted-foreground',
                            )}
                        >
                            {step.label}
                        </p>
                    </li>
                ))}
            </ol>

            <div className="mt-5 rounded-2xl border border-border/75 bg-secondary/25 p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <span className="inline-flex min-h-8 items-center gap-2 rounded-full bg-background px-3 text-xs font-extrabold">
                        {final ? (
                            <CheckCircle2 className="size-4 text-primary" />
                        ) : (
                            <Clock3 className="size-4 text-primary" />
                        )}
                        {appeal.statusLabel}
                    </span>
                    <time className="text-xs font-semibold text-muted-foreground">
                        Submitted {dateLabel(appeal.submittedAt)}
                    </time>
                </div>
                <p className="mt-4 text-[0.68rem] font-extrabold tracking-[0.12em] text-muted-foreground uppercase">
                    Your statement
                </p>
                <p className="mt-2 text-sm leading-6 whitespace-pre-wrap">
                    {appeal.statement}
                </p>
                {appeal.decisionMessage && (
                    <div className="mt-4 border-t border-border/70 pt-4">
                        <p className="text-[0.68rem] font-extrabold tracking-[0.12em] text-primary uppercase">
                            Platform response
                        </p>
                        <p className="mt-2 text-sm leading-6">
                            {appeal.decisionMessage}
                        </p>
                        {appeal.reviewedAt && (
                            <time className="mt-2 block text-xs font-semibold text-muted-foreground">
                                Updated {dateLabel(appeal.reviewedAt)}
                            </time>
                        )}
                    </div>
                )}
            </div>
        </div>
    );
}

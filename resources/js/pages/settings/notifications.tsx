import { Head, useForm } from '@inertiajs/react';
import {
    AtSign,
    BellRing,
    CalendarClock,
    Flag,
    LockKeyhole,
    Mail,
    MessageCircle,
} from 'lucide-react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';

type NotificationPreferencesProps = {
    preferences: {
        commentReplies: boolean;
        contentMentions: boolean;
        spaceModeration: boolean;
        emailDigestFrequency: 'off' | 'daily';
    };
    status?: string;
};

export default function NotificationPreferences({
    preferences,
    status,
}: NotificationPreferencesProps) {
    const { data, setData, patch, processing, isDirty, recentlySuccessful } =
        useForm({
            comment_replies: preferences.commentReplies,
            content_mentions: preferences.contentMentions,
            space_moderation: preferences.spaceModeration,
            email_digest_frequency: preferences.emailDigestFrequency,
        });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        patch('/settings/notifications', { preserveScroll: true });
    };

    return (
        <>
            <Head title="Notification settings" />
            <div className="space-y-7">
                <Heading
                    variant="small"
                    title="Notifications"
                    description="Choose what reaches your private inbox and whether you want one quiet email summary each day."
                />

                {status && (
                    <div
                        role="status"
                        className="rounded-2xl border border-primary/20 bg-primary/8 px-4 py-3 text-sm font-bold"
                    >
                        {status}
                    </div>
                )}

                <form onSubmit={submit} className="space-y-5">
                    <div className="space-y-3">
                        <PreferenceRow
                            icon={MessageCircle}
                            title="Replies to your posts"
                            description="Know when another member adds a comment to a post you authored. Your own comments never create an alert."
                            checked={data.comment_replies}
                            onCheckedChange={(checked) =>
                                setData('comment_replies', checked)
                            }
                        />
                        <PreferenceRow
                            icon={AtSign}
                            title="Mentions in posts and comments"
                            description="Know when another member directly mentions your handle. Repeated mentions in the same content create only one alert."
                            checked={data.content_mentions}
                            onCheckedChange={(checked) =>
                                setData('content_mentions', checked)
                            }
                        />
                        <PreferenceRow
                            icon={Flag}
                            title="Space moderation reports"
                            description="Alert you when a post or comment needs review in a Space you own or moderate. Reporter identity is not included."
                            checked={data.space_moderation}
                            onCheckedChange={(checked) =>
                                setData('space_moderation', checked)
                            }
                        />
                    </div>

                    <section
                        aria-labelledby="email-digest-heading"
                        className="overflow-hidden rounded-3xl border border-border/75 bg-background"
                    >
                        <div className="border-b border-border/70 bg-secondary/45 px-4 py-5 sm:px-6">
                            <div className="flex items-start gap-3">
                                <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-background text-primary shadow-sm">
                                    <Mail
                                        className="size-4.5"
                                        aria-hidden="true"
                                    />
                                </span>
                                <div>
                                    <h2
                                        id="email-digest-heading"
                                        className="font-extrabold tracking-tight"
                                    >
                                        Email delivery
                                    </h2>
                                    <p className="mt-1 max-w-2xl text-sm leading-6 text-muted-foreground">
                                        Keep email calm. The digest includes
                                        counts only—never post text, member
                                        names, Space names, or report details.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div className="grid gap-3 p-4 sm:grid-cols-2 sm:p-6">
                            <DigestOption
                                value="off"
                                title="In-app only"
                                description="Nothing leaves your notification inbox."
                                icon={LockKeyhole}
                                checked={data.email_digest_frequency === 'off'}
                                onChange={() =>
                                    setData('email_digest_frequency', 'off')
                                }
                            />
                            <DigestOption
                                value="daily"
                                title="Daily digest"
                                description="One queued email when unread updates are waiting."
                                icon={CalendarClock}
                                checked={
                                    data.email_digest_frequency === 'daily'
                                }
                                onChange={() =>
                                    setData('email_digest_frequency', 'daily')
                                }
                            />
                        </div>

                        <div className="flex items-start gap-2.5 border-t border-border/70 bg-secondary/25 px-4 py-3.5 text-xs leading-5 font-semibold text-muted-foreground sm:px-6">
                            <LockKeyhole
                                className="mt-0.5 size-4 shrink-0 text-primary"
                                aria-hidden="true"
                            />
                            Delivery runs through the community&apos;s queue and
                            rechecks access before counting each update. Turning
                            it on starts from new notifications only.
                        </div>
                    </section>

                    <div className="flex flex-wrap items-center justify-between gap-3 border-t pt-5">
                        <p className="inline-flex items-center gap-2 text-xs font-semibold text-muted-foreground">
                            <BellRing className="size-4" aria-hidden="true" />
                            Changes affect new notifications only.
                        </p>
                        <Button
                            type="submit"
                            disabled={processing || !isDirty}
                            className="min-h-11 rounded-xl"
                        >
                            {recentlySuccessful
                                ? 'Preferences saved'
                                : 'Save preferences'}
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

function DigestOption({
    value,
    title,
    description,
    icon: Icon,
    checked,
    onChange,
}: {
    value: 'off' | 'daily';
    title: string;
    description: string;
    icon: typeof BellRing;
    checked: boolean;
    onChange: () => void;
}) {
    return (
        <label
            className={[
                'relative flex min-h-28 cursor-pointer items-start gap-3 rounded-2xl border p-4 transition-all',
                checked
                    ? 'border-primary/45 bg-primary/[0.055] ring-2 ring-primary/10'
                    : 'border-border/75 hover:border-primary/25 hover:bg-secondary/35',
            ].join(' ')}
        >
            <input
                type="radio"
                name="email_digest_frequency"
                value={value}
                checked={checked}
                onChange={onChange}
                className="peer sr-only"
            />
            <span
                className={[
                    'flex size-10 shrink-0 items-center justify-center rounded-xl transition-colors peer-focus-visible:ring-2 peer-focus-visible:ring-primary peer-focus-visible:ring-offset-2',
                    checked
                        ? 'bg-primary text-primary-foreground'
                        : 'bg-secondary text-muted-foreground',
                ].join(' ')}
            >
                <Icon className="size-4.5" aria-hidden="true" />
            </span>
            <span className="min-w-0">
                <span className="flex items-center gap-2 font-extrabold tracking-tight">
                    {title}
                    {checked && (
                        <span className="rounded-full bg-primary/10 px-2 py-0.5 text-[0.65rem] font-black tracking-[0.08em] text-primary uppercase">
                            Selected
                        </span>
                    )}
                </span>
                <span className="mt-1 block text-sm leading-6 text-muted-foreground">
                    {description}
                </span>
            </span>
        </label>
    );
}

function PreferenceRow({
    icon: Icon,
    title,
    description,
    checked,
    onCheckedChange,
}: {
    icon: typeof BellRing;
    title: string;
    description: string;
    checked: boolean;
    onCheckedChange: (checked: boolean) => void;
}) {
    return (
        <label className="group flex cursor-pointer items-start gap-4 rounded-2xl border border-border/75 bg-background p-4 transition-colors hover:border-primary/20 hover:bg-primary/[0.025] sm:p-5">
            <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-secondary text-primary">
                <Icon className="size-4.5" aria-hidden="true" />
            </span>
            <span className="min-w-0 flex-1">
                <span className="block font-extrabold tracking-tight">
                    {title}
                </span>
                <span className="mt-1 block text-sm leading-6 text-muted-foreground">
                    {description}
                </span>
            </span>
            <Checkbox
                checked={checked}
                onCheckedChange={(value) => onCheckedChange(value === true)}
                aria-label={title}
                className="mt-1 size-5"
            />
        </label>
    );
}

NotificationPreferences.layout = {
    breadcrumbs: [
        {
            title: 'Notification settings',
            href: '/settings/notifications',
        },
    ],
};

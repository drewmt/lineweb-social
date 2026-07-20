import { Head, useForm } from '@inertiajs/react';
import { BellRing, Flag, MessageCircle } from 'lucide-react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';

type NotificationPreferencesProps = {
    preferences: {
        commentReplies: boolean;
        spaceModeration: boolean;
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
            space_moderation: preferences.spaceModeration,
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
                    description="Choose which useful updates reach your in-app inbox. Email delivery is not enabled in this release."
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
                            icon={Flag}
                            title="Space moderation reports"
                            description="Alert you when a post or comment needs review in a Space you own or moderate. Reporter identity is not included."
                            checked={data.space_moderation}
                            onCheckedChange={(checked) =>
                                setData('space_moderation', checked)
                            }
                        />
                    </div>

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

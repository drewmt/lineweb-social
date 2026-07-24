import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    LockKeyhole,
    MessageCircle,
    Send,
    ShieldCheck,
    UsersRound,
} from 'lucide-react';
import { useEffect } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { AvatarMark } from '@/components/social/avatar-mark';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

type Person = {
    name: string;
    handle: string;
};

type ConversationSummary = {
    id: number;
    url: string;
    other: Person;
    lastMessage: {
        body: string | null;
        createdAt: string | null;
        isOwn: boolean;
    };
    unread: boolean;
};

type ThreadMessage = {
    id: number;
    body: string;
    createdAt: string | null;
    isOwn: boolean;
};

type ActiveThread = {
    id: number;
    other: Person;
    canSend: boolean;
    hasUnread: boolean;
    historyLimited: boolean;
    messages: ThreadMessage[];
};

type MessagesProps = {
    conversations: ConversationSummary[];
    active: ActiveThread | null;
    composeTarget: Person | null;
    status?: string;
};

const timestamp = (value: string | null) =>
    value
        ? new Intl.DateTimeFormat(undefined, {
              dateStyle: 'medium',
              timeStyle: 'short',
          }).format(new Date(value))
        : '';

export default function Messages({
    conversations,
    active,
    composeTarget,
    status,
}: MessagesProps) {
    useEffect(() => {
        if (!active?.hasUnread) {
            return;
        }

        router.post(
            `/messages/${active.id}/read`,
            {},
            {
                preserveScroll: true,
                preserveState: true,
                only: ['active', 'conversations', 'messageSummary'],
            },
        );
    }, [active?.hasUnread, active?.id]);

    const showingThread = active !== null || composeTarget !== null;

    return (
        <>
            <Head title="Messages" />
            <main className="social-page max-w-[82rem]">
                <header className="social-page-heading">
                    <p className="social-eyebrow">
                        <MessageCircle className="size-3.5" />
                        Private conversations
                    </p>
                    <h1 className="mt-2 text-3xl font-black tracking-[-0.04em] sm:text-4xl">
                        Messages
                    </h1>
                    <p className="mt-2 max-w-2xl text-sm leading-6 text-muted-foreground sm:text-base">
                        Direct, chronological conversations between two members.
                        No ranking, public counts, or hidden recipients.
                    </p>
                </header>

                {status && (
                    <div
                        role="status"
                        className="mt-5 rounded-2xl border border-primary/20 bg-primary/8 px-4 py-3 text-sm font-bold"
                    >
                        {status}
                    </div>
                )}

                <div className="mt-5 grid min-h-[36rem] overflow-hidden rounded-[1.6rem] border border-border/80 bg-card shadow-[0_24px_70px_-48px_rgba(15,23,42,.48)] lg:grid-cols-[22rem_minmax(0,1fr)]">
                    <section
                        className={cn(
                            'min-w-0 border-border/75 lg:border-r',
                            showingThread && 'hidden lg:block',
                        )}
                        aria-labelledby="conversation-list-title"
                    >
                        <div className="border-b border-border/75 px-5 py-5">
                            <h2
                                id="conversation-list-title"
                                className="font-black tracking-tight"
                            >
                                Inbox
                            </h2>
                            <p className="mt-1 text-xs font-semibold text-muted-foreground">
                                Your 50 most recent conversations
                            </p>
                        </div>

                        {conversations.length === 0 ? (
                            <div className="px-6 py-16 text-center">
                                <span className="mx-auto flex size-14 items-center justify-center rounded-2xl bg-primary/8 text-primary">
                                    <MessageCircle
                                        className="size-6"
                                        aria-hidden="true"
                                    />
                                </span>
                                <h3 className="mt-4 font-black">
                                    No conversations yet.
                                </h3>
                                <p className="mt-2 text-sm leading-6 text-muted-foreground">
                                    Open a visible member profile to start a
                                    private conversation.
                                </p>
                                <Button
                                    asChild
                                    variant="outline"
                                    className="mt-5 rounded-xl"
                                >
                                    <Link href="/people">
                                        <UsersRound
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        Browse people
                                    </Link>
                                </Button>
                            </div>
                        ) : (
                            <ul className="divide-y divide-border/70">
                                {conversations.map((conversation) => (
                                    <ConversationRow
                                        key={conversation.id}
                                        conversation={conversation}
                                        active={active?.id === conversation.id}
                                    />
                                ))}
                            </ul>
                        )}
                    </section>

                    {active ? (
                        <ConversationThread thread={active} />
                    ) : composeTarget ? (
                        <NewConversation target={composeTarget} />
                    ) : (
                        <section className="hidden min-w-0 items-center justify-center p-8 text-center lg:flex">
                            <div className="max-w-sm">
                                <span className="mx-auto flex size-16 items-center justify-center rounded-[1.4rem] bg-secondary text-primary">
                                    <MessageCircle
                                        className="size-7"
                                        aria-hidden="true"
                                    />
                                </span>
                                <h2 className="mt-5 text-xl font-black tracking-tight">
                                    Choose a conversation
                                </h2>
                                <p className="mt-2 text-sm leading-6 text-muted-foreground">
                                    Messages stay between the two participants.
                                    Blocking stops new messages while preserving
                                    existing history.
                                </p>
                            </div>
                        </section>
                    )}
                </div>

                <div className="mt-4 flex items-start gap-3 rounded-2xl border border-border/75 bg-card/70 px-4 py-3 text-xs leading-5 text-muted-foreground">
                    <ShieldCheck
                        className="mt-0.5 size-4 shrink-0 text-primary"
                        aria-hidden="true"
                    />
                    <p>
                        Access is participant-only and protected by account
                        blocks. Messages are not end-to-end encrypted; server
                        operators retain normal database access.
                    </p>
                </div>
            </main>
        </>
    );
}

function ConversationRow({
    conversation,
    active,
}: {
    conversation: ConversationSummary;
    active: boolean;
}) {
    return (
        <li>
            <Link
                href={conversation.url}
                className={cn(
                    'social-focus relative flex min-h-20 items-center gap-3 px-4 py-3 transition-colors hover:bg-secondary/60',
                    active && 'bg-primary/[0.07]',
                )}
            >
                <AvatarMark
                    name={conversation.other.name}
                    className="size-11"
                />
                <span className="min-w-0 flex-1">
                    <span className="flex items-center justify-between gap-3">
                        <span className="truncate text-sm font-extrabold">
                            {conversation.other.name}
                        </span>
                        <span className="shrink-0 text-[0.65rem] font-semibold text-muted-foreground">
                            {timestamp(
                                conversation.lastMessage.createdAt,
                            ).replace(/, \d{1,2}:\d{2}.*/, '')}
                        </span>
                    </span>
                    <span
                        className={cn(
                            'mt-1 block truncate text-xs',
                            conversation.unread
                                ? 'font-extrabold text-foreground'
                                : 'font-medium text-muted-foreground',
                        )}
                    >
                        {conversation.lastMessage.isOwn ? 'You: ' : ''}
                        {conversation.lastMessage.body}
                    </span>
                </span>
                {conversation.unread && (
                    <span
                        className="size-2.5 shrink-0 rounded-full bg-coral"
                        aria-label="Unread messages"
                    />
                )}
            </Link>
        </li>
    );
}

function ConversationThread({ thread }: { thread: ActiveThread }) {
    return (
        <section className="flex min-w-0 flex-col" aria-label="Conversation">
            <div className="flex min-h-[4.75rem] items-center gap-3 border-b border-border/75 px-4 py-3 sm:px-5">
                <Link
                    href="/messages"
                    aria-label="Back to inbox"
                    className="social-focus flex size-10 shrink-0 items-center justify-center rounded-xl hover:bg-secondary lg:hidden"
                >
                    <ArrowLeft className="size-5" aria-hidden="true" />
                </Link>
                <AvatarMark name={thread.other.name} className="size-10" />
                <div className="min-w-0 flex-1">
                    <Link
                        href={`/people/${thread.other.handle}`}
                        className="block truncate font-black hover:underline"
                    >
                        {thread.other.name}
                    </Link>
                    <p className="truncate text-xs font-semibold text-muted-foreground">
                        @{thread.other.handle}
                    </p>
                </div>
                <LockKeyhole
                    className="size-4 text-muted-foreground"
                    aria-label="Participant-only conversation"
                />
            </div>

            <div className="flex min-h-[25rem] flex-1 flex-col justify-end gap-3 overflow-y-auto bg-background/45 px-4 py-5 sm:px-6">
                {thread.historyLimited && (
                    <p className="text-center text-xs font-semibold text-muted-foreground">
                        Showing the 50 most recent messages.
                    </p>
                )}
                {thread.messages.map((message) => (
                    <div
                        key={message.id}
                        className={cn(
                            'flex',
                            message.isOwn ? 'justify-end' : 'justify-start',
                        )}
                    >
                        <div
                            className={cn(
                                'max-w-[82%] rounded-[1.25rem] px-4 py-3 sm:max-w-[70%]',
                                message.isOwn
                                    ? 'rounded-br-md bg-primary text-primary-foreground'
                                    : 'rounded-bl-md border border-border/75 bg-card',
                            )}
                        >
                            <p className="text-sm leading-6 whitespace-pre-wrap">
                                {message.body}
                            </p>
                            <time
                                dateTime={message.createdAt ?? undefined}
                                className={cn(
                                    'mt-1.5 block text-[0.62rem] font-semibold',
                                    message.isOwn
                                        ? 'text-primary-foreground/65'
                                        : 'text-muted-foreground',
                                )}
                            >
                                {timestamp(message.createdAt)}
                            </time>
                        </div>
                    </div>
                ))}
            </div>

            {thread.canSend ? (
                <MessageComposer action={`/messages/${thread.id}`} />
            ) : (
                <div className="border-t border-border/75 px-5 py-4">
                    <p className="rounded-xl bg-secondary px-4 py-3 text-center text-sm font-bold text-muted-foreground">
                        New messages are unavailable because one participant
                        blocked the other.
                    </p>
                </div>
            )}
        </section>
    );
}

function NewConversation({ target }: { target: Person }) {
    return (
        <section
            className="flex min-w-0 flex-col"
            aria-label="New conversation"
        >
            <div className="flex min-h-[4.75rem] items-center gap-3 border-b border-border/75 px-4 py-3 sm:px-5">
                <Link
                    href="/messages"
                    aria-label="Back to inbox"
                    className="social-focus flex size-10 shrink-0 items-center justify-center rounded-xl hover:bg-secondary lg:hidden"
                >
                    <ArrowLeft className="size-5" aria-hidden="true" />
                </Link>
                <AvatarMark name={target.name} className="size-10" />
                <div className="min-w-0">
                    <p className="truncate font-black">{target.name}</p>
                    <p className="truncate text-xs font-semibold text-muted-foreground">
                        New private conversation
                    </p>
                </div>
            </div>
            <div className="flex min-h-[25rem] flex-1 items-center justify-center bg-background/45 p-6 text-center">
                <div className="max-w-sm">
                    <MessageCircle className="mx-auto size-9 text-primary" />
                    <h2 className="mt-4 text-xl font-black">
                        Start with a useful message.
                    </h2>
                    <p className="mt-2 text-sm leading-6 text-muted-foreground">
                        The conversation is created only when you send. A block
                        in either direction prevents delivery.
                    </p>
                </div>
            </div>
            <MessageComposer action={`/messages/new/${target.handle}`} />
        </section>
    );
}

function MessageComposer({ action }: { action: string }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        body: '',
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        post(action, {
            preserveScroll: true,
            onSuccess: () => reset('body'),
        });
    };

    return (
        <form
            onSubmit={submit}
            className="border-t border-border/75 bg-card px-4 py-4 sm:px-5"
        >
            <div className="flex items-end gap-2">
                <label className="min-w-0 flex-1">
                    <span className="sr-only">Message</span>
                    <textarea
                        value={data.body}
                        onChange={(event) =>
                            setData('body', event.target.value)
                        }
                        placeholder="Write a message…"
                        maxLength={2000}
                        rows={2}
                        required
                        className="social-inset social-focus min-h-12 w-full resize-none px-4 py-3 text-sm leading-6"
                    />
                </label>
                <Button
                    type="submit"
                    size="icon"
                    disabled={processing || data.body.trim() === ''}
                    aria-label="Send message"
                    className="size-12 shrink-0 rounded-xl"
                >
                    <Send className="size-4.5" aria-hidden="true" />
                </Button>
            </div>
            <div className="mt-1.5 flex items-center justify-between gap-3 px-1">
                <InputError message={errors.body} />
                <span className="ml-auto text-[0.65rem] font-semibold text-muted-foreground">
                    {data.body.length.toLocaleString()} / 2,000
                </span>
            </div>
        </form>
    );
}

Messages.layout = { breadcrumbs: [{ title: 'Messages', href: '/messages' }] };

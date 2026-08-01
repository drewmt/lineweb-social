import { router } from '@inertiajs/react';
import { BarChart3, Check, Clock3, Plus, X } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';

export type PostPollDraft = {
    question: string;
    options: string[];
    duration: string;
};

export type PostPollSummary = {
    question: string;
    options: Array<{
        id: number;
        label: string;
        votes: number | null;
        percentage: number | null;
    }>;
    totalVotes: number | null;
    viewerOptionId: number | null;
    canVote: boolean;
    isClosed: boolean;
    closesAt: string | null;
    showResults: boolean;
};

const emptyPoll = (): PostPollDraft => ({
    question: '',
    options: ['', ''],
    duration: '7',
});

const closingLabel = (poll: PostPollSummary) => {
    if (poll.isClosed) {
        return 'Poll closed';
    }

    if (!poll.closesAt) {
        return 'No closing date';
    }

    return `Closes ${new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(poll.closesAt))}`;
};

export function PostPollEditor({
    value,
    onChange,
    errors = {},
    compact = false,
}: {
    value: PostPollDraft | null;
    onChange: (value: PostPollDraft | null) => void;
    errors?: Record<string, string | undefined>;
    compact?: boolean;
}) {
    if (value === null) {
        return (
            <div
                className={
                    compact
                        ? 'border-t border-border/65 px-4 py-3 sm:px-5'
                        : 'border-t border-border/65 px-4 py-4 sm:px-6'
                }
            >
                <button
                    type="button"
                    onClick={() => onChange(emptyPoll())}
                    className="social-focus inline-flex min-h-11 items-center gap-2 rounded-xl px-3 text-sm font-extrabold text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground"
                >
                    <BarChart3
                        className="size-4 text-primary"
                        aria-hidden="true"
                    />
                    Add a poll
                </button>
            </div>
        );
    }

    const update = (partial: Partial<PostPollDraft>) => {
        onChange({ ...value, ...partial });
    };

    const updateOption = (index: number, label: string) => {
        update({
            options: value.options.map((option, optionIndex) =>
                optionIndex === index ? label : option,
            ),
        });
    };

    const removeOption = (index: number) => {
        if (value.options.length <= 2) {
            return;
        }

        update({
            options: value.options.filter(
                (_, optionIndex) => optionIndex !== index,
            ),
        });
    };

    return (
        <section
            className={`border-t border-border/65 bg-secondary/18 ${compact ? 'px-4 py-4 sm:px-5' : 'px-4 py-5 sm:px-6'}`}
            aria-labelledby="post-poll-heading"
        >
            <div className="flex items-start justify-between gap-3">
                <div>
                    <p
                        id="post-poll-heading"
                        className="flex items-center gap-2 text-sm font-black"
                    >
                        <BarChart3
                            className="size-4.5 text-primary"
                            aria-hidden="true"
                        />
                        Community poll
                    </p>
                    <p className="mt-1 text-xs leading-5 font-medium text-muted-foreground">
                        Members can choose one answer and change it while the
                        poll is open.
                    </p>
                </div>
                <button
                    type="button"
                    onClick={() => onChange(null)}
                    className="social-focus flex size-10 shrink-0 items-center justify-center rounded-xl text-muted-foreground transition-colors hover:bg-background hover:text-foreground"
                    aria-label="Remove poll"
                >
                    <X className="size-4" aria-hidden="true" />
                </button>
            </div>

            <label className="mt-4 block">
                <span className="text-xs font-extrabold tracking-[0.08em] text-muted-foreground uppercase">
                    Question
                </span>
                <input
                    value={value.question}
                    onChange={(event) =>
                        update({ question: event.target.value })
                    }
                    maxLength={180}
                    placeholder="What should the community decide?"
                    className="social-inset social-focus mt-2 h-12 w-full px-3.5 text-sm font-semibold"
                />
            </label>
            <InputError className="mt-2" message={errors.poll_question} />

            <div className="mt-4 space-y-2">
                <div className="flex items-center justify-between gap-3">
                    <span className="text-xs font-extrabold tracking-[0.08em] text-muted-foreground uppercase">
                        Answers
                    </span>
                    <span className="text-xs font-semibold text-muted-foreground">
                        {value.options.length} / 4
                    </span>
                </div>
                {value.options.map((option, index) => (
                    <div key={index} className="flex items-center gap-2">
                        <span className="flex size-9 shrink-0 items-center justify-center rounded-full border border-border/75 bg-background text-xs font-black text-muted-foreground">
                            {index + 1}
                        </span>
                        <input
                            value={option}
                            onChange={(event) =>
                                updateOption(index, event.target.value)
                            }
                            maxLength={100}
                            placeholder={`Answer ${index + 1}`}
                            className="social-inset social-focus h-11 min-w-0 flex-1 px-3 text-sm font-semibold"
                        />
                        {value.options.length > 2 && (
                            <button
                                type="button"
                                onClick={() => removeOption(index)}
                                className="social-focus flex size-10 shrink-0 items-center justify-center rounded-xl text-muted-foreground transition-colors hover:bg-background hover:text-foreground"
                                aria-label={`Remove answer ${index + 1}`}
                            >
                                <X className="size-4" aria-hidden="true" />
                            </button>
                        )}
                    </div>
                ))}
                {value.options.length < 4 && (
                    <button
                        type="button"
                        onClick={() =>
                            update({ options: [...value.options, ''] })
                        }
                        className="social-focus inline-flex min-h-10 items-center gap-1.5 rounded-lg px-2.5 text-xs font-extrabold text-primary transition-colors hover:bg-primary/8"
                    >
                        <Plus className="size-3.5" aria-hidden="true" />
                        Add answer
                    </button>
                )}
                <InputError message={errors.poll_options} />
            </div>

            <label className="mt-4 block max-w-xs">
                <span className="flex items-center gap-1.5 text-xs font-extrabold tracking-[0.08em] text-muted-foreground uppercase">
                    <Clock3 className="size-3.5" aria-hidden="true" />
                    Closes
                </span>
                <select
                    value={value.duration}
                    onChange={(event) =>
                        update({ duration: event.target.value })
                    }
                    className="social-inset social-focus mt-2 h-11 w-full px-3 text-sm font-semibold"
                >
                    <option value="">No closing date</option>
                    <option value="1">In 1 day</option>
                    <option value="3">In 3 days</option>
                    <option value="7">In 7 days</option>
                </select>
            </label>
            <InputError className="mt-2" message={errors.poll_duration} />
        </section>
    );
}

export function PostPoll({
    postId,
    poll,
    className = '',
}: {
    postId: number;
    poll: PostPollSummary | null;
    className?: string;
}) {
    const [processing, setProcessing] = useState(false);

    if (poll === null) {
        return null;
    }

    const vote = (optionId: number) => {
        if (!poll.canVote || processing) {
            return;
        }

        router.put(
            `/posts/${postId}/poll-vote`,
            { option_id: optionId },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <section
            className={`mt-4 overflow-hidden rounded-[1.15rem] border border-border/75 bg-secondary/22 ${className}`}
            aria-label="Community poll"
        >
            <div className="flex items-center justify-between gap-3 border-b border-border/60 px-3.5 py-2.5 sm:px-4">
                <span className="inline-flex items-center gap-1.5 text-[0.68rem] font-extrabold tracking-[0.1em] text-primary uppercase">
                    <BarChart3 className="size-3.5" aria-hidden="true" />
                    Community poll
                </span>
                <span className="text-xs font-semibold text-muted-foreground">
                    {closingLabel(poll)}
                </span>
            </div>
            <div className="p-3.5 sm:p-4">
                <h3 className="text-[0.98rem] leading-6 font-extrabold tracking-[-0.01em]">
                    {poll.question}
                </h3>
                <div className="mt-3 space-y-2">
                    {poll.options.map((option) => {
                        const selected = poll.viewerOptionId === option.id;
                        const optionLabel =
                            poll.showResults && option.percentage !== null
                                ? `${option.label}, ${option.percentage}%`
                                : option.label;

                        return (
                            <button
                                key={option.id}
                                type="button"
                                onClick={() => vote(option.id)}
                                disabled={!poll.canVote || processing}
                                aria-pressed={selected}
                                aria-label={optionLabel}
                                className={`social-focus relative flex min-h-12 w-full overflow-hidden rounded-xl border px-3.5 py-2.5 text-left text-sm font-bold transition-colors disabled:cursor-default ${
                                    selected
                                        ? 'border-primary/40 bg-primary/8 text-foreground'
                                        : 'border-border/75 bg-background/72 text-foreground hover:border-primary/25 hover:bg-background'
                                }`}
                            >
                                {poll.showResults &&
                                    option.percentage !== null && (
                                        <span
                                            className={`absolute inset-y-0 left-0 transition-[width] duration-300 ${
                                                selected
                                                    ? 'bg-primary/14'
                                                    : 'bg-secondary/75'
                                            }`}
                                            style={{
                                                width: `${option.percentage}%`,
                                            }}
                                            aria-hidden="true"
                                        />
                                    )}
                                <span className="relative flex min-w-0 flex-1 items-center gap-2">
                                    <span
                                        className={`flex size-5 shrink-0 items-center justify-center rounded-full border ${
                                            selected
                                                ? 'border-primary bg-primary text-primary-foreground'
                                                : 'border-muted-foreground/40 bg-background text-transparent'
                                        }`}
                                    >
                                        <Check
                                            className="size-3"
                                            aria-hidden="true"
                                        />
                                    </span>
                                    <span className="min-w-0 flex-1 break-words">
                                        {option.label}
                                    </span>
                                </span>
                                {poll.showResults &&
                                    option.percentage !== null && (
                                        <span className="relative ml-3 shrink-0 text-xs font-black text-muted-foreground tabular-nums">
                                            {option.percentage}%
                                        </span>
                                    )}
                            </button>
                        );
                    })}
                </div>
                <div className="mt-3 flex flex-wrap items-center justify-between gap-x-3 gap-y-1 text-xs font-semibold text-muted-foreground">
                    {poll.showResults && poll.totalVotes !== null ? (
                        <span>
                            {poll.totalVotes.toLocaleString()}{' '}
                            {poll.totalVotes === 1 ? 'vote' : 'votes'}
                        </span>
                    ) : (
                        <span>Vote to view results.</span>
                    )}
                    {poll.canVote && !poll.isClosed && (
                        <span>
                            {poll.viewerOptionId === null
                                ? 'Choose one answer.'
                                : 'You can still change your vote.'}
                        </span>
                    )}
                </div>
            </div>
        </section>
    );
}

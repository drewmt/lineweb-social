import { router } from '@inertiajs/react';
import { Heart, Lightbulb, PartyPopper } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useState } from 'react';

export type ReactionType = {
    value: string;
    label: string;
};

export type ReactionSummary = {
    total: number;
    counts: Record<string, number>;
    viewerType: string | null;
    canReact: boolean;
};

const reactionStyles: Record<
    string,
    { icon: LucideIcon; active: string; idle: string }
> = {
    like: {
        icon: Heart,
        active: 'border-rose-500/25 bg-rose-500/10 text-rose-600',
        idle: 'hover:bg-rose-500/8 hover:text-rose-600',
    },
    celebrate: {
        icon: PartyPopper,
        active: 'border-amber-500/30 bg-amber-500/12 text-amber-700',
        idle: 'hover:bg-amber-500/10 hover:text-amber-700',
    },
    insightful: {
        icon: Lightbulb,
        active: 'border-sky-500/25 bg-sky-500/10 text-sky-700',
        idle: 'hover:bg-sky-500/8 hover:text-sky-700',
    },
};

export function PostReactions({
    postId,
    reactions,
    reactionTypes,
}: {
    postId: number;
    reactions: ReactionSummary;
    reactionTypes: ReactionType[];
}) {
    const [processing, setProcessing] = useState(false);

    if (!reactions.canReact && reactions.total === 0) {
        return null;
    }

    const react = (type: string) => {
        if (!reactions.canReact || processing) {
            return;
        }

        const options = {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
        };

        if (reactions.viewerType === type) {
            router.delete(`/posts/${postId}/reaction`, options);

            return;
        }

        router.put(`/posts/${postId}/reaction`, { type }, options);
    };

    return (
        <div
            className="mt-4 flex flex-wrap items-center gap-1.5 border-t border-border/70 pt-3"
            aria-label="Post reactions"
        >
            {reactionTypes.map((type) => {
                const selected = reactions.viewerType === type.value;
                const style = reactionStyles[type.value];

                if (!style) {
                    return null;
                }

                const Icon = style.icon;
                const count = reactions.counts[type.value] ?? 0;

                return (
                    <button
                        key={type.value}
                        type="button"
                        onClick={() => react(type.value)}
                        disabled={!reactions.canReact || processing}
                        aria-pressed={selected}
                        aria-label={`${type.label}${count > 0 ? `, ${count}` : ''}`}
                        className={`social-focus inline-flex min-h-10 items-center gap-1.5 rounded-xl border px-3 text-xs font-extrabold transition-colors disabled:cursor-not-allowed disabled:opacity-55 ${
                            selected
                                ? style.active
                                : `border-transparent text-muted-foreground ${style.idle}`
                        }`}
                    >
                        <Icon
                            className={`size-4 ${selected && type.value === 'like' ? 'fill-current' : ''}`}
                            aria-hidden="true"
                        />
                        <span>{type.label}</span>
                        {count > 0 && (
                            <span
                                className={`tabular-nums ${selected ? 'text-current' : 'text-foreground/75'}`}
                            >
                                {count.toLocaleString()}
                            </span>
                        )}
                    </button>
                );
            })}
            {reactions.total > 0 && (
                <span className="ml-auto px-2 text-[0.7rem] font-semibold text-muted-foreground">
                    {reactions.total.toLocaleString()}{' '}
                    {reactions.total === 1 ? 'reaction' : 'reactions'}
                </span>
            )}
        </div>
    );
}

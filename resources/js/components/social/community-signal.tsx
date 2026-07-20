import { cn } from '@/lib/utils';

export function CommunitySignal({ className }: { className?: string }) {
    return (
        <span
            className={cn('relative inline-flex h-3 w-6 shrink-0', className)}
            aria-hidden="true"
        >
            <span className="absolute top-1/2 right-1 left-1 h-px -translate-y-1/2 bg-current opacity-30" />
            <span className="absolute top-1/2 left-0 size-2 -translate-y-1/2 rounded-full bg-current" />
            <span className="absolute top-1/2 left-1/2 size-2.5 -translate-x-1/2 -translate-y-1/2 rounded-full bg-mint ring-2 ring-background" />
            <span className="absolute top-1/2 right-0 size-2 -translate-y-1/2 rounded-full bg-current" />
        </span>
    );
}

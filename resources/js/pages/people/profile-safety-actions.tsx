import { router } from '@inertiajs/react';
import { EyeOff, ShieldX, Volume2 } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';

export function ProfileSafetyActions({
    handle,
    isMuted,
}: {
    handle: string;
    isMuted: boolean;
}) {
    const [confirmingBlock, setConfirmingBlock] = useState(false);

    const toggleMute = () => {
        const url = `/people/${encodeURIComponent(handle)}/mute`;

        if (isMuted) {
            router.delete(url, { preserveScroll: true });
        } else {
            router.post(url, {}, { preserveScroll: true });
        }
    };

    const block = () => {
        router.post(`/people/${encodeURIComponent(handle)}/block`);
    };

    return (
        <div className="flex flex-col items-start gap-2 sm:items-end">
            <div className="flex flex-wrap gap-2">
                <Button
                    type="button"
                    variant="outline"
                    className="bg-card"
                    onClick={toggleMute}
                >
                    {isMuted ? (
                        <Volume2 className="size-4" aria-hidden="true" />
                    ) : (
                        <EyeOff className="size-4" aria-hidden="true" />
                    )}
                    {isMuted ? 'Unmute' : 'Mute'}
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    className="bg-card/80 text-destructive hover:text-destructive"
                    onClick={() => setConfirmingBlock(true)}
                >
                    <ShieldX className="size-4" aria-hidden="true" />
                    Block
                </Button>
            </div>
            {confirmingBlock && (
                <div className="max-w-xs rounded-2xl border border-destructive/20 bg-card p-3 text-left text-sm shadow-lg">
                    <p className="font-bold">Block this person?</p>
                    <p className="mt-1 text-xs leading-5 text-muted-foreground">
                        You will disappear from each other’s profiles,
                        discovery, and feeds. You can undo this in Safety
                        settings.
                    </p>
                    <div className="mt-3 flex gap-2">
                        <Button
                            type="button"
                            size="sm"
                            variant="destructive"
                            onClick={block}
                        >
                            Confirm block
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            onClick={() => setConfirmingBlock(false)}
                        >
                            Cancel
                        </Button>
                    </div>
                </div>
            )}
        </div>
    );
}

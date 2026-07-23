import { router } from '@inertiajs/react';
import { UserCheck, UserPlus } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';

export function ProfileFollowButton({
    handle,
    isFollowing,
}: {
    handle: string;
    isFollowing: boolean;
}) {
    const [processing, setProcessing] = useState(false);
    const endpoint = `/people/${encodeURIComponent(handle)}/follow`;

    const toggle = () => {
        const options = {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
        };

        if (isFollowing) {
            router.delete(endpoint, options);

            return;
        }

        router.put(endpoint, {}, options);
    };

    return (
        <Button
            type="button"
            onClick={toggle}
            disabled={processing}
            variant={isFollowing ? 'outline' : 'default'}
            className="min-w-28 rounded-full px-5"
            aria-pressed={isFollowing}
        >
            {isFollowing ? (
                <UserCheck className="size-4" aria-hidden="true" />
            ) : (
                <UserPlus className="size-4" aria-hidden="true" />
            )}
            {processing ? 'Updating…' : isFollowing ? 'Following' : 'Follow'}
        </Button>
    );
}

import { router } from '@inertiajs/react';
import { Star } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';

export function FavoriteButton({
    endpoint,
    isFavorite,
}: {
    endpoint: string;
    isFavorite: boolean;
}) {
    const [processing, setProcessing] = useState(false);

    function toggle() {
        const options = {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
        };

        if (isFavorite) {
            router.delete(endpoint, options);
        } else {
            router.put(endpoint, {}, options);
        }
    }

    return (
        <Button
            type="button"
            variant={isFavorite ? 'secondary' : 'outline'}
            className="min-h-11 rounded-full"
            aria-pressed={isFavorite}
            disabled={processing}
            onClick={toggle}
        >
            <Star
                className={isFavorite ? 'size-4 fill-current' : 'size-4'}
                aria-hidden="true"
            />
            Favorite
        </Button>
    );
}

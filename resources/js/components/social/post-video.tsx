import { useEffect, useRef } from 'react';
import { cn } from '@/lib/utils';
import { shouldAutoPlayVideo } from './video-playback-policy';

export type PostVideoData = {
    url: string;
    posterUrl: string;
    description: string;
    durationMs: number;
    width: number;
    height: number;
};

export function PostVideo({
    video,
    className,
    active = false,
}: {
    video: PostVideoData;
    className?: string;
    active?: boolean;
}) {
    const player = useRef<HTMLVideoElement>(null);

    useEffect(() => {
        const element = player.current;

        if (!element) {
            return;
        }

        const motion = window.matchMedia('(prefers-reduced-motion: reduce)');
        const syncPlayback = () => {
            if (
                shouldAutoPlayVideo(
                    active,
                    motion.matches,
                    document.visibilityState === 'visible',
                )
            ) {
                element.muted = true;
                void element.play().catch(() => {
                    // Browser autoplay policy may still require a tap.
                });
            } else {
                element.pause();
            }
        };

        syncPlayback();
        motion.addEventListener('change', syncPlayback);
        document.addEventListener('visibilitychange', syncPlayback);

        return () => {
            motion.removeEventListener('change', syncPlayback);
            document.removeEventListener('visibilitychange', syncPlayback);
            element.pause();
        };
    }, [active, video.url]);

    return (
        <div
            className={cn(
                'overflow-hidden rounded-[1.25rem] border border-border/60 bg-slate-950',
                className,
            )}
        >
            <video
                ref={player}
                src={video.url}
                poster={video.posterUrl}
                width={video.width}
                height={video.height}
                preload={active ? 'metadata' : 'none'}
                controls
                playsInline
                muted={active}
                aria-label={video.description}
                className="mx-auto block max-h-[min(72vh,48rem)] w-full object-contain"
            >
                Your browser does not support video playback.
            </video>
            <p className="sr-only">{video.description}</p>
        </div>
    );
}

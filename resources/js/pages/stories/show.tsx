import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Clock3, LockKeyhole, Trash2 } from 'lucide-react';
import { AvatarMark } from '@/components/social/avatar-mark';
import { Button } from '@/components/ui/button';

type Story = {
    id: number;
    url: string;
    body: string | null;
    background: 'ink' | 'ocean' | 'violet' | 'sunset' | 'mint';
    image: {
        url: string;
        alt: string;
        width: number;
        height: number;
    } | null;
    createdAt: string;
    expiresAt: string;
    canDelete: boolean;
    ownedByViewer: boolean;
    author: { name: string; handle: string; profileVisible: boolean };
    space: { name: string; slug: string };
};

const backgroundClasses: Record<Story['background'], string> = {
    ink: 'from-slate-950 via-slate-800 to-slate-950',
    ocean: 'from-cyan-500 via-blue-600 to-indigo-800',
    violet: 'from-fuchsia-500 via-violet-600 to-indigo-800',
    sunset: 'from-amber-400 via-orange-500 to-rose-600',
    mint: 'from-emerald-300 via-teal-500 to-cyan-700',
};

const expiresLabel = (value: string) => {
    const hours = Math.max(
        1,
        Math.ceil((new Date(value).getTime() - Date.now()) / 3_600_000),
    );

    return `${hours}h left`;
};

export default function ShowStory({ story }: { story: Story }) {
    const deleteStory = () => {
        const action = story.ownedByViewer ? 'Delete' : 'Remove';

        if (
            window.confirm(`${action} this Story now? This cannot be undone.`)
        ) {
            router.delete(`/stories/${story.id}`);
        }
    };

    return (
        <>
            <Head title={`${story.author.name}'s Story`} />
            <main className="social-page">
                <div className="mx-auto grid max-w-4xl items-center gap-6 lg:grid-cols-[minmax(0,25rem)_minmax(16rem,1fr)]">
                    <div
                        className={`relative mx-auto aspect-[9/16] max-h-[76vh] w-full max-w-[25rem] overflow-hidden rounded-[2rem] bg-gradient-to-br shadow-[0_32px_80px_-38px_rgba(15,23,42,.85)] ${backgroundClasses[story.background]}`}
                    >
                        {story.image && (
                            <img
                                src={story.image.url}
                                alt={story.image.alt}
                                width={story.image.width}
                                height={story.image.height}
                                className="absolute inset-0 size-full object-cover"
                            />
                        )}
                        <div className="absolute inset-x-0 top-0 h-32 bg-gradient-to-b from-black/60 to-transparent" />
                        <div className="absolute top-5 right-5 left-5 flex items-center gap-3 text-white">
                            <AvatarMark
                                name={story.author.name}
                                className="size-10 ring-2 ring-white/80"
                            />
                            <div className="min-w-0 flex-1">
                                <p className="truncate text-sm font-black">
                                    {story.author.name}
                                </p>
                                <p className="truncate text-xs font-semibold text-white/75">
                                    {story.space.name}
                                </p>
                            </div>
                            <span className="rounded-full bg-black/30 px-2.5 py-1 text-xs font-extrabold backdrop-blur-sm">
                                {expiresLabel(story.expiresAt)}
                            </span>
                        </div>
                        {story.body && (
                            <div className="absolute inset-x-5 bottom-7 rounded-2xl bg-black/35 px-4 py-3 text-center text-lg leading-7 font-black whitespace-pre-wrap text-white backdrop-blur-sm">
                                {story.body}
                            </div>
                        )}
                    </div>

                    <aside className="social-card rounded-[1.75rem] p-5 sm:p-6">
                        <Link
                            href="/feed"
                            className="social-focus inline-flex min-h-10 items-center gap-2 rounded-xl px-3 text-sm font-extrabold text-muted-foreground hover:bg-secondary hover:text-foreground"
                        >
                            <ArrowLeft className="size-4" aria-hidden="true" />
                            Back to feed
                        </Link>
                        <p className="mt-6 text-[0.68rem] font-extrabold tracking-[0.16em] text-primary uppercase">
                            Community Story
                        </p>
                        <h1 className="mt-1 text-2xl font-black tracking-[-0.035em]">
                            A moment from {story.space.name}
                        </h1>
                        <div className="mt-5 space-y-3 text-sm leading-6 text-muted-foreground">
                            <p className="flex gap-3">
                                <Clock3
                                    className="mt-1 size-4 shrink-0 text-primary"
                                    aria-hidden="true"
                                />
                                This Story and its media are permanently removed
                                when the 24-hour window ends.
                            </p>
                            <p className="flex gap-3">
                                <LockKeyhole
                                    className="mt-1 size-4 shrink-0 text-primary"
                                    aria-hidden="true"
                                />
                                Space visibility rules are enforced on this page
                                and on the private image response.
                            </p>
                        </div>
                        <Button
                            asChild
                            variant="outline"
                            className="mt-6 w-full rounded-2xl"
                        >
                            <Link href={`/spaces/${story.space.slug}`}>
                                Open {story.space.name}
                            </Link>
                        </Button>
                        {story.canDelete && (
                            <Button
                                type="button"
                                variant="destructive"
                                onClick={deleteStory}
                                className="mt-2 w-full rounded-2xl"
                            >
                                <Trash2 className="size-4" aria-hidden="true" />
                                {story.ownedByViewer
                                    ? 'Delete Story'
                                    : 'Remove Story'}
                            </Button>
                        )}
                    </aside>
                </div>
            </main>
        </>
    );
}

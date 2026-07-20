import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    ArrowRight,
    Check,
    Globe2,
    LockKeyhole,
    Plus,
    Search,
    ShieldCheck,
    UsersRound,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { CommunitySignal } from '@/components/social/community-signal';
import { SpaceCover } from '@/components/social/space-cover';
import { Button } from '@/components/ui/button';

type Space = {
    name: string;
    slug: string;
    description: string | null;
    visibility: 'public' | 'private' | 'hidden';
    memberCount: number;
    isMember: boolean;
    isOwner: boolean;
};

type SpacesProps = { spaces: Space[]; status?: string };

const visibilityCopy = {
    public: {
        label: 'Public',
        help: 'Everyone can discover and join.',
        icon: Globe2,
    },
    private: {
        label: 'Private',
        help: 'Visible to members; joining requires an invitation.',
        icon: LockKeyhole,
    },
    hidden: {
        label: 'Hidden',
        help: 'Only members know that it exists.',
        icon: ShieldCheck,
    },
} as const;

function SpaceCard({
    space,
    onJoin,
}: {
    space: Space;
    onJoin: (slug: string) => void;
}) {
    const visibility = visibilityCopy[space.visibility];
    const VisibilityIcon = visibility.icon;

    return (
        <article className="social-card social-card-interactive group flex min-h-64 flex-col overflow-hidden rounded-[1.35rem]">
            <div className="relative h-28 overflow-hidden bg-secondary/70">
                <SpaceCover
                    seed={space.slug}
                    className="absolute inset-0 transition-transform duration-500 group-hover:scale-[1.035]"
                />
                <span className="absolute top-3 right-3 inline-flex items-center gap-1.5 rounded-full bg-white/88 px-2.5 py-1 text-[0.68rem] font-extrabold text-slate-800 shadow-sm backdrop-blur">
                    <VisibilityIcon className="size-3" aria-hidden="true" />
                    {visibility.label}
                </span>
            </div>
            <div className="flex flex-1 flex-col p-4 sm:p-5">
                <h3 className="text-lg font-black tracking-[-0.025em]">
                    {space.name}
                </h3>
                <p className="mt-1.5 line-clamp-2 text-sm leading-6 text-muted-foreground">
                    {space.description ??
                        'A focused home for useful conversations and shared interests.'}
                </p>
                <div className="mt-auto flex items-center justify-between gap-3 pt-5">
                    <span className="flex items-center gap-1.5 text-xs font-bold text-muted-foreground">
                        <UsersRound className="size-3.5" aria-hidden="true" />
                        {space.memberCount.toLocaleString()}{' '}
                        {space.memberCount === 1 ? 'member' : 'members'}
                    </span>
                    {space.isMember ? (
                        <Link
                            href={`/spaces/${space.slug}`}
                            className="social-focus inline-flex min-h-9 items-center gap-1.5 rounded-xl bg-secondary/55 px-3 py-1 text-sm font-extrabold text-primary transition-colors hover:bg-primary/10"
                        >
                            {space.isOwner ? 'Manage' : 'Open'}{' '}
                            <ArrowRight className="size-4" aria-hidden="true" />
                        </Link>
                    ) : space.visibility === 'public' ? (
                        <Button
                            type="button"
                            size="sm"
                            className="px-4"
                            onClick={() => onJoin(space.slug)}
                        >
                            <Plus className="size-4" aria-hidden="true" /> Join
                        </Button>
                    ) : (
                        <span className="text-xs font-bold text-muted-foreground">
                            Invite only
                        </span>
                    )}
                </div>
            </div>
        </article>
    );
}

function CreateSpace() {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        description: '',
        visibility: 'public',
    });

    const createSpace = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        post('/spaces', { onSuccess: () => reset() });
    };

    return (
        <aside
            className="social-card rounded-[1.35rem] p-4 sm:p-5 xl:sticky xl:top-6"
            aria-labelledby="create-space-title"
        >
            <div className="flex items-start gap-3">
                <span className="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-primary text-primary-foreground shadow-[0_14px_26px_-16px_rgba(37,74,220,.85)]">
                    <Plus className="size-5" aria-hidden="true" />
                </span>
                <div>
                    <p className="text-[0.67rem] font-extrabold tracking-[0.14em] text-primary uppercase">
                        Build your circle
                    </p>
                    <h2
                        id="create-space-title"
                        className="mt-0.5 text-xl font-black tracking-tight"
                    >
                        Create a space
                    </h2>
                </div>
            </div>
            <p className="mt-3 text-sm leading-6 text-muted-foreground">
                Give people a clear purpose, a shared identity, and a place to
                belong.
            </p>

            <form onSubmit={createSpace} className="mt-5 space-y-4">
                <label className="block">
                    <span className="text-sm font-extrabold">Name</span>
                    <input
                        value={data.name}
                        onChange={(event) =>
                            setData('name', event.target.value)
                        }
                        required
                        maxLength={120}
                        placeholder="e.g. Indie Makers"
                        className="social-focus mt-2 h-11 w-full rounded-xl border bg-background px-3.5 text-sm placeholder:text-muted-foreground/70"
                    />
                    <InputError className="mt-2" message={errors.name} />
                </label>
                <label className="block">
                    <span className="text-sm font-extrabold">Purpose</span>
                    <textarea
                        value={data.description}
                        onChange={(event) =>
                            setData('description', event.target.value)
                        }
                        maxLength={500}
                        rows={3}
                        placeholder="What brings this community together?"
                        className="social-focus mt-2 w-full resize-y rounded-xl border bg-background px-3.5 py-3 text-sm leading-6 placeholder:text-muted-foreground/70"
                    />
                    <div className="mt-1.5 flex justify-between gap-3">
                        <InputError message={errors.description} />
                        <span className="ml-auto text-xs text-muted-foreground">
                            {data.description.length}/500
                        </span>
                    </div>
                </label>
                <fieldset>
                    <legend className="text-sm font-extrabold">
                        Visibility
                    </legend>
                    <div className="mt-2 grid gap-2">
                        {Object.entries(visibilityCopy).map(
                            ([value, option]) => {
                                const Icon = option.icon;
                                const active = data.visibility === value;

                                return (
                                    <label
                                        key={value}
                                        className={`social-focus flex cursor-pointer items-center gap-3 rounded-xl border p-3 transition-colors ${active ? 'border-primary/35 bg-primary/8' : 'hover:bg-secondary/60'}`}
                                    >
                                        <input
                                            type="radio"
                                            name="visibility"
                                            value={value}
                                            checked={active}
                                            onChange={(event) =>
                                                setData(
                                                    'visibility',
                                                    event.target.value,
                                                )
                                            }
                                            className="sr-only"
                                        />
                                        <span
                                            className={`flex size-8 shrink-0 items-center justify-center rounded-xl ${active ? 'bg-primary text-primary-foreground' : 'bg-secondary text-muted-foreground'}`}
                                        >
                                            {active ? (
                                                <Check
                                                    className="size-4"
                                                    aria-hidden="true"
                                                />
                                            ) : (
                                                <Icon
                                                    className="size-4"
                                                    aria-hidden="true"
                                                />
                                            )}
                                        </span>
                                        <span>
                                            <span className="block text-sm font-extrabold">
                                                {option.label}
                                            </span>
                                            <span className="block text-[0.7rem] leading-4 text-muted-foreground">
                                                {option.help}
                                            </span>
                                        </span>
                                    </label>
                                );
                            },
                        )}
                    </div>
                    <InputError className="mt-2" message={errors.visibility} />
                </fieldset>
                <Button
                    type="submit"
                    disabled={processing || data.name.trim() === ''}
                    className="w-full"
                >
                    Create space{' '}
                    <ArrowRight className="size-4" aria-hidden="true" />
                </Button>
            </form>
        </aside>
    );
}

export default function Spaces({ spaces, status }: SpacesProps) {
    const [query, setQuery] = useState('');
    const [filter, setFilter] = useState<'all' | 'joined'>('all');
    const visibleSpaces = useMemo(() => {
        const normalizedQuery = query.trim().toLocaleLowerCase();

        return spaces.filter((space) => {
            const matchesFilter = filter === 'all' || space.isMember;
            const matchesQuery =
                normalizedQuery === '' ||
                space.name.toLocaleLowerCase().includes(normalizedQuery) ||
                space.description
                    ?.toLocaleLowerCase()
                    .includes(normalizedQuery) === true;

            return matchesFilter && matchesQuery;
        });
    }, [filter, query, spaces]);
    const join = (slug: string) =>
        router.post(
            `/spaces/${encodeURIComponent(slug)}/membership`,
            {},
            { preserveScroll: true },
        );

    return (
        <>
            <Head title="Spaces" />
            <main className="social-page">
                <section
                    className="social-card mb-5 overflow-hidden rounded-[1.55rem] border-foreground bg-foreground p-5 text-background sm:p-8 lg:p-10"
                    aria-labelledby="spaces-title"
                >
                    <div className="max-w-3xl">
                        <div className="flex items-center gap-2 text-xs font-extrabold tracking-[0.14em] text-mint uppercase">
                            <CommunitySignal />
                            Community discovery
                        </div>
                        <h1
                            id="spaces-title"
                            className="mt-3 text-3xl font-black tracking-[-0.045em] text-balance sm:text-5xl"
                        >
                            Find your people.
                            <br />
                            Build what is missing.
                        </h1>
                        <p className="mt-4 max-w-2xl text-sm leading-6 text-background/65 sm:text-base sm:leading-7">
                            Independent spaces for shared interests, local
                            groups, teams, and the conversations that deserve a
                            real home.
                        </p>
                        <div className="mt-6 flex flex-wrap items-center gap-3 text-xs font-bold text-background/55">
                            <span className="rounded-full bg-background/10 px-3 py-2 text-background">
                                <strong>
                                    {spaces.length.toLocaleString()}
                                </strong>{' '}
                                spaces
                            </span>
                            <span>
                                Chronological · Self-hosted · Open source
                            </span>
                        </div>
                    </div>
                </section>

                {status && (
                    <div
                        role="status"
                        className="mb-5 rounded-2xl border border-primary/20 bg-primary/8 px-4 py-3 text-sm font-bold"
                    >
                        {status}
                    </div>
                )}

                <div className="grid items-start gap-5 xl:grid-cols-[minmax(0,1fr)_21rem]">
                    <section aria-labelledby="space-directory-title">
                        <h2 id="space-directory-title" className="sr-only">
                            Discover spaces
                        </h2>
                        <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <label className="social-input-surface flex h-12 w-full items-center gap-3 px-4 sm:max-w-md sm:flex-1">
                                <Search
                                    className="size-4 shrink-0 text-muted-foreground"
                                    aria-hidden="true"
                                />
                                <span className="sr-only">Search spaces</span>
                                <input
                                    type="search"
                                    value={query}
                                    onChange={(event) =>
                                        setQuery(event.target.value)
                                    }
                                    placeholder="Search communities"
                                    className="min-w-0 flex-1 bg-transparent text-sm outline-none placeholder:text-muted-foreground"
                                />
                            </label>
                            <div
                                className="flex gap-2 overflow-x-auto"
                                role="group"
                                aria-label="Filter spaces"
                            >
                                {(['all', 'joined'] as const).map((value) => (
                                    <button
                                        key={value}
                                        type="button"
                                        aria-pressed={filter === value}
                                        onClick={() => setFilter(value)}
                                        className={`social-focus min-h-11 shrink-0 rounded-xl px-4 text-sm font-extrabold transition-[color,background-color,border-color,transform] active:translate-y-px ${filter === value ? 'bg-foreground text-background' : 'border border-border/80 bg-card text-muted-foreground hover:border-primary/20 hover:bg-secondary/55 hover:text-foreground'}`}
                                    >
                                        {value === 'all'
                                            ? 'Explore'
                                            : 'My spaces'}
                                    </button>
                                ))}
                            </div>
                        </div>

                        {visibleSpaces.length === 0 ? (
                            <div className="social-card rounded-[1.35rem] px-6 py-14 text-center">
                                <span className="mx-auto flex size-16 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                                    <UsersRound
                                        className="size-7"
                                        aria-hidden="true"
                                    />
                                </span>
                                <h3 className="mt-2 text-lg font-extrabold">
                                    No spaces found
                                </h3>
                                <p className="mx-auto mt-2 max-w-md text-sm leading-6 text-muted-foreground">
                                    Try a different search, or create the
                                    community you wish existed.
                                </p>
                            </div>
                        ) : (
                            <div className="grid gap-3 sm:grid-cols-2 2xl:grid-cols-3">
                                {visibleSpaces.map((space) => (
                                    <SpaceCard
                                        key={space.slug}
                                        space={space}
                                        onJoin={join}
                                    />
                                ))}
                            </div>
                        )}
                    </section>
                    <CreateSpace />
                </div>
            </main>
        </>
    );
}

Spaces.layout = { breadcrumbs: [{ title: 'Spaces', href: '/spaces' }] };

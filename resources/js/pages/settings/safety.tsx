import { Head, Link, router } from '@inertiajs/react';
import { EyeOff, ShieldCheck, ShieldX, Volume2 } from 'lucide-react';
import Heading from '@/components/heading';
import { AvatarMark } from '@/components/social/avatar-mark';
import { Button } from '@/components/ui/button';

type SafetyRelationship = {
    type: 'mute' | 'block';
    createdAt: string | null;
    person: { name: string; handle: string };
};

const dateLabel = (value: string | null) =>
    value
        ? new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(
              new Date(value),
          )
        : '';

export default function Safety({
    relationships,
}: {
    relationships: SafetyRelationship[];
}) {
    const muted = relationships.filter(
        (relationship) => relationship.type === 'mute',
    );
    const blocked = relationships.filter(
        (relationship) => relationship.type === 'block',
    );

    const remove = (relationship: SafetyRelationship) => {
        router.delete(
            `/people/${encodeURIComponent(relationship.person.handle)}/${relationship.type}`,
            { preserveScroll: true },
        );
    };

    return (
        <>
            <Head title="Safety settings" />
            <div className="space-y-8">
                <Heading
                    variant="small"
                    title="Safety"
                    description="Quiet unwanted content or create a complete boundary between accounts."
                />

                <div className="grid gap-3 sm:grid-cols-2">
                    <div className="rounded-2xl bg-secondary/65 p-4">
                        <EyeOff
                            className="size-5 text-primary"
                            aria-hidden="true"
                        />
                        <h2 className="mt-3 font-extrabold">Mute is private</h2>
                        <p className="mt-1 text-sm leading-6 text-muted-foreground">
                            Their posts leave your feed. They are not notified,
                            and profiles remain available.
                        </p>
                    </div>
                    <div className="rounded-2xl bg-secondary/65 p-4">
                        <ShieldX
                            className="size-5 text-destructive"
                            aria-hidden="true"
                        />
                        <h2 className="mt-3 font-extrabold">Block is mutual</h2>
                        <p className="mt-1 text-sm leading-6 text-muted-foreground">
                            Both accounts disappear from each other’s profiles,
                            discovery, and feeds.
                        </p>
                    </div>
                </div>

                <SafetyList
                    title="Muted people"
                    empty="You have not muted anyone."
                    relationships={muted}
                    onRemove={remove}
                />
                <SafetyList
                    title="Blocked people"
                    empty="You have not blocked anyone."
                    relationships={blocked}
                    onRemove={remove}
                />
            </div>
        </>
    );
}

function SafetyList({
    title,
    empty,
    relationships,
    onRemove,
}: {
    title: string;
    empty: string;
    relationships: SafetyRelationship[];
    onRemove: (relationship: SafetyRelationship) => void;
}) {
    const headingId = `${title.toLowerCase().replaceAll(' ', '-')}-title`;

    return (
        <section className="border-t pt-6" aria-labelledby={headingId}>
            <div className="flex items-center justify-between gap-3">
                <h2 id={headingId} className="font-extrabold tracking-tight">
                    {title}
                </h2>
                <span className="rounded-full bg-secondary px-2.5 py-1 text-xs font-extrabold">
                    {relationships.length}
                </span>
            </div>
            {relationships.length === 0 ? (
                <p className="mt-3 text-sm text-muted-foreground">{empty}</p>
            ) : (
                <ul className="mt-3 space-y-2">
                    {relationships.map((relationship) => (
                        <li
                            key={`${relationship.type}-${relationship.person.handle}`}
                            className="flex flex-col gap-3 rounded-2xl border bg-background p-3.5 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div className="flex min-w-0 items-center gap-3">
                                <AvatarMark
                                    name={relationship.person.name}
                                    className="size-10"
                                />
                                <div className="min-w-0">
                                    {relationship.type === 'block' ? (
                                        <span className="block truncate text-sm font-extrabold">
                                            {relationship.person.name}
                                        </span>
                                    ) : (
                                        <Link
                                            href={`/people/${relationship.person.handle}`}
                                            className="block truncate text-sm font-extrabold hover:underline"
                                        >
                                            {relationship.person.name}
                                        </Link>
                                    )}
                                    <p className="truncate text-xs text-muted-foreground">
                                        @{relationship.person.handle} ·{' '}
                                        {dateLabel(relationship.createdAt)}
                                    </p>
                                </div>
                            </div>
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                className="self-start rounded-full sm:self-auto"
                                onClick={() => onRemove(relationship)}
                            >
                                {relationship.type === 'mute' ? (
                                    <Volume2
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                ) : (
                                    <ShieldCheck
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                )}
                                {relationship.type === 'mute'
                                    ? 'Unmute'
                                    : 'Unblock'}
                            </Button>
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}

Safety.layout = {
    breadcrumbs: [{ title: 'Safety settings', href: '/settings/safety' }],
};

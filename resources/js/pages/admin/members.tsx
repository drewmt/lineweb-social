import { Head, Link, router } from '@inertiajs/react';
import {
    ChevronLeft,
    ChevronRight,
    Search,
    SquareTerminal,
    UserRoundCheck,
    UsersRound,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { AccountActionDialog } from '@/components/admin/account-action-dialog';
import { MemberRow } from '@/components/admin/member-row';
import type { Member } from '@/components/admin/member-row';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

type Filter = 'all' | 'active' | 'suspended' | 'administrators' | 'unverified';

type MembersPage = {
    data: Member[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
    total: number;
};

type Props = {
    query: string;
    filter: Filter;
    counts: Record<Filter, number>;
    members: MembersPage;
    status?: string;
};

const filters: Array<{ value: Filter; label: string }> = [
    { value: 'all', label: 'All' },
    { value: 'active', label: 'Active' },
    { value: 'suspended', label: 'Suspended' },
    { value: 'administrators', label: 'Administrators' },
    { value: 'unverified', label: 'Unverified' },
];

const directoryHref = (query: string, filter: Filter) => {
    const params = new URLSearchParams();

    if (query) {
        params.set('q', query);
    }

    if (filter !== 'all') {
        params.set('status', filter);
    }

    return `/admin/members${params.size > 0 ? `?${params.toString()}` : ''}`;
};

export default function AdminMembers({
    query,
    filter,
    counts,
    members,
    status,
}: Props) {
    const [search, setSearch] = useState(query);
    const [selectedMember, setSelectedMember] = useState<Member | null>(null);
    const [action, setAction] = useState<'suspend' | 'reinstate'>('suspend');

    const visitDirectory = (nextQuery: string, nextFilter: Filter) => {
        router.get(
            '/admin/members',
            {
                ...(nextQuery.trim() ? { q: nextQuery.trim() } : {}),
                ...(nextFilter !== 'all' ? { status: nextFilter } : {}),
            },
            {
                preserveState: true,
                replace: true,
            },
        );
    };

    const submitSearch = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        visitDirectory(search, filter);
    };

    const openAction = (
        member: Member,
        nextAction: 'suspend' | 'reinstate',
    ) => {
        setSelectedMember(member);
        setAction(nextAction);
    };

    return (
        <>
            <Head title="Member administration" />
            <div className="relative z-[1] mx-auto w-full max-w-[88rem] px-3 py-4 sm:px-6 sm:py-7 xl:px-8">
                <header className="max-w-3xl">
                    <p className="social-eyebrow">
                        <UsersRound className="size-3.5" aria-hidden="true" />
                        Account access
                    </p>
                    <h1 className="mt-2 text-3xl font-black tracking-[-0.045em] sm:text-4xl">
                        Members
                    </h1>
                    <p className="mt-2 max-w-2xl text-sm leading-6 text-muted-foreground sm:text-base">
                        Find accounts, understand their current access state,
                        and record every restriction or restoration.
                    </p>
                </header>

                {status && (
                    <div
                        role="status"
                        className="mt-5 rounded-2xl border border-primary/20 bg-primary/8 px-4 py-3 text-sm font-bold"
                    >
                        {status}
                    </div>
                )}

                <nav
                    aria-label="Member status"
                    className="-mx-3 mt-5 flex gap-2 overflow-x-auto px-3 pb-1 sm:mx-0 sm:px-0"
                >
                    {filters.map((item) => (
                        <Link
                            key={item.value}
                            href={directoryHref(query, item.value)}
                            preserveState
                            className={cn(
                                'social-focus flex min-h-12 shrink-0 items-center gap-3 rounded-2xl border px-4 text-sm font-extrabold transition-colors',
                                filter === item.value
                                    ? 'border-foreground bg-foreground text-background'
                                    : 'border-border/75 bg-card text-muted-foreground hover:border-primary/20 hover:bg-secondary/55 hover:text-foreground',
                            )}
                        >
                            {item.label}
                            <span
                                className={cn(
                                    'rounded-full px-2 py-0.5 text-xs tabular-nums',
                                    filter === item.value
                                        ? 'bg-background/12 text-background'
                                        : 'bg-secondary text-foreground',
                                )}
                            >
                                {counts[item.value].toLocaleString()}
                            </span>
                        </Link>
                    ))}
                </nav>

                <div className="mt-4 grid items-start gap-5 xl:grid-cols-[minmax(0,1fr)_22rem]">
                    <section className="social-card min-w-0 overflow-hidden rounded-[1.5rem]">
                        <div className="border-b p-4 sm:p-5">
                            <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                                <div>
                                    <h2 className="text-lg font-black tracking-tight">
                                        {members.total.toLocaleString()}{' '}
                                        {members.total === 1
                                            ? 'matching account'
                                            : 'matching accounts'}
                                    </h2>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        Results are newest first.
                                    </p>
                                </div>
                                <form
                                    onSubmit={submitSearch}
                                    className="flex w-full gap-2 lg:w-auto"
                                >
                                    <label className="relative min-w-0 flex-1 lg:w-80">
                                        <span className="sr-only">
                                            Search members
                                        </span>
                                        <Search
                                            className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                            aria-hidden="true"
                                        />
                                        <input
                                            type="search"
                                            value={search}
                                            onChange={(event) =>
                                                setSearch(event.target.value)
                                            }
                                            maxLength={100}
                                            placeholder="Name, handle, or email"
                                            className="social-inset social-focus h-11 w-full pl-10 text-sm"
                                        />
                                    </label>
                                    <Button
                                        type="submit"
                                        variant="outline"
                                        className="h-11 rounded-xl"
                                    >
                                        Search
                                    </Button>
                                </form>
                            </div>
                        </div>

                        {members.data.length === 0 ? (
                            <div className="px-5 py-16 text-center">
                                <Search className="mx-auto size-7 text-muted-foreground" />
                                <h3 className="mt-3 font-black">
                                    No matching members
                                </h3>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Try another search or account filter.
                                </p>
                                {(query || filter !== 'all') && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        className="mt-5"
                                        onClick={() => {
                                            setSearch('');
                                            visitDirectory('', 'all');
                                        }}
                                    >
                                        Clear filters
                                    </Button>
                                )}
                            </div>
                        ) : (
                            <ul className="divide-y divide-border/75">
                                {members.data.map((member) => (
                                    <MemberRow
                                        key={member.id}
                                        member={member}
                                        onAction={openAction}
                                    />
                                ))}
                            </ul>
                        )}

                        {members.last_page > 1 && (
                            <nav
                                aria-label="Member pages"
                                className="flex items-center justify-between gap-3 border-t px-4 py-4 sm:px-5"
                            >
                                {members.prev_page_url ? (
                                    <Button asChild variant="outline" size="sm">
                                        <Link href={members.prev_page_url}>
                                            <ChevronLeft className="size-4" />
                                            Previous
                                        </Link>
                                    </Button>
                                ) : (
                                    <span />
                                )}
                                <span className="text-xs font-bold text-muted-foreground">
                                    Page {members.current_page} of{' '}
                                    {members.last_page}
                                </span>
                                {members.next_page_url ? (
                                    <Button asChild variant="outline" size="sm">
                                        <Link href={members.next_page_url}>
                                            Next
                                            <ChevronRight className="size-4" />
                                        </Link>
                                    </Button>
                                ) : (
                                    <span />
                                )}
                            </nav>
                        )}
                    </section>

                    <aside className="space-y-4 xl:sticky xl:top-20">
                        <section className="social-card rounded-[1.5rem] p-5">
                            <div className="flex items-center gap-3">
                                <span className="flex size-10 items-center justify-center rounded-xl bg-foreground text-background">
                                    <SquareTerminal
                                        className="size-4.5"
                                        aria-hidden="true"
                                    />
                                </span>
                                <div>
                                    <h2 className="font-black tracking-tight">
                                        Administrator access
                                    </h2>
                                    <p className="text-xs text-muted-foreground">
                                        Console-only by design
                                    </p>
                                </div>
                            </div>
                            <p className="mt-4 text-sm leading-6 text-muted-foreground">
                                The web interface cannot promote accounts. Grant
                                or revoke access only from a trusted application
                                shell.
                            </p>
                            <code className="mt-3 block overflow-x-auto rounded-xl bg-foreground px-3 py-3 text-[0.7rem] font-semibold text-background">
                                php artisan platform:administrator email
                            </code>
                        </section>

                        <section className="social-card rounded-[1.5rem] p-5">
                            <UserRoundCheck
                                className="size-5 text-primary"
                                aria-hidden="true"
                            />
                            <h2 className="mt-3 font-black tracking-tight">
                                Explicit access decisions
                            </h2>
                            <p className="mt-2 text-sm leading-6 text-muted-foreground">
                                Suspension ends sessions and API tokens but does
                                not delete content. Reinstatement never restores
                                revoked credentials.
                            </p>
                        </section>
                    </aside>
                </div>
            </div>

            <AccountActionDialog
                member={selectedMember}
                action={action}
                query={query}
                filter={filter}
                open={selectedMember !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setSelectedMember(null);
                    }
                }}
            />
        </>
    );
}

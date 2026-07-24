import { Head, Link } from '@inertiajs/react';
import {
    ArrowRight,
    CalendarDays,
    ExternalLink,
    MapPin,
    MessageCircleMore,
    MessageCircle,
    Settings,
    UsersRound,
} from 'lucide-react';
import { AuthoredContentMenu } from '@/components/social/authored-content-menu';
import { AvatarMark } from '@/components/social/avatar-mark';
import { PostImage } from '@/components/social/post-image';
import type { PostMedia } from '@/components/social/post-image';
import { SpaceCover } from '@/components/social/space-cover';
import { Button } from '@/components/ui/button';
import { ProfileFollowButton } from './profile-follow-button';
import { ProfileSafetyActions } from './profile-safety-actions';

type Profile = {
    name: string;
    handle: string;
    headline: string | null;
    bio: string | null;
    location: string | null;
    websiteUrl: string | null;
    memberSince: string | null;
    isSelf: boolean;
    isMuted: boolean;
    isFollowing: boolean;
    canFollow: boolean;
    canMessage: boolean;
};

type ProfileStats = {
    visibleSpaces: number;
    visiblePosts: number;
    followers: number;
    following: number;
};

type ProfileSpace = {
    name: string;
    slug: string;
    description: string | null;
    memberCount: number;
};

type ProfilePost = {
    id: number;
    url: string;
    body: string;
    media: PostMedia | null;
    publishedAt: string | null;
    editedAt: string | null;
    canEdit: boolean;
    canDelete: boolean;
    space: { name: string; slug: string };
};

const monthYear = (value: string | null) =>
    value
        ? new Intl.DateTimeFormat(undefined, {
              month: 'long',
              year: 'numeric',
          }).format(new Date(value))
        : null;

const postDate = (value: string | null) =>
    value
        ? new Intl.DateTimeFormat(undefined, {
              dateStyle: 'medium',
              timeStyle: 'short',
          }).format(new Date(value))
        : 'Draft';

const websiteHost = (value: string) => {
    try {
        return new URL(value).hostname.replace(/^www\./, '');
    } catch {
        return value;
    }
};

export default function ShowProfile({
    profile,
    stats,
    spaces,
    posts,
}: {
    profile: Profile;
    stats: ProfileStats;
    spaces: ProfileSpace[];
    posts: ProfilePost[];
}) {
    const joined = monthYear(profile.memberSince);

    return (
        <>
            <Head title={profile.name} />
            <main className="social-page max-w-[82rem]">
                <section
                    className="social-card overflow-hidden rounded-[1.8rem]"
                    aria-labelledby="profile-name"
                >
                    <div className="relative h-36 overflow-hidden bg-[linear-gradient(115deg,oklch(0.23_0.08_260),oklch(0.46_0.22_265)_58%,oklch(0.74_0.12_190))] sm:h-52">
                        <div className="absolute -top-24 right-[8%] size-64 rounded-full border-[3rem] border-white/8" />
                        <div className="absolute -bottom-28 left-[34%] size-56 rounded-full border-[2.5rem] border-mint/14" />
                        <p className="absolute right-5 bottom-4 max-w-[70%] truncate text-right text-[clamp(2.4rem,7vw,6rem)] leading-none font-black tracking-[-0.075em] text-white/10 sm:right-8">
                            @{profile.handle}
                        </p>
                    </div>

                    <div className="relative px-5 pb-0 sm:px-8">
                        <div className="-mt-14 flex items-end justify-between gap-4 sm:-mt-16">
                            <AvatarMark
                                name={profile.name}
                                className="size-28 border-[5px] border-card text-2xl ring-0 sm:size-32 sm:text-3xl"
                            />
                            <div className="mb-2 flex shrink-0 items-center gap-2">
                                {profile.isSelf ? (
                                    <Button
                                        asChild
                                        variant="outline"
                                        className="rounded-full bg-card px-5"
                                    >
                                        <Link href="/settings/profile">
                                            <Settings
                                                className="size-4"
                                                aria-hidden="true"
                                            />
                                            Edit profile
                                        </Link>
                                    </Button>
                                ) : (
                                    <>
                                        {profile.canFollow && (
                                            <ProfileFollowButton
                                                handle={profile.handle}
                                                isFollowing={
                                                    profile.isFollowing
                                                }
                                            />
                                        )}
                                        {profile.canMessage && (
                                            <Button
                                                asChild
                                                variant="outline"
                                                className="rounded-full bg-card px-3 sm:px-4"
                                            >
                                                <Link
                                                    href={`/messages/new/${profile.handle}`}
                                                    aria-label={`Message ${profile.name}`}
                                                >
                                                    <MessageCircleMore
                                                        className="size-4"
                                                        aria-hidden="true"
                                                    />
                                                    <span className="hidden sm:inline">
                                                        Message
                                                    </span>
                                                </Link>
                                            </Button>
                                        )}
                                        <ProfileSafetyActions
                                            handle={profile.handle}
                                            isMuted={profile.isMuted}
                                        />
                                    </>
                                )}
                            </div>
                        </div>

                        <div className="mt-5 max-w-3xl">
                            <div className="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                                <h1
                                    id="profile-name"
                                    className="text-3xl font-black tracking-[-0.045em] sm:text-[2.65rem]"
                                >
                                    {profile.name}
                                </h1>
                                <span className="text-sm font-extrabold text-primary">
                                    @{profile.handle}
                                </span>
                            </div>
                            {profile.headline && (
                                <p className="mt-2 text-lg font-bold tracking-[-0.015em] text-foreground/82 sm:text-xl">
                                    {profile.headline}
                                </p>
                            )}
                            {profile.bio && (
                                <p className="mt-4 max-w-2xl text-[0.98rem] leading-7 text-muted-foreground">
                                    {profile.bio}
                                </p>
                            )}
                        </div>

                        <dl className="mt-6 flex flex-wrap gap-x-8 gap-y-3 border-t border-border/65 py-5">
                            <div className="flex items-baseline gap-2">
                                <dt className="text-sm font-semibold text-muted-foreground">
                                    Followers
                                </dt>
                                <dd className="text-xl font-black tracking-tight">
                                    {stats.followers.toLocaleString()}
                                </dd>
                            </div>
                            <div className="flex items-baseline gap-2">
                                <dt className="text-sm font-semibold text-muted-foreground">
                                    Following
                                </dt>
                                <dd className="text-xl font-black tracking-tight">
                                    {stats.following.toLocaleString()}
                                </dd>
                            </div>
                            <div className="flex items-baseline gap-2">
                                <dt className="text-sm font-semibold text-muted-foreground">
                                    Visible posts
                                </dt>
                                <dd className="text-xl font-black tracking-tight">
                                    {stats.visiblePosts.toLocaleString()}
                                </dd>
                            </div>
                            <div className="flex items-baseline gap-2">
                                <dt className="text-sm font-semibold text-muted-foreground">
                                    Visible spaces
                                </dt>
                                <dd className="text-xl font-black tracking-tight">
                                    {stats.visibleSpaces.toLocaleString()}
                                </dd>
                            </div>
                        </dl>

                        <nav
                            className="flex gap-6 overflow-x-auto border-t border-border/65"
                            aria-label="Profile sections"
                        >
                            <a
                                href="#overview"
                                className="social-focus border-b-2 border-primary px-1 py-4 text-sm font-extrabold text-primary"
                            >
                                Overview
                            </a>
                            <a
                                href="#activity"
                                className="social-focus px-1 py-4 text-sm font-extrabold text-muted-foreground hover:text-foreground"
                            >
                                Activity
                            </a>
                            <a
                                href="#spaces"
                                className="social-focus px-1 py-4 text-sm font-extrabold text-muted-foreground hover:text-foreground"
                            >
                                Spaces
                            </a>
                        </nav>
                    </div>
                </section>

                <div
                    id="overview"
                    className="mt-5 grid scroll-mt-24 items-start gap-5 lg:grid-cols-[20rem_minmax(0,1fr)]"
                >
                    <aside className="space-y-5 lg:sticky lg:top-5">
                        <section
                            className="social-card rounded-[1.45rem] p-5"
                            aria-labelledby="profile-about-title"
                        >
                            <p className="social-eyebrow">Profile</p>
                            <h2
                                id="profile-about-title"
                                className="mt-2 text-xl font-black tracking-[-0.03em]"
                            >
                                About {profile.name.split(' ')[0]}
                            </h2>
                            <dl className="mt-5 space-y-4 text-sm">
                                {profile.location && (
                                    <div className="flex items-start gap-3">
                                        <MapPin
                                            className="mt-0.5 size-4 shrink-0 text-primary"
                                            aria-hidden="true"
                                        />
                                        <div>
                                            <dt className="text-xs font-bold text-muted-foreground">
                                                Location
                                            </dt>
                                            <dd className="mt-0.5 font-extrabold">
                                                {profile.location}
                                            </dd>
                                        </div>
                                    </div>
                                )}
                                {profile.websiteUrl && (
                                    <div className="flex items-start gap-3">
                                        <ExternalLink
                                            className="mt-0.5 size-4 shrink-0 text-primary"
                                            aria-hidden="true"
                                        />
                                        <div className="min-w-0">
                                            <dt className="text-xs font-bold text-muted-foreground">
                                                On the web
                                            </dt>
                                            <dd className="mt-0.5 truncate">
                                                <a
                                                    href={profile.websiteUrl}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className="social-focus rounded font-extrabold text-primary hover:underline"
                                                >
                                                    {websiteHost(
                                                        profile.websiteUrl,
                                                    )}
                                                </a>
                                            </dd>
                                        </div>
                                    </div>
                                )}
                                {joined && (
                                    <div className="flex items-start gap-3">
                                        <CalendarDays
                                            className="mt-0.5 size-4 shrink-0 text-primary"
                                            aria-hidden="true"
                                        />
                                        <div>
                                            <dt className="text-xs font-bold text-muted-foreground">
                                                Member since
                                            </dt>
                                            <dd className="mt-0.5 font-extrabold">
                                                {joined}
                                            </dd>
                                        </div>
                                    </div>
                                )}
                            </dl>
                            {!profile.location &&
                                !profile.websiteUrl &&
                                !joined && (
                                    <p className="mt-4 text-sm leading-6 text-muted-foreground">
                                        No additional profile details yet.
                                    </p>
                                )}
                        </section>

                        <section
                            id="spaces"
                            className="social-card scroll-mt-24 rounded-[1.45rem] p-4"
                            aria-labelledby="profile-spaces-title"
                        >
                            <div className="flex items-center justify-between gap-3 px-1">
                                <div className="flex items-center gap-2">
                                    <UsersRound
                                        className="size-4 text-primary"
                                        aria-hidden="true"
                                    />
                                    <h2
                                        id="profile-spaces-title"
                                        className="font-extrabold tracking-tight"
                                    >
                                        Spaces
                                    </h2>
                                </div>
                                <span className="text-xs font-bold text-muted-foreground">
                                    Visible to you
                                </span>
                            </div>
                            {spaces.length === 0 ? (
                                <p className="mt-4 px-1 text-sm leading-6 text-muted-foreground">
                                    No public or shared Spaces are visible to
                                    you.
                                </p>
                            ) : (
                                <div className="mt-3 space-y-2">
                                    {spaces.map((space) => (
                                        <Link
                                            key={space.slug}
                                            href={`/spaces/${space.slug}`}
                                            className="social-focus group flex items-center gap-3 rounded-2xl p-2 transition-colors hover:bg-secondary/70"
                                        >
                                            <span className="h-12 w-14 shrink-0 overflow-hidden rounded-xl bg-primary/8">
                                                <SpaceCover seed={space.slug} />
                                            </span>
                                            <span className="min-w-0 flex-1">
                                                <span className="block truncate text-sm font-extrabold">
                                                    {space.name}
                                                </span>
                                                <span className="block text-xs text-muted-foreground">
                                                    {space.memberCount.toLocaleString()}{' '}
                                                    {space.memberCount === 1
                                                        ? 'member'
                                                        : 'members'}
                                                </span>
                                            </span>
                                            <ArrowRight
                                                className="size-4 text-muted-foreground transition-transform group-hover:translate-x-0.5"
                                                aria-hidden="true"
                                            />
                                        </Link>
                                    ))}
                                </div>
                            )}
                        </section>
                    </aside>

                    <section
                        id="activity"
                        className="scroll-mt-24"
                        aria-labelledby="profile-posts-title"
                    >
                        <div className="mb-3 flex items-end justify-between px-1">
                            <div>
                                <p className="social-eyebrow">
                                    Recent activity
                                </p>
                                <h2
                                    id="profile-posts-title"
                                    className="mt-1 text-2xl font-black tracking-[-0.035em]"
                                >
                                    Conversations
                                </h2>
                            </div>
                            <MessageCircle
                                className="size-5 text-muted-foreground"
                                aria-hidden="true"
                            />
                        </div>
                        {posts.length === 0 ? (
                            <div className="social-card rounded-[1.45rem] px-6 py-14 text-center">
                                <span className="mx-auto flex size-12 items-center justify-center rounded-2xl bg-primary/8 text-primary">
                                    <MessageCircle
                                        className="size-5"
                                        aria-hidden="true"
                                    />
                                </span>
                                <p className="mt-4 font-extrabold">
                                    Nothing visible here yet.
                                </p>
                                <p className="mx-auto mt-2 max-w-md text-sm leading-6 text-muted-foreground">
                                    Posts appear only when you can also view the
                                    Space where they were shared.
                                </p>
                            </div>
                        ) : (
                            <div className="space-y-3">
                                {posts.map((post) => (
                                    <article
                                        key={post.id}
                                        className="social-card rounded-[1.45rem] p-4 sm:p-5"
                                    >
                                        <header className="flex items-start gap-3">
                                            <AvatarMark
                                                name={profile.name}
                                                className="size-10"
                                            />
                                            <div className="min-w-0 flex-1">
                                                <p className="truncate text-sm font-extrabold">
                                                    {profile.name}
                                                </p>
                                                <div className="flex flex-wrap items-center gap-x-1.5 text-xs font-semibold text-muted-foreground">
                                                    <Link
                                                        href={`/spaces/${post.space.slug}`}
                                                        className="text-primary hover:underline"
                                                    >
                                                        {post.space.name}
                                                    </Link>
                                                    <span aria-hidden="true">
                                                        ·
                                                    </span>
                                                    <Link
                                                        href={post.url}
                                                        className="social-focus rounded-md hover:text-foreground"
                                                    >
                                                        <time
                                                            dateTime={
                                                                post.publishedAt ??
                                                                undefined
                                                            }
                                                        >
                                                            {postDate(
                                                                post.publishedAt,
                                                            )}
                                                        </time>
                                                    </Link>
                                                    {post.editedAt && (
                                                        <>
                                                            <span aria-hidden="true">
                                                                ·
                                                            </span>
                                                            <span>Edited</span>
                                                        </>
                                                    )}
                                                </div>
                                            </div>
                                            <AuthoredContentMenu
                                                body={post.body}
                                                canEdit={post.canEdit}
                                                canDelete={post.canDelete}
                                                contentType="post"
                                                updateUrl={`/posts/${post.id}`}
                                                deleteUrl={`/posts/${post.id}`}
                                                maxLength={2000}
                                                compact
                                            />
                                        </header>
                                        <p className="mt-4 text-[1.01rem] leading-7 whitespace-pre-wrap text-foreground/90">
                                            {post.body}
                                        </p>
                                        {post.media && (
                                            <PostImage
                                                media={post.media}
                                                className="mt-4"
                                            />
                                        )}
                                    </article>
                                ))}
                            </div>
                        )}
                    </section>
                </div>
            </main>
        </>
    );
}

ShowProfile.layout = { breadcrumbs: [{ title: 'People', href: '/people' }] };

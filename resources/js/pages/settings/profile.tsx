import { Form, Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowUpRight,
    Check,
    Eye,
    EyeOff,
    Search,
    ShieldCheck,
} from 'lucide-react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/delete-user';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/profile';
import { send } from '@/routes/verification';
import type { Auth } from '@/types';

type PageProps = { auth: Auth };

const visibilityOptions = [
    {
        value: 'public',
        title: 'Public',
        description: 'Every verified member can open your profile.',
        icon: Eye,
    },
    {
        value: 'members',
        title: 'Shared spaces',
        description: 'Only people who share at least one Space with you.',
        icon: ShieldCheck,
    },
    {
        value: 'private',
        title: 'Private',
        description: 'Only you can open your profile.',
        icon: EyeOff,
    },
] as const;

export default function Profile({
    mustVerifyEmail,
    status,
}: {
    mustVerifyEmail: boolean;
    status?: string;
}) {
    const { auth } = usePage<PageProps>().props;
    const user = auth.user;

    return (
        <>
            <Head title="Profile settings" />
            <div className="space-y-8">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <Heading
                        variant="small"
                        title="Your profile"
                        description="Choose what people see and who is allowed to find you."
                    />
                    <Button
                        asChild
                        variant="outline"
                        className="w-fit rounded-full"
                    >
                        <Link href={`/people/${user.handle}`}>
                            Preview profile{' '}
                            <ArrowUpRight
                                className="size-4"
                                aria-hidden="true"
                            />
                        </Link>
                    </Button>
                </div>

                <Form
                    {...ProfileController.update.form()}
                    options={{ preserveScroll: true }}
                    className="space-y-8"
                >
                    {({ processing, errors }) => (
                        <>
                            <section
                                className="space-y-5"
                                aria-labelledby="identity-title"
                            >
                                <div>
                                    <h2
                                        id="identity-title"
                                        className="font-extrabold tracking-tight"
                                    >
                                        Identity
                                    </h2>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        The human details shown on your profile.
                                    </p>
                                </div>
                                <div className="grid gap-5 sm:grid-cols-2">
                                    <label className="grid gap-2">
                                        <Label htmlFor="name">
                                            Display name
                                        </Label>
                                        <Input
                                            id="name"
                                            defaultValue={user.name}
                                            name="name"
                                            required
                                            autoComplete="name"
                                            placeholder="Full name"
                                            className="rounded-xl"
                                        />
                                        <InputError message={errors.name} />
                                    </label>
                                    <label className="grid gap-2">
                                        <Label htmlFor="handle">Handle</Label>
                                        <div className="relative">
                                            <span className="absolute top-1/2 left-3 -translate-y-1/2 font-bold text-muted-foreground">
                                                @
                                            </span>
                                            <Input
                                                id="handle"
                                                defaultValue={user.handle}
                                                name="handle"
                                                required
                                                minLength={3}
                                                maxLength={40}
                                                autoComplete="off"
                                                className="rounded-xl pl-8"
                                            />
                                        </div>
                                        <InputError message={errors.handle} />
                                    </label>
                                </div>
                                <label className="grid gap-2">
                                    <Label htmlFor="headline">Headline</Label>
                                    <Input
                                        id="headline"
                                        defaultValue={user.headline ?? ''}
                                        name="headline"
                                        maxLength={120}
                                        placeholder="e.g. Community product builder"
                                        className="rounded-xl"
                                    />
                                    <p className="text-xs leading-5 text-muted-foreground">
                                        A short line that explains what you do
                                        or care about.
                                    </p>
                                    <InputError message={errors.headline} />
                                </label>
                                <label className="grid gap-2">
                                    <Label htmlFor="bio">Bio</Label>
                                    <textarea
                                        id="bio"
                                        name="bio"
                                        defaultValue={user.bio ?? ''}
                                        maxLength={320}
                                        rows={4}
                                        placeholder="What do you care about, build, or bring to a community?"
                                        className="social-focus w-full resize-y rounded-xl border bg-background px-3.5 py-3 text-sm leading-6 placeholder:text-muted-foreground/70"
                                    />
                                    <InputError message={errors.bio} />
                                </label>
                                <div className="grid gap-5 sm:grid-cols-2">
                                    <label className="grid gap-2">
                                        <Label htmlFor="location">
                                            Location
                                        </Label>
                                        <Input
                                            id="location"
                                            defaultValue={user.location ?? ''}
                                            name="location"
                                            maxLength={120}
                                            placeholder="e.g. Thessaloniki"
                                            className="rounded-xl"
                                        />
                                        <InputError message={errors.location} />
                                    </label>
                                    <label className="grid gap-2">
                                        <Label htmlFor="website_url">
                                            Website
                                        </Label>
                                        <Input
                                            id="website_url"
                                            type="url"
                                            defaultValue={
                                                user.website_url ?? ''
                                            }
                                            name="website_url"
                                            maxLength={2048}
                                            placeholder="https://example.com"
                                            className="rounded-xl"
                                        />
                                        <InputError
                                            message={errors.website_url}
                                        />
                                    </label>
                                </div>
                                <label className="grid gap-2">
                                    <Label htmlFor="email">Email address</Label>
                                    <Input
                                        id="email"
                                        type="email"
                                        defaultValue={user.email}
                                        name="email"
                                        required
                                        autoComplete="username"
                                        placeholder="Email address"
                                        className="rounded-xl"
                                    />
                                    <InputError message={errors.email} />
                                </label>
                                {mustVerifyEmail &&
                                    user.email_verified_at === null && (
                                        <div className="rounded-xl bg-secondary p-3 text-sm text-muted-foreground">
                                            Your email address is unverified.{' '}
                                            <Link
                                                href={send()}
                                                as="button"
                                                className="font-bold text-primary hover:underline"
                                            >
                                                Send a new verification email.
                                            </Link>
                                            {status ===
                                                'verification-link-sent' && (
                                                <p className="mt-2 font-bold text-emerald-700">
                                                    A new verification link was
                                                    sent.
                                                </p>
                                            )}
                                        </div>
                                    )}
                            </section>

                            <section
                                className="border-t pt-7"
                                aria-labelledby="privacy-title"
                            >
                                <div>
                                    <h2
                                        id="privacy-title"
                                        className="font-extrabold tracking-tight"
                                    >
                                        Privacy
                                    </h2>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        These rules are enforced by the server
                                        on every profile request.
                                    </p>
                                </div>
                                <fieldset className="mt-4">
                                    <legend className="text-sm font-bold">
                                        Who can open your profile?
                                    </legend>
                                    <div className="mt-3 grid gap-2">
                                        {visibilityOptions.map((option) => {
                                            const Icon = option.icon;
                                            const selected =
                                                user.profile_visibility ===
                                                option.value;

                                            return (
                                                <label
                                                    key={option.value}
                                                    className="social-focus group flex cursor-pointer items-start gap-3 rounded-2xl border p-3.5 transition-colors hover:bg-secondary/45 has-checked:border-primary/35 has-checked:bg-primary/8"
                                                >
                                                    <input
                                                        type="radio"
                                                        name="profile_visibility"
                                                        value={option.value}
                                                        defaultChecked={
                                                            selected
                                                        }
                                                        className="sr-only"
                                                    />
                                                    <span className="flex size-9 shrink-0 items-center justify-center rounded-xl bg-secondary text-muted-foreground group-has-checked:bg-primary group-has-checked:text-primary-foreground">
                                                        {selected ? (
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
                                                            {option.title}
                                                        </span>
                                                        <span className="mt-0.5 block text-xs leading-5 text-muted-foreground">
                                                            {option.description}
                                                        </span>
                                                    </span>
                                                </label>
                                            );
                                        })}
                                    </div>
                                    <InputError
                                        className="mt-2"
                                        message={errors.profile_visibility}
                                    />
                                </fieldset>

                                <fieldset className="mt-5">
                                    <legend className="text-sm font-bold">
                                        Discovery
                                    </legend>
                                    <div className="mt-3 grid gap-2 sm:grid-cols-2">
                                        <label className="social-focus flex cursor-pointer gap-3 rounded-2xl border p-3.5 has-checked:border-primary/35 has-checked:bg-primary/8">
                                            <input
                                                type="radio"
                                                name="is_discoverable"
                                                value="1"
                                                defaultChecked={
                                                    user.is_discoverable
                                                }
                                                className="sr-only"
                                            />
                                            <Search
                                                className="mt-0.5 size-5 shrink-0 text-primary"
                                                aria-hidden="true"
                                            />
                                            <span>
                                                <span className="block text-sm font-extrabold">
                                                    Appear in People
                                                </span>
                                                <span className="mt-0.5 block text-xs leading-5 text-muted-foreground">
                                                    Eligible viewers can find
                                                    you through discovery.
                                                </span>
                                            </span>
                                        </label>
                                        <label className="social-focus flex cursor-pointer gap-3 rounded-2xl border p-3.5 has-checked:border-primary/35 has-checked:bg-primary/8">
                                            <input
                                                type="radio"
                                                name="is_discoverable"
                                                value="0"
                                                defaultChecked={
                                                    !user.is_discoverable
                                                }
                                                className="sr-only"
                                            />
                                            <EyeOff
                                                className="mt-0.5 size-5 shrink-0 text-primary"
                                                aria-hidden="true"
                                            />
                                            <span>
                                                <span className="block text-sm font-extrabold">
                                                    Direct link only
                                                </span>
                                                <span className="mt-0.5 block text-xs leading-5 text-muted-foreground">
                                                    Your visibility rule still
                                                    applies to direct visits.
                                                </span>
                                            </span>
                                        </label>
                                    </div>
                                    <InputError
                                        className="mt-2"
                                        message={errors.is_discoverable}
                                    />
                                </fieldset>
                            </section>

                            <div className="flex items-center gap-3 border-t pt-6">
                                <Button
                                    disabled={processing}
                                    data-test="update-profile-button"
                                    className="rounded-full px-6 font-bold"
                                >
                                    Save profile
                                </Button>
                                <span className="text-xs text-muted-foreground">
                                    Privacy changes apply immediately.
                                </span>
                            </div>
                        </>
                    )}
                </Form>
            </div>
            <DeleteUser />
        </>
    );
}

Profile.layout = { breadcrumbs: [{ title: 'Profile settings', href: edit() }] };

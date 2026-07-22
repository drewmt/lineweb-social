import { Form, router } from '@inertiajs/react';
import { Check, Copy, KeyRound, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import ApiTokenController from '@/actions/App/Http/Controllers/Settings/ApiTokenController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export type ApiToken = {
    id: string;
    name: string;
    abilities: string[];
    createdAt: string | null;
    lastUsedAt: string | null;
    expiresAt: string | null;
    expired: boolean;
};

type NewApiToken = {
    name: string;
    plainTextToken: string;
    expiresAt: string;
};

type Props = {
    apiTokens: ApiToken[];
};

const date = (value: string | null) => value?.slice(0, 10) ?? 'Never';

export default function ManageApiTokens({ apiTokens }: Props) {
    const [newToken, setNewToken] = useState<NewApiToken | null>(null);
    const [copied, setCopied] = useState(false);
    const [copyFailed, setCopyFailed] = useState(false);

    useEffect(() => {
        return router.on('flash', (event) => {
            const flash = (event as CustomEvent).detail?.flash;
            const token = flash?.apiToken as NewApiToken | undefined;

            if (token) {
                setNewToken(token);
                setCopied(false);
                setCopyFailed(false);
            }
        });
    }, []);

    const copyToken = async () => {
        if (!newToken) {
            return;
        }

        try {
            await navigator.clipboard.writeText(newToken.plainTextToken);
            setCopied(true);
            setCopyFailed(false);
        } catch {
            setCopied(false);
            setCopyFailed(true);
        }
    };

    return (
        <div className="space-y-6">
            <Heading
                variant="small"
                title="API access"
                description="Create short-lived tokens for trusted native, automation, or decoupled clients"
            />

            {newToken && (
                <div
                    role="status"
                    className="space-y-3 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-950 dark:border-emerald-900 dark:bg-emerald-950/35 dark:text-emerald-50"
                >
                    <div>
                        <p className="font-semibold">
                            Copy “{newToken.name}” now
                        </p>
                        <p className="mt-1 text-sm opacity-80">
                            This token is shown once and expires on{' '}
                            {date(newToken.expiresAt)}.
                        </p>
                    </div>
                    <div className="flex flex-col gap-2 sm:flex-row">
                        <Input
                            readOnly
                            value={newToken.plainTextToken}
                            aria-label="New API token"
                            className="font-mono text-xs"
                            onFocus={(event) => event.currentTarget.select()}
                        />
                        <Button
                            type="button"
                            variant="outline"
                            onClick={copyToken}
                        >
                            {copied ? <Check /> : <Copy />}
                            {copied ? 'Copied' : 'Copy token'}
                        </Button>
                    </div>
                    {copyFailed && (
                        <p className="text-sm font-medium">
                            Automatic copy was blocked. Select the token field
                            and copy it manually.
                        </p>
                    )}
                </div>
            )}

            <Form
                {...ApiTokenController.store.form()}
                options={{ preserveScroll: true }}
                resetOnSuccess
                className="rounded-2xl border border-border bg-card p-4 sm:p-5"
            >
                {({ errors, processing }) => (
                    <div className="grid gap-5">
                        <div className="grid gap-2 sm:max-w-xl">
                            <Label htmlFor="api-token-name">Token name</Label>
                            <Input
                                id="api-token-name"
                                name="name"
                                placeholder="My phone or reporting tool"
                                minLength={2}
                                maxLength={80}
                                autoComplete="off"
                                required
                            />
                            <InputError message={errors.name} />
                        </div>

                        <fieldset className="grid gap-3">
                            <legend className="text-sm font-medium">
                                Token access
                            </legend>
                            <input
                                type="hidden"
                                name="abilities[]"
                                value="profile:read"
                            />
                            <label className="flex items-start gap-3 rounded-xl border border-border p-3">
                                <span
                                    aria-hidden="true"
                                    className="mt-0.5 flex size-4 shrink-0 items-center justify-center rounded-sm bg-primary text-primary-foreground opacity-60"
                                >
                                    <Check className="size-3" />
                                </span>
                                <span>
                                    <span className="block text-sm font-medium">
                                        Own safe profile
                                    </span>
                                    <span className="block text-xs text-muted-foreground">
                                        Required for every token.
                                    </span>
                                </span>
                            </label>
                            <label className="flex cursor-pointer items-start gap-3 rounded-xl border border-border p-3 transition-colors hover:bg-muted/50">
                                <input
                                    type="checkbox"
                                    name="abilities[]"
                                    value="profiles:read"
                                    className="mt-0.5 size-4 shrink-0 accent-primary"
                                />
                                <span>
                                    <span className="block text-sm font-medium">
                                        Visible member profiles
                                    </span>
                                    <span className="block text-xs text-muted-foreground">
                                        Read only profiles already allowed by
                                        their privacy and safety settings.
                                    </span>
                                </span>
                            </label>
                            <label className="flex cursor-pointer items-start gap-3 rounded-xl border border-border p-3 transition-colors hover:bg-muted/50">
                                <input
                                    type="checkbox"
                                    name="abilities[]"
                                    value="spaces:read"
                                    className="mt-0.5 size-4 shrink-0 accent-primary"
                                />
                                <span>
                                    <span className="block text-sm font-medium">
                                        Discoverable Spaces
                                    </span>
                                    <span className="block text-xs text-muted-foreground">
                                        Read public Spaces and private or hidden
                                        Spaces where you are already a member.
                                    </span>
                                </span>
                            </label>
                            <label className="flex cursor-pointer items-start gap-3 rounded-xl border border-border p-3 transition-colors hover:bg-muted/50">
                                <input
                                    type="checkbox"
                                    name="abilities[]"
                                    value="feed:read"
                                    className="mt-0.5 size-4 shrink-0 accent-primary"
                                />
                                <span>
                                    <span className="block text-sm font-medium">
                                        Policy-filtered feed
                                    </span>
                                    <span className="block text-xs text-muted-foreground">
                                        Read chronological posts and their
                                        private images only where this account
                                        already has access.
                                    </span>
                                </span>
                            </label>
                            <InputError message={errors.abilities} />
                        </fieldset>

                        <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <p className="text-xs text-muted-foreground">
                                Tokens expire after 30 days. Grant only the
                                access this client needs.
                            </p>
                            <Button type="submit" disabled={processing}>
                                <KeyRound />
                                {processing ? 'Creating...' : 'Create token'}
                            </Button>
                        </div>
                    </div>
                )}
            </Form>

            <div className="overflow-hidden rounded-2xl border border-border bg-card">
                {apiTokens.length === 0 ? (
                    <div className="p-6 text-center sm:p-8">
                        <div className="mx-auto mb-3 flex size-12 items-center justify-center rounded-xl bg-muted">
                            <KeyRound className="size-5 text-muted-foreground" />
                        </div>
                        <p className="font-medium">No API tokens</p>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Create one only when a trusted client needs API
                            access.
                        </p>
                    </div>
                ) : (
                    apiTokens.map((token) => (
                        <div
                            key={token.id}
                            className="flex flex-col gap-3 border-b p-4 last:border-b-0 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div className="min-w-0">
                                <div className="flex flex-wrap items-center gap-2">
                                    <p className="truncate font-medium">
                                        {token.name}
                                    </p>
                                    {token.expired && (
                                        <span className="rounded-md bg-destructive/10 px-2 py-0.5 text-xs font-medium text-destructive">
                                            Expired
                                        </span>
                                    )}
                                </div>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Created {date(token.createdAt)} · Last used{' '}
                                    {date(token.lastUsedAt)} · Expires{' '}
                                    {date(token.expiresAt)}
                                </p>
                                <p className="mt-1 font-mono text-xs text-muted-foreground">
                                    {token.abilities.join(', ')}
                                </p>
                            </div>

                            <Dialog>
                                <DialogTrigger asChild>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        className="self-start text-destructive hover:bg-destructive/10 hover:text-destructive sm:self-auto"
                                    >
                                        <Trash2 />
                                        Revoke
                                    </Button>
                                </DialogTrigger>
                                <DialogContent>
                                    <DialogTitle>Revoke API token?</DialogTitle>
                                    <DialogDescription>
                                        “{token.name}” will immediately stop
                                        working. This cannot be undone.
                                    </DialogDescription>
                                    <DialogFooter className="gap-2">
                                        <DialogClose asChild>
                                            <Button variant="secondary">
                                                Cancel
                                            </Button>
                                        </DialogClose>
                                        <Form
                                            {...ApiTokenController.destroy.form(
                                                token.id,
                                            )}
                                            options={{ preserveScroll: true }}
                                        >
                                            {({ processing }) => (
                                                <Button
                                                    type="submit"
                                                    variant="destructive"
                                                    disabled={processing}
                                                >
                                                    {processing
                                                        ? 'Revoking...'
                                                        : 'Revoke token'}
                                                </Button>
                                            )}
                                        </Form>
                                    </DialogFooter>
                                </DialogContent>
                            </Dialog>
                        </div>
                    ))
                )}
            </div>

            {apiTokens.length > 1 && (
                <Dialog>
                    <DialogTrigger asChild>
                        <Button variant="outline">Revoke all tokens</Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogTitle>Revoke every API token?</DialogTitle>
                        <DialogDescription>
                            All connected clients will immediately lose access.
                            You will need to create new tokens manually.
                        </DialogDescription>
                        <DialogFooter className="gap-2">
                            <DialogClose asChild>
                                <Button variant="secondary">Cancel</Button>
                            </DialogClose>
                            <Form
                                {...ApiTokenController.destroyAll.form()}
                                options={{ preserveScroll: true }}
                            >
                                {({ processing }) => (
                                    <Button
                                        type="submit"
                                        variant="destructive"
                                        disabled={processing}
                                    >
                                        {processing
                                            ? 'Revoking...'
                                            : 'Revoke all'}
                                    </Button>
                                )}
                            </Form>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            )}
        </div>
    );
}

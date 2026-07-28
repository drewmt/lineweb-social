import { Head } from '@inertiajs/react';
import {
    Boxes,
    Check,
    CircleAlert,
    CodeXml,
    Database,
    FileCode2,
    FileClock,
    FileUp,
    HardDrive,
    LockKeyhole,
    Power,
    PackageCheck,
    ShieldCheck,
    TerminalSquare,
} from 'lucide-react';
import { cn } from '@/lib/utils';

type Status = 'compatible' | 'duplicate' | 'incompatible' | 'invalid';

type Author = {
    name: string;
    url?: string;
};

type Extension = {
    key: string;
    id: string | null;
    name: string;
    version: string | null;
    core: string | null;
    license: string | null;
    authors: Author[];
    permissions: string[];
    uiSlots: string[];
    provider: string | null;
    database: {
        migrations: string | null;
        uninstallData: string;
    };
    assets: {
        styles: string[];
        scripts: string[];
    };
    assetPlan: {
        status: 'none' | 'unpublished' | 'published' | 'blocked';
        message: string;
        declared: number;
        published: number;
        blocked: number;
        release: string | null;
        items: {
            type: 'style' | 'script';
            path: string;
            bytes: number;
            status: 'unpublished' | 'published' | 'blocked';
        }[];
    } | null;
    migrations: {
        status: 'none' | 'pending' | 'applied' | 'blocked';
        message: string;
        declared: number;
        applied: number;
        pending: number;
        blocked: number;
        uninstallData: string;
        items: {
            name: string;
            status: 'applied' | 'pending' | 'changed';
            batch: number | null;
            appliedAt: string | null;
        }[];
    } | null;
    status: Status;
    message: string;
    active: boolean;
};

type Props = {
    coreVersion: string;
    summary: {
        discovered: number;
        active: number;
        compatible: number;
        actionRequired: number;
        migrationPending: number;
        migrationBlocked: number;
        retainedData: number;
        assetsPublished: number;
        assetsAttention: number;
    };
    retainedExtensionIds: string[];
    extensions: Extension[];
};

const statusLabel: Record<Status, string> = {
    compatible: 'Compatible',
    duplicate: 'Duplicate ID',
    incompatible: 'Needs update',
    invalid: 'Invalid manifest',
};

export default function AdminExtensions({
    coreVersion,
    summary,
    retainedExtensionIds,
    extensions,
}: Props) {
    return (
        <>
            <Head title="Extension center" />
            <main className="relative z-[1] mx-auto w-full max-w-[88rem] px-3 py-4 sm:px-6 sm:py-7 xl:px-8">
                <header className="overflow-hidden rounded-[1.7rem] border border-foreground bg-foreground px-5 py-6 text-background sm:px-8 sm:py-8">
                    <div className="flex flex-col gap-7 lg:flex-row lg:items-end lg:justify-between">
                        <div className="max-w-3xl">
                            <p className="inline-flex items-center gap-2 text-[0.68rem] font-extrabold tracking-[0.15em] text-mint uppercase">
                                <span className="size-1.5 rounded-full bg-mint" />
                                Deploy-time trust boundary
                            </p>
                            <h1 className="mt-3 text-3xl font-black tracking-[-0.045em] sm:text-4xl">
                                Know what can run.
                            </h1>
                            <p className="mt-3 max-w-2xl text-sm leading-6 text-background/68 sm:text-base">
                                Review local manifests, schema readiness, and
                                immutable browser assets. Only providers
                                selected in deploy configuration can run.
                            </p>
                        </div>
                        <div className="flex w-fit items-center gap-3 rounded-2xl border border-white/12 bg-white/[0.07] px-4 py-3">
                            <span className="flex size-10 items-center justify-center rounded-xl bg-mint text-foreground">
                                <CodeXml
                                    className="size-5"
                                    aria-hidden="true"
                                />
                            </span>
                            <span>
                                <span className="block text-[0.65rem] font-extrabold tracking-[0.12em] text-background/55 uppercase">
                                    Core release
                                </span>
                                <span className="mt-0.5 block font-mono text-sm font-black">
                                    {coreVersion}
                                </span>
                            </span>
                        </div>
                    </div>
                </header>

                <section
                    aria-label="Extension readiness"
                    className="mt-5 grid grid-cols-2 gap-3 lg:grid-cols-4"
                >
                    <SummaryCard
                        label="Discovered"
                        value={summary.discovered}
                        detail="Local manifests"
                        icon={Boxes}
                    />
                    <SummaryCard
                        label="Active"
                        value={summary.active}
                        detail="Deploy-approved"
                        icon={Power}
                    />
                    <SummaryCard
                        label="Compatible"
                        value={summary.compatible}
                        detail="Ready for this core"
                        icon={ShieldCheck}
                    />
                    <SummaryCard
                        label="Schema pending"
                        value={summary.migrationPending}
                        detail="Awaiting deploy"
                        icon={FileClock}
                        warning={summary.migrationPending > 0}
                    />
                    <SummaryCard
                        label="Schema blocked"
                        value={summary.migrationBlocked}
                        detail="Integrity issue"
                        icon={CircleAlert}
                        warning={summary.migrationBlocked > 0}
                    />
                    <SummaryCard
                        label="Retained data"
                        value={summary.retainedData}
                        detail="Source removed"
                        icon={HardDrive}
                    />
                    <SummaryCard
                        label="Assets published"
                        value={summary.assetsPublished}
                        detail="Immutable files"
                        icon={PackageCheck}
                    />
                    <SummaryCard
                        label="Asset attention"
                        value={summary.assetsAttention}
                        detail="Publish or repair"
                        icon={FileUp}
                        warning={summary.assetsAttention > 0}
                    />
                </section>

                {summary.actionRequired > 0 && (
                    <div className="mt-4 flex items-start gap-3 rounded-2xl border border-coral/45 bg-coral/[0.055] px-4 py-3.5">
                        <CircleAlert
                            className="mt-0.5 size-4.5 shrink-0"
                            aria-hidden="true"
                        />
                        <p className="text-sm leading-6">
                            <strong>{summary.actionRequired}</strong> manifest
                            {summary.actionRequired === 1
                                ? ' needs'
                                : 's need'}{' '}
                            attention before deployment.
                        </p>
                    </div>
                )}

                <div className="mt-5 grid items-start gap-5 xl:grid-cols-[minmax(0,1fr)_23rem]">
                    <section
                        className="min-w-0"
                        aria-labelledby="installed-extensions"
                    >
                        <div className="mb-3 px-1">
                            <h2
                                id="installed-extensions"
                                className="text-xl font-black tracking-[-0.03em]"
                            >
                                Local extensions
                            </h2>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Each manifest is checked independently so one
                                broken package cannot hide the rest.
                            </p>
                        </div>

                        {extensions.length === 0 ? (
                            <div className="social-card rounded-[1.5rem] p-8 text-center sm:p-12">
                                <span className="mx-auto flex size-12 items-center justify-center rounded-2xl bg-secondary text-muted-foreground">
                                    <Boxes
                                        className="size-5"
                                        aria-hidden="true"
                                    />
                                </span>
                                <h3 className="mt-4 text-lg font-black">
                                    No manifests found
                                </h3>
                                <p className="mx-auto mt-2 max-w-md text-sm leading-6 text-muted-foreground">
                                    Add a reviewed extension directory to one of
                                    the configured local paths, then run the
                                    inspection again.
                                </p>
                            </div>
                        ) : (
                            <div className="grid min-w-0 gap-3">
                                {extensions.map((extension) => (
                                    <ExtensionCard
                                        key={`${extension.key}-${extension.name}`}
                                        extension={extension}
                                    />
                                ))}
                            </div>
                        )}

                        {retainedExtensionIds.length > 0 && (
                            <div className="mt-4 rounded-[1.35rem] border border-border/80 bg-secondary/35 p-4 sm:p-5">
                                <div className="flex items-start gap-3">
                                    <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-background text-muted-foreground">
                                        <HardDrive
                                            className="size-4.5"
                                            aria-hidden="true"
                                        />
                                    </span>
                                    <div>
                                        <h3 className="font-black">
                                            Removed source, retained data
                                        </h3>
                                        <p className="mt-1 text-sm leading-6 text-muted-foreground">
                                            Ownership records remain for{' '}
                                            {retainedExtensionIds.join(', ')}.
                                            Data is never deleted when extension
                                            source disappears.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        )}
                    </section>

                    <aside className="social-card min-w-0 rounded-[1.5rem] p-5 xl:sticky xl:top-24">
                        <div className="flex size-11 items-center justify-center rounded-2xl bg-primary/9 text-primary">
                            <LockKeyhole
                                className="size-5"
                                aria-hidden="true"
                            />
                        </div>
                        <h2 className="mt-4 text-lg font-black tracking-[-0.025em]">
                            No browser activation
                        </h2>
                        <p className="mt-2 text-sm leading-6 text-muted-foreground">
                            Web uploads and one-click activation are not safe
                            defaults for executable PHP. Operators review,
                            install, and enable trusted source during
                            deployment.
                        </p>
                        <div className="mt-5 border-t border-border/70 pt-5">
                            <p className="text-[0.68rem] font-extrabold tracking-[0.13em] text-muted-foreground uppercase">
                                Deployment workflow
                            </p>
                            <code className="mt-3 flex items-start gap-2 rounded-xl bg-foreground px-3 py-3 text-xs leading-5 font-bold break-all whitespace-normal text-background">
                                <TerminalSquare
                                    className="size-4 shrink-0 text-mint"
                                    aria-hidden="true"
                                />
                                php artisan platform:extensions
                            </code>
                            <code className="mt-2 block rounded-xl border border-border/70 bg-secondary/55 px-3 py-3 font-mono text-[0.68rem] leading-5 font-bold break-all whitespace-normal">
                                platform:extensions:migrate trusted-extension
                            </code>
                            <code className="mt-2 block rounded-xl border border-border/70 bg-secondary/55 px-3 py-3 font-mono text-[0.68rem] leading-5 font-bold break-all whitespace-normal">
                                platform:extensions:publish-assets
                                trusted-extension
                            </code>
                            <p className="mt-3 text-xs leading-5 text-muted-foreground">
                                Verify an external backup, migrate while the
                                provider is disabled, publish its immutable
                                browser release, then activate it in deploy
                                configuration. Source and public files stay
                                checksum locked.
                            </p>
                        </div>
                    </aside>
                </div>
            </main>
        </>
    );
}

function SummaryCard({
    label,
    value,
    detail,
    icon: Icon,
    warning = false,
}: {
    label: string;
    value: number;
    detail: string;
    icon: typeof Boxes;
    warning?: boolean;
}) {
    return (
        <article
            className={cn(
                'social-card rounded-[1.35rem] p-4 sm:p-5',
                warning && 'border-coral/45 bg-coral/[0.045]',
            )}
        >
            <div className="flex items-start justify-between gap-3">
                <div>
                    <p className="text-xs font-extrabold tracking-[0.11em] text-muted-foreground uppercase">
                        {label}
                    </p>
                    <p className="mt-2 text-3xl font-black tracking-[-0.05em] tabular-nums">
                        {value.toLocaleString()}
                    </p>
                </div>
                <span
                    className={cn(
                        'flex size-10 items-center justify-center rounded-xl',
                        warning
                            ? 'bg-coral/15 text-foreground'
                            : 'bg-primary/9 text-primary',
                    )}
                >
                    <Icon className="size-4.5" aria-hidden="true" />
                </span>
            </div>
            <p className="mt-3 text-xs font-semibold text-muted-foreground">
                {detail}
            </p>
        </article>
    );
}

function ExtensionCard({ extension }: { extension: Extension }) {
    const ready = extension.status === 'compatible';
    const stateLabel = ready
        ? extension.active
            ? 'Active'
            : 'Available'
        : statusLabel[extension.status];

    return (
        <article className="social-card w-full min-w-0 overflow-hidden rounded-[1.5rem]">
            <div className="p-4 sm:p-5">
                <div className="flex items-start gap-3">
                    <span
                        className={cn(
                            'flex size-11 shrink-0 items-center justify-center rounded-2xl',
                            ready
                                ? 'bg-mint/25 text-foreground'
                                : 'bg-coral/15 text-foreground',
                        )}
                    >
                        {ready ? (
                            <Check className="size-5" aria-hidden="true" />
                        ) : (
                            <CircleAlert
                                className="size-5"
                                aria-hidden="true"
                            />
                        )}
                    </span>
                    <div className="min-w-0 flex-1">
                        <div className="flex flex-wrap items-start justify-between gap-2">
                            <div className="min-w-0">
                                <h3 className="truncate text-base font-black tracking-[-0.02em]">
                                    {extension.name}
                                </h3>
                                <p className="mt-1 font-mono text-[0.7rem] font-bold text-muted-foreground">
                                    {extension.id ?? extension.key}
                                </p>
                            </div>
                            <span
                                className={cn(
                                    'inline-flex min-h-8 items-center rounded-full border px-3 text-[0.68rem] font-extrabold',
                                    extension.active
                                        ? 'border-mint/55 bg-mint/18 text-foreground'
                                        : ready
                                          ? 'border-border bg-secondary/65 text-muted-foreground'
                                          : 'border-coral/45 bg-coral/10 text-foreground',
                                )}
                            >
                                {stateLabel}
                            </span>
                        </div>
                        <p className="mt-3 text-sm leading-6 text-muted-foreground">
                            {extension.message}
                        </p>
                        {ready && (
                            <p className="mt-1.5 text-xs font-bold text-muted-foreground">
                                {extension.active
                                    ? 'Provider registered from the explicit deploy allowlist.'
                                    : 'Compatible source is present, but its provider is not enabled.'}
                            </p>
                        )}
                    </div>
                </div>

                {extension.version && (
                    <dl className="mt-5 grid grid-cols-2 gap-3 border-t border-border/70 pt-4 sm:grid-cols-4">
                        <Metadata label="Version" value={extension.version} />
                        <Metadata label="Core" value={extension.core ?? '—'} />
                        <Metadata
                            label="License"
                            value={extension.license ?? '—'}
                        />
                        <Metadata
                            label="Authors"
                            value={
                                extension.authors
                                    .map((author) => author.name)
                                    .join(', ') || '—'
                            }
                        />
                    </dl>
                )}
            </div>

            {extension.provider && (
                <div className="grid w-full min-w-0 grid-cols-1 gap-4 border-t border-border/70 bg-secondary/35 px-4 py-4 sm:grid-cols-2 sm:px-5">
                    <DetailList
                        icon={FileCode2}
                        label="Declared provider"
                        items={[extension.provider]}
                        monospace
                    />
                    <DetailList
                        icon={ShieldCheck}
                        label="Declared access"
                        items={[
                            ...extension.permissions,
                            ...extension.uiSlots.map((slot) => `UI: ${slot}`),
                        ]}
                    />
                    <MigrationDetail extension={extension} />
                    <AssetDetail extension={extension} />
                </div>
            )}
        </article>
    );
}

function AssetDetail({ extension }: { extension: Extension }) {
    const plan = extension.assetPlan;

    if (!plan) {
        return null;
    }

    const label = {
        none: 'No browser assets',
        unpublished: `${plan.declared} awaiting publish`,
        published: `${plan.published} published`,
        blocked: 'Blocked',
    }[plan.status];

    return (
        <div className="w-full min-w-0 sm:col-span-2">
            <div className="flex min-w-0 flex-col items-start gap-2 sm:flex-row sm:items-center sm:justify-between">
                <p className="flex items-center gap-2 text-[0.65rem] font-extrabold tracking-[0.11em] text-muted-foreground uppercase">
                    <PackageCheck className="size-3.5" aria-hidden="true" />
                    Browser asset release
                </p>
                <span
                    className={cn(
                        'inline-flex min-h-7 max-w-full items-center rounded-full border px-2.5 text-[0.65rem] font-extrabold',
                        plan.status === 'blocked'
                            ? 'border-coral/45 bg-coral/10'
                            : plan.status === 'unpublished'
                              ? 'border-amber-300/70 bg-amber-100/55 text-amber-950'
                              : 'border-border bg-background text-muted-foreground',
                    )}
                >
                    {label}
                </span>
            </div>
            <p className="mt-2 text-xs leading-5 text-muted-foreground">
                {plan.message}{' '}
                {plan.release && (
                    <span className="font-mono font-bold text-foreground">
                        Release {plan.release.slice(0, 10)}
                    </span>
                )}
            </p>
            {plan.items.length > 0 && (
                <div className="mt-3 grid min-w-0 gap-1.5">
                    {plan.items.slice(0, 4).map((asset) => (
                        <div
                            key={`${asset.type}-${asset.path}`}
                            className="flex min-h-9 w-full min-w-0 items-center justify-between gap-3 rounded-lg border border-border/70 bg-background px-2.5 py-1.5"
                        >
                            <span
                                className="min-w-0 flex-1 truncate font-mono text-[0.65rem] font-bold"
                                title={asset.path}
                            >
                                {asset.path}
                            </span>
                            <span className="shrink-0 text-[0.62rem] font-extrabold text-muted-foreground uppercase">
                                {asset.type} · {formatBytes(asset.bytes)}
                            </span>
                        </div>
                    ))}
                    {plan.items.length > 4 && (
                        <p className="px-1 text-[0.65rem] font-bold text-muted-foreground">
                            +{plan.items.length - 4} more asset
                            {plan.items.length - 4 === 1 ? '' : 's'}
                        </p>
                    )}
                </div>
            )}
        </div>
    );
}

function formatBytes(bytes: number) {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    return `${(bytes / 1024).toFixed(bytes < 10240 ? 1 : 0)} KB`;
}

function MigrationDetail({ extension }: { extension: Extension }) {
    const plan = extension.migrations;

    if (!plan) {
        return null;
    }

    const statusLabel = {
        none: 'No schema changes',
        pending: `${plan.pending} pending`,
        applied: `${plan.applied} applied`,
        blocked: 'Blocked',
    }[plan.status];

    return (
        <div className="w-full min-w-0 sm:col-span-2">
            <div className="flex min-w-0 flex-col items-start gap-2 sm:flex-row sm:items-center sm:justify-between">
                <p className="flex items-center gap-2 text-[0.65rem] font-extrabold tracking-[0.11em] text-muted-foreground uppercase">
                    <Database className="size-3.5" aria-hidden="true" />
                    Database lifecycle
                </p>
                <span
                    className={cn(
                        'inline-flex min-h-7 max-w-full items-center rounded-full border px-2.5 text-[0.65rem] font-extrabold',
                        plan.status === 'blocked'
                            ? 'border-coral/45 bg-coral/10'
                            : plan.status === 'pending'
                              ? 'border-amber-300/70 bg-amber-100/55 text-amber-950'
                              : 'border-border bg-background text-muted-foreground',
                    )}
                >
                    {statusLabel}
                </span>
            </div>
            <p className="mt-2 text-xs leading-5 text-muted-foreground">
                {plan.message} Uninstall policy:{' '}
                <strong className="text-foreground">
                    retain operator data
                </strong>
                .
            </p>
            {plan.items.length > 0 && (
                <div className="mt-3 grid min-w-0 gap-1.5">
                    {plan.items.slice(0, 3).map((migration) => (
                        <div
                            key={migration.name}
                            className="flex min-h-9 w-full min-w-0 items-center justify-between gap-3 rounded-lg border border-border/70 bg-background px-2.5 py-1.5"
                        >
                            <span
                                className="min-w-0 flex-1 truncate font-mono text-[0.65rem] font-bold"
                                title={migration.name}
                            >
                                {migration.name}
                            </span>
                            <span className="shrink-0 text-[0.62rem] font-extrabold text-muted-foreground uppercase">
                                {migration.status}
                            </span>
                        </div>
                    ))}
                    {plan.items.length > 3 && (
                        <p className="px-1 text-[0.65rem] font-bold text-muted-foreground">
                            +{plan.items.length - 3} more migration
                            {plan.items.length - 3 === 1 ? '' : 's'}
                        </p>
                    )}
                </div>
            )}
        </div>
    );
}

function Metadata({ label, value }: { label: string; value: string }) {
    return (
        <div className="w-full min-w-0">
            <dt className="text-[0.65rem] font-extrabold tracking-[0.11em] text-muted-foreground uppercase">
                {label}
            </dt>
            <dd className="mt-1 truncate text-xs font-bold">{value}</dd>
        </div>
    );
}

function DetailList({
    icon: Icon,
    label,
    items,
    monospace = false,
}: {
    icon: typeof FileCode2;
    label: string;
    items: string[];
    monospace?: boolean;
}) {
    return (
        <div className="min-w-0">
            <p className="flex items-center gap-2 text-[0.65rem] font-extrabold tracking-[0.11em] text-muted-foreground uppercase">
                <Icon className="size-3.5" aria-hidden="true" />
                {label}
            </p>
            {items.length > 0 ? (
                <div className="mt-2 flex w-full min-w-0 flex-wrap gap-1.5">
                    {items.map((item) => (
                        <span
                            key={item}
                            className={cn(
                                'max-w-full truncate rounded-lg border border-border/70 bg-background px-2 py-1 text-[0.68rem] font-bold',
                                monospace && 'font-mono',
                            )}
                            title={item}
                        >
                            {item}
                        </span>
                    ))}
                </div>
            ) : (
                <p className="mt-2 text-xs font-semibold text-muted-foreground">
                    None declared
                </p>
            )}
        </div>
    );
}

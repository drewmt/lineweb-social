import { Head } from '@inertiajs/react';
import {
    Boxes,
    Check,
    CircleAlert,
    CodeXml,
    FileCode2,
    LockKeyhole,
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
    status: Status;
    message: string;
};

type Props = {
    coreVersion: string;
    summary: {
        discovered: number;
        compatible: number;
        actionRequired: number;
    };
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
                                Inspect local extension manifests and core
                                compatibility before a deployment. This screen
                                never downloads, activates, or executes
                                extension code.
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
                    className="mt-5 grid gap-3 sm:grid-cols-3"
                >
                    <SummaryCard
                        label="Discovered"
                        value={summary.discovered}
                        detail="Local manifests"
                        icon={Boxes}
                    />
                    <SummaryCard
                        label="Compatible"
                        value={summary.compatible}
                        detail="Ready for this core"
                        icon={ShieldCheck}
                    />
                    <SummaryCard
                        label="Action required"
                        value={summary.actionRequired}
                        detail="Fix before bootstrapping"
                        icon={CircleAlert}
                        warning={summary.actionRequired > 0}
                    />
                </section>

                <div className="mt-5 grid items-start gap-5 xl:grid-cols-[minmax(0,1fr)_23rem]">
                    <section aria-labelledby="installed-extensions">
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
                            <div className="grid gap-3">
                                {extensions.map((extension) => (
                                    <ExtensionCard
                                        key={`${extension.key}-${extension.name}`}
                                        extension={extension}
                                    />
                                ))}
                            </div>
                        )}
                    </section>

                    <aside className="social-card rounded-[1.5rem] p-5 xl:sticky xl:top-24">
                        <div className="flex size-11 items-center justify-center rounded-2xl bg-primary/9 text-primary">
                            <LockKeyhole
                                className="size-5"
                                aria-hidden="true"
                            />
                        </div>
                        <h2 className="mt-4 text-lg font-black tracking-[-0.025em]">
                            Deliberately read-only
                        </h2>
                        <p className="mt-2 text-sm leading-6 text-muted-foreground">
                            Web uploads and one-click activation are not safe
                            defaults for executable PHP. Operators install
                            reviewed source during deployment.
                        </p>
                        <div className="mt-5 border-t border-border/70 pt-5">
                            <p className="text-[0.68rem] font-extrabold tracking-[0.13em] text-muted-foreground uppercase">
                                Deployment check
                            </p>
                            <code className="mt-3 flex items-center gap-2 overflow-x-auto rounded-xl bg-foreground px-3 py-3 text-xs font-bold whitespace-nowrap text-background">
                                <TerminalSquare
                                    className="size-4 shrink-0 text-mint"
                                    aria-hidden="true"
                                />
                                php artisan platform:extensions
                            </code>
                            <p className="mt-3 text-xs leading-5 text-muted-foreground">
                                The command exits with failure when any manifest
                                is invalid, duplicated, or incompatible, making
                                it suitable for CI and release scripts.
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

    return (
        <article className="social-card overflow-hidden rounded-[1.5rem]">
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
                                    ready
                                        ? 'border-mint/55 bg-mint/18 text-foreground'
                                        : 'border-coral/45 bg-coral/10 text-foreground',
                                )}
                            >
                                {statusLabel[extension.status]}
                            </span>
                        </div>
                        <p className="mt-3 text-sm leading-6 text-muted-foreground">
                            {extension.message}
                        </p>
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
                <div className="grid gap-3 border-t border-border/70 bg-secondary/35 px-4 py-4 sm:grid-cols-2 sm:px-5">
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
                </div>
            )}
        </article>
    );
}

function Metadata({ label, value }: { label: string; value: string }) {
    return (
        <div className="min-w-0">
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
                <div className="mt-2 flex flex-wrap gap-1.5">
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

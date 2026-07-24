import { Head, Link } from '@inertiajs/react';
import { Download, LogOut, ShieldAlert } from 'lucide-react';
import DeleteUser from '@/components/delete-user';
import { Button } from '@/components/ui/button';
import { logout } from '@/routes';

type Props = {
    account: {
        handle: string;
        emailVerified: boolean;
    };
    deletionBlockers: Array<{
        name: string;
        manage_url: null;
    }>;
};

export default function SuspendedAccount({ account, deletionBlockers }: Props) {
    return (
        <>
            <Head title="Account access restricted" />

            <section className="rounded-[1.5rem] border bg-card p-5 shadow-sm">
                <span className="flex size-12 items-center justify-center rounded-2xl bg-destructive/10 text-destructive">
                    <ShieldAlert className="size-6" aria-hidden="true" />
                </span>
                <h2 className="mt-4 text-xl font-black tracking-tight">
                    Community access is restricted
                </h2>
                <p className="mt-2 text-sm leading-6 text-muted-foreground">
                    The account @{account.handle} cannot currently publish,
                    interact, or use the API. Contact the platform operator if
                    you believe this is a mistake.
                </p>
            </section>

            <section className="space-y-4">
                <div>
                    <h2 className="font-black tracking-tight">
                        Your account data
                    </h2>
                    <p className="mt-1 text-sm leading-6 text-muted-foreground">
                        Restricting community access does not remove your data
                        rights.
                    </p>
                </div>

                {account.emailVerified ? (
                    <Button
                        asChild
                        variant="outline"
                        className="min-h-11 w-full rounded-xl"
                    >
                        <a href="/settings/data-export">
                            <Download className="size-4" />
                            Download your data
                        </a>
                    </Button>
                ) : (
                    <p className="rounded-xl bg-secondary p-3 text-xs leading-5 text-muted-foreground">
                        Verify the account email before requesting a data export
                        or deletion.
                    </p>
                )}
            </section>

            {account.emailVerified && (
                <DeleteUser deletionBlockers={deletionBlockers} />
            )}

            {deletionBlockers.length > 0 && (
                <p className="rounded-xl border px-3 py-3 text-xs leading-5 text-muted-foreground">
                    A platform operator must help transfer the listed active
                    Spaces before account deletion can be completed.
                </p>
            )}

            <Button
                asChild
                variant="ghost"
                className="min-h-11 w-full rounded-xl"
            >
                <Link href={logout()} as="button">
                    <LogOut className="size-4" />
                    Log out
                </Link>
            </Button>
        </>
    );
}

SuspendedAccount.layout = {
    title: 'Account access restricted',
    description:
        'Community access is paused while your account data remains available.',
};

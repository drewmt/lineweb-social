import { Download, FileJson, LockKeyhole } from 'lucide-react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';

export default function PersonalDataControls() {
    return (
        <div className="space-y-6">
            <Heading
                variant="small"
                title="Your data"
                description="Take a portable copy of the account data and content you created."
            />

            <div className="overflow-hidden rounded-2xl border bg-muted/20">
                <div className="flex items-start gap-4 p-4 sm:p-5">
                    <div className="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                        <FileJson className="size-5" aria-hidden="true" />
                    </div>
                    <div className="min-w-0 flex-1">
                        <h3 className="font-extrabold tracking-tight">
                            Download JSON export
                        </h3>
                        <p className="mt-1 text-sm leading-6 text-muted-foreground">
                            Includes your profile, memberships, posts, comments,
                            reactions, follows, sent messages, reports, and safe
                            security metadata.
                        </p>
                    </div>
                </div>

                <div className="flex flex-col gap-3 border-t bg-background/60 p-4 sm:flex-row sm:items-center sm:justify-between">
                    <p className="flex items-center gap-2 text-xs leading-5 text-muted-foreground">
                        <LockKeyhole
                            className="size-3.5 shrink-0"
                            aria-hidden="true"
                        />
                        Password confirmation is required. Secrets and messages
                        sent by other people are excluded.
                    </p>
                    <Button
                        asChild
                        variant="outline"
                        className="shrink-0 rounded-full"
                    >
                        <a href="/settings/data-export">
                            <Download className="size-4" aria-hidden="true" />
                            Download data
                        </a>
                    </Button>
                </div>
            </div>
        </div>
    );
}

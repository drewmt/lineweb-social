import { useForm } from '@inertiajs/react';
import { Flag, ShieldCheck } from 'lucide-react';
import { useEffect } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

export type MessageReportReason = {
    value: string;
    label: string;
};

export type ReportableMessage = {
    id: number;
    body: string;
    reportUrl: string;
};

export function MessageReportDialog({
    message,
    reasons,
    open,
    onOpenChange,
}: {
    message: ReportableMessage | null;
    reasons: MessageReportReason[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const form = useForm({
        reason: reasons[0]?.value ?? '',
        details: '',
    });

    useEffect(() => {
        form.setData({
            reason: reasons[0]?.value ?? '',
            details: '',
        });
        form.clearErrors();
        // Reset when a different message is selected.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [message?.id]);

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (!message) {
            return;
        }

        form.post(message.reportUrl, {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[calc(100dvh-2rem)] overflow-y-auto rounded-[1.45rem] border-border/80 p-5 sm:max-w-lg sm:p-6">
                <DialogHeader>
                    <span className="mb-1 flex size-11 items-center justify-center rounded-2xl bg-coral/14 text-foreground">
                        <Flag className="size-5" aria-hidden="true" />
                    </span>
                    <DialogTitle className="text-xl font-black tracking-tight">
                        Report this message?
                    </DialogTitle>
                    <DialogDescription className="leading-6">
                        Share this exact message and only the context you add
                        with platform administrators.
                    </DialogDescription>
                </DialogHeader>

                <div className="rounded-2xl border border-border/75 bg-secondary/35 p-4">
                    <p className="text-[0.68rem] font-extrabold tracking-[0.12em] text-muted-foreground uppercase">
                        Message evidence
                    </p>
                    <p className="mt-2 max-h-28 overflow-y-auto text-sm leading-6 whitespace-pre-wrap">
                        {message?.body}
                    </p>
                </div>

                <form onSubmit={submit} className="space-y-4">
                    <label className="block text-sm font-bold">
                        Why are you reporting it?
                        <select
                            value={form.data.reason}
                            onChange={(event) =>
                                form.setData('reason', event.target.value)
                            }
                            required
                            className="social-inset social-focus mt-2 h-11 w-full px-3 text-sm"
                        >
                            {reasons.map((reason) => (
                                <option key={reason.value} value={reason.value}>
                                    {reason.label}
                                </option>
                            ))}
                        </select>
                    </label>
                    <InputError message={form.errors.reason} />

                    <label className="block text-sm font-bold">
                        Additional context{' '}
                        <span className="font-medium text-muted-foreground">
                            {form.data.reason === 'other'
                                ? '(required)'
                                : '(optional)'}
                        </span>
                        <textarea
                            value={form.data.details}
                            onChange={(event) =>
                                form.setData('details', event.target.value)
                            }
                            required={form.data.reason === 'other'}
                            maxLength={750}
                            rows={4}
                            placeholder="Explain what happened without adding unrelated private information."
                            className="social-inset social-focus mt-2 w-full resize-y px-4 py-3 text-sm leading-6"
                        />
                    </label>
                    <div className="flex items-start justify-between gap-3">
                        <InputError message={form.errors.details} />
                        <span className="ml-auto shrink-0 text-xs font-semibold text-muted-foreground">
                            {form.data.details.length} / 750
                        </span>
                    </div>

                    <div className="flex items-start gap-2.5 rounded-xl bg-primary/[0.07] px-3.5 py-3 text-xs leading-5 text-muted-foreground">
                        <ShieldCheck
                            className="mt-0.5 size-4 shrink-0 text-primary"
                            aria-hidden="true"
                        />
                        <p>
                            The sender is not notified. Administrators do not
                            receive the rest of your conversation through this
                            report.
                        </p>
                    </div>

                    <DialogFooter className="flex-row">
                        <Button
                            type="button"
                            variant="secondary"
                            onClick={() => onOpenChange(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            disabled={
                                form.processing ||
                                form.data.reason === '' ||
                                (form.data.reason === 'other' &&
                                    form.data.details.trim() === '')
                            }
                        >
                            <Flag className="size-4" aria-hidden="true" />
                            Submit report
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

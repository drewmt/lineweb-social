import { useForm } from '@inertiajs/react';
import { Ban, RotateCcw } from 'lucide-react';
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
import type { Member } from './member-row';

export function AccountActionDialog({
    member,
    action,
    query,
    filter,
    open,
    onOpenChange,
}: {
    member: Member | null;
    action: 'suspend' | 'reinstate';
    query: string;
    filter: string;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const form = useForm({ reason: '' });
    const isSuspension = action === 'suspend';
    const memberError = (
        form.errors as typeof form.errors & { member?: string }
    ).member;

    useEffect(() => {
        form.reset();
        form.clearErrors();
        // Reset the form when a different account action is opened.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [member?.id, action]);

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (!member) {
            return;
        }

        const options = {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        };
        const params = new URLSearchParams();

        if (query) {
            params.set('q', query);
        }

        if (filter !== 'all') {
            params.set('status', filter);
        }

        const url = `/admin/members/${member.handle}/suspension${
            params.size > 0 ? `?${params.toString()}` : ''
        }`;

        if (isSuspension) {
            form.put(url, options);
        } else {
            form.delete(url, options);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="rounded-[1.35rem] border-border/80 p-5 sm:max-w-lg sm:p-6">
                <DialogHeader>
                    <DialogTitle className="text-xl font-black tracking-tight">
                        {isSuspension ? 'Suspend' : 'Reinstate'}{' '}
                        {member?.name ?? 'member'}?
                    </DialogTitle>
                    <DialogDescription className="leading-6">
                        {isSuspension
                            ? 'This immediately ends active sessions and API access. Existing public content is not removed automatically.'
                            : 'This restores community and API access. Record why the account is safe to return.'}
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <label className="block text-sm font-bold">
                        Audit reason
                        <textarea
                            autoFocus
                            required
                            minLength={10}
                            maxLength={500}
                            rows={4}
                            value={form.data.reason}
                            onChange={(event) =>
                                form.setData('reason', event.target.value)
                            }
                            placeholder={
                                isSuspension
                                    ? 'Describe the policy or safety reason.'
                                    : 'Describe the completed review or resolution.'
                            }
                            className="social-inset social-focus mt-2 w-full resize-y px-4 py-3 text-sm leading-6"
                        />
                    </label>
                    <div className="flex items-start justify-between gap-3">
                        <div>
                            <InputError message={form.errors.reason} />
                            <InputError message={memberError} />
                            {!form.errors.reason && !memberError && (
                                <span className="text-xs font-semibold text-muted-foreground">
                                    {form.data.reason.length} / 500
                                </span>
                            )}
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
                                variant={
                                    isSuspension ? 'destructive' : 'default'
                                }
                                disabled={
                                    form.processing ||
                                    form.data.reason.trim().length < 10
                                }
                            >
                                {isSuspension ? (
                                    <Ban className="size-4" />
                                ) : (
                                    <RotateCcw className="size-4" />
                                )}
                                {isSuspension
                                    ? 'Suspend account'
                                    : 'Restore access'}
                            </Button>
                        </DialogFooter>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}

import { router, useForm } from '@inertiajs/react';
import { MoreHorizontal, Pencil, Trash2 } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

type AuthoredContentMenuProps = {
    body: string;
    canEdit: boolean;
    canDelete: boolean;
    contentType: 'post' | 'comment';
    updateUrl: string;
    deleteUrl: string;
    maxLength: number;
    compact?: boolean;
};

export function AuthoredContentMenu({
    body,
    canEdit,
    canDelete,
    contentType,
    updateUrl,
    deleteUrl,
    maxLength,
    compact = false,
}: AuthoredContentMenuProps) {
    const [editing, setEditing] = useState(false);
    const [confirmingDelete, setConfirmingDelete] = useState(false);
    const [deleteError, setDeleteError] = useState<string | null>(null);
    const { data, setData, patch, processing, errors, clearErrors } = useForm<{
        body: string;
        content?: string;
    }>({ body });
    const noun = contentType === 'post' ? 'post' : 'comment';

    if (!canEdit && !canDelete) {
        return null;
    }

    const openEditor = () => {
        setData('body', body);
        clearErrors();
        setEditing(true);
    };

    const submitEdit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        patch(updateUrl, {
            preserveScroll: true,
            onSuccess: () => setEditing(false),
        });
    };

    const destroy = () => {
        setDeleteError(null);
        router.delete(deleteUrl, {
            preserveScroll: true,
            onSuccess: () => setConfirmingDelete(false),
            onError: (responseErrors) => {
                setDeleteError(
                    typeof responseErrors.content === 'string'
                        ? responseErrors.content
                        : `The ${noun} could not be deleted.`,
                );
            },
        });
    };

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <button
                        type="button"
                        aria-label={`Manage your ${noun}`}
                        className={`social-focus inline-flex shrink-0 items-center justify-center rounded-xl text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground ${
                            compact ? '-my-1.5 size-11' : 'size-11'
                        }`}
                    >
                        <MoreHorizontal
                            className={compact ? 'size-4' : 'size-[1.1rem]'}
                            aria-hidden="true"
                        />
                    </button>
                </DropdownMenuTrigger>
                <DropdownMenuContent
                    align="end"
                    className="w-44 rounded-xl p-1.5"
                >
                    {canEdit && (
                        <DropdownMenuItem
                            onSelect={openEditor}
                            className="min-h-11 rounded-lg font-semibold"
                        >
                            <Pencil aria-hidden="true" />
                            Edit {noun}
                        </DropdownMenuItem>
                    )}
                    {canDelete && (
                        <DropdownMenuItem
                            variant="destructive"
                            onSelect={() => {
                                setDeleteError(null);
                                setConfirmingDelete(true);
                            }}
                            className="min-h-11 rounded-lg font-semibold"
                        >
                            <Trash2 aria-hidden="true" />
                            Delete {noun}
                        </DropdownMenuItem>
                    )}
                </DropdownMenuContent>
            </DropdownMenu>

            <Dialog open={editing} onOpenChange={setEditing}>
                <DialogContent className="rounded-[1.35rem] border-border/80 p-5 sm:max-w-xl sm:p-6">
                    <DialogHeader>
                        <DialogTitle className="text-xl font-black tracking-tight">
                            Edit {noun}
                        </DialogTitle>
                        <DialogDescription className="leading-6">
                            Keep the conversation clear. An edited label will be
                            shown after you save.
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={submitEdit} className="space-y-4">
                        <label className="block">
                            <span className="sr-only">Updated {noun}</span>
                            <textarea
                                autoFocus
                                value={data.body}
                                onChange={(event) =>
                                    setData('body', event.target.value)
                                }
                                maxLength={maxLength}
                                rows={contentType === 'post' ? 7 : 4}
                                required
                                className="social-inset social-focus w-full resize-y px-4 py-3 text-[0.95rem] leading-7"
                            />
                        </label>
                        <div className="flex items-center justify-between gap-3">
                            <div>
                                <InputError
                                    message={errors.body ?? errors.content}
                                />
                                {!errors.body && !errors.content && (
                                    <span className="text-xs font-semibold text-muted-foreground">
                                        {data.body.length.toLocaleString()} /{' '}
                                        {maxLength.toLocaleString()}
                                    </span>
                                )}
                            </div>
                            <DialogFooter className="flex-row">
                                <DialogClose asChild>
                                    <Button
                                        type="button"
                                        variant="secondary"
                                        className="h-11"
                                    >
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button
                                    type="submit"
                                    className="h-11"
                                    disabled={
                                        processing ||
                                        data.body.trim() === '' ||
                                        data.body.trim() === body
                                    }
                                >
                                    Save changes
                                </Button>
                            </DialogFooter>
                        </div>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog open={confirmingDelete} onOpenChange={setConfirmingDelete}>
                <DialogContent className="rounded-[1.35rem] border-border/80 p-5 sm:max-w-md sm:p-6">
                    <DialogHeader>
                        <DialogTitle className="text-xl font-black tracking-tight">
                            Delete this {noun}?
                        </DialogTitle>
                        <DialogDescription className="leading-6">
                            This permanently removes it
                            {contentType === 'post'
                                ? ' together with its comments'
                                : ''}{' '}
                            and cannot be undone.
                        </DialogDescription>
                    </DialogHeader>
                    {deleteError && (
                        <p className="rounded-xl bg-destructive/10 px-3 py-2 text-sm font-semibold text-destructive">
                            {deleteError}
                        </p>
                    )}
                    <DialogFooter className="flex-row">
                        <DialogClose asChild>
                            <Button
                                type="button"
                                variant="secondary"
                                className="h-11"
                            >
                                Keep {noun}
                            </Button>
                        </DialogClose>
                        <Button
                            type="button"
                            variant="destructive"
                            onClick={destroy}
                            className="h-11"
                        >
                            Delete {noun}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

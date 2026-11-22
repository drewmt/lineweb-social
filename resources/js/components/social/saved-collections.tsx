import { Link, router, useForm } from '@inertiajs/react';
import {
    ChevronLeft,
    ChevronRight,
    Folder,
    FolderOpen,
    Pencil,
    Plus,
    Trash2,
} from 'lucide-react';
import { useId, useState } from 'react';
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
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Collection = {
    id: number;
    name: string;
};

export type SavedLibrary = {
    collections: Collection[];
    collectionLimit: number;
    activeCollection: Collection | null;
    pagination: { page: number; previous: string | null; next: string | null };
};

const collectionUrl = (id: number) => `/saved/collections/${id}`;

function CollectionNameDialog({
    collection,
    disabled,
}: {
    collection?: Collection;
    disabled?: boolean;
}) {
    const [open, setOpen] = useState(false);
    const nameId = useId();
    const errorId = useId();
    const form = useForm({ name: collection?.name ?? '' });
    const title = collection ? 'Rename collection' : 'New collection';
    const Icon = collection ? Pencil : Plus;

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        const options = { onSuccess: () => setOpen(false) };

        if (collection) {
            form.patch(collectionUrl(collection.id), options);
        } else {
            form.post('/saved/collections', options);
        }
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(value) => {
                if (value) {
                    form.setData('name', collection?.name ?? '');
                    form.clearErrors();
                }

                setOpen(value);
            }}
        >
            <DialogTrigger asChild>
                <Button
                    type="button"
                    variant={collection ? 'ghost' : 'outline'}
                    disabled={disabled}
                >
                    <Icon aria-hidden="true" />
                    {title}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>
                        {collection
                            ? 'Renaming keeps all the posts in this collection.'
                            : 'Only you can see your collections and the posts you organize in them.'}
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="space-y-2">
                        <Label htmlFor={nameId}>Collection name</Label>
                        <Input
                            id={nameId}
                            value={form.data.name}
                            onChange={(event) =>
                                form.setData('name', event.target.value)
                            }
                            required
                            maxLength={80}
                            aria-invalid={Boolean(form.errors.name)}
                            aria-describedby={
                                form.errors.name ? errorId : undefined
                            }
                        />
                        <InputError id={errorId} message={form.errors.name} />
                    </div>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                Cancel
                            </Button>
                        </DialogClose>
                        <Button
                            type="submit"
                            disabled={form.processing || !form.data.name.trim()}
                        >
                            {collection ? 'Save name' : 'Create collection'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function DeleteCollectionDialog({ collection }: { collection: Collection }) {
    const form = useForm({});

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.delete(collectionUrl(collection.id));
    };

    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button type="button" variant="ghost">
                    <Trash2 aria-hidden="true" />
                    Delete collection
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete collection?</DialogTitle>
                    <DialogDescription>
                        <strong className="font-semibold break-words">
                            {collection.name}
                        </strong>{' '}
                        will be removed. Its posts will stay in All saved and in
                        any other collections.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit}>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                Cancel
                            </Button>
                        </DialogClose>
                        <Button
                            type="submit"
                            variant="destructive"
                            disabled={form.processing}
                        >
                            Delete collection
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export function SavedLibraryControls({
    activeCollection,
    collections,
    collectionLimit,
}: SavedLibrary) {
    const atLimit = collections.length >= collectionLimit;
    const items = [
        { id: null, name: 'All saved', url: '/saved' },
        ...collections.map(({ id, name }) => ({
            id,
            name,
            url: collectionUrl(id),
        })),
    ];

    return (
        <section
            aria-labelledby="saved-collections-heading"
            className="social-card mb-4 space-y-3 rounded-[1.35rem] p-3 sm:mb-5 sm:p-4"
        >
            <div className="flex flex-wrap items-center justify-between gap-2">
                <h2
                    id="saved-collections-heading"
                    tabIndex={-1}
                    className="ml-1 text-sm font-extrabold"
                >
                    Collections
                </h2>
                <CollectionNameDialog disabled={atLimit} />
            </div>
            {atLimit && (
                <p className="px-1 text-sm text-muted-foreground">
                    You can create up to {collectionLimit} collections.
                </p>
            )}
            <nav
                aria-label="Saved collections"
                className="flex flex-wrap gap-1.5"
            >
                {items.map(({ id, name, url }) => {
                    const current = id === (activeCollection?.id ?? null);

                    return (
                        <Link
                            key={id ?? 'all'}
                            href={url}
                            aria-current={current ? 'page' : undefined}
                            className={`social-focus flex min-h-11 max-w-full items-center gap-2 rounded-xl px-3 py-2 text-sm font-bold transition-colors ${
                                current
                                    ? 'bg-primary/10 text-primary'
                                    : 'bg-secondary/70 text-muted-foreground hover:bg-secondary hover:text-foreground'
                            }`}
                        >
                            {id !== null && (
                                <Folder
                                    className="size-4 shrink-0"
                                    aria-hidden="true"
                                />
                            )}
                            <span className="min-w-0 break-words">{name}</span>
                        </Link>
                    );
                })}
            </nav>
            {activeCollection && (
                <div className="flex flex-wrap gap-1 border-t border-border pt-2">
                    <CollectionNameDialog
                        key={activeCollection.id}
                        collection={activeCollection}
                    />
                    <DeleteCollectionDialog
                        key={`delete-${activeCollection.id}`}
                        collection={activeCollection}
                    />
                </div>
            )}
        </section>
    );
}

export function SavedLibraryPagination({
    pagination,
}: {
    pagination: SavedLibrary['pagination'];
}) {
    const { page, previous, next } = pagination;

    if (!previous && !next) {
        return null;
    }

    return (
        <nav
            aria-label="Saved posts pagination"
            className="mt-4 flex flex-wrap items-center gap-2"
        >
            <span className="mr-auto px-1 text-sm font-semibold text-muted-foreground">
                Page {page}
            </span>
            {previous && (
                <Button asChild variant="outline">
                    <Link href={previous} rel="prev">
                        <ChevronLeft aria-hidden="true" />
                        Previous page
                    </Link>
                </Button>
            )}
            {next && (
                <Button asChild variant="outline">
                    <Link href={next} rel="next">
                        Next page
                        <ChevronRight aria-hidden="true" />
                    </Link>
                </Button>
            )}
        </nav>
    );
}

export function SavedCollectionAssignment({
    library,
    postId,
    selectedIds,
}: {
    library: SavedLibrary;
    postId: number;
    selectedIds: number[];
}) {
    const [pending, setPending] = useState(false);
    const { collections, activeCollection } = library;

    const change = (collectionId: number, assigned: boolean) => {
        const url = `${collectionUrl(collectionId)}/posts/${postId}`;
        const options = {
            preserveScroll: true,
            onStart: () => setPending(true),
            onFinish: () => setPending(false),
            onSuccess: () => {
                if (!assigned && activeCollection?.id === collectionId) {
                    document
                        .getElementById('saved-collections-heading')
                        ?.focus({
                            preventScroll: true,
                        });
                }
            },
        };

        if (assigned) {
            router.put(url, {}, options);
        } else {
            router.delete(url, options);
        }
    };

    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    className="text-xs text-muted-foreground"
                >
                    <FolderOpen className="size-3.5" aria-hidden="true" />
                    Collections
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[85dvh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Organize saved post</DialogTitle>
                    <DialogDescription>
                        {collections.length
                            ? 'Choose one or more private collections. Changes are saved as you go.'
                            : 'Create a collection on the Saved page first, then choose where this post belongs.'}
                    </DialogDescription>
                </DialogHeader>
                {collections.length > 0 && (
                    <fieldset
                        className="min-w-0 space-y-1"
                        disabled={pending}
                        aria-busy={pending}
                    >
                        <legend className="sr-only">Private collections</legend>
                        {collections.map(({ id, name }) => (
                            <label
                                key={id}
                                className="flex min-h-11 cursor-pointer items-center gap-3 rounded-xl bg-secondary/75 px-3 py-2 text-sm font-semibold hover:bg-secondary"
                            >
                                <input
                                    type="checkbox"
                                    checked={selectedIds.includes(id)}
                                    onChange={(event) =>
                                        change(id, event.target.checked)
                                    }
                                    className="social-focus size-4 shrink-0 accent-primary"
                                />
                                <span className="min-w-0 break-words">
                                    {name}
                                </span>
                            </label>
                        ))}
                    </fieldset>
                )}
                <DialogFooter>
                    <DialogClose asChild>
                        <Button type="button" variant="outline">
                            Done
                        </Button>
                    </DialogClose>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

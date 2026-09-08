import AuthenticatedLayout from '@/layouts/authenticated-layout';
import type { Book } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import type { FormEventHandler } from 'react';

export default function BookShow({ book }: { book: Book }) {
    const { auth, flash } = usePage().props;
    const canManage =
        auth.user?.role === 'admin' || auth.user?.role === 'librarian';

    const destroy = () => {
        if (confirm(`Delete "${book.title}"?`)) {
            router.delete(`/books/${book.id}`);
        }
    };

    return (
        <AuthenticatedLayout>
            <Head title={book.title} />

            {flash?.status && (
                <div className="mb-4 text-sm font-medium text-green-600">
                    {flash.status}
                </div>
            )}
            {flash?.error && (
                <div className="mb-4 text-sm font-medium text-red-600">
                    {flash.error}
                </div>
            )}

            <div className="flex items-start justify-between">
                <div>
                    <h1 className="text-xl font-medium">{book.title}</h1>
                    <p className="text-[#706f6c] dark:text-[#A1A09A]">
                        {book.author}
                    </p>
                </div>

                {canManage && (
                    <div className="flex gap-2">
                        <Link
                            href={`/books/${book.id}/edit`}
                            className="rounded-sm border border-[#e3e3e0] px-4 py-2 text-sm dark:border-[#3E3E3A]"
                        >
                            Edit
                        </Link>
                        <button
                            onClick={destroy}
                            className="rounded-sm border border-red-600 px-4 py-2 text-sm text-red-600"
                        >
                            Delete
                        </button>
                    </div>
                )}
            </div>

            <dl className="mt-6 grid grid-cols-2 gap-4 text-sm">
                <div>
                    <dt className="text-[#706f6c] dark:text-[#A1A09A]">
                        Availability
                    </dt>
                    <dd className="font-medium">
                        {book.available_copies}/{book.total_copies} copies
                        available
                    </dd>
                </div>
                {book.category && (
                    <div>
                        <dt className="text-[#706f6c] dark:text-[#A1A09A]">
                            Category
                        </dt>
                        <dd>{book.category}</dd>
                    </div>
                )}
                {book.publisher && (
                    <div>
                        <dt className="text-[#706f6c] dark:text-[#A1A09A]">
                            Publisher
                        </dt>
                        <dd>{book.publisher}</dd>
                    </div>
                )}
                {book.published_year && (
                    <div>
                        <dt className="text-[#706f6c] dark:text-[#A1A09A]">
                            Published
                        </dt>
                        <dd>{book.published_year}</dd>
                    </div>
                )}
                {book.isbn && (
                    <div>
                        <dt className="text-[#706f6c] dark:text-[#A1A09A]">
                            ISBN
                        </dt>
                        <dd>{book.isbn}</dd>
                    </div>
                )}
            </dl>

            {book.description && (
                <p className="mt-6 text-sm whitespace-pre-line">
                    {book.description}
                </p>
            )}

            {canManage && <AdjustInventory book={book} />}
        </AuthenticatedLayout>
    );
}

function AdjustInventory({ book }: { book: Book }) {
    const { data, setData, put, processing, errors } = useForm({
        total_copies: book.total_copies,
    });

    const onLoan = book.total_copies - book.available_copies;

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(`/books/${book.id}/inventory`, { preserveScroll: true });
    };

    return (
        <section className="mt-10 max-w-md rounded-sm border border-[#e3e3e0] p-4 dark:border-[#3E3E3A]">
            <h2 className="text-sm font-medium">Adjust Inventory</h2>
            <p className="mt-1 text-xs text-[#706f6c] dark:text-[#A1A09A]">
                {book.total_copies} total &middot; {book.available_copies}{' '}
                available &middot; {onLoan} on loan
            </p>

            <form onSubmit={submit} className="mt-4 space-y-3">
                <div>
                    <label
                        htmlFor="total_copies"
                        className="mb-1 block text-sm font-medium"
                    >
                        New total copies
                    </label>
                    <input
                        id="total_copies"
                        type="number"
                        min={0}
                        value={data.total_copies}
                        onChange={(e) =>
                            setData('total_copies', Number(e.target.value))
                        }
                        className="mt-1 w-full rounded-sm border border-[#e3e3e0] bg-transparent px-3 py-2 text-sm dark:border-[#3E3E3A]"
                    />
                    {errors.total_copies && (
                        <p className="mt-1 text-sm text-red-600">
                            {errors.total_copies}
                        </p>
                    )}
                </div>

                <button
                    type="submit"
                    disabled={processing}
                    className="rounded-sm bg-[#1b1b18] px-4 py-2 text-sm text-white hover:bg-black disabled:opacity-50 dark:bg-[#eeeeec] dark:text-[#1C1C1A]"
                >
                    Update Inventory
                </button>
            </form>
        </section>
    );
}

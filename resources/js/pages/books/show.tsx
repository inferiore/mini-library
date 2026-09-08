import AuthenticatedLayout from '@/layouts/authenticated-layout';
import type { Book } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';

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
        </AuthenticatedLayout>
    );
}

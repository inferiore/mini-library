import AuthenticatedLayout from '@/layouts/authenticated-layout';
import type { PaginatedBooks } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';

export default function BooksIndex({ books }: { books: PaginatedBooks }) {
    const { auth } = usePage().props;
    const canManage =
        auth.user?.role === 'admin' || auth.user?.role === 'librarian';

    return (
        <AuthenticatedLayout>
            <Head title="Books" />

            <div className="mb-6 flex items-center justify-between">
                <h1 className="text-xl font-medium">Books</h1>
                {canManage && (
                    <Link
                        href="/books/create"
                        className="rounded-sm bg-[#1b1b18] px-4 py-2 text-sm text-white hover:bg-black dark:bg-[#eeeeec] dark:text-[#1C1C1A]"
                    >
                        Add Book
                    </Link>
                )}
            </div>

            <div className="divide-y divide-[#e3e3e0] dark:divide-[#3E3E3A]">
                {books.data.map((book) => (
                    <Link
                        key={book.id}
                        href={`/books/${book.id}`}
                        className="flex items-center justify-between py-3 hover:bg-black/5 dark:hover:bg-white/5"
                    >
                        <div>
                            <p className="font-medium">{book.title}</p>
                            <p className="text-sm text-[#706f6c] dark:text-[#A1A09A]">
                                {book.author}{' '}
                                {book.category ? `· ${book.category}` : ''}
                            </p>
                        </div>
                        <span className="text-sm text-[#706f6c] dark:text-[#A1A09A]">
                            {book.available_copies}/{book.total_copies}{' '}
                            available
                        </span>
                    </Link>
                ))}

                {books.data.length === 0 && (
                    <p className="py-6 text-sm text-[#706f6c] dark:text-[#A1A09A]">
                        No books yet.
                    </p>
                )}
            </div>

            {books.last_page > 1 && (
                <div className="mt-6 flex flex-wrap gap-2 text-sm">
                    {books.links.map((link, i) => (
                        <Link
                            key={i}
                            href={link.url ?? '#'}
                            preserveScroll
                            className={`rounded-sm border px-3 py-1 ${
                                link.active
                                    ? 'border-[#1b1b18] font-medium dark:border-[#eeeeec]'
                                    : 'border-[#e3e3e0] text-[#706f6c] dark:border-[#3E3E3A] dark:text-[#A1A09A]'
                            } ${!link.url ? 'pointer-events-none opacity-40' : ''}`}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ))}
                </div>
            )}
        </AuthenticatedLayout>
    );
}

import AuthenticatedLayout from '@/layouts/authenticated-layout';
import type { BookFilters, PaginatedBooks } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

type Props = {
    books: PaginatedBooks;
    filters: BookFilters;
    categories: string[];
    publishers: string[];
};

export default function BooksIndex({
    books,
    filters,
    categories,
    publishers,
}: Props) {
    const { auth } = usePage().props;
    const canManage =
        auth.user?.role === 'admin' || auth.user?.role === 'librarian';

    const [query, setQuery] = useState(filters.query ?? '');
    const isFirstRender = useRef(true);

    // Reload results server-side with the current filters. Empty values are
    // omitted so the URL stays clean and bookmarkable/shareable. preserveState
    // keeps the input focused/typed as results come back.
    const reload = (next: Partial<BookFilters>) => {
        const params: Record<string, string> = {};
        const merged = { ...filters, query, ...next };

        if (merged.query.trim() !== '') params.query = merged.query;
        if (merged.category !== '') params.category = merged.category;
        if (merged.publisher !== '') params.publisher = merged.publisher;

        router.get('/books', params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    // Debounce free-text query changes so we don't round-trip on every keypress.
    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;
            return;
        }

        const timeout = setTimeout(() => reload({ query }), 300);

        return () => clearTimeout(timeout);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [query]);

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

            <div className="mb-6 flex flex-col gap-3 sm:flex-row">
                <input
                    type="search"
                    value={query}
                    onChange={(e) => setQuery(e.target.value)}
                    placeholder="Search by title, author, ISBN…"
                    aria-label="Search books"
                    className="flex-1 rounded-sm border border-[#e3e3e0] bg-transparent px-3 py-2 text-sm dark:border-[#3E3E3A]"
                />
                <select
                    value={filters.category ?? ''}
                    onChange={(e) => reload({ category: e.target.value })}
                    aria-label="Filter by category"
                    className="rounded-sm border border-[#e3e3e0] bg-transparent px-3 py-2 text-sm dark:border-[#3E3E3A]"
                >
                    <option value="">All categories</option>
                    {categories.map((category) => (
                        <option key={category} value={category}>
                            {category}
                        </option>
                    ))}
                </select>
                <select
                    value={filters.publisher ?? ''}
                    onChange={(e) => reload({ publisher: e.target.value })}
                    aria-label="Filter by publisher"
                    className="rounded-sm border border-[#e3e3e0] bg-transparent px-3 py-2 text-sm dark:border-[#3E3E3A]"
                >
                    <option value="">All publishers</option>
                    {publishers.map((publisher) => (
                        <option key={publisher} value={publisher}>
                            {publisher}
                        </option>
                    ))}
                </select>
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
                        No books found.
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
                            preserveState
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

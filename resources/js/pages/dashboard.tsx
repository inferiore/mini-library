import AuthenticatedLayout from '@/layouts/authenticated-layout';
import type { Recommendation, RecommendationResult } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { useState } from 'react';

function xsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

export default function Dashboard() {
    const { auth } = usePage().props;

    return (
        <AuthenticatedLayout>
            <Head title="Dashboard" />
            <h1 className="text-xl font-medium">Welcome, {auth.user?.name}</h1>
            <p className="mt-2 text-sm text-[#706f6c] dark:text-[#A1A09A]">
                The book catalog, loans, and other role-specific views land here
                in later specs.
            </p>

            <AiRecommendations />
        </AuthenticatedLayout>
    );
}

function AiRecommendations() {
    const [query, setQuery] = useState('');
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [result, setResult] = useState<RecommendationResult | null>(null);

    const submit = async (event: React.FormEvent) => {
        event.preventDefault();

        if (query.trim().length < 3) {
            setError('Please describe what you would like to read (at least 3 characters).');

            return;
        }

        setLoading(true);
        setError(null);
        setResult(null);

        try {
            const response = await fetch('/recommendations', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': xsrfToken(),
                },
                credentials: 'same-origin',
                body: JSON.stringify({ query }),
            });

            if (!response.ok) {
                const body = await response.json().catch(() => null);
                setError(
                    body?.message ??
                        'Something went wrong generating recommendations. Please try again.',
                );

                return;
            }

            setResult((await response.json()) as RecommendationResult);
        } catch {
            setError('Something went wrong generating recommendations. Please try again.');
        } finally {
            setLoading(false);
        }
    };

    return (
        <section className="mt-10 rounded-md border border-[#e3e3e0] p-6 dark:border-[#3E3E3A]">
            <h2 className="text-lg font-medium">AI Recommendations</h2>
            <p className="mt-1 text-sm text-[#706f6c] dark:text-[#A1A09A]">
                Describe what you would like to read, in your own words. Suggestions
                come from our actual catalog.
            </p>

            <form onSubmit={submit} className="mt-4 flex flex-col gap-3 sm:flex-row">
                <input
                    type="text"
                    value={query}
                    onChange={(event) => setQuery(event.target.value)}
                    placeholder="e.g. a practical book on software architecture for senior devs"
                    aria-label="Describe what you would like to read"
                    maxLength={500}
                    className="flex-1 rounded-sm border border-[#e3e3e0] bg-transparent px-3 py-2 text-sm dark:border-[#3E3E3A]"
                />
                <button
                    type="submit"
                    disabled={loading}
                    className="rounded-sm bg-[#1b1b18] px-4 py-2 text-sm text-white hover:bg-black disabled:opacity-50 dark:bg-[#eeeeec] dark:text-[#1C1C1A]"
                >
                    {loading ? 'Thinking…' : 'Recommend'}
                </button>
            </form>

            {error && (
                <p className="mt-4 text-sm font-medium text-red-600">{error}</p>
            )}

            {loading && (
                <p className="mt-4 text-sm text-[#706f6c] dark:text-[#A1A09A]">
                    Finding books that match…
                </p>
            )}

            {result && result.status === 'no_matches' && (
                <p className="mt-4 text-sm text-[#706f6c] dark:text-[#A1A09A]">
                    No matching books found. Try describing what you want to read a
                    little differently.
                </p>
            )}

            {result && result.status === 'ok' && (
                <ul className="mt-4 flex flex-col gap-3">
                    {result.recommendations.map((book) => (
                        <RecommendationCard key={book.id} book={book} />
                    ))}
                </ul>
            )}
        </section>
    );
}

function RecommendationCard({ book }: { book: Recommendation }) {
    return (
        <li className="rounded-md border border-[#e3e3e0] p-4 dark:border-[#3E3E3A]">
            <div className="flex items-start justify-between gap-4">
                <div>
                    <Link
                        href={`/books/${book.id}`}
                        className="font-medium hover:underline"
                    >
                        {book.title}
                    </Link>
                    <p className="text-sm text-[#706f6c] dark:text-[#A1A09A]">
                        {book.author}
                    </p>
                </div>
                <span
                    className={`shrink-0 rounded-full border px-2 py-0.5 text-xs ${
                        book.is_available
                            ? 'border-green-600 text-green-700 dark:text-green-500'
                            : 'border-[#e3e3e0] text-[#706f6c] dark:border-[#3E3E3A] dark:text-[#A1A09A]'
                    }`}
                >
                    {book.is_available
                        ? `${book.available_copies}/${book.total_copies} available`
                        : 'Unavailable'}
                </span>
            </div>
            <p className="mt-2 text-sm">
                <span className="font-medium">Why this matches: </span>
                {book.why}
            </p>
        </li>
    );
}

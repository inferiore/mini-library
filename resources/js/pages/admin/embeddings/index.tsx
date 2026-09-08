import AuthenticatedLayout from '@/layouts/authenticated-layout';
import type {
    PaginatedRagDocuments,
    RagDocumentStatus,
    RagStatusSummary,
} from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';

type Filter = RagDocumentStatus | null;

const STATUSES: RagDocumentStatus[] = [
    'pending',
    'processing',
    'completed',
    'failed',
];

const STATUS_BADGE: Record<RagDocumentStatus, string> = {
    pending: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
    processing: 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300',
    completed:
        'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300',
    failed: 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300',
};

function formatDate(value: string | null): string {
    return value ? new Date(value).toLocaleString() : '—';
}

function StatusBadge({ status }: { status: RagDocumentStatus }) {
    return (
        <span
            className={`rounded-full px-2 py-0.5 text-xs font-medium capitalize ${STATUS_BADGE[status]}`}
        >
            {status}
        </span>
    );
}

export default function EmbeddingsIndex({
    documents,
    filter,
    summary,
}: {
    documents: PaginatedRagDocuments;
    filter: Filter;
    summary: RagStatusSummary;
}) {
    const { flash } = usePage().props;

    const applyFilter = (value: Filter) => {
        router.get(
            '/admin/embeddings',
            value === null ? {} : { status: value },
            { preserveState: true, preserveScroll: true },
        );
    };

    return (
        <AuthenticatedLayout>
            <Head title="Embeddings" />

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

            <h1 className="mb-6 text-xl font-medium">AI / Embeddings</h1>

            <div className="mb-6 flex flex-wrap gap-2 text-sm">
                {STATUSES.map((status) => (
                    <span
                        key={status}
                        className={`rounded-full px-3 py-1 text-xs font-medium capitalize ${STATUS_BADGE[status]}`}
                    >
                        {summary[status]} {status}
                    </span>
                ))}
            </div>

            <div className="mb-6 flex flex-wrap gap-2 text-sm">
                <button
                    onClick={() => applyFilter(null)}
                    className={`rounded-sm border px-3 py-1 ${
                        filter === null
                            ? 'border-[#1b1b18] font-medium dark:border-[#eeeeec]'
                            : 'border-[#e3e3e0] text-[#706f6c] dark:border-[#3E3E3A] dark:text-[#A1A09A]'
                    }`}
                >
                    All
                </button>
                {STATUSES.map((status) => (
                    <button
                        key={status}
                        onClick={() => applyFilter(status)}
                        className={`rounded-sm border px-3 py-1 capitalize ${
                            filter === status
                                ? 'border-[#1b1b18] font-medium dark:border-[#eeeeec]'
                                : 'border-[#e3e3e0] text-[#706f6c] dark:border-[#3E3E3A] dark:text-[#A1A09A]'
                        }`}
                    >
                        {status}
                    </button>
                ))}
            </div>

            <div className="divide-y divide-[#e3e3e0] dark:divide-[#3E3E3A]">
                {documents.data.map((document) => (
                    <div
                        key={document.id}
                        className="flex items-start justify-between gap-4 py-3"
                    >
                        <div className="min-w-0">
                            <div className="flex items-center gap-2">
                                <Link
                                    href={`/admin/embeddings/${document.id}`}
                                    className="font-medium hover:underline"
                                >
                                    {document.book?.title ??
                                        `Document #${document.id}`}
                                </Link>
                                <StatusBadge status={document.status} />
                                {document.book?.trashed && (
                                    <span className="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-900 dark:text-amber-300">
                                        book deleted
                                    </span>
                                )}
                            </div>
                            <p className="mt-1 truncate text-sm text-[#706f6c] dark:text-[#A1A09A]">
                                {document.content_preview}
                            </p>
                            <p className="mt-1 text-xs text-[#706f6c] dark:text-[#A1A09A]">
                                Attempts: {document.attempts} · Processed:{' '}
                                {formatDate(document.processed_at)}
                            </p>
                        </div>
                        <Link
                            href={`/admin/embeddings/${document.id}`}
                            className="shrink-0 rounded-sm border border-[#1b1b18] px-4 py-2 text-sm font-medium hover:bg-black/5 dark:border-[#eeeeec] dark:hover:bg-white/5"
                        >
                            View
                        </Link>
                    </div>
                ))}

                {documents.data.length === 0 && (
                    <p className="py-6 text-sm text-[#706f6c] dark:text-[#A1A09A]">
                        No documents match this filter.
                    </p>
                )}
            </div>

            {documents.last_page > 1 && (
                <div className="mt-6 flex flex-wrap gap-2 text-sm">
                    {documents.links.map((link, i) => (
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

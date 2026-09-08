import AuthenticatedLayout from '@/layouts/authenticated-layout';
import type { RagDocumentDetail, RagDocumentStatus } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/react';

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

function Meta({ label, value }: { label: string; value: string | number }) {
    return (
        <div>
            <dt className="text-xs text-[#706f6c] dark:text-[#A1A09A]">
                {label}
            </dt>
            <dd className="text-sm">{value}</dd>
        </div>
    );
}

export default function EmbeddingsShow({
    document,
}: {
    document: RagDocumentDetail;
}) {
    const { flash } = usePage().props;
    const retryForm = useForm({});
    const regenerateForm = useForm({});

    const retry = () => {
        retryForm.post(`/admin/embeddings/${document.id}/retry`, {
            preserveScroll: true,
        });
    };

    const regenerate = () => {
        regenerateForm.post(`/admin/embeddings/${document.id}/regenerate`, {
            preserveScroll: true,
        });
    };

    return (
        <AuthenticatedLayout>
            <Head title="Embedding Document" />

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

            <Link
                href="/admin/embeddings"
                className="text-sm text-[#706f6c] hover:underline dark:text-[#A1A09A]"
            >
                ← Back to embeddings
            </Link>

            <div className="mt-4 mb-6 flex items-center gap-3">
                <h1 className="text-xl font-medium">
                    {document.book?.title ?? `Document #${document.id}`}
                </h1>
                <span
                    className={`rounded-full px-2 py-0.5 text-xs font-medium capitalize ${STATUS_BADGE[document.status]}`}
                >
                    {document.status}
                </span>
                {document.book?.trashed && (
                    <span className="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-900 dark:text-amber-300">
                        book deleted
                    </span>
                )}
            </div>

            <div className="mb-6 flex flex-wrap gap-3">
                {document.status === 'failed' && (
                    <button
                        onClick={retry}
                        disabled={retryForm.processing}
                        className="rounded-sm border border-[#1b1b18] px-4 py-2 text-sm font-medium hover:bg-black/5 disabled:opacity-50 dark:border-[#eeeeec] dark:hover:bg-white/5"
                    >
                        Retry
                    </button>
                )}
                <button
                    onClick={regenerate}
                    disabled={regenerateForm.processing}
                    className="rounded-sm border border-[#1b1b18] px-4 py-2 text-sm font-medium hover:bg-black/5 disabled:opacity-50 dark:border-[#eeeeec] dark:hover:bg-white/5"
                >
                    Regenerate
                </button>
            </div>

            <dl className="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-3">
                <Meta label="Attempts" value={document.attempts} />
                <Meta
                    label="Provider"
                    value={document.embedding_provider ?? '—'}
                />
                <Meta label="Model" value={document.embedding_model ?? '—'} />
                <Meta
                    label="Processed at"
                    value={formatDate(document.processed_at)}
                />
                <Meta
                    label="Created at"
                    value={formatDate(document.created_at)}
                />
                <Meta
                    label="Updated at"
                    value={formatDate(document.updated_at)}
                />
            </dl>

            {document.status === 'failed' && document.error_message && (
                <div className="mb-6">
                    <h2 className="mb-2 text-sm font-medium">Error</h2>
                    <pre className="overflow-x-auto rounded-sm bg-red-50 p-4 text-sm text-red-700 dark:bg-red-950 dark:text-red-300">
                        {document.error_message}
                    </pre>
                </div>
            )}

            <div>
                <h2 className="mb-2 text-sm font-medium">
                    Content sent for embedding
                </h2>
                <pre className="overflow-x-auto rounded-sm border border-[#e3e3e0] p-4 text-sm whitespace-pre-wrap dark:border-[#3E3E3A]">
                    {document.content}
                </pre>
            </div>
        </AuthenticatedLayout>
    );
}

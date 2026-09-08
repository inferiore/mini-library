import AuthenticatedLayout from '@/layouts/authenticated-layout';
import type { Loan, PaginatedLoans } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/react';

function isOverdue(loan: Loan): boolean {
    return loan.returned_at === null && new Date(loan.due_at) < new Date();
}

function formatDate(value: string): string {
    return new Date(value).toLocaleDateString();
}

export default function MyLoans({ loans }: { loans: PaginatedLoans }) {
    const { flash } = usePage().props;

    return (
        <AuthenticatedLayout>
            <Head title="My Loans" />

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

            <h1 className="mb-6 text-xl font-medium">My Loans</h1>

            <div className="divide-y divide-[#e3e3e0] dark:divide-[#3E3E3A]">
                {loans.data.map((loan) => (
                    <LoanRow key={loan.id} loan={loan} />
                ))}

                {loans.data.length === 0 && (
                    <p className="py-6 text-sm text-[#706f6c] dark:text-[#A1A09A]">
                        You have no loans yet.
                    </p>
                )}
            </div>

            {loans.last_page > 1 && (
                <div className="mt-6 flex flex-wrap gap-2 text-sm">
                    {loans.links.map((link, i) => (
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

function LoanRow({ loan }: { loan: Loan }) {
    const overdue = isOverdue(loan);
    const returnForm = useForm({});

    const returnBook = () => {
        returnForm.put(`/loans/${loan.id}`, { preserveScroll: true });
    };

    return (
        <div className="flex items-center justify-between py-3">
            <div>
                <Link
                    href={`/books/${loan.book_id}`}
                    className="font-medium hover:underline"
                >
                    {loan.book?.title ?? `Book #${loan.book_id}`}
                </Link>
                <p className="text-sm text-[#706f6c] dark:text-[#A1A09A]">
                    Checked out {formatDate(loan.checked_out_at)}
                    {loan.returned_at ? (
                        <> · Returned {formatDate(loan.returned_at)}</>
                    ) : (
                        <>
                            {' · '}
                            <span
                                className={
                                    overdue
                                        ? 'font-medium text-red-600'
                                        : undefined
                                }
                            >
                                Due {formatDate(loan.due_at)}
                                {overdue ? ' (overdue)' : ''}
                            </span>
                        </>
                    )}
                </p>
            </div>

            {!loan.returned_at && (
                <button
                    onClick={returnBook}
                    disabled={returnForm.processing}
                    className="rounded-sm border border-[#1b1b18] px-4 py-2 text-sm font-medium hover:bg-black/5 disabled:opacity-50 dark:border-[#eeeeec] dark:hover:bg-white/5"
                >
                    Return
                </button>
            )}
        </div>
    );
}

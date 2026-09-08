import AuthenticatedLayout from '@/layouts/authenticated-layout';
import type { Book } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import type { FormEventHandler, ReactNode } from 'react';

const inputClass =
    'mt-1 w-full rounded-sm border border-[#e3e3e0] bg-transparent px-3 py-2 text-sm dark:border-[#3E3E3A]';

export default function BookEdit({ book }: { book: Book }) {
    const { data, setData, post, processing, errors } = useForm({
        title: book.title,
        author: book.author,
        isbn: book.isbn ?? '',
        description: book.description ?? '',
        published_year: book.published_year?.toString() ?? '',
        category: book.category ?? '',
        publisher: book.publisher ?? '',
        total_copies: book.total_copies,
        cover_image: null as File | null,
        _method: 'put',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(`/books/${book.id}`, { forceFormData: true });
    };

    return (
        <AuthenticatedLayout>
            <Head title={`Edit ${book.title}`} />

            <h1 className="mb-6 text-xl font-medium">Edit Book</h1>

            <form onSubmit={submit} className="max-w-lg space-y-4">
                <Field label="Title" error={errors.title}>
                    <input
                        type="text"
                        value={data.title}
                        onChange={(e) => setData('title', e.target.value)}
                        className={inputClass}
                    />
                </Field>

                <Field label="Author" error={errors.author}>
                    <input
                        type="text"
                        value={data.author}
                        onChange={(e) => setData('author', e.target.value)}
                        className={inputClass}
                    />
                </Field>

                <Field label="ISBN" error={errors.isbn}>
                    <input
                        type="text"
                        value={data.isbn}
                        onChange={(e) => setData('isbn', e.target.value)}
                        className={inputClass}
                    />
                </Field>

                <Field label="Description" error={errors.description}>
                    <textarea
                        value={data.description}
                        onChange={(e) => setData('description', e.target.value)}
                        className={inputClass}
                        rows={4}
                    />
                </Field>

                <Field label="Published year" error={errors.published_year}>
                    <input
                        type="number"
                        value={data.published_year}
                        onChange={(e) =>
                            setData('published_year', e.target.value)
                        }
                        className={inputClass}
                    />
                </Field>

                <Field label="Category" error={errors.category}>
                    <input
                        type="text"
                        value={data.category}
                        onChange={(e) => setData('category', e.target.value)}
                        className={inputClass}
                    />
                </Field>

                <Field label="Publisher" error={errors.publisher}>
                    <input
                        type="text"
                        value={data.publisher}
                        onChange={(e) => setData('publisher', e.target.value)}
                        className={inputClass}
                    />
                </Field>

                <Field label="Total copies" error={errors.total_copies}>
                    <input
                        type="number"
                        min={0}
                        value={data.total_copies}
                        onChange={(e) =>
                            setData('total_copies', Number(e.target.value))
                        }
                        className={inputClass}
                    />
                    <p className="mt-1 text-xs text-[#706f6c] dark:text-[#A1A09A]">
                        Currently {book.available_copies} of {book.total_copies}{' '}
                        available.
                    </p>
                </Field>

                <Field label="Cover image" error={errors.cover_image}>
                    <input
                        type="file"
                        accept="image/*"
                        onChange={(e) =>
                            setData('cover_image', e.target.files?.[0] ?? null)
                        }
                    />
                </Field>

                <button
                    type="submit"
                    disabled={processing}
                    className="rounded-sm bg-[#1b1b18] px-4 py-2 text-sm text-white hover:bg-black disabled:opacity-50 dark:bg-[#eeeeec] dark:text-[#1C1C1A] dark:hover:bg-white"
                >
                    Save Changes
                </button>
            </form>
        </AuthenticatedLayout>
    );
}

function Field({
    label,
    error,
    children,
}: {
    label: string;
    error?: string;
    children: ReactNode;
}) {
    return (
        <div>
            <label className="mb-1 block text-sm font-medium">{label}</label>
            {children}
            {error && <p className="mt-1 text-sm text-red-600">{error}</p>}
        </div>
    );
}

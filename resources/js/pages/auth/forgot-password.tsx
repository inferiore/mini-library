import { Head, useForm } from '@inertiajs/react';
import type { FormEventHandler } from 'react';

export default function ForgotPassword({ status }: { status?: string }) {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post('/forgot-password');
    };

    return (
        <div className="flex min-h-screen items-center justify-center bg-[#FDFDFC] p-6 text-[#1b1b18] dark:bg-[#0a0a0a] dark:text-[#EDEDEC]">
            <Head title="Forgot password" />

            <div className="w-full max-w-sm space-y-6">
                <h1 className="text-xl font-medium">Forgot your password?</h1>
                <p className="text-sm text-[#706f6c] dark:text-[#A1A09A]">
                    Enter your email and we'll send you a link to reset it.
                </p>

                {status && (
                    <div className="text-sm font-medium text-green-600">
                        {status}
                    </div>
                )}

                <form onSubmit={submit} className="space-y-4">
                    <div>
                        <label
                            htmlFor="email"
                            className="block text-sm font-medium"
                        >
                            Email
                        </label>
                        <input
                            id="email"
                            type="email"
                            value={data.email}
                            autoFocus
                            onChange={(e) => setData('email', e.target.value)}
                            className="mt-1 w-full rounded-sm border border-[#e3e3e0] bg-transparent px-3 py-2 text-sm dark:border-[#3E3E3A]"
                        />
                        {errors.email && (
                            <p className="mt-1 text-sm text-red-600">
                                {errors.email}
                            </p>
                        )}
                    </div>

                    <button
                        type="submit"
                        disabled={processing}
                        className="w-full rounded-sm bg-[#1b1b18] px-4 py-2 text-sm text-white hover:bg-black disabled:opacity-50 dark:bg-[#eeeeec] dark:text-[#1C1C1A]"
                    >
                        Email password reset link
                    </button>
                </form>
            </div>
        </div>
    );
}

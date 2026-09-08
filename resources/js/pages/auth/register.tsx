import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEventHandler } from 'react';

export default function Register() {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post('/register', {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <div className="flex min-h-screen items-center justify-center bg-[#FDFDFC] p-6 text-[#1b1b18] dark:bg-[#0a0a0a] dark:text-[#EDEDEC]">
            <Head title="Register" />

            <div className="w-full max-w-sm space-y-6">
                <h1 className="text-xl font-medium">Create an account</h1>

                <form onSubmit={submit} className="space-y-4">
                    <div>
                        <label
                            htmlFor="name"
                            className="block text-sm font-medium"
                        >
                            Name
                        </label>
                        <input
                            id="name"
                            type="text"
                            value={data.name}
                            autoComplete="name"
                            autoFocus
                            onChange={(e) => setData('name', e.target.value)}
                            className="mt-1 w-full rounded-sm border border-[#e3e3e0] bg-transparent px-3 py-2 text-sm dark:border-[#3E3E3A]"
                        />
                        {errors.name && (
                            <p className="mt-1 text-sm text-red-600">
                                {errors.name}
                            </p>
                        )}
                    </div>

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
                            autoComplete="username"
                            onChange={(e) => setData('email', e.target.value)}
                            className="mt-1 w-full rounded-sm border border-[#e3e3e0] bg-transparent px-3 py-2 text-sm dark:border-[#3E3E3A]"
                        />
                        {errors.email && (
                            <p className="mt-1 text-sm text-red-600">
                                {errors.email}
                            </p>
                        )}
                    </div>

                    <div>
                        <label
                            htmlFor="password"
                            className="block text-sm font-medium"
                        >
                            Password
                        </label>
                        <input
                            id="password"
                            type="password"
                            value={data.password}
                            autoComplete="new-password"
                            onChange={(e) =>
                                setData('password', e.target.value)
                            }
                            className="mt-1 w-full rounded-sm border border-[#e3e3e0] bg-transparent px-3 py-2 text-sm dark:border-[#3E3E3A]"
                        />
                        {errors.password && (
                            <p className="mt-1 text-sm text-red-600">
                                {errors.password}
                            </p>
                        )}
                    </div>

                    <div>
                        <label
                            htmlFor="password_confirmation"
                            className="block text-sm font-medium"
                        >
                            Confirm password
                        </label>
                        <input
                            id="password_confirmation"
                            type="password"
                            value={data.password_confirmation}
                            autoComplete="new-password"
                            onChange={(e) =>
                                setData('password_confirmation', e.target.value)
                            }
                            className="mt-1 w-full rounded-sm border border-[#e3e3e0] bg-transparent px-3 py-2 text-sm dark:border-[#3E3E3A]"
                        />
                        {errors.password_confirmation && (
                            <p className="mt-1 text-sm text-red-600">
                                {errors.password_confirmation}
                            </p>
                        )}
                    </div>

                    <button
                        type="submit"
                        disabled={processing}
                        className="w-full rounded-sm bg-[#1b1b18] px-4 py-2 text-sm text-white hover:bg-black disabled:opacity-50 dark:bg-[#eeeeec] dark:text-[#1C1C1A]"
                    >
                        Register
                    </button>
                </form>

                <p className="text-sm text-[#706f6c] dark:text-[#A1A09A]">
                    Already have an account?{' '}
                    <Link
                        href="/login"
                        className="text-[#f53003] hover:underline dark:text-[#FF4433]"
                    >
                        Log in
                    </Link>
                </p>
            </div>
        </div>
    );
}

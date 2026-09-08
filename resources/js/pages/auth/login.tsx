import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEventHandler } from 'react';

type DemoRole = 'admin' | 'librarian' | 'member';

const DEMO_ROLES: { role: DemoRole; label: string }[] = [
    { role: 'admin', label: 'Continue as Admin' },
    { role: 'librarian', label: 'Continue as Librarian' },
    { role: 'member', label: 'Continue as Member' },
];

export default function Login({
    canResetPassword,
    status,
    demoLoginEnabled,
}: {
    canResetPassword: boolean;
    status?: string;
    demoLoginEnabled: boolean;
}) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post('/login', {
            onFinish: () => reset('password'),
        });
    };

    return (
        <div className="flex min-h-screen items-center justify-center bg-[#FDFDFC] p-6 text-[#1b1b18] dark:bg-[#0a0a0a] dark:text-[#EDEDEC]">
            <Head title="Log in" />

            <div className="w-full max-w-sm space-y-6">
                <h1 className="text-xl font-medium">Log in</h1>

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
                            autoComplete="username"
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
                            autoComplete="current-password"
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

                    <div className="flex items-center justify-between text-sm">
                        <label className="flex items-center gap-2">
                            <input
                                type="checkbox"
                                checked={data.remember}
                                onChange={(e) =>
                                    setData('remember', e.target.checked)
                                }
                            />
                            Remember me
                        </label>

                        {canResetPassword && (
                            <Link
                                href="/forgot-password"
                                className="text-[#f53003] hover:underline dark:text-[#FF4433]"
                            >
                                Forgot password?
                            </Link>
                        )}
                    </div>

                    <button
                        type="submit"
                        disabled={processing}
                        className="w-full rounded-sm bg-[#1b1b18] px-4 py-2 text-sm text-white hover:bg-black disabled:opacity-50 dark:bg-[#eeeeec] dark:text-[#1C1C1A] dark:hover:bg-white"
                    >
                        Log in
                    </button>
                </form>

                <p className="text-sm text-[#706f6c] dark:text-[#A1A09A]">
                    Don't have an account?{' '}
                    <Link
                        href="/register"
                        className="text-[#f53003] hover:underline dark:text-[#FF4433]"
                    >
                        Register
                    </Link>
                </p>

                {demoLoginEnabled && (
                    <div className="space-y-2 border-t border-[#e3e3e0] pt-6 dark:border-[#3E3E3A]">
                        <p className="text-sm font-medium text-[#706f6c] dark:text-[#A1A09A]">
                            Demo accounts
                        </p>
                        <div className="flex flex-col gap-2">
                            {DEMO_ROLES.map(({ role, label }) => (
                                <Link
                                    key={role}
                                    href={`/demo-login/${role}`}
                                    method="post"
                                    as="button"
                                    className="w-full rounded-sm border border-[#e3e3e0] px-4 py-2 text-left text-sm hover:bg-black/5 dark:border-[#3E3E3A] dark:hover:bg-white/5"
                                >
                                    {label}
                                </Link>
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
}

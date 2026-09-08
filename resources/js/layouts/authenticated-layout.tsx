import { Link, usePage } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';

const ROLE_LABELS: Record<string, string> = {
    admin: 'Admin',
    librarian: 'Librarian',
    member: 'Member',
};

export default function AuthenticatedLayout({ children }: PropsWithChildren) {
    const { auth } = usePage().props;
    const user = auth.user;

    return (
        <div className="min-h-screen bg-[#FDFDFC] text-[#1b1b18] dark:bg-[#0a0a0a] dark:text-[#EDEDEC]">
            <nav className="border-b border-[#e3e3e0] dark:border-[#3E3E3A]">
                <div className="mx-auto flex max-w-5xl items-center justify-between px-6 py-4">
                    <div className="flex items-center gap-6">
                        <Link href="/dashboard" className="font-medium">
                            Mini Library
                        </Link>
                        {user && (
                            <div className="flex items-center gap-4 text-sm">
                                <Link href="/books" className="hover:underline">
                                    Books
                                </Link>
                                {user.role === 'member' && (
                                    <Link
                                        href="/my-loans"
                                        className="hover:underline"
                                    >
                                        My Loans
                                    </Link>
                                )}
                                {(user.role === 'admin' ||
                                    user.role === 'librarian') && (
                                    <Link
                                        href="/loans"
                                        className="hover:underline"
                                    >
                                        All Loans
                                    </Link>
                                )}
                            </div>
                        )}
                    </div>

                    {user && (
                        <div className="flex items-center gap-4 text-sm">
                            <span>{user.name}</span>
                            <span className="rounded-full border border-[#e3e3e0] px-2 py-0.5 text-xs text-[#706f6c] dark:border-[#3E3E3A] dark:text-[#A1A09A]">
                                {ROLE_LABELS[user.role] ?? user.role}
                            </span>
                            <Link
                                href="/logout"
                                method="post"
                                as="button"
                                className="text-[#f53003] hover:underline dark:text-[#FF4433]"
                            >
                                Log out
                            </Link>
                        </div>
                    )}
                </div>
            </nav>

            <main className="mx-auto max-w-5xl px-6 py-8">{children}</main>
        </div>
    );
}

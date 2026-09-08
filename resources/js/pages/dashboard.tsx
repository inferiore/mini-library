import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { Head, usePage } from '@inertiajs/react';

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
        </AuthenticatedLayout>
    );
}

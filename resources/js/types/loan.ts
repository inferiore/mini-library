import type { Book } from '@/types/book';
import type { User } from '@/types/auth';

export type Loan = {
    id: number;
    book_id: number;
    user_id: number;
    checked_out_at: string;
    due_at: string;
    returned_at: string | null;
    created_at: string;
    updated_at: string;
    book?: Book;
    user?: User;
    is_overdue?: boolean;
};

export type PaginatedLoans = {
    data: Loan[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
};

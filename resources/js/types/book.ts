export type Book = {
    id: number;
    title: string;
    author: string;
    isbn: string | null;
    description: string | null;
    published_year: number | null;
    category: string | null;
    publisher: string | null;
    cover_path: string | null;
    total_copies: number;
    available_copies: number;
    created_at: string;
    updated_at: string;
};

export type PaginatedBooks = {
    data: Book[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
};

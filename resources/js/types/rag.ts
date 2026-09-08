export type RagDocumentStatus =
    | 'pending'
    | 'processing'
    | 'completed'
    | 'failed';

export type RagDocumentBook = {
    id: number;
    title: string;
    trashed: boolean;
};

export type RagDocumentListItem = {
    id: number;
    status: RagDocumentStatus;
    embedding_provider: string | null;
    embedding_model: string | null;
    attempts: number;
    processed_at: string | null;
    content_preview: string;
    book: RagDocumentBook | null;
};

export type RagDocumentDetail = {
    id: number;
    status: RagDocumentStatus;
    content: string;
    error_message: string | null;
    embedding_provider: string | null;
    embedding_model: string | null;
    attempts: number;
    processed_at: string | null;
    created_at: string | null;
    updated_at: string | null;
    book: RagDocumentBook | null;
};

export type PaginatedRagDocuments = {
    data: RagDocumentListItem[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
};

export type RagStatusSummary = Record<RagDocumentStatus, number>;

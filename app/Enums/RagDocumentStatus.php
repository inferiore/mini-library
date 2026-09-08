<?php

namespace App\Enums;

/**
 * Lifecycle of a rag_documents row's embedding (see docs/specs/007-rag.md):
 * pending (content written, embedding not yet generated) → processing
 * (GenerateBookEmbedding is running) → completed (vector stored) | failed
 * (embedding generation exhausted its retries).
 */
enum RagDocumentStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
}

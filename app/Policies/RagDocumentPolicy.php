<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\RagDocument;
use App\Models\User;

/**
 * Embedding internals are an ADMIN-only concern (spec 009). Unlike the catalog,
 * which LIBRARIANs co-manage, the RAG pipeline's raw documents/status are never
 * exposed to LIBRARIAN or MEMBER — every ability here is admin-gated.
 */
class RagDocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function view(User $user, RagDocument $document): bool
    {
        return $this->isAdmin($user);
    }

    /**
     * Retry a failed embedding. Admin-only; the failed-status precondition is
     * enforced in the controller (a retry on a non-failed doc is a validation
     * rejection, not an authorization failure).
     */
    public function retry(User $user, RagDocument $document): bool
    {
        return $this->isAdmin($user);
    }

    /**
     * Regenerate an embedding from any status. Admin-only.
     */
    public function regenerate(User $user, RagDocument $document): bool
    {
        return $this->isAdmin($user);
    }

    private function isAdmin(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }
}

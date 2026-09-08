<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Book;
use App\Models\User;

class BookPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Book $book): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $this->isCatalogManager($user);
    }

    public function update(User $user, Book $book): bool
    {
        return $this->isCatalogManager($user);
    }

    public function delete(User $user, Book $book): bool
    {
        return $this->isCatalogManager($user);
    }

    /**
     * Adjust total_copies (spec 004). Same audience as update today, but named
     * distinctly so a future spec can split the permission without touching the
     * general edit path.
     */
    public function adjustInventory(User $user, Book $book): bool
    {
        return $this->isCatalogManager($user);
    }

    private function isCatalogManager(User $user): bool
    {
        return in_array($user->role, [UserRole::Admin, UserRole::Librarian], true);
    }
}

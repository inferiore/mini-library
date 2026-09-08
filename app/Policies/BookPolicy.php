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

    private function isCatalogManager(User $user): bool
    {
        return in_array($user->role, [UserRole::Admin, UserRole::Librarian], true);
    }
}

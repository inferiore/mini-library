<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Loan;
use App\Models\User;

class LoanPolicy
{
    /**
     * View all loans system-wide (the "All Loans" index) — staff only.
     */
    public function viewAny(User $user): bool
    {
        return $this->isStaff($user);
    }

    /**
     * View a single loan: the owning member, or staff for any loan.
     */
    public function view(User $user, Loan $loan): bool
    {
        return $loan->user_id === $user->id || $this->isStaff($user);
    }

    /**
     * Check out a book — MEMBER only. Librarians/admins manage the catalog and
     * don't personally borrow in this model (spec 005 authorization flag).
     */
    public function create(User $user): bool
    {
        return $user->role === UserRole::Member;
    }

    /**
     * Return a loan: the loan's own borrower, or staff on their behalf
     * (desk-assisted returns).
     */
    public function return(User $user, Loan $loan): bool
    {
        return $loan->user_id === $user->id || $this->isStaff($user);
    }

    private function isStaff(User $user): bool
    {
        return in_array($user->role, [UserRole::Admin, UserRole::Librarian], true);
    }
}

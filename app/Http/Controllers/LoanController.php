<?php

namespace App\Http\Controllers;

use App\Exceptions\AlreadyBorrowedException;
use App\Exceptions\BookUnavailableException;
use App\Exceptions\LoanAlreadyReturnedException;
use App\Http\Requests\StoreLoanRequest;
use App\Models\Loan;
use App\Services\LoanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class LoanController extends Controller
{
    public function __construct(private readonly LoanService $loans) {}

    /**
     * All loans, system-wide (staff only). Scoped by the viewAny policy, and
     * filterable by active/overdue/returned. Role scoping lives in the query
     * path (this endpoint is staff-only), not just in the UI.
     */
    public function index(): Response
    {
        Gate::authorize('viewAny', Loan::class);

        $filter = request('filter', 'all');

        $loans = Loan::query()
            ->with(['book', 'user'])
            ->when($filter === 'active', fn ($q) => $q->active())
            ->when($filter === 'overdue', fn ($q) => $q->overdue())
            ->when($filter === 'returned', fn ($q) => $q->whereNotNull('returned_at'))
            ->orderByDesc('checked_out_at')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('loans/index', [
            'loans' => $loans,
            'filter' => $filter,
        ]);
    }

    /**
     * The current user's own loan history ("My Loans"). Scoped to the actor in
     * the query itself so a member can never see another member's loans.
     */
    public function myLoans(): Response
    {
        $loans = Loan::query()
            ->with('book')
            ->where('user_id', request()->user()->id)
            ->orderByDesc('checked_out_at')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('loans/my-loans', [
            'loans' => $loans,
        ]);
    }

    /**
     * Check out a book (MEMBER only, enforced by the Form Request's authorize).
     * Domain rejections are surfaced as flash errors, not 500s.
     */
    public function store(StoreLoanRequest $request): RedirectResponse
    {
        $book = $request->book();

        try {
            $this->loans->checkout($book, $request->user());
        } catch (AlreadyBorrowedException|BookUnavailableException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('books.show', $book)->with('status', 'Book checked out.');
    }

    /**
     * Return a loan (the borrower, or staff on their behalf).
     */
    public function update(Loan $loan): RedirectResponse
    {
        Gate::authorize('return', $loan);

        try {
            $this->loans->return($loan);
        } catch (LoanAlreadyReturnedException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Book returned.');
    }
}

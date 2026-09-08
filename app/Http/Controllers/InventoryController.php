<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientAvailableCopiesException;
use App\Http\Requests\AdjustInventoryRequest;
use App\Models\Book;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class InventoryController extends Controller
{
    public function __construct(private readonly InventoryService $inventory) {}

    /**
     * Adjust a book's total_copies. Kept on a dedicated, invariant-sensitive
     * endpoint (not the general book-edit form) per spec 004. Thin: authorize
     * via the Form Request, delegate to the service, and surface the
     * insufficient-copies case as a validation error rather than a 500.
     */
    public function update(AdjustInventoryRequest $request, Book $book): RedirectResponse
    {
        try {
            $this->inventory->adjustTotalCopies($book, $request->integer('total_copies'));
        } catch (InsufficientAvailableCopiesException $e) {
            throw ValidationException::withMessages([
                'total_copies' => $e->getMessage(),
            ]);
        }

        return redirect()->route('books.show', $book)->with('status', 'Inventory updated.');
    }
}

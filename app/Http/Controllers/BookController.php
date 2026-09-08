<?php

namespace App\Http\Controllers;

use App\Exceptions\BookHasActiveLoansException;
use App\Http\Requests\StoreBookRequest;
use App\Http\Requests\UpdateBookRequest;
use App\Models\Book;
use App\Services\BookService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class BookController extends Controller
{
    public function __construct(private readonly BookService $books) {}

    public function index(): Response
    {
        return Inertia::render('books/index', [
            'books' => Book::query()
                ->orderBy('title')
                ->paginate(20)
                ->withQueryString(),
        ]);
    }

    public function show(Book $book): Response
    {
        return Inertia::render('books/show', [
            'book' => $book,
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Book::class);

        return Inertia::render('books/create');
    }

    public function store(StoreBookRequest $request): RedirectResponse
    {
        $book = $this->books->create($this->attributesFromRequest($request));

        return redirect()->route('books.show', $book)->with('status', 'Book created.');
    }

    public function edit(Book $book): Response
    {
        Gate::authorize('update', $book);

        return Inertia::render('books/edit', [
            'book' => $book,
        ]);
    }

    public function update(UpdateBookRequest $request, Book $book): RedirectResponse
    {
        $this->books->update($book, $this->attributesFromRequest($request));

        return redirect()->route('books.show', $book)->with('status', 'Book updated.');
    }

    public function destroy(Book $book): RedirectResponse
    {
        Gate::authorize('delete', $book);

        try {
            $this->books->delete($book);
        } catch (BookHasActiveLoansException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('books.index')->with('status', 'Book deleted.');
    }

    /**
     * @param  StoreBookRequest|UpdateBookRequest  $request
     * @return array<string, mixed>
     */
    private function attributesFromRequest($request): array
    {
        $attributes = $request->safe()->except('cover_image');

        if ($request->hasFile('cover_image')) {
            $attributes['cover_path'] = $request->file('cover_image')->store('covers', 'public');
        }

        return $attributes;
    }
}

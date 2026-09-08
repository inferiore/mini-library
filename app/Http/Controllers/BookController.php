<?php

namespace App\Http\Controllers;

use App\Exceptions\BookHasActiveLoansException;
use App\Http\Requests\SearchBooksRequest;
use App\Http\Requests\StoreBookRequest;
use App\Http\Requests\UpdateBookRequest;
use App\Models\Book;
use App\Services\BookSearchService;
use App\Services\BookService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class BookController extends Controller
{
    public function __construct(
        private readonly BookService $books,
        private readonly BookSearchService $search,
    ) {}

    public function index(SearchBooksRequest $request): Response
    {
        $query = $request->string('query')->trim()->value();
        $category = $request->string('category')->trim()->value();
        $publisher = $request->string('publisher')->trim()->value();

        // Delegate to the search service when any search/filter param is
        // present; otherwise fall back to spec 003's plain paginated listing.
        $hasSearch = $query !== '' || $category !== '' || $publisher !== '';

        $books = $hasSearch
            ? $this->search->search($query, $category ?: null, $publisher ?: null)
            : Book::query()->orderBy('title')->paginate(20)->withQueryString();

        return Inertia::render('books/index', [
            'books' => $books,
            'filters' => [
                'query' => $query,
                'category' => $category,
                'publisher' => $publisher,
            ],
            'categories' => Book::query()
                ->whereNotNull('category')
                ->distinct()
                ->orderBy('category')
                ->pluck('category'),
            'publishers' => Book::query()
                ->whereNotNull('publisher')
                ->distinct()
                ->orderBy('publisher')
                ->pluck('publisher'),
        ]);
    }

    public function show(Book $book): Response
    {
        // The current user's active (unreturned) loan for this book, if any —
        // drives the Check Out vs. Return action on the detail page (spec 005).
        $activeLoan = $book->loans()
            ->where('user_id', request()->user()->id)
            ->whereNull('returned_at')
            ->first();

        return Inertia::render('books/show', [
            'book' => $book,
            'activeLoan' => $activeLoan,
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

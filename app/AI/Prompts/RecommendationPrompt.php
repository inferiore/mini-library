<?php

namespace App\AI\Prompts;

class RecommendationPrompt
{
    /**
     * Build the grounded recommendation prompt. Every book the model is allowed
     * to recommend is listed inline with its catalog Book ID; the instructions
     * forbid recommending anything not in that list, so the model can't invent
     * a title/author that isn't in the actual catalog (spec 008 FR3).
     *
     * The model is asked for strict JSON keyed by Book ID so parsing never
     * relies on fragile text scraping and the service can re-query each book's
     * live availability by ID rather than trusting the model's output.
     *
     * @param  array<int, array{title: string, author: string, content: string}>  $candidates  keyed by book id
     */
    public function build(string $query, array $candidates): string
    {
        $catalog = '';

        foreach ($candidates as $id => $book) {
            $catalog .= sprintf(
                "- Book ID %d | Title: \"%s\" | Author: %s\n  Content: %s\n",
                $id,
                $book['title'],
                $book['author'],
                $book['content'],
            );
        }

        return <<<PROMPT
        You are a library recommendation assistant. Recommend books to the reader
        based ONLY on the catalog listed below. You must never invent, guess, or
        recommend a title or author that is not present in that list, and you must
        only ever reference a book by one of the exact Book IDs provided.

        For each book you recommend, write a short, specific explanation of why it
        matches the reader's request, grounded in that book's content above. Order
        your recommendations from most to least relevant. If none of the listed
        books are a reasonable match, return an empty recommendations array.

        Respond with STRICT JSON only — no prose, no markdown fences — in exactly
        this shape:
        {"recommendations":[{"book_id":<one of the Book IDs below>,"reason":"<why it matches>"}]}

        Reader's request:
        {$query}

        Available books (the ONLY books you may recommend):
        {$catalog}
        PROMPT;
    }
}

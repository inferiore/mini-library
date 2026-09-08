<?php

namespace Database\Factories;

use App\Models\Book;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Book>
 */
class BookFactory extends Factory
{
    private const array CATEGORIES = [
        'Fiction', 'Non-Fiction', 'Science Fiction', 'Fantasy', 'Biography',
        'Technical', 'History', 'Mystery', 'Self-Help', 'Poetry',
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $totalCopies = fake()->numberBetween(1, 10);

        return [
            'title' => fake()->unique()->sentence(rand(2, 5)),
            'author' => fake()->name(),
            'isbn' => fake()->unique()->isbn13(),
            'description' => fake()->paragraph(),
            'published_year' => fake()->numberBetween(1950, (int) date('Y')),
            'category' => fake()->randomElement(self::CATEGORIES),
            'publisher' => fake()->company(),
            'cover_path' => null,
            'total_copies' => $totalCopies,
            'available_copies' => $totalCopies,
        ];
    }

    /**
     * Some, but not all, copies are currently checked out.
     */
    public function partiallyBorrowed(): static
    {
        return $this->state(function (array $attributes) {
            $total = $attributes['total_copies'];

            return [
                'available_copies' => $total > 1 ? fake()->numberBetween(1, $total - 1) : 0,
            ];
        });
    }

    /**
     * Every copy is currently checked out.
     */
    public function fullyBorrowed(): static
    {
        return $this->state(fn (array $attributes) => [
            'available_copies' => 0,
        ]);
    }
}

<?php

namespace Database\Factories;

use App\Models\Book;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Loan>
 */
class LoanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $checkedOutAt = now()->subDays(fake()->numberBetween(0, 10));

        return [
            'book_id' => Book::factory(),
            'user_id' => User::factory(),
            'checked_out_at' => $checkedOutAt,
            'due_at' => $checkedOutAt->copy()->addDays(config('library.loan_period_days')),
            'returned_at' => null,
        ];
    }

    /**
     * A loan that has been returned.
     */
    public function returned(): static
    {
        return $this->state(fn (array $attributes) => [
            'returned_at' => now(),
        ]);
    }

    /**
     * An active loan already past its due date.
     */
    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'checked_out_at' => now()->subDays(30),
            'due_at' => now()->subDays(16),
            'returned_at' => null,
        ]);
    }
}

<?php

namespace Database\Factories;

use App\Enums\RagDocumentStatus;
use App\Models\RagDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RagDocument>
 */
class RagDocumentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'content' => fake()->paragraph(),
            'status' => RagDocumentStatus::Pending,
            'embedding' => null,
            'embedding_provider' => null,
            'embedding_model' => null,
            'error_message' => null,
            'attempts' => 0,
            'processed_at' => null,
        ];
    }

    /**
     * A completed document with a stored embedding vector.
     *
     * @param  array<int, float>|null  $embedding
     */
    public function completed(?array $embedding = null): static
    {
        return $this->state(fn (): array => [
            'status' => RagDocumentStatus::Completed,
            'embedding' => $embedding !== null ? json_encode(array_values($embedding)) : null,
            'embedding_provider' => 'test',
            'embedding_model' => 'fake-embedding',
            'processed_at' => now(),
        ]);
    }

    public function failed(string $message = 'embedding failed'): static
    {
        return $this->state(fn (): array => [
            'status' => RagDocumentStatus::Failed,
            'error_message' => $message,
            'attempts' => 3,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (): array => [
            'status' => RagDocumentStatus::Pending,
        ]);
    }

    public function processing(): static
    {
        return $this->state(fn (): array => [
            'status' => RagDocumentStatus::Processing,
            'attempts' => 1,
        ]);
    }
}

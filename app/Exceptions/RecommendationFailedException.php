<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Thrown when the recommendation pipeline cannot produce a usable result —
 * e.g. the LLM's response can't be parsed as the expected structured format.
 * The controller translates this into a clean user-facing error so a raw
 * parse/HTTP exception never leaks to the client (spec 008 edge cases).
 */
class RecommendationFailedException extends RuntimeException
{
    public function __construct(
        string $message = 'We could not generate recommendations right now. Please try again.',
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, previous: $previous);
    }
}

<?php

namespace App\Http\Controllers;

use App\Exceptions\RecommendationFailedException;
use App\Http\Requests\RecommendationRequest;
use App\Services\RecommendationService;
use Illuminate\Http\JsonResponse;

class RecommendationController extends Controller
{
    public function __construct(
        private readonly RecommendationService $recommendations,
    ) {}

    /**
     * Return grounded AI recommendations as JSON for the dashboard widget's
     * client-side fetch. Thin: validate -> service -> JSON. Rate limiting lives
     * on the route (spec 008 non-functional requirements).
     */
    public function store(RecommendationRequest $request): JsonResponse
    {
        $query = $request->string('query')->value();

        try {
            $result = $this->recommendations->recommend($query);
        } catch (RecommendationFailedException $e) {
            // A clean, generic message — never the underlying parse/HTTP detail.
            return response()->json(['message' => $e->getMessage()], 502);
        }

        return response()->json($result);
    }
}

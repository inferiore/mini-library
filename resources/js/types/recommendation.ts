export type Recommendation = {
    id: number;
    title: string;
    author: string;
    why: string;
    available_copies: number;
    total_copies: number;
    is_available: boolean;
};

export type RecommendationResult = {
    status: 'ok' | 'no_matches';
    recommendations: Recommendation[];
};

<?php

namespace App\Http\Controllers\Seeker;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Services\RecommendationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ListingAvailabilityController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, RecommendationService $recommendations): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:50'],
            'ids.*' => ['required', 'integer', 'min:1', 'distinct'],
        ]);
        $listings = $recommendations->visibleTo($request->user())->where('status', 'published')
            ->whereKey($data['ids'])->get();

        return response()->json(['listings' => $listings->mapWithKeys(fn (Listing $listing): array => [
            $listing->id => $listing->availability(),
        ])])->header('Cache-Control', 'private, no-store, max-age=0');
    }
}

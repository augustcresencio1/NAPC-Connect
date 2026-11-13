<?php

namespace App\Http\Controllers\Seeker;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Services\Seeker\ListingStateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ChecklistController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, Listing $listing, ListingStateService $states): RedirectResponse
    {
        $states->visibleListing($request->user(), $listing);
        $data = $request->validate([
            'checked' => ['nullable', 'array', 'max:100'],
            'checked.*' => ['string', 'distinct', Rule::in(array_keys($listing->documentItems()))],
        ]);
        $states->update($request->user(), $listing, ['checked_documents' => json_encode(array_values($data['checked'] ?? []))]);

        return back()->with('status', __('Checklist saved.'));
    }
}

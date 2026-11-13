<?php

namespace App\Http\Controllers\Seeker;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Services\RecommendationService;
use App\Services\Seeker\ListingStateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SavedListingController extends Controller
{
    public function index(Request $request, ListingStateService $states, RecommendationService $recommendations): View
    {
        $listings = $states->saved($request->user())->with('user:id,organization')
            ->orderByDesc('seeker_state.saved_at')->orderBy('listings.id')->paginate(9);
        $listings->getCollection()->each(fn (Listing $listing) => $recommendations->explain($listing));

        return view('seeker.saved.index', compact('listings'));
    }

    public function store(Request $request, Listing $listing, ListingStateService $states): RedirectResponse
    {
        $states->visibleListing($request->user(), $listing);
        $states->update($request->user(), $listing, ['saved_at' => now()]);

        return back()->with('status', __('Listing saved.'));
    }

    public function destroy(Request $request, Listing $listing): RedirectResponse
    {
        $removed = DB::table('seeker_listing_states')->where('user_id', $request->user()->id)->where('listing_id', $listing->id)->whereNotNull('saved_at')
            ->update(['saved_at' => null, 'reminder_read_deadline' => null]);
        abort_unless($removed > 0, 404);

        return back()->with('status', __('Listing removed from saved.'));
    }
}

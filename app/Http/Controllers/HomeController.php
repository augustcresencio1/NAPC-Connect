<?php

namespace App\Http\Controllers;

use App\Services\RecommendationService;
use App\Services\Seeker\ListingStateService;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    /** /dashboard sends each role to its own home page */
    public function dashboard(Request $request)
    {
        return redirect()->route($request->user()->homeRoute());
    }

    public function seeker(Request $request, RecommendationService $recommendations, ListingStateService $states)
    {
        $user = $request->user();

        return view('seeker.home', [
            'completion' => $user->seekerProfile?->completion() ?? 0,
            'recommended' => $recommendations->forUser($user),
            'closingSoon' => $states->closingSoon($user)->with('user:id,organization')->orderBy('deadline')->orderBy('id')->limit(6)->get()
                ->each(fn ($listing) => $recommendations->explain($listing)),
        ]);
    }
}

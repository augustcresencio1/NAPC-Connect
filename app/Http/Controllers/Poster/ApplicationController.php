<?php

namespace App\Http\Controllers\Poster;

use App\Http\Controllers\Controller;
use App\Http\Requests\Poster\UpdateApplicationStatusRequest;
use App\Models\Application;
use App\Models\Listing;
use App\Services\Poster\AcceptedApplicationsExport;
use App\Services\RecommendationService;
use App\Services\TicketService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ApplicationController extends Controller
{
    public function index(Request $request, Listing $listing, RecommendationService $recommendations): View
    {
        $this->authorizeListing($request, $listing);

        $status = in_array($request->query('status'), Application::FILTER_STATUSES, true) ? $request->query('status') : 'pending';
        $totals = $listing->applications()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $counts = collect(Application::FILTER_STATUSES)->mapWithKeys(fn (string $key): array => [$key => (int) ($totals[$key] ?? 0)])->all();

        $applications = $listing->applications()
            ->where('status', $status)
            ->with('user.seekerProfile')
            ->latest()
            ->paginate(15)->withQueryString();

        $matchScores = $recommendations->applicantScores($listing, $applications->getCollection());

        return view('poster.listings.applications', compact('listing', 'applications', 'matchScores', 'status', 'counts'));
    }

    public function update(UpdateApplicationStatusRequest $request, Listing $listing, Application $application, TicketService $tickets): RedirectResponse
    {
        $this->authorizeListing($request, $listing);
        abort_unless((int) $application->listing_id === (int) $listing->id && $listing->apply_mode === 'form', 404);
        $tickets->decide($application, $request->validated('status'));

        return redirect()->route('poster.listings.applications', [
            'listing' => $listing, 'status' => $request->validated('status'),
        ])->with('status', 'Application updated.');
    }

    public function export(Request $request, Listing $listing, AcceptedApplicationsExport $export): StreamedResponse
    {
        $this->authorizeListing($request, $listing);
        abort_unless($listing->apply_mode === 'form', 404);

        return $export->download($listing);
    }

    public function review(Request $request, Listing $listing, Application $application, TicketService $tickets): RedirectResponse
    {
        $this->authorizeListing($request, $listing);
        abort_unless((int) $application->listing_id === (int) $listing->id && $listing->apply_mode === 'form', 404);
        $tickets->review($application);

        return back()->with('status', __('Application marked as reviewed.'));
    }

    private function authorizeListing(Request $request, Listing $listing): void
    {
        abort_unless($request->user()?->role === 'poster' && $request->user()->poster_status === 'approved'
            && (int) $listing->user_id === (int) $request->user()->id, 404);
    }
}

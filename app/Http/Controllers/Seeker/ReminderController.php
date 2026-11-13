<?php

namespace App\Http\Controllers\Seeker;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Services\Seeker\ListingStateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReminderController extends Controller
{
    public function index(Request $request, ListingStateService $states): View
    {
        $reminders = $states->reminders($request->user())->with('user:id,organization')
            ->orderBy('deadline')->orderBy('listings.id')->paginate(15);

        return view('seeker.reminders.index', compact('reminders'));
    }

    public function update(Request $request, Listing $listing, ListingStateService $states): RedirectResponse
    {
        $reminder = $states->reminders($request->user())->whereKey($listing->id)->firstOrFail();
        $states->update($request->user(), $listing, ['reminder_read_deadline' => $reminder->deadline->toDateString()]);

        return back()->with('status', __('Reminder marked as read.'));
    }
}

<?php

namespace App\Http\Controllers\Seeker;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Services\TicketService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TicketController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, Application $application, TicketService $tickets): Response
    {
        abort_unless($request->user()?->role === 'seeker'
            && (int) $application->user_id === (int) $request->user()->id
            && $application->hasTicket() && $application->listing->apply_mode === 'form', 404);

        return $tickets->download($application);
    }
}

<?php

namespace App\Services;

use App\Models\Application;
use App\Models\Listing;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class TicketService
{
    /** A row lock makes repeated acceptance idempotent and keeps status and ticket atomic. */
    public function decide(Application $application, string $status): Application
    {
        return DB::transaction(function () use ($application, $status): Application {
            $listing = Listing::query()->whereKey($application->listing_id)->lockForUpdate()->firstOrFail();
            $locked = Application::query()->whereKey($application->id)->lockForUpdate()->firstOrFail();
            abort_if($locked->status === 'withdrawn', 409, __('This application has been withdrawn.'));
            if ($status === 'accepted' && $locked->status !== 'accepted'
                && $listing->acceptance_limit !== null
                && $listing->acceptedApplications()->count() >= $listing->acceptance_limit) {
                throw ValidationException::withMessages(['status' => __('All acceptance slots are filled. Increase the limit or free a slot before accepting another applicant.')]);
            }
            if ($locked->status !== $status) {
                $locked->recordActivity($status);
            }
            if ($status === 'accepted') {
                if (! $locked->hasTicket()) {
                    $locked->ticket_code = 'NAPC-'.strtoupper((string) Str::uuid());
                    $locked->accepted_at = now();
                }
            } else {
                $locked->ticket_code = null;
                $locked->accepted_at = null;
            }
            if ($status === 'pending') {
                $locked->reviewed_at = null;
            }
            $locked->status = $status;
            $locked->save();

            return $locked;
        }, 3);
    }

    public function review(Application $application): void
    {
        DB::transaction(function () use ($application): void {
            $locked = Application::query()->whereKey($application->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'pending', 409, __('This application is no longer pending.'));
            if (! $locked->reviewed_at) {
                $locked->recordActivity('reviewed');
                $locked->reviewed_at = now();
                $locked->save();
            }
        }, 3);
    }

    public function withdraw(Application $application): void
    {
        DB::transaction(function () use ($application): void {
            $locked = Application::query()->whereKey($application->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'pending', 409, __('This application is no longer pending.'));
            $locked->recordActivity('withdrawn');
            $locked->status = 'withdrawn';
            $locked->withdrawn_at = now();
            $locked->ticket_code = null;
            $locked->accepted_at = null;
            $locked->save();
        }, 3);
    }

    public function qrCode(string $code): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle(240, 4), new SvgImageBackEnd));

        return 'data:image/svg+xml;base64,'.base64_encode($writer->writeString($code));
    }

    public function download(Application $application): Response
    {
        $application->loadMissing(['listing.user', 'user']);

        return Pdf::loadView('seeker.tickets.show', [
            'application' => $application,
            'listing' => $application->listing,
            'qrCode' => $this->qrCode($application->ticket_code),
        ])->setPaper('a4')->setOptions([
            'isRemoteEnabled' => false,
            'isPhpEnabled' => false,
            'isJavascriptEnabled' => false,
            'defaultFont' => 'DejaVu Sans',
        ], true)->download('ticket-'.$application->ticket_code.'.pdf')
            ->header('Cache-Control', 'private, no-store, max-age=0');
    }
}

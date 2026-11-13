<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Listing;
use App\Models\User;
use App\Services\TicketService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ApplicationDecisionsTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo('2026-11-13 12:00:00');
    }

    private function listing(array $attributes = [], ?User $owner = null): Listing
    {
        $owner ??= User::factory()->create(['role' => 'poster', 'poster_status' => 'approved', 'organization' => 'Samahan ng Bayan']);

        return $owner->listings()->create(array_merge([
            'title' => 'Tulong para sa komunidad', 'description' => 'Community assistance',
            'type' => 'ayuda', 'status' => 'published', 'published_at' => now(), 'apply_mode' => 'form',
            'details' => ['venue' => 'Barangay Hall', 'schedule' => 'November 20, 2026, 9:00 AM', 'documents' => "Valid ID\nApplication copy"],
        ], $attributes));
    }

    private function application(?Listing $listing = null, ?User $seeker = null, string $status = 'pending'): Application
    {
        $listing ??= $this->listing();
        $seeker ??= User::factory()->create(['role' => 'seeker', 'first_name' => 'María', 'last_name' => 'Peña']);

        return $listing->applications()->create(['user_id' => $seeker->id, 'status' => $status, 'answers' => ['reason' => 'Kailangan ng tulong']]);
    }

    public function test_acceptance_creates_ticket_and_repeated_acceptance_keeps_it(): void
    {
        $application = $this->application();
        $url = route('poster.applications.update', [$application->listing, $application]);

        $this->actingAs($application->listing->user)->patch($url, ['status' => 'accepted', 'ticket_code' => 'FORGED'])
            ->assertRedirect(route('poster.listings.applications', ['listing' => $application->listing, 'status' => 'accepted']));
        $application->refresh();
        $this->assertSame('accepted', $application->status);
        $this->assertMatchesRegularExpression('/^NAPC-[0-9A-F-]{36}$/', $application->ticket_code);
        $this->assertSame('2026-11-13 12:00:00', $application->accepted_at->format('Y-m-d H:i:s'));
        $code = $application->ticket_code;
        $acceptedAt = $application->accepted_at->toIso8601String();
        $this->travel(1)->hours();

        $this->patch($url, ['status' => 'accepted'])->assertRedirect();
        $application->refresh();
        $this->assertSame($code, $application->ticket_code);
        $this->assertSame($acceptedAt, $application->accepted_at->toIso8601String());
    }

    public static function reversalStatuses(): array
    {
        return ['pending' => ['pending'], 'rejected' => ['rejected']];
    }

    #[DataProvider('reversalStatuses')]
    public function test_reversal_disables_ticket_and_reacceptance_issues_new_code(string $status): void
    {
        $application = app(TicketService::class)->decide($this->application(), 'accepted');
        $oldCode = $application->ticket_code;
        $url = route('poster.applications.update', [$application->listing, $application]);

        $this->actingAs($application->listing->user)->patch($url, ['status' => $status])->assertRedirect();
        $this->assertDatabaseHas('applications', ['id' => $application->id, 'status' => $status, 'ticket_code' => null, 'accepted_at' => null]);
        $this->actingAs($application->user)->get(route('seeker.applications.ticket', $application))->assertNotFound();

        $this->travel(1)->days();
        $this->actingAs($application->listing->user)->patch($url, ['status' => 'accepted'])->assertRedirect();
        $this->assertNotSame($oldCode, $application->fresh()->ticket_code);
        $this->assertSame('2026-11-14', $application->fresh()->accepted_at->toDateString());
    }

    public static function invalidStatuses(): array
    {
        return ['missing' => [null], 'old submitted' => ['submitted'], 'unknown' => ['approved']];
    }

    #[DataProvider('invalidStatuses')]
    public function test_invalid_status_does_not_change_application(?string $status): void
    {
        $application = $this->application();

        $this->actingAs($application->listing->user)->patchJson(route('poster.applications.update', [$application->listing, $application]), ['status' => $status])
            ->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->assertDatabaseHas('applications', ['id' => $application->id, 'status' => 'pending', 'ticket_code' => null]);
    }

    public function test_status_tabs_have_counts_and_confirmation_forms(): void
    {
        $pending = $this->application();
        $listing = $pending->listing;
        $this->application($listing, status: 'accepted');
        $this->application($listing, status: 'accepted');
        $this->application($listing, status: 'rejected');

        $this->actingAs($listing->user)->get(route('poster.listings.applications', $listing))->assertOk()
            ->assertViewHas('counts', ['pending' => 1, 'accepted' => 2, 'rejected' => 1, 'withdrawn' => 0])
            ->assertSee('Accept this application and issue a ticket?')->assertSee('Download accepted (CSV)')
            ->assertViewHas('applications', fn ($rows) => $rows->total() === 1 && $rows->first()->id === $pending->id);
        $this->get(route('poster.listings.applications', ['listing' => $listing, 'status' => 'accepted']))->assertOk()
            ->assertViewHas('applications', fn ($rows) => $rows->total() === 2);
    }

    public static function deniedPosters(): array
    {
        return ['unapproved' => ['poster', 'pending'], 'seeker' => ['seeker', null], 'admin' => ['admin', null]];
    }

    #[DataProvider('deniedPosters')]
    public function test_decisions_and_exports_require_approved_poster_role(string $role, ?string $posterStatus): void
    {
        $user = User::factory()->create(['role' => $role, 'poster_status' => $posterStatus]);
        $application = $this->application($this->listing([], $user));

        $this->actingAs($user)->patch(route('poster.applications.update', [$application->listing, $application]), ['status' => 'accepted'])->assertNotFound();
        $this->get(route('poster.applications.export', $application->listing))->assertNotFound();
        $this->assertDatabaseHas('applications', ['id' => $application->id, 'status' => 'pending', 'ticket_code' => null]);
    }

    public function test_other_poster_and_wrong_listing_cannot_modify_or_export_applications(): void
    {
        $application = $this->application();
        $otherListing = $this->listing();

        $this->actingAs($otherListing->user)->patch(route('poster.applications.update', [$application->listing, $application]), ['status' => 'accepted'])->assertNotFound();
        $this->get(route('poster.applications.export', $application->listing))->assertNotFound();
        $this->patch(route('poster.applications.update', [$otherListing, $application]), ['status' => 'accepted'])->assertNotFound();
        $this->assertDatabaseHas('applications', ['id' => $application->id, 'status' => 'pending']);
    }

    public static function externalModes(): array
    {
        return ['link' => ['link'], 'info' => ['info']];
    }

    #[DataProvider('externalModes')]
    public function test_external_modes_have_no_decisions_exports_or_tickets(string $mode): void
    {
        $application = $this->application($this->listing(['apply_mode' => $mode]));

        $this->actingAs($application->listing->user)->patch(route('poster.applications.update', [$application->listing, $application]), ['status' => 'accepted'])->assertNotFound();
        $this->get(route('poster.applications.export', $application->listing))->assertNotFound();
        $application = app(TicketService::class)->decide($application, 'accepted');
        $this->actingAs($application->user)->get(route('seeker.applications.ticket', $application))->assertNotFound();
        $this->get(route('seeker.listings.show', $application->listing))->assertOk()
            ->assertSee('tickets are only available')->assertDontSee('Download ticket (PDF)');
    }

    public function test_csv_contains_only_accepted_applicants_with_utf8_profile_answers_and_ticket(): void
    {
        $application = $this->application($this->listing(['application_form' => [
            ['key' => 'reason', 'type' => 'text', 'label' => 'Dahilan'],
            ['key' => 'items', 'type' => 'checkbox', 'label' => 'Mga dokumento'],
        ]]));
        $application->user->seekerProfile()->create(['birthdate' => '2000-01-02', 'income_monthly' => 0, 'school_name' => 'Pamantasan ng Maynila', 'sectors' => ['pwd']]);
        $application->update(['answers' => ['reason' => '=HYPERLINK("bad")', 'items' => ['ID', 'Barangay clearance']]]);
        $application = app(TicketService::class)->decide($application, 'accepted');
        $this->application($application->listing, User::factory()->create(['role' => 'seeker', 'first_name' => 'PendingOnly']));
        $this->application($application->listing, status: 'rejected');
        app(TicketService::class)->decide($this->application(), 'accepted');

        $response = $this->actingAs($application->listing->user)->get(route('poster.applications.export', $application->listing))
            ->assertOk()->assertDownload('listing-'.$application->listing_id.'-accepted.csv')->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $csv = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, substr($csv, 3));
        rewind($stream);
        $headers = fgetcsv($stream, escape: '');
        $row = array_combine($headers, fgetcsv($stream, escape: ''));
        $this->assertFalse(fgetcsv($stream, escape: ''));
        fclose($stream);
        $this->assertSame('María Peña', $row['Name']);
        $this->assertSame('Pamantasan ng Maynila', $row['School name']);
        $this->assertSame('0', $row['Monthly household income (PHP)']);
        $this->assertSame("'=HYPERLINK(\"bad\")", $row['Dahilan']);
        $this->assertSame('ID; Barangay clearance', $row['Mga dokumento']);
        $this->assertSame($application->ticket_code, $row['Ticket code']);
        $this->assertSame($application->accepted_at->toIso8601String(), $row['Accepted at (ISO 8601)']);
    }

    public function test_empty_export_is_a_header_only_csv(): void
    {
        $listing = $this->listing();

        $response = $this->actingAs($listing->user)->get(route('poster.applications.export', $listing))->assertOk();

        $this->assertSame(1, substr_count($response->streamedContent(), "\r\n"));
    }

    public function test_accepted_seeker_downloads_pdf_from_both_pages(): void
    {
        $application = app(TicketService::class)->decide($this->application(), 'accepted');

        $this->actingAs($application->user)->get(route('seeker.applications.index'))->assertOk()->assertSee('Accepted')->assertSee('Download ticket (PDF)');
        $this->get(route('seeker.listings.show', $application->listing))->assertOk()->assertSee('Download ticket (PDF)');
        $response = $this->get(route('seeker.applications.ticket', $application))->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')->assertDownload('ticket-'.$application->ticket_code.'.pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public static function unavailableTickets(): array
    {
        return ['pending' => ['pending'], 'rejected' => ['rejected'], 'accepted without code' => ['accepted']];
    }

    #[DataProvider('unavailableTickets')]
    public function test_ticket_is_not_available_without_current_acceptance_and_code(string $status): void
    {
        $application = $this->application(status: $status);

        $this->actingAs($application->user)->get(route('seeker.applications.ticket', $application))->assertNotFound();
        $this->get(route('seeker.applications.index'))->assertOk()->assertDontSee('Download ticket (PDF)');
    }

    public function test_other_seeker_and_poster_cannot_download_ticket(): void
    {
        $application = app(TicketService::class)->decide($this->application(), 'accepted');
        $other = User::factory()->create(['role' => 'seeker']);

        $this->actingAs($other)->get(route('seeker.applications.ticket', $application))->assertNotFound();
        $this->actingAs($application->listing->user)->get(route('seeker.applications.ticket', $application))->assertNotFound();
    }

    public function test_ticket_remains_available_after_profile_or_listing_changes(): void
    {
        $application = app(TicketService::class)->decide($this->application(), 'accepted');
        $application->listing->update(['status' => 'closed', 'sectors' => ['pwd']]);

        $this->actingAs($application->user)->get(route('seeker.applications.ticket', $application))->assertOk();
        $this->get(route('seeker.listings.show', $application->listing))->assertNotFound();
    }

    public function test_guest_cannot_decide_export_or_download_tickets(): void
    {
        $application = $this->application();

        $this->patch(route('poster.applications.update', [$application->listing, $application]), ['status' => 'accepted'])->assertRedirect(route('login'));
        $this->get(route('poster.applications.export', $application->listing))->assertRedirect(route('login'));
        $this->get(route('seeker.applications.ticket', $application))->assertRedirect(route('login'));
    }

    public function test_migration_converts_existing_submitted_applications_to_pending(): void
    {
        $application = $this->application(status: 'submitted');
        $migration = require database_path('migrations/2026_11_13_143414_add_decisions_and_tickets_to_applications_table.php');
        $migration->down();

        $migration->up();

        $this->assertDatabaseHas('applications', ['id' => $application->id, 'status' => 'pending', 'ticket_code' => null, 'accepted_at' => null]);
        $this->assertSame('pending', DB::table('applications')->where('id', $application->id)->value('status'));
    }
}

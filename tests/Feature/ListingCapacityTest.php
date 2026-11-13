<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Listing;
use App\Models\User;
use App\Services\TicketService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ListingCapacityTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo('2026-11-13 12:00:00');
    }

    private function listing(?int $limit = 2): Listing
    {
        return User::factory()->create(['role' => 'poster', 'poster_status' => 'approved'])->listings()->create([
            'title' => 'Community program', 'description' => 'Support for the community', 'type' => 'ayuda',
            'status' => 'published', 'published_at' => now(), 'apply_mode' => 'form', 'acceptance_limit' => $limit,
        ]);
    }

    private function application(Listing $listing): Application
    {
        return $listing->applications()->create(['user_id' => User::factory()->create(['role' => 'seeker'])->id]);
    }

    private function payload(mixed $limit = 2): array
    {
        return [
            'action' => 'save', 'type' => 'ayuda', 'title' => 'Community program', 'description' => 'Support for the community',
            'apply_mode' => 'form', 'acceptance_limit' => $limit,
            'questions' => [['key' => 'q_abcdef', 'label' => 'Reason', 'type' => 'text', 'required' => false]],
        ];
    }

    public function test_poster_sets_and_edits_an_acceptance_limit(): void
    {
        $owner = User::factory()->create(['role' => 'poster', 'poster_status' => 'approved']);

        $this->actingAs($owner)->post(route('poster.listings.store'), $this->payload(5))->assertRedirect();
        $listing = $owner->listings()->firstOrFail();
        $this->assertSame(5, $listing->acceptance_limit);
        $this->get(route('poster.listings.edit', $listing))->assertOk()->assertSee('Maximum accepted applicants');
        $this->put(route('poster.listings.update', $listing), $this->payload(8))->assertRedirect();
        $this->assertSame(8, $listing->fresh()->acceptance_limit);
        $this->put(route('poster.listings.update', $listing), $this->payload(null))->assertRedirect();
        $this->assertNull($listing->fresh()->acceptance_limit);
    }

    public static function invalidLimits(): array
    {
        return ['zero' => [0], 'negative' => [-1], 'decimal' => [1.5], 'text' => ['many'], 'too large' => [1000001]];
    }

    #[DataProvider('invalidLimits')]
    public function test_invalid_limits_are_rejected(mixed $limit): void
    {
        $listing = $this->listing();

        $this->actingAs($listing->user)->putJson(route('poster.listings.update', $listing), $this->payload($limit))
            ->assertUnprocessable()->assertJsonValidationErrors('acceptance_limit');
        $this->assertSame(2, $listing->fresh()->acceptance_limit);
    }

    public function test_acceptance_cannot_exceed_limit_even_with_stale_application_models(): void
    {
        $listing = $this->listing(1);
        $first = $this->application($listing);
        $second = $this->application($listing);
        $url = route('poster.applications.update', [$listing, $second]);

        $this->actingAs($listing->user)->patch(route('poster.applications.update', [$listing, $first]), ['status' => 'accepted'])->assertRedirect();
        $this->patchJson($url, ['status' => 'accepted'])->assertUnprocessable()->assertJsonValidationErrors('status');

        $this->assertSame(1, $listing->acceptedApplications()->count());
        $this->assertDatabaseHas('applications', ['id' => $second->id, 'status' => 'pending', 'ticket_code' => null, 'accepted_at' => null]);
        $this->assertNull($second->fresh()->activity);
        $this->patch(route('poster.applications.update', [$listing, $first]), ['status' => 'accepted'])->assertRedirect();
        $this->assertSame(1, $listing->acceptedApplications()->count());
    }

    public static function releasingStatuses(): array
    {
        return ['pending' => ['pending'], 'rejected' => ['rejected']];
    }

    #[DataProvider('releasingStatuses')]
    public function test_reversing_acceptance_releases_a_slot(string $status): void
    {
        $listing = $this->listing(1);
        $first = app(TicketService::class)->decide($this->application($listing), 'accepted');
        $second = $this->application($listing);

        $this->actingAs($listing->user)->patch(route('poster.applications.update', [$listing, $first]), ['status' => $status])->assertRedirect();
        $this->patch(route('poster.applications.update', [$listing, $second]), ['status' => 'accepted'])->assertRedirect();

        $this->assertSame('accepted', $second->fresh()->status);
        $this->assertNull($first->fresh()->ticket_code);
        $this->assertSame(0, $listing->availability()['remaining']);
    }

    public function test_lowering_limit_below_accepted_count_fails_without_changing_listing(): void
    {
        $listing = $this->listing(3);
        app(TicketService::class)->decide($this->application($listing), 'accepted');
        app(TicketService::class)->decide($this->application($listing), 'accepted');

        $this->actingAs($listing->user)->putJson(route('poster.listings.update', $listing), $this->payload(1))
            ->assertUnprocessable()->assertJsonValidationErrors('acceptance_limit');
        $this->assertSame(3, $listing->fresh()->acceptance_limit);
        $this->put(route('poster.listings.update', $listing), $this->payload(2))->assertRedirect();
        $this->assertSame(2, $listing->fresh()->acceptance_limit);
    }

    public function test_unlimited_existing_listings_and_pending_applications_do_not_consume_slots(): void
    {
        $listing = $this->listing(null);
        $first = $this->application($listing);
        app(TicketService::class)->decide($first, 'accepted');
        app(TicketService::class)->decide($this->application($listing), 'accepted');
        $this->application($listing);

        $this->assertSame(['limit' => null, 'accepted' => 2, 'remaining' => null, 'label' => 'No acceptance limit set'], $listing->availability());
        $limited = $this->listing(3);
        $this->application($limited);
        $this->assertSame(3, $limited->availability()['remaining']);
    }

    public function test_live_endpoint_reports_fresh_counts_after_decisions_without_applicant_information(): void
    {
        $listing = $this->listing(2);
        $application = $this->application($listing);
        $seeker = $application->user;
        $url = route('seeker.listings.availability', ['ids' => [$listing->id]]);

        $this->actingAs($seeker)->getJson($url)->assertOk()
            ->assertJsonPath('listings.'.$listing->id.'.remaining', 2);
        app(TicketService::class)->decide($application, 'accepted');
        $response = $this->getJson($url)->assertOk()->assertJsonPath('listings.'.$listing->id.'.remaining', 1)
            ->assertJsonPath('listings.'.$listing->id.'.accepted', 1);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertSame(['limit', 'accepted', 'remaining', 'label'], array_keys($response->json('listings.'.$listing->id)));
        $response->assertDontSee($seeker->email);
        app(TicketService::class)->decide($application, 'pending');
        $this->getJson($url)->assertOk()->assertJsonPath('listings.'.$listing->id.'.remaining', 2);
        $listing->update(['acceptance_limit' => 4]);
        $this->getJson($url)->assertOk()->assertJsonPath('listings.'.$listing->id.'.remaining', 4);
    }

    public function test_live_endpoint_excludes_unpublished_and_ineligible_listings(): void
    {
        $user = User::factory()->create(['role' => 'seeker']);
        $open = $this->listing();
        $restricted = $this->listing();
        $restricted->update(['sectors' => ['pwd']]);
        $draft = $this->listing();
        $draft->update(['status' => 'draft']);

        $this->actingAs($user)->getJson(route('seeker.listings.availability', ['ids' => [$open->id, $restricted->id, $draft->id, 999999]]))
            ->assertOk()->assertJsonCount(1, 'listings')->assertJsonPath('listings.'.$open->id.'.remaining', 2);
        $open->update(['status' => 'closed']);
        $this->getJson(route('seeker.listings.availability', ['ids' => [$open->id]]))->assertOk()->assertJsonCount(0, 'listings');
    }

    public function test_live_endpoint_validates_batch_size_and_identifiers(): void
    {
        $user = User::factory()->create(['role' => 'seeker']);

        $this->actingAs($user)->getJson(route('seeker.listings.availability', ['ids' => range(1, 51)]))
            ->assertUnprocessable()->assertJsonValidationErrors('ids');
        $this->getJson(route('seeker.listings.availability', ['ids' => ['1 OR 1=1']]))
            ->assertUnprocessable()->assertJsonValidationErrors('ids.0');
    }

    public function test_live_endpoint_requires_seeker_role(): void
    {
        $listing = $this->listing();
        $url = route('seeker.listings.availability', ['ids' => [$listing->id]]);

        $this->getJson($url)->assertUnauthorized();
        $this->actingAs($listing->user)->getJson($url)->assertNotFound();
    }

    public function test_other_organization_cannot_edit_capacity(): void
    {
        $listing = $this->listing();
        $other = User::factory()->create(['role' => 'poster', 'poster_status' => 'approved']);

        $this->actingAs($other)->putJson(route('poster.listings.update', $listing), $this->payload(100))->assertNotFound();
        $this->assertSame(2, $listing->fresh()->acceptance_limit);
    }

    public function test_seeker_cards_and_detail_have_initial_availability_in_both_languages(): void
    {
        $user = User::factory()->create(['role' => 'seeker']);
        $listing = $this->listing(5);
        app(TicketService::class)->decide($this->application($listing), 'accepted');

        $this->actingAs($user)->get(route('seeker.listings.index'))->assertOk()->assertSee('4 of 5 slots left');
        $this->get(route('seeker.listings.show', $listing))->assertOk()->assertSee('4 of 5 slots left')->assertSee('slotAvailability');
        $this->put(route('seeker.preferences.update'), ['locale' => 'fil']);
        $this->getJson(route('seeker.listings.availability', ['ids' => [$listing->id]]))->assertOk()
            ->assertJsonPath('listings.'.$listing->id.'.label', '4 sa 5 puwesto ang natitira');
    }

    public function test_external_forms_do_not_advertise_a_live_slot_count(): void
    {
        $listing = $this->listing();
        $listing->update(['apply_mode' => 'info', 'how_to_apply' => 'Visit our office']);
        $user = User::factory()->create(['role' => 'seeker']);

        $this->actingAs($user)->get(route('seeker.listings.show', $listing))->assertOk()->assertDontSee('slotAvailability');
        $this->actingAs($listing->user)->put(route('poster.listings.update', $listing), [
            ...$this->payload(7), 'apply_mode' => 'info', 'how_to_apply' => 'Visit our office',
        ])->assertRedirect();
        $this->assertNull($listing->fresh()->acceptance_limit);
    }

    public static function linklessActions(): array
    {
        return ['draft without link' => ['save', null, 'draft'], 'publish without link' => ['publish', '', 'published']];
    }

    #[DataProvider('linklessActions')]
    public function test_poster_can_save_or_publish_without_any_link(string $action, ?string $url, string $status): void
    {
        $owner = User::factory()->create(['role' => 'poster', 'poster_status' => 'approved']);

        $this->actingAs($owner)->post(route('poster.listings.store'), [
            ...$this->payload(), 'action' => $action, 'apply_mode' => 'link', 'apply_url' => $url,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('listings', ['user_id' => $owner->id, 'status' => $status, 'source_url' => null, 'apply_url' => null]);
    }

    public function test_poster_can_clear_link_and_seekers_see_no_empty_application_button(): void
    {
        $listing = $this->listing();
        $listing->update(['apply_mode' => 'link', 'apply_url' => 'https://example.com/apply']);

        $this->actingAs($listing->user)->put(route('poster.listings.update', $listing), [
            ...$this->payload(), 'apply_mode' => 'link', 'apply_url' => '',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertNull($listing->fresh()->apply_url);
        $this->assertSame('published', $listing->fresh()->status);
        $seeker = User::factory()->create(['role' => 'seeker']);
        $this->actingAs($seeker)->get(route('seeker.listings.show', $listing))->assertOk()
            ->assertSee('No application link has been provided.')->assertDontSee('Open the application form')->assertDontSee('href=""', false);
    }

    public function test_optional_links_still_require_valid_http_or_https_urls_when_supplied(): void
    {
        $listing = $this->listing();

        $this->actingAs($listing->user)->putJson(route('poster.listings.update', $listing), [
            ...$this->payload(), 'apply_mode' => 'link', 'apply_url' => 'javascript:alert(1)', 'source_url' => 'not a url',
        ])->assertUnprocessable()->assertJsonValidationErrors(['apply_url', 'source_url']);

        $this->assertNull($listing->fresh()->apply_url);
        $this->assertNull($listing->fresh()->source_url);
    }
}
